<?php

namespace App\Services;

use App\Enums\Language;
use App\Models\Setting;
use Illuminate\Support\Facades\App;

class LanguageService
{
    public function getCurrent(): Language
    {
        $locale = Setting::where('key', 'language')->value('value');

        return $locale && Language::tryFrom($locale)
            ? Language::from($locale)
            : $this->getDefault();
    }

    public function getDefault(): Language
    {
        return Language::EN;
    }

    public function setCurrent(string|Language $language): void
    {
        if ($language instanceof Language) {
            $locale = $language->value;
        } elseif (Language::tryFrom($language)) {
            $locale = $language;
        } else {
            return;
        }

        Setting::updateOrCreate(
            ['key' => 'language'],
            ['value' => $locale]
        );

        // for workers
        file_put_contents(
            storage_path('runtime/locale.signal'),
            json_encode(['locale' => $locale]),
            LOCK_EX
        );

        App::setLocale($locale);
    }
}

