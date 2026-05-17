<?php

namespace App\Livewire;

use Livewire\Component;
use \App\Models\Connection;
use App\Enums\Protocol;
use App\Enums\AuthenticationType;


class CreateEditConnection extends Component
{

    public $aProtocols = [];
    public $aAuthenticationTypes = [];
    public $save_profile;
    public $ssh_key_field = false;
    public $defaultProfileColor = '3592C4';
    public $last_left_path = '/';
    public $last_right_path = '/';

    public $edited_connection;

    public $id, $name, $color, $host, $port, $protocol, $username, $authentication_type, $password, $ssh_key;

    protected $rules = [
        'name' => 'required',
        'host' => 'required',
    ];

    public function mount($connection = false) {

        $this->aProtocols = Protocol::options();
        $this->protocol = count($this->aProtocols) > 0 ? collect($this->aProtocols)->first() : null;

        $this->aAuthenticationTypes = AuthenticationType::options();
        $this->authentication_type = count($this->aAuthenticationTypes) > 0 ? collect($this->aAuthenticationTypes)->first() : null;

        $this->save_profile = true;

        if ($connection && is_object($connection)) {

            $this->save_profile = false;

            $this->edited_connection = $connection;

            $this->name = $this->edited_connection->name;
            $this->color = $this->edited_connection->color;
            $this->host = $this->edited_connection->host;
            $this->port = $this->edited_connection->port;
            $this->protocol = $this->edited_connection->protocol;
            $this->username = $this->edited_connection->username;
            $this->authentication_type = $this->edited_connection->authentication_type;
            $this->password = $this->edited_connection->password;
            $this->ssh_key = $this->edited_connection->ssh_key;
            $this->last_left_path = $this->edited_connection->last_left_path;
            $this->last_right_path = $this->edited_connection->last_right_path;

            $this->changeAuthenticationType($this->authentication_type);
        }
    }

    public function changeAuthenticationType($value)
    {
        $this->ssh_key_field = $value == 'ssh_key' ? true : false;
    }

    public function saveForm() : Connection
    {
        $this->validate();

        $profileData = [
            'id' => $this->id,
            'name' => $this->name,
            'host' => $this->host,
            'port' => $this->port,
            'protocol' => $this->protocol,
            'username' => $this->username,
            'authentication_type' => $this->authentication_type,
            'password' => $this->password,
            'ssh_key' => $this->ssh_key,
            'color' => $this->color,
            'last_left_path' => $this->last_left_path,
            'last_right_path' => $this->last_right_path,
        ]; 

        if ($this->save_profile) {         

            $connection = Connection::create($profileData);

        } else if (!is_null($connection = $this->edited_connection)) {

            $connection->update($profileData);
        }

        $this->dispatch('refreshConnections');

        return $connection;
    }

    public function save()
    {
        $this->saveForm();
        $this->dispatch('refreshConnections');
    }

    public function connect()
    {
        $connection = $this->saveForm();

        $this->dispatch('modal.close');

        $this->dispatch('addActiveConnection', $connection->id);
    }

    public function close()
    {
        $this->dispatch('refreshConnections');
    }

    public function render()
    {
        return view('livewire.create-edit-connection');
    }

    public function messages()
    {
        return [
            'name.required' => 'Пожалуйста, заполните поле "Название Профиля"',
            'host.required' => 'Пожалуйста, заполните поле "Хост"',
        ];
    }
}
