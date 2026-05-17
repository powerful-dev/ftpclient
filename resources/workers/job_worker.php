<?php

require __DIR__ . '/../../vendor/autoload.php';

$workerName = $argv[1];

use App\Workers\Core\WorkerCore;
use App\Models\Connection;
use App\Services\ConnectionService;
use League\Flysystem\Filesystem;
use App\Helpers\PathHelper;
use App\Services\FileCopyService;
use App\Services\FileDeleteService;
use App\Services\FileMoveService;
use App\Services\FileDownloadService;
use App\Services\FileOpenerService;
use League\Flysystem\FileAttributes;
use App\Helpers\FileHelper;
use App\DTO\ConnectionContext;
use App\Support\Runtime;
use App\Exceptions\TaskCancelledException;
use App\Exceptions\TaskPausedException;
use App\Exceptions\ConflictException;
use App\Enums\TaskStatus;
use App\Helpers\TaskHelper;
use App\Services\EventService;


class JobWorker extends WorkerCore
{

    private array $connections = []; 
    private string $commandsDir;
    private string $resultsDir;
    private string $tasksDir;
    private string $locksDir;

    /**
     * Counts consecutive idle loops when no command files are found.
     * Used to progressively increase the worker sleep time and reduce CPU usage
     * while the command queue is empty.
     */
    private int $idleLoops = 0;

    /**
     * Tracks the last usage timestamp for each connection.
     *
     * Key: connection_id
     * Value: unix timestamp (time of last activity)
     *
     * Used to automatically close idle connections after a TTL.
     */
    private array $connectionLastUsed = [];

    public function __construct(string $workerName)
    {
        parent::__construct($workerName);

        foreach ([
            'commandsDir' => 'commands',
            'resultsDir'  => 'results',
            'tasksDir'    => 'tasks',
            'eventsDir'   => 'events',
            'locksDir'    => 'locks',
            'cacheDir'    => 'cache',
        ] as $property => $dir) {
            $path = Runtime::path($dir);
            $this->{$property} = $path;

            if (!is_dir($path)) {
                mkdir($path, 0777, true);
            }
        }

        // restore commands that were in processing state after crash
        foreach (glob($this->commandsDir . '/*.processing') as $file) {
            $original = substr($file, 0, -11); // remove ".processing"
            @rename($file, $original);
        }
    }

    private ?string $lastLocale = null;

    private function setLocale(string $locale): void
    {
        if ($this->lastLocale !== $locale) {
            app()->setLocale($locale);
            $this->lastLocale = $locale;
        }
    }

