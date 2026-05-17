<?php

namespace App\Livewire;

use Livewire\Component;

class ElevationRequired extends Component
{
    public array $payload = [];

    public function render()
    {
        return view('livewire.elevation-required');
    }

    public function confirm(): void
    {
        $actionPayload = $this->payload['actionPayload'] ?? [];

        $actionPayload['elevated'] = true;

        $this->dispatch('start-elevated-action', payload: $actionPayload);

        $this->dispatch('modal.close');
    }

    public function cancel(): void
    {
        $this->dispatch('modal.close');
    }
}