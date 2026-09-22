<?php

declare(strict_types=1);

use App\Mcp\Servers\KoAkademyServer;
use Laravel\Mcp\Facades\Mcp;

Mcp::web('/mcp/koakademy', KoAkademyServer::class)
    ->middleware([
        'auth:sanctum',
        'api.enabled',
        'mcp.enabled',
        'api.tenant',
        'throttle:mcp',
    ]);
