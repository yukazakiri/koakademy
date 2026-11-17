<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Contracts\Auth\Authenticatable;
use Laravel\Passport\Client;
use Laravel\Passport\Http\Controllers\AuthorizationController;

final class McpAuthorizationController extends AuthorizationController
{
    /**
     * Public clients have no secret, so a previously approved client ID must
     * not allow another installation to silently reuse the user's consent.
     *
     * @param  \Laravel\Passport\Scope[]  $scopes
     */
    protected function hasGrantedScopes(Authenticatable $user, Client $client, array $scopes): bool
    {
        return false;
    }
}
