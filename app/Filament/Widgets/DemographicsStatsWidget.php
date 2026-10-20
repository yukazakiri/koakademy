<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Models\Student;
use App\Services\DashboardStatisticsService;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Override;

final class DemographicsStatsWidget extends BaseWidget
{
    #[Override]
    protected static ?int $sort = 21;

    #[Override]
    protected ?string $heading = 'Demographics & Diversity';

    #[Override]
    protected ?string $pollingInterval = '60s';

    protected function getStats(): array
    {
        // One statement resolves the indigenous and total counters.
        $counts = app(DashboardStatisticsService::class)->countsByConditions(
            model: Student::class,
            conditions: [
                'indigenous' => ['is_indigenous_person', '=', true],
            ],
        );

        $totalIndigenous = $counts['indigenous'];
        $totalStudents = $counts['total'];

        $indigenousPercentage = $totalStudents > 0
            ? round(($totalIndigenous / $totalStudents) * 100, 1)
            : 0;

        // Ethnicity diversity (count of unique ethnicities)
        $uniqueEthnicities = Student::whereNotNull('ethnicity')
            ->distinct('ethnicity')
            ->count('ethnicity');

        // Geographic diversity and the most common region, resolved with a
        // single grouped query.
        $regions = Student::whereNotNull('region_of_origin')
            ->selectRaw('region_of_origin, COUNT(*) as count')
            ->groupBy('region_of_origin')
            ->orderByDesc('count')
            ->get();

        $uniqueRegions = $regions->count();
        $topRegion = $regions->first();

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
