<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Enums\ScholarshipType;
use App\Models\Student;
use App\Services\DashboardStatisticsService;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Override;

final class ScholarshipStatsWidget extends BaseWidget
{
    #[Override]
    protected static ?int $sort = 24;

    #[Override]
    protected ?string $heading = 'Scholarship Distribution';

    #[Override]
    protected ?string $pollingInterval = '60s';

    protected function getStats(): array
    {
        // One grouped query resolves every scholarship counter.
        $counts = app(DashboardStatisticsService::class)->countsByColumn(
            Student::class,
            'scholarship_type',
        );

        $totalStudents = array_sum($counts);

        // TDP scholars
        $tdpScholars = $counts[ScholarshipType::TDP->value] ?? 0;

        // TES scholars
        $tesScholars = $counts[ScholarshipType::TES->value] ?? 0;

        // CHED scholarships (TDP + TES)
        $chedScholars = $tdpScholars + $tesScholars;

        $chedPercentage = $totalStudents > 0
            ? round(($chedScholars / $totalStudents) * 100, 1)
            : 0;

        // Other scholarships
        $institutionalScholars = $counts[ScholarshipType::Institutional->value] ?? 0;
        $privateScholars = $counts[ScholarshipType::Private->value] ?? 0;
        $otherScholars = $counts[ScholarshipType::Other->value] ?? 0;

        $scholarshipHolders = $chedScholars + $institutionalScholars + $privateScholars + $otherScholars;
        $scholarshipCoverage = $totalStudents > 0
            ? round(($scholarshipHolders / $totalStudents) * 100, 1)
            : 0;

        return [
            Stat::make('CHED Scholars', number_format($chedScholars))
                ->description("{$chedPercentage}% of students (TDP: {$tdpScholars}, TES: {$tesScholars})")
                ->descriptionIcon('heroicon-m-academic-cap')
                ->color('success'),

            Stat::make('Total Scholarship Coverage', "{$scholarshipCoverage}%")
                ->description("{$scholarshipHolders} of {$totalStudents} students")
                ->descriptionIcon('heroicon-m-currency-dollar')
                ->color('info'),

            Stat::make('Other Scholarships', number_format($institutionalScholars + $privateScholars + $otherScholars))
                ->description("Institutional: {$institutionalScholars}, Private: {$privateScholars}, Other: {$otherScholars}")
                ->descriptionIcon('heroicon-m-gift')
                ->color('warning'),
        ];
    }
}
