<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Models\StudentEnrollment;
use Filament\Widgets\ChartWidget;
use Flowframe\Trend\Trend;
use Flowframe\Trend\TrendValue;

final class StudentEnrollmentTrendsChart extends ChartWidget
{
    public ?string $filter = 'thisYear';

    protected static ?int $sort = 6;

    protected ?string $heading = 'Enrollment Trends';

    protected ?string $description = 'Monthly trends for student registrations';

    protected string $color = 'info';

    protected ?string $pollingInterval = '60s';

    protected function getData(): array
    {
        $activeFilter = $this->filter;

        $query = StudentEnrollment::query();

        match ($activeFilter) {
            'thisMonth' => $query->where('created_at', '>=', now()->startOfMonth()),
            'lastMonth' => $query->whereBetween('created_at', [
                now()->subMonth()->startOfMonth(),
                now()->subMonth()->endOfMonth(),
            ]),
            'thisYear' => $query->where('created_at', '>=', now()->startOfYear()),
            'lastYear' => $query->whereBetween('created_at', [
                now()->subYear()->startOfYear(),
                now()->subYear()->endOfYear(),
            ]),
            default => $query->where('created_at', '>=', now()->startOfYear()),
        };

        $start = match ($activeFilter) {
            'thisMonth' => now()->startOfMonth(),
            'lastMonth' => now()->subMonth()->startOfMonth(),
            'thisYear' => now()->startOfYear(),
            'lastYear' => now()->subYear()->startOfYear(),
            default => now()->startOfYear(),
        };

        $end = match ($activeFilter) {
            'thisMonth' => now()->endOfMonth(),
            'lastMonth' => now()->subMonth()->endOfMonth(),
            'thisYear' => now()->endOfYear(),
            'lastYear' => now()->subYear()->endOfYear(),
            default => now()->endOfYear(),
        };

        $data = Trend::query(StudentEnrollment::withTrashed())
            ->between(start: $start, end: $end)
            ->perMonth()
            ->count();

        return [
            'datasets' => [
                [
                    'label' => 'Total Enrollments',
                    'data' => $data->map(fn (TrendValue $value): mixed => $value->aggregate),
                    'borderColor' => 'rgb(59, 130, 246)',
                    'backgroundColor' => 'rgba(59, 130, 246, 0.1)',
                    'fill' => true,
                ],
            ],
            'labels' => $data->map(fn (TrendValue $value): string => $value->date),
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }

    protected function getFilters(): array
    {
        return [
            'thisMonth' => 'This Month',
            'lastMonth' => 'Last Month',
            'thisYear' => 'This Year',
            'lastYear' => 'Last Year',
        ];
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => [
                'legend' => [
                    'display' => true,
                    'position' => 'top',
                ],
            ],
            'scales' => [
                'y' => [
                    'beginAtZero' => true,
                    'ticks' => [
                        'stepSize' => 1,
                    ],
                ],
            ],
            'responsive' => true,
            'maintainAspectRatio' => false,
        ];
    }
}
