<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Enums\ScholarshipType;
use App\Models\Student;
use App\Services\DashboardStatisticsService;
use Filament\Widgets\ChartWidget;
use Override;

final class ScholarshipDistributionChart extends ChartWidget
{
    #[Override]
    protected static ?int $sort = 34;

    #[Override]
    protected ?string $heading = 'Scholarship Type Distribution';

    #[Override]
    protected ?string $description = 'Breakdown of students by scholarship type';

    #[Override]
    protected string $color = 'warning';

    #[Override]
    protected ?string $pollingInterval = '60s';

    #[Override]
    protected int|string|array $columnSpan = [
        'md' => 2,
        'xl' => 1,
    ];

    protected function getData(): array
    {
        $types = ScholarshipType::cases();

        // One grouped query replaces one COUNT per scholarship type.
        $counts = app(DashboardStatisticsService::class)->countsByColumn(
            Student::class,
            'scholarship_type',
        );

        $data = [];
        $labels = [];
        $colors = [];

        foreach ($types as $type) {
            $count = $counts[$type->value] ?? 0;

            if ($count > 0) {
                $data[] = $count;
                $labels[] = $type->getLabel();
                $colors[] = $this->getScholarshipColor($type);
            }
        }

        return [
            'datasets' => [
                [
                    'label' => 'Students',
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
            ],
            'responsive' => true,
            'maintainAspectRatio' => true,
        ];
    }

    private function getScholarshipColor(ScholarshipType $type): string
    {
        return match ($type) {
            ScholarshipType::None => 'rgba(156, 163, 175, 0.8)',           // Gray
            ScholarshipType::TDP => 'rgba(34, 197, 94, 0.8)',              // Green
            ScholarshipType::TES => 'rgba(59, 130, 246, 0.8)',             // Blue
            ScholarshipType::Institutional => 'rgba(168, 85, 247, 0.8)',   // Purple
            ScholarshipType::Private => 'rgba(251, 191, 36, 0.8)',         // Yellow
            ScholarshipType::Other => 'rgba(236, 72, 153, 0.8)',           // Pink
        };
    }
}
