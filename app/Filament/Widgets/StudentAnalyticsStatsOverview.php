<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Enums\StudentType;
use App\Models\Student;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

final class StudentAnalyticsStatsOverview extends BaseWidget
{
    protected static ?int $sort = 11;

    protected ?string $heading = 'Student Type Analytics';

    protected ?string $description = 'Breakdown of students by enrollment type';

    protected ?string $pollingInterval = '30s';

    protected function getStats(): array
    {
        // Total counts by type
        $totalStudents = Student::count();
        $collegeStudents = Student::where('student_type', StudentType::College->value)->count();
        $shsStudents = Student::where('student_type', StudentType::SeniorHighSchool->value)->count();
        $tesdaStudents = Student::where('student_type', StudentType::TESDA->value)->count();
        $dhrtStudents = Student::where('student_type', StudentType::DHRT->value)->count();

        // Recent additions (last 30 days)
        Student::where('student_type', StudentType::College->value)
            ->where('created_at', '>=', now()->subDays(30))
            ->count();

        Student::where('student_type', StudentType::SeniorHighSchool->value)
            ->where('created_at', '>=', now()->subDays(30))
            ->count();

        Student::where('student_type', StudentType::TESDA->value)
            ->where('created_at', '>=', now()->subDays(30))
            ->count();

        Student::where('student_type', StudentType::DHRT->value)
            ->where('created_at', '>=', now()->subDays(30))
            ->count();

        // Calculate percentages
        $collegePercentage = $totalStudents > 0 ? round(($collegeStudents / $totalStudents) * 100, 1) : 0;
        $shsPercentage = $totalStudents > 0 ? round(($shsStudents / $totalStudents) * 100, 1) : 0;
        $tesdaPercentage = $totalStudents > 0 ? round(($tesdaStudents / $totalStudents) * 100, 1) : 0;
        $dhrtPercentage = $totalStudents > 0 ? round(($dhrtStudents / $totalStudents) * 100, 1) : 0;

        return [
            Stat::make('College Students', number_format($collegeStudents))
                ->description("{$collegePercentage}% of all students")
                ->descriptionIcon('heroicon-m-academic-cap')
                ->color('primary')
                ->chart($this->getRecentTrend(StudentType::College))
                ->extraAttributes([
                    'class' => 'college-stat',
                ]),

            Stat::make('Senior High School', number_format($shsStudents))
                ->description("{$shsPercentage}% of all students")
                ->descriptionIcon('heroicon-m-building-library')
                ->color('success')
                ->chart($this->getRecentTrend(StudentType::SeniorHighSchool))
                ->extraAttributes([
                    'class' => 'shs-stat',
                ]),

            Stat::make('TESDA Students', number_format($tesdaStudents))
                ->description("{$tesdaPercentage}% of all students")
                ->descriptionIcon('heroicon-m-wrench-screwdriver')
                ->color('warning')
                ->chart($this->getRecentTrend(StudentType::TESDA))
                ->extraAttributes([
                    'class' => 'tesda-stat',
                ]),

            Stat::make('DHRT Students', number_format($dhrtStudents))
                ->description("{$dhrtPercentage}% of all students")
                ->descriptionIcon('heroicon-m-cog-6-tooth')
                ->color('purple')
                ->chart($this->getRecentTrend(StudentType::DHRT))
                ->extraAttributes([
                    'class' => 'dhrt-stat',
                ]),

            Stat::make('Total Students', number_format($totalStudents))
                ->description('All student types combined')
                ->descriptionIcon('heroicon-m-users')
                ->color('info')
                ->chart($this->getAllStudentsTrend())
                ->extraAttributes([
                    'class' => 'total-stat',
                ]),
        ];
    }

    /**
     * Get recent trend data for a specific student type
     */
    private function getRecentTrend(StudentType $type): array
    {
        $data = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = now()->subDays($i);
            $count = Student::where('student_type', $type->value)
                ->whereDate('created_at', $date)
                ->count();
            $data[] = $count;
        }

        return $data;
    }

    /**
     * Get recent trend data for all students
     */
    private function getAllStudentsTrend(): array
    {
        $data = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = now()->subDays($i);
            $count = Student::whereDate('created_at', $date)->count();
            $data[] = $count;
        }

        return $data;
    }
}
