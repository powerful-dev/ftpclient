<div>
    <div class="modal-header">
        <div>{{ __("All Connections") }}</div>
        <a class="close" wire:click="$dispatch('modal.close')">
            <i class="bi bi-x"></i>
        </a>
    </div>

    <div class="modal-body">

        @if ($creating || $editingConnection !== null)
            @livewire(
                'create-edit-connection',
                ['connection' => $editingConnection],
                key($editingConnection
                    ? 'edit-' . $editingConnection->id
                    : 'create-connection')
            )
        @else

            <div class="connections-header">
                <span>{{ __("My Connections") }}</span>

                <button type="button"
                        class="btn blue-btn"
                        wire:click="create">
                    <span>+</span> {{ __("Add New Connection") }}
                </button>
            </div>

            @if ($connections->count() > 0)
                <div class="connections-list">
                    @foreach($connections as $connection)
                        <div class="connection-item">
                            <div class="d-flex justify-content-between align-items-center">

                                <div class="d-flex align-items-center gap-2">
                                    <span class="profile-color-dot"
                                          @if($connection->color)
                                              style="background-color: {{ $connection->color }}"
                                          @endif>
                                    </span>

                                    <span class="connection-name">
                                        {{ $connection->name }}
                                    </span>

                                    <span class="connection-host">
                                        ({{ $connection->host }})
                                    </span>
                                </div>

                                <div class="d-flex gap-2">
                                    <button class="btn success"
                                            wire:click="connect({{ $connection->id }})">
                                        {{ __("Connect") }}
                                    </button>

                                    <button class="btn primary"
                                            wire:click="edit({{ $connection->id }})">
                                        {{ __("Edit") }}
                                    </button>

                                    <button class="btn danger"
                                            wire:click="delete({{ $connection->id }})">
                                        {{ __("Delete") }}
                                    </button>
                                </div>

                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <p>{{ __("There are no connections yet") }}</p>
            @endif

        @endif
    </div>
</div>