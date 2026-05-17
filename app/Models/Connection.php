<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Connection extends Model {

    protected $fillable = [
        'name', 'color', 'host', 'port', 'protocol', 'username', 'password',
        'ssh_key', 'last_left_path', 'last_right_path', 'authentication_type'
    ];

    protected $casts = [
        'password' => 'encrypted',
    ];
}