<?php

namespace App\Workers\Watchers;

use App\Services\Commands\CommandDispatcher;
use App\Helpers\PathHelper;
use Illuminate\Support\Str;
use App\Models\Connection;

class FileWatcher
{
    private array $state = [];
    private string $logFile;

    public function __construct(
        private string $runtimeDir,
        private string $tasksDir,
        private string $commandsDir,
        private int $stabilitySeconds = 2,
    ) {
        @mkdir($this->commandsDir, 0777, true);

        $this->logFile = $this->runtimeDir . '/workers/file_watcher/watcher.log';
    }

    public function check(): void
    {
        foreach ($this->getCompletedOpenTasks() as $task) {
            if (empty($task['to'])) {
                continue;
            }

            $this->checkFile($task['to'], $task);
        }
    }

    private function getCompletedOpenTasks(): array
    {
        if (!is_dir($this->tasksDir)) {
            return [];
        }

        $tasks = [];

        foreach (glob($this->tasksDir . '/*.json') as $file) {

            $json = @file_get_contents($file);
            $task = json_decode($json, true);

            if (
                !$task ||
                ($task['type'] ?? null) !== 'open' ||
                ($task['status'] ?? null) !== 'completed'
            ) {
                continue;
            }

            $tasks[] = $task;
        }

        return $tasks;
    }

    private function checkFile(string $path, array $task): void
    {
        if (!is_file($path)) {
            return;
        }

        $key = md5(($task['id'] ?? '') . '|' . $path);

        $now   = time();
        $mtime = filemtime($path);
        $size  = filesize($path);

        if (!isset($this->state[$key])) {
            $this->state[$key] = [
                'mtime' => $mtime,
                'size'  => $size,
                'last_change_at' => $now,
                'dirty' => false,
                'reported' => false,
                'task' => $task,
            ];
            return;
        }

        $state = &$this->state[$key];

        // изменения файла
        if ($state['mtime'] !== $mtime || $state['size'] !== $size) {
            $state['mtime'] = $mtime;
            $state['size']  = $size;
            $state['last_change_at'] = $now;
            $state['dirty'] = true;
            $state['reported'] = false;
            return;
        }

        // файл стабилен
        if (
            $state['dirty'] &&
            !$state['reported'] &&
            ($now - $state['last_change_at']) >= $this->stabilitySeconds
        ) {
            $this->state[$key]['reported'] = true;

            app(CommandDispatcher::class)->dispatch($this->build($task), [
                'type' => 'copy',
                'init' => true,
            ]);

            $this->log("STABLE → {$path}");
        }
    }

    public function build(array $task): array
    {
        return [
            'files' => [[
                'name' => basename($task['to']),
                'path' => $task['to'],
                'unixPath' => PathHelper::toUnixPath($task['to']),
            ]],
            'sourcePanel' => 'left',
            'destinationPanel' => 'right',
            'sourceType' => 'local',
            'destinationType' => 'remote',
            'sourceDir' => dirname($task['to']),
            'destinationDir' => dirname($task['from']),
            'connection' => Connection::find($task['connection_id']) ?? null,
            'taskId' => (string) Str::uuid(),
            'options' => [
                'overwrite' => true
            ]
        ];
    }


    private function log(string $message): void
    {
        file_put_contents(
            $this->logFile,
            '[' . date('H:i:s') . '] ' . $message . PHP_EOL,
            FILE_APPEND
        );
    }
}