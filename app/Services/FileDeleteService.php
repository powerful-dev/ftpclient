<?php

namespace App\Services;

use App\Helpers\PathHelper;
use App\Traits\TaskTrait;
use League\Flysystem\Filesystem;
use App\Services\EventService;

class FileDeleteService
{
    use TaskTrait;

    protected string $taskId;

    private $from = '', $to = '';

    public function __construct(private EventService $events)
    {

    }

    public function delete(array $files, string $directory, string $taskId, ?Filesystem $fs = null): void
    {
        $this->taskId = $taskId;

        try {
            if (!is_null($fs)) {
                $this->deleteFromRemote($files, $fs);
            } else {
                $this->deleteFromLocal($files);
            }
        } finally {

            // Notify UI that directory content changed
            $this->events->emit([
                'event' => 'panel_refresh',
                'path'  => $directory,
            ]);
        }
    }

    private function deleteFromRemote(array $files, Filesystem $fs): void
    {
        foreach ($files as $file) {
            $path = PathHelper::encode(PathHelper::normalize($file['path']));

            if ($fs->fileExists($path)) {
                $this->deleteRemoteFile($fs, $path);
                continue;
            }

            if ($fs->directoryExists($path)) {
                $this->deleteRemoteDirectory($fs, $path);
            }
        }
    }


    private function deleteRemoteFile(
        Filesystem $fs,
        string $path
    ): void {

        $this->from = PathHelper::decode($path);

        try {

            $fs->delete($path);

            $this->updateTask(function (&$task) use ($path) {

                $this->progress()->advanceItem($task, $path);
            });

        } catch (\Throwable $e) {

            $this->addTaskError(
                "Не удалось удалить файл: {$this->from}"
            );
        }
    }

    private function deleteRemoteDirectory(
        Filesystem $fs,
        string $path
    ): void {

        $this->deleteRemoteDirectoryContents($fs, $path);

        try {

            $fs->deleteDirectory($path);

            $this->updateTask(function (&$task) use ($path) {

                $this->progress()->advanceItem($task, $path);
            });

        } catch (\Throwable $e) {

            $this->addTaskError(
                "Не удалось удалить директорию: {$path}"
            );
        }
    }

    private function deleteRemoteDirectoryContents(
        Filesystem $fs,
        string $path
    ): void {

        foreach ($fs->listContents($path, false) as $item) {

            if ($item->isDir()) {

                $this->deleteRemoteDirectoryContents(
                    $fs,
                    $item->path()
                );

                try {

                    $fs->deleteDirectory($item->path());

                } catch (\Throwable $e) {

                    $this->addTaskError(
                        "Не удалось удалить директорию: {$item->path()}"
                    );
                }

                continue;
            }

            if ($item->isFile()) {

                try {

                    $fs->delete($item->path());

                } catch (\Throwable $e) {

                    $this->addTaskError(
                        "Не удалось удалить файл: {$item->path()}"
                    );
                }
            }
        }
    }

    private function deleteFromLocal(array $files): void
    {

        foreach ($files as $file) {
            $path = $file['path'];

            if (is_file($path)) {
                $this->deleteLocalFile($path);
            } elseif (is_dir($path)) {
                $this->deleteLocalDirectory($path);
            }
        }
    }

    private function deleteLocalFile(string $path): bool
    {
        $this->from = $path;

        try {

            if (!file_exists($path)) {
                return true;
            }

            if (unlink($path)) {

                $this->updateTask(function (&$task) use ($path) {
                    $this->progress()->advanceItem($task, $path);
                });

                return true;
            }
        } catch (\Throwable $e) {

            logger()->warning("Delete failed: {$path}", [
                'error' => $e->getMessage()
            ]);
        }

        return false;
    }

    private function deleteLocalDirectory(string $path): bool
    {
        $this->from = $path;
        $success = true;

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $item) {
            $itemPath = $item->getPathname();

            if ($item->isFile()) {
                try {
                    if (unlink($itemPath)) {
                        $this->updateTask(function (&$task) use ($itemPath) {
                            $this->progress()->advanceItem($task, $itemPath);
                        });

                    } else {
                        $success = false;
                    }
                } catch (\Throwable $e) {
                    $success = false;

                    $this->addTaskError("Failed to delete file: {$itemPath}");
                }
            } else {
                if (@rmdir($itemPath)) {
                    $this->updateTask(function (&$task) use ($itemPath) {
                        $this->progress()->advanceItem($task, $itemPath);
                    });
                } else {
                    $success = false;
                }
            }
        }

        if (@rmdir($path)) {
            $this->updateTask(function (&$task) use ($itemPath) {
                $this->progress()->advanceItem($task, $itemPath);
            });
        } else {
            $success = false;
        }

        return $success;
    }
}