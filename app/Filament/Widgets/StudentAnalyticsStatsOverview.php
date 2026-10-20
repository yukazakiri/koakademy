<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Enums\StudentType;
use App\Models\Student;
use App\Services\DashboardStatisticsService;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Override;

final class StudentAnalyticsStatsOverview extends BaseWidget
{
    #[Override]
    protected static ?int $sort = 11;

    #[Override]
    protected ?string $heading = 'Student Type Analytics';

    #[Override]
    protected ?string $description = 'Breakdown of students by enrollment type';

    #[Override]
    protected ?string $pollingInterval = '30s';

    /**
     * Memoised per-request daily counts, so the trend charts below share a
     * single grouped query instead of running one per day and per type.
     *
     * @var array<string, array<string, int>>|null
     */
    private ?array $dailyCounts = null;

    protected function getStats(): array
    {
        $statistics = app(DashboardStatisticsService::class);

        // One grouped statement resolves the total and every type count.
        $typeCounts = $statistics->countsByColumn(Student::class, 'student_type');

        $totalStudents = array_sum($typeCounts);
        $collegeStudents = $typeCounts[StudentType::College->value] ?? 0;
        $shsStudents = $typeCounts[StudentType::SeniorHighSchool->value] ?? 0;
        $tesdaStudents = $typeCounts[StudentType::TESDA->value] ?? 0;
        $dhrtStudents = $typeCounts[StudentType::DHRT->value] ?? 0;

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
        $counts = $this->dailyCounts();

        $data = [];

        foreach ($this->trendDayLabels() as $label) {
            $data[] = $counts[$label][$type->value] ?? 0;
        }

        return $data;
    }

    /**
     * Get recent trend data for all students
     */
    private function getAllStudentsTrend(): array
    {
        $counts = $this->dailyCounts();

        $data = [];

        foreach ($this->trendDayLabels() as $label) {
            $data[] = array_sum($counts[$label] ?? []);
        }

        return $data;
    }

    /**
     * The daily counts of the last seven days, grouped by student type.
     *
     * @return array<string, array<string, int>>
     */
    private function dailyCounts(): array
    {
        return $this->dailyCounts ??= app(DashboardStatisticsService::class)->dailyCounts(
            model: Student::class,
            labels: $this->trendDayLabels(),
            column: 'student_type',
        );
    }

    /**
     * The `Y-m-d` labels of the last seven days, oldest first.
     *
     * @return array<int, string>
     */
    private function trendDayLabels(): array
    {
        $labels = [];

        for ($i = 6; $i >= 0; $i--) {
            $labels[] = now()->subDays($i)->format('Y-m-d');
        }

        return $labels;
    }
}
