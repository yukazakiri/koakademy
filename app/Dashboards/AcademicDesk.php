<?php

declare(strict_types=1);

namespace App\Dashboards;

use App\Dashboards\Contracts\Dashboard;
use App\Enums\UserRole;
use App\Models\Classes;
use App\Models\Course;
use App\Models\Department;
use App\Models\Faculty;
use App\Models\Student;
use App\Models\User;

/**
 * Academic delivery desk for department heads and program chairs.
 *
 * Scoped to the viewer's own department when DashboardContext carries one, so a chair only
 * ever aggregates their own faculty and programs. Without a department it falls back to
 * institution-wide totals for sysadmins.
 */
final class AcademicDesk implements Dashboard
{
    public function id(): string
    {
        return 'academic';
    }

    public function title(): string
    {
        return 'Academics';
    }

    public function description(): string
    {
        return 'Teaching load, class delivery and student headcount for your department.';
    }

    /**
     * @param  list<string>  $permissions
     */
    /**
     * Academic leadership only.
     *
     * Gated on the role, not on a permission: ViewAny:Student is held by the cashier, the
     * registrar, student affairs and guidance, so a permission gate here would expose faculty
     * rosters, teaching loads and program counts to all of them. The role list is the same one
     * the desk is documented for; sysadmins are bypassed by DashboardRegistry.
     */
    public function canView(User $user, array $permissions = []): bool
    {
        return in_array($user->role, [
            UserRole::DepartmentHead,
            UserRole::ProgramChair,
            UserRole::Dean,
            UserRole::AssociateDean,
        ], true);
    }

    /**
     * @return array<string, mixed>
     */
    public function data(User $user, DashboardContext $context): array
    {
        $department = $context->department;

        $faculty = $this->facultyQuery($department);
        $courses = $this->courseQuery($department);

        $scopedClasses = $this->classQuery($context, $department);

        $classCount = (clone $scopedClasses)->count();
        $unassigned = (clone $scopedClasses)->whereNull('faculty_id')->count();

        return [
            'scope' => $department === null ? null : [
                'id' => $department->id,
                'name' => $department->name,
                'code' => $department->code,
            ],
            'kpis' => [
                [
                    'label' => 'Faculty',
                    'value' => $faculty->count(),
                    'description' => 'Teaching personnel in scope.',
                    'tone' => 'neutral',
                    'format' => 'number',
                ],
                [
                    'label' => 'Programs',
                    'value' => $courses->count(),
                    'description' => 'Courses offered in scope.',
                    'tone' => 'neutral',
                    'format' => 'number',
                ],
                [
                    'label' => 'Classes This Term',
                    'value' => $classCount,
                    'description' => $context->periodLabel(),
                    'tone' => 'info',
                    'format' => 'number',
                ],
                [
                    'label' => 'Students',
                    'value' => $this->studentCount($courses),
                    'description' => 'Enrolled across scoped programs.',
                    'tone' => 'neutral',
                    'format' => 'number',
                ],
            ],
            'queues' => [
                [
                    'id' => 'unassigned-classes',
                    'title' => 'Classes without faculty',
                    'description' => 'Sections with no instructor assigned.',
                    'count' => $unassigned,
                    'severity' => $unassigned > 0 ? 'warning' : 'success',
                    'href' => '/administrators/classes',
                    'icon' => 'users',
                ],
            ],
            'trends' => [],
            'tables' => [
                [
                    'id' => 'faculty-load',
                    'title' => 'Faculty load',
                    'description' => 'Classes and students assigned per faculty member.',
                    'columns' => [
                        ['key' => 'name', 'label' => 'Faculty'],
                        ['key' => 'classes', 'label' => 'Classes', 'align' => 'end'],
                        ['key' => 'status', 'label' => 'Status'],
                    ],
                    'rows' => $this->facultyLoad($context, $department),
                ],
            ],
        ];
    }

    /**
     * Classes for the term, scoped to the department via the faculty who teach them.
     *
     * `classes` has no department_id, and a class with no instructor belongs to no faculty,
     * so an unassigned class cannot be attributed to a department. Those are therefore only
     * counted institution-wide, which keeps the departmental figure honest instead of
     * silently dropping them.
     *
     * @return \Illuminate\Database\Eloquent\Builder<Classes>
     */
    private function classQuery(DashboardContext $context, ?Department $department)
    {
        return Classes::query()
            ->where('school_year', $context->schoolYear)
            ->where('semester', $context->semester)
            ->when($department instanceof Department, fn ($query) => $query
                ->whereHas('Faculty', fn ($faculty) => $faculty
                    ->where('department_id', $department->id)));
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder<Faculty>
     */
    private function facultyQuery(?Department $department)
    {
        return Faculty::query()
            ->when($department instanceof Department, fn ($query) => $query
                ->where('department_id', $department->id));
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder<Course>
     */
    private function courseQuery(?Department $department)
    {
        return Course::query()
            ->when($department instanceof Department, fn ($query) => $query
                ->where('department_id', $department->id));
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<Course>  $courses
     */
    private function studentCount($courses): int
    {
        $courseIds = (clone $courses)->pluck('id');

        if ($courseIds->isEmpty()) {
            return 0;
        }

        return Student::query()->whereIn('course_id', $courseIds)->count();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function facultyLoad(DashboardContext $context, ?Department $department): array
    {
        return $this->facultyQuery($department)
            ->orderBy('last_name')
            ->limit(10)
            ->get(['id', 'first_name', 'last_name', 'status'])
            ->map(function (Faculty $faculty) use ($context): array {
                $classCount = Classes::query()
                    ->where('faculty_id', $faculty->id)
                    ->where('school_year', $context->schoolYear)
                    ->where('semester', $context->semester)
                    ->count();

                return [
                    'id' => $faculty->id,
                    'name' => mb_trim($faculty->first_name.' '.$faculty->last_name) ?: 'Unnamed faculty',
                    'classes' => $classCount,
                    'status' => (string) ($faculty->status ?? 'active'),
                ];
            })
            ->values()
            ->all();
    }
}
