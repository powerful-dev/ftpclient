<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Services\ConnectionService;

class AppStartupCleanup
{
    public function handle(Request $request, Closure $next)
    {
        if (! $request->cookies->has('ftp_app_started')) {

            $cacheDir = storage_path('framework/cache/data');

            if (is_dir($cacheDir)) {
                $this->clearDirectory($cacheDir);
            }

            $tempDir = storage_path('app/temp');
            
            if (is_dir($tempDir)) {
                $this->clearDirectory($tempDir);
            }

            app(ConnectionService::class)->closeAll();

            session()->forget([
                'left-path',
                'right-path',
            ]);

            $response = $next($request);
            return $response->cookie('ftp_app_started', '1', 0);
        }

        return $next($request);
    }

    /**
    * Recursively delete all files and folders inside directory
    */
    protected function clearDirectory(string $dir): void
    {
        $items = scandir($dir);

        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
            continue;
        }

        $path = $dir . DIRECTORY_SEPARATOR . $item;

        if (is_dir($path)) {
            $this->clearDirectory($path);
            @rmdir($path);
        } else {
            @unlink($path);
        }
    }
}
}
