<?php

namespace App\Exceptions;

class ConflictException extends \Exception
{
    public function __construct(
        private string $source,
        private string $destination
    ) {
        parent::__construct("File conflict: {$destination}");
    }

    public function getSource(): string
    {
        return $this->source;
    }

    public function getDestination(): string
    {
        return $this->destination;
    }
}