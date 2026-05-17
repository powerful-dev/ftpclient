<?php

namespace App\Helpers;

use App\Support\Runtime;
use App\Enums\TaskStatus;

class TaskHelper
{
    private static function getPath(string $taskId): string
    {
        return Runtime::path('tasks') . "/{$taskId}.json";
    }

    /**
     * Safely update task using atomic read-modify-write with file locking.
     *
     * @param callable(array &$task): void $callback
     */
    public static function update(string $taskId, callable $callback): void
    {
        $file = self::getPath($taskId);

        if (!file_exists($file)) {
            return;
        }

        $fp = fopen($file, 'c+');

        if (!$fp) {
            throw new \RuntimeException(
                "Cannot open task file: {$file}"
            );
        }

        try {

            // Acquire exclusive lock
            if (!flock($fp, LOCK_EX)) {
                throw new \RuntimeException(
                    "Cannot lock task file: {$file}"
                );
            }

            rewind($fp);

            $raw = stream_get_contents($fp);

            // Prevent corrupted empty state
            if ($raw === false || trim($raw) === '') {

                logger()->warning('Task update skipped: empty JSON', [
                    'task_id' => $taskId,
                    'file' => $file,
                ]);

                return;
            }

            $task = json_decode($raw, true);

            // Prevent invalid JSON overwrite
            if (!is_array($task)) {

                logger()->error('Task update skipped: invalid JSON', [
                    'task_id' => $taskId,
                    'file' => $file,
                    'raw' => $raw,
                    'json_error' => json_last_error_msg(),
                ]);

                return;
            }

            // Apply mutation callback
            $callback($task);

            // Auto-fix completed state
            if (
                ($task['progress'] ?? 0) >= 100
                && ($task['status'] ?? null) !== TaskStatus::COMPLETED->value
            ) {
                $task['status'] = TaskStatus::COMPLETED->value;
                $task['finished_at'] = microtime(true);
            }

            $task['updated_at'] = time();

            // Validate critical fields before write
            if (
                empty($task['id']) ||
                empty($task['type'])
            ) {

                logger()->error('Task update prevented: corrupted state', [
                    'task_id' => $taskId,
                    'task' => $task,
                ]);

                return;
            }

            $json = json_encode(
                $task,
                JSON_UNESCAPED_UNICODE
            );

            if ($json === false) {
                throw new \RuntimeException(
                    'Failed to encode task JSON: '
                    . json_last_error_msg()
                );
            }

            // Atomic rewrite
            ftruncate($fp, 0);
            rewind($fp);

            $written = fwrite($fp, $json);

            if ($written === false) {
                throw new \RuntimeException(
                    "Failed to write task file: {$file}"
                );
            }

            fflush($fp);

        } finally {

            flock($fp, LOCK_UN);
            fclose($fp);
        }
    }

    public static function read(string $file): ?array
    {

        $fp = fopen($file, 'r');

        if (!$fp) {
            return [];
        }

        try {
            if (!flock($fp, LOCK_SH)) {
                return [];
            }

            $raw = stream_get_contents($fp);
            $data = json_decode($raw ?: '{}', true);

            return is_array($data) ? $data : [];

        } finally {
            flock($fp, LOCK_UN);
            fclose($fp);
        }
    }
}