<?php

namespace App\Services;

use App\Helpers\PathHelper;
use App\Helpers\StringHelper;
use Illuminate\Support\Str;
use App\Traits\TaskTrait;
use App\Services\EventService;
use App\Support\Runtime;
use App\Exceptions\TaskCancelledException;
use App\Exceptions\TaskPausedException;
use App\Exceptions\ConflictException;
use App\Enums\TaskStatus;
use App\Helpers\TaskHelper;
use App\Services\ConflictResolver;
use App\Services\StreamCopier;
use App\DTO\ConnectionContext;
use App\Flysystem\ProgressTrackingSftpAdapter;
use App\Flysystem\ProgressTrackingFtpAdapter;

class FileCopyService
{
    use TaskTrait;

    protected string $taskId;
    protected ConnectionService $connectionService;

    private $from = '', $to = '';

    private array $fileDecisions = [];
    private bool $overwriteAll = false;
    private bool $skipAll = false;

    private $adapter = null;
    private $fs = null;

    private $connectionId = null;

    /**
     * Cached map of remote files for current directory.
     * Key: normalized path, Value: file meta
     */
    private array $remoteFileMap = [];

    /**
     * Current remote directory for which the map is built.
     */
    private ?string $remoteDir = null;

    public function __construct(ConnectionService $connectionService, private EventService $events)
    {
        $this->connectionService = $connectionService;
    }

    public function copy(
        array $files,
        string $destinationDir,
        string $taskId,
        ?ConnectionContext $ctx = null,
        array $options = []
    ): void
    {

        $this->taskId       = $taskId;
        $this->adapter      = $ctx?->adapter;
        $this->fs           = $ctx?->fs;
        $this->connectionId = $ctx?->connectionId;

        $this->overwriteAll = (bool)($options['overwrite_all'] ?? false);
        $this->skipAll = (bool)($options['skip_all'] ?? false);
        $this->fileDecisions = $options['file_decisions'] ?? [];

        try {
            if ($this->fs) {
                $this->copyToRemote($files, $destinationDir);
            } else {
                $this->copyToLocal($files, $destinationDir);
            }

        } catch (\Throwable $e) {

            throw $e;

        } finally {

            // Notify UI that directory content changed
            $this->events->emit([
                'event' => 'panel_refresh',
                'path'  => $destinationDir,
            ]);
        }
    }

    private function copyToRemote(array $files, string $destDir): void
    {

        // делаем zip
        //$zip = $this->makeZip($files);

        if (!is_null($this->fs)) {

            foreach ($files as $file) {
                try {
                    $remotePath = PathHelper::normalize($destDir . '/' . $file['name']);
                    $sourcePath = str_replace('\\', '/', $file['path']);

                    if (is_dir($sourcePath)) {
                        $this->copyDirectoryToRemote($sourcePath, $remotePath);
                    } else {
                        $this->copyRemoteFile($sourcePath, $remotePath);
                    }
                }
                catch (TaskCancelledException|TaskPausedException|ConflictException $e) {
                    throw $e;
                } catch (\Throwable $e) {

                    $this->addTaskError(__("Error copying file") . " '{$file['path']}' → '{$remotePath}': {$e->getMessage()}");

                    continue;
                }
            }
        } else {
            $this->addTaskError("Не удалось создать подключение!");
        }
    }

    private function copyToLocal(array $files, string $destPath): void
    {

        $destDir = rtrim(str_replace('\\', '/', $destPath), '/') . '/';


        foreach ($files as $file) {

            $src = $file['path'];

            $progress = $this->getFileProgress($src);

            if (!empty($progress['done'])) {
                continue;
            }
            
            try {

                $this->checkCancelled();
                $this->checkPaused();

                $src = $file['path'];
                $rel = ltrim($file['name'], '/\\');
                $dest = PathHelper::normalize($destDir . $rel);

                if (is_dir($src)) {
                    $iterator = new \RecursiveIteratorIterator(
                        new \RecursiveDirectoryIterator($src, \RecursiveDirectoryIterator::SKIP_DOTS),
                        \RecursiveIteratorIterator::SELF_FIRST
                    );

                    if (!is_dir($dest)) {
                        try {
                            @mkdir($dest, 0777, true);
                        } catch (\Throwable $e) {
                            $this->addTaskError("Ошибка создания папки '{$file['path']}': {$e->getMessage()}");
                            continue;
                        }
                    }

                    foreach ($iterator as $item) {
                        $subSrc = PathHelper::normalize($item->getPathname());
                        $subRel = ltrim(str_replace($src, '', $subSrc), '/\\');
                        $subDest = PathHelper::normalize($destDir . $rel . '/' . $subRel);
                        try {
                            if ($item->isDir()) {
                                if (!is_dir($subDest)) {
                                    mkdir($subDest, 0777, true);
                                }
                            } else {
                                $this->copyLocalFile($subSrc, $subDest);
                            }
                        } catch (\Throwable $e) {
                            $this->addTaskError("⚠️ Ошибка копирования файла '{$subSrc}' → '{$subDest}': {$e->getMessage()}");
                            continue;
                        }
                    }
                } else {
                    $this->copyLocalFile($src, $dest);
                }

            } catch (TaskCancelledException | TaskPausedException | ConflictException $e) {

                throw $e;
                
            } catch (\Throwable $e) {

                $this->addTaskError("Ошибка при паузе '{$file['path']}': {$e->getMessage()}");
                continue;
            }
        }
    }

