<?php

namespace App\Workers\Core;

abstract class WorkerCore
{
    protected string $workerName;
    protected string $runtimeDir;
    protected string $runtimeWorkerDir;
    protected string $logFile;
    protected string $pidFile;
    protected string $parentFile;

    protected int $parentPid = 0;

    public function __construct(string $workerName)
    {
        // =========================
        // 1. Laravel bootstrap
        // =========================

        require __DIR__ . '/../../../vendor/autoload.php';

        $app = require __DIR__ . '/../../../bootstrap/app.php';
        $app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

        // =========================
        // 2. Worker init
        // =========================

        $this->workerName = $workerName;

        $runtimeDir = storage_path('runtime');

        $this->runtimeDir = $runtimeDir;

        $this->runtimeWorkerDir = $this->getWorkerDir();

        @mkdir($this->runtimeWorkerDir, 0777, true);

        $this->logFile    = $this->runtimeWorkerDir . '/log.log';
        $this->pidFile    = $this->runtimeWorkerDir . '/process.pid';
        $this->parentFile = $this->runtimeWorkerDir . '/parent.pid';

        if (file_exists($this->parentFile)) {
            $this->parentPid = (int) file_get_contents($this->parentFile);
        }
    }

    public function run(): void
    {


        file_put_contents($this->pidFile, getmypid());

        $this->log("START (pid=" . getmypid() . ")");

        register_shutdown_function(function () {
            $this->cleanupRuntimeDir();
        });

        while (true) {

            if (!$this->parentAlive($this->parentPid)) {
                $this->log("PARENT DEAD → EXIT");
                exit(0);
            }

            $this->loop();

            $this->sleep();
        }
    }

    protected function log(string $message): void
    {
        file_put_contents(
            $this->logFile,
            date('H:i:s') . ' ' . $message . PHP_EOL,
            FILE_APPEND
        );
    }

    protected function parentAlive(int $pid): bool
    {
        if ($pid <= 0) return false;

        if (PHP_OS_FAMILY === 'Windows') {
            exec('tasklist /FI "PID eq ' . $pid . '"', $out);
            return count($out) > 1;
        }

        return posix_kill($pid, 0);
    }

    protected function sleep(): void
    {
        usleep(200_000);
    }

    abstract protected function loop(): void;

    protected function cleanupRuntimeDir(): void
    {
        if (!is_dir($this->runtimeWorkerDir)) {
            return;
        }

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(
                $this->runtimeDir,
                \FilesystemIterator::SKIP_DOTS
            ),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($files as $file) {
            if ($file->isDir()) {
                @rmdir($file->getRealPath());
            } else {
                @unlink($file->getRealPath());
            }
        }

        @rmdir($this->runtimeDir);
    }

    protected function getBusDir(): string
    {
        return $this->runtimeDir . '/bus';
    }

    protected function getWorkerDir(): string
    {
        return $this->runtimeDir . '/workers/' . $this->workerName;
    }
}
