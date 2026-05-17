<?php

namespace App\Livewire;

use Livewire\Component;
use App\Models\Connection;
use App\Services\ConnectionService;

class ConnectionsTabs extends Component
{
    public array $activeConnections = [];
    public ?string $activeTab = null;

    protected $listeners = [
        'addActiveConnection',
    ];

    protected ConnectionService $connectionService;

    public function boot(ConnectionService $connectionService)
    {
        $this->connectionService = $connectionService;
    }

    public function mount(): void
    {
        $this->activeConnections = $this->connectionService->getAll();

        if (!empty($this->activeConnections)) {
            $activeId = $this->connectionService->getActiveConnectionId();

            if (!$activeId || !isset($this->activeConnections[$activeId])) {
                $activeId = array_key_first($this->activeConnections);
            }

            $this->activateConnection($activeId);
        }
    }

    public function addActiveConnection(int $connectionId): void
    {
        $connection = Connection::findOrFail($connectionId);

        // Build config (same logic, но аккуратно)
        $config = [
            'id' => $connection->id,
            'uid' => uniqid(),
            'name' => $connection->name,
            'host' => $connection->host,
            'port' => $connection->port,
            'protocol' => $connection->protocol,
            'authentication_type' => $connection->authentication_type,
            'username' => $connection->username,
            'password' => $connection->password,
            'ssh_key' => $connection->ssh_key,
            'color' => $connection->color,
            'operation_id' => uniqid(),
        ];

        $this->connectionService->setConnection($config);

        $this->activeConnections = $this->connectionService->getAll();

        $this->activateConnection($config['uid']);
    }

    public function switchTab(string $connectionId): void
    {
        if (!isset($this->activeConnections[$connectionId])) {
            return;
        }

        $this->activeTab = $connectionId;

        $this->connectionService->setActiveConnectionId($connectionId);

        $this->dispatch('showRightPanelLoading');

        $this->dispatch('switchTab')
            ->to('file-explorer');
    }

    private function activateConnection(string $connectionId): void
    {
        $this->activeTab = $connectionId;

        $this->connectionService->setActiveConnectionId($connectionId);

        $connection = $this->activeConnections[$connectionId];

        $this->dispatch('showRightPanelLoading');

        $this->dispatch('initConnection', $connection['operation_id'])
            ->to('file-explorer');
    }

    public function closeTab(string $connectionId): void
    {
        if (!isset($this->activeConnections[$connectionId])) {
            return;
        }

        $this->connectionService->close($connectionId);

        $this->activeConnections = $this->connectionService->getAll();

        if ($this->activeTab === $connectionId) {
            if (!empty($this->activeConnections)) {
                $newId = array_key_first($this->activeConnections);
                $this->activateConnection($newId);
            } else {
                $this->activeTab = null;
                $this->connectionService->setActiveConnectionId(null);

                $this->dispatch('closeTab')->to('file-explorer');
            }
        }
    }

    public function render()
    {
        return view('livewire.connections-tabs');
    }
}