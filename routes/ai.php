<?php

declare(strict_types=1);

use App\Http\Middleware\ResetFeatureAccessState;
use App\Mcp\Servers\KoAkademyServer;
use Laravel\Mcp\Facades\Mcp;

Mcp::web('/mcp/koakademy', KoAkademyServer::class)
    ->middleware([
        ResetFeatureAccessState::class,
        'auth:sanctum',
        'api.enabled',
        'mcp.enabled',
        'api.tenant',
        'throttle:mcp',
    ]);
