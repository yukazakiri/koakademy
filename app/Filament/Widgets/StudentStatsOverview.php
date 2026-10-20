<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Enums\StudentStatus;
use App\Models\Student;
use App\Services\DashboardStatisticsService;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Override;

final class StudentStatsOverview extends BaseWidget
{
    #[Override]
    protected static ?int $sort = 1;

    #[Override]
    protected ?string $heading = 'Student Statistics';

    #[Override]
    protected ?string $description = 'Overview of student data in the system';

    #[Override]
    protected ?string $pollingInterval = '30s';

    protected function getStats(): array
    {
        // One statement resolves every counter instead of one COUNT per value.
        $counts = app(DashboardStatisticsService::class)->countsByConditions(
            model: Student::class,
            conditions: [
                'enrolled' => ['status', '=', StudentStatus::Enrolled->value],
                'graduates' => ['academic_year', '=', 5],
                'recent' => ['created_at', '>=', now()->subDays(30)],
            ],
        );

        $totalStudents = $counts['total'];
        $activeStudents = $counts['enrolled'];
        $graduateStudents = $counts['graduates'];
        $recentStudents = $counts['recent'];

        return [
            Stat::make('Total Students', number_format($totalStudents))
                ->description('All students in the system')
                ->descriptionIcon('heroicon-m-academic-cap')
                ->color('primary'),

            Stat::make('Enrolled Students', number_format($activeStudents))
                ->description('Currently enrolled students')
                ->descriptionIcon('heroicon-m-user')
                ->color('success'),

            Stat::make('Graduate Students', number_format($graduateStudents))
                ->description('Students in 5th year')
                ->descriptionIcon('fas-graduation-cap')
                ->color('info'),

            Stat::make('Recent Additions', number_format($recentStudents))
                ->description('Students added in the last 30 days')
                ->descriptionIcon('heroicon-m-clock')
                ->color('warning'),
        ];
    }
}
