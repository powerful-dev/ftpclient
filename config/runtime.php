<?php

return [

    'base' => storage_path('runtime'),

    'bus' => storage_path('runtime/bus'),

    'workers' => storage_path('runtime/workers'),

    'dirs' => [
        'commands' => 'commands',
        'tasks'    => 'tasks',
        'results'  => 'results',
        'events'   => 'events',
        'locks'    => 'locks',
        'cache'    => 'cache',
    ],

];