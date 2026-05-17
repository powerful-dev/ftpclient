<?php

namespace App\Services\FileActions;

interface FileActionInterface
{
    public function handle(array $payload): array|null;
}