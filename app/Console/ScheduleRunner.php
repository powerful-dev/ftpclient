<?php

use Illuminate\Support\Facades\Artisan;

require __DIR__ . '/../../vendor/autoload.php';

$app = require_once __DIR__ . '/../../bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

try {
    while (true) {
        Artisan::call('schedule:run');
    }
} catch (\Exception $e) {
}
