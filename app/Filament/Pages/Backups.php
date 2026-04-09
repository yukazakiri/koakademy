<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use BackedEnum;
use ShuvroRoy\FilamentSpatieLaravelBackup\Pages\Backups as BaseBackups;
use UnitEnum;

final class Backups extends BaseBackups
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-cpu-chip';

    protected static string|UnitEnum|null $navigationGroup = 'System Tools';

    protected static ?int $navigationSort = 40;

    protected string $view = 'filament.pages.backups';

    public static function getNavigationGroup(): string
    {
        return 'System Tools';
    }

    public function getHeading(): string
    {
        return 'Application Backups';
    }
}
