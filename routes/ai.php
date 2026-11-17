<?php

declare(strict_types=1);

use App\Http\Controllers\McpOAuthRegisterController;
use App\Http\Middleware\ResetFeatureAccessState;
use App\Mcp\Servers\KoAkademyServer;
use Illuminate\Support\Facades\Route;
use Laravel\Mcp\Facades\Mcp;

Mcp::oauthRoutes(registration: false);

Route::post('/oauth/register', McpOAuthRegisterController::class)
    ->middleware('throttle:mcp-registration')
    ->name('mcp.oauth.register');

Mcp::web('/mcp/koakademy', KoAkademyServer::class)
    ->middleware([
        ResetFeatureAccessState::class,
        'auth.mcp',
        App\Http\Middleware\EnsureUserAccountAccess::class,
        'api.enabled',
        'mcp.enabled',
        'api.tenant',
        'throttle:mcp',
    ]);
