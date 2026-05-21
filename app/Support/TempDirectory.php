<?php

namespace App\Support;

class TempDirectory
{
    public static function path(): string
    {
        return storage_path('app/temp');
    }
}