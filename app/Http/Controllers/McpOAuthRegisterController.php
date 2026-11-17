<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Laravel\Mcp\Server\Http\Controllers\OAuthRegisterController;

final class McpOAuthRegisterController extends OAuthRegisterController
{
    protected function isValidRedirectUri(string $value): bool
    {
        if (! parent::isValidRedirectUri($value) || parse_url($value, PHP_URL_FRAGMENT) !== null) {
            return false;
        }

        // HTTP callbacks are safe only for native clients using loopback.
        return parse_url($value, PHP_URL_SCHEME) !== 'http'
            || $this->isLocalhostUrl($value);
    }
}
