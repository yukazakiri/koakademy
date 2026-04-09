<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\Administrators\StoreFacultyRequest;
use App\Http\Requests\Administrators\UpdateFacultyRequest;
use App\Models\Classes;
use App\Models\Faculty;
use App\Services\ClassAssignmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

final class AdministratorFacultyManagementController extends Controller
{
    public function index(Request $request): Response
    {
        $search = $request->input('search');
        $department = $request->input('department');
        $status = $request->input('status');
        $currentClasses = $request->input('current_classes');

        $facultiesQuery = Faculty::query()
            ->withCount([
                'classes',
                'classes as current_classes_count' => fn ($query) => $query->currentAcademicPeriod(),
            ])
            ->when(is_string($search) && mb_trim($search) !== '', function ($builder) use ($search): void {
                $query = mb_trim($search);

                $builder->where(function ($nested) use ($query): void {
                    $nested->whereRaw('CAST(faculty_id_number AS TEXT) ILIKE ?', ["%{$query}%"])
                        ->orWhere('first_name', 'ilike', "%{$query}%")
                        ->orWhere('last_name', 'ilike', "%{$query}%")
                        ->orWhere('email', 'ilike', "%{$query}%")
                        ->orWhereRaw("CONCAT(first_name, ' ', last_name) ILIKE ?", ["%{$query}%"])
                        ->orWhereRaw("CONCAT(last_name, ', ', first_name) ILIKE ?", ["%{$query}%"]);
                });
            })
            ->when(is_string($department) && $department !== '' && $department !== 'all', function ($builder) use ($department): void {
                $builder->where('department', $department);
            })
            ->when(is_string($status) && $status !== '' && $status !== 'all', function ($builder) use ($status): void {
                $builder->where('status', $status);
            })
            ->when(is_string($currentClasses) && $currentClasses !== '' && $currentClasses !== 'all', function ($builder) use ($currentClasses): void {
                if ($currentClasses === 'has') {
                    $builder->whereHas('classes', fn ($query) => $query->currentAcademicPeriod());
                }

                if ($currentClasses === 'none') {
                    $builder->whereDoesntHave('classes', fn ($query) => $query->currentAcademicPeriod());
                }
            })
            ->orderBy('last_name')
            ->orderBy('first_name');

        /** @var LengthAwarePaginator $faculties */
        $faculties = $facultiesQuery
            ->paginate(20)
            ->withQueryString();

        $faculties->through(fn (Faculty $faculty): array => [
            'id' => $faculty->id,
            'faculty_id_number' => $faculty->faculty_id_number,
            'name' => $faculty->full_name,
            'first_name' => $faculty->first_name,
            'last_name' => $faculty->last_name,
            'email' => $faculty->email,
            'department' => $faculty->department,
            'status' => $faculty->status,
            'avatar_url' => $faculty->photo_url ?: $faculty->getFilamentAvatarUrl(),
            'classes_count' => $faculty->classes_count,
            'current_classes_count' => $faculty->current_classes_count,
            'created_at' => format_timestamp($faculty->created_at),
            'filament' => [
                'view_url' => route('filament.admin.resources.faculties.view', $faculty),
                'edit_url' => route('filament.admin.resources.faculties.edit', $faculty),
            ],
        ]);

        $departments = Faculty::query()
            ->whereNotNull('department')
            ->distinct()
            ->orderBy('department')
            ->pluck('department')
            ->filter()
            ->values()
            ->all();

        $stats = [
            'total' => Faculty::query()->count(),
            'active' => Faculty::query()->where('status', 'active')->count(),
            'inactive' => Faculty::query()->where('status', 'inactive')->count(),
            'on_leave' => Faculty::query()->where('status', 'on_leave')->count(),
            'with_current_classes' => Faculty::query()
                ->whereHas('classes', fn ($query) => $query->currentAcademicPeriod())
                ->count(),
        ];

        return Inertia::render('administrators/faculties/index', [
            'user' => $this->getUserProps(),
            'filament' => [
                'faculties' => [
                    'index_url' => route('filament.admin.resources.faculties.index'),
                    'create_url' => route('filament.admin.resources.faculties.create'),
                ],
            ],
            'stats' => $stats,
            'faculties' => $faculties,
            'filters' => [
                'search' => is_string($search) ? $search : null,
                'department' => is_string($department) ? $department : null,
                'status' => is_string($status) ? $status : null,
                'current_classes' => is_string($currentClasses) ? $currentClasses : null,
            ],
            'options' => [
                'departments' => $departments,
                'statuses' => [
                    ['value' => 'active', 'label' => 'Active'],
                    ['value' => 'inactive', 'label' => 'Inactive'],
                    ['value' => 'on_leave', 'label' => 'On Leave'],
                ],
                'current_classes' => [
                    ['value' => 'all', 'label' => 'All faculty'],
                    ['value' => 'has', 'label' => 'Has current classes'],
                    ['value' => 'none', 'label' => 'No current classes'],
                ],
            ],
        ]);
    }

