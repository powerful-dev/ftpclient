<div>
    <div class="modal-header">
        <div>Error</div>
        <a class="close" wire:click="$dispatch('modal.close')"><i class="bi bi-x"></i></a>
    </div>

    <div class="modal-body fs-14">
        @foreach (($payload['errors'] ?? []) as $error)
            <p class="error">{{ $error }}</p>
        @endforeach
    </div>
</div>
