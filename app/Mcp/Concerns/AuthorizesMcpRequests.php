<?php

declare(strict_types=1);

namespace App\Mcp\Concerns;

use App\Models\School;
use App\Models\User;
use App\Services\TenantContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Laravel\Mcp\Request;

trait AuthorizesMcpRequests
{
    protected function user(Request $request): User
    {
        $user = $request->user();

        if (! $user instanceof User) {
            throw new AuthenticationException('Authentication is required.');
        }

        // MCP HTTP requests always arrive with a Sanctum token. The testing
        // harness authenticates the user directly, so only tests may resolve
        // the latest fixture token as a convenience.
        if (app()->environment('testing') && $user->currentAccessToken() === null) {
            $token = $user->tokens()->latest('id')->first();

            if ($token !== null) {
                $user->withAccessToken($token);
            }
        }

        return $user;
    }

    protected function school(): School
    {
        $school = app(TenantContext::class)->getCurrentSchool();

        if (! $school instanceof School) {
            throw new AuthorizationException('An accessible school context is required.');
        }

        return $school;
    }

    protected function requireRead(Request $request): User
    {
        $user = $this->user($request);
        $this->requireTokenAbility($user, (string) config('api.mcp.abilities.read', 'mcp:read'));
        $this->school();

        return $user;
    }

    protected function requireWrite(Request $request): User
    {
        $user = $this->requireRead($request);

        if (! app(\App\Services\GeneralSettingsService::class)->isMcpWriteEnabled()) {
            throw new AuthorizationException('MCP data modifications are disabled in system settings.');
        }

        $this->requireTokenAbility($user, (string) config('api.mcp.abilities.write', 'mcp:write'));

        return $user;
    }

    protected function requireAdmin(Request $request): User
    {
        $user = $this->requireRead($request);

        if (! $user->canAccessAdminPortal() && ! $user->hasRole('super_admin')) {
            throw new AuthorizationException('This tool is restricted to administrators.');
        }

        return $user;
    }

    protected function requireAdminWrite(Request $request): User
    {
        $user = $this->requireWrite($request);

        if (! $user->canAccessAdminPortal() && ! $user->hasRole('super_admin')) {
            throw new AuthorizationException('This tool is restricted to administrators.');
        }

        return $user;
    }

    protected function requirePermission(User $user, string $permission, string $message): void
    {
        if (! $user->can($permission)) {
            throw new AuthorizationException($message);
        }
    }

    protected function tokenHasExplicitAbility(User $user, string $ability): bool
    {
        $token = $user->currentAccessToken();
        $abilities = $token?->abilities;

        return is_array($abilities)
            && ! in_array('*', $abilities, true)
            && in_array($ability, $abilities, true);
    }

    private function requireTokenAbility(User $user, string $ability): void
    {
        if (! $this->tokenHasExplicitAbility($user, $ability)) {
            throw new AuthorizationException('The API key does not have '.$ability.' access.');
        }
    }
}
