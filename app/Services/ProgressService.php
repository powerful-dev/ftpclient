<?php

namespace App\Services;

use App\Enums\TaskStatus;

class ProgressService
{
    public function advance(array &$task, string $file, int $offset): void
    {

        $task['files_progress'][$file]['offset'] = $offset;
        $task['files_progress'][$file]['done'] = false;

        $this->recalcCopied($task);
        $this->updateSpeed($task);
        $this->updateProgress($task);
        $this->tryFinish($task);
    }

    public function finishFile(array &$task, string $file): void
    {
        $size = $task['files_progress'][$file]['size'] ?? 0;

        $task['files_progress'][$file]['offset'] = $size;
        $task['files_progress'][$file]['done'] = true;

        $this->recalcCopied($task);
        $this->updateProgress($task);
        $this->tryFinish($task);
    }

    private function recalcCopied(array &$task): void
    {

        $sum = 0;

        foreach ($task['files_progress'] ?? [] as $file) {
            $sum += $file['offset'] ?? 0;
        }

        $task['copied_bytes'] = $sum;

    }

    private function updateProgress(array &$task): void
    {
        $total = max(1, $task['total_bytes'] ?? 1);

        $task['progress'] = min(
            100,
            round(($task['copied_bytes'] / $total) * 100, 2)
        );
    }

    private function updateSpeed(array &$task): void
    {
   
        $now = microtime(true);

        $last = $task['last_update_at'] ?? $now;
        $dt = max(0.001, $now - $last);

        $prevBytes = $task['last_copied_bytes'] ?? $task['copied_bytes'];
        $delta = max(0, $task['copied_bytes'] - $prevBytes);

        $instant = $delta / $dt;

        $prevSpeed = $task['speed_raw'] ?? $instant;

        $alpha = 0.2;
        $avg = ($prevSpeed * (1 - $alpha)) + ($instant * $alpha);

        $task['speed_raw'] = $avg;

        $task['last_update_at'] = $now;
        $task['last_copied_bytes'] = $task['copied_bytes'];
    }

    protected function tryFinish(array &$task): void
    {

        if (($task['status'] ?? null) === TaskStatus::COMPLETED->value) {
            return;
        }

        // --- 1. BYTES ---
        if (!empty($task['files_progress'])) {

            $files = $task['files'] ?? [];
            $progress = $task['files_progress'] ?? [];

            if (count($progress) < count($files)) {
                return;
            }

            $completedBytes = 0;

            foreach ($task['files_progress'] ?? [] as $file) {

                if (!empty($file['done'])) {
                    $completedBytes += $file['size'] ?? 0;
                }
            }

            $totalBytes = $task['total_bytes'] ?? 0;

            if (
                $totalBytes > 0
                && $completedBytes >= $totalBytes
            ) {
                $this->completeTask($task);
            }

            return;
        }

        // --- 2. ITEMS ---
        if (!empty($task['total_items'])) {

            $done  = $task['processed_items'] ?? 0;
            $total = $task['total_items'];

            if ($total > 0 && $done >= $total) {
                $this->completeTask($task);
                return;
            }
        }

        if (($task['progress'] ?? 0) >= 100) {
            $this->completeTask($task);
            return;
        }
    }

    public function completeTask(array &$task): void
    {

        if (
            ($task['status'] ?? null) === TaskStatus::COMPLETED->value
        ) {
            return;
        }

        $task['progress'] = 100;
        $task['status']   = TaskStatus::COMPLETED->value;

        $task['finished_at'] = microtime(true);
    }

    public function advanceItem(array &$task, string $file): void
    {
        $task['from'] = $file;

        $task['processed_items'] =
            ($task['processed_items'] ?? 0) + 1;

        $total = max(1, $task['total_items'] ?? 1);

        $task['progress'] = min(
            100,
            round(
                ($task['processed_items'] / $total) * 100
            )
        );

        $this->tryFinish($task);
    }

}