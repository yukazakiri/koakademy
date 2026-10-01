<?php

declare(strict_types=1);

namespace App\Dashboards;

use App\Dashboards\Contracts\Dashboard;
use App\Dashboards\Support\StatAggregates;
use App\Enums\UserRole;
use App\Models\Classes;
use App\Models\Course;
use App\Models\Department;
use App\Models\Faculty;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\User;
use App\Services\EnrollmentPipelineService;
use Flowframe\Trend\Trend;

/**
 * Institution-wide view for leadership (President, VP, Dean, Associate Dean).
 *
 * Answers "how is each department doing relative to the others" rather than "what do I have to
 * do today", so it carries a cross-department comparison and no operational queues.
 */
final class ExecutiveDesk implements Dashboard
{
    public function id(): string
    {
        return 'executive';
    }

    public function title(): string
    {
        return 'Executive';
    }

    public function description(): string
    {
        return 'Institution-wide performance and how each department compares.';
    }

    /**
     * Leadership only. Gated on department-wide visibility rather than ViewAny:Student, which
     * every registrar, cashier and adviser also holds and which would leak the
     * cross-department view to the wrong audience.
     *
     * @param  list<string>  $permissions
     */
    public function canView(User $user, array $permissions = []): bool
    {
        if ($permissions === []) {
            return in_array($user->role, [
                UserRole::President,
                UserRole::VicePresident,
                UserRole::Dean,
                UserRole::AssociateDean,
            ], true);
        }

        return $this->granted($permissions, ['ViewAny:Department', 'ViewAny:Role']);
    }

    /**
     * @return array<string, mixed>
     */
    public function data(User $user, DashboardContext $context): array
    {
        return [
            'kpis' => $this->kpis($context),
            'queues' => $this->queues($context),
            'trends' => [
                [
                    'id' => 'enrollment',
                    'title' => 'Enrollment trend',
                    'description' => 'Verified enrollments per month across all departments.',
                    'data' => $this->enrollmentTrend($context),
                    'permission' => 'ViewAny:StudentEnrollment',
                ],
            ],
            'tables' => [
                [
                    'id' => 'department-comparison',
                    'title' => 'Department comparison',
                    'description' => 'Headcount and enrollment load per active department.',
                    'columns' => $this->comparisonColumns(),
                    'rows' => $this->departmentComparison($context),
                    'permissions' => ['ViewAny:Department'],
                ],
            ],
        ];
    }

    /**
     * @return list<array{label: string, value: int|string, description: string, tone: string, format: string}>
     */
    private function kpis(DashboardContext $context): array
    {
        // One shared aggregate pass supplies the totals the desk reports, so this screen and the
        // original portal dashboard read the same figures.
        $studentStats = app(StatAggregates::class)->studentStats();
        $totalStudents = (int) $studentStats->total;
        $enrolled = StudentEnrollment::query()
            ->where('school_year', $context->schoolYear)
            ->where('semester', $context->semester)
            ->whereNotNull('student_id')
            ->distinct('student_id')
            ->count('student_id');

        $faculty = Faculty::query()->count();
        $departments = Department::query()->active()->count();

        return [
            [
                'label' => 'Total Students',
                'value' => $totalStudents,
                'description' => 'All enrolled student profiles.',
                'tone' => 'neutral',
                'format' => 'number',
            ],
            [
                'label' => 'Enrolled This Term',
                'value' => $enrolled,
                'description' => $context->periodLabel(),
                'tone' => 'info',
                'format' => 'number',
            ],
            [
                'label' => 'Teaching Faculty',
                'value' => $faculty,
                'description' => 'Active faculty records.',
                'tone' => 'neutral',
                'format' => 'number',
            ],
            [
                'label' => 'Active Departments',
                'value' => $departments,
                'description' => 'Departments currently accepting work.',
                'tone' => 'neutral',
                'format' => 'number',
            ],
        ];
    }

