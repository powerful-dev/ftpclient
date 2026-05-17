<?php

namespace App\Services;

use App\Helpers\PathHelper;
use League\Flysystem\Filesystem;
use App\Traits\TaskTrait;
use App\Exceptions\ConflictException;

class FileMoveService
{
    use TaskTrait;

    protected string $taskId;
    protected ConnectionService $connectionService;

    private string $from = '';
    private string $to   = '';

    private array $fileDecisions = [];
    private bool $overwriteAll = false;
    private bool $skipAll = false;

    public function __construct(ConnectionService $connectionService, private EventService $events)
    {
        $this->connectionService = $connectionService;
    }

    public function move(
        array $files,
        string $destinationDir,
        string $sourceDir,
        string $taskId,
        ?Filesystem $fs = null,
        array $options = []
    ): void {
        $this->taskId = $taskId;

        $this->overwriteAll  = (bool)($options['overwrite_all'] ?? false);
        $this->skipAll       = (bool)($options['skip_all'] ?? false);
        $this->fileDecisions = $options['file_decisions'] ?? [];

        try {
            if ($fs) {
                $this->moveToRemote($files, $destinationDir, $fs);
            } else {
                $this->moveToLocal($files, $destinationDir);
            }
        } finally {

            // Notify UI that directory content changed
            $this->events->emit([
                'event' => 'panel_refresh',
                'path'  => $sourceDir,
            ]);
        }
    }


    private function moveToRemote(
        array $files,
        string $destDir,
        Filesystem $fs
    ): void {

        foreach ($files as $file) {

            $src = PathHelper::normalize(
                $file['path']
            );

            $dst = PathHelper::normalize(
                $destDir . '/' . $file['name']
            );

            $this->from = $src;
            $this->to   = $dst;

            try {

                $encodedSrc = PathHelper::encode($src);

                // -------------------------------------------------
                // Source existence check
                // -------------------------------------------------

                if (
                    !$fs->fileExists($encodedSrc) &&
                    !$fs->directoryExists($encodedSrc)
                ) {

                    $this->addTaskError(
                        "Источник не найден: {$src}"
                    );

                    $this->updateTask(function (&$task) use ($src) {

                        $this->progress()->advanceItem(
                            $task,
                            $src
                        );
                    });

                    continue;
                }

                // -------------------------------------------------
                // Directory move (merge behavior)
                // -------------------------------------------------

                if ($fs->directoryExists($encodedSrc)) {

                    $this->moveRemoteDirectory(
                        $src,
                        $dst,
                        $fs
                    );

                } else {

                    $this->moveRemoteFile(
                        $src,
                        $dst,
                        $fs
                    );
                }

                $this->updateTask(function (&$task) use ($src) {

                    $this->progress()->finishFile(
                        $task,
                        $src
                    );
                });

            } catch (ConflictException $e) {

                throw $e;

            } catch (\Throwable $e) {

                $this->addTaskError(
                    "Ошибка перемещения '{$src}' → '{$dst}': {$e->getMessage()}"
                );
            }
        }
    }

    private function moveRemoteFile(
        string $src,
        string $dst,
        Filesystem $fs
    ): void {

        $resolver = new ConflictResolver(
            $this->fileDecisions,
            $this->overwriteAll,
            $this->skipAll
        );

        $encodedDst = PathHelper::encode($dst);

        $exists =
            $fs->fileExists($encodedDst) ||
            $fs->directoryExists($encodedDst);

        $result = $resolver->resolve(
            $src,
            $dst,
            0,
            $exists
        );

        switch ($result->action) {

            case 'overwrite':

                if ($fs->fileExists($encodedDst)) {
                    $fs->delete($encodedDst);
                }

                $this->updateTask(function (&$task) use ($src) {
                    unset($task['file_decisions'][$src]);
                });

                break;

            case 'skip':

                $this->markFileAsDone($src);

                return;

            case 'rename':

                $dst = PathHelper::normalize(
                    $result->path
                );

                $encodedDst = PathHelper::encode($dst);

                $this->updateTask(function (&$task) use ($src) {
                    unset($task['file_decisions'][$src]);
                });

                break;

            case 'conflict':

                throw new ConflictException(
                    $result->src,
                    $result->dst
                );
        }

        // Ensure remote parent directory exists
        $encodedDir = PathHelper::encode(
            dirname($dst)
        );

        if (!$fs->directoryExists($encodedDir)) {
            $fs->createDirectory($encodedDir);
        }

        // Atomic remote move
        $fs->move(
            PathHelper::encode($src),
            $encodedDst
        );
    }

    private function moveRemoteDirectory(
        string $src,
        string $dst,
        Filesystem $fs
    ): void {

        $encodedDst = PathHelper::encode($dst);

        // Merge behavior:
        // existing directories are reused
        if (!$fs->directoryExists($encodedDst)) {
            $fs->createDirectory($encodedDst);
        }

        foreach (
            $fs->listContents(
                PathHelper::encode($src),
                false
            ) as $item
        ) {

            $subSrc = PathHelper::decode(
                $item->path()
            );

            $name = basename($subSrc);

            $subDst = PathHelper::normalize(
                $dst . '/' . $name
            );

            if ($item->isDir()) {

                $this->moveRemoteDirectory(
                    $subSrc,
                    $subDst,
                    $fs
                );

            } else {

                $this->moveRemoteFile(
                    $subSrc,
                    $subDst,
                    $fs
                );
            }
        }

        // Remove empty source directory
        $fs->deleteDirectory(
            PathHelper::encode($src)
        );
    }


