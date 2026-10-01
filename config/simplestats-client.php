<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Auth\Events\Login;

/*
|--------------------------------------------------------------------------
| SimpleStats (optional third-party server-side analytics)
|--------------------------------------------------------------------------
|
| Koakademy ships without this provider enabled. SimpleStats is a commercial,
| third-party service that reports visits, registrations, and payment
| attribution to an external account (https://simplestats.io). The upstream
| package enables it by default; this file overrides that to opt-in so that a
| fresh install of Koakademy does not contact any third party.
|
| Self-hosting Koakademy? Leave this disabled. The built-in analytics settings
| (System Management > Analytics) cover pageviews, and Laravel Pulse covers
| server-side request monitoring, with no external calls.
|
| To opt in, set SIMPLESTATS_ENABLED=true and supply your own API token.
|
*/

return [

    'enabled' => env('SIMPLESTATS_ENABLED', false),

    'except' => [
        'telescope*',
        'horizon*',
        'pulse*',
        'admin*',
    ],

    'blocked_ips' => [
        // '192.168.1.1',
        // '10.0.0.0/8',
        // '172.16.0.0/12',
    ],

    'api_url' => env('SIMPLESTATS_API_URL', 'https://simplestats.io/api/v1/'),

    'api_token' => env('SIMPLESTATS_API_TOKEN'),

    'queue' => env('SIMPLESTATS_QUEUE', 'default'),

    'log_errors' => env('SIMPLESTATS_LOG_ERRORS', false),

    'middleware_groups' => ['web'],

    'tracking_storage' => 'session',

    'beacon' => [
        'enabled' => env('SIMPLESTATS_BEACON_ENABLED', false),
        'path' => env('SIMPLESTATS_BEACON_PATH', 'assets/v'),
    ],

    'tracking_codes' => [
        'source' => ['utm_source', 'ref'],
        'medium' => ['utm_medium', 'adGroup', 'adGroupId'],
        'campaign' => ['utm_campaign'],
        'term' => ['utm_term'],
        'content' => ['utm_content'],
    ],

    'tracking_types' => [
        'login' => [
            'event' => Login::class,
        ],

        'user' => [
            'model' => User::class,
        ],

        'payment' => [
            'model' => null,
        ],
    ],

    'custom_properties_resolvers' => [
        'user' => null,
        'visitor' => null,
    ],
];
