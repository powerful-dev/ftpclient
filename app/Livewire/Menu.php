<?php

namespace App\Livewire;

use Livewire\Component;
use Illuminate\Support\Facades\App;

class Menu extends Component
{
    protected $listeners = ['setLanguage'];

    public function render()
    {
        return view('livewire.menu');
    }

    public function showSettings()
    {
        $this->dispatch('openModal', modal: 'settings');
    }

    public function showConnections()
    {
        $this->dispatch('openModal', modal: 'connections');
    }

    public function setLanguage($event)
    {
        App::setLocale($event['code']);
    }
}