    protected function loop(): void
    {
        $this->cleanupIdleConnections();

        try {

            $files = array_slice(glob($this->commandsDir . '/*.json'), 0, 1);

            if (!$files) {
                $this->idleLoops++;
                return;
            }

            $this->idleLoops = 0;

            $grouped = [];
            $myId = getmypid();

            foreach ($files as $file) {

                if (!is_file($file)) {
                    continue;
                }

                $processing = $file . '.' . $myId . '.processing';

                // Atomic lock: only one worker can take the command
                if (!@rename($file, $processing)) {
                    continue;
                }

                $json = false;

                // Windows filesystem race protection:
                // file may exist after rename but still be unreadable for a moment
                for ($i = 0; $i < 5; $i++) {

                    clearstatcache(true, $processing);

                    $json = @file_get_contents($processing);

                    if ($json !== false && $json !== '') {
                        break;
                    }

                    usleep(20_000); // 20ms
                }

                if ($json === false || $json === '') {

                    $this->log("Failed to read file: {$processing}");

                    // Return command back to queue
                    $original = preg_replace(
                        '/\.\d+\.processing$/',
                        '',
                        $processing
                    );

                    @rename($processing, $original);

                    continue;
                }

                $command = json_decode($json, true);

                // JSON may still be partially written
                if (json_last_error() !== JSON_ERROR_NONE) {

                    $this->log(
                        "Invalid JSON in {$processing}: " .
                        json_last_error_msg()
                    );

                    // Return back to queue for retry
                    $original = preg_replace(
                        '/\.\d+\.processing$/',
                        '',
                        $processing
                    );

                    @rename($processing, $original);

                    continue;
                }

                if (!is_array($command) || empty($command['type'])) {

                    $this->log("Invalid command structure: {$processing}");

                    if (is_file($processing)) {
                        @unlink($processing);
                    }
                    continue;
                }

                $connectionId = $command['connection_id'] ?? null;
                $groupKey = $connectionId ?? 'local';

                $grouped[$groupKey][] = [
                    'file' => $processing,
                    'command' => $command
                ];
            }

            foreach ($grouped as $connectionKey => $commands) {

                $lockHandle = null;

                try {

                    // =========================
                    // Acquire slot
                    // =========================
                    if ($connectionKey !== 'local') {

                        $slot = $this->acquireConnectionSlot(
                            (int)$connectionKey,
                            2
                        );

                        if (!$slot) {

                            // Return commands back to queue
                            foreach ($commands as $item) {

                                $processing = $item['file'];

                                $original = preg_replace(
                                    '/\.\d+\.processing$/',
                                    '',
                                    $processing
                                );

                                @rename($processing, $original);
                            }

                            continue;
                        }

                        [$lockHandle] = $slot;
                    }

                    $fsConnectionId = $connectionKey === 'local'
                        ? null
                        : (int)$connectionKey;

                    if ($fsConnectionId !== null) {
                        $this->getConnection($fsConnectionId);
                    }

                    // =========================
                    // Execute commands
                    // =========================
                    foreach ($commands as $item) {

                        $file = $item['file'];
                        $command = $item['command'];

                        $this->setLocale(
                            $command['locale'] ?? config('app.locale')
                        );

                        $result = match ($command['type']) {

                            'list' => $this->handleListCommand($command),

                            'init_copy' => $this->handleInitCopy($command),
                            'copy' => $this->handleCopy($command),

                            'init_delete' => $this->handleInitDelete($command),
                            'delete' => $this->handleDelete($command),

                            'init_move' => $this->handleInitMove($command),
                            'move' => $this->handleMove($command),

                            'download' => $this->handleDownload($command),
                            'open' => $this->handleOpen($command),

                            'resume' => $this->handleResume($command),

                            'mkdir' => $this->handleCreateDirectory($command),

                            'create_file' => $this->handleCreateFile($command),

                            default => false
                        };

                        if ($result !== false) {

                            if (is_file($file)) {
                                @unlink($file);
                            }
                        }
                    }

                } catch (\Throwable $e) {

                    // =========================
                    // Connection / execution error
                    // =========================
                    $this->log(
                        "Connection {$connectionKey} failed: " .
                        $e->getMessage()
                    );

                    foreach ($commands as $item) {

                        $file = $item['file'];
                        $command = $item['command'];

                        if (is_file($file)) {
                            @unlink($file);
                        }

                        $connLabel = $command['connection_id'] ?? 'local';

                        $fileName =
                            "{$command['type']}_{$connLabel}_" .
                            "{$command['operation_id']}.json";

                        $this->writeResult($fileName, [
                            'ok' => false,
                            'data' => null,
                            'error' => "Connection failed: " . $e->getMessage()
                        ]);
                    }

                } finally {

                    // =========================
                    // Always release slot
                    // =========================
                    if ($lockHandle) {
                        $this->releaseConnectionSlot($lockHandle);
                    }
                }
            }

        } catch (\Throwable $e) {

            $this->log("WORKER CRASH: " . $e->getMessage());
        }
    }

    private function handleInit(
        array $command,
        string $field,
        callable $calculator
    ): void {
        $taskId = $command['task_id'];

        $this->executeCommand(
            "{$command['type']}_{$taskId}.json",
            $taskId,
            function () use ($command, $taskId, $field, $calculator) {

                $file = $this->tasksDir . "/{$taskId}.json";

                if (!file_exists($file)) {
                    return false;
                }

                $raw = file_get_contents($file);
                $task = json_decode($raw, true) ?: [];

                // already initialized → skip
                if (!empty($task[$field])) {
                    return true;
                }

                $task[$field] = $calculator($command['files']);

                file_put_contents($file, json_encode($task, JSON_UNESCAPED_UNICODE), LOCK_EX);

                return true;
            }
        );
    }

