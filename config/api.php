<?php

declare(strict_types=1);

return [
    'enabled' => (bool) env('API_ENABLED', true),
    'version' => 'v1',
    'token_expiration' => (int) env('API_TOKEN_EXPIRATION', 43200),
    'rate_limit' => (int) env('API_RATE_LIMIT', 60),
    'login_rate_limit' => (int) env('API_LOGIN_RATE_LIMIT', 5),
    'otp_rate_limit' => (int) env('API_OTP_RATE_LIMIT', 5),
    'default_per_page' => (int) env('API_DEFAULT_PER_PAGE', 20),
    'max_per_page' => (int) env('API_MAX_PER_PAGE', 100),
    'max_upload_kb' => (int) env('API_MAX_UPLOAD_KB', 51200),
    'abilities' => [
        'read' => 'mobile:read',
        'write' => 'mobile:write',
    ],
];
