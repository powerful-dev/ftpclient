<?php

namespace App\Livewire;

use Livewire\Component;
use App\Enums\TaskStatus;
use App\Support\Runtime;
use App\Helpers\FormatHelper;
use App\Enums\Eta;
use App\Services\Commands\CommandDispatcher;
use App\Helpers\TaskHelper;
use App\Services\ConnectionService;
use App\Helpers\PathHelper;

class StatusBar extends Component
{
    public $tasks = [];
    public $showErrorsForStatus = false;
    private $countTaskToShow = 5;

    protected $listeners = [
        'refresh-status-bar' => 'refresh'
    ];

    private function connectionService(): ConnectionService
    {
        return app(ConnectionService::class);
    }

    public function render()
    {
        return view('livewire.status-bar');
    }

    public function refresh()
    {

        $this->processEvents();

        $tasks = [];

        $tasksDir = Runtime::path('tasks');

        if (!is_dir($tasksDir)) {
            $this->tasks = [];
            return;
        }

        $files = glob($tasksDir . '/*.json');

        foreach ($files as $file) {

            $task = TaskHelper::read($file);

            if (!$task) {
                continue;
            }

            $speed = (float)($task['speed_raw'] ?? 0);

            $etaSeconds = null;
            if ($speed > 1024) {
                $remaining = max(0, ($task['total_bytes'] ?? 0) - ($task['copied_bytes'] ?? 0));
                $etaSeconds = (int)($remaining / $speed);
            }

            $started = $task['started_at'] ?? $task['created_at'] ?? time();
            $pausedTotal = $task['total_paused_seconds'] ?? 0;

            $status = $task['status'] ?? null;

            if (in_array($status, [
                TaskStatus::COMPLETED->value,
                TaskStatus::CANCELED->value,
                TaskStatus::ERROR->value,
            ])) {
                $end = $task['finished_at'] ?? $task['updated_at'] ?? time();
            } elseif ($status === TaskStatus::PAUSED->value) {
                $end = $task['paused_at'] ?? time();
            } else {
                $end = time();
            }

            $elapsedSeconds = (int) max(0, $end - $started - floor($pausedTotal));

            $elapsed = gmdate('H:i:s', $elapsedSeconds);

            $tasks[] = [
                'id'         => $task['id'] ?? basename($file, '.json'),
                'from'       => $task['from'] ?? '',
                'to'         => $task['to'] ?? '',
                'label'      => $task['label'] ?? '',
                'status'     => $task['status'] ?? 'running',
                'elapsed'    => $elapsed,
                'type'       => $task['type'] ?? 'Unknown',
                'info'       => $task['info'] ?? '',
                'errors'     => $task['errors'] ?? [],
                'created_at' => $task['created_at'] ?? time(),

                'progress' => $task['progress'] ?? 0,
                'speed'    => FormatHelper::speed($speed),
                'eta'      => $this->resolveEta($task, $etaSeconds),
            ];
        }

        usort($tasks, fn($a, $b) => $b['created_at'] <=> $a['created_at']);

        $this->tasks = array_slice($tasks, 0, $this->countTaskToShow);
    }

    public function showErrors($task_id)
    {
        $this->showErrorsForStatus = $this->showErrorsForStatus != $task_id
            ? $task_id
            : false;
    }

    private function processEvents(): void
    {
        $dir = Runtime::path('events');

        if (!is_dir($dir)) {
            return;
        }

        $files = glob($dir . '/*.json');

        if (!$files) {
            return;
        }

        sort($files);

        $handlers = [

            'dir.created' => function ($event) {

                $path = $event['path'] ?? null;

                if (!$path) {
                    return;
                }

                $path = PathHelper::normalize($path);

                if ($connectionId = ($event['connection_id'] ?? null)) {

                    $this->connectionService()
                        ->forgetConnectionCache(
                            $connectionId,
                            $path
                        );
                }

                $this->dispatch('modal.close');

                $this->dispatch(
                    'refresh-file-explorer',
                    $path,
                    connectionId: $connectionId
                );
            },

            'dir.error' => function ($event) {

                $this->dispatch(
                    'create-directory-error',
                    $event['message']
                );
            },

            'file.created' => function ($event) {

                $path = $event['path'] ?? null;

                if (!$path) {
                    return;
                }

                $path = PathHelper::normalize($path);

                if ($connectionId = ($event['connection_id'] ?? null)) {

                    $this->connectionService()
                        ->forgetConnectionCache(
                            $connectionId,
                            $path
                        );
                }

                $this->dispatch('modal.close');

                $this->dispatch(
                    'refresh-file-explorer',
                    $path,
                    connectionId: $connectionId
                );
            },

            'file.error' => function ($event) {

                $this->dispatch(
                    'create-file-error',
                    $event['message']
                );
            },

            'panel_refresh' => function ($event) {
                if ($connection = $this->connectionService()->getConnection()) {
                    $this->connectionService()->forgetConnectionCache($connection['id'], $event['path']);
                }

                $this->dispatch('refresh-file-explorer', $event['path']);
            },

            'conflict' => function ($event) {
                $this->dispatch('openModal', 'conflicts', $event);
            },

            'conflict_resolved' => function ($event) {
                app(CommandDispatcher::class)->dispatchSimple([
                    'type'    => 'resume',
                    'task_id' => $event['task_id'],
                ]);
            },
        ];

        foreach ($files as $file) {

            $event = json_decode(@file_get_contents($file), true);

            @unlink($file);

            if (!$event) {
                continue;
            }

            $type = $event['event'] ?? null;

            if ($type && isset($handlers[$type])) {
                $handlers[$type]($event);
            }
        }
    }

    private function resolveEta(array $task, ?int $etaSeconds): string
    {
        $status = $task['status'] ?? null;

        $map = [
            TaskStatus::COMPLETED->value => Eta::COMPLETED,
            TaskStatus::PAUSED->value    => Eta::PAUSED,
            TaskStatus::CANCELED->value  => Eta::CANCELED,
            TaskStatus::ERROR->value     => Eta::ERROR,
        ];

        if (isset($map[$status])) {
            return $map[$status]->label();
        }

        return  FormatHelper::eta($etaSeconds);
    }

    public function cancel(string $taskId): void
    {
        TaskHelper::update($taskId, function (&$task) {
            $task['status'] = \App\Enums\TaskStatus::CANCELED->value;
            $task['finished_at'] = time();
        });
    }

    public function pause(string $taskId): void
    {
        TaskHelper::update($taskId, function (&$task) {
            $task['status'] = TaskStatus::PAUSED->value;
            $task['paused_at'] = time();
        });

        app(CommandDispatcher::class)->dispatchSimple([
            'type' => TaskStatus::PAUSED->value,
            'task_id' => $taskId,
        ]);
    }

    public function resume(string $taskId): void
    {

        app(CommandDispatcher::class)->dispatchSimple([
            'type' => TaskStatus::RESUME->value,
            'task_id' => $taskId,
        ]);
    }
}