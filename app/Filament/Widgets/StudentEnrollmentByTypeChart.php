<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Enums\StudentType;
use App\Models\StudentEnrollment;
use App\Services\DashboardStatisticsService;
use Filament\Widgets\ChartWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Carbon;
use Override;

final class StudentEnrollmentByTypeChart extends ChartWidget
{
    #[Override]
    public ?string $filter = 'last_6_months';

    #[Override]
    protected static ?int $sort = 12;

    #[Override]
    protected ?string $heading = 'Student Enrollment Trends by Type';

    #[Override]
    protected ?string $description = 'Monthly enrollment trends for each student type';

    #[Override]
    protected ?string $pollingInterval = '60s';

    #[Override]
    protected ?string $maxHeight = '400px';

    #[Override]
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

        $labels = [];
        $periods = [];

        foreach ($months as $month) {
            $labels[] = $month['label'];
            $periods[] = [
                'start' => $month['start'],
                'end' => $month['end'],
            ];
        }

        // One grouped statement resolves every month and every student type
        // instead of one COUNT per month and type.
        $counts = app(DashboardStatisticsService::class)->countsByPeriods(
            model: StudentEnrollment::class,
            periods: $periods,
            table: 'student_enrollment',
            timestampColumn: 'created_at',
            column: 'students.student_type',
            scope: function (Builder $query): void {
                $query->withoutGlobalScope(SoftDeletingScope::class)
                    ->join('students', 'student_enrollment.student_id', '=', 'students.id');
            },
        );

        $types = [
            StudentType::College,
            StudentType::SeniorHighSchool,
            StudentType::TESDA,
            StudentType::DHRT,
        ];

        $series = [];

        foreach ($types as $type) {
            $series[$type->value] = [];
        }

        foreach (array_values($months) as $index => $month) {
            foreach ($types as $type) {
                $series[$type->value][] = $counts[$index][$type->value] ?? 0;
            }
        }

        $collegeData = $series[StudentType::College->value];
        $shsData = $series[StudentType::SeniorHighSchool->value];
        $tesdaData = $series[StudentType::TESDA->value];
        $dhrtData = $series[StudentType::DHRT->value];

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

    /**
     * The months covered by the chart, oldest first.
     *
     * @return array<int, array{label: string, start: Carbon, end: Carbon}>
     */
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
