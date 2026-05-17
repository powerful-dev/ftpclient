<?php

namespace App\Services\Tasks;

use App\Enums\TaskStatus;
use App\Helpers\PathHelper;
use App\Support\Runtime;

class TaskBuilder
{
    public static function build(
        string $taskId,
        string $type,
        array $data
    ): void {

        $now = time();

        // --- from / to ---
        $firstFile = reset($data['files']);
        $from = $firstFile['path'] ?? '';

        $to = ($type != 'delete' && $from)
            ? PathHelper::normalize(
                $data['destinationDir'] . '/' . basename($from)
            )
            : '';

        // --- task payload ---
        $task = [

            'id'     => $taskId,
            'type'   => $type,
            'status' => TaskStatus::RUNNING->value,

            'connection_id' => $data['connection']->id ?? null,

            'options' => $data['options'] ?? [],

            'progress_mode' => $type === 'copy' ? 'bytes' : 'items',

            'progress' => 0,
            'speed_raw' => 0,
            'eta_seconds' => 0,
            'total_paused_seconds' => 0,

            'label' => __($type . '.label'),
            'from'  => $from,
            'to'    => $to,

            'total_bytes'  => 0,
            'copied_bytes' => 0,

            'total_items'  => 0,
            'processed_items' => 0,

            'speed_history' => [],

            'errors' => [],

            'locale' => app()->getLocale(),

            'files' => $data['files'],

            'created_at' => $now,
            'updated_at' => $now,
        ];

        self::writeTask($taskId, $task);
    }

    private static function writeTask(string $taskId, array $task): void
    {
        $dir = Runtime::path('tasks');

        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        $tmp   = "{$dir}/{$taskId}.tmp";
        $final = "{$dir}/{$taskId}.json";

        file_put_contents(
            $tmp,
            json_encode($task, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
            LOCK_EX
        );

        rename($tmp, $final);
    }
}