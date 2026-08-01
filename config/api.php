<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | External API Configuration
    |--------------------------------------------------------------------------
    |
    | Settings for the versioned external API (/api/v1/*) consumed by mobile
    | applications and third-party integrations. The internal JSON endpoints
    | used by the first-party Inertia SPA are not affected by these values.
    |
    */

    // Requests per minute allowed per authenticated user (or per IP for
    // unauthenticated calls) across every /api/* route.
    'rate_limit' => env('API_RATE_LIMIT', 60),

    // Requests per minute allowed per email (or per IP) on the credential
    // and OTP endpoints under /api/v1/auth/*.
    'login_rate_limit' => env('API_LOGIN_RATE_LIMIT', 5),
];
