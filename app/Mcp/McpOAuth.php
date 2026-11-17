<?php

declare(strict_types=1);

namespace App\Mcp;

use App\Models\User;
use App\Services\GeneralSettingsService;

final class McpOAuth
{
    public const string READ = 'mcp:use';

    public const string WRITE = 'mcp:write';

    public static function userMayWrite(User $user): bool
    {
        return ! $user->isStudentRole() && ($user->isFaculty() || $user->canAccessAdminPortal());
    }

    public static function writeGrantable(User $user): bool
    {
        return self::userMayWrite($user)
            && app(GeneralSettingsService::class)->isMcpWriteEnabled()
            && config('api.enabled', true);
    }
}
