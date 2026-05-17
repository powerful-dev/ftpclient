<div>
    <div class="modal-header">
        {{ __("Settings") }}
        <div>{{ __("Settings") }}</div>
        <a class="close" wire:click="$dispatch('modal.close')"><i class="bi bi-x"></i></a>
    </div>

    <div class="modal-body">
        <div class="form-group">
            <label for="protocol">{{ __("Language") }}</label>
            <select wire:change="setLanguage" wire:model.live="language">
                @foreach ($languages as $key => $language)
                    <option value="{{ $key }}">{{ $language }}</option>
                @endforeach
            </select>
        </div>
    </div>
</div>