<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Toast extends Model
{
    protected $fillable = ['task_id', 'value', 'created_at', 'updated_at'];
}
