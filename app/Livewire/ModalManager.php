<?php

namespace App\Livewire;

use Livewire\Component;

class ModalManager extends Component
{

    protected $listeners = [
        'openModal',
        'modal.close' => 'closeModal',
    ];

    public array $allowedModals = [
        'settings',
        'conflict',
        'create-directory',
        'create-file',
        'error',
        'connections',
        'conflicts',
        'elevation-required',
    ];

    public ?string $activeModal = null;
    public array $payload = [];

    public function render()
    {
        return view('livewire.modal-manager');
    }

    public function openModal(string $modal, array $payload = [])
    {
        if (empty($modal)) {
            return;
        }

        if ($this->activeModal === $modal) {
            $this->payload = $payload;
            return;
        }

        $this->activeModal = $modal;
        $this->payload = $payload;
    }

    public function closeModal()
    {
        $this->activeModal = null;
        $this->payload = [];
    }
}
