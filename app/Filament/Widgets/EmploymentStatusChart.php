<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Enums\EmploymentStatus;
use App\Enums\StudentStatus;
use App\Models\Student;
use Filament\Widgets\ChartWidget;

final class EmploymentStatusChart extends ChartWidget
{
    protected static ?int $sort = 33;

    protected ?string $heading = 'Graduate Employment Status';

    protected ?string $description = 'Employment outcomes for graduated students';

    protected string $color = 'success';

    protected ?string $pollingInterval = '60s';

    protected int|string|array $columnSpan = [
        'md' => 2,
        'xl' => 1,
    ];

    protected function getData(): array
    {
        $statuses = [
            EmploymentStatus::Employed,
            EmploymentStatus::SelfEmployed,
            EmploymentStatus::Unemployed,
            EmploymentStatus::Underemployed,
            EmploymentStatus::FurtherStudy,
        ];

        $data = [];
        $labels = [];
        $colors = [];

        foreach ($statuses as $status) {
            $count = Student::where('status', StudentStatus::Graduated->value)
                ->where('employment_status', $status->value)
                ->count();

            if ($count > 0) {
                $data[] = $count;
                $labels[] = $status->getLabel();
                $colors[] = $this->getStatusColor($status);
            }
        }

        return [
            'datasets' => [
                [
                    'label' => 'Graduates',
                    'data' => $data,
                    'backgroundColor' => $colors,
                    'borderWidth' => 0,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'pie';
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => [
                'legend' => [
                    'display' => true,
                    'position' => 'bottom',
                ],
            ],
            'responsive' => true,
            'maintainAspectRatio' => true,
        ];
    }

    private function getStatusColor(EmploymentStatus $status): string
    {
        return match ($status) {
            EmploymentStatus::Employed => 'rgba(34, 197, 94, 0.8)',         // Green
            EmploymentStatus::SelfEmployed => 'rgba(59, 130, 246, 0.8)',    // Blue
            EmploymentStatus::Unemployed => 'rgba(239, 68, 68, 0.8)',       // Red
            EmploymentStatus::Underemployed => 'rgba(251, 191, 36, 0.8)',   // Yellow
            EmploymentStatus::FurtherStudy => 'rgba(168, 85, 247, 0.8)',    // Purple
            default => 'rgba(156, 163, 175, 0.8)',                          // Gray
        };
    }
}