    private function copyRemoteFile(
        string $src,
        string $dst
    ): void {

        if ($this->adapter instanceof ProgressTrackingSftpAdapter) {
            $this->attachProgressCallback($src);
        }

        $dir = PathHelper::encode(dirname($dst));
        $fileInfo = pathinfo($src);

        $filename = $fileInfo['filename'] ?? pathinfo($src, PATHINFO_BASENAME);
        $extension = $fileInfo['extension'] ?? '';

        $translitName = $this->prepareFileName($filename, $extension);
        $originalName = $extension ? "{$filename}.{$extension}" : $filename;

        $remotePath = $dir . '/' . $translitName;

        $this->from = $src;
        $this->to   = $dst;

        $size = @filesize($src) ?: 0;

        $this->initRemoteIndex($this->connectionId, $dir);

        // Init progress
        $this->updateTask(function (&$task) use ($src, $size) {
            $task['files_progress'][$src] = [
                'offset' => 0,
                'done'   => false,
                'size'   => $size,
            ];
        });

        // -------------------------------------
        // CONFLICT RESOLUTION
        // -------------------------------------

        $resolver = new ConflictResolver(
            $this->fileDecisions,
            $this->overwriteAll,
            $this->skipAll
        );

        $exists = $this->remoteExists($dst);

        $result = $resolver->resolve($src, $dst, exists: $exists);

        switch ($result->action) {

            case 'overwrite':
                try {
                    $this->fs->delete(PathHelper::encode($dst));
                } catch (\Throwable $e) {
                    $this->addTaskError("❌ Failed to delete remote file: {$dst}");
                    return;
                }

                $this->updateTask(function (&$task) use ($src) {
                    unset($task['file_decisions'][$src]);
                });
                break;

            case 'skip':
                $this->markFileAsDone($src);
                return;

            case 'rename':
                $remotePath = PathHelper::encode($result->path);

                $this->updateTask(function (&$task) use ($src) {
                    unset($task['file_decisions'][$src]);
                });
                break;

            case 'conflict':
                throw new ConflictException($result->src, $result->dst);
        }

        // -------------------------------------
        // Upload
        // -------------------------------------

        $handle = @fopen($src, 'rb');
        if (!$handle) {
            $this->addTaskError("❌ Failed to open file: {$src}");
            return;
        }

        $success = $this->fs->writeStream($remotePath, $handle);
        fclose($handle);

        if ($success === false) {
            $this->addTaskError("❌ Failed to upload file: {$src} → {$remotePath}");
            return;
        }

        // -------------------------------------
        // Rename back to original (encoding fix)
        // -------------------------------------

        if ($translitName !== $originalName) {
            $this->renameRemoteFile($dir, $translitName, $originalName);
        }

        // Finish progress
        $this->updateTask(function (&$task) use ($src) {
            $this->progress()->finishFile($task, $src);
        });
    }

    private function attachProgressCallback(string $src): void
    {
        $offset = 0;

        $this->adapter->setProgressCallback(function ($transferred) use ($src, &$offset) {

            $delta = max(0, $transferred - $offset);
            $offset = $transferred;

            if ($delta <= 0) {
                return;
            }


            $interval = config('transfers.progress.remote_interval', 0.5);

            if (!$this->shouldFlushProgress($src, $interval)) {
                return;
            }

            $this->updateTask(function (&$task) use ($src, $offset) {
                $this->progress()->advance($task, $src, $offset);
            });

        });
    }

    /**
     * Renames a file on a remote server taking into account the Windows-1251 encoding.
     */
    private function renameRemoteFile(string $remotePath, string $currentFileName, string $targetFileName): void
    {
        
        try {
            $path = $remotePath . '/' . $currentFileName;

            $newPath = $remotePath . '/' . mb_convert_encoding($targetFileName, 'Windows-1251', 'UTF-8');

            $this->fs->move($path, $newPath);

        } catch (\Exception $e) {
            logger()->error("Failed to rename file from '$currentFileName' to '$targetFileName': {$e->getMessage()}");
        }
    }

