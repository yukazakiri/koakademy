<?php

declare(strict_types=1);

return [

    // Enable or disable monitoring
    'enabled' => env('LARAOWL_ENABLED', false),

    // Application Token and Server URL
    'token' => env('LARAOWL_TOKEN'),
    'server_url' => env('LARAOWL_SERVER_URL', 'https://laraowl.test'),

    // Metadata about the environment
    'environment' => [
        'deploy_id' => env('LARAOWL_DEPLOY'),
        'server_name' => env('LARAOWL_SERVER_NAME', gethostname()),
    ],

    // Privacy and Data Redaction
    'privacy' => [
        'capture_source_code' => env('LARAOWL_CAPTURE_SOURCE_CODE', true),
        'capture_payload' => env('LARAOWL_CAPTURE_PAYLOAD', false),
        'redact_fields' => explode(',', env('LARAOWL_REDACT_FIELDS', '_token,password,password_confirmation')),
        'redact_headers' => explode(',', env('LARAOWL_REDACT_HEADERS', 'Authorization,Cookie,Proxy-Authorization,X-XSRF-TOKEN')),
    ],

    // Sampling Rates (1.0 = capture everything)
    'sampling' => [
        'requests' => (float) env('LARAOWL_SAMPLE_REQUESTS', 1.0),
        'commands' => (float) env('LARAOWL_SAMPLE_COMMANDS', 1.0),
        'exceptions' => (float) env('LARAOWL_SAMPLE_EXCEPTIONS', 1.0),
        'tasks' => (float) env('LARAOWL_SAMPLE_TASKS', 1.0),
    ],

    // What to ignore
    'ignore' => [
        'cache' => env('LARAOWL_IGNORE_CACHE', false),
        'mail' => env('LARAOWL_IGNORE_MAIL', false),
        'notifications' => env('LARAOWL_IGNORE_NOTIFICATIONS', false),
        'queries' => env('LARAOWL_IGNORE_QUERIES', false),
        'outgoing_requests' => env('LARAOWL_IGNORE_OUTGOING', false),
    ],

    // Transmission Settings
    'ingest' => [
        'timeout' => (float) env('LARAOWL_INGEST_TIMEOUT', 2.0),
        'buffer_size' => (int) env('LARAOWL_INGEST_BUFFER', 500),
    ],
];
