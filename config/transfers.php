<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Transfer Progress Configuration
    |--------------------------------------------------------------------------
    |
    | Defines how frequently progress updates should be emitted during
    | file transfer operations.
    |
    | Lower intervals:
    | - smoother and more responsive UI updates
    | - higher CPU and IPC/event overhead
    |
    | Higher intervals:
    | - reduced event overhead
    | - less responsive progress indicators
    |
    */

    'progress' => [

        /*
        | Remote FTP/SFTP transfer progress update interval (seconds)
        */
        'remote_interval' => 0.5,

        /*
        | Local filesystem copy progress update interval (seconds)
        */
        'local_interval' => 0.1,
    ],
];