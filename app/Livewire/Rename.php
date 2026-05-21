<?php

namespace App\Livewire;

use Livewire\Component;

class Rename extends Component
{
    public string $name = '';

    public string $panel = '';

    public ?string $error = null;

    public array $payload = [];

    protected $listeners = [
        'rename-error' => 'handleError',
    ];

    public function mount(): void
    {
        $this->panel = $this->payload['panel'] ?? '';
        $this->name = $this->payload['name'] ?? '';

        $this->dispatch('focus-input');
    }

    public function render()
    {
        return view('livewire.rename');
    }

    public function save(): void
    {
        $this->error = null;

        if (trim($this->name) === '') {
            $this->error = __('validation.name.required');

            return;
        }

        $this->dispatch(
            'rename',
            panel: $this->panel,
            newName: $this->name,
            oldPath: $this->payload['path'] ?? ''
        );
    }

    public function handleError(string $message): void
    {
        $this->error = $message;
    }
}
