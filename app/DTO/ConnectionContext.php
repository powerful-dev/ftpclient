<?php

namespace App\DTO;

use League\Flysystem\Filesystem;

class ConnectionContext
{
    public Filesystem $fs;
    public mixed $adapter;
    public int $connectionId;

    public function __construct(Filesystem $fs, mixed $adapter, int $connectionId)
    {
        $this->fs = $fs;
        $this->adapter = $adapter;
        $this->connectionId = $connectionId;
    }
}