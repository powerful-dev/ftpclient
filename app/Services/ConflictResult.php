<?php

namespace App\Services;

/**
 * Value object representing a resolved conflict decision.
 */
class ConflictResult
{
    public function __construct(
        public string $action,
        public ?string $path = null,
        public ?string $src = null,
        public ?string $dst = null,
    ) {}

    public static function proceed(string $path): self
    {
        return new self('proceed', $path);
    }

    public static function overwrite(string $path): self
    {
        return new self('overwrite', $path);
    }

    public static function skip(): self
    {
        return new self('skip');
    }

    public static function rename(string $path): self
    {
        return new self('rename', $path);
    }

    public static function conflict(string $src, string $dst): self
    {
        return new self('conflict', null, $src, $dst);
    }
}