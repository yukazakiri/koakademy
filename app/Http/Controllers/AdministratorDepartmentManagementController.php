<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\Administrators\DepartmentRequest;
use App\Models\Department;
use App\Models\School;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class AdministratorDepartmentManagementController extends Controller
{
    public function index(Request $request): Response
    {
        $search = $request->input('search');
        $status = $request->input('status');

        $departments = Department::query()
            ->with('school')
            ->withCount(['users', 'courses'])
            ->when(is_string($search) && mb_trim($search) !== '', function ($query) use ($search): void {
                $term = mb_trim($search);
                $query->where(function ($nested) use ($term): void {
                    $nested->where('name', 'ilike', "%{$term}%")
                        ->orWhere('code', 'ilike', "%{$term}%")
                        ->orWhere('head_name', 'ilike', "%{$term}%")
                        ->orWhere('head_email', 'ilike', "%{$term}%");
                });
            })
            ->when(is_string($status) && $status !== '' && $status !== 'all', function ($query) use ($status): void {
                $query->where('is_active', $status === 'active');
            })
            ->orderBy('name')
            ->get()
            ->map(fn (Department $department): array => [
                'id' => $department->id,
                'school_id' => $department->school_id,
                'name' => $department->name,
                'code' => $department->code,
                'description' => $department->description,
                'head_name' => $department->head_name,
                'head_email' => $department->head_email,
                'location' => $department->location,
                'phone' => $department->phone,
                'email' => $department->email,
                'is_active' => $department->is_active,
                'school' => $department->school ? [
                    'id' => $department->school->id,
                    'name' => $department->school->name,
                ] : null,
                'users_count' => $department->users_count,
                'courses_count' => $department->courses_count,
                'created_at' => format_timestamp($department->created_at),
            ]);

        $stats = [
            'total' => Department::query()->count(),
            'active' => Department::query()->where('is_active', true)->count(),
            'inactive' => Department::query()->where('is_active', false)->count(),
        ];

        $schools = School::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get()
            ->map(fn (School $school): array => [
                'id' => $school->id,
                'name' => $school->name,
            ]);

        return Inertia::render('administrators/departments/index', [
            'user' => $this->getUserProps(),
            'departments' => $departments,
            'stats' => $stats,
            'schools' => $schools,
            'filters' => [
                'search' => is_string($search) ? $search : null,
                'status' => is_string($status) ? $status : null,
            ],
            'flash' => session('flash'),
        ]);
    }

    public function create(): Response
    {
        $schools = School::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get()
            ->map(fn (School $school): array => [
                'id' => $school->id,
                'name' => $school->name,
            ]);

        return Inertia::render('administrators/departments/edit', [
            'user' => $this->getUserProps(),
            'department' => null,
            'schools' => $schools,
        ]);
    }

    public function store(DepartmentRequest $request): RedirectResponse
    {
        $department = Department::create($request->validated());

        return redirect()
            ->route('administrators.departments.index')
            ->with('flash', [
                'type' => 'success',
                'message' => "Department {$department->name} created successfully.",
            ]);
    }

    public function edit(Department $department): Response
    {
        $schools = School::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get()
            ->map(fn (School $school): array => [
                'id' => $school->id,
                'name' => $school->name,
            ]);

        return Inertia::render('administrators/departments/edit', [
            'user' => $this->getUserProps(),
            'department' => [
                'id' => $department->id,
                'school_id' => $department->school_id,
                'name' => $department->name,
                'code' => $department->code,
                'description' => $department->description,
                'head_name' => $department->head_name,
                'head_email' => $department->head_email,
                'location' => $department->location,
                'phone' => $department->phone,
                'email' => $department->email,
                'is_active' => $department->is_active,
            ],
            'schools' => $schools,
        ]);
    }

    public function update(DepartmentRequest $request, Department $department): RedirectResponse
    {
        $department->update($request->validated());

        return redirect()
            ->route('administrators.departments.index')
            ->with('flash', [
                'type' => 'success',
                'message' => 'Department updated successfully.',
            ]);
    }

    public function destroy(Department $department): RedirectResponse
    {
        $department->delete();

        return redirect()
            ->route('administrators.departments.index')
            ->with('flash', [
                'type' => 'success',
                'message' => 'Department deleted successfully.',
            ]);
    }

    private function getUserProps(): array
    {
        $user = request()->user();

        if (! $user) {
            return [];
        }

        return [
            'name' => $user->name,
            'email' => $user->email,
            'avatar' => $user->avatar_url ?? null,
            'role' => $user->role?->getLabel() ?? 'Administrator',
        ];
    }
}
