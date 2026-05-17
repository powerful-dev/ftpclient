<?php

namespace App\Providers;

use Native\Laravel\Facades\Window;
use Symfony\Component\Process\Process;
use App\Support\Runtime;

class NativeAppServiceProvider
{
    protected static array $workers = [];

    public function boot(): void
    {
        Window::open()
            ->hideMenu()
            ->maximized()
            ->route('init');

        foreach ([
            Runtime::base(),
            Runtime::workers(),
            Runtime::bus()
        ] as $dir) {
            @mkdir($dir, 0777, true);
        }

        foreach (config('workers.pool') as $worker => $count) {

            for ($i = 0; $i < $count; $i++) {

                $name = $i === 0
                    ? $worker
                    : "{$worker}_{$i}";

                $this->startWorker(
                    $name,
                    'resources/workers/' . $worker . '.php'
                );
            }
        }   
    }

    protected function startWorker(string $name, string $relativePath): void
    {
        if (isset(self::$workers[$name])) {
            return;
        }

        $script = base_path($relativePath);

        if (!is_file($script)) {
            return;
        }

        $runtimeWorkerDir = Runtime::workers() . '/' . $name;
        @mkdir($runtimeWorkerDir, 0777, true);

        file_put_contents(
            $runtimeWorkerDir . '/parent.pid',
            (string) getmypid(),
            LOCK_EX
        );

        $process = $this->makeProcess($script, $name);

        $process->disableOutput();
        $process->start();

        self::$workers[$name] = $process;
    }

    protected function makeProcess(
        string $script,
        string $name
        ): Process {

        $php = PHP_BINARY;

        if (PHP_OS_FAMILY === 'Windows') {

            $phpDir = base_path(
                'resources/php-bin/bin/win/x64'
            );

            $phpExe = $phpDir . '/php.exe';

            return new Process([
                'cmd.exe',
                '/c',
                'start',
                '/B',
                $phpExe,
                '-c',
                $phpDir . '/php.ini',
                $script,
                $name
            ]);
        }

        return new Process([
            $php,
            $script,
            $name
        ]);
    }
}