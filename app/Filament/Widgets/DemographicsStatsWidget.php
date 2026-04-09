<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Models\Student;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

final class DemographicsStatsWidget extends BaseWidget
{
    protected static ?int $sort = 21;

    protected ?string $heading = 'Demographics & Diversity';

    protected ?string $pollingInterval = '60s';

    protected function getStats(): array
    {
        // Indigenous participation
        $totalIndigenous = Student::where('is_indigenous_person', true)->count();
        $totalStudents = Student::count();
        $indigenousPercentage = $totalStudents > 0
            ? round(($totalIndigenous / $totalStudents) * 100, 1)
            : 0;

        // Ethnicity diversity (count of unique ethnicities)
        $uniqueEthnicities = Student::whereNotNull('ethnicity')
            ->distinct('ethnicity')
            ->count('ethnicity');

        // Geographic diversity (count of unique regions)
        $uniqueRegions = Student::whereNotNull('region_of_origin')
            ->distinct('region_of_origin')
            ->count('region_of_origin');

        // Most common region
        $topRegion = Student::whereNotNull('region_of_origin')
            ->selectRaw('region_of_origin, COUNT(*) as count')
            ->groupBy('region_of_origin')
            ->orderByDesc('count')
            ->first();

        return [
            Stat::make('Indigenous Students', number_format($totalIndigenous))
                ->description("{$indigenousPercentage}% of total students")
                ->descriptionIcon('heroicon-m-user-group')
                ->color('purple'),

            Stat::make('Ethnic Diversity', $uniqueEthnicities)
                ->description('Unique ethnic groups represented')
                ->descriptionIcon('heroicon-m-globe-alt')
                ->color('info'),

            Stat::make('Geographic Reach', $uniqueRegions)
                ->description($topRegion ? "Top: {$topRegion->region_of_origin}" : 'No data')
                ->descriptionIcon('heroicon-m-map-pin')
                ->color('success'),
        ];
    }
}
