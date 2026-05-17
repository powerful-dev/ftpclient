<?php

namespace App\Services\FileActions\Validators;

use App\Services\FileActions\Contracts\FileActionValidatorInterface;

class MoveValidator extends BaseValidator implements FileActionValidatorInterface
{
    use ReturnsValidationErrors;

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