<?php

namespace App\Enums;

enum Protocol: string
{

    case FTP  = 'FTP';
    case FTPS = 'FTPS';
    case SFTP = 'SFTP';

    public function label(): string
    {
        return match ($this) {
            self::FTP => 'FTP',
            self::FTPS => 'FTPS',
            self::SFTP => 'SFTP',
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