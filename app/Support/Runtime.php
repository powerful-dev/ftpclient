<?php

namespace App\Support;

class Runtime
{
    public static function base(): string
    {
        return config('runtime.base');
    }

    public static function bus(): string
    {
        return config('runtime.bus');
    }

    public static function workers(): string
    {
        return config('runtime.workers');
    }

    public static function path(string $dir): string
    {
        return self::bus() . '/' . config("runtime.dirs.$dir");
    }
}