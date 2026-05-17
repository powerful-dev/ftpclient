<?php

namespace App\Services;

use App\Support\Runtime;

class EventService
{

    public function emit(array $event): void
    {
        $dir = Runtime::path('events');

        if (!is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }

        // unique filename
        $file = $dir . '/' . microtime(true) . '_' . bin2hex(random_bytes(4)) . '.json';

        $event['ts'] = microtime(true);

        file_put_contents(
            $file,
            json_encode($event, JSON_UNESCAPED_UNICODE),
            LOCK_EX
        );
    }
}