<?php

declare(strict_types=1);

namespace App\Filament\Clusters\Settings;

use BackedEnum;
use Filament\Clusters\Cluster;
use Filament\Support\Icons\Heroicon;
use Override;
use UnitEnum;

final class SettingsCluster extends Cluster
{
    #[Override]
    protected static string|BackedEnum|null $navigationIcon = Heroicon::Cog8Tooth;

    #[Override]
    protected static string|BackedEnum|null $activeNavigationIcon = Heroicon::OutlinedCog8Tooth;

    #[Override]
    protected static string|UnitEnum|null $navigationGroup = 'Administration';

    #[Override]
    protected static ?int $navigationSort = 3;
}