    public function create(): Response
    {
        $nextFacultyIdNumber = $this->generateNextFacultyIdNumber();

        $departments = Faculty::query()
            ->whereNotNull('department')
            ->distinct()
            ->orderBy('department')
            ->pluck('department')
            ->filter()
            ->values()
            ->all();

        return Inertia::render('administrators/faculties/create', [
            'user' => $this->getUserProps(),
            'defaults' => [
                'faculty_id_number' => $nextFacultyIdNumber,
                'status' => 'active',
            ],
            'options' => [
                'departments' => $departments,
                'statuses' => [
                    ['value' => 'active', 'label' => 'Active'],
                    ['value' => 'inactive', 'label' => 'Inactive'],
                    ['value' => 'on_leave', 'label' => 'On Leave'],
                ],
                'genders' => [
                    ['value' => 'male', 'label' => 'Male'],
                    ['value' => 'female', 'label' => 'Female'],
                    ['value' => 'other', 'label' => 'Other'],
                ],
            ],
        ]);
    }

    public function store(StoreFacultyRequest $request): RedirectResponse
    {
        $data = $request->validated();

        if ($request->hasFile('photo')) {
            $data['photo_url'] = $request->file('photo')->storePublicly('faculty-photos', 'public');
        }

        unset($data['photo']);

        // The faculty table requires a password in some installs; generate one if not present.
        $data['password'] = Hash::make(Str::random(32));

        $faculty = Faculty::create($data);

        return redirect()
            ->route('administrators.faculties.show', $faculty)
            ->with('flash', [
                'type' => 'success',
                'message' => 'Faculty created successfully.',
            ]);
    }

    public function show(Faculty $faculty, ClassAssignmentService $classAssignmentService): Response
    {
        $faculty->loadMissing([
            'classes' => fn ($query) => $query->orderByDesc('school_year')->orderByDesc('semester'),
        ]);

        $unassignedClasses = Classes::query()
            ->currentAcademicPeriod()
            ->whereNull('faculty_id')
            ->with(['schedules.room'])
            ->orderBy('subject_code')
            ->get()
            ->map(fn (Classes $class): array => [
                'id' => $class->id,
                'subject_code' => $class->subject_code,
                'subject_title' => $class->subject_title,
                'section' => $class->section,
                'schedule' => $class->formatted_weekly_schedule,
                'units' => $class->units ?? 3.0, // Assuming units exists or default to 3
                'label' => $classAssignmentService->formatClassLabel($class),
            ])
            ->values()
            ->all();

        $currentClasses = Classes::query()
            ->currentAcademicPeriod()
            ->where('faculty_id', $faculty->id)
            ->with(['schedules.room'])
            ->orderBy('subject_code')
            ->orderBy('section')
            ->get();

        $currentClassesPayload = $currentClasses->map(fn (Classes $class): array => [
            'id' => $class->id,
            'subject_code' => $class->subject_code,
            'subject_title' => $class->subject_title,
            'section' => $class->section,
            'school_year' => $class->school_year,
            'semester' => $class->semester,
            'classification' => $class->classification,
            'schedule' => $class->formatted_weekly_schedule,
        ])->all();

        return Inertia::render('administrators/faculties/show', [
            'user' => $this->getUserProps(),
            'faculty' => [
                'id' => $faculty->id,
                'faculty_id_number' => $faculty->faculty_id_number,
                'first_name' => $faculty->first_name,
                'middle_name' => $faculty->middle_name,
                'last_name' => $faculty->last_name,
                'name' => $faculty->full_name,
                'email' => $faculty->email,
                'phone_number' => $faculty->phone_number,
                'department' => $faculty->department,
                'office_hours' => $faculty->office_hours,
                'birth_date' => $faculty->birth_date?->format('Y-m-d'),
                'address_line1' => $faculty->address_line1,
                'biography' => $faculty->biography,
                'education' => $faculty->education,
                'courses_taught' => $faculty->courses_taught,
                'photo_url' => $faculty->photo_url,
                'avatar_url' => $faculty->photo_url ?: $faculty->getFilamentAvatarUrl(),
                'status' => $faculty->status,
                'gender' => $faculty->gender,
                'age' => $faculty->age,
                'created_at' => format_timestamp($faculty->created_at),
                'updated_at' => format_timestamp($faculty->updated_at),
                'classes' => $faculty->classes->map(fn (Classes $class): array => [
                    'id' => $class->id,
                    'subject_code' => $class->subject_code,
                    'subject_title' => $class->subject_title,
                    'section' => $class->section,
                    'school_year' => $class->school_year,
                    'semester' => $class->semester,
                    'classification' => $class->classification,
                ])->all(),
                'current_classes' => $currentClassesPayload,
                'filament' => [
                    'view_url' => route('filament.admin.resources.faculties.view', $faculty),
                    'edit_url' => route('filament.admin.resources.faculties.edit', $faculty),
                ],
            ],
            'options' => [
                'unassigned_classes' => $unassignedClasses,
                'statuses' => [
                    ['value' => 'active', 'label' => 'Active'],
                    ['value' => 'inactive', 'label' => 'Inactive'],
                    ['value' => 'on_leave', 'label' => 'On Leave'],
                ],
            ],
        ]);
    }

