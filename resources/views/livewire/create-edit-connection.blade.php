<form wire:submit.prevent="saveConnection()">

    <div class="form-group d-flex gap-2">
        <div class="l-9">
            <label for="profile-name">{{ __("Profile Name") }}</label>
            <input type="text" @error('name')class="error"@enderror wire:model="name" placeholder="{{ __('Enter profile name') }}">
        </div>
        <div class="l-3">
            <label for="profile-color">{{ __("Profile Color") }}</label>
            <input type="color" wire:model="color" value="#{{ $defaultProfileColor }}">
        </div>
    </div>
    <div class="form-group d-flex gap-2">
        <div class="l-9">
            <label for="host">{{ __("Server Address") }}</label>
            <input type="text" @error('host')class="error"@enderror wire:model="host" placeholder="{{ __('e.g.') }} 37.27.139.228">
        </div>
        <div class="l-3">
            <label for="port">{{ __("Port (optional)") }}</label>
            <input type="text" wire:model="port" placeholder="{{ __("Default") }}: 21 (FTP), 22 (SFTP)">
        </div>
    </div>
    <div class="form-group">
        <label for="protocol">{{ __("Protocol") }}</label>
        <select wire:model="protocol">
            @foreach ($aProtocols as $key => $aProtocol)
                <option value="{{ $key }}">{{ $aProtocol }}</option>
            @endforeach
        </select>
    </div>

    <div class="form-group">
        <label for="auth-type">{{ __("Authentication Type") }}</label>
        <select wire:model="authentication_type" wire:change="changeAuthenticationType($event.target.value)">
            @foreach ($aAuthenticationTypes as $key => $aAuthenticationType)
                <option value="{{ $key }}">{{ $aAuthenticationType }}</option>
            @endforeach
        </select>
    </div>

    <div @class(['form-group', 'd-none' => !$ssh_key_field])>
        <label for="ssh-key">{{ __("SSH Key") }}</label>
        <textarea wire:model="ssh_key" placeholder="{{ __("Paste your SSH private key") }}"></textarea>
    </div>

    <div @class(['form-group', 'd-flex', 'gap-2', 'd-none' => $ssh_key_field])>
        <div class="l-6">
            <label for="username">{{ __("Username (optional)") }}</label>
            <input type="text" wire:model="username" placeholder="{{ __("Enter username") }}">
        </div>
        <div @class(['form-group', 'l-6'])>
            <label for="password">{{ __("Password") }}</label>
            <input type="password" wire:model="password" placeholder="{{ __("Enter password") }}">
        </div>
    </div>

    <div class="form-group d-flex gap-2">
        <div class="l-6">
            <label>Локальный каталог по умолчанию</label>
            <input type="text" wire:model="last_left_path" placeholder="Локальный каталог по умолчанию">
        </div>
        <div class="l-6">
            <label>Удаленный каталог по умолчанию</label>
            <input type="text" wire:model="last_right_path" placeholder="Удаленный каталог по умолчанию">
        </div>
    </div>

    <div class="form-group d-flex justify-content-between">
        <div class="d-flex align-items-center gap-1">

        </div>
        @if (is_null($edited_connection))
            <div class="d-flex align-items-center">
                <label class="d-flex align-items-center">
                    <input type="checkbox" wire:model="save_profile" checked> {{ __("Save this profile") }}
                </label>
            </div>
        @endif
    </div>

    <div class="form-group d-flex gap-2 justify-content-end">
        <button type="button" class="btn blue-btn" wire:click="save">{{ __("Save") }}</button>
        <button type="button" class="btn green-btn" wire:click="connect">{{ __("Connect") }}</button>
        <button type="button" class="btn gray-btn" wire:click="close">{{ __("Cancel") }}</button>
    </div>
</form>