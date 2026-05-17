<?php

namespace App\Enums;

enum TaskStatus: string
{
    case RUNNING   = 'running';
    case COMPLETED = 'completed';
    case ERROR     = 'error';
    case PAUSED    = 'paused';
    case CANCELED  = 'cancelled';
    case RESUME    = 'resume';
}