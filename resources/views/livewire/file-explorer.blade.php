
<div class="panel {{ $panel }}" >
    <div class="path">

        @foreach ($breadcrumbs[$panel] as $index => $crumb)

            <a 
                wire:click="changeDirectory('{{ $crumb['path'] }}', '{{ $panel }}')"
                href="javascript:void(0)"
               
            >
                {{ $crumb['name'] }}
            </a>

            @if (!$loop->last)
                >
            @endif

        @endforeach

    </div>

    @if($showContextMenu && count($contextMenu['data']) > 0)
        <div 
            class="context-menu"
            id="context-menu-{{ $panel }}"
            style="top: {{ $contextMenu['y'] }}px; left: {{ $contextMenu['x'] }}px;"
        >

            @foreach($contextMenu['data'] as $item)
                <div 
                    @class([
                        'item', 
                        'disabled' => $item['disabled'] ?? false
                        ])
                    @if (!$item['disabled'])
                        onclick="ActionHelper.{{ $item['action'] }}('file-explorer', @js($item['params']))"
                    @endif
                >
                    {{ $item['label'] }}
                </div>
            @endforeach

        </div>
    @endif

    <div class="file-list" id="{{ $panel }}-panel" data-path="{{ $this->getPath() }}">
        
        <div class="headers">
            <div class="column" data-column="name" style="width: {{ $columnWidths['name'] }}">
                <span class="sortable" wire:click="sortBy('name')">{{ __("File Name") }}</span>
                @if ($sortColumn === 'name')
                    {{ $sortDirection === 'asc' ? '▲' : '▼' }}
                @endif
                <div class="resizer" data-column="name"></div>
            </div>
            <div class="column" data-column="size" style="width: {{ $columnWidths['size'] }}">
                <span class="sortable" wire:click="sortBy('size')">{{ __("Size") }}</span>
                @if ($sortColumn === 'size')
                    {{ $sortDirection === 'asc' ? '▲' : '▼' }}
                @endif
                <div class="resizer" data-column="size"></div>
            </div>
            <div class="column" data-column="modified" style="width: {{ $columnWidths['modified'] }}">
                <span class="sortable" wire:click="sortBy('modified')">{{ __("Modified") }}</span>
                @if ($sortColumn === 'modified')
                    {{ $sortDirection === 'asc' ? '▲' : '▼' }}
                @endif
                <div class="resizer" data-column="modified"></div>
            </div>
            <div class="column" data-column="permissions" style="width: {{ $columnWidths['permissions'] }}">
                <span class="sortable" wire:click="sortBy('permissions')">{{ __("Permissions") }}</span>
                @if ($sortColumn === 'permissions')
                    {{ $sortDirection === 'asc' ? '▲' : '▼' }}
                @endif
                <div class="resizer" data-column="permissions"></div>
            </div>
        </div>

        <div class="body">

            @if($panel === 'right' && $isLoading)
                <div @class([
                    "con-loading" => $showConnectionLoader
                ]) wire:poll.500ms.keep-alive="loadItems">
                </div>
            @endif
   
            <div class="file-list-content" data-root="{{ \App\Constants\Path::ROOT }}" data-path="{{ $this->getPath() }}">
                @if ($this->canGoUp())
                    <div 
                        @php
                            $parentPath = \App\Helpers\PathHelper::normalizeSlashes($this->getParentPath());
                        @endphp
                        @class([
                            "file-item folder go-up",
                            "navigating" => $pendingPath && $pendingPath === $parentPath,
                        ])
                        data-path="{{ $parentPath }}">
                        <div style="width: {{ $columnWidths['name'] }}" class="file-item-col d-flex"><div class="icon folder-icon"></div>...</div>
                        <div style="width: {{ $columnWidths['size'] }}" class="file-item-col"></div>
                        <div style="width: {{ $columnWidths['modified'] }}" class="file-item-col"></div>
                        <div style="width: {{ $columnWidths['permissions'] }}" class="file-item-col"></div>
                    </div>
                @endif
            
                @foreach ($files as $file)

                    @php
                        $isSelected = collect($selected[$panel] ?? [])
                            ->contains(fn($f) => $f['path'] === $file['path']);
                    @endphp

                    <div class="d-flex">
  
                        <div data-type="{{ $file['type'] }}" @class([
                            "file-item",
                            "selected" => $isSelected,
                            "folder" => $file['type'] === 'dir',
                            "file" => $file['type'] === 'file',
                            "actions" => $file['size'] != config('app.drive_label'),
                            "navigating" => $pendingPath && 
                                            $this->normalizePath($pendingPath) === $this->normalizePath($file['path']),
                        ]) 
                            wire:key="file-{{ md5($file['path']) }}"
                            data-path="{{ $file['path'] }}"
                            draggable="true"
                            data-name="{{ $file['name'] }}">
                            <div style="width: {{ $columnWidths['name'] }}" class="file-item-col d-flex overflow-hidden">
                                <i class="icon {{ $file['icon'] }}"></i>
                                <div class="file-name">{{ $file['name'] }}</div>
                            </div>
                            <div style="width: {{ $columnWidths['size'] }}" class="file-item-col">{{ $file['size'] }}</div>
                            <div style="width: {{ $columnWidths['modified'] }}" class="file-item-col">{{ $file['modified'] }}</div>
                            <div style="width: {{ $columnWidths['permissions'] }}" class="file-item-col">{{ $file['permissions'] }}</div>
                        </div>

                    </div>
                @endforeach
            </div>
        </div>
    </div>
</div>