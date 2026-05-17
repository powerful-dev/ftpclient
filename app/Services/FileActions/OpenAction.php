<?php

namespace App\Services\FileActions;

use App\Services\FileOpenerService;
use App\Services\ConnectionService;

class OpenAction implements FileActionInterface
{
    public function __construct(
        protected FileOpenerService $opener,
        protected ConnectionService $connectionService,
    ) {}

    public function handle(array $payload): ?array
    {

        $files = $payload['items'] ?? [];

        if (empty($files)) {
            return null;
        }

        $file = $files[0];

        $openInExplorer = $payload['openInExplorer'] ?? false;

        $filesystem = null;

        if (($payload['sourcePanel'] ?? null) === 'right') {
            $filesystem = $this->connectionService->getFilesystem();
        }

        $this->opener->open(
            file: $file,
            openInExplorer: $openInExplorer,
            fs: $filesystem
        );

        return ['success' => true];
    }
}