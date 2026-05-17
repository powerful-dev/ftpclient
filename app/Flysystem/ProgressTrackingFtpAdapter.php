<?php

namespace App\Flysystem;

use League\Flysystem\Ftp\FtpAdapter;
use League\Flysystem\Ftp\FtpConnectionOptions;
use League\Flysystem\Config;
use League\Flysystem\UnableToMoveFile;
use League\Flysystem\PathPrefixer;
use League\Flysystem\Ftp\UnableToResolveConnectionRoot;
use League\Flysystem\Ftp\ConnectivityChecker;
use League\Flysystem\Ftp\ConnectionProvider;
use League\Flysystem\Ftp\FtpConnectionProvider;
use League\Flysystem\Ftp\NoopCommandConnectivityChecker;

class ProgressTrackingFtpAdapter extends FtpAdapter
{
    protected $progressCallback;
    private mixed $connection = false;
    private ?string $rootDirectory = null;
    private PathPrefixer $prefixer;
    private ConnectionProvider $connectionProvider;
    private ConnectivityChecker $connectivityChecker;

    public function __construct(
        private FtpConnectionOptions $connectionOptions,
        ?ConnectionProvider $connectionProvider = null,
        ?ConnectivityChecker $connectivityChecker = null,
    ) {
        parent::__construct($connectionOptions);
        $this->connectionOptions = $connectionOptions;
        $this->connectionProvider = $connectionProvider ?? new FtpConnectionProvider();
        $this->connectivityChecker = $connectivityChecker ?? new NoopCommandConnectivityChecker();
    }

    public function setProgressCallback(callable $callback): void
    {
        $this->progressCallback = $callback;
    }

    public function writeStream(string $path, $resource, Config $config): void
    {
        if (!is_resource($resource)) {
            throw new \InvalidArgumentException(
                'The $resource argument must be a valid resource.'
            );
        }

        $connection = $this->connection();

        $remotePath = $this->getRemotePath($path);

        $stat = fstat($resource);

        $fileSize = $stat['size'] ?? 0;

        $success = ftp_fput(
            $connection,
            $remotePath,
            $resource,
            FTP_BINARY
        );

        if ($success === false) {
            throw new \RuntimeException(
                'Failed to upload file via ftp_fput'
            );
        }

        // Only final completion progress
        if ($this->progressCallback) {

            call_user_func(
                $this->progressCallback,
                $fileSize,
                $fileSize,
                100
            );
        }
    }

    public function move(string $source, string $destination, Config $config): void
    {
        $sourceLocation = $this->prefixer()->prefixPath($source);
        $destinationLocation = $this->prefixer()->prefixPath($destination);
        $connection = $this->connection();

        if (!@ftp_rename($connection, $sourceLocation, $destinationLocation)) {
            $error = error_get_last()['message'] ?? 'reason unknown';
            throw UnableToMoveFile::because($error, $source, $destination);
        }
    }

    private function connection()
    {
        start:
        if (!$this->hasFtpConnection()) {
            $this->connection = $this->connectionProvider->createConnection($this->connectionOptions);
            $this->rootDirectory = $this->resolveConnectionRoot($this->connection);
            $this->prefixer = new PathPrefixer($this->rootDirectory);
            logger()->debug("Established FTP connection: Root={$this->rootDirectory}");
            return $this->connection;
        }

        if ($this->connectivityChecker->isConnected($this->connection) === false) {
            $this->connection = false;
            logger()->debug("FTP connection lost, reconnecting...");
            goto start;
        }

        ftp_chdir($this->connection, $this->rootDirectory);
        return $this->connection;
    }

    private function hasFtpConnection(): bool
    {
        return $this->connection instanceof \FTP\Connection || is_resource($this->connection);
    }

    private function resolveConnectionRoot($connection): string
    {
        $root = $this->connectionOptions->root();
        error_clear_last();

        if ($root !== '' && @ftp_chdir($connection, $root) !== true) {
            throw UnableToResolveConnectionRoot::itDoesNotExist($root, error_get_last()['message'] ?? '');
        }

        error_clear_last();
        $pwd = @ftp_pwd($connection);

        if (!is_string($pwd)) {
            throw UnableToResolveConnectionRoot::couldNotGetCurrentDirectory(error_get_last()['message'] ?? '');
        }

        return $pwd;
    }

    private function prefixer(): PathPrefixer
    {
        if ($this->rootDirectory === null) {
            $this->connection();
        }

        return $this->prefixer;
    }

    public function getRemotePath(string $path): string
    {
        $path = str_replace('\\', '/', $path);
        return $this->connectionOptions->root() . ltrim($path, '/');
    }
}