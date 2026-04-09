<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Models\Course;
use Filament\Widgets\ChartWidget;

final class StudentCourseDistributionChart extends ChartWidget
{
    protected static ?int $sort = 2;

    protected ?string $heading = 'Student Course Distribution';

    protected ?string $description = 'Distribution of students across different courses';

    protected string $color = 'success';

    protected ?string $pollingInterval = '60s';

    protected ?string $maxHeight = '400px';

    protected int|string|array $columnSpan = [
        'md' => 2,
        'xl' => 1,
    ];

    protected function getData(): array
    {
        // Get course distribution data
        $courses = Course::getCoursesWithStudentCount();

        $courseLabels = $courses->pluck('code')->toArray();
        $studentCounts = $courses->pluck('student_count')->toArray();

        // Get colors for each course
        $colors = $this->getCourseColors($courseLabels);

        return [
            'datasets' => [
                [
                    'label' => 'Students by Course',
                    'data' => $studentCounts,
                    'backgroundColor' => $colors['background'],
                    'borderColor' => $colors['border'],
                    'borderWidth' => 2,
                ],
            ],
            'labels' => $courseLabels,
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

    private function getCourseColors(array $courseCodes): array
    {
        $backgroundColors = [];
        $borderColors = [];

        $colorMap = [
            'BSIT' => ['background' => 'rgba(59, 130, 246, 0.8)', 'border' => 'rgba(59, 130, 246, 1)'], // Blue
            'BSHM' => ['background' => 'rgba(34, 197, 94, 0.8)', 'border' => 'rgba(34, 197, 94, 1)'], // Green
            'BSBA' => ['background' => 'rgba(251, 191, 36, 0.8)', 'border' => 'rgba(251, 191, 36, 1)'], // Yellow
            'default' => ['background' => 'rgba(156, 163, 175, 0.8)', 'border' => 'rgba(156, 163, 175, 1)'], // Gray
        ];

        foreach ($courseCodes as $code) {
            $baseCode = mb_substr((string) $code, 0, 4); // Get first 4 characters to match with color map
            if (isset($colorMap[$baseCode])) {
                $backgroundColors[] = $colorMap[$baseCode]['background'];
                $borderColors[] = $colorMap[$baseCode]['border'];
            } else {
                $backgroundColors[] = $colorMap['default']['background'];
                $borderColors[] = $colorMap['default']['border'];
            }
        }

        return [
            'background' => $backgroundColors,
            'border' => $borderColors,
        ];
    }
}
