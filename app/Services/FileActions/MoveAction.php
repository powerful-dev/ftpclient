<?php

namespace App\Services\FileActions;

use App\Services\Commands\CommandDispatcher;
use App\Services\FileActions\Validators\MoveValidator;
use App\Services\ElevatedMoveService;

class MoveAction implements FileActionInterface
{
    public function __construct(
        protected FileActionContextBuilder $builder,
        protected MoveValidator $validator,
        protected CommandDispatcher $dispatcher,
        protected ElevatedMoveService $elevatedMove
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
            return $this->elevatedMove->handle($payload);
        }

        $this->dispatcher->dispatch($data, [
            'type' => 'move',
            'init' => is_null($data["connection"]),
        ]);

        return ['success' => true];
    }
}