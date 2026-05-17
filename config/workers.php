<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Worker Pool Sizes
    |--------------------------------------------------------------------------
    |
    | Defines how many parallel worker processes should be started for each
    | worker type.
    |
    | - file_watcher: Usually 1 process is enough (lightweight, event-driven).
    | - job_worker: Handles file operations (copy, move, delete, etc.).
    |   Can be increased to enable parallel task execution.
    |
    | Keep in mind:
    | - More workers = higher parallelism, but also higher CPU/IO load.
    | - For remote connections (FTP/SFTP), it is recommended to avoid too many
    |   concurrent workers per connection.
    |
    */
    'pool' => [

        'file_watcher' => 1,
        'job_worker' => 3,
    ],

    /*
    |--------------------------------------------------------------------------
    | Worker Runtime Behavior
    |--------------------------------------------------------------------------
    |
    | connection_idle_ttl:
    | Time (in seconds) after which an idle connection will be automatically
    | closed inside a worker process.
    |
    | Lower values:
    | - reduce number of open connections
    | - increase reconnect frequency
    |
    | Higher values:
    | - keep connections warm
    | - use more memory / sockets
    |
    */

    'connection_idle_ttl' => 60,

];