<?php

namespace App\Livewire;

use Livewire\Component;

class Error extends Component
{

    public array $errors = [];

    public array $payload = [];

    public function mount(array $errors = [])
    {
        $this->errors = $errors;
    }

    public function render()
    {
        return view('livewire.error');
    }
}