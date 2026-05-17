<?php

namespace App\Services\FileActions\Validators;

use App\Enums\FileActionError;
use App\Helpers\FileHelper;
use App\Models\Connection;

abstract class BaseValidator
{
    use ReturnsValidationErrors;

    protected function validateEmptyFiles(array $data): ?array
    {
        if (empty($data['files'])) {
            return $this->error(
                FileActionError::EMPTY_FILES,
                'No valid files selected'
            );
        }

        return null;
    }

    protected function validateSameDirectory(array $data): ?array
    {

        if ($data['sourceDir'] === $data['destinationDir']) {
            return $this->error(
                FileActionError::SAME_DIRECTORY,
                'Cannot write to the same directory :directory',
                ['directory' => $data['destinationDir']]
            );
        }

        return null;
    }

    protected function validateLocalPermission(
        ?string $path,
        Connection|array|null $connection,
        string $message,
        array $params = []
    ): ?array 
    {
        if (
            is_null($connection) &&
            !FileHelper::isDirectoryWritable($path)
        ) {

            if (PHP_OS_FAMILY === 'Windows') {

                return $this->elevationRequired(
                    $message,
                    $params
                );
            }

            return $this->error(
                FileActionError::LOCAL_PERMISSION_DENIED,
                'Cannot write to :destinationPath',
                ['destinationPath' => $path]
            );
        }

        return null;
    }

    protected function elevationRequired(string $message, array $params = []): array
    {
        return [
            'elevation' => [
                'message' => __($message, $params),
            ]
        ];
    }
}