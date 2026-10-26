<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Models\StudentEnrollment;
use Filament\Widgets\ChartWidget;
use Override;

final class CourseDistributionChart extends ChartWidget
{
    #[Override]
    protected static ?int $sort = 3;

    #[Override]
    protected ?string $heading = 'Course Distribution';

    #[Override]
    protected ?string $description = 'Distribution of students by course program';

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
        // Get course distribution data
        $bsitCount = StudentEnrollment::currentAcademicPeriod()
            ->withTrashed()
            ->whereRaw("EXISTS (SELECT * FROM students WHERE student_enrollment.student_id = students.id AND EXISTS (SELECT * FROM courses WHERE students.course_id = courses.id AND code LIKE 'BSIT%'))")
            ->count();

        $bshmCount = StudentEnrollment::currentAcademicPeriod()
            ->withTrashed()
            ->whereRaw("EXISTS (SELECT * FROM students WHERE student_enrollment.student_id = students.id AND EXISTS (SELECT * FROM courses WHERE students.course_id = courses.id AND code LIKE 'BSHM%'))")
            ->count();

        $bsbaCount = StudentEnrollment::currentAcademicPeriod()
            ->withTrashed()
            ->whereRaw("EXISTS (SELECT * FROM students WHERE student_enrollment.student_id = students.id AND EXISTS (SELECT * FROM courses WHERE students.course_id = courses.id AND code LIKE 'BSBA%'))")
            ->count();

        // Get other courses (not BSIT, BSHM, BSBA)
        $otherCount = StudentEnrollment::currentAcademicPeriod()
            ->withTrashed()
            ->whereRaw("EXISTS (SELECT * FROM students WHERE student_enrollment.student_id = students.id AND EXISTS (SELECT * FROM courses WHERE students.course_id = courses.id AND code NOT LIKE 'BSIT%' AND code NOT LIKE 'BSHM%' AND code NOT LIKE 'BSBA%'))")
            ->count();

        return [
            'datasets' => [
                [
                    'label' => 'Course Distribution',
                    'data' => [$bsitCount, $bshmCount, $bsbaCount, $otherCount],
                    'backgroundColor' => [
                        'rgba(59, 130, 246, 0.8)',   // Blue for BSIT
                        'rgba(34, 197, 94, 0.8)',    // Green for BSHM
                        'rgba(251, 191, 36, 0.8)',   // Yellow for BSBA
                        'rgba(156, 163, 175, 0.8)',  // Gray for Others
                    ],
                    'borderColor' => [
                        'rgba(59, 130, 246, 1)',
                        'rgba(34, 197, 94, 1)',
                        'rgba(251, 191, 36, 1)',
                        'rgba(156, 163, 175, 1)',
                    ],
                    'borderWidth' => 2,
                ],
            ],
            'labels' => ['BSIT', 'BSHM', 'BSBA', 'Others'],
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