    private function handleInitCopy(array $command): void
    {

        $this->handleInit(
            $command,
            'total_bytes',
            fn($files) => FileHelper::calculateTotalBytes($files)
        );
    }

    private function runWithConnection(
        array $command,
        callable $callback
    ): mixed {
        $connectionId = $command['connection_id'] ?? null;

        $ctx = null;

        if (!empty($connectionId)) {
            $ctx = $this->getConnectionContext($connectionId);

            /**
             * Updates the last usage timestamp for the given connection.
             *
             * Should be called whenever a connection is actively used,
             * ensuring that it is not considered idle and prematurely closed.
             */
            $this->connectionLastUsed[$connectionId] = time();
        }

        return $callback($ctx);
    }

    private function resultFileName(array $command): string
    {
        $connLabel = $command['connection_id'] ?? 'local';

        return "{$command['type']}_{$connLabel}_{$command['operation_id']}.json";
    }

    private function handleCopy(array $command): void
    {
        $taskId = $command['task_id'];
        $task = TaskHelper::read($this->tasksDir . "/{$taskId}.json") ?? [];

        if (empty($task['total_bytes'])) {
            throw new \RuntimeException('Total bytes not initialized yet');
        }

        $this->executeCommand(
            $this->resultFileName($command),
            $taskId,
            function () use ($command, $task) {

                return $this->copy(
                    $task,
                    $command['payload']['files'],
                    $command['payload']['destination'],
                    $command['payload']['options'] ?? [],
                    $command
                );
            }
        );
    }

    private function handleResume(array $command): void
    {
        $taskId = $command['task_id'];
        $task = TaskHelper::read($this->tasksDir . "/{$taskId}.json") ?? [];

        if (
            $task['type'] === 'copy' &&
            empty($task['total_bytes'])
        ) {
            throw new \RuntimeException(
                'Total bytes not initialized yet'
            );
        }

        TaskHelper::update($taskId, function (&$task) {
            if (!empty($task['paused_at'])) {

                $task['total_paused_seconds'] +=
                    time() - $task['paused_at'];

                $task['paused_at'] = null;
            }

            $task['status'] = TaskStatus::RUNNING->value;
        });

        $files = $task['files'] ?? [];

        $options = [
            ...($task['options'] ?? []),
            'file_decisions' => $task['file_decisions'] ?? [],
        ];

        $destination = dirname($task['to']);
        $source = dirname($task['from']);

        $this->executeCommand(
            $this->resultFileName($command),
            $taskId,
            function () use (
                $task,
                $files,
                $destination,
                $source,
                $options,
                $command
            ) {

                return match ($task['type']) {

                    'copy' => $this->copy(
                        $task,
                        $files,
                        $destination,
                        $options,
                        $command
                    ),

                    'download' => $this->download(
                        $task,
                        $files,
                        $destination,
                        $options,
                        $command
                    ),

                    'move' => $this->move(
                        $task,
                        $files,
                        $destination,
                        $source,
                        $options,
                        $command
                    ),

                    default => $this->log("unsuported type: " . $task['type']),
                };
            }
        );
    }

    private function copy(array $task, array $files, string $destination, array $options, array $command): bool
    {
        return $this->runWithConnection($task, function ($ctx) use ($task, $files, $destination, $options, $command) {

            try {

                app(FileCopyService::class)->copy(
                    $files,
                    $destination,
                    $task['id'],
                    $ctx,
                    $options
                );

                return true;

            } catch (ConflictException $e) {

                app(EventService::class)->emit([
                    'event' => 'conflict',
                    'task_id' => $command['task_id'],
                    'operation_id' => $command['operation_id'],
                    'source' => $e->getSource(),
                    'destination' => $e->getDestination(),
                ]);

                throw new TaskPausedException('Conflict detected');
            }
        });
    }

