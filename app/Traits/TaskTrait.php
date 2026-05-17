<?php

namespace App\Traits;

use App\Helpers\TaskHelper;
use App\Services\ProgressService;
use App\Enums\TaskStatus;
use App\Support\Runtime;
use App\Exceptions\TaskCancelledException;

trait TaskTrait
{

    protected ProgressService $progress;

    protected function progress(): ProgressService
    {
        return $this->progress ??= app(ProgressService::class);
    }

    /**
     * Safely update task with callback.
     *
     * @param callable(array &$task): void $callback
     */
    protected function updateTask(callable $callback): void
    {
        TaskHelper::update($this->taskId, function (&$task) use ($callback) {
            $callback($task);
        });
    }


    /**
     * Add error message to task (max 10 errors).
     */
    protected function addTaskError(string $message): void
    {
        $this->updateTask(function (&$task) use ($message) {

            if (!isset($task['errors']) || !is_array($task['errors'])) {
                $task['errors'] = [];
            }

            if (count($task['errors']) < 10) {
                $task['errors'][] = $message;
            }
        });
    }

    private function checkCancelled(): void
    {
        if (empty($this->taskId)) {
            logger()->warning('checkCancelled: empty taskId');
            return;
        }

        $file = Runtime::path("tasks") . "/{$this->taskId}.json";

        if (!file_exists($file)) {
            return;
        }

        $task = TaskHelper::read($file);

        if (($task['status'] ?? null) === TaskStatus::CANCELED->value) {
            throw new TaskCancelledException ('TASK_CANCELLED');
        }
    }

    private function markFileAsDone(string $src): void
    {
        $this->updateTask(function (&$task) use ($src) {
            $task['files_progress'][$src]['done'] = true;

            $size = $task['files_progress'][$src]['size'] ?? 0;
            $task['files_progress'][$src]['offset'] = $size;

            $this->progress()->finishFile($task, $src);
        });
    }

    protected array $lastProgressUpdate = [];

    private function shouldFlushProgress(string $src, float $interval): bool
    {
        $now = microtime(true);

        $last = $this->lastProgressUpdate[$src] ?? 0;

        if (($now - $last) < $interval) {
            return false;
        }

        $this->lastProgressUpdate[$src] = $now;

        return true;
    }
}