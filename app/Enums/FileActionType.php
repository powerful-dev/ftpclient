<?php

namespace App\Enums;

enum FileActionType: string
{
    case COPY = 'copy';
    case MOVE = 'move';
    case DELETE = 'delete';
    case RENAME = 'rename';

    case CREATE_DIRECTORY = 'createDirectory';
    case CREATE_FILE = 'createFile';

    case OPEN = 'open';

    public function isUi(): bool
    {
        return match ($this) {
            self::CREATE_DIRECTORY,
            self::CREATE_FILE,
            self::RENAME => true,
            default => false,
        };
    }

    public function isOpen(): bool
    {
        return match ($this) {
            self::OPEN => true,
            default => false,
        };
    }

    public function isFileOperation(): bool
    {
        return match ($this) {
            self::COPY,
            self::MOVE,
            self::DELETE => true,
            default => false,
        };
    }
}