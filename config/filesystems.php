<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Default Filesystem Disk
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default filesystem disk that should be used
    | by the framework. The "local" disk, as well as a variety of cloud
    | based disks are available to your application for file storage.
    |
    */

    'default' => env('FILESYSTEM_DISK', 'public'),

    /*
    |--------------------------------------------------------------------------
    | Private Export Disk
    |--------------------------------------------------------------------------
    |
    | Generated exports (assessment forms, student lists, timetable PDFs) must
    | never be written to a disk that is linked into the document root, or the
    | files become reachable without authentication. They are written to this
    | disk instead.
    |
    | The built-in "private" disk is local, so a deployment running more than
    | one application replica has to point this at a shared volume or at an
    | object storage disk (s3/r2) that every replica can read.
    |
    */

    'private_disk' => env('FILESYSTEM_PRIVATE_DISK', 'private'),

    /*
    |--------------------------------------------------------------------------
    | Filesystem Disks
    |--------------------------------------------------------------------------
    |
    | Below you may configure as many filesystem disks as necessary, and you
    | may even configure multiple disks for the same driver. Examples for
    | most supported storage drivers are configured here for reference.
    |
    | Supported drivers: "local", "ftp", "sftp", "s3"
    |
    */

    'disks' => [

        'local' => [
            'driver' => 'local',
            'root' => storage_path('app'),
            'serve' => false,
            'throw' => false,
            'report' => false,
        ],

        'private' => [
            'driver' => 'local',
            'root' => storage_path('app/private'),
            'serve' => false,
            'throw' => false,
            'report' => false,
        ],

        'library' => env('LIBRARY_EBOOK_DRIVER', 'local') === 's3'
            ? [
                'driver' => 's3',
                'key' => env('LIBRARY_R2_ACCESS_KEY_ID'),
                'secret' => env('LIBRARY_R2_SECRET_ACCESS_KEY'),
                'region' => env('LIBRARY_R2_DEFAULT_REGION', 'auto'),
                'bucket' => env('LIBRARY_R2_BUCKET'),
                'endpoint' => env('LIBRARY_R2_ENDPOINT'),
                'use_path_style_endpoint' => false,
                'visibility' => 'private',
                'throw' => true,
                'report' => true,
            ]
            : [
                'driver' => 'local',
                'root' => storage_path('app/private/library'),
                'serve' => false,
                'visibility' => 'private',
                'throw' => true,
                'report' => true,
            ],

        'public' => [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'url' => env('APP_URL').'/storage',
            'visibility' => 'public',
            'throw' => false,
            'report' => false,
        ],
        's3' => [
            'driver' => 's3',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION'),
            'bucket' => env('AWS_BUCKET', 'default-bucket'),
            'url' => env('AWS_URL'),
            'endpoint' => env('AWS_ENDPOINT'),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
            'throw' => false,
            'report' => false,
        ],
        'r2' => [
            'driver' => 's3',
            'key' => env('R2_ACCESS_KEY_ID'),
            'secret' => env('R2_SECRET_ACCESS_KEY'),
            'region' => env('R2_DEFAULT_REGION', 'auto'),
            'bucket' => env('R2_BUCKET', 'default-r2-bucket'),
            'url' => env('R2_URL'),
            'endpoint' => env('R2_ENDPOINT'),
            'use_path_style_endpoint' => false,
            'throw' => false,
            'report' => false,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Symbolic Links
    |--------------------------------------------------------------------------
    |
    | Here you may configure the symbolic links that will be created when the
    | `storage:link` Artisan command is executed. The array keys should be
    | the locations of the links and the values should be their targets.
    |
    */

    'links' => [
        public_path('storage') => storage_path('app/public'),
    ],

];
