<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Enums\StudentType;
use App\Models\StudentEnrollment;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;

final class StudentEnrollmentByTypeChart extends ChartWidget
{
    public ?string $filter = 'last_6_months';

    protected static ?int $sort = 12;

    protected ?string $heading = 'Student Enrollment Trends by Type';

    protected ?string $description = 'Monthly enrollment trends for each student type';

    protected ?string $pollingInterval = '60s';

    protected ?string $maxHeight = '400px';

    protected int|string|array $columnSpan = 'full';

    protected function getFilters(): array
    {
        return [
            'last_3_months' => 'Last 3 months',
            'last_6_months' => 'Last 6 months',
            'last_12_months' => 'Last 12 months',
            'this_year' => 'This year',
        ];
    }

    protected function getData(): array
    {
        $months = $this->getMonthsBasedOnFilter();

        $collegeData = [];
        $shsData = [];
        $tesdaData = [];
        $dhrtData = [];
        $labels = [];

        foreach ($months as $month) {
            $labels[] = $month['label'];

            $collegeCount = StudentEnrollment::withTrashed()
                ->join('students', function ($join): void {
                    $join->whereRaw('CAST(student_enrollment.student_id AS BIGINT) = students.id');
                })
                ->where('students.student_type', StudentType::College->value)
                ->whereBetween('student_enrollment.created_at', [$month['start'], $month['end']])
                ->count();

            $shsCount = StudentEnrollment::withTrashed()
                ->join('students', function ($join): void {
                    $join->whereRaw('CAST(student_enrollment.student_id AS BIGINT) = students.id');
                })
                ->where('students.student_type', StudentType::SeniorHighSchool->value)
                ->whereBetween('student_enrollment.created_at', [$month['start'], $month['end']])
                ->count();

            $tesdaCount = StudentEnrollment::withTrashed()
                ->join('students', function ($join): void {
                    $join->whereRaw('CAST(student_enrollment.student_id AS BIGINT) = students.id');
                })
                ->where('students.student_type', StudentType::TESDA->value)
                ->whereBetween('student_enrollment.created_at', [$month['start'], $month['end']])
                ->count();

            $dhrtCount = StudentEnrollment::withTrashed()
                ->join('students', function ($join): void {
                    $join->whereRaw('CAST(student_enrollment.student_id AS BIGINT) = students.id');
                })
                ->where('students.student_type', StudentType::DHRT->value)
                ->whereBetween('student_enrollment.created_at', [$month['start'], $month['end']])
                ->count();

            $collegeData[] = $collegeCount;
            $shsData[] = $shsCount;
            $tesdaData[] = $tesdaCount;
            $dhrtData[] = $dhrtCount;
        }

        return [
            'datasets' => [
                [
                    'label' => 'College Students',
                    'data' => $collegeData,
                    'borderColor' => 'rgba(59, 130, 246, 1)',
                    'backgroundColor' => 'rgba(59, 130, 246, 0.1)',
                    'borderWidth' => 2,
                    'fill' => true,
                    'tension' => 0.4,
                ],
                [
                    'label' => 'SHS Students',
                    'data' => $shsData,
                    'borderColor' => 'rgba(34, 197, 94, 1)',
                    'backgroundColor' => 'rgba(34, 197, 94, 0.1)',
                    'borderWidth' => 2,
                    'fill' => true,
                    'tension' => 0.4,
                ],
                [
                    'label' => 'TESDA Students',
                    'data' => $tesdaData,
                    'borderColor' => 'rgba(251, 191, 36, 1)',
                    'backgroundColor' => 'rgba(251, 191, 36, 0.1)',
                    'borderWidth' => 2,
                    'fill' => true,
                    'tension' => 0.4,
                ],
                [
                    'label' => 'DHRT Students',
                    'data' => $dhrtData,
                    'borderColor' => 'rgba(147, 51, 234, 1)',
                    'backgroundColor' => 'rgba(147, 51, 234, 0.1)',
                    'borderWidth' => 2,
                    'fill' => true,
                    'tension' => 0.4,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => [
                'legend' => [
                    'display' => true,
                    'position' => 'top',
                ],
                'tooltip' => [
                    'mode' => 'index',
                    'intersect' => false,
                ],
            ],
            'scales' => [
                'y' => [
                    'beginAtZero' => true,
                    'ticks' => [
                        'precision' => 0,
                    ],
                ],
                'x' => [
                    'grid' => [
                        'display' => false,
                    ],
                ],
            ],
            'responsive' => true,
            'maintainAspectRatio' => false,
            'interaction' => [
                'mode' => 'index',
                'intersect' => false,
            ],
        ];
    }

    private function getMonthsBasedOnFilter(): array
    {
        $months = [];

        switch ($this->filter) {
            case 'last_3_months':
                $startDate = now()->subMonths(3)->startOfMonth();
                $endDate = now()->endOfMonth();
                break;
            case 'last_6_months':
                $startDate = now()->subMonths(6)->startOfMonth();
                $endDate = now()->endOfMonth();
                break;
            case 'last_12_months':
                $startDate = now()->subMonths(12)->startOfMonth();
                $endDate = now()->endOfMonth();
                break;
            case 'this_year':
                $startDate = now()->startOfYear();
                $endDate = now()->endOfYear();
                break;
            default:
                $startDate = now()->subMonths(6)->startOfMonth();
                $endDate = now()->endOfMonth();
        }

        $current = Carbon::parse($startDate);
        while ($current <= $endDate) {
            $months[] = [
                'label' => $current->format('M Y'),
                'start' => $current->copy()->startOfMonth(),
                'end' => $current->copy()->endOfMonth(),
            ];
            $current->addMonth();
        }

        return $months;
    }
}