    private function moveToLocal(array $files, string $destDir): void
    {
        $destDir = rtrim(
            $destDir,
            DIRECTORY_SEPARATOR
        ) . DIRECTORY_SEPARATOR;

        foreach ($files as $file) {

            $src = $file['path'];

            $dst = PathHelper::normalize(
                $destDir . $file['name']
            );

            $this->from = $src;
            $this->to   = $dst;

            try {

                $moved = false;

                // Fast path:
                // atomic rename for files on same filesystem
                //
                // Directories are excluded because we support
                // merge behavior for existing destination folders.
                if (
                    is_file($src) &&
                    $this->canUseRename($src, $dst)
                ) {

                    clearstatcache(true, $src);

                    $moved = @rename($src, $dst);
                }

                // Fallback move
                if (!$moved) {

                    if (is_dir($src)) {

                        // Merge behavior:
                        // existing directories are reused
                        if (!is_dir($dst)) {

                            if (
                                !@mkdir($dst, 0777, true) &&
                                !is_dir($dst)
                            ) {
                                throw new \RuntimeException(
                                    "Failed to create directory: {$dst}"
                                );
                            }
                        }

                        $this->moveDirectory(
                            $src,
                            $dst
                        );

                    } else {

                        $this->moveFile(
                            $src,
                            $dst
                        );
                    }
                }

                $this->updateTask(function (&$task) use ($src) {

                    $this->progress()->finishFile(
                        $task,
                        $src
                    );
                });

            } catch (ConflictException $e) {

                throw $e;

            } catch (\Throwable $e) {

                $this->addTaskError(
                    "Ошибка перемещения '{$src}' → '{$dst}': {$e->getMessage()}"
                );
            }
        }
    }

    private function canUseRename(string $src, string $dst): bool
    {
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            return strtoupper(substr(realpath($src), 0, 1)) ===
                   strtoupper(substr(realpath(dirname($dst)), 0, 1));
        }

        if (file_exists($src) && file_exists(dirname($dst))) {
            return stat($src)['dev'] === stat(dirname($dst))['dev'];
        }

        return false;
    }

    private function moveDirectory(string $src, string $dst): void
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($src, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item) {
            $subSrc  = $item->getPathname();
            $relPath = ltrim(str_replace($src, '', $subSrc), '/\\');
            $subDst  = $dst . DIRECTORY_SEPARATOR . $relPath;

            if ($item->isDir()) {
                if (!is_dir($subDst)) {
                    mkdir($subDst, 0777, true);
                }
            } else {
                $this->moveFile($subSrc, $subDst);
            }
        }

        @rmdir($src);
    }

    private function moveFile(
        string $src,
        string $dst
    ): void {

        // -------------------------------------------------
        // Conflict resolution
        // -------------------------------------------------

        $resolver = new ConflictResolver(
            $this->fileDecisions,
            $this->overwriteAll,
            $this->skipAll
        );

        $exists = file_exists($dst);

        $result = $resolver->resolve(
            $src,
            $dst,
            0,
            $exists
        );

        switch ($result->action) {

            case 'overwrite':

                if (
                    file_exists($dst) &&
                    !@unlink($dst)
                ) {
                    throw new \RuntimeException(
                        "Failed to delete existing file: {$dst}"
                    );
                }

                $this->updateTask(function (&$task) use ($src) {
                    unset($task['file_decisions'][$src]);
                });

                break;

            case 'skip':

                $this->markFileAsDone($src);

                return;

            case 'rename':

                $dst = PathHelper::normalize(
                    $result->path
                );

                $this->updateTask(function (&$task) use ($src) {
                    unset($task['file_decisions'][$src]);
                });

                break;

            case 'conflict':

                throw new ConflictException(
                    $result->src,
                    $result->dst
                );
        }

        // -------------------------------------------------
        // Ensure destination directory exists
        // -------------------------------------------------

        $dstDir = dirname($dst);

        if (!is_dir($dstDir)) {

            if (
                !@mkdir($dstDir, 0777, true) &&
                !is_dir($dstDir)
            ) {
                throw new \RuntimeException(
                    "Failed to create directory: {$dstDir}"
                );
            }
        }

        // -------------------------------------------------
        // Open streams
        // -------------------------------------------------

        $from = @fopen($src, 'rb');

        if (!$from) {
            throw new \RuntimeException(
                "Failed to open source file: {$src}"
            );
        }

        $to = @fopen($dst, 'wb');

        if (!$to) {

            fclose($from);

            throw new \RuntimeException(
                "Failed to create destination file: {$dst}"
            );
        }

        try {

            $copier = new StreamCopier();

            $copier->copy(
                $from,
                $to,
                0,

                // Progress callback
                function ($offset) use ($src) {

                    if (
                        !$this->shouldFlushProgress(
                            $src,
                            0.1
                        )
                    ) {
                        return;
                    }

                    $this->updateTask(function (&$task) use ($src, $offset) {

                        $this->progress()->advance(
                            $task,
                            $src,
                            $offset
                        );
                    });
                },

                // Interrupt callback
                function () {
                    $this->checkCancelled();
                }
            );

        } finally {

            fclose($from);
            fclose($to);
        }

        // -------------------------------------------------
        // Remove source after successful move
        // -------------------------------------------------

        if (!@unlink($src)) {

            $this->addTaskError(
                "File moved but source was not deleted: {$src}"
            );
        }
    }
}
