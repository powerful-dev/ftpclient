<div>
    <div class="modal-header">
        <div>
            {{ __("Administrator permissions required") }}
        </div>

        <a class="close" wire:click="$dispatch('modal.close')">
            <i class="bi bi-x"></i>
        </a>
    </div>

    <div class="modal-body">

        <div class="elevation-info">

            <div class="description">
                {{ $payload['message'] ?? '' }}
            </div>

        </div>

        <div class="conflict-actions d-flex justify-content-center gap-2">

            <button
                class="btn"
                wire:click="$dispatch('modal.close')"
            >
                {{ __("Cancel") }}
            </button>

            <button
                class="btn primary"
                wire:click="confirm"
            >
                {{ __("Continue as Administrator") }}
            </button>

        </div>
    </div>
</div>