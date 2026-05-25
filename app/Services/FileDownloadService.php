<?php

namespace App\Services;

use League\Flysystem\Filesystem;
use App\Helpers\PathHelper;
use App\Traits\TaskTrait;
use App\Exceptions\ConflictException;
use App\Exceptions\TaskCancelledException;

class FileDownloadService
{
    use TaskTrait;

    protected string $taskId;
    protected ConnectionService $connectionService;

    private $from = '', $to = '';

    private $fs = null;

    private array $fileDecisions = [];
    private bool $overwriteAll = false;
    private bool $skipAll = false;

    private bool $trackProgress = true;

    public function __construct(ConnectionService $connectionService, private EventService $events)
    {
        $this->connectionService = $connectionService;
    }

    public function download(
        array $files,
        string $localDest,
        ?string $taskId,
        Filesystem $fs,
        array $options = []
    ): void
    {
        $this->taskId        = $taskId;
        $this->fs            = $fs;
        $this->overwriteAll  = (bool)($options['overwrite_all'] ?? false);
        $this->skipAll       = (bool)($options['skip_all'] ?? false);
        $this->fileDecisions = $options['file_decisions'] ?? [];

        try {
            if ($fs) {
                foreach ($files as $file) {

                    $remotePath = $file['path'];
                    $dirName    = basename($remotePath);
                    $localPath = PathHelper::normalize($localDest . '/' . $dirName);

                    if ($fs->directoryExists($remotePath)) {
                        $this->downloadRemoteDirectory($remotePath, $localPath);
                    } elseif ($fs->fileExists($remotePath)) {
                        $this->downloadRemoteFile($remotePath, $localPath);
                    } else {
                        $this->addTaskError("❌ path does not exist: {$remotePath}");
                    }
                }
            } 
        } finally {

            // Notify UI that directory content changed
            $this->events->emit([
                'event' => 'panel_refresh',
                'path'  => $localDest,
            ]);
        }
    }

    /**
     * DOWNLOAD FILE
     */
    private function downloadRemoteFile(
        string $remotePath,
        string $localPath,
        bool $trackProgress = true
    ): void {

        $this->checkCancelled();

        $this->from = $remotePath;
        $this->to   = $localPath;

        $resolver = new ConflictResolver(
            $this->fileDecisions,
            $this->overwriteAll,
            $this->skipAll
        );

        $exists = file_exists($localPath);

        $result = $resolver->resolve(
            $remotePath,
            $localPath,
            0,
            $exists
        );

        switch ($result->action) {

            case 'overwrite':

                if (
                    file_exists($localPath) &&
                    !@unlink($localPath)
                ) {
                    throw new \RuntimeException(
                        "Failed to delete existing file: {$localPath}"
                    );
                }

                $this->updateTask(function (&$task) use ($remotePath) {
                    unset($task['file_decisions'][$remotePath]);
                });

                break;

            case 'skip':

                $this->markFileAsDone($remotePath);

                return;

            case 'rename':

                $localPath = PathHelper::normalize($result->path);

                $this->updateTask(function (&$task) use ($remotePath) {
                    unset($task['file_decisions'][$remotePath]);
                });

                break;

            case 'conflict':

                throw new ConflictException(
                    $result->src,
                    $result->dst
                );
        }

        $dir = dirname($localPath);

        if (!is_dir($dir)) {

            if (!@mkdir($dir, 0777, true) && !is_dir($dir)) {
                throw new \RuntimeException(
                    "Failed to create directory: {$dir}"
                );
            }
        }

        $stream = $this->fs->readStream(
            PathHelper::encode($remotePath)
        );

        if (!$stream) {

            $this->addTaskError(
                "❌ Failed to download file: {$remotePath}"
            );

            return;
        }

        $local = fopen($localPath, 'wb');
        if (!$local) {

            fclose($stream);

            throw new \RuntimeException(
                "Failed to open local file for writing: {$localPath}"
            );
        }

        try {

            $copier = new StreamCopier();

            $copier->copy(
                $stream,
                $local,
                0,

                function ($offset) use ($remotePath) {

                    if (
                        !$this->shouldFlushProgress(
                            $remotePath,
                            0.1
                        )
                    ) {
                        return;
                    }
                },

                function () {
                    $this->checkCancelled();
                }
            );

            $this->updateTask(function (&$task) use ($remotePath) {

                $this->progress()->finishItem(
                    $task,
                    PathHelper::decode($remotePath)
                );
            });

        } catch (TaskCancelledException $e) {

            if (file_exists($localPath)) {
                @unlink($localPath);
            }

            throw $e;

        } finally {

            fclose($stream);
            fclose($local);
        }
    }

    private function downloadRemoteDirectory(
        string $remoteDir,
        string $localDir,
        bool $trackProgress = true
    ): void {
        try {
            if (!is_dir($localDir)) {
                mkdir($localDir, 0777, true);
            }

            foreach ($this->fs->listContents(PathHelper::encode($remoteDir), false) as $item) {

                $remotePath = PathHelper::decode($item->path());

                $name = basename($remotePath);

                $localPath = PathHelper::normalize($localDir . '/' . $name);

                if ($item->isDir()) {
                    $this->downloadRemoteDirectory(
                        $remotePath,
                        $localPath,
                        false
                    );
                } else if ($item->isFile()) {
                    $this->downloadRemoteFile(
                        $remotePath,
                        $localPath,
                        false
                    );
                }
            }

            if ($trackProgress) {

                $this->updateTask(function (&$task) use ($remoteDir) {

                    $this->progress()->finishItem(
                        $task,
                        PathHelper::decode($remoteDir)
                    );
                });
            }
        } 
        catch (TaskCancelledException | ConflictException $e) {
            throw $e;
        }
        catch (\Throwable $e) {
            $this->addTaskError($e->getMessage());
        }
    }
}