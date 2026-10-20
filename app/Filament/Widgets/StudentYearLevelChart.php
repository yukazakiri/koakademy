<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Models\Student;
use App\Services\DashboardStatisticsService;
use Filament\Widgets\ChartWidget;
use Override;

final class StudentYearLevelChart extends ChartWidget
{
    #[Override]
    protected static ?int $sort = 3;

    #[Override]
    protected ?string $heading = 'Student Year Level Distribution';

    #[Override]
    protected ?string $description = 'Distribution of students by academic year level';

    #[Override]
    protected string $color = 'info';

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
        // One grouped query replaces one COUNT per year level.
        $counts = app(DashboardStatisticsService::class)->countsByColumn(
            Student::class,
            'academic_year',
        );

        $yearData = [];
        for ($year = 1; $year <= 5; $year++) {
            $yearData[$year] = $counts[(string) $year] ?? 0;
        }

        $yearLabels = [
            1 => '1st Year',
            2 => '2nd Year',
            3 => '3rd Year',
            4 => '4th Year',
            5 => 'Graduates',
        ];

        return [
            'datasets' => [
                [
                    'label' => 'Students by Year Level',
                    'data' => array_values($yearData),
                    'backgroundColor' => [
                        'rgba(59, 130, 246, 0.8)',   // Blue for 1st year
                        'rgba(34, 197, 94, 0.8)',    // Green for 2nd year
                        'rgba(251, 191, 36, 0.8)',   // Yellow for 3rd year
                        'rgba(249, 115, 22, 0.8)',   // Orange for 4th year
                        'rgba(139, 92, 246, 0.8)',   // Purple for graduates
                    ],
                    'borderColor' => [
                        'rgba(59, 130, 246, 1)',
                        'rgba(34, 197, 94, 1)',
                        'rgba(251, 191, 36, 1)',
                        'rgba(249, 115, 22, 1)',
                        'rgba(139, 92, 246, 1)',
                    ],
                    'borderWidth' => 2,
                ],
            ],
            'labels' => array_values($yearLabels),
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => [
                'legend' => [
                    'display' => false,
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
