<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Models\Student;
use App\Services\DashboardStatisticsService;
use Filament\Widgets\ChartWidget;
use Override;

final class StudentGenderDistributionChart extends ChartWidget
{
    #[Override]
    protected static ?int $sort = 7;

    #[Override]
    protected ?string $heading = 'Student Gender Distribution';

    #[Override]
    protected ?string $description = 'Distribution of students by gender';

    #[Override]
    protected string $color = 'primary';

    #[Override]
    protected ?string $pollingInterval = '60s';

    #[Override]
    protected ?string $maxHeight = '400px';

    #[Override]
    protected int|string|array $columnSpan = [
        'md' => 2,
        'xl' => 1,
    ];

    protected function getData(): array
    {
        // One grouped query replaces one COUNT per gender.
        $counts = app(DashboardStatisticsService::class)->countsByColumn(
            Student::class,
            'gender',
        );

        $maleCount = $counts['male'] ?? 0;
        $femaleCount = $counts['female'] ?? 0;
        $otherCount = array_sum($counts) - $maleCount - $femaleCount;

        return [
            'datasets' => [
                [
                    'label' => 'Students by Gender',
                    'data' => [$maleCount, $femaleCount, $otherCount],
                    'backgroundColor' => [
                        'rgba(59, 130, 246, 0.8)',   // Blue for male
                        'rgba(244, 114, 182, 0.8)', // Pink for female
                        'rgba(156, 163, 175, 0.8)',  // Gray for other
                    ],
                    'borderColor' => [
                        'rgba(59, 130, 246, 1)',
                        'rgba(244, 114, 182, 1)',
                        'rgba(156, 163, 175, 1)',
                    ],
                    'borderWidth' => 2,
                ],
            ],
            'labels' => ['Male', 'Female', 'Other'],
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => [
                'legend' => [
                    'display' => true,
                    'position' => 'bottom',
                ],
                'tooltip' => [
                    'callbacks' => [
                        'label' => 'function(context) { return context.label + ": " + context.parsed + " students (" + Math.round(context.parsed / context.dataset.data.reduce((a, b) => a + b, 0) * 100) + "%)"; }',
                    ],
                ],
            ],
            'responsive' => true,
            'maintainAspectRatio' => false,
            'cutout' => '50%',
        ];
    }
}
