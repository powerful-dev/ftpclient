<?php

namespace App\Services\FileActions;

use App\Services\Commands\CommandDispatcher;
use App\Services\FileActions\Validators\DeleteValidator;
use App\Services\ElevatedDeleteService;

class DeleteAction implements FileActionInterface
{
    public function __construct(
        protected FileActionContextBuilder $builder,
        protected DeleteValidator $validator,
        protected CommandDispatcher $dispatcher,
        protected ElevatedDeleteService $elevatedDelete
    ) {}

    public function handle(array $payload): ?array
    {
        $data = $this->builder->build($payload);

        if (empty($data)) {
            return null;
        }

        $isElevated = (bool)($payload['elevated'] ?? false);

        if (!$isElevated) {

            if ($error = $this->validator->validate($data)) {
                return $error;
            }
        }

        if ($isElevated) {
            return $this->elevatedDelete->handle($payload);
        }

        $this->dispatcher->dispatch($data, [
            'type' => 'delete',
            'init' => true, // is_null($data["connection"]),
        ]);

        return ['success' => true];
    }
}