<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Enums\ScholarshipType;
use App\Models\Student;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

final class ScholarshipStatsWidget extends BaseWidget
{
    protected static ?int $sort = 24;

    protected ?string $heading = 'Scholarship Distribution';

    protected ?string $pollingInterval = '60s';

    protected function getStats(): array
    {
        $totalStudents = Student::count();

        // CHED scholarships (TDP + TES)
        $chedScholars = Student::whereIn('scholarship_type', [
            ScholarshipType::TDP->value,
            ScholarshipType::TES->value,
        ])->count();

        $chedPercentage = $totalStudents > 0
            ? round(($chedScholars / $totalStudents) * 100, 1)
            : 0;

        // TDP scholars
        $tdpScholars = Student::where('scholarship_type', ScholarshipType::TDP->value)->count();

        // TES scholars
        $tesScholars = Student::where('scholarship_type', ScholarshipType::TES->value)->count();

        // Other scholarships
        $institutionalScholars = Student::where('scholarship_type', ScholarshipType::Institutional->value)->count();
        $privateScholars = Student::where('scholarship_type', ScholarshipType::Private->value)->count();
        $otherScholars = Student::where('scholarship_type', ScholarshipType::Other->value)->count();

        $totalScholars = $chedScholars + $institutionalScholars + $privateScholars + $otherScholars;
        $scholarshipCoverage = $totalStudents > 0
            ? round(($totalScholars / $totalStudents) * 100, 1)
            : 0;

        return [
            Stat::make('CHED Scholars', number_format($chedScholars))
                ->description("{$chedPercentage}% of students (TDP: {$tdpScholars}, TES: {$tesScholars})")
                ->descriptionIcon('heroicon-m-academic-cap')
                ->color('success'),

            Stat::make('Total Scholarship Coverage', "{$scholarshipCoverage}%")
                ->description("{$totalScholars} of {$totalStudents} students")
                ->descriptionIcon('heroicon-m-currency-dollar')
                ->color('info'),

            Stat::make('Other Scholarships', number_format($institutionalScholars + $privateScholars + $otherScholars))
                ->description("Institutional: {$institutionalScholars}, Private: {$privateScholars}, Other: {$otherScholars}")
                ->descriptionIcon('heroicon-m-gift')
                ->color('warning'),
        ];
    }
}