    private function handleInitDelete(array $command): void
    {

        $this->handleInit(
            $command,
            'total_items',
            fn($files) => count($files)
        );
    }

    private function handleDelete(array $command): void
    {
        $this->executeCommand(
            $this->resultFileName($command), 
            $command['task_id'] ?? null, 
            function () use ($command) {

                return $this->runWithConnection($command, function ($ctx) use ($command) {

                    app(FileDeleteService::class)->delete(
                        $command['payload']['files'],
                        $command['payload']['source'],
                        $command['task_id'],
                        $ctx?->fs,
                    );

                    return true;
                });
        });
    }

    private function handleInitMove(array $command)
    {
       $this->handleInit(
            $command,
            'total_items',
            fn($files) => FileHelper::calculateTotalFiles($files)
        );
    }

    private function handleMove(array $command): void
    {
        $taskId = $command['task_id'];

        $task = TaskHelper::read(
            $this->tasksDir . "/{$taskId}.json"
        ) ?? [];

        $this->executeCommand(
            $this->resultFileName($command),
            $taskId,

            function () use ($command, $task) {

                return $this->move(
                    $task,
                    $command['payload']['files'],
                    $command['payload']['destination'],
                    $command['payload']['source'],
                    $command['payload']['options'] ?? [],
                    $command
                );
            }
        );
    }

    private function move(
        array $task,
        array $files,
        string $destination,
        string $source,
        array $options,
        array $command
    ): bool {

        return $this->runWithConnection(
            $task,

            function ($ctx) use (
                $task,
                $files,
                $destination,
                $source,
                $options,
                $command
            ) {

                try {

                    app(FileMoveService::class)->move(
                        $files,
                        $destination,
                        $source,
                        $task['id'],
                        $ctx?->fs,
                        $options
                    );

                    return true;

                } catch (ConflictException $e) {

                    app(EventService::class)->emit([
                        'event' => 'conflict',
                        'task_id' => $command['task_id'],
                        'operation_id' => $command['operation_id'],
                        'source' => $e->getSource(),
                        'destination' => $e->getDestination(),
                    ]);

                    throw new TaskPausedException(
                        'Conflict detected'
                    );
                }
            }
        );
    }

    private function handleDownload(array $command): void
    {

        if (empty(($command['connection_id'] ?? null))) {
            throw new \RuntimeException('Empty connection!');
        }

        $taskId = $command['task_id'];

        $task = TaskHelper::read(
            $this->tasksDir . "/{$taskId}.json"
        ) ?? [];

        $this->executeCommand(
            $this->resultFileName($command),
            $taskId,

            function () use ($command, $task) {

                return $this->download(
                    $task,
                    $command['payload']['files'],
                    $command['payload']['destination'],
                    $command['payload']['options'] ?? [],
                    $command
                );
            }
        );
    }

    private function download(
        array $task,
        array $files,
        string $destination,
        array $options,
        array $command
    ): bool {

        return $this->runWithConnection(
            $task,
            function ($ctx) use (
                $task,
                $files,
                $destination,
                $options,
                $command
            ) {

                try {

                    app(FileDownloadService::class)->download(
                        $files,
                        $destination,
                        $task['id'],
                        $ctx?->fs,
                        $options
                    );

                    return true;

                } catch (ConflictException $e) {

                    app(EventService::class)->emit([
                        'event' => 'conflict',
                        'task_id' => $command['task_id'],
                        'operation_id' => $command['operation_id'],
                        'source' => $e->getSource(),
                        'destination' => $e->getDestination(),
                    ]);

                    throw new TaskPausedException(
                        'Conflict detected'
                    );
                }
            }
        );
    }

