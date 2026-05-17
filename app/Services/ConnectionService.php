<?php

namespace App\Services;

use League\Flysystem\Ftp\FtpConnectionOptions;
use League\Flysystem\Filesystem;
use App\Models\Connection;
use App\Flysystem\ProgressTrackingSftpAdapter;
use App\Flysystem\ProgressTrackingFtpAdapter;
use \phpseclib3\Net\SSH2;
use App\Support\Runtime;
use App\Helpers\PathHelper;

class ConnectionService
{
    /**
     * @var array<string, Filesystem>
     */

    protected $prefix = 'connections';

    public function createListCommand(array $config, string $path = '/'): string
    {

        $operationId  = $config['operation_id'] ?? uniqid();
        $connectionId = $config["id"];

        $command = [
            'type' => 'list',
            'connection_id' => $connectionId,
            'path' => $path,
            'operation_id' => $operationId,
            'createdAt' => now()->timestamp,
        ];

        $file = Runtime::path('commands') . "/list_{$connectionId}_{$operationId}.json";

        file_put_contents($file, json_encode($command, JSON_PRETTY_PRINT));

        return $operationId;
    }

    public function storeDirectoryCache(int $connectionId, string $path, array $files): void
    {
        $path = PathHelper::normalize($path);
        $hash = md5($path);

        $file = Runtime::path('cache') . "/dir_{$connectionId}_{$hash}.json";

        file_put_contents($file, json_encode($files));
    }

    public function getDirectoryCache(int $connectionId, string $path): ?array
    {
        $path = PathHelper::normalize($path);
        $hash = md5($path);

        $file = Runtime::path('cache') . "/dir_{$connectionId}_{$hash}.json";

        if (!file_exists($file)) {
            return null;
        }

        return json_decode(file_get_contents($file), true);
    }

    /**
     * Remove cached listing for a specific directory.
     */
    public function forgetConnectionCache(int $connectionId, ?string $path = null): void
    {
        if ($path !== null) {
            $path = PathHelper::normalize($path);
            $hash = md5($path);

            $file = Runtime::path('cache') . "/dir_{$connectionId}_{$hash}.json";

            if (file_exists($file)) {
                @unlink($file);
            }

            return;
        }

        $dir = Runtime::path('cache');

        if (!is_dir($dir)) {
            return;
        }

        foreach (glob($dir . "/dir_{$connectionId}_*.json") as $file) {
            @unlink($file);
        }

    }

    /**
     * set active connection id, if app reload
     * @param id hash string
    */
    public function setActiveConnectionId($id)
    {

        session()->put("activeConnectionId", $id);
        session()->save();
    }

    public function getActiveConnectionId()
    {
        $activeConnectionId = session("activeConnectionId");

        $connection = session("{$this->prefix}.$activeConnectionId") ?? null;

        if (!is_null($connection)) {
            return $activeConnectionId;
        }

        return null;
    }

    public function getConnectionName()
    {
        if ($aConnectionConfig = $this->getConnection()) {
            return $aConnectionConfig['name'];
        }
        return null;
    }

    public function getConnection()
    {
        $id = $this->getActiveConnectionId();

        return session("{$this->prefix}.$id") ?? null;
    }

    public function setConnection(array $config)
    {

        $id = $config["uid"];

        session()->put("{$this->prefix}.$id", $config);
        session()->save();

        $this->setActiveConnectionId($id);

        $this->createListCommand($config, '/');
    }

    public function setConnectionPath(string $path)
    {
        $id = $this->getActiveConnectionId();

        session()->put("{$this->prefix}.$id.path", $path);
        session()->save();
    }

    public function getConnectionPath()
    {

        $path = session("{$this->prefix}.{$this->getActiveConnectionId()}.path");

        if (!is_null($this->getConnection()) && !is_null($path)) {
            return $path;
        }

        return '/';
    }

    public function getAll(): array
    {
        return session("{$this->prefix}") ?? [];
    }

    public function close(string $id): void
    {
        session()->forget("{$this->prefix}.$id");
    }

    public function closeAll(): void
    {
        session()->forget($this->prefix);
    }

    public function getObject()
    {
        $connection = $this->getConnection();
        if (!is_null($connection)) {
            return Connection::find($connection['id']);
        }

        return null;
    }

    // ============================
    // =    factories             =
    // ============================

    public function getFilesystem($connection = null): ?Filesystem
    {
        if ($connection !== null) {
            $adapter = $this->getAdapter($connection);
        } else {
            $config = $this->getConnection();
            $adapter = $config
                ? $this->getAdapter(Connection::find($config['id']))
                : null;
        }

        return $adapter ? new Filesystem($adapter) : null;
    }

    public function getAdapter(Connection $connection)
    {
        $protocol = strtoupper($connection->protocol);

        return match ($protocol) {
            'SFTP'  => $this->createSftpAdapter($connection),
            'FTP'   => $this->createFtpAdapter($connection, false),
            'FTPS'  => $this->createFtpAdapter($connection, true),
        };
    }

    public function createSftpAdapter(Connection $connection)
    {

        $root     = $connection->last_right_path ?? '/';
        $timeout  = (int)($connection->timeout ?? 30);

        $config = [
            'host' => $connection->host,
            'username' => $connection->username,
            'password' => $connection->password ?? null,
            'privateKey' => $connection->private_key ?? null,
            'passphrase' => $connection->passphrase ?? null,
            'port' => (int)($connection->port ?? 22),
            'useAgent' => false,
            'timeout' => $timeout,
            'maxTries' => 4,
            'hostFingerprint' => null,
            'root' => $root,
            'utf8' => true, 
        ];

        return new ProgressTrackingSftpAdapter($config);
    }

    public function createFtpAdapter(Connection $connection, bool $ssl = false)
    {

        $root     = $connection->last_right_path ?? '/';
        $timeout  = (int)($connection->timeout ?? 30);

        $options = FtpConnectionOptions::fromArray([
            'host'     => $connection->host,
            'username' => $connection->username,
            'password' => $connection->password,
            'port'     => (int)($connection->port ?? 21),
            'root'     => $root,
            'timeout'  => $timeout,
            'ssl'      => $ssl,
            'utf8'     => true,
        ]);

        return new ProgressTrackingFtpAdapter($options);
    }

    public function createSshConnection(Connection $connection)
    {
        $ssh = new SSH2($connection->host, $connection->port ?? 22);
        if (!$ssh->login($connection->username, $connection->password)) {
            throw new \Exception("SSH login failed for {$connection->host}");
        }
        return $ssh;
    }
}