    private function copyDirectoryToRemote(string $localPath, string $remoteBasePath): void
    {
        $encodedRemoteBase = PathHelper::encode($remoteBasePath);
        if (!$this->fs->directoryExists($encodedRemoteBase)) {
            try {
                $this->fs->createDirectory($encodedRemoteBase);
            } catch (\Throwable $e) {
                
                $this->addTaskError(__("Error creating directory", ["path" => $remoteBasePath, "message" => $e->getMessage()]));
                return; 
            }
        } else {
            $this->addTaskError("Директория уже существует на удалённом: {$remoteBasePath}");
        }

        try {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($localPath, \RecursiveDirectoryIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::SELF_FIRST
            );

            foreach ($iterator as $item) {
                try {
                    $subSrc = StringHelper::reverseBackSlashes($item->getPathname());
                    $subRel = ltrim(str_replace($localPath, '', $subSrc), '/\\');
                    $subDst = rtrim($remoteBasePath, '/') . '/' . $subRel;
                    if ($item->isDir()) {
                        $subDst = PathHelper::encode($subDst);
                        if (!$this->fs->directoryExists($subDst)) {
                            $this->fs->createDirectory($subDst);
                        }
                    } else {
                        $parentDir = PathHelper::encode(dirname($subDst));
                        if (!$this->fs->directoryExists($parentDir)) {
                            $this->fs->createDirectory($parentDir);
                        }
                        $this->copyRemoteFile($subSrc, $subDst);
                    }
                } catch (\Throwable $e) {
                    $this->addTaskError(__("Error copying file") . " → '{$localPath}': {$e->getMessage()}");
                    continue;
                }
            }
        } catch (\Throwable $e) {
            $this->addTaskError("Error reading content '{$localPath}': {$e->getMessage()}");
        }
    }

    private function copyLocalFile(string $src, string $dst): void
    {
        $this->from = $src;
        $this->to   = $dst;

        $size = @filesize($src) ?: 0;
        $dstDir = dirname($dst);

        $progress = $this->getFileProgress($src);
        $offset = $progress['offset'] ?? 0;

        // Initialize file size in progress
        $this->updateTask(function (&$task) use ($src, $size) {
            $task['files_progress'][$src]['size'] = $size;
        });

        // Resolve conflict
        $resolver = new ConflictResolver(
            $this->fileDecisions,
            $this->overwriteAll,
            $this->skipAll
        );

        $exists = file_exists($dst);

        $result = $resolver->resolve($src, $dst, $offset, $exists);

        switch ($result->action) {

            case 'overwrite':
                @unlink($dst);

                $this->updateTask(function (&$task) use ($src) {
                    unset($task['file_decisions'][$src]);
                });
                break;

            case 'skip':
                $this->markFileAsDone($src);
                return;

            case 'rename':
                $dst = PathHelper::normalize($result->path);

                $this->updateTask(function (&$task) use ($src) {
                    unset($task['file_decisions'][$src]);
                });
                break;

            case 'conflict':
                throw new ConflictException($result->src, $result->dst);
        }

        // Ensure destination directory exists
        if (!is_dir($dstDir)) {
            if (!@mkdir($dstDir, 0777, true) && !is_dir($dstDir)) {
                throw new \RuntimeException("Failed to create dir: {$dstDir}");
            }
        }

        // Open streams
        [$from, $to] = $this->openStreams($src, $dst, $offset);

        $copier = new StreamCopier();

        try {

            $offset = $copier->copy(
                $from,
                $to,
                $offset,

                // Progress callback
                function ($offset) use ($src) {

                    $interval = config('transfers.progress.remote_interval', 0.1);

                    if (!$this->shouldFlushProgress($src, $interval)) {
                        return;
                    }

                    $this->updateTask(function (&$task) use ($src, $offset) {
                        $this->progress()->advance($task, $src, $offset);
                    });
                },

                // Pause / cancel check
                function () {
                    $this->checkCancelled();
                    $this->checkPaused();
                }
            );

            // Mark file as completed
            $this->updateTask(function (&$task) use ($src) {
                $this->progress()->finishFile($task, $src);
            });

        } catch (TaskCancelledException $e) {

            if (file_exists($dst)) {
                @unlink($dst);
            }

            throw $e;

        } catch (TaskPausedException $e) {

            $this->updateTask(function (&$task) use ($src, $offset) {
                $task['files_progress'][$src]['offset'] = $offset;
            });

            throw $e;

        } finally {
            fclose($from);
            fclose($to);
        }
    }

    

