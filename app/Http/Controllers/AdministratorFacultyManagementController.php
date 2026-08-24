<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Http\Requests\Administrators\AssignFacultyClassesRequest;
use App\Http\Requests\Administrators\BulkUpdateFacultyStatusRequest;
use App\Http\Requests\Administrators\ManageFacultyPortalAccountRequest;
use App\Http\Requests\Administrators\SendFacultyNoticeRequest;
use App\Http\Requests\Administrators\StoreFacultyDeadlineRequest;
use App\Http\Requests\Administrators\StoreFacultyRequest;
use App\Http\Requests\Administrators\UpdateFacultyRequest;
use App\Models\Classes;
use App\Models\Department;
use App\Models\Faculty;
use App\Models\FacultyDeadline;
use App\Models\User;
use App\Notifications\AdminFacultyNoticeNotification;
use App\Services\ClassAssignmentService;
use App\Services\IdentifierGenerator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
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
        $portal = $request->input('portal');
        $profile = $request->input('profile');
        $segment = $this->stringFilter($request->input('segment')) ?? 'all';
        $sort = $this->stringFilter($request->input('sort')) ?? 'faculty';
        $direction = $this->stringFilter($request->input('direction')) === 'desc' ? 'desc' : 'asc';
        $perPage = min(max($request->integer('per_page', 20), 10), 100);

        $facultiesQuery = Faculty::query()
            ->withCount([
                'classes',
                'classes as current_classes_count' => fn (Builder $query): Builder => $query->currentAcademicPeriod(),
            ])
            ->when(is_string($search) && mb_trim($search) !== '', function (Builder $builder) use ($search): void {
                $query = mb_trim($search);

                $builder->where(function (Builder $nested) use ($query): void {
                    $like = "%{$query}%";

                    $nested->where('faculty_id_number', 'like', $like)
                        ->orWhere('first_name', 'like', $like)
                        ->orWhere('last_name', 'like', $like)
                        ->orWhere('email', 'like', $like);
                });
            })
            ->when(is_string($department) && $department !== '' && $department !== 'all', function (Builder $builder) use ($department): void {
                $builder->where('department', $department);
            })
            ->when(is_string($status) && $status !== '' && $status !== 'all', function (Builder $builder) use ($status): void {
                $builder->where('status', $status);
            })
            ->when(is_string($currentClasses) && $currentClasses !== '' && $currentClasses !== 'all', function (Builder $builder) use ($currentClasses): void {
                if ($currentClasses === 'has') {
                    $builder->whereHas('classes', fn (Builder $query): Builder => $query->currentAcademicPeriod());
                }

                if ($currentClasses === 'none') {
                    $builder->whereDoesntHave('classes', fn (Builder $query): Builder => $query->currentAcademicPeriod());
                }
            })
            ->when($portal === 'linked', fn (Builder $builder): Builder => $this->wherePortalLinked($builder))
            ->when($portal === 'not_linked', fn (Builder $builder): Builder => $this->wherePortalNotLinked($builder))
            ->when($profile === 'incomplete', fn (Builder $builder): Builder => $this->whereProfileIncomplete($builder));

        $this->applySegment($facultiesQuery, $segment);
        $this->applySort($facultiesQuery, $sort, $direction);

        /** @var LengthAwarePaginator $faculties */
        $faculties = $facultiesQuery
            ->paginate($perPage)
            ->withQueryString();

        $portalUsers = $this->portalUsersFor($faculties->getCollection());

        $faculties->through(function (Faculty $faculty) use ($portalUsers): array {
            $portalUser = $this->matchingPortalUser($faculty, $portalUsers);

            return [
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
                'profile_completion' => $this->profileCompletion($faculty),
                'portal_account' => $this->portalAccountPayload($faculty, $portalUser),
                'workload_summary' => $this->workloadSummary((int) $faculty->current_classes_count),
                'filament' => [
                    'view_url' => route('filament.admin.resources.faculties.view', $faculty),
                    'edit_url' => route('filament.admin.resources.faculties.edit', $faculty),
                ],
            ];
        });

        return Inertia::render('administrators/faculties/index', [
            'user' => $this->getUserProps(),
            'filament' => [
                'faculties' => [
                    'index_url' => route('filament.admin.resources.faculties.index'),
                    'create_url' => route('filament.admin.resources.faculties.create'),
                ],
            ],
            'stats' => $this->facultyOperationsStats(),
            'segments' => $this->segmentsPayload(),
            'faculties' => $faculties,
            'filters' => [
                'search' => $this->stringFilter($search),
                'department' => $this->stringFilter($department),
                'status' => $this->stringFilter($status),
                'current_classes' => $this->stringFilter($currentClasses),
                'portal' => $this->stringFilter($portal),
                'profile' => $this->stringFilter($profile),
                'segment' => $segment,
                'sort' => $sort,
                'direction' => $direction,
                'per_page' => $perPage,
            ],
            'options' => [
                'departments' => $this->departmentOptions(),
                'statuses' => $this->statusOptions(),
                'current_classes' => [
                    ['value' => 'all', 'label' => 'All faculty'],
                    ['value' => 'has', 'label' => 'Has current classes'],
                    ['value' => 'none', 'label' => 'No current classes'],
                ],
                'portal' => [
                    ['value' => 'all', 'label' => 'All portal states'],
                    ['value' => 'linked', 'label' => 'Portal linked'],
                    ['value' => 'not_linked', 'label' => 'Portal not linked'],
                ],
                'profile' => [
                    ['value' => 'all', 'label' => 'All profiles'],
                    ['value' => 'incomplete', 'label' => 'Incomplete profile'],
                ],
                'sorts' => [
                    ['value' => 'faculty', 'label' => 'Faculty name'],
                    ['value' => 'department', 'label' => 'Department'],
                    ['value' => 'status', 'label' => 'Status'],
                    ['value' => 'current_classes_count', 'label' => 'Current class load'],
                    ['value' => 'created_at', 'label' => 'Newest profile'],
                ],
            ],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('administrators/faculties/create', [
            'user' => $this->getUserProps(),
            'defaults' => [
                'faculty_id_number' => $this->generateNextFacultyIdNumber(),
                'status' => 'active',
            ],
            'options' => $this->formOptions(),
        ]);
    }

    public function store(StoreFacultyRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $identifierGenerator = app(IdentifierGenerator::class);

        if (($data['faculty_id_number'] ?? null) === $identifierGenerator->previewStaffId()) {
            $data['faculty_id_number'] = $identifierGenerator->generateStaffId();
        }

        if ($request->hasFile('photo')) {
            $data['photo_url'] = $request->file('photo')->storePublicly('faculty-photos', 'public');
        }

        unset($data['photo']);

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
            'classes' => fn ($query) => $query
                ->withCount('class_enrollments')
                ->orderByDesc('school_year')
                ->orderByDesc('semester')
                ->orderBy('subject_code'),
        ]);

        $currentClasses = Classes::query()
            ->currentAcademicPeriod()
            ->where('faculty_id', $faculty->id)
            ->with(['schedules.room'])
            ->withCount('class_enrollments')
            ->orderBy('subject_code')
            ->orderBy('section')
            ->get();

        $availableClasses = Classes::query()
            ->currentAcademicPeriod()
            ->with(['schedules.room', 'Faculty'])
            ->withCount('class_enrollments')
            ->orderBy('subject_code')
            ->orderBy('section')
            ->get();

        $portalUser = $this->portalUserFor($faculty);
        $recentNotifications = $portalUser instanceof User
            ? $portalUser->notifications()->latest()->take(5)->get()->map(fn ($notification): array => [
                'id' => $notification->id,
                'title' => $notification->data['title'] ?? 'Notification',
                'message' => $notification->data['message'] ?? $notification->data['body'] ?? '',
                'priority' => $notification->data['priority'] ?? $notification->data['type'] ?? 'normal',
                'read_at' => format_timestamp($notification->read_at),
                'created_at' => format_timestamp($notification->created_at),
            ])->all()
            : [];

        $deadlines = FacultyDeadline::query()
            ->where('faculty_id', $faculty->id)
            ->where('is_active', true)
            ->with('relatedClass')
            ->orderBy('due_date')
            ->take(8)
            ->get()
            ->map(fn (FacultyDeadline $deadline): array => [
                'id' => $deadline->id,
                'title' => $deadline->title,
                'description' => $deadline->description,
                'due_date' => $deadline->due_date?->format('Y-m-d H:i:s'),
                'priority' => $deadline->priority,
                'type' => $deadline->type,
                'class_label' => $deadline->relatedClass ? $classAssignmentService->formatClassLabel($deadline->relatedClass) : null,
            ])
            ->all();

        return Inertia::render('administrators/faculties/show', [
            'user' => $this->getUserProps(),
            'faculty' => [
                ...$this->facultyDetailPayload($faculty),
                'classes' => $faculty->classes->map(fn (Classes $class): array => $this->classPayload($class, false))->all(),
                'current_classes' => $currentClasses->map(fn (Classes $class): array => $this->classPayload($class))->all(),
                'profile_completion' => $this->profileCompletion($faculty),
                'portal_account' => $this->portalAccountPayload($faculty, $portalUser),
                'workload_summary' => $this->workloadSummary($currentClasses->count()),
                'recommended_actions' => $this->recommendedActions($faculty, $portalUser, $currentClasses->count()),
                'recent_notifications' => $recentNotifications,
                'deadlines' => $deadlines,
                'filament' => [
                    'view_url' => route('filament.admin.resources.faculties.view', $faculty),
                    'edit_url' => route('filament.admin.resources.faculties.edit', $faculty),
                ],
            ],
            'assignment_planner' => [
                'classes' => $availableClasses
                    ->map(fn (Classes $class): array => [
                        ...$this->classPayload($class),
                        'label' => $classAssignmentService->formatClassLabel($class),
                        'assigned_faculty' => $class->Faculty ? [
                            'id' => $class->Faculty->id,
                            'name' => $class->Faculty->full_name,
                        ] : null,
                        'assignment_status' => $this->assignmentStatus($class, $faculty),
                        'warnings' => $this->assignmentWarnings($faculty, $class, $currentClasses),
                    ])
                    ->values()
                    ->all(),
            ],
            'options' => [
                'statuses' => $this->statusOptions(),
                'faculty_roles' => $this->facultyRoleOptions(),
                'deadline_priorities' => [
                    ['value' => 'low', 'label' => 'Low'],
                    ['value' => 'medium', 'label' => 'Medium'],
                    ['value' => 'high', 'label' => 'High'],
                ],
                'notice_priorities' => [
                    ['value' => 'low', 'label' => 'Low'],
                    ['value' => 'normal', 'label' => 'Normal'],
                    ['value' => 'high', 'label' => 'High'],
                    ['value' => 'urgent', 'label' => 'Urgent'],
                ],
            ],
        ]);
    }

    public function edit(Faculty $faculty): Response
    {
        return Inertia::render('administrators/faculties/edit', [
            'user' => $this->getUserProps(),
            'faculty' => $this->facultyDetailPayload($faculty),
            'options' => $this->formOptions(),
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

        $portalUser = $this->portalUserFor($faculty);
        if ($portalUser instanceof User) {
            $portalUser->forceFill([
                'name' => $faculty->full_name,
                'email' => $faculty->email,
                'faculty_id_number' => $faculty->faculty_id_number,
                'record_id' => $faculty->id,
            ])->save();
        }

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

    public function bulkUpdateStatus(BulkUpdateFacultyStatusRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $count = Faculty::query()
            ->whereIn('id', $data['faculty_ids'])
            ->update(['status' => $data['status']]);

        return redirect()
            ->back()
            ->with('flash', [
                'type' => 'success',
                'message' => sprintf('Updated %d faculty profile(s).', $count),
            ]);
    }

    public function assignClasses(
        AssignFacultyClassesRequest $request,
        Faculty $faculty,
        ClassAssignmentService $classAssignmentService
    ): RedirectResponse {
        $data = $request->validated();
        $classIds = $data['class_ids'];

        $classes = Classes::query()
            ->whereIn('id', $classIds)
            ->get();

        $reassignedClasses = $classes
            ->filter(fn (Classes $class): bool => filled($class->faculty_id) && (string) $class->faculty_id !== (string) $faculty->id);

        if ($reassignedClasses->isNotEmpty() && ! (bool) ($data['allow_reassignment'] ?? false)) {
            throw ValidationException::withMessages([
                'class_ids' => 'One or more selected classes already have a faculty assignment. Confirm reassignment before continuing.',
            ]);
        }

        $count = $classAssignmentService->assignClassesToFaculty($classIds, (string) $faculty->id);

        if ((bool) ($data['notify_faculty'] ?? false)) {
            $this->notifyPortalUser(
                $faculty,
                'Class assignment updated',
                sprintf('%d class(es) were assigned to your teaching load.', $count),
                route('faculty.schedule'),
            );
        }

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

    public function managePortalAccount(ManageFacultyPortalAccountRequest $request, Faculty $faculty): RedirectResponse
    {
        $data = $request->validated();
        $portalUser = User::query()->where('email', $faculty->email)->first();

        if (! $portalUser && $data['mode'] === 'repair') {
            throw ValidationException::withMessages([
                'mode' => 'No portal user exists for this faculty email. Create a portal account instead.',
            ]);
        }

        if (! $portalUser) {
            $portalUser = User::create([
                'name' => $faculty->full_name,
                'email' => $faculty->email,
                'password' => Hash::make(Str::random(32)),
                'role' => $data['role'],
                'school_id' => $faculty->school_id,
                'department' => $faculty->department,
                'faculty_id_number' => $faculty->faculty_id_number,
                'record_id' => $faculty->id,
            ]);
        } else {
            $portalUser->forceFill([
                'name' => $portalUser->name ?: $faculty->full_name,
                'role' => $data['role'],
                'school_id' => $portalUser->school_id ?: $faculty->school_id,
                'department' => $portalUser->department ?: $faculty->department,
                'faculty_id_number' => $faculty->faculty_id_number,
                'record_id' => $faculty->id,
            ])->save();
        }

        if ((bool) ($data['send_reset_link'] ?? false)) {
            Password::broker()->sendResetLink(['email' => $portalUser->email]);
        }

        return redirect()
            ->back()
            ->with('flash', [
                'type' => 'success',
                'message' => 'Faculty portal account is ready.',
            ]);
    }

    public function sendNotice(SendFacultyNoticeRequest $request, Faculty $faculty): RedirectResponse
    {
        $data = $request->validated();
        $portalUser = $this->portalUserFor($faculty);

        if (! $portalUser instanceof User) {
            throw ValidationException::withMessages([
                'title' => 'Create or repair the portal account before sending a notice.',
            ]);
        }

        $portalUser->notify(new AdminFacultyNoticeNotification(
            $data['title'],
            $data['message'],
            $data['priority'],
            $data['action_url'] ?? null,
        ));

        return redirect()
            ->back()
            ->with('flash', [
                'type' => 'success',
                'message' => 'Notice sent to faculty portal.',
            ]);
    }

    public function storeDeadline(StoreFacultyDeadlineRequest $request, Faculty $faculty): RedirectResponse
    {
        $data = $request->validated();

        FacultyDeadline::create([
            ...$data,
            'faculty_id' => $faculty->id,
            'is_active' => true,
        ]);

        return redirect()
            ->back()
            ->with('flash', [
                'type' => 'success',
                'message' => 'Faculty deadline created.',
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

        $portalUser = $this->portalUserFor($faculty);
        if ($portalUser instanceof User) {
            $portalUser->forceFill([
                'faculty_id_number' => $data['faculty_id_number'],
                'record_id' => $faculty->id,
            ])->save();
        }

        return redirect()
            ->back()
            ->with('flash', [
                'type' => 'success',
                'message' => 'Faculty ID number updated successfully.',
            ]);
    }

    private function applySegment(Builder $query, string $segment): void
    {
        match ($segment) {
            'needs_classes' => $query->whereDoesntHave('classes', fn (Builder $builder): Builder => $builder->currentAcademicPeriod()),
            'on_leave' => $query->where('status', 'on_leave'),
            'portal_not_linked' => $this->wherePortalNotLinked($query),
            'incomplete_profile' => $this->whereProfileIncomplete($query),
            default => null,
        };
    }

    private function applySort(Builder $query, string $sort, string $direction): void
    {
        match ($sort) {
            'department' => $query->orderBy('department', $direction)->orderBy('last_name')->orderBy('first_name'),
            'status' => $query->orderBy('status', $direction)->orderBy('last_name')->orderBy('first_name'),
            'current_classes_count' => $query->orderBy('current_classes_count', $direction)->orderBy('last_name'),
            'created_at' => $query->orderBy('created_at', $direction)->orderBy('last_name'),
            default => $query->orderBy('last_name', $direction)->orderBy('first_name', $direction),
        };
    }

    private function wherePortalLinked(Builder $query): Builder
    {
        return $query->whereExists(function ($subQuery): void {
            $subQuery->selectRaw('1')
                ->from('users')
                ->whereColumn('users.email', 'faculty.email')
                ->where(function ($nested): void {
                    $nested->whereColumn('users.record_id', DB::raw('cast(faculty.id as varchar)'))
                        ->orWhereColumn('users.faculty_id_number', 'faculty.faculty_id_number');
                });
        });
    }

    private function wherePortalNotLinked(Builder $query): Builder
    {
        return $query->whereNotExists(function ($subQuery): void {
            $subQuery->selectRaw('1')
                ->from('users')
                ->whereColumn('users.email', 'faculty.email')
                ->where(function ($nested): void {
                    $nested->whereColumn('users.record_id', DB::raw('cast(faculty.id as varchar)'))
                        ->orWhereColumn('users.faculty_id_number', 'faculty.faculty_id_number');
                });
        });
    }

    private function whereProfileIncomplete(Builder $query): Builder
    {
        return $query->where(function (Builder $builder): void {
            foreach (['phone_number', 'department', 'office_hours', 'education', 'courses_taught'] as $column) {
                $builder->orWhereNull($column)->orWhere($column, '');
            }
        });
    }

    private function facultyOperationsStats(): array
    {
        return [
            'total' => Faculty::query()->count(),
            'active' => Faculty::query()->where('status', 'active')->count(),
            'inactive' => Faculty::query()->where('status', 'inactive')->count(),
            'on_leave' => Faculty::query()->where('status', 'on_leave')->count(),
            'with_current_classes' => Faculty::query()
                ->whereHas('classes', fn (Builder $query): Builder => $query->currentAcademicPeriod())
                ->count(),
            'needs_classes' => Faculty::query()
                ->whereDoesntHave('classes', fn (Builder $query): Builder => $query->currentAcademicPeriod())
                ->count(),
            'portal_not_linked' => $this->wherePortalNotLinked(Faculty::query())->count(),
            'incomplete_profile' => $this->whereProfileIncomplete(Faculty::query())->count(),
            'unassigned_current_classes' => Classes::query()
                ->currentAcademicPeriod()
                ->whereNull('faculty_id')
                ->count(),
        ];
    }

    private function segmentsPayload(): array
    {
        return [
            ['value' => 'all', 'label' => 'All'],
            ['value' => 'needs_classes', 'label' => 'Needs classes'],
            ['value' => 'on_leave', 'label' => 'On leave'],
            ['value' => 'portal_not_linked', 'label' => 'Portal not linked'],
            ['value' => 'incomplete_profile', 'label' => 'Incomplete profile'],
        ];
    }

    private function departmentOptions(): array
    {
        $canonical = Department::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['code', 'name'])
            ->flatMap(fn (Department $department): array => [
                $department->code,
                $department->name,
            ]);

        $legacy = Faculty::query()
            ->whereNotNull('department')
            ->distinct()
            ->orderBy('department')
            ->pluck('department');

        return $canonical
            ->merge($legacy)
            ->filter(fn ($department): bool => is_string($department) && mb_trim($department) !== '')
            ->unique()
            ->values()
            ->all();
    }

    private function formOptions(): array
    {
        return [
            'departments' => $this->departmentOptions(),
            'statuses' => $this->statusOptions(),
            'genders' => [
                ['value' => 'male', 'label' => 'Male'],
                ['value' => 'female', 'label' => 'Female'],
                ['value' => 'other', 'label' => 'Other'],
            ],
        ];
    }

    private function statusOptions(): array
    {
        return [
            ['value' => 'active', 'label' => 'Active'],
            ['value' => 'inactive', 'label' => 'Inactive'],
            ['value' => 'on_leave', 'label' => 'On Leave'],
        ];
    }

    private function facultyRoleOptions(): array
    {
        return collect([
            UserRole::Professor,
            UserRole::AssociateProfessor,
            UserRole::AssistantProfessor,
            UserRole::Instructor,
            UserRole::PartTimeFaculty,
        ])->map(fn (UserRole $role): array => [
            'value' => $role->value,
            'label' => $role->getLabel(),
        ])->all();
    }

    private function facultyDetailPayload(Faculty $faculty): array
    {
        return [
            'id' => $faculty->id,
            'faculty_id_number' => $faculty->faculty_id_number,
            'first_name' => $faculty->first_name,
            'middle_name' => $faculty->middle_name,
            'last_name' => $faculty->last_name,
            'name' => $faculty->full_name,
            'email' => $faculty->email,
            'phone_number' => $faculty->phone_number,
            'department' => $faculty->department,
            'position' => $faculty->position,
            'office_hours' => $faculty->office_hours,
            'birth_date' => $faculty->birth_date?->format('Y-m-d'),
            'date_employed' => $faculty->date_employed?->format('Y-m-d'),
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
        ];
    }

    private function classPayload(Classes $class, bool $includeSchedule = true): array
    {
        return [
            'id' => $class->id,
            'subject_code' => $class->subject_code,
            'subject_title' => $class->subject_title,
            'section' => $class->section,
            'school_year' => $class->school_year,
            'semester' => $class->semester,
            'classification' => $class->classification,
            'student_count' => $class->class_enrollments_count ?? null,
            'schedule' => $includeSchedule ? $class->formatted_weekly_schedule : null,
        ];
    }

    /**
     * @param  Collection<int, Faculty>  $faculties
     * @return Collection<int, User>
     */
    private function portalUsersFor(Collection $faculties): Collection
    {
        return User::query()
            ->whereIn('email', $faculties->pluck('email')->filter()->unique()->values())
            ->get();
    }

    /**
     * @param  Collection<int, User>  $users
     */
    private function matchingPortalUser(Faculty $faculty, Collection $users): ?User
    {
        return $users->first(fn (User $user): bool => $user->email === $faculty->email
            && ((string) $user->record_id === (string) $faculty->id
                || $user->faculty_id_number === $faculty->faculty_id_number)) ?? $users->first(fn (User $user): bool => $user->email === $faculty->email);
    }

    private function portalUserFor(Faculty $faculty): ?User
    {
        return User::query()
            ->where('email', $faculty->email)
            ->where(function (Builder $query) use ($faculty): void {
                $query->where('record_id', (string) $faculty->id)
                    ->orWhere('faculty_id_number', $faculty->faculty_id_number);
            })
            ->first()
            ?? User::query()->where('email', $faculty->email)->first();
    }

    private function portalAccountPayload(Faculty $faculty, ?User $user): array
    {
        if (! $user instanceof User) {
            return [
                'status' => 'not_linked',
                'label' => 'Portal not linked',
                'user_id' => null,
                'role' => null,
                'role_label' => null,
                'email_verified_at' => null,
                'last_login_at' => null,
                'needs_repair' => true,
            ];
        }

        $needsRepair = (string) $user->record_id !== (string) $faculty->id
            || $user->faculty_id_number !== $faculty->faculty_id_number
            || ! $user->role?->isFaculty();

        return [
            'status' => $needsRepair ? 'needs_repair' : 'linked',
            'label' => $needsRepair ? 'Needs repair' : 'Portal linked',
            'user_id' => $user->id,
            'role' => $user->role?->value,
            'role_label' => $user->role?->getLabel(),
            'email_verified_at' => format_timestamp($user->email_verified_at),
            'last_login_at' => format_timestamp($user->last_login_at),
            'needs_repair' => $needsRepair,
        ];
    }

    private function profileCompletion(Faculty $faculty): array
    {
        $fields = [
            'faculty_id_number' => 'Faculty ID',
            'email' => 'Email',
            'phone_number' => 'Phone',
            'department' => 'Department',
            'office_hours' => 'Office hours',
            'education' => 'Education',
            'courses_taught' => 'Courses taught',
        ];

        $missing = collect($fields)
            ->filter(fn (string $label, string $field): bool => blank($faculty->{$field}))
            ->values()
            ->all();

        $completed = count($fields) - count($missing);

        return [
            'completed' => $completed,
            'total' => count($fields),
            'percent' => (int) round(($completed / count($fields)) * 100),
            'missing' => $missing,
        ];
    }

    private function workloadSummary(int $currentClassesCount): array
    {
        $level = match (true) {
            $currentClassesCount === 0 => 'needs_classes',
            $currentClassesCount >= 6 => 'heavy',
            $currentClassesCount >= 4 => 'balanced',
            default => 'light',
        };

        return [
            'current_classes_count' => $currentClassesCount,
            'level' => $level,
            'label' => match ($level) {
                'needs_classes' => 'Needs assignment',
                'heavy' => 'Heavy load',
                'balanced' => 'Balanced load',
                default => 'Light load',
            },
        ];
    }

    private function recommendedActions(Faculty $faculty, ?User $portalUser, int $currentClassesCount): array
    {
        $actions = [];
        $completion = $this->profileCompletion($faculty);

        if ($currentClassesCount === 0 && $faculty->status === 'active') {
            $actions[] = [
                'type' => 'assignment',
                'title' => 'Assign current classes',
                'description' => 'This active faculty member has no classes in the current academic period.',
            ];
        }

        if (! $portalUser || $this->portalAccountPayload($faculty, $portalUser)['needs_repair']) {
            $actions[] = [
                'type' => 'portal',
                'title' => 'Repair portal access',
                'description' => 'Portal user metadata does not fully match this faculty profile.',
            ];
        }

        if ($completion['percent'] < 75) {
            $actions[] = [
                'type' => 'profile',
                'title' => 'Complete profile records',
                'description' => 'Important contact or teaching profile fields are still missing.',
            ];
        }

        return $actions;
    }

    private function assignmentStatus(Classes $class, Faculty $faculty): string
    {
        if (! $class->faculty_id) {
            return 'unassigned';
        }

        if ((string) $class->faculty_id === (string) $faculty->id) {
            return 'assigned_here';
        }

        return 'assigned_elsewhere';
    }

    /**
     * @param  Collection<int, Classes>  $currentClasses
     * @return array<int, array<string, string>>
     */
    private function assignmentWarnings(Faculty $faculty, Classes $candidateClass, Collection $currentClasses): array
    {
        $warnings = [];

        if ($this->assignmentStatus($candidateClass, $faculty) === 'assigned_elsewhere') {
            $warnings[] = [
                'type' => 'reassignment',
                'message' => 'Already assigned to '.$candidateClass->Faculty?->full_name.'.',
            ];
        }

        foreach ($candidateClass->schedules as $candidateSchedule) {
            foreach ($currentClasses as $currentClass) {
                if ((int) $currentClass->id === (int) $candidateClass->id) {
                    continue;
                }

                foreach ($currentClass->schedules as $currentSchedule) {
                    if (mb_strtolower((string) $candidateSchedule->day_of_week) !== mb_strtolower((string) $currentSchedule->day_of_week)) {
                        continue;
                    }

                    if ($candidateSchedule->start_time < $currentSchedule->end_time && $candidateSchedule->end_time > $currentSchedule->start_time) {
                        $warnings[] = [
                            'type' => 'schedule_conflict',
                            'message' => sprintf('Overlaps with %s %s.', $currentClass->subject_code, $currentClass->section),
                        ];
                    }
                }
            }
        }

        if ($currentClasses->count() >= 6 && $this->assignmentStatus($candidateClass, $faculty) !== 'assigned_here') {
            $warnings[] = [
                'type' => 'heavy_load',
                'message' => 'Faculty already has a heavy current load.',
            ];
        }

        return collect($warnings)->unique('message')->values()->all();
    }

    private function notifyPortalUser(Faculty $faculty, string $title, string $message, ?string $actionUrl = null): void
    {
        $portalUser = $this->portalUserFor($faculty);

        if (! $portalUser instanceof User) {
            return;
        }

        $portalUser->notify(new AdminFacultyNoticeNotification($title, $message, 'normal', $actionUrl));
    }

    private function stringFilter(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }

    private function generateNextFacultyIdNumber(): string
    {
        return app(IdentifierGenerator::class)->previewStaffId();
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
