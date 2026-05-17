<?php

namespace App\Services;

use App\Helpers\PowershellHelper;
use App\Services\EventService;

class ElevatedMoveService
{
    public function __construct(
        protected EventService $events
    ) {}

    public function handle(array $payload): array
    {
        $items = $payload['items'] ?? [];
        $targetPath = $payload['targetBasePath'] ?? $payload['targetPath'] ?? null;

        if (empty($items) || !$targetPath) {
            return [
                'success' => false,
                'message' => 'Invalid move payload'
            ];
        }

        $operations = [];

        foreach ($payload['items'] as $file) {

            $operations[] = [
                'from' => $file['path'],
                'to' => rtrim($targetPath, '/\\')
                    . DIRECTORY_SEPARATOR
                    . $file['name'],
            ];
        }

        $script = PowershellHelper::buildMoveScript($operations);

        $result = PowershellHelper::runScript($script);
    
        if ($result['code'] === 0) {

            $this->events->emit([
                'event' => 'panel_refresh',
                'path'  => $targetPath,
            ]);

            return ['success' => true];
        }

        return [
            'success' => false,
            'message' => implode("\n", $result['output'] ?? []),
        ];
    }
}