<?php

namespace App\Services\FileActions\Validators;

use App\Enums\FileActionError;

trait ReturnsValidationErrors
{
    protected function error(FileActionError $code, string $key, array $params = []): array
    {
        return [
            'error' => [
                'code' => $code,
                'key' => $key,
                'params' => $params,
            ]
        ];
    }
}