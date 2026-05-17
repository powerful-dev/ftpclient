<?php

namespace App\Services;

use App\Helpers\PathHelper;
use Native\Laravel\Facades\Shell;
use League\Flysystem\Filesystem;

class FileOpenerService
{
    protected ConnectionService $connectionService;
    protected FileDownloadService $downloadService;

    public function __construct(
        ConnectionService $connectionService,
        FileDownloadService $downloadService
    ) {
        $this->connectionService = $connectionService;
        $this->downloadService   = $downloadService;
    }


    public function open(
        array $file,
        bool $openInExplorer = false,
        ?string $taskId = null,
        ?Filesystem $fs = null
    ): void {

        $path = $file['path'] ?? null;

        if (!$path) {
            return;
        }

        // remote → temp
        if ($fs) {
            $path = $this->downloadToTemp($file, $fs, $taskId);
            if (!$path) {
                return;
            }
        } else {
            $path = $file['path'];
        }

        $this->openLocal($path, $openInExplorer);
    }

    /**
     * Только локальное открытие
     */
    private function openLocal(string $path, bool $openInExplorer): void
    {
        $realPath = realpath($path);

        if (!$realPath || !file_exists($realPath)) {
            return;
        }

        switch (PHP_OS_FAMILY) {
            case 'Windows':
                $this->openWindows($realPath, $openInExplorer);
                break;

            case 'Darwin':
                $escaped = escapeshellarg($realPath);
                exec($openInExplorer ? "open -R $escaped" : "open $escaped");
                break;

            case 'Linux':
                if ($openInExplorer) Shell::showInFolder($realPath);
                else Shell::openFile($realPath);
                break;
        }
    }

    private function openWindows(string $path, bool $openInExplorer): void
    {
        $path = str_replace('/', '\\', $path);

        $command = $openInExplorer
            ? 'explorer /select,"' . $path . '"'
            : 'cmd /c start "" "' . $path . '"';

        proc_open($command, [], $pipes);
    }

    /**
     * Remote → temp
     */
    private function downloadToTemp(
        array $file,
        Filesystem $fs,
        ?string $taskId
    ): ?string {

        $baseTempDir = storage_path('app/temp');

        if (!is_dir($baseTempDir)) {
            mkdir($baseTempDir, 0755, true);
        }

        $fileName = basename($file['path']);
        $localPath = PathHelper::normalize($baseTempDir . '/' . $fileName);

        if (is_file($localPath)) {
            try {
                @unlink($localPath);
            } catch (\Throwable $e) {
                logger('file_delete_failed', [
                    'path' => $localPath,
                    'error' => $e->getMessage()
                ]);
            }
        }

        $this->downloadService->download(
            files: [$file],
            localDest: $baseTempDir,
            fs: $fs,
            taskId: $taskId
        );

        return is_file($localPath) ? $localPath : null;
    }
}
