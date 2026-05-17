<?php

require __DIR__ . '/../../vendor/autoload.php';

$workerName = $argv[1];

use App\Workers\Core\WorkerCore;
use App\Workers\Watchers\FileWatcher;
use App\Support\Runtime;

class FileWatcherWorker extends WorkerCore
{

    private FileWatcher $watcher;

    public function __construct(string $workerName)
    {
        parent::__construct($workerName);

        $this->watcher = new FileWatcher(
            runtimeDir: Runtime::base(),
            tasksDir: Runtime::path('tasks'),
            commandsDir: Runtime::path('commands'),
            stabilitySeconds: 2
        );
    }

    protected function loop(): void
    {

        $this->watcher->check();
    }
}

(new FileWatcherWorker($workerName))->run();
