<?php

namespace App\Services;

use App\Helpers\PowershellHelper;
use App\Services\EventService;

class ElevatedCopyService
{
    public function __construct(
        protected EventService $events
    ) {}

    public function handle(array $payload): array
    {

        $operations = [];

        foreach ($payload['items'] as $file) {

            $operations[] = [
                'from' => $file['path'],
                'to' => rtrim($payload['targetBasePath'], '/\\')
                    . DIRECTORY_SEPARATOR
                    . $file['name'],
            ];
        }

        $script = PowershellHelper::buildCopyScript($operations);

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