    public function edit(Faculty $faculty): Response
    {
        $departments = Faculty::query()
            ->whereNotNull('department')
            ->distinct()
            ->orderBy('department')
            ->pluck('department')
            ->filter()
            ->values()
            ->all();

        return Inertia::render('administrators/faculties/edit', [
            'user' => $this->getUserProps(),
            'faculty' => [
                'id' => $faculty->id,
                'faculty_id_number' => $faculty->faculty_id_number,
                'first_name' => $faculty->first_name,
                'middle_name' => $faculty->middle_name,
                'last_name' => $faculty->last_name,
                'email' => $faculty->email,
                'phone_number' => $faculty->phone_number,
                'department' => $faculty->department,
                'status' => $faculty->status,
                'gender' => $faculty->gender,
                'birth_date' => $faculty->birth_date?->format('Y-m-d'),
                'age' => $faculty->age,
                'office_hours' => $faculty->office_hours,
                'address_line1' => $faculty->address_line1,
                'biography' => $faculty->biography,
                'education' => $faculty->education,
                'courses_taught' => $faculty->courses_taught,
                'photo_url' => $faculty->photo_url,
            ],
            'options' => [
                'departments' => $departments,
                'statuses' => [
                    ['value' => 'active', 'label' => 'Active'],
                    ['value' => 'inactive', 'label' => 'Inactive'],
                    ['value' => 'on_leave', 'label' => 'On Leave'],
                ],
                'genders' => [
                    ['value' => 'male', 'label' => 'Male'],
                    ['value' => 'female', 'label' => 'Female'],
                    ['value' => 'other', 'label' => 'Other'],
                ],
            ],
        ]);
    }

    public function update(UpdateFacultyRequest $request, Faculty $faculty): RedirectResponse
    {
        $data = $request->validated();

        if ($request->hasFile('photo')) {
            $data['photo_url'] = $request->file('photo')->storePublicly('faculty-photos', 'public');
        }

        unset($data['photo']);

        $faculty->update($data);

        return redirect()
            ->route('administrators.faculties.show', $faculty)
            ->with('flash', [
                'type' => 'success',
                'message' => 'Faculty updated successfully.',
            ]);
    }

    public function destroy(Faculty $faculty): RedirectResponse
    {
        $faculty->delete();

        return redirect()
            ->route('administrators.faculties.index')
            ->with('flash', [
                'type' => 'success',
                'message' => 'Faculty deleted successfully.',
            ]);
    }

    public function assignClasses(Request $request, Faculty $faculty, ClassAssignmentService $classAssignmentService): RedirectResponse
    {
        $data = $request->validate([
            'class_ids' => ['required', 'array'],
            'class_ids.*' => ['integer', 'exists:classes,id'],
        ]);

        $count = $classAssignmentService->assignClassesToFaculty($data['class_ids'], (string) $faculty->id);

        return redirect()
            ->back()
            ->with('flash', [
                'type' => 'success',
                'message' => sprintf('Assigned %d class(es) to %s.', $count, $faculty->full_name),
            ]);
    }

    public function unassignClass(Faculty $faculty, Classes $class, ClassAssignmentService $classAssignmentService): RedirectResponse
    {
        if ((string) $class->faculty_id !== (string) $faculty->id) {
            abort(404);
        }

        $classAssignmentService->unassignClass($class);

        return redirect()
            ->back()
            ->with('flash', [
                'type' => 'success',
                'message' => 'Class unassigned successfully.',
            ]);
    }

    public function updateFacultyIdNumber(Request $request, Faculty $faculty): RedirectResponse
    {
        $data = $request->validate([
            'faculty_id_number' => [
                'required',
                'string',
                'max:255',
                Rule::unique('faculty', 'faculty_id_number')->ignore($faculty->id),
            ],
        ]);

        $faculty->update([
            'faculty_id_number' => $data['faculty_id_number'],
        ]);

        return redirect()
            ->back()
            ->with('flash', [
                'type' => 'success',
                'message' => 'Faculty ID number updated successfully.',
            ]);
    }

    private function generateNextFacultyIdNumber(): string
    {
        $latestFaculty = Faculty::query()
            ->whereNotNull('faculty_id_number')
            ->orderByRaw('CAST(faculty_id_number AS INTEGER) DESC')
            ->first();

        if (! $latestFaculty || ! $latestFaculty->faculty_id_number) {
            return '1';
        }

        $latestId = (int) $latestFaculty->faculty_id_number;

        return (string) ($latestId + 1);
    }

    private function getUserProps(): array
    {
        $user = Auth::user();

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
