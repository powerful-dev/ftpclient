<?php

namespace App\Flysystem;

use League\Flysystem\PhpseclibV3\SftpAdapter;
use League\Flysystem\PhpseclibV3\SftpConnectionProvider;
use League\Flysystem\UnixVisibility\PortableVisibilityConverter;
use League\Flysystem\Config;
use League\Flysystem\UnableToMoveFile;
use phpseclib3\Net\SFTP;

/**
 * @property \League\Flysystem\PhpseclibV3\SftpConnectionProvider $connectionProvider
 */
class ProgressTrackingSftpAdapter extends SftpAdapter
{
    protected $progressCallback;
    private string $host;
    private string $username;
    private ?string $password;
    private ?string $privateKey;
    private string $root;

    protected SftpConnectionProvider $connectionProvider;

    public function __construct(array $config)
    {
        $this->host = $config['host'] ?? 'localhost';
        $this->username = $config['username'] ?? '';
        $this->password = $config['password'] ?? null;
        $this->privateKey = $config['privateKey'] ?? null;

        $rawRoot = $config['root'] ?? '/';

        $this->root = ($rawRoot === '/') ? '/' : rtrim($rawRoot, '/');

        if (str_starts_with($this->root, '~') || $this->root === '') {
            $this->root = '/';
        }

        $this->connectionProvider = new SftpConnectionProvider(
            host: $this->host,
            username: $this->username,
            password: $this->password,
            privateKey: $this->privateKey,
            passphrase: $config['passphrase'] ?? null,
            port: $config['port'] ?? 22,
            useAgent: $config['useAgent'] ?? false,
            timeout: $config['timeout'] ?? 10,
            maxTries: $config['maxTries'] ?? 4,
            hostFingerprint: $config['hostFingerprint'] ?? null
        );

        $visibilityConverter = PortableVisibilityConverter::fromArray([
            'file' => ['public' => 0644, 'private' => 0600],
            'dir' => ['public' => 0755, 'private' => 0700],
        ]);

        parent::__construct(
            $this->connectionProvider,
            $this->root,
            $visibilityConverter
        );
    }

    public function setProgressCallback(callable $callback): void
    {
        $this->progressCallback = $callback;
    }


    /**
     * @param string $path
     * @param resource $resource
     * @param Config $config
     * @throws \RuntimeException
     */
    public function writeStream(string $path, $resource, Config $config): void
    {
        if (!is_resource($resource)) {
            throw new \InvalidArgumentException('Invalid resource.');
        }

        $sftp = $this->getSftp(); // Persistent connection

        $fullPath = $this->root . '/' . ltrim($path, '/');

        $stat = fstat($resource);
        $fileSize = $stat['size'] ?? 0;

        $remoteStart = 0;
        if ($sftp->file_exists($fullPath)) {
            $remoteStart = $sftp->filesize($fullPath);
            if ($remoteStart > 0 && $remoteStart < $fileSize) {
                fseek($resource, $remoteStart);
                logger()->info("Resuming SFTP upload from offset: {$remoteStart} / {$fileSize}");
            }
        }

        $localStart = $remoteStart; // Для симметрии

        // Throttled wrapper для callback (1 arg, как в phpseclib)
        $throttledCallback = $this->createThrottledCallback($fileSize);

        // Native put с resume и progress
        $mode = SFTP::RESUME; // Дозапись
        $result = $sftp->put($fullPath, $resource, $mode, $remoteStart, $localStart, $throttledCallback);

        if ($result === false) {
            $errors = $sftp->getErrors();
            logger()->error('SFTP put failed: ' . implode(', ', $errors));
            throw new \RuntimeException('Failed to upload via SFTP: ' . implode(', ', $errors));
        }
    }


    private function createThrottledCallback(int $fileSize): callable
    {
        $prevUploaded = 0;
        $lastProgressTime = microtime(true);
        $updateInterval = 512 * 1024;// 512 KB
        $timeInterval = 0.2; // 0.2 сек
        $originalCallback = $this->progressCallback;

        

        return function ($transferred) use ($fileSize, &$prevUploaded, &$lastProgressTime, $updateInterval, $timeInterval, $originalCallback) {
            $currentTime = microtime(true);
            $delta = $transferred - $prevUploaded;

            if ($delta >= $updateInterval || ($currentTime - $lastProgressTime >= $timeInterval) || $transferred >= $fileSize) {
                $percent = $fileSize > 0 ? ($transferred / $fileSize) * 100 : 0;
                if ($originalCallback) {
                    call_user_func($originalCallback, $transferred, $fileSize, $percent);
                }
                $prevUploaded = $transferred;
                $lastProgressTime = $currentTime;
            }

            // 🔥 страховка финала
            if ($originalCallback && $transferred >= $fileSize) {
                call_user_func($originalCallback, $fileSize, $fileSize, 100);
            }
        };
    }

    public function getSftp(): SFTP
    {
        return $this->connectionProvider->provideConnection();
    }

    public function fileExists(string $path): bool
    {
        $sftp = $this->connectionProvider->provideConnection();
        $fullPath = $this->root . '/' . ltrim($path, '/');

        if ($sftp->is_file($fullPath)) {
            return true;
        }

        $convertedPath = @iconv('UTF-8', 'CP1251//IGNORE', $fullPath);
        if ($convertedPath !== false && $convertedPath !== $fullPath) {
            return $sftp->is_file($convertedPath);
        }

        return false;
    }

    public function directoryExists(string $path): bool
    {
        $sftp = $this->connectionProvider->provideConnection();
        $fullPath = $this->root . '/' . ltrim($path, '/');

        if ($sftp->is_dir($fullPath)) {
            return true;
        }

        $convertedPath = @iconv('UTF-8', 'CP1251//IGNORE', $fullPath);
        if ($convertedPath !== false && $convertedPath !== $fullPath) {
            return $sftp->is_dir($convertedPath);
        }

        return false;
    }

    public function move(string $source, string $destination, Config $config): void
    {
        $connection = $this->connectionProvider->provideConnection();

        $sourcePath = $this->root . '/' . ltrim($source, '/');
        $destinationPath = $this->root . '/' . ltrim($destination, '/');

        if (!$connection->rename($sourcePath, $destinationPath)) {
            $error = "Failed to rename {$source} to {$destination}";
            throw UnableToMoveFile::because($error, $source, $destination);
        }
    }
}