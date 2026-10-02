<?php

declare(strict_types=1);

namespace App\Dashboards;

use App\Dashboards\Contracts\Dashboard;
use App\Models\Department;
use App\Models\Faculty;
use App\Models\User;

/**
 * Personnel desk for the HR manager.
 *
 * Focused on staffing: headcount per department, faculty status mix and gaps in the org
 * structure. Deliberately exposes no student-level data.
 */
final class HrDesk implements Dashboard
{
    public function id(): string
    {
        return 'hr';
    }

    public function title(): string
    {
        return 'Human Resources';
    }

    public function description(): string
    {
        return 'Headcount, staffing structure and faculty records across departments.';
    }

    /**
     * @param  list<string>  $permissions
     */
    public function canView(User $user, array $permissions = []): bool
    {

        return array_intersect(['ViewAny:User', 'ViewAny:Faculty', 'ViewAny:Department'], $permissions) !== [];
    }

    /**
     * @return array<string, mixed>
     */
    public function data(User $user, DashboardContext $context): array
    {
        $totalFaculty = Faculty::query()->count();
        $totalStaff = User::query()->count();
        $unstaffed = $this->unstaffedCount();

        return [
            'kpis' => [
                [
                    'label' => 'Faculty',
                    'value' => $totalFaculty,
                    'description' => 'Faculty records on file.',
                    'tone' => 'neutral',
                    'format' => 'number',
                ],
                [
                    'label' => 'Staff Accounts',
                    'value' => $totalStaff,
                    'description' => 'Users with portal access.',
                    'tone' => 'neutral',
                    'format' => 'number',
                ],
                [
                    'label' => 'Departments',
                    'value' => Department::query()->count(),
                    'description' => 'Total departments configured.',
                    'tone' => 'neutral',
                    'format' => 'number',
                ],
                [
                    'label' => 'Unstaffed Departments',
                    'value' => $unstaffed,
                    'description' => 'Active departments with no faculty attached.',
                    'tone' => $unstaffed > 0 ? 'warning' : 'success',
                    'format' => 'number',
                ],
            ],
            'queues' => $unstaffed > 0
                ? [[
                    'id' => 'unstaffed-departments',
                    'title' => 'Departments without faculty',
                    'description' => 'Active departments that have no faculty record linked.',
                    'count' => $unstaffed,
                    'severity' => 'warning',
                    'href' => '/administrators/departments',
                    'icon' => 'briefcase',
                ]]
                : [],
            'trends' => [],
            'tables' => [
                [
                    'id' => 'headcount',
                    'title' => 'Headcount by department',
                    'description' => 'Faculty and portal staff per department.',
                    'columns' => [
                        ['key' => 'name', 'label' => 'Department'],
                        ['key' => 'faculty', 'label' => 'Faculty', 'align' => 'end'],
                        ['key' => 'staff', 'label' => 'Staff', 'align' => 'end'],
                        ['key' => 'head', 'label' => 'Department head'],
                    ],
                    'rows' => $this->headcount(),
                ],
            ],
        ];
    }

    private function unstaffedCount(): int
    {
        $departments = Department::query()->active()->get(['id']);

        if ($departments->isEmpty()) {
            return 0;
        }

        $staffed = Faculty::query()
            ->whereIn('department_id', $departments->pluck('id'))
            ->distinct()
            ->count('department_id');

        return $departments->count() - $staffed;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function headcount(): array
    {
        $departments = Department::query()->orderBy('name')->get(['id', 'name', 'code', 'head_name']);

        if ($departments->isEmpty()) {
            return [];
        }

        $facultyCounts = Faculty::query()
            ->whereIn('department_id', $departments->pluck('id'))
            ->selectRaw('department_id, count(*) as aggregate')
            ->groupBy('department_id')
            ->pluck('aggregate', 'department_id');

        $staffCounts = User::query()
            ->whereIn('department_id', $departments->pluck('id'))
            ->selectRaw('department_id, count(*) as aggregate')
            ->groupBy('department_id')
            ->pluck('aggregate', 'department_id');

        return $departments->map(fn (Department $department): array => [
            'id' => $department->id,
            'name' => $department->name,
            'faculty' => (int) ($facultyCounts[$department->id] ?? 0),
            'staff' => (int) ($staffCounts[$department->id] ?? 0),
            'head' => $department->head_name ?: '—',
        ])->values()->all();
    }
}
