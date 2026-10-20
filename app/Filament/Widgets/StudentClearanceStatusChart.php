<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Models\Student;
use Filament\Widgets\ChartWidget;
use Override;

final class StudentClearanceStatusChart extends ChartWidget
{
    #[Override]
    protected static ?int $sort = 5;

    #[Override]
    protected ?string $heading = 'Student Clearance Status';

    #[Override]
    protected ?string $description = 'Overview of student clearance completion status';

    #[Override]
    protected string $color = 'warning';

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
        // One statement resolves all three counters instead of one COUNT per
        // clearance state.
        $row = Student::query()
            ->leftJoin('student_clearances', 'student_clearances.student_id', '=', 'students.id')
            ->whereNull('students.deleted_at')
            ->selectRaw('count(distinct case when student_clearances.is_cleared = 1 then students.id end) as cleared')
            ->selectRaw('count(distinct case when student_clearances.is_cleared = 0 then students.id end) as not_cleared')
            ->selectRaw('count(distinct case when student_clearances.id is null then students.id end) as no_clearance')
            ->first();

        $attributes = $row?->getAttributes() ?? [];

        $clearedStudents = is_numeric($attributes['cleared'] ?? null) ? (int) $attributes['cleared'] : 0;
        $notClearedStudents = is_numeric($attributes['not_cleared'] ?? null) ? (int) $attributes['not_cleared'] : 0;
        $noClearanceStudents = is_numeric($attributes['no_clearance'] ?? null) ? (int) $attributes['no_clearance'] : 0;

        return [
            'datasets' => [
                [
                    'label' => 'Students by Clearance Status',
                    'data' => [$clearedStudents, $notClearedStudents, $noClearanceStudents],
                    'backgroundColor' => [
                        'rgba(34, 197, 94, 0.8)',    // Green for cleared
                        'rgba(239, 68, 68, 0.8)',    // Red for not cleared
                        'rgba(156, 163, 175, 0.8)',  // Gray for no clearance
                    ],
                    'borderColor' => [
                        'rgba(34, 197, 94, 1)',
                        'rgba(239, 68, 68, 1)',
                        'rgba(156, 163, 175, 1)',
                    ],
                    'borderWidth' => 2,
                ],
            ],
            'labels' => ['Cleared', 'Not Cleared', 'No Clearance Record'],
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
                'tooltip' => [
                    'callbacks' => [
                        'label' => 'function(context) { return context.label + ": " + context.parsed + " students (" + Math.round(context.parsed / context.dataset.data.reduce((a, b) => a + b, 0) * 100) + "%)"; }',
                    ],
                ],
            ],
            'responsive' => true,
            'maintainAspectRatio' => false,
        ];
    }
}
