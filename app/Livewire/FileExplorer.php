<?php

namespace App\Livewire;

use Livewire\Component;
use Illuminate\Support\Facades\App;
use App\Models\ColumnWidth;
use App\Services\ConnectionService;
use App\Helpers\PathHelper;
use App\Helpers\StringHelper;
use App\Helpers\FileHelper;
use Websemantics\FileIcons\FileIcons;
use App\Constants\Path;
use App\Constants\FileActions;
use App\Support\Runtime;
use App\Services\FileActionHandler;
use App\Enums\FileActionType;
use App\Services\Commands\CommandDispatcher;

class FileExplorer extends Component
{

    /*
    |--------------------------------------------------------------------------
    | Public state (Livewire)
    |--------------------------------------------------------------------------
    */
    public $panel;
    public $files = [];
    public $sortColumn = 'name';
    public $sortDirection = 'asc';
    public $columnWidths = [];

    // Next path to navigate to, applied only after data is successfully loaded
    public ?string $pendingPath = null;
    
    /* only for connection */
    public bool $isLoading = false;
    public bool $showConnectionLoader = false;
    public ?string $pendingOperationId = null;

    public array $selected = [
        'left' => [],
        'right' => [],
    ];

    /* active panel, change when click and select files */
    public string $activePanel = 'left';

    public array $breadcrumbs = [
        "left" => [],
        "right" => []
    ];

    /*
    |--------------------------------------------------------------------------
    | Dependencies
    |--------------------------------------------------------------------------
    */
    protected ConnectionService $connectionService;
    protected CommandDispatcher $commandDispatcher;
    
    /*
    |--------------------------------------------------------------------------
    | Event listeners
    |--------------------------------------------------------------------------
    */
    protected $listeners = [
        'setColumnWidth', 
        'changeDirectory', 
        'setLanguage', 
        'handleAction',
        'openCreateDirectoryModal',
        'openCreateFileModal',
        'create-directory' => 'createDirectory',
        'create-file' => 'createFile',
        'initConnection',
        'switchTab',
        'closeTab',
        'refresh-file-explorer' => 'refreshExplorer',
        'openContextMenu',
        'closeContextMenu',
        'executeElevatedAction',
    ];


    /*
    |--------------------------------------------------------------------------
    | Lifecycle hooks
    |--------------------------------------------------------------------------
    */

    public function boot(
        ConnectionService $connectionService,
        CommandDispatcher $commandDispatcher
    )
    {
        $this->connectionService = $connectionService;
        $this->commandDispatcher = $commandDispatcher;
    }

    public function mount($panel)
    {

        $this->panel = $panel;
        $this->sortColumn = session()->get("{$this->panel}_sort_column", 'name');
        $this->sortDirection = session()->get("{$this->panel}_sort_direction", 'asc');

        $this->loadItems();
    }

