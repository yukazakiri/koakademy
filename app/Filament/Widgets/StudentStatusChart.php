<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Enums\StudentStatus;
use App\Models\Student;
use App\Services\DashboardStatisticsService;
use Filament\Widgets\ChartWidget;
use Override;

final class StudentStatusChart extends ChartWidget
{
    #[Override]
    protected static ?int $sort = 8;

    #[Override]
    protected ?string $heading = 'Student Status Distribution';

    #[Override]
    protected ?string $description = 'Distribution of students by their current status';

    #[Override]
    protected string $color = 'success';

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
        // One grouped query replaces one COUNT per status.
        $counts = app(DashboardStatisticsService::class)->countsByColumn(
            Student::class,
            'status',
        );

        $statuses = [
            StudentStatus::Enrolled,
            StudentStatus::Applicant,
            StudentStatus::Graduated,
            StudentStatus::Withdrawn,
            StudentStatus::Dropped,
        ];

        $data = [];

        foreach ($statuses as $status) {
            $data[] = $counts[$status->value] ?? 0;
        }

        return [
            'datasets' => [
                [
                    'label' => 'Students by Status',
                    'data' => $data,
                    'backgroundColor' => [
                        'rgba(34, 197, 94, 0.8)',    // Green for enrolled
                        'rgba(251, 191, 36, 0.8)',   // Yellow for applicant
                        'rgba(59, 130, 246, 0.8)',   // Blue for graduated
                        'rgba(239, 68, 68, 0.8)',    // Red for withdrawn
                        'rgba(156, 163, 175, 0.8)',  // Gray for dropped
                    ],
                    'borderColor' => [
                        'rgba(34, 197, 94, 1)',
                        'rgba(251, 191, 36, 1)',
                        'rgba(59, 130, 246, 1)',
                        'rgba(239, 68, 68, 1)',
                        'rgba(156, 163, 175, 1)',
                    ],
                    'borderWidth' => 2,
                ],
            ],
            'labels' => ['Enrolled', 'Applicant', 'Graduated', 'Withdrawn', 'Dropped'],
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
