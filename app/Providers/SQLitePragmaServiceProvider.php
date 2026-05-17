<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\DB;

class SQLitePragmaServiceProvider extends ServiceProvider
{
    public function boot()
    {
        if (config('database.default') === 'sqlite') {
            $pdo = DB::connection()->getPdo();

            $pdo->exec('PRAGMA journal_mode = WAL;');
            $pdo->exec('PRAGMA synchronous = NORMAL;');
            $pdo->exec('PRAGMA busy_timeout = 5000;');
        }
    }
}