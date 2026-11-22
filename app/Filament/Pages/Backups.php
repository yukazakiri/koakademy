<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Enums\UserRole;
use App\Models\User;
use BackedEnum;
use Illuminate\Support\Facades\Auth;
use Override;
use ShuvroRoy\FilamentSpatieLaravelBackup\Pages\Backups as BaseBackups;
use UnitEnum;

final class Backups extends BaseBackups
{
    #[Override]
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-cpu-chip';

    #[Override]
    protected static string|UnitEnum|null $navigationGroup = 'System Tools';

    #[Override]
    protected static ?int $navigationSort = 40;

    #[Override]
    protected string $view = 'filament.pages.backups';

    public static function canAccess(): bool
    {
        $user = Auth::user();

        if (! $user instanceof User) {
            return false;
        }

        if (in_array($user->role, [UserRole::SuperAdmin, UserRole::Developer], true)) {
            return true;
        }

        $superAdminRole = (string) config('filament-shield.super_admin.name', UserRole::SuperAdmin->value);

        return $user->hasAnyRole([$superAdminRole, UserRole::Developer->value]);
    }

    public static function shouldRegisterNavigation(): bool
    {
        return self::canAccess();
    }

    public static function getNavigationGroup(): string
    {
        return 'System Tools';
    }

    public static function getCluster(): ?string
    {
        return null;
    }

    public function getHeading(): string
    {
        return 'Application Backups';
    }
}
