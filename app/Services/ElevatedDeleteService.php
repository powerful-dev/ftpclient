<?php

namespace App\Services;

use App\Helpers\PowershellHelper;
use App\Services\EventService;

class ElevatedDeleteService
{
    public function __construct(
        protected EventService $events
    ) {}

    /**
     * Delete files/directories using elevated PowerShell.
     */
    public function handle(array $payload): array
    {
        $paths = [];

        foreach ($payload['items'] as $file) {
            $paths[] = $file;
        }

        $script = PowershellHelper::buildDeleteScript($paths);

        $result = PowershellHelper::runScript($script);

        if ($result['code'] === 0) {

            $this->events->emit([
                'event' => 'panel_refresh',
                'path'  => $payload['targetBasePath'],
            ]);

            return ['success' => true];
        }

        return [
            'success' => false,
            'message' => implode("\n", $result['output'] ?? []),
        ];
    }
}