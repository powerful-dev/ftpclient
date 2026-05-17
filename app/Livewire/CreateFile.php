<?php

namespace App\Livewire;

use Livewire\Component;

class CreateFile extends Component
{

    public $name;
    public $panel;
    public ?string $error = null;

    public array $payload = [];

    protected $listeners = [
        'create-file-error' => 'handleError',
    ];

    public function mount()
    {
        $this->panel = $this->payload['panel'] ?? null;
        
        $this->dispatch('focus-input');
    }

    public function render()
    {
        return view('livewire.create-file');
    }

    public function save()
    {

        $this->error = null;

        $this->dispatch(
            'create-file', 
            panel: $this->panel, 
            name: $this->name
        );
    }

    public function handleError($message)
    {
 
        $this->error = $message;
    }
}
