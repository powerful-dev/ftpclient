<?php

namespace App\Services\FileActions;

use App\Services\Commands\CommandDispatcher;
use App\Services\FileActions\Validators\CopyValidator;
use App\Services\ElevatedCopyService;

class CopyAction implements FileActionInterface
{
    public function __construct(
        protected FileActionContextBuilder $builder,
        protected CopyValidator $validator,
        protected CommandDispatcher $dispatcher,
        protected ElevatedCopyService $elevatedCopy
    ) {}

    public function handle(array $payload): ?array
    {

        $data = $this->builder->build($payload);

        if (empty($data)) {
            return null;
        }

        $isElevated = (bool)($payload['elevated'] ?? false);

        // Skip local permission validation after elevation confirmation
        if (!$isElevated) {

            if ($error = $this->validator->validate($data)) {
                return $error;
            }
        }

        if ($isElevated) {
            return $this->elevatedCopy->handle($payload);
        }

        $isDownload = $this->isDownload($data);

        $this->dispatcher->dispatch($data, [
            'type' => $isDownload ? 'download' : 'copy',
            'init' => !$isDownload,
        ]);

        return ['success' => true];
    }

    private function isDownload(array $data): bool
    {
        return $data['sourceType'] === 'remote'
            && $data['destinationType'] === 'local';
    }
}