<?php

namespace App\Services\FileActions;

use App\Services\FileOpenerService;
use App\Services\ConnectionService;
use App\Services\Commands\CommandDispatcher;

class OpenAction implements FileActionInterface
{
    public function __construct(
        protected FileActionContextBuilder $builder,
        protected FileOpenerService $opener,
        protected CommandDispatcher $dispatcher,
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

        $data = $this->builder->build($payload);

        $isDownload = $this->isDownload($data);

        if ($isDownload) {

            $this->dispatcher->dispatch($data, [
                'type' => 'open',
                'init' => false,
            ]);

            return ['success' => true];
        }

        $this->opener->open(
            file: $file,
            openInExplorer: $openInExplorer,
            fs: $filesystem
        );

        return ['success' => true];
    }

    private function isDownload(array $data): bool
    {
        return $data['sourceType'] === 'remote'
            && $data['destinationType'] === 'local';
    }
}