    private function handleOpen(array $command): void
    {
        

        $this->executeCommand(
            $this->resultFileName($command), 
            $command['task_id'] ?? null, 
            function () use ($command) {

                return $this->runWithConnection($command, function ($ctx) use ($command) {

                    $files = $command['payload']['files'] ?? [];

                    if (count($files) !== 1) {
                        throw new \RuntimeException('Open operation supports only one file');
                    }

                    $file = $files[0];

                    app(FileOpenerService::class)->open(
                        $file,
                        $command['payload']['options']['openInExplorer'] ?? false,
                        $command['task_id'],
                        $ctx?->fs,
                    );

                    return true;
                });
        });
    }

    private function handleCreateDirectory(array $command): bool
    {
        $path = $command['payload']['path'];

        return $this->runWithConnection(
            $command,
            function ($ctx) use ($path, $command) {

                try {

                    if ($ctx?->fs) {

                        if ($ctx->fs->directoryExists(
                            PathHelper::encode($path)
                        )) {
                            throw new RuntimeException(
                                'Directory already exists'
                            );
                        }

                        $ctx->fs->createDirectory(
                            PathHelper::encode($path)
                        );

                    } else {

                        if (is_dir($path)) {
                            throw new RuntimeException(
                                'Directory already exists'
                            );
                        }

                        if (!mkdir($path, 0777, true)) {
                            throw new RuntimeException(
                                'Failed to create directory'
                            );
                        }
                    }

                    // Notify UI about successful filesystem update
                    app(EventService::class)->emit([
                        'event' => 'dir.created',
                        'type' => 'mkdir',
                        'panel' => $command['panel'] ?? null,
                        'path' => dirname($path),
                        'target' => $path,
                        'connection_id' => $command['connection_id'] ?? null,
                    ]);

                    return true;

                } catch (\Throwable $e) {

                    // Notify UI about failure
                    app(EventService::class)->emit([
                        'event' => 'dir.error',
                        'type' => 'mkdir',
                        'panel' => $command['panel'] ?? null,
                        'path' => dirname($path),
                        'target' => $path,
                        'connection_id' => $command['connection_id'] ?? null,
                        'message' => $e->getMessage(),
                    ]);

                    throw $e;
                }
            }
        );
    }

    private function handleCreateFile(array $command): bool
    {
        $path = $command['payload']['path'];

        return $this->runWithConnection(
            $command,
            function ($ctx) use ($path, $command) {

                try {

                    if ($ctx?->fs) {

                        $encodedPath = PathHelper::encode($path);

                        if (
                            $ctx->fs->fileExists($encodedPath) ||
                            $ctx->fs->directoryExists($encodedPath)
                        ) {
                            throw new RuntimeException(
                                'File already exists'
                            );
                        }

                        $ctx->fs->write(
                            $encodedPath,
                            ''
                        );

                    } else {

                        if (
                            is_file($path) ||
                            is_dir($path)
                        ) {
                            throw new RuntimeException(
                                'File already exists'
                            );
                        }

                        $dir = dirname($path);

                        if (!is_dir($dir)) {

                            if (!mkdir($dir, 0777, true)) {
                                throw new RuntimeException(
                                    'Failed to create parent directory'
                                );
                            }
                        }

                        set_error_handler(
                            function ($severity, $message) {
                                throw new RuntimeException($message);
                            }
                        );

                        try {

                            $result = touch($path);

                        } finally {

                            restore_error_handler();
                        }

                        if (!$result) {
                            throw new RuntimeException(
                                'Failed to create file'
                            );
                        }
                    }

                    // Notify UI about successful filesystem update
                    app(EventService::class)->emit([
                        'event' => 'file.created',
                        'type' => 'create_file',
                        'panel' => $command['panel'] ?? null,
                        'path' => dirname($path),
                        'target' => $path,
                        'connection_id' => $command['connection_id'] ?? null,
                    ]);


                    return true;

                } catch (\Throwable $e) {

                    // Notify UI about failure
                    app(EventService::class)->emit([
                        'event' => 'file.error',
                        'type' => 'create_file',
                        'panel' => $command['panel'] ?? null,
                        'path' => dirname($path),
                        'target' => $path,
                        'connection_id' => $command['connection_id'] ?? null,
                        'message' => $e->getMessage(),
                    ]);

                    throw $e;
                }
            }
        );
    }

