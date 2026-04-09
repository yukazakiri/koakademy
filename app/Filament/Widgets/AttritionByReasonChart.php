<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Enums\AttritionCategory;
use App\Enums\StudentStatus;
use App\Models\Student;
use Filament\Widgets\ChartWidget;

final class AttritionByReasonChart extends ChartWidget
{
    protected static ?int $sort = 30;

    protected ?string $heading = 'Attrition by Category';

    protected ?string $description = 'Breakdown of student withdrawals and dropouts by reason';

    protected string $color = 'danger';

    protected ?string $pollingInterval = '60s';

    protected int|string|array $columnSpan = [
        'md' => 2,
        'xl' => 1,
    ];

    protected function getData(): array
    {
        $categories = AttritionCategory::cases();
        $data = [];
        $labels = [];
        $colors = [];

        foreach ($categories as $category) {
            $count = Student::whereIn('status', [
                StudentStatus::Withdrawn->value,
                StudentStatus::Dropped->value,
            ])
                ->where('attrition_category', $category->value)
                ->count();

            if ($count > 0) {
                $data[] = $count;
                $labels[] = $category->getLabel();
                $colors[] = $this->getCategoryColor($category);
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

    private function getCategoryColor(AttritionCategory $category): string
    {
        return match ($category) {
            AttritionCategory::Academic => 'rgba(239, 68, 68, 0.8)',      // Red
            AttritionCategory::Financial => 'rgba(251, 191, 36, 0.8)',    // Yellow
            AttritionCategory::Personal => 'rgba(59, 130, 246, 0.8)',     // Blue
            AttritionCategory::Transfer => 'rgba(34, 197, 94, 0.8)',      // Green
            AttritionCategory::Relocation => 'rgba(168, 85, 247, 0.8)',   // Purple
            AttritionCategory::Other => 'rgba(156, 163, 175, 0.8)',       // Gray
        };
    }
}
