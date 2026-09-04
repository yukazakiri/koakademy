<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use BackedEnum;
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
