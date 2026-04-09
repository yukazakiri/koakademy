<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Enums\StudentStatus;
use App\Models\Student;
use Filament\Widgets\ChartWidget;

final class StudentStatusChart extends ChartWidget
{
    protected static ?int $sort = 8;

    protected ?string $heading = 'Student Status Distribution';

    protected ?string $description = 'Distribution of students by their current status';

    protected string $color = 'success';

    protected ?string $pollingInterval = '60s';

    protected ?string $maxHeight = '400px';

    protected int|string|array $columnSpan = [
        'md' => 2,
        'xl' => 1,
    ];

    protected function getData(): array
    {
        // Get status distribution data using new StudentStatus enum
        $enrolledStudents = Student::where('status', StudentStatus::Enrolled->value)->count();
        $applicantStudents = Student::where('status', StudentStatus::Applicant->value)->count();
        $graduatedStudents = Student::where('status', StudentStatus::Graduated->value)->count();
        $withdrawnStudents = Student::where('status', StudentStatus::Withdrawn->value)->count();
        $droppedStudents = Student::where('status', StudentStatus::Dropped->value)->count();

        return [
            'datasets' => [
                [
                    'label' => 'Students by Status',
                    'data' => [
                        $enrolledStudents,
                        $applicantStudents,
                        $graduatedStudents,
                        $withdrawnStudents,
                        $droppedStudents,
                    ],
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
