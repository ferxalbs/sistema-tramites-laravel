<?php

return [
    'directory' => env('BACKUP_DIRECTORY') ?: storage_path('backups'),
    'password' => env('BACKUP_PASSWORD'),
];
