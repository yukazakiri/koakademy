<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Public module registry
    |--------------------------------------------------------------------------
    |
    | Registry access is disabled until a public, signed registry is configured.
    | This keeps an application from fetching or executing third-party module
    | code merely because the marketplace layer is present.
    |
    */
    'enabled' => (bool) env('MODULE_MARKETPLACE_ENABLED', false),
    'registry_url' => env('MODULE_REGISTRY_URL'),
    'cache_ttl' => (int) env('MODULE_REGISTRY_CACHE_TTL', 3600),
    'require_signature' => (bool) env('MODULE_REGISTRY_REQUIRE_SIGNATURE', true),
    'public_key' => env('MODULE_REGISTRY_PUBLIC_KEY'),
    'public_key_file' => env('MODULE_REGISTRY_PUBLIC_KEY_FILE'),
];
