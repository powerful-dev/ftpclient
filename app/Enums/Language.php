<?php

namespace App\Enums;

enum Language: string
{
    case EN = 'en';
    case RU = 'ru';

    public function label(): string
    {
        return match ($this) {
            self::EN => 'English',
            self::RU => 'Русский',
        };
    }

    /**
     * All cases
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * All cases like [value => label]
     */
    public static function options(): array
    {
        $result = [];
        foreach (self::cases() as $case) {
            $result[$case->value] = $case->label();
        }
        return $result;
    }
}
