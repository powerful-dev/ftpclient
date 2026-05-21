<?php

namespace App\Services\FileActions;

use App\Helpers\PathHelper;
use App\Services\ConnectionService;
use Illuminate\Support\Str;
use App\Support\TempDirectory;

class FileActionContextBuilder
{
    public function __construct(
        protected ConnectionService $connectionService
    ) {}

    public function build(array $payload): array
    {
        $items = $payload['items'] ?? [];
        $targetPath = $payload['targetPath'] ?? null;
        $sourcePanel = $payload['sourcePanel'] ?? 'left';
        $targetPanel = $payload['targetPanel'] ?? null;
        $sourcePath = $payload['sourcePath'] ?? null;
        $targetBasePath = $payload['targetBasePath'] ?? '/';
        $type = $payload['type'] ?? null;

        if (empty($items)) {
            return [];
        }

        $files = collect($items)->map(fn ($f) => [
            'name' => $f['name'],
            'path' => PathHelper::normalize($f['path']),
            'unixPath' => PathHelper::toUnixPath(
                PathHelper::normalize($f['path'])
            ),
        ])->values()->all();

        $destinationPanel = $targetPanel ?? (
            $targetPath !== null
                ? $sourcePanel
                : ($sourcePanel === 'left' ? 'right' : 'left')
        );

        $source = $this->getPanelContext(
            $sourcePanel,
            null,
            $sourcePath
        );

        $destination = $this->resolveDestination(
            type: $type,
            source: $source,
            destinationPanel: $destinationPanel,
            targetPath: $targetPath,
            targetBasePath: $targetBasePath,
        );

        $connection = $this->resolveConnection(
            $source,
            $destination,
            $destinationPanel,
            $type
        );

        return [
            'files' => $files,
            'sourcePanel' => $sourcePanel,
            'destinationPanel' => $destinationPanel,
            'sourceType' => $source['type'],
            'destinationType' => $destination['type'],
            'sourceDir' => $source['path'],
            'destinationDir' => $destination['path'],
            'connection' => $connection,
            'taskId' => (string) Str::uuid(),
        ];
    }

    private function getPanelContext(
        string $panel,
        ?string $targetPath,
        string $fallbackPath
    ): array {
        $connectionId = $this->connectionService->getActiveConnectionId();

        return [
            'type' => $panel === 'right' && $connectionId ? 'remote' : 'local',
            'path' => PathHelper::normalize($targetPath ?? $fallbackPath),
        ];
    }

    private function resolveConnection(
        array $source,
        array $destination,
        string $destinationPanel,
        ?string $type
    ) {
        if ($type === 'delete') {
            return $source['type'] === 'remote'
                ? $this->connectionService->getObject()
                : null;
        }

        $needsConnection =
            ($destination['type'] === $source['type'] && $destinationPanel === 'right')
            || ($destination['type'] !== $source['type']);

        return $needsConnection
            ? $this->connectionService->getObject()
            : null;
    }

    private function resolveDestination(
        ?string $type,
        array $source,
        string $destinationPanel,
        ?string $targetPath,
        string $targetBasePath
    ): array {

        if ($type === 'open' && $source['type'] === 'remote') {
            return [
                'type' => 'local',
                'path' => PathHelper::normalize(
                    TempDirectory::path()
                ),
            ];
        }

        return $this->getPanelContext(
            $destinationPanel,
            $targetPath,
            $targetBasePath
        );
    }
}
