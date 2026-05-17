<?php

namespace App\Enums;

enum FileActionError: string
{
    case SAME_DIRECTORY = 'same_directory';
    case LOCAL_PERMISSION_DENIED = 'local_permission_denied';
    case EMPTY_FILES = 'empty_files';
}