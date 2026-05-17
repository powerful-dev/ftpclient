<?php

namespace App\Services\Commands;

use Illuminate\Support\Str;
use App\Services\Tasks\TaskBuilder;
use App\Support\Runtime;
use App\Enums\TaskStatus;

class CommandDispatcher
{
    private string $commandsDir;

    private $chunkSize = 5;

    public function __construct()
    {
        $this->commandsDir = Runtime::path('commands');

        if (!is_dir($this->commandsDir)) {
            mkdir($this->commandsDir, 0777, true);
        }
    }

    public function dispatch(array $data, array $config): void
    {
        $chunks = array_chunk($data['files'], $this->chunkSize);

        TaskBuilder::build(
            $data['taskId'],
            $config['type'],
            $data
        );

        if ($config['init']) {

            // Dispatch a dedicated initialization command before processing copy chunks.
            // This ensures total_bytes is calculated once in the worker,
            // preventing race conditions and incorrect progress calculations.

            $this->writeCommand([
                'type' => 'init_' . $config['type'],
                'task_id' => $data['taskId'],
                'connection_id' => $data['connection']->id ?? null,
                'files' => $data['files'],
            ]);
        }

        foreach ($chunks as $chunk) {
            $this->writeCommand(
                $this->buildCommand(
                    $config['type'],
                    $data,
                    $chunk
                )
            );
        }
    }

    private function buildCommand(string $type, array $data, array $chunk): array
    {

        return [
            'type' => $type,
            'connection_id' => $data['connection']->id ?? null,
            'operation_id' => (string) Str::uuid(),
            'task_id' => $data['taskId'],
            'locale' => app()->getLocale(),
            'payload' => [
                'files' => $chunk,
                'source' => $data['sourceDir'],
                'destination' => $data['destinationDir'],
                'options' => $data['options'] ?? []
            ]
        ];
    }

    private function writeCommand(array $command): void
    {
        $type = $command['type'];

        $prefix = str_starts_with($type, 'init_') ? '0' : '1';

        if (in_array($type, [
            TaskStatus::CANCELED->value,
            TaskStatus::PAUSED->value,
            TaskStatus::RESUME->value
        ])) {
            $prefix = '00';
        }

        $name = "{$prefix}_{$type}_" . \Illuminate\Support\Str::uuid();

        $tmp = "{$this->commandsDir}/{$name}.tmp";
        $final = "{$this->commandsDir}/{$name}.json";

        $json = json_encode(
            $command,
            JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE
        );

        if ($json === false) {
            throw new \RuntimeException('Failed to encode command JSON');
        }

        $bytes = file_put_contents(
            $tmp,
            $json,
            LOCK_EX
        );

        if ($bytes === false) {

            @unlink($tmp);

            throw new \RuntimeException(
                "Failed to write temp command file: {$tmp}"
            );
        }

        // Atomic publish
        if (!@rename($tmp, $final)) {

            @unlink($tmp);

            throw new \RuntimeException(
                "Failed to publish command: {$final}"
            );
        }

        clearstatcache(true, $final);
    }

    public function dispatchSimple(array $command): void
    {

        $this->writeCommand(array_merge([
            'operation_id' => (string) \Illuminate\Support\Str::uuid(),
            'locale' => app()->getLocale(),
        ], $command));
    }
}