<div>

    <form wire:submit.prevent="save()">

        <div class="modal-header">
            <div>{{ __("Rename") }}</div>
            <a class="close" wire:click="$dispatch('modal.close')"><i class="bi bi-x"></i></a>
        </div>
        <div class="modal-body">
            <div class="form-group d-flex gap-2">
                <div class="l-12">
                    <label>{{ __("Enter new name") }}</label>
                    <input id="focus-input" type="text" @if($error)class="error"@endif wire:model="name" placeholder="{{ __('Enter new name') }}">
                </div>
            </div>

            @if($error)
                <div class="error">
                    {{ $error }}
                </div>
            @endif

        </div>
        <div class="modal-footer">
            <button type="submit" class="btn blue-btn">{{ __("Save") }}</button>
            <button type="button" class="btn gray-btn" wire:click="$dispatch('modal.close')">{{ __("Cancel") }}</button>
        </div>
    </form>
</div>
