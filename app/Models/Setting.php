<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Enums\Language;

class Setting extends Model
{
    protected $fillable = ['key', 'value'];

    protected $casts = [
        'language' => Language::class,
    ];
}