<div class="tabs">

    @foreach($activeConnections as $connection)

        <div
            @if ($connection['uid'] != $activeTab)
                wire:click="switchTab('{{ $connection['uid'] }}')"
            @endif

            @class([
                "tab",
                "active" => $connection['uid'] === $activeTab
            ])
        >
            <span class="profile-color-dot"
                    @if(!empty($connection['color']))
                        style="background-color: {{ $connection['color'] }}"
                    @endif>
            </span>

            <span>{{ $connection['name'] }}</span>

            <div
                class="close"
                wire:click.stop="closeTab('{{ $connection['uid'] }}')">
            </div>
        </div>

    @endforeach
</div>