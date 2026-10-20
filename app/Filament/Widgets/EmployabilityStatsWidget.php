<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Enums\EmploymentStatus;
use App\Enums\StudentStatus;
use App\Models\Student;
use App\Services\DashboardStatisticsService;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Override;

final class EmployabilityStatsWidget extends BaseWidget
{
    #[Override]
    protected static ?int $sort = 23;

    #[Override]
    protected ?string $heading = 'Graduate Employability & Absorption';

    #[Override]
    protected ?string $pollingInterval = '60s';

    protected function getStats(): array
    {
        // One statement resolves every graduate counter.
        $counts = app(DashboardStatisticsService::class)->countsByConditions(
            model: Student::class,
            conditions: [
                'employed' => ['employment_status', 'in', [
                    EmploymentStatus::Employed->value,
                    EmploymentStatus::SelfEmployed->value,
                ]],
                'institutional' => ['employed_by_institution', '=', true],
                'unemployed' => ['employment_status', '=', EmploymentStatus::Unemployed->value],
                'further_study' => ['employment_status', '=', EmploymentStatus::FurtherStudy->value],
            ],
            filters: [
                ['status', '=', StudentStatus::Graduated->value],
            ],
        );

        // Total graduates
        $totalGraduates = $counts['total'];

        // Employment metrics
        $employedGraduates = $counts['employed'];

        // Calculate employability rate
        $employabilityRate = $totalGraduates > 0
            ? round(($employedGraduates / $totalGraduates) * 100, 1)
            : 0;

        // Institutional absorption (graduates employed by the institution)
        $institutionalAbsorption = $counts['institutional'];

        $absorptionRate = $totalGraduates > 0
            ? round(($institutionalAbsorption / $totalGraduates) * 100, 1)
            : 0;

        // Unemployed graduates
        $unemployedGraduates = $counts['unemployed'];

        // Further study
        $furtherStudy = $counts['further_study'];

        return [
            Stat::make('Employability Rate', "{$employabilityRate}%")
                ->description("{$employedGraduates} of {$totalGraduates} graduates employed")
                ->descriptionIcon('heroicon-m-briefcase')
                ->color($employabilityRate >= 70 ? 'success' : ($employabilityRate >= 50 ? 'warning' : 'danger')),

            Stat::make('Institutional Absorption', "{$absorptionRate}%")
                ->description("{$institutionalAbsorption} graduates employed by institution")
                ->descriptionIcon('heroicon-m-building-office')
                ->color('info'),

            Stat::make('Unemployed Graduates', number_format($unemployedGraduates))
                ->description("{$furtherStudy} pursuing further study")
                ->descriptionIcon('heroicon-m-user-minus')
                ->color($unemployedGraduates > 0 ? 'warning' : 'success'),
        ];
    }
}
