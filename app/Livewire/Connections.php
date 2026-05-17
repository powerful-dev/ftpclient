<?php

namespace App\Livewire;

use Livewire\Component;
use \App\Models\Connection;

class Connections extends Component
{
    public $connections = [];

    public $editingConnection = null; 

    protected $listeners = [
        'refreshConnections' => 'loadConnections',
    ];

    public bool $creating = false;

    public function mount()
    {
        $this->loadConnections();
    }

    public function loadConnections(): void
    {
        $this->creating = false;
        $this->editingConnection = null;
        $this->connections = Connection::all();
    }

    public function create(): void
    {
        $this->creating = true;
        $this->editingConnection = null;
    }

    public function edit(int $connectionId): void
    {
        $this->creating = false;
        $this->editingConnection = Connection::findOrFail($connectionId);
    }

    public function delete(int $connectionId): void
    {
        Connection::findOrFail($connectionId)->delete();

        $this->loadConnections();
    }

    public function connect(int $connectionId): void
    {

        $this->dispatch('addActiveConnection', $connectionId)->to('connections-tabs');

        $this->dispatch('modal.close');
    }

    public function close(): void
    {
        $this->dispatch('modal.close');
    }

    public function render()
    {
        return view('livewire.connections');
    }
}



















/*
class Connections extends Component
{

    public $connections = [];

    protected $listeners = [
        'showAllConnections',
        'showConnections',
        'showListOfConnectionsModal',
        'setLanguage',
        'addActiveConnection'
    ];

    public $showListOfConnections = false; 
    public $createEditConnection =  false;
    public $activeConnections = [];
    public $activeTab;

    protected ConnectionService $connectionService;

    public function boot(ConnectionService $connectionService)
    {
        $this->connectionService = $connectionService;
    }


    public function mount() {

        $this->connections = Connection::all();

        $this->activeConnections = $this->connectionService->getAll();

        if (count($this->activeConnections) > 0) {

            if (is_null($activeConnectionId = $this->connectionService->getActiveConnectionId())) {
                $firstConnection = reset($this->activeConnections);
                $activeConnectionId = $firstConnection['uid'];
            }

            $this->switchTab($activeConnectionId);
        }
    }

    public function switchTab($connectionId)
    {
        if (!isset($this->activeConnections[$connectionId])) {
            return;
        }

        $this->activateConnection($connectionId);
    }

    public function closeTab($connectionId)
    {
        
        $connection = $this->activeConnections[$connectionId];

        $this->connectionService->createDisconnectCommand($connection['id']);
        $this->connectionService->close($connectionId);

        $this->activeConnections = $this->connectionService->getAll();

        if ($this->activeTab === $connectionId) {

            if (!empty($this->activeConnections)) {

                $newConnectionId = array_key_first($this->activeConnections);

                $this->activateConnection($newConnectionId);

            } else {

                $this->activeTab = null;
                $this->connectionService->setActiveConnectionId(null);

                $this->dispatch('setConnection', null)->to('file-explorer');
            }
        }
    }

    private function activateConnection(string $connectionId): void
    {
        $this->activeTab = $connectionId;

        $this->connectionService->setActiveConnectionId($connectionId);

        $files = $this->connectionService->getConnectionFiles($connectionId);

        if (!empty($files)) {
            $this->dispatch('switchConnection', $connectionId)->to('file-explorer');
        } else {
            $connection = $this->activeConnections[$connectionId];
            $this->dispatchToFileExplorer($connection['operation_id']);
        }
    }

    public function addActiveConnection(Connection $connection)
    {

        $config['id'] = $connection->id;
        $config['uid'] = uniqid();
        $config['name'] = $connection->name;
        $config['host'] = $connection->host;
        $config['port'] = $connection->port;
        $config['protocol'] = $connection->protocol;
        $config['authentication_type'] = $connection->authentication_type;
        $config['username'] = $connection->username;
        $config['password'] = $connection->password;
        $config['ssh_key'] = $connection->ssh_key;
        $config['color'] = $connection->color;
        $config['operation_id'] = uniqid();

        $this->connectionService->setConnection($config);

        $this->activeTab = $config['uid'];
        $this->hideListOfConnectionsModal();
        $this->dispatchToFileExplorer($config['operation_id']);   
    
        $this->showAllConnections();
    }

    protected function dispatchToFileExplorer($operation_id)
    {
        $this->dispatch('showRightPanelLoading');
        
        $this->dispatch('setConnection', $operation_id)->to('file-explorer');
      
    }

    public function showAllConnections() {

        $this->activeConnections = $this->connectionService->getAll();

        $this->createEditConnection = false;
    }

    public function showCreateEditConnectionForm()
    {
        $this->createEditConnection = true;
    }

    public function showConnections()
    {
        $this->dispatch('openModal', modal: 'connections');
    }

    public function hideListOfConnectionsModal()
    {
        $this->showListOfConnections = false;
        $this->createEditConnection = false;
    }

    public function deleteConnection(Connection $connection)
    {
        $connection->delete();

        
    }

    public function editConnection(Connection $connection)
    {
        $this->createEditConnection = $connection;
    }

    public function closeWindow() 
    {
        $this->showListOfConnections = false;
    }


    public function render()
    {
        $this->connections = Connection::all();
        
        return view('livewire.connections');
    }

    public function setLanguage($event)
    {
        App::setLocale($event['code']);
    }
}
*/