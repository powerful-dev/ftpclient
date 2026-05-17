<?php

namespace App\Enums;

enum Eta: string
{
    case PREPARING = 'eta.preparing';
    case DOWNLOADING = 'eta.downloading';
    case COMPLETED = 'eta.completed';
    case ERROR     = 'eta.error';
    case CANCELED  = 'eta.canceled';
    case PAUSED    = 'eta.paused';

    public function label(): string
    {
        return __($this->value);
    }
}