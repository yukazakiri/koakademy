<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Enums\StudentStatus;
use App\Models\Student;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

final class StudentStatsOverview extends BaseWidget
{
    protected static ?int $sort = 1;

    protected ?string $heading = 'Student Statistics';

    protected ?string $description = 'Overview of student data in the system';

    protected ?string $pollingInterval = '30s';

    protected function getStats(): array
    {
        $totalStudents = Student::count();
        $activeStudents = Student::where('status', StudentStatus::Enrolled->value)->count();
        $graduateStudents = Student::where('academic_year', 5)->count();
        $recentStudents = Student::where('created_at', '>=', now()->subDays(30))->count();

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
