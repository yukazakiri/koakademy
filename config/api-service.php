<?php

declare(strict_types=1);

return [
    'navigation' => [
        'token' => [
            'cluster' => null,
            'group' => 'Administration',
            'sort' => 4,
            'icon' => 'heroicon-o-key',
            'should_register_navigation' => true,
        ],
    ],
    'models' => [
        'token' => [
            'enable_policy' => true,
        ],
    ],
    'route' => [
        'panel_prefix' => false,
        'use_resource_middlewares' => false,
    ],
    'tenancy' => [
        'enabled' => false,
        'awareness' => false,
    ],
    'login-rules' => [
        'email' => 'required|email',
        'password' => 'required',
    ],
    'login-middleware' => [
        // Add any additional middleware you want to apply to the login route
    ],
    'logout-middleware' => [
        'auth:sanctum',
        // Add any additional middleware you want to apply to the logout route
    ],
    'use-spatie-permission-middleware' => true,
];