    private function openStreams(string $src, string $dst, int $offset)
    {
        $from = @fopen($src, 'rb');
        if (!$from) {
            throw new \RuntimeException("Failed to open source: {$src}");
        }

        if ($offset > 0) {
            fseek($from, $offset);
        }

        $to = @fopen($dst, $offset > 0 ? 'ab' : 'wb');
        if (!$to) {
            fclose($from);
            throw new \RuntimeException("Failed to open destination: {$dst}");
        }

        return [$from, $to];
    }

    /**
     * Prepares the file name (with transliteration if needed).
     */
    private function prepareFileName(string $fileName, string $extension): string
    {
        $baseName = StringHelper::hasCyrillic($fileName)
            ? StringHelper::transliteration($fileName)
            : $fileName;

        return $extension
            ? "{$baseName}.{$extension}"
            : $baseName;
    }

    private function makeZip($files)
    {
        $zipPath = storage_path('app/' . Str::uuid() . '.zip');

        $zip = new \ZipArchive();

        try {
            if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
                return false;
            }

            $addToZip = function ($zip, $path, $baseInZip = '') use (&$addToZip) {
                if (is_dir($path)) {
                    $folderName = basename($path);
                    if (!$zip->addEmptyDir($baseInZip . $folderName)) {
                        throw new \RuntimeException("Не удалось добавить директорию: {$path}");
                    }

                    $files = scandir($path);
                    foreach ($files as $file) {
                        if ($file === '.' || $file === '..') continue;
                        $addToZip($zip, "$path/$file", $baseInZip . $folderName . '/');
                    }
                } elseif (is_file($path)) {
                    if (!$zip->addFile($path, $baseInZip . basename($path))) {
                        throw new \RuntimeException("Не удалось добавить файл: {$path}");
                    }
                }
            };

            foreach ($files as $file) {
                if (!isset($file['path']) || !file_exists($file['path'])) {
                    continue;
                }

                $relativePath = ltrim($file['unixPath'] ?? basename($file['path']), '/');
                $addToZip($zip, $file['path'], dirname($relativePath) . '/');
            }

            $zip->close();

            return $zipPath;

        } catch (\Throwable $e) {
            if (file_exists($zipPath)) {
                @unlink($zipPath);
            }
            return false;
        }
    }


    

    private function checkPaused(): void
    {
        if (empty($this->taskId)) {
            return;
        }

        $file = Runtime::path("tasks") . "/{$this->taskId}.json";

        if (!file_exists($file)) {
            return;
        }

        $task = TaskHelper::read($file);

        if (($task['status'] ?? null) === TaskStatus::PAUSED->value) {
            throw new TaskPausedException('TASK_PAUSED');
        }
    }


    private function getFileProgress(string $path): array
    {
        $task = $this->getTask();

        return $task['files_progress'][$path] ?? [
            'offset' => 0,
            'done' => false,
        ];
    }

    private function getTask(): array
    {
        $file = Runtime::path("tasks") . "/{$this->taskId}.json";

        if (!file_exists($file)) {
            return [];
        }

        return TaskHelper::read($file);
    }

    /**
     * Build in-memory index of remote files for a directory using ConnectionService cache.
     */
    private function initRemoteIndex(int $connectionId, string $dir): void
    {
        $dir = PathHelper::normalize($dir);

        // Avoid rebuilding for the same directory
        if ($this->remoteDir === $dir && !empty($this->remoteFileMap)) {
            return;
        }

        $this->remoteDir = $dir;
        $this->remoteFileMap = [];

        $files = $this->connectionService->getDirectoryCache($connectionId, $dir);

        if (empty($files)) {
            return; // no cache → map stays empty
        }

        foreach ($files as $file) {
            $path = PathHelper::normalize($file['path'] ?? '');
            if ($path) {
                $this->remoteFileMap[$path] = $file;
            }
        }
    }

    /**
     * Check file existence using cache first, then fallback to FS.
     */
    private function remoteExists(string $path): bool
    {

        $path = PathHelper::normalize($path);

        // 1. Cache hit
        if (!empty($this->remoteFileMap)) {
            if (isset($this->remoteFileMap[$path])) {
                return true;
            }

            // cache says "no such file" → but it might be stale
            // fall through to FS check
        }

        // 2. Fallback to real FS
        return $this->fs->fileExists($path);
    }

    private function addToRemoteIndex(string $path): void
    {
        $path = PathHelper::normalize($path);

        if ($this->remoteDir === dirname($path)) {
            $this->remoteFileMap[$path] = [
                'path' => $path,
                'type' => 'file',
            ];
        }
    }

    private function removeFromRemoteIndex(string $path): void
    {
        $path = PathHelper::normalize($path);
        unset($this->remoteFileMap[$path]);
    }

    
}
