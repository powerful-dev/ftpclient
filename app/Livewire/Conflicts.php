<?php

namespace App\Livewire;

use Livewire\Component;
use \App\Helpers\PathHelper;
use App\Helpers\TaskHelper;
use \App\Services\EventService;

class Conflicts extends Component
{
    public array $payload = [];

    public array $sourceParts = [];
    public array $destParts = [];
    public array $diff = [];

    public bool $renameMode = false;
    public string $newName = '';

    public function render()
    {
        return view('livewire.conflicts');
    }


    public function mount()
    {
        $src = $this->payload['source'] ?? '';
        $dst = $this->payload['destination'] ?? '';

        $this->sourceParts = PathHelper::splitPath($src);
        $this->destParts   = PathHelper::splitPath($dst);

        $this->diff = PathHelper::diffBase($src, $dst);
    }

    public function choose(string $action)
    {

        $taskId = $this->payload['task_id'];

        $src = $this->payload['source'];

        $newName = trim($this->newName ?? '');

        TaskHelper::update($taskId, function (&$task) use ($action, $src, $newName) {

            // --- per-file decisions ---
            if (in_array($action, ['overwrite', 'skip'])) {
                $task['file_decisions'][$src] = [
                    'action' => $action,
                ];
            }

            if ($action === 'rename' && $newName !== '') {

                $task['file_decisions'][$src] = [
                    'action' => 'rename',
                    'name'   => $newName,
                ];
            }

            // --- global decisions ---
            if (in_array($action, ['skip_all', 'overwrite_all'])) {
                $task['options'][$action] = true;
            }

            $task['conflict_resolved'] = true;
        });

        app(EventService::class)->emit([
            'event'   => 'conflict_resolved',
            'task_id' => $taskId,
            'payload' => ['action' => $action],
        ]);

        $this->dispatch('modal.close');
    }

    public function enableRename(): void
    {
        $this->renameMode = true;

        $this->newName = basename($this->payload['destination'] ?? '');
    }

    public function applyRename(): void
    {
        if (empty(trim($this->newName))) {
            return;
        }

        $this->choose('rename');

        $this->dispatch('modal.close');
    }
}