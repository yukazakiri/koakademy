<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Models\Course;
use App\Models\StudentEnrollment;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

final class EnrollmentByCourseStatsWidget extends ChartWidget
{
    protected static ?int $sort = 3;

    protected ?string $heading = 'Enrollment by Course';

    protected ?string $maxHeight = '400px';

    protected int|string|array $columnSpan = 'full';

    protected function getData(): array
    {
        $activeFilter = $this->filter;

        // Query to count students per course group (BSIT, BSHM, BSBA, Others)
        // If filter is set, use it. Otherwise default to current school year via scope or just latest
        $query = StudentEnrollment::query()
            ->join('courses', DB::raw('CAST(student_enrollment.course_id AS BIGINT)'), '=', 'courses.id')
            ->select(
                DB::raw("
                    CASE 
                        WHEN courses.code LIKE 'BSIT%' THEN 'BSIT'
                        WHEN courses.code LIKE 'BSHM%' THEN 'BSHM'
                        WHEN courses.code LIKE 'BSBA%' THEN 'BSBA'
                        ELSE 'Others'
                    END as course_group
                "),
                DB::raw('count(*) as total')
            );

        // Use current academic period as base scope
        $generalSettings = app(\App\Services\GeneralSettingsService::class);
        $query->where('student_enrollment.school_year', $generalSettings->getCurrentSchoolYearString())
            ->where('student_enrollment.semester', $generalSettings->getCurrentSemester());

        // Apply Year Level filter if selected
        if ($activeFilter) {
            $query->where('student_enrollment.academic_year', $activeFilter);
        }

        $data = $query->groupBy('course_group')
            ->orderByDesc('total')
            ->pluck('total', 'course_group')
            ->toArray();

        return [
            'datasets' => [
                [
                    'label' => 'Enrolled Students',
                    'data' => array_values($data),
                    'backgroundColor' => [
                        'rgba(59, 130, 246, 0.8)',
                        'rgba(34, 197, 94, 0.8)',
                        'rgba(251, 191, 36, 0.8)',
                        'rgba(248, 113, 113, 0.8)',
                        'rgba(167, 139, 250, 0.8)',
                        'rgba(236, 72, 153, 0.8)',
                    ],
                ],
            ],
            'labels' => array_keys($data),
        ];
    }

    protected function getFilters(): array
    {
        return [
            '1' => '1st Year',
            '2' => '2nd Year',
            '3' => '3rd Year',
            '4' => '4th Year',
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