    /**
     * Short, cross-department attention list. Deliberately not this desk's own action queue:
     * leadership watches for anomalies, they do not process individual records.
     *
     * @return list<array{id: string, title: string, description: string, count: int, severity: string, href: string, icon: string}>
     */
    private function queues(DashboardContext $context): array
    {
        $pending = StudentEnrollment::query()
            ->where('school_year', $context->schoolYear)
            ->where('semester', $context->semester)
            ->where('status', app(EnrollmentPipelineService::class)->getPendingStatus())
            ->count();

        $unassigned = Classes::query()
            ->where('school_year', $context->schoolYear)
            ->where('semester', $context->semester)
            ->whereNull('faculty_id')
            ->count();

        return [
            [
                'id' => 'pending-enrollments',
                'title' => 'Enrollments awaiting review',
                'description' => 'Admission requests not yet actioned this term.',
                'count' => $pending,
                'severity' => $pending > 0 ? 'warning' : 'success',
                'href' => '/administrators/enrollments',
                'icon' => 'clipboard-check',
            ],
            [
                'id' => 'unassigned-classes',
                'title' => 'Classes without faculty',
                'description' => 'Sections running this term with no instructor assigned.',
                'count' => $unassigned,
                'severity' => $unassigned > 0 ? 'warning' : 'success',
                'href' => '/administrators/classes',
                'icon' => 'users',
            ],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function enrollmentTrend(DashboardContext $context): array
    {
        $series = Trend::model(StudentEnrollment::class)
            ->between($context->from, $context->to)
            ->perMonth()
            ->count();

        return $series->map(fn (object $trend): array => [
            'date' => $trend->date,
            'label' => date('M Y', strtotime((string) $trend->date)),
            'enrollments' => $trend->aggregate,
        ])->values()->all();
    }

    /**
     * @return list<array{key: string, label: string, align?: string}>
     */
    private function comparisonColumns(): array
    {
        return [
            ['key' => 'name', 'label' => 'Department'],
            ['key' => 'code', 'label' => 'Code'],
            ['key' => 'faculty', 'label' => 'Faculty', 'align' => 'end'],
            ['key' => 'courses', 'label' => 'Programs', 'align' => 'end'],
            ['key' => 'students', 'label' => 'Students', 'align' => 'end'],
        ];
    }

    /**
     * Per-department headcount in a single grouped pass rather than a query per department.
     *
     * Faculty are joined on the department_id foreign key rather than the legacy free-text
     * `department` string, which needed a code-or-name fallback that silently dropped rows.
     *
     * @return list<array<string, mixed>>
     */
    private function departmentComparison(DashboardContext $context): array
    {
        $departments = Department::query()
            ->active()
            ->orderBy('name')
            ->get(['id', 'name', 'code']);

        if ($departments->isEmpty()) {
            return [];
        }

        $ids = $departments->pluck('id')->all();

        $facultyCounts = Faculty::query()
            ->whereIn('department_id', $ids)
            ->selectRaw('department_id, count(*) as aggregate')
            ->groupBy('department_id')
            ->pluck('aggregate', 'department_id');

        $coursesByDepartment = Course::query()
            ->whereIn('department_id', $ids)
            ->get(['id', 'department_id'])
            ->groupBy('department_id');

        $courseCounts = $coursesByDepartment->map(fn ($courses): int => $courses->count());

        // Students are counted per course, then rolled up per department, so a single grouped
        // query covers every department instead of one query per department.
        $studentCountsByCourse = Student::query()
            ->whereIn('course_id', $coursesByDepartment->flatten()->pluck('id'))
            ->selectRaw('course_id, count(*) as aggregate')
            ->groupBy('course_id')
            ->pluck('aggregate', 'course_id');

        return $departments->map(function (Department $department) use ($facultyCounts, $coursesByDepartment, $courseCounts, $studentCountsByCourse): array {
            $courses = $coursesByDepartment[$department->id] ?? collect();

            $students = $courses->sum(fn ($course): int => (int) ($studentCountsByCourse[$course->id] ?? 0));

            return [
                'id' => $department->id,
                'name' => $department->name,
                'code' => $department->code,
                'faculty' => (int) ($facultyCounts[$department->id] ?? 0),
                'courses' => (int) ($courseCounts[$department->id] ?? 0),
                'students' => (int) $students,
            ];
        })->values()->all();
    }

    /**
     * @param  list<string>  $permissions
     * @param  list<string>  $required
     */
    private function granted(array $permissions, array $required): bool
    {
        return array_intersect($required, $permissions) !== [];
    }
}
