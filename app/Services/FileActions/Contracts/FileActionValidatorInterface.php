<?php

namespace App\Services\FileActions\Contracts;

interface FileActionValidatorInterface
{
    public function validate(array $data): ?array;
}