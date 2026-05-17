<?php

namespace App\Services;

class FileActionHandler
{

    protected array $actions = [
        'copy'   => \App\Services\FileActions\CopyAction::class,
        'move'   => \App\Services\FileActions\MoveAction::class,
        'delete' => \App\Services\FileActions\DeleteAction::class,
        'open'   => \App\Services\FileActions\OpenAction::class,
    ];

    public function handle(array $payload): ?array
    {
        $type = $payload['type'] ?? null;

        if (!$type || !isset($this->actions[$type])) {
            return null;
        }

        return app($this->actions[$type])->handle($payload);
    }
}