    private function handleConnect(int $id): void
    {
        if (isset($this->connections[$id])) {
            $this->log("Connection {$id} already exists");
            return;
        }

        $model = Connection::find($id);

        if (!$model) {
            $this->log("Connection {$id} not found in DB");
            return;
        }

        try {

            $service = app(ConnectionService::class);
            $adapter = $service->getAdapter($model);

            if (!$adapter) {
                throw new \RuntimeException("Adapter creation failed");
            }

            $filesystem = new Filesystem($adapter);

            $this->connections[$id]['fs'] = $filesystem;
            $this->connections[$id]['adapter'] = $adapter;

            $this->log("Connection {$id} CREATED and stored in memory");

            $this->log("Connections in memory: " . json_encode(array_keys($this->connections)));

        } catch (\Throwable $e) {

            $this->log("Connection {$id} FAILED: " . $e->getMessage());
        }
    }

    private function handleListCommand(array $command): void
    {
        $connection_id = (int)$command['connection_id'];
        $operation_id  = $command['operation_id'];
        $fileName = "list_{$connection_id}_{$operation_id}.json";
        $path = PathHelper::encode($command['path'] ?? '/');

        $this->executeCommand(
            $fileName,
            $command['task_id'] ?? null,
            function () use ($command, $path) {

                return $this->runWithConnection($command, function ($ctx) use ($path) {

                    $fs = $ctx->fs;

                    $contents = [];

                    foreach ($fs->listContents($path, false) as $item) {

                        $itemPath = $item->path();
                        $type = $item->type();
                        $modified = $item->lastModified();
                        $visibility = $item->visibility();
                        $size = null;

                        if ($item instanceof FileAttributes) {
                            $size = $item->fileSize();
                        }

                        $contents[] = [
                            'path' => PathHelper::decode($itemPath),
                            'type' => $type,
                            'size' => $size,
                            'modified' => $modified,
                            'visibility' => $visibility,
                        ];
                    }

                    return $contents;
                });
            }
        );
    }

    private function writeResult(string $fileName, array $data): void
    {

        file_put_contents(
            $this->resultsDir . "/" . $fileName,
            json_encode(
                $data,
                JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE
            ),
            LOCK_EX
        );
    }

    protected function sleep(): void
    {
        if ($this->idleLoops < 5) {
            usleep(100_000); // 0.1s
        } elseif ($this->idleLoops < 20) {
            usleep(300_000); // 0.3s
        } else {
            usleep(800_000); // 0.8s
        }
    }

    private function executeCommand(string $fileName, ?string $taskId, callable $callback): void
    {
        try {

            $result = $callback();

            $this->writeResult($fileName, [
                'ok' => true,
                'data' => $result,
                'error' => null
            ]);

            $this->log("COMMAND OK {$fileName}, worker: {$this->workerName}");

        } catch (TaskCancelledException $e) {

            $this->log("TASK {$taskId} cancelled, worker: {$this->workerName}");

            $this->writeResult($fileName, [
                'ok' => true,
                'data' => null,
                'error' => null,
                'meta' => ['status' => 'cancelled'],
            ]);

            return; 

        } catch (TaskPausedException $e) {

            $this->log("TASK {$taskId} paused, worker: {$this->workerName}");

            if ($taskId) {
                \App\Helpers\TaskHelper::update($taskId, function (&$task) {

                    if (($task['status'] ?? null) !== \App\Enums\TaskStatus::PAUSED->value) {
                        $task['status'] = \App\Enums\TaskStatus::PAUSED->value;
                        $task['paused_at'] = time();
                    }
                });
            }

            $this->writeResult($fileName, [
                'ok' => true,
                'data' => null,
                'error' => null,
                'meta' => ['status' => 'paused'],
            ]);

            return;

        } catch (\Throwable $e) {

            $this->writeResult($fileName, [
                'ok' => false,
                'data' => null,
                'error' => $e->getMessage()
            ]);

            $this->log("COMMAND FAILED {$fileName}, worker: {$this->workerName}: " . $e->getMessage());
        }
    }


