<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Enums\StudentType;
use App\Models\Student;
use App\Services\DashboardStatisticsService;
use Filament\Widgets\ChartWidget;
use Override;

final class StudentTypeDistributionChart extends ChartWidget
{
    #[Override]
    protected static ?int $sort = 10;

    #[Override]
    protected ?string $heading = 'Student Type Distribution';

    #[Override]
    protected ?string $description = 'Distribution of students by type (College, SHS, TESDA)';

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
        // One grouped query replaces one COUNT per student type.
        $counts = app(DashboardStatisticsService::class)->countsByColumn(
            Student::class,
            'student_type',
        );

        $types = [
            StudentType::College,
            StudentType::SeniorHighSchool,
            StudentType::TESDA,
            StudentType::DHRT,
        ];

        $data = [];

        foreach ($types as $type) {
            $data[] = $counts[$type->value] ?? 0;
        }

        return [
            'datasets' => [
                [
                    'label' => 'Students by Type',
                    'data' => $data,
                    'backgroundColor' => [
                        'rgba(59, 130, 246, 0.8)',   // Blue for College
                        'rgba(34, 197, 94, 0.8)',    // Green for SHS
                        'rgba(251, 191, 36, 0.8)',   // Orange for TESDA
                        'rgba(147, 51, 234, 0.8)',   // Purple for DHRT
                    ],
                    'borderColor' => [
                        'rgba(59, 130, 246, 1)',     // Blue border
                        'rgba(34, 197, 94, 1)',      // Green border
                        'rgba(251, 191, 36, 1)',     // Orange border
                        'rgba(147, 51, 234, 1)',     // Purple border
                    ],
                    'borderWidth' => 2,
                ],
            ],
            'labels' => ['College', 'Senior High School', 'TESDA', 'DHRT'],
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
                        'label' => 'function(context) { 
                            const total = context.dataset.data.reduce((a, b) => a + b, 0);
                            const percentage = total > 0 ? Math.round(context.parsed / total * 100) : 0;
                            return context.label + ": " + context.parsed + " students (" + percentage + "%)"; 
                        }',
                    ],
                ],
            ],
            'responsive' => true,
            'maintainAspectRatio' => false,
            'cutout' => '50%',
        ];
    }
}
