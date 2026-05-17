<?php

namespace App\Livewire;

use Livewire\Component;
use App\Services\LanguageService;
use App\Enums\Language;

class Settings extends Component
{

    public $languages;
    public $language;

    public function mount()
    {
        $this->languages = Language::options();

        $languageService = new LanguageService();
        $lang = $languageService->getCurrent();

        $this->language = $lang->value;
    }

    public function render()
    {
        return view('livewire.settings');
    }

    public function setLanguage()
    { 
        $languageService = new LanguageService();
        $languageService->setCurrent($this->language);
        $this->dispatch('setLanguage', ['code' => $this->language]);
    }
}
