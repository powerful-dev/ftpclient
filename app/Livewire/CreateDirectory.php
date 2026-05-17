<?php

namespace App\Livewire;

use Livewire\Component;

class CreateDirectory extends Component
{

    public $name = '';
    public $panel;
    public ?string $error = null;

    public array $payload = [];

    protected $listeners = [
        'create-directory-error' => 'handleError',
    ];

    public function mount()
    {
        $this->panel = $this->payload['panel'] ?? null;

        $this->dispatch('focus-input');
    }

    public function render()
    {
        return view('livewire.create-directory');
    }

    public function save()
    {
        $this->error = null;

        $this->dispatch(
            'create-directory',
            panel: $this->panel,
            name: $this->name
        );
    }

    public function handleError($message)
    {
 
        $this->error = $message;
    }
}
