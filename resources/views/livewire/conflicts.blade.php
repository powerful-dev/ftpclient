<div>
    <div class="modal-header">
        <div>{{ __("File exists") }}</div>
        <a class="close" wire:click="$dispatch('modal.close')">
            <i class="bi bi-x"></i>
        </a>
    </div>

    <div class="modal-body conflict-body">

        <div class="conflict-info">

            <div class="path">
                <span class="label">{{ $sourceParts['dir'] }}</span>
                <span class="file">
                    <span class="common">{{ $diff['a_common'] }}</span><span class="diff">{{ $diff['a_diff'] }}</span>
                </span>
            </div>

            <div class="path">
                <span class="dir">{{ $destParts['dir'] }}</span>
                    
                @if(!$renameMode)
                    <span class="file">
                        <span class="common">{{ $diff['b_common'] }}</span>
                        <span class="diff">{{ $diff['b_diff'] }}</span>
                    </span>
                @else
                    <div class="rename">
                        <input 
                            type="text" 
                            wire:model="newName"
                            class="input"
                        >

                        <button class="btn primary" wire:click="applyRename">
                            ✔
                        </button>
                    </div>
                @endif

                
            </div>

        </div>

        <div class="conflict-actions">

            <button class="btn primary" wire:click="choose('overwrite')">
                {{ __("Replace") }}
            </button>

            <button class="btn" wire:click="choose('skip')">
                {{ __("Skip") }}
            </button>

            <button class="btn" wire:click="enableRename">
                {{ __("Rename") }}
            </button>

            <div class="divider"></div>

            <button class="btn danger" wire:click="choose('overwrite_all')">
                {{ __("Replace all") }}
            </button>

            <button class="btn" wire:click="choose('skip_all')">
                {{ __("Skip all") }}
            </button>

        </div>

    </div>
</div>
