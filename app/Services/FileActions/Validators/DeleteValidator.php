<?php

namespace App\Services\FileActions\Validators;

use App\Services\FileActions\Contracts\FileActionValidatorInterface;

class DeleteValidator extends BaseValidator implements FileActionValidatorInterface
{

    public function validate(array $data): ?array
    {
        return 
            $this->validateEmptyFiles($data)
            ?? $this->validateLocalPermission(
                    $data['sourceDir'],
                    $data['connection'],
                    'Administrator permissions are required to delete :path',
                    [
                        'path' => $data['sourceDir']
                    ]
                );
    }
}