<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ColumnWidth extends Model
{
    protected $fillable = ['panel', 'column', 'width'];
}