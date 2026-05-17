<?php

use Illuminate\Support\Facades\Route;

Route::get('/', ['\App\Http\Controllers\FtpController', 'init'])->name('init');

Route::post('/ping', function () {
    return response()->json(['status' => 'ok']);
})->name('ping');
