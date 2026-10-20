<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Enums\StudentType;
use App\Models\Student;
use App\Services\DashboardStatisticsService;
use Filament\Widgets\ChartWidget;
use Override;

final class StudentAcademicYearChart extends ChartWidget
{
    #[Override]
    protected static ?int $sort = 13;

    #[Override]
    protected ?string $heading = 'College Students by Academic Year';

    #[Override]
    protected ?string $description = 'Distribution of college students across year levels';

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
        $yearLevels = [
            1 => '1st Year',
            2 => '2nd Year',
            3 => '3rd Year',
            4 => '4th Year',
            5 => 'Graduate',
        ];

        // One grouped query replaces one COUNT per year level.
        $counts = app(DashboardStatisticsService::class)->countsByColumn(
            model: Student::class,
            column: 'academic_year',
            filters: [
                ['student_type', '=', StudentType::College->value],
            ],
        );

        $data = [];
        $labels = [];

        foreach ($yearLevels as $year => $label) {
            $data[] = $counts[(string) $year] ?? 0;
            $labels[] = $label;
        }

        return [
            'datasets' => [
                [
                    'label' => 'Students by Year Level',
                    'data' => $data,
                    'backgroundColor' => [
                        'rgba(239, 68, 68, 0.8)',    // Red for 1st Year
                        'rgba(245, 158, 11, 0.8)',   // Amber for 2nd Year
                        'rgba(34, 197, 94, 0.8)',    // Green for 3rd Year
                        'rgba(59, 130, 246, 0.8)',   // Blue for 4th Year
                        'rgba(147, 51, 234, 0.8)',   // Purple for Graduate
                    ],
                    'borderColor' => [
                        'rgba(239, 68, 68, 1)',      // Red border
                        'rgba(245, 158, 11, 1)',     // Amber border
                        'rgba(34, 197, 94, 1)',      // Green border
                        'rgba(59, 130, 246, 1)',     // Blue border
                        'rgba(147, 51, 234, 1)',     // Purple border
                    ],
                    'borderWidth' => 2,
                ],
            ],
            'labels' => $labels,
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
                'tooltip' => [
                    'callbacks' => [
                        'label' => 'function(context) {
                            const total = context.dataset.data.reduce((a, b) => a + b, 0);
                            const percentage = total > 0 ? Math.round(context.parsed.y / total * 100) : 0;
                            return context.label + ": " + context.parsed.y + " students (" + percentage + "%)";
                        }',
                    ],
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
        ];
    }
}