    public function render()
    {
        $this->loadColumnWidths();

        return view('livewire.file-explorer', [
            'connectionName' => $this->panel === 'right' ? $this->connectionService->getConnectionName() : '',
            'columnWidths'   => $this->columnWidths,
            'connectionId'     => $this->connectionService->getActiveConnectionId()
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Data loading
    |--------------------------------------------------------------------------
    */
  
    private function loadColumnWidths()
    {
        $widths = ColumnWidth::where('panel', $this->panel)->pluck('width', 'column')->toArray();
        $this->columnWidths = [
            'name' => isset($widths['name']) ? "{$widths['name']}px" : '200px',
            'size' => isset($widths['size']) ? "{$widths['size']}px" : '150px',
            'modified' => isset($widths['modified']) ? "{$widths['modified']}px" : '200px',
            'permissions' => isset($widths['permissions']) ? "{$widths['permissions']}px" : '150px',
        ];
    }

    public function refreshExplorer(string $path, ?int $connectionId = null): void
    {
        $connection = $this->connectionService->getConnection();

        // Local panel
        if ($this->panel === 'left' || is_null($connection)) {
            $this->loadItems();
            return;
        }

        // Ignore refresh events from another connection
        if (
            $connectionId !== null &&
            $connection['id'] !== $connectionId
        ) {
            return;
        }

        // Refresh only current opened directory
        if ($path !== $this->getPath()) {
            return;
        }

        $this->pendingPath = $path;

        // Remote panel
        $this->refreshDirectory();
    }

    // Prevents scheduling multiple LIST operations while one is already pending
    public bool $refreshScheduled = false;

    public function refreshDirectory(): void
    {
        $connection = $this->connectionService->getConnection();

        if (is_null($connection) || $this->panel !== 'right') {
            return;
        }

        // If a LIST request is already pending, do not schedule another one
        if ($this->pendingOperationId !== null || $this->refreshScheduled) {
            return;
        }

        $this->refreshScheduled = true;

        $this->pendingOperationId = uniqid();
        $this->isLoading = true;

        $this->dispatchListCommand();
    }

    public function loadItems()
    {
        $connection = $this->connectionService->getConnection();

        if (!is_null($connection) && $this->panel === 'right') {

            // If no pending operation — nothing to wait for
            if ($this->pendingOperationId === null) {
                return;
            }

            $path = $this->pendingPath ?? $this->getPath();

            $files = $this->connectionService->getDirectoryCache($connection['id'], $path);

            if (!is_null($files)) {

                $this->updateNavigationState($path);
                $this->setPath($path);

                $this->files = $files;
                $this->isLoading = false;
                $this->pendingPath = null;
                $this->showConnectionLoader = false;
                $this->pendingOperationId = null;
                $this->refreshScheduled = false;

                $this->dispatch('directory-changed');
                return;
            }

            $file = Runtime::path('results') . "/list_{$connection["id"]}_{$this->pendingOperationId}.json";

            if (!file_exists($file)) {
                return;
            }

            $contents = json_decode(file_get_contents($file), true);

            if ($contents !== null && isset($contents['data'])) {

                $icons = new FileIcons();

                $files = array_map(function ($file) use ($icons) {

                    $filename = basename($file['path']);
                    $isFile = $file['type'] === 'file';

                    return [
                        'name'        => $filename,
                        'path'        => $file['path'],
                        'type'        => $file['type'],
                        'size'        => $isFile
                            ? StringHelper::formatSize($file['size'])
                            : null,
                        'modified'    => StringHelper::formatLastModified($file['modified']),
                        'permissions' => $file['visibility'],
                        'icon'        => !$isFile
                            ? 'folder-icon'
                            : $icons->getClassWithColor($filename),
                    ];
                }, $contents['data']);

                @unlink($file);

                $files = $this->sortFiles($files);

                $this->connectionService->storeDirectoryCache($connection['id'], $this->pendingPath, $files);

                $this->files = $files;

                if ($this->pendingPath !== null) {
                    $this->setPath($this->pendingPath);
                    $this->updateNavigationState($this->pendingPath);
                    $this->pendingPath = null;
                }

            } else {
                $this->files = [];
            }

            $this->isLoading = false;
            $this->showConnectionLoader = false;
            $this->pendingOperationId = null;
            $this->refreshScheduled = false;
        }

        if (is_null($connection) || $this->panel === 'left') {
            $path = $this->getPath();

            $this->updateNavigationState($path);

            $files = FileHelper::getFilesFromPath($path);
            $this->files = $this->sortFiles($files);
        }

        $this->dispatch('directory-changed');
    }
    

    private function updateNavigationState(string $path): void
    {
        $breadcrumbs = [];

        $connection = $this->connectionService->getConnection();

        $rootName = ($this->panel === 'right' && $connection)
            ? $this->connectionService->getConnectionName()
            : __("Computer");

        $rootPath = ($this->panel === 'left' || is_null($connection))
            ? Path::ROOT
            : '/';

        $breadcrumbs[] = [
            'name' => $rootName,
            'path' => $rootPath,
        ];

        if ($path === $breadcrumbs[0]['path']) {
            $this->breadcrumbs[$this->panel] = $breadcrumbs;
            return;
        }

        $path = str_replace('\\', '/', $path);
        $parts = array_values(array_filter(explode('/', $path)));

        $current = '';

        foreach ($parts as $index => $part) {

            if ($this->panel === 'left' && $index === 0 && str_ends_with($part, ':')) {
                $current = $part;
            } else {
                $current .= ($current ? '/' : '') . $part;
            }

            $breadcrumbs[] = [
                'name' => $part,
                'path' => $current,
            ];
        }

        $this->breadcrumbs[$this->panel] = $breadcrumbs;
    }

    private function dispatchListCommand(): void
    {
        $connection = $this->connectionService->getConnection();

        //$this->pendingOperationId = uniqid();

        $commandsDir = Runtime::path('commands');
        @mkdir($commandsDir, 0777, true);

        $command = [
            'type' => 'list',
            'connection_id' => $connection['id'],
            'operation_id' => $this->pendingOperationId,
            'path' => $this->pendingPath,
        ];

        $file = $commandsDir . "/list_{$connection['id']}_{$this->pendingOperationId}.json";
        $tmp  = $file . '.tmp';

        file_put_contents($tmp, json_encode($command));
        rename($tmp, $file);
    }


    /*
    |--------------------------------------------------------------------------
    | File operations (actions)
    |--------------------------------------------------------------------------
    */

    public function executeElevatedAction($payload): void
    {
        $payload['elevated'] = true;

        $this->handleAction($payload);
    }

    public function handleAction($payload)
    {

        $this->closeContextMenu();

        $typeValue = $payload['type'] ?? null;

        if (!$typeValue) return;

        $type = FileActionType::tryFrom($typeValue);

        if (!$type) return;

        if (($payload['sourcePanel'] ?? null) !== $this->panel) {
            return;
        }

        if ($type->isUi()) {

            match ($type) {
                FileActionType::CREATE_DIRECTORY => $this->openCreateDirectoryModal(),
                FileActionType::CREATE_FILE      => $this->openCreateFileModal(),
                default => null,
            };

            return;
        }

        if ($type->isFileOperation() || $type->isOpen()) {

            $result = app(FileActionHandler::class)->handle($payload);

            if (!$result) {
                return;
            }

            if (isset($result['elevation'])) {

                return $this->dispatch(
                    'openModal',
                    modal: 'elevation-required',
                    payload: [
                        'message' => $result['elevation']['message'],
                        'actionPayload' => $payload,
                    ]
                );
            }

            if (isset($result['error'])) {
                $error = $result['error'];

                return $this->dispatch(
                    'openModal',
                    modal: 'error',
                    payload: [
                        'errors' => [
                            __($error['key'], $error['params'] ?? [])
                        ]
                    ]
                );
            }
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Navigation
    |--------------------------------------------------------------------------
    */
    public function changeDirectory(string $path, string $panel): void
    {

        $this->closeContextMenu();

        if ($panel !== $this->panel) {
            return;
        }

        $this->selected[$this->activePanel] = [];

        $path = $path !== Path::ROOT
            ? PathHelper::normalize($path)
            : Path::ROOT;

        $connection = $this->connectionService->getConnection();

        // local
        if (is_null($connection) || $this->panel === 'left') {
            $this->setPath($path);
            $this->loadItems();
            return;
        }

        // remote
        $this->pendingPath = $path;
        $this->pendingOperationId = uniqid();
        $this->isLoading = true;
        $this->connectionService->forgetConnectionCache($connection['id'], $path);

        $this->dispatchListCommand();
    }

    public function getParentPath(): string
    {
        $path = $this->getPath();

        if ($path === Path::ROOT || preg_match('/^[A-Za-z]:\/?$/', $path)) {
            return Path::ROOT;
        }

        $parent = dirname(PathHelper::normalize($path));

        return $parent === '.' ? Path::ROOT : $parent;
    }

    private function getPath($panel = null): string
    {
        $panel = $panel ?? $this->panel;

        if ($panel === 'right') {

            $connection = $this->connectionService->getConnection();

            if ($connection) {
                return $this->connectionService->getConnectionPath() ?? '/';
            }
        }

        return session()->get($panel . '-path', Path::ROOT);
    }

    private function setPath($path): void
    {
        if ($this->panel === 'right') {

            $connection = $this->connectionService->getConnection();

            if ($connection) {
                $this->connectionService->setConnectionPath($path);

                return;
            }
        }

        session()->put($this->panel . '-path', $path);
    }

    /*
    |--------------------------------------------------------------------------
    | File system mutations (UI)
    |--------------------------------------------------------------------------
    */

    public function openCreateDirectoryModal()
    {

        $this->closeContextMenu();

        if ($this->activePanel !== $this->panel) {
            return;
        }

        $this->dispatch(
            'openModal',
            modal: 'create-directory',
            payload: [
                'panel' => $this->panel,
            ]
        );
    }

    public function openCreateFileModal()
    {

        $this->closeContextMenu();

        if ($this->activePanel !== $this->panel) {
            return;
        }

        $this->dispatch(
            'openModal',
            modal: 'create-file',
            payload: [
                'panel' => $this->panel,
            ]
        );
    }

    public function createDirectory(string $panel, string $name)
    {
        if ($panel !== $this->panel) {
            return;
        }

        $currentDir = PathHelper::normalize($this->getPath());

        $path = $currentDir . '/' . $name;

        $connection_id = null;

        if (
            $this->panel === 'right' &&
            $connection = $this->connectionService->getConnection()
        ) {
            $connection_id = $connection['id'];
        }

        $this->commandDispatcher->dispatchSimple([
            'type' => 'mkdir',
            'connection_id' => $connection_id,
            'panel' => $panel,
            'payload' => [
                'path' => $path,
            ]
        ]);
    }

    public function createFile(string $panel, string $name)
    {
        if ($panel !== $this->panel) {
            return;
        }

        $currentDir = PathHelper::normalize($this->getPath());

        $path = $currentDir . '/' . $name;

        $connection_id = null;

        if (
            $this->panel === 'right' &&
            $connection = $this->connectionService->getConnection()
        ) {
            $connection_id = $connection['id'];
        }

        $this->commandDispatcher->dispatchSimple([
            'type' => 'create_file',
            'connection_id' => $connection_id,
            'panel' => $panel,
            'payload' => [
                'path' => $path,
            ]
        ]);
    }

    public function initConnection(string $operationId): void
    {
        if ($this->panel !== 'right') {
            return;
        }

        $this->breadcrumbs[$this->panel] = [];

        $this->pendingPath = '/';

        $this->showConnectionLoader = true;
        $this->isLoading = true;

        $this->pendingOperationId = $operationId;

        $this->loadItems();
    }

    public function closeTab(): void
    {
        $this->showConnectionLoader = false;

        if ($this->panel !== 'right') {
            return;
        }

        if (is_null($this->connectionService->getConnection())) {
            $this->setPath(Path::ROOT);
            $this->loadItems();
        }
    }

    public function switchTab(): void
    {
        if ($this->panel !== 'right') {
            return;
        }

        $connection = $this->connectionService->getConnection();

        if (!$connection) {
            return;
        }

        $operationId = uniqid();

        $this->pendingOperationId = $operationId;

        $path = $this->connectionService->getConnectionPath($connection['id']);

        $this->pendingPath = $path;

        $this->connectionService->createListCommand(
            array_merge($connection, ['operation_id' => $operationId]),
            $path
        );

        //$this->updateNavigationState($path);

        $this->loadItems();
    }


    // Global application state
    public function setLanguage($event)
    {
        App::setLocale($event['code']);
    }


    //UI preferences
    public function setColumnWidth($column, $width, $panel)
    {
        ColumnWidth::updateOrCreate(
            ['panel' => $panel, 'column' => $column],
            ['width' => $width]
        );
        $this->loadColumnWidths();
    }

    public bool $showContextMenu = false;

    public array $contextMenu = [
        'panel' => null,
        'x' => 0,
        'y' => 0,
        'data' => []
    ];

    public function openContextMenu($sourcePath, $panel, $files, $x, $y)
    {

        $this->selected[$panel] = $files;
        $this->activePanel = $panel;

        $countItems = count($this->selected[$panel]);

        $selectedItems = $this->selected[$panel] ?? [];
        $firstItem = $selectedItems[0] ?? null;
        $isEmpty = $countItems === 0;
        $isRoot = $sourcePath === Path::ROOT;
        $isSingle = $countItems === 1;
        $isDir = $isSingle && ($firstItem['type'] ?? null) === 'dir';
        $openAction = $isDir ? 'changeDirectory' : 'open';

        $openParams = $isDir
            ? ['path' => $firstItem['path'], 'panel' => $panel]
            : ['openInExplorer' => false];

        $this->contextMenu = [
            'panel' => $panel,
            'x' => $x,
            'y' => $y,
            'data' => [

                $this->menuItem(
                    'Open',
                    $openAction,
                    $openParams,
                    !$isSingle
                ),

                $this->menuItem(
                    'Open in Explorer',
                    'open',
                    ['openInExplorer' => true],
                    $isEmpty
                ),

                $this->menuItem(
                    'Copy',
                    'fileAction',
                    ['type' => FileActions::COPY],
                    $isEmpty
                ),

                $this->menuItem(
                    'Move',
                    'fileAction',
                    ['type' => FileActions::MOVE],
                    $isEmpty
                ),

                $this->menuItem(
                    'Delete',
                    'fileAction',
                    ['type' => FileActions::DELETE],
                    $isEmpty
                ),

                $this->menuItem(
                    'Create Directory',
                    'fileAction',
                    ['type' => FileActionType::CREATE_DIRECTORY->value],
                    $isRoot
                ),

                $this->menuItem(
                    'Create File',
                    'fileAction',
                    ['type' => FileActionType::CREATE_FILE->value],
                    $isRoot
                ),
            ]
        ];

        $this->showContextMenu = true;
    }

    private function menuItem($label, $action, $params = [], $disabled = false)
    {
        return [
            'label' => __($label),
            'action' => $action,
            'params' => $params,
            'disabled' => $disabled,
        ];
    }

    public function closeContextMenu()
    {
        $this->showContextMenu = false;
    }

    /*
    |--------------------------------------------------------------------------
    | Sorting
    |--------------------------------------------------------------------------
    */

    public function sortBy($column)
    {
        if ($this->sortColumn === $column) {
            // If we are already sorting by this column, change the direction
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            // New column, start with ASC
            $this->sortColumn = $column;
            $this->sortDirection = 'asc';
        }
        $this->loadItems();

        session()->put("{$this->panel}_sort_column", $this->sortColumn);
        session()->put("{$this->panel}_sort_direction", $this->sortDirection);
    }

    private function sortFiles($files)
    {
        usort($files, function ($a, $b) {
            // Primary sorting by type
            $typeOrder = $this->sortDirection === 'asc' ? -1 : 1;
            if ($a['type'] === 'dir' && $b['type'] === 'file') {
                return $typeOrder; // asc: folders above, desc: files above
            }
            if ($a['type'] === 'file' && $b['type'] === 'dir') {
                return -$typeOrder;
            }
    
            $direction = $this->sortDirection === 'asc' ? 1 : -1;
    
            if ($this->sortColumn === 'name') {
                return $direction * strcmp($a['name'], $b['name']);
            } elseif ($this->sortColumn === 'size') {
                if ($a['size'] === 'dir' && $b['size'] === 'dir') return 0;
                if ($a['size'] === 'dir') return -$typeOrder;
                if ($b['size'] === 'dir') return $typeOrder;
                return $direction * (FileHelper::convertSizeToBytes($a['size']) - FileHelper::convertSizeToBytes($b['size']));
            } elseif ($this->sortColumn === 'modified') {
                return $direction * (strtotime($a['modified']) - strtotime($b['modified']));
            } elseif ($this->sortColumn === 'permissions') {
                return $direction * strcmp($a['permissions'], $b['permissions']);
            }
    
            return 0;
        });
    
        return $files;
    }

    /**
     * used from ui
     */
    private function normalizePath(string $path): string
    {
        return '/' . ltrim(PathHelper::normalize($path), '/');
    }

    /**
     * used from ui
     */
    private function canGoUp(): bool
    {
        $connection = $this->connectionService->getConnection();

        $root = '';

        if (
            $this->panel === 'right' &&
            $this->showConnectionLoader
        ) {
            return false;
        }

        if (!is_null($connection) && $this->panel === 'right') {
            $root = '/';
        }

        if (is_null($connection) || $this->panel === 'left') {
            $root = Path::ROOT;
        }

        return $this->getPath() !== $root;
    }
}