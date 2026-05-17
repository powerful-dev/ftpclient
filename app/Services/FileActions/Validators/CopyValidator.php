<?php

namespace App\Services\FileActions\Validators;

use App\Services\FileActions\Contracts\FileActionValidatorInterface;

class CopyValidator extends BaseValidator implements FileActionValidatorInterface
{

    public function validate(array $data): ?array
    {
        return
            $this->validateEmptyFiles($data)
            ?? $this->validateSameDirectory($data)
            ?? $this->validateLocalPermission(
                    $data['destinationDir'],
                    $data['connection'],
                    'Administrator permissions are required to write to :path',
                    [
                        'path' => $data['destinationDir']
                    ]
                );
    }
}