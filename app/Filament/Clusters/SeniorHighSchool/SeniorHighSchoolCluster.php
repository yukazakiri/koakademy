<?php

declare(strict_types=1);

namespace App\Filament\Clusters\SeniorHighSchool;

use BackedEnum;
use Filament\Clusters\Cluster;
use Filament\Support\Icons\Heroicon;
use Override;
use UnitEnum;

final class SeniorHighSchoolCluster extends Cluster
{
    #[Override]
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSquares2x2;

    #[Override]
    protected static string|UnitEnum|null $navigationGroup = 'Academics';

    #[Override]
    protected static ?int $navigationSort = 5;
}
