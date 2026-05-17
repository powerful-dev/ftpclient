<?php

namespace App\Enums;

enum AuthenticationType: string
{

    case PASSWORD  = 'password';
    case SSH_KEY = 'ssh_key';

    public function label(): string
    {
        return match ($this) {
            self::PASSWORD => 'password',
            self::SSH_KEY => 'SSH Key',
        };
    }

    public static function options(): array
    {
        $result = [];
        foreach (self::cases() as $case) {
            $result[$case->value] = $case->label();
        }
        return $result;
    }
}