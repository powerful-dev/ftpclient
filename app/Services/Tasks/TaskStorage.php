<?php

namespace App\Services\Tasks;

use App\Support\Runtime;

class TaskStorage
{

    private function path(string $taskId): string
    {
        return Runtime::path('tasks') . "/{$taskId}.json";
    }

    public function get(string $taskId): ?array
    {
        $file = $this->path($taskId);

        if (!file_exists($file)) {
            return null;
        }

        return json_decode(file_get_contents($file), true);
    }

    public function put(string $taskId, array $task): void
    {
        $file = $this->path($taskId);

        $fp = fopen($file, 'c+');

        if (!$fp) {
            throw new \RuntimeException("Cannot open task file: $file");
        }

        flock($fp, LOCK_EX);

        ftruncate($fp, 0);
        fwrite($fp, json_encode($task, JSON_UNESCAPED_UNICODE));
        fflush($fp);

        flock($fp, LOCK_UN);
        fclose($fp);
    }

    public function update(string $taskId, callable $callback): void
    {
        $file = $this->path($taskId);

        $fp = fopen($file, 'c+');

        if (!$fp) {
            throw new \RuntimeException("Cannot open task file: $file");
        }

        flock($fp, LOCK_EX);

        $contents = stream_get_contents($fp);
        $task = $contents ? json_decode($contents, true) : [];

        $callback($task);

        ftruncate($fp, 0);
        rewind($fp);
        fwrite($fp, json_encode($task, JSON_UNESCAPED_UNICODE));
        fflush($fp);

        flock($fp, LOCK_UN);
        fclose($fp);
    }
}