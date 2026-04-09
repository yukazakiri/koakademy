<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Enums\EmploymentStatus;
use App\Enums\StudentStatus;
use App\Models\Student;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

final class EmployabilityStatsWidget extends BaseWidget
{
    protected static ?int $sort = 23;

    protected ?string $heading = 'Graduate Employability & Absorption';

    protected ?string $pollingInterval = '60s';

    protected function getStats(): array
    {
        // Total graduates
        $totalGraduates = Student::where('status', StudentStatus::Graduated->value)->count();

        // Employment metrics
        $employedGraduates = Student::where('status', StudentStatus::Graduated->value)
            ->whereIn('employment_status', [
                EmploymentStatus::Employed->value,
                EmploymentStatus::SelfEmployed->value,
            ])
            ->count();

        // Calculate employability rate
        $employabilityRate = $totalGraduates > 0
            ? round(($employedGraduates / $totalGraduates) * 100, 1)
            : 0;

        // Institutional absorption (graduates employed by the institution)
        $institutionalAbsorption = Student::where('status', StudentStatus::Graduated->value)
            ->where('employed_by_institution', true)
            ->count();

        $absorptionRate = $totalGraduates > 0
            ? round(($institutionalAbsorption / $totalGraduates) * 100, 1)
            : 0;

        // Unemployed graduates
        $unemployedGraduates = Student::where('status', StudentStatus::Graduated->value)
            ->where('employment_status', EmploymentStatus::Unemployed->value)
            ->count();

        // Further study
        $furtherStudy = Student::where('status', StudentStatus::Graduated->value)
            ->where('employment_status', EmploymentStatus::FurtherStudy->value)
            ->count();

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