    /**
    * Get or restore connection (Filesystem + Adapter)
    *
    * @param int $connection_id
    * @return array{fs: \League\Flysystem\Filesystem, adapter: mixed}
    * @throws \RuntimeException
    */
    private function getConnection(int $connection_id): array
    {
        $attempts = 0;

        while ($attempts < 3) {

            // если нет в памяти — создаём
            if (!isset($this->connections[$connection_id])) {
                $this->handleConnect($connection_id);
            }

            try {
                $conn = $this->connections[$connection_id];

                // защита от кривого состояния
                if (
                    !is_array($conn) ||
                    !isset($conn['fs'], $conn['adapter'])
                ) {
                    throw new \RuntimeException("Invalid connection structure");
                }

                //$fs = $conn['fs'];

                // "пинг" соединения
                //$fs->directoryExists('/');

                return $conn;

            } catch (\Throwable $e) {

                $this->log("Connection {$connection_id} lost, reconnecting...");

                // выкидываем битое соединение
                unset($this->connections[$connection_id]);

                // создаём заново
                $this->handleConnect($connection_id);

                $attempts++;
            }
        }

        throw new \RuntimeException("Connection {$connection_id} failed after reconnect attempts");
    }

    // private function getConnection(int $connection_id): array
    // {
    //     if (!isset($this->connections[$connection_id])) {
    //         $this->handleConnect($connection_id);
    //     }

    //     if (!isset($this->connections[$connection_id])) {
    //         throw new \RuntimeException(
    //             "Connection {$connection_id} unavailable"
    //         );
    //     }

    //     $conn = $this->connections[$connection_id];

    //     if (
    //         !is_array($conn) ||
    //         !isset($conn['fs'], $conn['adapter'])
    //     ) {
    //         unset($this->connections[$connection_id]);

    //         throw new \RuntimeException(
    //             "Invalid connection structure"
    //         );
    //     }

    //     return $conn;
    // }

    private function getConnectionContext(int $connection_id): ConnectionContext
    {
        $conn = $this->getConnection($connection_id);

        return new ConnectionContext(
            $conn['fs'],
            $conn['adapter'],
            $connection_id
        );
    }

    private function acquireConnectionSlot(int $connectionId, int $maxSlots = 2): ?array
    {
        for ($i = 0; $i < $maxSlots; $i++) {

            $lockFile = $this->locksDir . "/conn_{$connectionId}_{$i}.lock";

            $fp = fopen($lockFile, 'c');

            if ($fp && flock($fp, LOCK_EX | LOCK_NB)) {
                return [$fp, $lockFile];
            }

            if ($fp) {
                fclose($fp);
            }
        }

        return null; 
    }

    private function releaseConnectionSlot($fp): void
    {
        flock($fp, LOCK_UN);
        fclose($fp);
    }

    /**
     * Closes connections that have been idle longer than the defined TTL.
     *
     * This method iterates over all active connections and checks their
     * last usage time. If a connection has not been used for more than
     * the configured TTL, it is removed from memory.
     *
     * Helps to:
     * - prevent unused connections from hanging indefinitely
     * - reduce resource usage (FTP/SFTP sessions)
     * - keep worker memory clean
     *
     * Note: connections will be re-established automatically when needed.
     */
    private function cleanupIdleConnections(): void
    {
        $ttl = config('workers.connection_idle_ttl', 60);

        foreach ($this->connections as $id => $conn) {

            if (!isset($this->connectionLastUsed[$id])) {
                continue;
            }

            $lastUsed = $this->connectionLastUsed[$id] ?? 0;

            if (time() - $lastUsed > $ttl) {

                unset($this->connections[$id]);
                unset($this->connectionLastUsed[$id]);

                $this->log("Connection {$id} AUTO-CLOSED (idle)");
                $this->log("Connections in memory: " . json_encode(array_keys($this->connections)));
            }
        }
    }
}

(new JobWorker($workerName))->run();