<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\AttendanceStatus;
use App\Enums\ClassPostType;
use App\Enums\UserRole;
use App\Exports\StudentListExport;
use App\Http\Controllers\Api\V1\ApiPagination;
use App\Http\Requests\Administrators\CopyClassRequest;
use App\Http\Requests\Administrators\StoreClassRequest;
use App\Http\Requests\Administrators\UpdateClassRequest;
use App\Http\Requests\Administrators\UpdateClassScheduleRequest;
use App\Jobs\MoveStudentToSectionJob;
use App\Models\ClassAttendanceRecord;
use App\Models\ClassAttendanceSession;
use App\Models\ClassEnrollment;
use App\Models\Classes;
use App\Models\ClassPost;
use App\Models\ClassScheduleChange;
use App\Models\ClassScheduleChangeStudentNotification;
use App\Models\Course;
use App\Models\EnrollmentClassWaitlist;
use App\Models\Faculty;
use App\Models\Room;
use App\Models\Schedule;
use App\Models\ShsStrand;
use App\Models\ShsTrack;
use App\Models\StrandSubject;
use App\Models\Subject;
use App\Models\SubjectEnrollment;
use App\Models\User;
use App\Rules\ScheduleOverlapRule;
use App\Services\ClassCurriculumPlacementService;
use App\Services\ClassScheduleChangeNotificationService;
use App\Services\GeneralSettingsService;
use App\Services\Scheduling\ClassScheduleWorkspaceService;
use App\Services\Scheduling\ScheduleConflictService;
use App\Services\Scheduling\SchedulingService;
use App\Support\Database\JsonArrayQuery;
use App\Support\Database\SqlDialect;
use App\Support\ScheduleDelivery;
use Exception;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;
use RuntimeException;
use Spatie\Activitylog\Models\Activity;
use Throwable;

final class AdministratorClassManagementController extends Controller
{
    /**
     * Sort keys the class list accepts.
     *
     * Every one of them maps to something the database can order by, so the
     * list never sorts in PHP behind the user's back. The toolbar control and
     * the column headers offer the same set.
     *
     * @var list<string>
     */
    private const array CLASS_SORTABLE_COLUMNS = [
        'created_at',
        'record_title',
        'subject_code',
        'students_count',
        'faculty',
        'semester',
        'period',
        'section',
    ];

    /**
     * Page sizes the class list accepts.
     *
     * @var list<int>
     */
    private const array CLASS_PAGE_SIZES = [10, 20, 30, 40, 50];

    public function index(
        Request $request,
        GeneralSettingsService $generalSettingsService,
        ClassScheduleWorkspaceService $scheduleWorkspace,
    ): Response {
        $filters = [
            'search' => $this->nullableString($request->input('search')),
            'classification' => $this->nullableString($request->input('classification')),
            'course_id' => $this->nullableInt($request->input('course_id')),
            'shs_track_id' => $this->nullableInt($request->input('shs_track_id')),
            'shs_strand_id' => $this->nullableInt($request->input('shs_strand_id')),
            'subject_code' => $this->nullableString($request->input('subject_code')),
            'room_id' => $this->nullableInt($request->input('room_id')),
            'faculty_id' => $this->nullableString($request->input('faculty_id')),
            'academic_year' => $this->nullableInt($request->input('academic_year')),
            'grade_level' => $this->nullableString($request->input('grade_level')),
            'semester' => $this->nullableString($request->input('semester')),
            'available_slots' => $request->boolean('available_slots') ? true : null,
            'fully_enrolled' => $request->filled('fully_enrolled') ? $request->boolean('fully_enrolled') : null,
            'needs_faculty' => $request->boolean('needs_faculty') ? true : null,
            'needs_schedule' => $request->boolean('needs_schedule') ? true : null,
            'over_capacity' => $request->boolean('over_capacity') ? true : null,
            'trashed' => in_array($request->input('trashed'), ['only', 'with'], true) ? (string) $request->input('trashed') : null,
        ];

        $sort = $this->nullableString($request->input('sort')) ?? 'created_at';
        if (! in_array($sort, self::CLASS_SORTABLE_COLUMNS, true)) {
            $sort = 'created_at';
        }

        $direction = $request->input('direction') === 'asc' ? 'asc' : 'desc';
        $perPage = $this->resolveClassPerPage($request->input('per_page'));
        $page = max(1, (int) $request->input('page', 1));

        /** @var list<int> $enrollmentCourseIds */
        $enrollmentCourseIds = $generalSettingsService->getGlobalSettingsModel()?->enrollment_courses ?? [];

        $courses = Course::query()
            ->when($enrollmentCourseIds !== [], fn ($query) => $query->whereIn('id', $enrollmentCourseIds))
            ->orderBy('code')
            ->get(['id', 'code', 'curriculum_year']);

        $courseCodeById = $courses
            ->mapWithKeys(fn (Course $course): array => [
                $course->id => $course->code,
            ])
            ->all();

        $courseLabelById = $courses
            ->mapWithKeys(fn (Course $course): array => [
                $course->id => $course->curriculum_year
                    ? sprintf('%s (%s)', $course->code, $course->curriculum_year)
                    : $course->code,
            ])
            ->all();

        $classesResolver = function () use (
            $filters,
            $courseCodeById,
            $sort,
            $direction,
            $perPage,
            $page,
            $scheduleWorkspace,
        ): array {
            // The work queues and the rail counts are taken over the filtered
            // set without the rail's own filters, so a queue never hides its
            // own count and both class types stay visible.
            $railBase = $this->buildClassListQuery($filters, ['classification', 'needs_faculty', 'needs_schedule', 'over_capacity']);

            $queues = $this->classQueueCounts($railBase);
            $classificationCounts = $this->classTypeCounts($railBase);

            $classesQuery = $this->buildClassListQuery($filters);

            $this->applyClassSort($classesQuery, $sort, $direction);

            $paginator = $classesQuery->paginate($perPage, ['*'], 'page', $page);
            $totals = $this->classListTotals($classesQuery);
            $summaries = $scheduleWorkspace->summarizeForIndex($paginator->getCollection());

            $filamentClassIndexUrl = route('filament.admin.resources.classes.index');

            return [
                'data' => $paginator->getCollection()
                    ->map(function (Classes $class) use ($courseCodeById, $filamentClassIndexUrl, $summaries): array {
                        $subject = $class->subjects->first();

                        if (! $subject) {
                            $subject = $class->isShs()
                                ? $class->ShsSubject
                                : ($class->Subject ?: $class->SubjectByCodeFallback);
                        }

                        $courseAbbreviations = array_values(array_unique(array_filter(array_map(
                            fn ($id) => $courseCodeById[(int) $id] ?? null,
                            is_array($class->course_codes) ? $class->course_codes : []
                        ))));

                        $shsTrack = $class->shsTrack?->track_name;
                        $shsStrand = $class->shsStrand?->strand_name;
                        $summary = $summaries[(int) $class->id] ?? null;

                        return [
                            'id' => $class->id,
                            'record_title' => $class->record_title,
                            'subject_code' => $subject?->code ?? $class->subject_code ?? 'N/A',
                            'subject_title' => $subject?->title ?? 'N/A',
                            'section' => $class->section ?? 'N/A',
                            'school_year' => $class->school_year ?? 'N/A',
                            'semester' => $class->semester ?? 'N/A',
                            'classification' => $class->classification ?? 'college',
                            'delivery_mode' => $class->delivery_mode ?? Classes::DELIVERY_FACE_TO_FACE,
                            'delivery_summary' => $class->deliverySummary(),
                            'academic_year' => $class->academic_year,
                            'grade_level' => $class->grade_level,
                            'course_abbreviations' => $courseAbbreviations === [] ? null : $courseAbbreviations,
                            'shs_track' => $shsTrack,
                            'shs_strand' => $shsStrand,
                            'faculty' => $class->faculty?->full_name ?? 'TBA',
                            'room' => $summary['room'] ?? $class->Room?->name,
                            'room_capacity' => $summary['room_capacity'] ?? $class->Room?->capacity,
                            'schedules' => $summary['schedules'] ?? [],
                            'schedule_health' => $summary['schedule_health'] ?? null,
                            'waitlist_count' => $summary['waitlist_count'] ?? 0,
                            'schedule_revision' => $summary['schedule_revision'] ?? '',
                            'students_count' => (int) ($class->class_enrollments_count ?? 0),
                            'maximum_slots' => (int) ($class->maximum_slots ?? 0),
                            'deleted_at' => $class->deleted_at?->toIso8601String(),
                            'filament' => [
                                'view_url' => "{$filamentClassIndexUrl}/{$class->id}",
                                'edit_url' => "{$filamentClassIndexUrl}/{$class->id}/edit",
                            ],
                        ];
                    })
                    ->values()
                    ->all(),
                'meta' => [
                    'current_page' => $paginator->currentPage(),
                    'last_page' => $paginator->lastPage(),
                    'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(),
                    'from' => $paginator->firstItem() ?? 0,
                    'to' => $paginator->lastItem() ?? 0,
                    'total_students' => $totals['total_students'],
                    'total_capacity' => $totals['total_capacity'],
                    'queues' => $queues,
                    'classification_counts' => $classificationCounts,
                ],
            ];
        };

        $selectedId = $this->nullableInt($request->input('selected'));

        return Inertia::render('administrators/classes/index', [
            'user' => $this->getUserProps(),
            'filament' => [
                'classes' => [
                    'index_url' => route('filament.admin.resources.classes.index'),
                    'create_url' => route('filament.admin.resources.classes.create'),
                ],
            ],
            'classes' => fn () => $classesResolver(),
            'selected_class' => fn (): ?array => is_int($selectedId) ? $this->buildSelectedClassProps($selectedId, $courseCodeById, $courseLabelById) : null,
            'filters' => array_merge($filters, [
                'sort' => $sort,
                'direction' => $direction,
                'per_page' => $perPage,
            ]),
            'capabilities' => [
                'create' => $this->canManageClasses('Create:Classes'),
                'update' => $this->canManageClasses('Update:Classes'),
                'delete' => $this->canManageClasses('Delete:Classes'),
                'restore' => $this->canManageClasses('Restore:Classes'),
                'force_delete' => $this->canManageClasses('ForceDelete:Classes'),
            ],
            'options' => fn (): array => [
                'classifications' => [
                    ['value' => 'all', 'label' => 'All'],
                    ['value' => 'college', 'label' => 'College'],
                    ['value' => 'shs', 'label' => 'SHS'],
                ],
                'sections' => [
                    ['value' => 'A', 'label' => 'Section A'],
                    ['value' => 'B', 'label' => 'Section B'],
                    ['value' => 'C', 'label' => 'Section C'],
                    ['value' => 'D', 'label' => 'Section D'],
                ],
                'semesters' => [
                    ['value' => '1', 'label' => '1st Semester'],
                    ['value' => '2', 'label' => '2nd Semester'],
                    ['value' => 'summer', 'label' => 'Summer'],
                ],
                'grade_levels' => [
                    ['value' => 'Grade 11', 'label' => 'Grade 11'],
                    ['value' => 'Grade 12', 'label' => 'Grade 12'],
                ],
                'day_of_week' => [
                    ['value' => 'Monday', 'label' => 'Monday'],
                    ['value' => 'Tuesday', 'label' => 'Tuesday'],
                    ['value' => 'Wednesday', 'label' => 'Wednesday'],
                    ['value' => 'Thursday', 'label' => 'Thursday'],
                    ['value' => 'Friday', 'label' => 'Friday'],
                    ['value' => 'Saturday', 'label' => 'Saturday'],
                    ['value' => 'Sunday', 'label' => 'Sunday'],
                ],
                'courses' => $courses->map(fn (Course $course): array => [
                    'id' => $course->id,
                    'label' => $courseLabelById[$course->id],
                ])->values(),
                'faculties' => Faculty::query()
                    ->orderBy('last_name')
                    ->orderBy('first_name')
                    ->get(['id', 'first_name', 'last_name', 'middle_name'])
                    ->map(fn (Faculty $faculty): array => [
                        'id' => $faculty->id,
                        'label' => $faculty->full_name,
                    ])->values(),
                'rooms' => Room::query()
                    ->where('is_active', true)
                    ->orderBy('name')
                    ->get(['id', 'name', 'capacity', 'type'])
                    ->map(fn (Room $room): array => [
                        'id' => $room->id,
                        'label' => $room->name,
                        'capacity' => $room->capacity,
                        'type' => $room->type,
                    ])->values(),
                'shs_tracks' => ShsTrack::query()
                    ->orderBy('track_name')
                    ->get(['id', 'track_name'])
                    ->map(fn (ShsTrack $track): array => [
                        'id' => $track->id,
                        'label' => $track->track_name,
                    ])->values(),
            ],
            'defaults' => fn (): array => [
                'semester' => (string) $generalSettingsService->getCurrentSemester(),
                'school_year' => $generalSettingsService->getCurrentSchoolYearString(),
            ],
            'flash' => session('flash'),
        ]);
    }

    public function create(Request $request, GeneralSettingsService $generalSettingsService): Response
    {
        $this->authorizeClassAction('Create:Classes');

        $semester = (string) $generalSettingsService->getCurrentSemester();
        $schoolYear = $generalSettingsService->getCurrentSchoolYearString();
        $duplicateFromId = $request->integer('duplicate_from');
        $initialMode = $request->query('mode') === 'batch' ? 'batch' : 'single';
        $prefillData = null;
        $siblingSections = [];
        $blueprintSections = [];

        if ($duplicateFromId > 0) {
            $sourceClass = Classes::query()->with(['schedules.room'])->find($duplicateFromId);
            if ($sourceClass instanceof Classes) {
                $prefillData = $this->buildClassFormData($sourceClass);
                $siblingSections = $this->buildSiblingSections($sourceClass, includeSelf: true);
                $takenSections = collect($siblingSections)->pluck('section')->map(fn ($s) => mb_strtoupper((string) $s))->all();
                $nextSection = collect(range('A', 'Z'))
                    ->first(fn (string $letter): bool => ! in_array($letter, $takenSections, true)) ?? '';

                $prefillData['section'] = $nextSection;
                $prefillData['schedules'] = [];
                $prefillData['settings']['banner_image'] = null;
                $prefillData['settings']['banner_image_url'] = null;
            }
        } elseif ($request->integer('blueprint_class_id') > 0) {
            $sourceClass = Classes::query()->with(['schedules.room'])->find($request->integer('blueprint_class_id'));
            if ($sourceClass instanceof Classes) {
                $prefillData = $this->buildClassFormData($sourceClass);
                $siblingSections = $this->buildSiblingSections($sourceClass, includeSelf: true);
                $classes = Classes::query()->with('schedules.room')
                    ->whereIn('id', array_column($siblingSections, 'id'))
                    ->get()->keyBy('id');
                foreach ($siblingSections as $sibling) {
                    $siblingClass = $classes->get($sibling['id']);
                    if (! $siblingClass instanceof Classes) {
                        continue;
                    }
                    $form = $this->buildClassFormData($siblingClass);
                    $blueprintSections[] = [
                        'client_key' => 'existing-'.$siblingClass->id,
                        'class_id' => (int) $siblingClass->id,
                        'section' => $form['section'],
                        'faculty_id' => $form['faculty_id'] !== null ? (string) $form['faculty_id'] : null,
                        'maximum_slots' => $form['maximum_slots'],
                        'room_id' => $form['room_id'],
                        'schedules' => $form['schedules'],
                        'enrolled_count' => $sibling['students_count'],
                    ];
                }
            }
        }

        return Inertia::render('administrators/classes/create', [
            'user' => $this->getUserProps(),
            'filament' => [
                'classes' => [
                    'index_url' => route('filament.admin.resources.classes.index'),
                ],
            ],
            'options' => $this->buildClassFormOptions($prefillData['semester'] ?? $semester, $prefillData['school_year'] ?? $schoolYear),
            'defaults' => $prefillData ?? [
                'semester' => $semester,
                'school_year' => $schoolYear,
                'delivery_mode' => Classes::DELIVERY_FACE_TO_FACE,
            ],
            'initial_mode' => $initialMode,
            'duplicate_from' => $duplicateFromId > 0 ? $duplicateFromId : null,
            'sibling_sections' => $siblingSections,
            'blueprint_sections' => $blueprintSections,
            'flash' => session('flash'),
        ]);
    }

    public function edit(Classes $class): Response
    {
        $this->authorizeClassAction('Update:Classes');

        $class->loadMissing(['schedules.room']);

        $activeEnrolledCount = $class->class_enrollments()
            ->where(fn (Builder $q) => $this->applyActiveEnrollmentConstraint($q))
            ->count();
        $totalEnrolledCount = $class->class_enrollments()->count();
        $formData = $this->buildClassFormData($class);

        return Inertia::render('administrators/classes/create', [
            'user' => $this->getUserProps(),
            'filament' => [
                'classes' => [
                    'index_url' => route('filament.admin.resources.classes.index'),
                ],
            ],
            'options' => $this->buildClassFormOptions((string) ($class->semester ?? '1'), (string) ($class->school_year ?? '')),
            'defaults' => $formData,
            'mode' => 'edit',
            'class_id' => $class->id,
            'class_title' => $class->record_title,
            'sibling_sections' => $this->buildSiblingSections($class, includeSelf: true),
            'class_meta' => [
                'enrolled_count' => $totalEnrolledCount,
                'active_enrolled_count' => $activeEnrolledCount,
                'is_archived' => $class->trashed(),
                'banner_image_url' => $formData['settings']['banner_image_url'] ?? null,
                'schedule_revision' => app(SchedulingService::class)->revisionFor($class->schedules),
            ],
            'flash' => session('flash'),
        ]);
    }

    public function store(StoreClassRequest $request, ClassCurriculumPlacementService $curriculumPlacement): \Illuminate\Http\RedirectResponse
    {
        $this->authorizeClassAction('Create:Classes');

        $validated = $request->validated();
        $redirectTo = is_string($validated['redirect_to'] ?? null) ? $validated['redirect_to'] : 'index';
        $scheduleStatus = is_string($validated['schedule_status'] ?? null) ? $validated['schedule_status'] : null;
        $notifyEnrolled = array_key_exists('notify_enrolled', $validated) ? (bool) $validated['notify_enrolled'] : true;
        unset($validated['redirect_to'], $validated['schedule_status'], $validated['notify_enrolled']);

        $classification = $validated['classification'];

        /** @var array<string, mixed> $settings */
        $settings = (array) ($validated['settings'] ?? []);
        unset($validated['settings'], $settings['banner_image_url']);

        $settings = array_merge(Classes::getDefaultSettings(), $settings);

        if (isset($settings['banner_image']) && $settings['banner_image'] instanceof \Illuminate\Http\UploadedFile) {
            $settings['banner_image'] = $settings['banner_image']->storePublicly('class-banners', 'public');
        }

        $validated['settings'] = $settings;

        if ($classification === 'shs') {
            $validated['subject_code'] = $validated['subject_code_shs'];
            unset($validated['subject_code_shs']);

            $validated['course_codes'] = null;
            $validated['subject_ids'] = null;
            $validated['subject_id'] = null;
            $validated['academic_year'] = null;
        }

        if ($classification === 'college') {
            unset($validated['subject_code_shs'], $validated['shs_track_id'], $validated['shs_strand_id'], $validated['grade_level']);

            $validated = $curriculumPlacement->normalizeCollegeAttributes($validated);

            $subjectIds = Arr::wrap($validated['subject_ids'] ?? []);

            if ($subjectIds !== []) {
                $validated['subject_id'] = (int) $subjectIds[0];

                $codes = Subject::query()->whereIn('id', $subjectIds)->pluck('code')->filter()->unique()->values();
                $generatedCode = $codes->implode(', ');

                if (! isset($validated['subject_code']) || ! is_string($validated['subject_code']) || mb_trim($validated['subject_code']) === '') {
                    $validated['subject_code'] = $generatedCode;
                }
            }
        }

        $schedules = $this->normalizeIncomingSchedules(Arr::wrap($validated['schedules'] ?? []));
        unset($validated['schedules']);

        $class = DB::transaction(function () use ($validated, $schedules, $scheduleStatus, $notifyEnrolled): Classes {
            app(SchedulingService::class)->lockScheduleWrites();
            Validator::make([...$validated, 'schedules' => $schedules], [
                'schedules' => [new ScheduleOverlapRule],
            ])->validate();

            $class = Classes::query()->create($validated);

            app(SchedulingService::class)->syncClassSchedules(
                $class,
                $schedules,
                Auth::user() instanceof User ? Auth::user() : null,
                scheduleStatus: $scheduleStatus,
                notifyEnrolled: $notifyEnrolled,
            );

            return $class;
        });

        if ($redirectTo === 'show') {
            return redirect()->route('administrators.classes.show', $class)->with('flash', [
                'type' => 'success',
                'message' => 'Class created successfully.',
                'class_id' => $class->id,
            ]);
        }

        if ($redirectTo === 'duplicate') {
            return redirect()->route('administrators.classes.create', ['duplicate_from' => $class->id])->with('flash', [
                'type' => 'success',
                'message' => sprintf('%s created. Ready to configure next section.', $class->record_title),
                'class_id' => $class->id,
            ]);
        }

        return redirect()->route('administrators.classes.index')->with('flash', [
            'type' => 'success',
            'message' => 'Class created successfully.',
            'class_id' => $class->id,
        ]);
    }

    public function update(
        UpdateClassRequest $request,
        Classes $class,
        ClassCurriculumPlacementService $curriculumPlacement,
        ClassScheduleChangeNotificationService $scheduleNotifications,
    ): \Illuminate\Http\RedirectResponse {
        $this->authorizeClassAction('Update:Classes');

        $validated = $request->validated();
        $redirectTo = is_string($validated['redirect_to'] ?? null) ? $validated['redirect_to'] : 'index';
        $scheduleStatus = is_string($validated['schedule_status'] ?? null) ? $validated['schedule_status'] : null;
        $notifyEnrolled = array_key_exists('notify_enrolled', $validated) ? (bool) $validated['notify_enrolled'] : true;
        $allowBelowEnrolled = (bool) ($validated['allow_below_enrolled'] ?? false);
        unset($validated['redirect_to'], $validated['allow_below_enrolled'], $validated['schedule_status'], $validated['notify_enrolled']);
        $matchLegacySchedules = ! ($validated['schedules_match_by_id'] ?? false);
        unset($validated['schedules_match_by_id']);
        $previousClassification = $class->classification === 'shs' ? 'shs' : 'college';
        $classification = $validated['classification'] ?? $previousClassification;
        $classificationChanged = array_key_exists('classification', $validated) && $classification !== $previousClassification;

        /** @var array<string, mixed> $settings */
        $settings = (array) ($validated['settings'] ?? []);
        unset($validated['settings']);

        $shouldRemoveBanner = (bool) ($validated['remove_banner_image'] ?? false);
        unset($validated['remove_banner_image']);
        $uploadedBanner = ! $shouldRemoveBanner && ($settings['banner_image'] ?? null) instanceof \Illuminate\Http\UploadedFile
            ? $settings['banner_image'] : null;
        if ($shouldRemoveBanner) {
            $settings['banner_image'] = null;
        } else {
            // A new file is stored only after validation under the lock; null means preserve.
            unset($settings['banner_image']);
        }

        unset($settings['banner_image_url']);

        if ($classification === 'shs') {
            if (isset($validated['subject_code_shs'])) {
                $validated['subject_code'] = $validated['subject_code_shs'];
                unset($validated['subject_code_shs']);
            }

            if ($classificationChanged) {
                $validated['course_codes'] = null;
                $validated['subject_ids'] = null;
                $validated['subject_id'] = null;
                $validated['academic_year'] = null;
            }
        }

        if ($classification === 'college') {
            unset($validated['subject_code_shs']);

            if ($classificationChanged) {
                $validated['shs_track_id'] = null;
                $validated['shs_strand_id'] = null;
                $validated['grade_level'] = null;
            }

            $validated = $curriculumPlacement->normalizeCollegeAttributes($validated, $class);

            if (isset($validated['subject_ids']) && is_array($validated['subject_ids']) && $validated['subject_ids'] !== []) {
                $validated['subject_id'] = (int) $validated['subject_ids'][0];

                $codes = Subject::query()->whereIn('id', $validated['subject_ids'])->pluck('code')->filter()->unique()->values();
                $generatedCode = $codes->implode(', ');

                if (! isset($validated['subject_code']) || ! is_string($validated['subject_code']) || mb_trim($validated['subject_code']) === '') {
                    $validated['subject_code'] = $generatedCode;
                }
            }
        }

        $schedules = null;
        if (array_key_exists('schedules', $validated)) {
            $schedules = $this->normalizeIncomingSchedules(Arr::wrap($validated['schedules']));
            unset($validated['schedules']);
        }

        $applyDeliveryModeToSchedules = $request->boolean('apply_delivery_mode_to_schedules');
        unset($validated['apply_delivery_mode_to_schedules']);

        $actor = Auth::user() instanceof User ? Auth::user() : null;

        $newBannerPath = null;
        try {
            DB::transaction(function () use ($class, $validated, $schedules, $scheduleNotifications, $actor, $matchLegacySchedules, $applyDeliveryModeToSchedules, $scheduleStatus, $notifyEnrolled, $allowBelowEnrolled, $settings, $uploadedBanner, $shouldRemoveBanner, &$newBannerPath): void {
                app(SchedulingService::class)->lockScheduleWrites();
                $lockedClass = $class->newQuery()->whereKey($class->getKey())->lockForUpdate()->firstOrFail();
                if (! $allowBelowEnrolled && array_key_exists('maximum_slots', $validated) && (int) $validated['maximum_slots'] > 0) {
                    $requestedSlots = (int) $validated['maximum_slots'];
                    $currentSlots = (int) ($lockedClass->maximum_slots ?? 0);
                    if ($currentSlots === 0 || $requestedSlots < $currentSlots) {
                        $activeEnrolled = $lockedClass->class_enrollments()
                            ->where(fn (Builder $query) => $this->applyActiveEnrollmentConstraint($query))->count();
                        if ($requestedSlots < $activeEnrolled) {
                            throw ValidationException::withMessages([
                                'maximum_slots' => sprintf('Capacity cannot be reduced to %d while %d active students are currently enrolled.', $requestedSlots, $activeEnrolled),
                            ]);
                        }
                    }
                }

                $checksRetainedSchedules = $scheduleStatus === 'published'
                    || $applyDeliveryModeToSchedules
                    || Arr::hasAny($validated, ['delivery_mode', 'faculty_id', 'school_year', 'semester', 'classification', 'section', 'academic_year', 'course_codes', 'shs_strand_id', 'grade_level']);
                if (is_array($schedules) || $checksRetainedSchedules) {
                    $checkedSchedules = $schedules ?? $class->schedules()->get()->map(function (Schedule $schedule) use ($applyDeliveryModeToSchedules, $validated): array {
                        $mode = $applyDeliveryModeToSchedules && isset($validated['delivery_mode'])
                            ? ScheduleDelivery::canonicalize($validated['delivery_mode'])
                            : $schedule->delivery_mode;

                        return [
                            'id' => $schedule->id,
                            'day_of_week' => $schedule->day_of_week,
                            'start_time' => $schedule->start_time?->format('H:i:s'),
                            'end_time' => $schedule->end_time?->format('H:i:s'),
                            'room_id' => ScheduleDelivery::isOnline($mode) ? null : $schedule->room_id,
                            'delivery_mode' => $mode,
                        ];
                    })->all();
                    Validator::make([...$validated, 'schedules' => $checkedSchedules], [
                        'schedules' => [new ScheduleOverlapRule($class->id)],
                    ])->validate();
                }

                $currentBanner = $lockedClass->getSetting('banner_image');
                if ($uploadedBanner !== null) {
                    $newBannerPath = $uploadedBanner->storePublicly('class-banners', 'public');
                    if (! is_string($newBannerPath) || $newBannerPath === '') {
                        throw new RuntimeException('The class banner could not be stored.');
                    }
                    $settings['banner_image'] = $newBannerPath;
                }
                if ($settings !== []) {
                    $class->settings = array_merge((array) ($lockedClass->settings ?? []), $settings);
                }
                if (($shouldRemoveBanner || $uploadedBanner !== null) && is_string($currentBanner) && $currentBanner !== '') {
                    DB::afterCommit(static fn () => Storage::disk('public')->delete($currentBanner));
                }

                // Class delivery_mode is the default for new meetings; existing meetings only change
                // when schedules are submitted or apply_delivery_mode_to_schedules is explicitly requested.
                $oldSnapshot = $scheduleNotifications->snapshot($class);
                $class->fill($validated);
                $class->save();

                if (is_array($schedules)) {
                    app(SchedulingService::class)->syncClassSchedules(
                        $class,
                        $schedules,
                        $actor,
                        $oldSnapshot,
                        $matchLegacySchedules,
                        $scheduleStatus,
                        $notifyEnrolled,
                    );
                } else {
                    if ($applyDeliveryModeToSchedules && array_key_exists('delivery_mode', $validated)) {
                        $targetMode = ScheduleDelivery::canonicalize($validated['delivery_mode']);
                        $class->schedules()->get()->each(function (Schedule $schedule) use ($targetMode): void {
                            $schedule->delivery_mode = $targetMode;
                            if (ScheduleDelivery::isOnline($targetMode)) {
                                $schedule->room_id = null;
                            }
                            $schedule->save();
                        });
                        $class->unsetRelation('schedules');
                        $class->unsetRelation('Schedule');
                    }

                    if ($scheduleStatus !== null) {
                        $class->schedules()->update(['status' => $scheduleStatus]);
                        $class->unsetRelation('schedules');
                        $class->unsetRelation('Schedule');
                    }

                    if (($scheduleStatus ?? 'published') === 'published') {
                        $scheduleNotifications->notifyIfChanged($class, $oldSnapshot, $actor, $notifyEnrolled);
                    }
                }
            });
        } catch (Throwable $exception) {
            if (is_string($newBannerPath) && $newBannerPath !== '') {
                Storage::disk('public')->delete($newBannerPath);
            }
            throw $exception;
        }

        if ($redirectTo === 'show') {
            return redirect()->route('administrators.classes.show', $class)->with('flash', [
                'type' => 'success',
                'message' => 'Class updated successfully.',
                'class_id' => $class->id,
            ]);
        }

        if ($redirectTo === 'duplicate' && $this->canManageClasses('Create:Classes')) {
            return redirect()->route('administrators.classes.create', ['duplicate_from' => $class->id])->with('flash', [
                'type' => 'success',
                'message' => sprintf('%s updated. Ready to duplicate as next section.', $class->record_title),
                'class_id' => $class->id,
            ]);
        }

        return redirect()->route('administrators.classes.index')->with('flash', [
            'type' => 'success',
            'message' => 'Class updated successfully.',
            'class_id' => $class->id,
        ]);
    }

    public function updateSchedule(UpdateClassScheduleRequest $request, Classes $class, SchedulingService $scheduling): \Illuminate\Http\RedirectResponse
    {
        $this->authorizeClassAction('Update:Classes');

        $scheduling->replaceChecked(
            $class,
            $request->validated('schedules'),
            $request->validated('schedule_revision'),
            Auth::user() instanceof User ? Auth::user() : null,
            $request->validated('schedule_status'),
            $request->boolean('notify_enrolled', true),
        );

        return redirect()->route('administrators.classes.show', $class)->with('flash', [
            'type' => 'success',
            'message' => 'Class schedule updated successfully.',
        ]);
    }

    /**
     * Archive a class.
     *
     * The class is soft deleted, which also archives its schedule blocks and
     * its enrolments, so nothing is lost and the whole thing can be brought
     * back with restore().
     */
    public function destroy(Classes $class): \Illuminate\Http\RedirectResponse
    {
        $this->authorizeClassAction('Delete:Classes');

        $class->delete();

        return redirect()->route('administrators.classes.index')->with('flash', [
            'type' => 'success',
            'message' => 'Class archived. It can be restored from the deleted classes view.',
        ]);
    }

    /**
     * Bring an archived class back, together with its schedule and enrolments.
     */
    public function restore(int $class): \Illuminate\Http\RedirectResponse
    {
        $this->authorizeClassAction('Restore:Classes');

        $classModel = Classes::withTrashed()->findOrFail($class);

        if (! $classModel->trashed()) {
            return back()->with('flash', [
                'type' => 'warning',
                'message' => 'That class is not archived.',
            ]);
        }

        $classModel->restore();

        return redirect()->route('administrators.classes.index')->with('flash', [
            'type' => 'success',
            'message' => 'Class restored.',
        ]);
    }

    /**
     * Permanently remove a class and everything that depends on it.
     */
    public function forceDestroy(int $class): \Illuminate\Http\RedirectResponse
    {
        $this->authorizeClassAction('ForceDelete:Classes');

        $classModel = Classes::withTrashed()->findOrFail($class);

        $classModel->forceDelete();

        return redirect()->route('administrators.classes.index')->with('flash', [
            'type' => 'success',
            'message' => 'Class permanently deleted.',
        ]);
    }

    public function copy(CopyClassRequest $request, Classes $class): \Illuminate\Http\RedirectResponse
    {
        $this->authorizeClassAction('Create:Classes');

        $section = $request->validated()['section'];

        $copySchedules = $request->boolean('copy_schedules', false);
        $hadScheduleConflicts = false;

        $newClass = DB::transaction(function () use ($class, $section, $copySchedules, &$hadScheduleConflicts): Classes {
            if ($copySchedules) {
                app(SchedulingService::class)->lockScheduleWrites();
            }
            $newClass = $class->replicate(['section'])->unsetRelations();
            $newClass->section = $section;

            foreach ($newClass->getAttributes() as $key => $value) {
                if (str_ends_with((string) $key, '_count')) {
                    unset($newClass->{$key});
                }
            }

            $newClass->save();

            if ($copySchedules) {
                $copiedSchedules = app(SchedulingService::class)->copyClassSchedules($class, $newClass);

                if ($copiedSchedules->isNotEmpty()) {
                    $schoolYearVariants = $this->schoolYearVariants($newClass->school_year);
                    $termClasses = Classes::query()
                        ->where('semester', $newClass->semester)
                        ->when($schoolYearVariants !== [], fn (Builder $q) => $q->whereIn('school_year', $schoolYearVariants))
                        ->with(['schedules.room', 'Faculty'])
                        ->get();

                    $conflicts = app(ScheduleConflictService::class)->detectConflictsForClasses($termClasses, includeSectionConflicts: true);
                    $conflictedScheduleIds = [];

                    foreach ($conflicts as $conflict) {
                        $c1 = (int) ($conflict['class_1']['id'] ?? 0);
                        $c2 = (int) ($conflict['class_2']['id'] ?? 0);

                        if ($c1 === (int) $newClass->id && isset($conflict['schedule_1_id'])) {
                            $conflictedScheduleIds[] = (int) $conflict['schedule_1_id'];
                        }
                        if ($c2 === (int) $newClass->id && isset($conflict['schedule_2_id'])) {
                            $conflictedScheduleIds[] = (int) $conflict['schedule_2_id'];
                        }
                    }

                    $conflictedScheduleIds = array_values(array_unique($conflictedScheduleIds));

                    if ($conflictedScheduleIds !== []) {
                        $hadScheduleConflicts = true;
                        Schedule::query()
                            ->where('class_id', $newClass->id)
                            ->whereIn('id', $conflictedScheduleIds)
                            ->update(['status' => 'draft']);
                    }
                }
            }

            return $newClass;
        });

        if ($hadScheduleConflicts) {
            return redirect()->route('administrators.classes.index')->with('flash', [
                'type' => 'warning',
                'message' => 'Class copied, but conflicting schedules were saved as draft due to room or faculty overlap.',
                'class_id' => $newClass->id,
            ]);
        }

        return redirect()->route('administrators.classes.index')->with('flash', [
            'type' => 'success',
            'message' => $copySchedules
                ? 'Class and schedules copied successfully.'
                : 'Class copied successfully (schedules not copied).',
            'class_id' => $newClass->id,
        ]);
    }

    public function show(Request $request, Classes $class, ClassScheduleWorkspaceService $scheduleWorkspace): Response
    {
        $class->loadMissing([
            'Subject',
            'SubjectByCodeFallback',
            'ShsSubject',
            'faculty',
            'Room',
            'shsTrack',
            'shsStrand',
            'schedules.room',
        ]);

        $subjects = $class->subjects;
        $subjectsDisplay = $subjects->isEmpty()
            ? [($class->isShs() ? $class->ShsSubject?->title : $class->Subject?->title) ?? ($class->SubjectByCodeFallback?->title ?? 'N/A')]
            : $subjects->pluck('title')->filter()->unique()->values()->all();

        $primarySubject = $subjects->first();

        if (! $primarySubject instanceof Subject) {
            $primarySubject = $class->isShs()
                ? $class->ShsSubject
                : ($class->Subject ?: $class->SubjectByCodeFallback);
        }

        $semester = (string) ($class->semester ?? 'N/A');
        $semesterLabel = match ($semester) {
            '1', '1st' => '1st Semester',
            '2', '2nd' => '2nd Semester',
            'summer' => 'Summer',
            default => $semester,
        };

        $yearLevelLabel = null;
        if ($class->isShs()) {
            $yearLevelLabel = $class->grade_level;
        } elseif ($class->academic_year) {
            $yearLevelLabel = match ((string) $class->academic_year) {
                '1' => '1st Year',
                '2' => '2nd Year',
                '3' => '3rd Year',
                '4' => '4th Year',
                default => (string) $class->academic_year,
            };
        }

        $settings = array_merge(Classes::getDefaultSettings(), (array) ($class->settings ?? []));
        $bannerUrl = $this->resolveBannerUrl($settings['banner_image'] ?? null);
        $schoolYearVariants = $this->schoolYearVariants($class->school_year);

        // Fetch enrollments (including soft-deleted so the Show Soft-Deleted toggle works)
        $allClassEnrollments = $class->class_enrollments()
            ->withTrashed()
            ->with([
                'student:id,student_id,first_name,last_name,middle_name,course_id,academic_year,email',
                'student.course:id,code',
            ])
            ->latest('created_at')
            ->get();

        $nonTrashedEnrollments = $allClassEnrollments
            ->filter(fn (ClassEnrollment $enrollment): bool => ! $enrollment->trashed())
            ->values();
        $trashedEnrollments = $allClassEnrollments
            ->filter(fn (ClassEnrollment $enrollment): bool => $enrollment->trashed())
            ->values();
        $activeEnrollments = $nonTrashedEnrollments
            ->filter(fn (ClassEnrollment $enrollment): bool => $this->isActiveEnrollment($enrollment))
            ->values();

        // Map student IDs to their StudentEnrollment IDs for the current semester/school year
        $studentIds = $allClassEnrollments->pluck('student_id')->filter()->unique();
        $studentEnrollmentIds = \App\Models\StudentEnrollment::query()
            ->whereIn('student_id', $studentIds)
            ->where('semester', $class->semester)
            ->when($schoolYearVariants !== [], fn (Builder $q) => $q->whereIn('school_year', $schoolYearVariants))
            ->orderByDesc('id')
            ->get(['id', 'student_id', 'school_year'])
            ->groupBy('student_id')
            ->map(fn (Collection $rows) => ($rows->firstWhere('school_year', $class->school_year) ?? $rows->first())?->id);

        $currentSchedules = $class->schedules
            ->map(fn (Schedule $schedule): array => [
                'day_of_week' => (string) $schedule->day_of_week,
                'start_time' => $schedule->start_time?->format('H:i') ?? '',
                'end_time' => $schedule->end_time?->format('H:i') ?? '',
                'room_id' => $schedule->room_id !== null ? (int) $schedule->room_id : null,
                'delivery_mode' => $schedule->delivery_mode ?? Classes::DELIVERY_FACE_TO_FACE,
                'component_type' => $this->normalizeComponentType($schedule->component_type),
            ])
            ->values()
            ->all();

        $impact = $scheduleWorkspace->previewRescheduleImpact($class, $currentSchedules);
        $timetableMap = $impact['enrolled_timetable_map'] ?? [];

        $formatEnrollment = static function (ClassEnrollment $enrollment) use ($studentEnrollmentIds, $timetableMap): array {
            $studentTimetable = $timetableMap[(int) $enrollment->student_id] ?? [
                'status' => 'clear',
                'conflicts' => [],
            ];
            $isVerified = (bool) $enrollment->is_grades_verified;
            $isFinalized = (bool) $enrollment->is_grades_finalized;

            return [
                'id' => $enrollment->id,
                'student_enrollment_id' => $enrollment->student_id
                    ? ($studentEnrollmentIds[$enrollment->student_id] ?? null)
                    : null,
                'student' => $enrollment->student ? [
                    'id' => $enrollment->student->id,
                    'student_id' => $enrollment->student->student_id,
                    'name' => $enrollment->student->full_name,
                    'course' => $enrollment->student->course?->code,
                    'academic_year' => $enrollment->student->academic_year,
                    'email' => $enrollment->student->email,
                ] : null,
                'status' => $enrollment->status,
                'enrollment_status' => $enrollment->resolvedEnrollmentStatus(),
                'status_reason' => $enrollment->status_reason,
                'prelim_grade' => $enrollment->prelim_grade,
                'midterm_grade' => $enrollment->midterm_grade,
                'finals_grade' => $enrollment->finals_grade,
                'total_average' => $enrollment->total_average,
                'grade_symbol' => $enrollment->grade_symbol,
                'grade_outcome' => $enrollment->grade_outcome,
                'remarks' => $enrollment->remarks,
                'is_grades_verified' => $isVerified,
                'is_grades_finalized' => $isFinalized,
                'is_grade_protected' => $enrollment->isGradeProtected(),
                'deleted_at' => $enrollment->deleted_at?->toIso8601String(),
                'timetable_status' => ($studentTimetable['status'] ?? 'clear') === 'clash' ? 'clash' : 'ok',
                'timetable_conflicts' => array_values($studentTimetable['conflicts'] ?? []),
            ];
        };

        $includeDeleted = $request->boolean('include_deleted');
        $enrollments = ($includeDeleted ? $allClassEnrollments : $nonTrashedEnrollments)
            ->map($formatEnrollment)
            ->values();
        $formattedTrashedEnrollments = $trashedEnrollments
            ->map($formatEnrollment)
            ->values();

        // Fetch transferable classes (other non-archived sections of the same subject in the same term)
        $transferableClasses = Classes::query()
            ->where('id', '!=', $class->id)
            ->where('semester', $class->semester)
            ->when($schoolYearVariants !== [], fn (Builder $q) => $q->whereIn('school_year', $schoolYearVariants))
            ->when(
                $class->isShs(),
                fn (Builder $q) => $q->where('classification', 'shs'),
                fn (Builder $q) => $q->where(fn (Builder $sq) => $sq->whereNull('classification')->orWhere('classification', 'college')),
            )
            ->where(function (Builder $query) use ($class): void {
                if ($class->isShs()) {
                    if (filled($class->subject_code)) {
                        $query->where('subject_code', $class->subject_code);
                    } else {
                        $query->whereRaw('1 = 0');
                    }
                } elseif ($class->subject_id && filled($class->subject_code)) {
                    $query->where('subject_id', $class->subject_id)
                        ->orWhere('subject_code', $class->subject_code);
                } elseif ($class->subject_id) {
                    $query->where('subject_id', $class->subject_id);
                } elseif (filled($class->subject_code)) {
                    $query->where('subject_code', $class->subject_code);
                } else {
                    $query->whereRaw('1 = 0');
                }
            })
            ->withCount([
                'class_enrollments as active_students_count' => fn (Builder $q) => $this->applyActiveEnrollmentConstraint($q),
            ])
            ->orderBy('section')
            ->orderBy('id')
            ->get(['id', 'section', 'maximum_slots', 'subject_code'])
            ->map(function (Classes $c): array {
                $maxSlots = (int) ($c->maximum_slots ?? 0);
                $activeCount = (int) ($c->active_students_count ?? 0);
                $isFull = $maxSlots > 0 && $activeCount >= $maxSlots;
                $available = $maxSlots > 0 ? max(0, $maxSlots - $activeCount) : null;

                return [
                    'id' => $c->id,
                    'section' => $c->section,
                    'label' => $maxSlots > 0
                        ? sprintf(
                            '%s - Section %s (%d/%d)',
                            $c->subject_code ?? 'Unknown Subject',
                            $c->section ?? 'N/A',
                            $activeCount,
                            $maxSlots,
                        )
                        : sprintf(
                            '%s - Section %s (%d enrolled)',
                            $c->subject_code ?? 'Unknown Subject',
                            $c->section ?? 'N/A',
                            $activeCount,
                        ),
                    'is_full' => $isFull,
                    'available_slots' => $available,
                ];
            })
            ->values();

        // Format schedule for the view (grouped by day)
        $scheduleGrouped = $class->schedules->groupBy('day_of_week')->map(fn ($schedules) => $schedules->map(fn ($s): array => [
            'start_time' => $s->start_time?->format('H:i'),
            'end_time' => $s->end_time?->format('H:i'),
            'time_range' => ($s->start_time?->format('h:i A') ?? 'N/A').' - '.($s->end_time?->format('h:i A') ?? 'N/A'),
            'room' => [
                'id' => $s->room_id,
                'name' => $s->isOnline() ? 'Online' : ($s->room?->name ?? 'TBA'),
            ],
            'delivery_mode' => $s->delivery_mode,
        ]));

        $associatedCourses = null;
        if (! $class->isShs() && is_array($class->course_codes) && $class->course_codes !== []) {
            $courses = Course::whereIn('id', $class->course_codes)->get(['code', 'curriculum_year']);
            $associatedCourses = $courses->map(fn ($c) => $c->curriculum_year ? "$c->code ($c->curriculum_year)" : $c->code)->implode(', ');
        } elseif ($class->isShs()) {
            $associatedCourses = $class->formatted_track_strand;
        }

        $policyFlags = [
            'enable_announcements' => (bool) ($settings['enable_announcements'] ?? true),
            'enable_grade_visibility' => (bool) ($settings['enable_grade_visibility'] ?? true),
            'enable_attendance_tracking' => (bool) ($settings['enable_attendance_tracking'] ?? false),
            'enable_online_resources' => (bool) ($settings['enable_online_resources'] ?? true),
            'allow_late_submissions' => (bool) ($settings['allow_late_submissions'] ?? false),
            'enable_discussion_board' => (bool) ($settings['enable_discussion_board'] ?? false),
            'notify_students_on_schedule_changes' => (bool) ($settings['notify_students_on_schedule_changes'] ?? true),
        ];

        $dossier = $this->buildDossierProps(
            $class,
            $activeEnrollments,
            $scheduleWorkspace,
            $impact,
            $schoolYearVariants,
        );

        return Inertia::render('administrators/classes/show', [
            'user' => $this->getUserProps(),
            'class' => [
                'id' => $class->id,
                'record_title' => $class->record_title,
                'classification' => $class->classification ?? 'college',
                'subjects' => $subjectsDisplay,
                'associated_courses' => $associatedCourses,
                'subject_code' => $primarySubject?->code ?? $class->subject_code ?? 'N/A',
                'subject_title' => $primarySubject?->title ?? 'N/A',
                'section' => $class->section ?? 'N/A',
                'year_level' => $yearLevelLabel,
                'semester' => $semesterLabel,
                'school_year' => $class->school_year ?? 'N/A',
                'start_date' => $class->start_date?->format('Y-m-d'),
                'students_count' => $activeEnrollments->count(),
                'maximum_slots' => (int) ($class->maximum_slots ?? 0),
                'banner_image_url' => $bannerUrl,
                'faculty_id' => $class->faculty_id,
                'faculty' => $class->faculty ? [
                    'id' => $class->faculty->id,
                    'name' => $class->faculty->full_name,
                    'email' => $class->faculty->email,
                    'avatar_url' => $class->faculty->avatar_url,
                ] : null,
                'schedule' => $scheduleGrouped,
                'settings' => [
                    'background_color' => $settings['background_color'] ?? null,
                    'accent_color' => $settings['accent_color'] ?? null,
                    'theme' => $settings['theme'] ?? null,
                    'banner_image_url' => $bannerUrl,
                    ...$policyFlags,
                    'features' => $policyFlags,
                    'custom' => (array) ($settings['custom'] ?? []),
                ],
                'filament' => [
                    'view_url' => route('filament.admin.resources.classes.view', $class),
                    'edit_url' => route('filament.admin.resources.classes.edit', $class),
                ],
                'deleted_at' => $class->deleted_at?->toIso8601String(),
                'delivery_mode' => $class->delivery_mode ?? Classes::DELIVERY_FACE_TO_FACE,
                'delivery_summary' => $class->deliverySummary(),
                'is_archived' => $class->trashed(),
            ],
            'capabilities' => [
                'create' => $this->canManageClasses('Create:Classes'),
                'update' => $this->canManageClasses('Update:Classes'),
                'delete' => $this->canManageClasses('Delete:Classes'),
                'restore' => $this->canManageClasses('Restore:Classes'),
                'force_delete' => $this->canManageClasses('ForceDelete:Classes'),
            ],
            'enrollments' => $enrollments,
            'trashed_enrollments' => $formattedTrashedEnrollments,
            'raw_schedules' => $class->schedules
                ->sortBy([
                    fn ($schedule) => $schedule->day_of_week,
                    fn ($schedule) => $schedule->start_time,
                ])
                ->values()
                ->map(fn ($schedule): array => [
                    'id' => $schedule->id,
                    'day_of_week' => $schedule->day_of_week,
                    'start_time' => $schedule->start_time?->format('H:i'),
                    'end_time' => $schedule->end_time?->format('H:i'),
                    'room_id' => $schedule->room_id,
                    'room_name' => $schedule->room?->name,
                    'delivery_mode' => $schedule->delivery_mode,
                    'component_type' => $this->normalizeComponentType($schedule->component_type),
                    'pattern_group_id' => $schedule->pattern_group_id,
                    'status' => $schedule->status,
                    'subject_id' => $schedule->subject_id,
                    'temp_id' => (string) $schedule->id, // Ensure temp_id exists for keying
                ]),
            'schedule_revision' => app(SchedulingService::class)->revisionFor($class->schedules),
            'transferable_classes' => $transferableClasses,
            'rooms' => Room::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'capacity', 'type'])
                ->map(fn (Room $room): array => [
                    'id' => $room->id,
                    'label' => $room->name,
                    'capacity' => $room->capacity !== null ? (int) $room->capacity : null,
                    'type' => $room->type,
                ])->values(),
            'faculties' => $this->buildFacultyOptions((string) ($class->semester ?? '1'), (string) ($class->school_year ?? '')),
            'dossier' => $dossier,
            'flash' => session('flash'),
        ]);
    }

    public function subjectOptions(Request $request): JsonResponse
    {
        $courseIds = $request->input('course_ids');

        if (! is_array($courseIds) || $courseIds === []) {
            return response()->json(['data' => []]);
        }

        $availableCourseIds = Course::query()
            ->whereIn('id', array_map(intval(...), $courseIds))
            ->pluck('id');

        $subjects = Subject::query()
            ->with('course:id,code,curriculum_year')
            ->whereIn('course_id', $availableCourseIds)
            ->orderBy('code')
            ->get(['id', 'code', 'title', 'course_id', 'academic_year', 'semester', 'lecture', 'laboratory', 'units']);

        return response()->json([
            'data' => $subjects->map(fn (Subject $subject): array => [
                'id' => $subject->id,
                'label' => sprintf(
                    '%s - %s (%s)',
                    $subject->code,
                    $subject->title,
                    $subject->course?->curriculum_year
                        ? sprintf('%s %s', $subject->course->code, $subject->course->curriculum_year)
                        : ($subject->course?->code ?? 'No Course'),
                ),
                'code' => $subject->code,
                'title' => $subject->title,
                'course_id' => (int) $subject->course_id,
                'course_code' => $subject->course?->code,
                'academic_year' => $subject->academic_year ? (int) $subject->academic_year : null,
                'semester' => $subject->semester ? (string) $subject->semester : null,
                'lecture' => $subject->lecture !== null ? (int) $subject->lecture : null,
                'laboratory' => $subject->laboratory !== null ? (int) $subject->laboratory : null,
                'units' => $subject->units !== null ? (int) $subject->units : null,
            ])->values(),
        ]);
    }

    public function shsStrandOptions(Request $request): JsonResponse
    {
        $trackId = $this->nullableInt($request->input('track_id'));

        if (! is_int($trackId)) {
            return response()->json(['data' => []]);
        }

        $strands = ShsStrand::query()
            ->where('track_id', $trackId)
            ->orderBy('strand_name')
            ->get(['id', 'strand_name']);

        return response()->json([
            'data' => $strands->map(fn (ShsStrand $strand): array => [
                'id' => $strand->id,
                'label' => $strand->strand_name,
            ])->values(),
        ]);
    }

    public function shsSubjectOptions(Request $request): JsonResponse
    {
        $strandId = $this->nullableInt($request->input('strand_id'));

        if (! is_int($strandId)) {
            return response()->json(['data' => []]);
        }

        $subjects = StrandSubject::query()
            ->where('strand_id', $strandId)
            ->orderBy('code')
            ->get(['code', 'title', 'grade_year']);

        return response()->json([
            'data' => $subjects->map(fn (StrandSubject $subject): array => [
                'code' => $subject->code,
                'label' => $subject->grade_year
                    ? sprintf('%s - %s (%s)', $subject->code, $subject->title, $subject->grade_year)
                    : sprintf('%s - %s', $subject->code, $subject->title),
                'title' => $subject->title,
            ])->values(),
        ]);
    }

    public function moveStudent(Request $request, Classes $class): \Illuminate\Http\RedirectResponse
    {
        $this->authorizeClassAction('Update:Classes');

        $validated = $request->validate([
            'student_id' => ['required', 'integer', 'exists:students,id'],
            'target_class_id' => ['required', 'integer', 'exists:classes,id'],
            'notify_student' => ['boolean'],
        ]);

        $studentId = (int) $validated['student_id'];
        $targetClassId = (int) $validated['target_class_id'];
        $notifyStudent = (bool) ($validated['notify_student'] ?? true);

        // Find the enrollment record
        $enrollment = $class->class_enrollments()
            ->where('student_id', $studentId)
            ->firstOrFail();

        MoveStudentToSectionJob::dispatch(
            $enrollment->id,
            $targetClassId,
            Auth::id(),
            $notifyStudent
        );

        return back()->with('flash', [
            'type' => 'success',
            'message' => 'Student move queued successfully.',
        ]);
    }

    /**
     * Export student list as Excel or PDF.
     */
    public function exportStudentList(Request $request, Classes $class)
    {
        $format = $request->query('format', 'excel');

        if ($format === 'pdf') {
            return $this->downloadStudentListPdf($class);
        }

        // Default: Excel format (.xlsx) using Maatwebsite/Excel
        $fileName = sprintf(
            'student-list-%s-%s-%s.xlsx',
            Str::slug($class->subject_code ?? 'class'),
            Str::slug($class->section ?? 'section'),
            now()->format('Y-m-d')
        );

        return Excel::download(new StudentListExport($class), $fileName);
    }

    /**
     * Generate and download student list PDF immediately.
     */
    public function downloadStudentListPdf(Classes $class): \Symfony\Component\HttpFoundation\Response
    {
        $enrolledStudents = $class->class_enrollments()
            ->with([
                'student:id,student_id,first_name,last_name,middle_name,course_id,academic_year',
                'student.course:id,code',
            ])
            ->where('status', true)
            ->whereHas('student')
            ->get()
            ->sortBy([
                ['student.last_name', 'asc'],
                ['student.first_name', 'asc'],
            ]);

        $data = [
            'class' => $class,
            'students' => $enrolledStudents,
            'generated_at' => now()->format('F j, Y \a\t g:i A'),
            'total_students' => $enrolledStudents->count(),
        ];

        $downloadName = sprintf(
            'student-list-%s-%s-%s.pdf',
            Str::slug($class->subject_code ?? 'class'),
            Str::slug($class->section ?? 'section'),
            now()->format('Y-m-d_His')
        );

        $tempBasePath = tempnam(sys_get_temp_dir(), 'student_list_');

        if ($tempBasePath === false) {
            return response()->json([
                'error' => 'Failed to allocate temporary file for PDF generation.',
            ], 500);
        }

        $tempPath = $tempBasePath.'.pdf';
        rename($tempBasePath, $tempPath);

        try {
            $pdfService = app(\App\Services\PdfGenerationService::class);

            $pdfService->generatePdfFromView('exports.student-list-pdf', $data, $tempPath, [
                'headless' => true,
                'no-sandbox' => true,
                'disable-dev-shm-usage' => true,
                'disable-gpu' => true,
                'no-first-run' => true,
                'disable-background-timer-throttling' => true,
                'disable-backgrounding-occluded-windows' => true,
                'disable-renderer-backgrounding' => true,
                'print-to-pdf-no-header' => true,
                'run-all-compositor-stages-before-draw' => true,
                'disable-extensions' => true,
                'virtual-time-budget' => 10000,
            ]);

            return response()->download($tempPath, $downloadName)->deleteFileAfterSend(true);
        } catch (Exception $e) {
            \Illuminate\Support\Facades\Log::error('Failed to generate student list PDF (direct download)', [
                'class_id' => $class->id,
                'error' => $e->getMessage(),
            ]);

            if (file_exists($tempPath)) {
                unlink($tempPath);
            }

            return response()->json([
                'error' => 'Failed to generate PDF: '.$e->getMessage(),
            ], 500);
        }
    }

    public function compare(Request $request): JsonResponse
    {
        $classAId = $this->nullableInt($request->input('class_a'));
        $classBId = $this->nullableInt($request->input('class_b'));

        if (! $classAId || ! $classBId) {
            return response()->json(['error' => 'Both class_a and class_b are required.'], 422);
        }

        if ($classAId === $classBId) {
            return response()->json(['error' => 'Please select two different classes.'], 422);
        }

        $classA = Classes::with([
            'class_enrollments.student:id,student_id,first_name,last_name,course_id,academic_year',
            'class_enrollments.student.course:id,code',
        ])->find($classAId);

        $classB = Classes::with([
            'class_enrollments.student:id,student_id,first_name,last_name,course_id,academic_year',
            'class_enrollments.student.course:id,code',
        ])->find($classBId);

        if (! $classA || ! $classB) {
            return response()->json(['error' => 'One or both classes were not found.'], 404);
        }

        $studentsA = $classA->class_enrollments
            ->map(fn ($ce) => $ce->student)
            ->filter()
            ->keyBy('id');

        $studentsB = $classB->class_enrollments
            ->map(fn ($ce) => $ce->student)
            ->filter()
            ->keyBy('id');

        $studentIdsA = $studentsA->keys()->toArray();
        $studentIdsB = $studentsB->keys()->toArray();

        // Resolve StudentEnrollment IDs for all students in both classes
        $allStudentIds = array_unique(array_merge($studentIdsA, $studentIdsB));
        $studentEnrollmentIds = \App\Models\StudentEnrollment::whereIn('student_id', $allStudentIds)
            ->where('semester', $classA->semester)
            ->where('school_year', $classA->school_year)
            ->pluck('id', 'student_id');

        $formatStudent = fn ($student): array => [
            'id' => $student->id,
            'student_id' => $student->student_id,
            'name' => $student->full_name,
            'course' => $student->course?->code,
            'academic_year' => $student->academic_year,
            'student_enrollment_id' => $studentEnrollmentIds[$student->id] ?? null,
        ];

        $inBoth = $studentsA->only($studentIdsB)->values()->map($formatStudent);
        $onlyInA = $studentsA->except($studentIdsB)->values()->map($formatStudent);
        $onlyInB = $studentsB->except($studentIdsA)->values()->map($formatStudent);

        return response()->json([
            'class_a' => [
                'id' => $classA->id,
                'record_title' => $classA->record_title,
                'subject_code' => $classA->subject_code,
                'section' => $classA->section,
                'total_students' => $studentsA->count(),
            ],
            'class_b' => [
                'id' => $classB->id,
                'record_title' => $classB->record_title,
                'subject_code' => $classB->subject_code,
                'section' => $classB->section,
                'total_students' => $studentsB->count(),
            ],
            'comparison' => [
                'in_both' => $inBoth,
                'only_in_a' => $onlyInA,
                'only_in_b' => $onlyInB,
            ],
        ]);
    }

    /**
     * The class list query: every filter is applied by the database so the
     * page only ever ships the rows it displays.
     *
     * @param  array<string, mixed>  $filters
     */
    /**
     * @param  array<string, mixed>  $filters
     * @param  list<string>  $except  The filters to leave out of the query.
     */
    private function buildClassListQuery(array $filters, array $except = []): Builder
    {
        $query = Classes::query()
            ->currentAcademicPeriod()
            ->with([
                'Subject:id,code,title,lecture,laboratory,units',
                'SubjectByCodeFallback:id,code,title',
                'ShsSubject:id,code,title',
                'subjects:id,code,title',
                'faculty:id,first_name,last_name,middle_name',
                'Room:id,name,capacity',
                'schedules:id,class_id,day_of_week,start_time,end_time,room_id,delivery_mode,component_type,status',
                'schedules.room:id,name,capacity',
                'shsTrack:id,track_name',
                'shsStrand:id,strand_name',
            ])
            ->withCount('class_enrollments');

        if ($filters['trashed'] === 'only') {
            $query->onlyTrashed();
        } elseif ($filters['trashed'] === 'with') {
            $query->withTrashed();
        }

        $this->applyClassFilters($query, $filters, $except);

        return $query;
    }

    /**
     * Apply every list filter in SQL.
     *
     * @param  array<string, mixed>  $filters
     */
    /**
     * @param  array<string, mixed>  $filters
     * @param  list<string>  $except  The filters to leave out of the query.
     */
    private function applyClassFilters(Builder $query, array $filters, array $except = []): void
    {
        $skip = static fn (string $key): bool => in_array($key, $except, true);

        if (! $skip('classification') && $filters['classification'] === 'college') {
            $query->where(fn (Builder $classificationQuery) => $classificationQuery
                ->whereNull('classes.classification')
                ->orWhere('classes.classification', 'college'));
        } elseif (! $skip('classification') && $filters['classification'] === 'shs') {
            $query->where('classes.classification', 'shs');
        }

        if ($filters['room_id'] !== null) {
            $query->where('classes.room_id', $filters['room_id']);
        }

        if ($filters['faculty_id'] !== null) {
            $query->where('classes.faculty_id', $filters['faculty_id']);
        }

        if ($filters['semester'] !== null) {
            $query->where('classes.semester', $filters['semester']);
        }

        if ($filters['grade_level'] !== null) {
            $query->where('classes.grade_level', $filters['grade_level']);
        }

        if ($filters['shs_track_id'] !== null) {
            $query->where('classes.shs_track_id', $filters['shs_track_id']);
        }

        if ($filters['shs_strand_id'] !== null) {
            $query->where('classes.shs_strand_id', $filters['shs_strand_id']);
        }

        if ($filters['course_id'] !== null) {
            $courseId = $filters['course_id'];

            $query->where(function (Builder $courseQuery) use ($courseId): void {
                JsonArrayQuery::whereContainsInt($courseQuery, 'classes.course_codes', $courseId);

                JsonArrayQuery::whereElementMatches(
                    $courseQuery,
                    'classes.subject_ids',
                    'subject.course_id = ?',
                    [$courseId],
                    'or',
                    'JOIN subject ON subject.id = {element}',
                );
            });
        }

        if ($filters['academic_year'] !== null) {
            $academicYear = $filters['academic_year'];

            $query->where(function (Builder $yearQuery) use ($academicYear): void {
                $yearQuery->where('classes.academic_year', $academicYear);

                JsonArrayQuery::whereElementMatches(
                    $yearQuery,
                    'classes.subject_ids',
                    'subject.academic_year = ?',
                    [$academicYear],
                    'or',
                    'JOIN subject ON subject.id = {element}',
                );
            });
        }

        if ($filters['subject_code'] !== null) {
            $this->applySubjectCodeFilter($query, $filters['subject_code']);
        }

        $this->applyEnrollmentFilters($query, $filters);

        $this->applyQueueFilters($query, $filters, $except);

        if ($filters['search'] !== null) {
            $this->applyClassSearch($query, $filters['search']);
        }
    }

    /**
     * The work queues: the classes that still need someone to act on them.
     *
     * @param  array<string, mixed>  $filters
     */
    private function applyQueueFilters(Builder $query, array $filters, array $except = []): void
    {
        $skip = static fn (string $key): bool => in_array($key, $except, true);

        if (! $skip('needs_faculty') && $filters['needs_faculty'] === true) {
            $query->whereNull('classes.faculty_id');
        }

        if (! $skip('needs_schedule') && $filters['needs_schedule'] === true) {
            $query->whereDoesntHave('schedules');
        }

        if (! $skip('over_capacity') && $filters['over_capacity'] === true) {
            $count = $this->enrollmentCountSubquery();

            $query->whereRaw(
                sprintf('classes.maximum_slots > 0 and classes.maximum_slots < %s', $count['sql']),
                $count['bindings'],
            );
        }
    }

    /**
     * How many classes of each type are in the filtered set.
     *
     * @return array{college: int, shs: int}
     */
    private function classTypeCounts(Builder $query): array
    {
        return [
            'college' => (clone $query)
                ->cloneWithout(['limit', 'offset'])
                ->reorder()
                ->where(fn (Builder $collegeQuery) => $collegeQuery
                    ->whereNull('classes.classification')
                    ->orWhere('classes.classification', 'college'))
                ->count(),
            'shs' => (clone $query)->cloneWithout(['limit', 'offset'])->reorder()->where('classes.classification', 'shs')->count(),
        ];
    }

    /**
     * How many classes in the filtered set are waiting on something.
     *
     * @return array{unassigned_faculty: int, no_schedule: int, over_capacity: int}
     */
    private function classQueueCounts(Builder $query): array
    {
        $count = $this->enrollmentCountSubquery();

        return [
            'unassigned_faculty' => (clone $query)->cloneWithout(['limit', 'offset'])->reorder()->whereNull('classes.faculty_id')->count(),
            'no_schedule' => (clone $query)->cloneWithout(['limit', 'offset'])->reorder()->whereDoesntHave('schedules')->count(),
            'over_capacity' => (clone $query)
                ->cloneWithout(['limit', 'offset'])
                ->reorder()
                ->whereRaw(
                    sprintf('classes.maximum_slots > 0 and classes.maximum_slots < %s', $count['sql']),
                    $count['bindings'],
                )
                ->count(),
        ];
    }

    private function applySubjectCodeFilter(Builder $query, string $subjectCode): void
    {
        $like = '%'.$subjectCode.'%';
        $operator = SqlDialect::likeOperator($query);

        $query->where(function (Builder $codeQuery) use ($like, $operator): void {
            $codeQuery->where('classes.subject_code', $operator, $like);

            JsonArrayQuery::whereElementMatches(
                $codeQuery,
                'classes.subject_ids',
                sprintf('subject.code %1$s ? OR subject.title %1$s ?', $operator),
                [$like, $like],
                'or',
                'JOIN subject ON subject.id = {element}',
            );
        });
    }

    /**
     * Filter on how full a class is.
     *
     * @param  array<string, mixed>  $filters
     */
    private function applyEnrollmentFilters(Builder $query, array $filters): void
    {
        $count = $this->enrollmentCountSubquery();

        if ($filters['available_slots'] === true) {
            $query->whereRaw(
                sprintf('classes.maximum_slots > 0 and classes.maximum_slots > %s', $count['sql']),
                $count['bindings'],
            );
        }

        if ($filters['fully_enrolled'] === true) {
            $query->whereRaw(
                sprintf('classes.maximum_slots > 0 and classes.maximum_slots <= %s', $count['sql']),
                $count['bindings'],
            );
        } elseif ($filters['fully_enrolled'] === false) {
            $query->whereRaw(
                sprintf('(classes.maximum_slots <= 0 or classes.maximum_slots > %s)', $count['sql']),
                $count['bindings'],
            );
        }
    }

    /**
     * Match a search term against the identifying fields of a class.
     *
     * The course and subject codes live in JSON arrays, so they are matched
     * through a correlated subquery rather than a plain column comparison.
     */
    private function applyClassSearch(Builder $query, string $search): void
    {
        $term = mb_trim($search);

        if ($term === '') {
            return;
        }

        $like = '%'.$term.'%';
        $operator = SqlDialect::likeOperator($query);

        $query->where(function (Builder $searchQuery) use ($query, $like, $operator): void {
            $searchQuery
                ->where('classes.subject_code', $operator, $like)
                ->orWhere('classes.section', $operator, $like)
                ->orWhere('classes.school_year', $operator, $like)
                ->orWhere('classes.classification', $operator, $like)
                ->orWhere(DB::raw(SqlDialect::textCast($query, 'classes.semester')), $operator, $like)
                // The record title a user sees is "<subject codes> - <section>",
                // so that exact shape has to match as well.
                ->orWhereRaw(
                    sprintf("(classes.subject_code || ' - ' || classes.section) %s ?", $operator),
                    [$like],
                )
                ->orWhereHas('faculty', fn (Builder $facultyQuery) => $facultyQuery->where('full_name', $operator, $like))
                ->orWhereHas('shsTrack', fn (Builder $trackQuery) => $trackQuery->where('track_name', $operator, $like))
                ->orWhereHas('shsStrand', fn (Builder $strandQuery) => $strandQuery->where('strand_name', $operator, $like));

            JsonArrayQuery::whereElementMatches(
                $searchQuery,
                'classes.subject_ids',
                sprintf('subject.code %1$s ? OR subject.title %1$s ?', $operator),
                [$like, $like],
                'or',
                'JOIN subject ON subject.id = {element}',
            );

            JsonArrayQuery::whereElementMatches(
                $searchQuery,
                'classes.course_codes',
                sprintf('courses.code %s ?', $operator),
                [$like],
                'or',
                'JOIN courses ON courses.id = {element}',
            );
        });
    }

    /**
     * Order the list by a database column.
     */
    private function applyClassSort(Builder $query, string $sort, string $direction): void
    {
        $direction = $direction === 'asc' ? 'asc' : 'desc';

        match ($sort) {
            'record_title' => $query
                ->orderBy('classes.subject_code', $direction)
                ->orderBy('classes.section', $direction),
            'subject_code' => $query->orderBy('classes.subject_code', $direction),
            'students_count' => $query->orderBy('class_enrollments_count', $direction),
            'faculty' => $query
                ->withAggregate('Faculty', 'full_name')
                ->orderBy('faculty_full_name', $direction),
            'semester' => $query->orderBy('classes.semester', $direction),
            'period' => $query
                ->orderBy('classes.school_year', $direction)
                ->orderBy('classes.semester', $direction),
            'section' => $query->orderBy('classes.section', $direction),
            default => $query->orderBy('classes.created_at', $direction),
        };

        // A stable tiebreaker keeps the page order from shuffling between two
        // requests that return the same set of rows.
        $query->orderBy('classes.id', $direction);
    }

    /**
     * Totals for the whole filtered set, not just the page on screen.
     *
     * @return array{total_students: int, total_capacity: int}
     */
    private function classListTotals(Builder $query): array
    {
        $count = $this->enrollmentCountSubquery();

        // paginate() leaves the limit and the offset on the builder, so they
        // have to come off again: the totals cover the whole filtered set, not
        // the page that was just fetched.
        $inner = (clone $query)->toBase()->cloneWithout(['limit', 'offset'])->reorder();
        $inner->select(DB::raw('classes.maximum_slots as maximum_slots'));
        $inner->selectRaw($count['sql'].' as enrollment_count', $count['bindings']);

        $totals = DB::query()
            ->fromSub($inner, 'filtered_classes')
            ->selectRaw('coalesce(sum(enrollment_count), 0) as total_students')
            ->selectRaw('coalesce(sum(maximum_slots), 0) as total_capacity')
            ->first();

        return [
            'total_students' => (int) ($totals->total_students ?? 0),
            'total_capacity' => (int) ($totals->total_capacity ?? 0),
        ];
    }

    /**
     * A correlated subquery that counts a class' live enrolments.
     *
     * The count is built from the relation so the tenant scope and the
     * soft-delete rule are exactly the ones the withCount() alias on the row
     * payload uses. The bindings come back separately because the fragment is
     * interpolated into whereRaw() calls.
     *
     * @return array{sql: string, bindings: array<int, mixed>}
     */
    private function enrollmentCountSubquery(): array
    {
        // toBase() applies the relation's global scopes exactly once. Calling
        // applyScopes() and then toSql() would apply them twice.
        $scoped = (new ClassEnrollment())
            ->selectRaw('count(*)')
            ->whereColumn('class_enrollments.class_id', 'classes.id')
            ->toBase();

        return [
            'sql' => '('.$scoped->toSql().')',
            'bindings' => $scoped->getBindings(),
        ];
    }

    /**
     * Authorise a class management action.
     *
     * Every mutating action on a class needs the matching Shield permission —
     * the permissions that gate the Filament resource — so a user who can
     * reach the administrator portal but may not manage classes cannot delete
     * one.
     *
     * The administrator role holds every permission, but relying on the role
     * seeder to have granted them would lock the portal out of class
     * management on an install whose roles were seeded before the classes
     * policy existed. The role is therefore allowed explicitly.
     */
    private function authorizeClassAction(string $permission): void
    {
        abort_unless($this->canManageClasses($permission), 403);
    }

    /**
     * Whether the signed in user may perform a class management action.
     */
    private function canManageClasses(string $permission): bool
    {
        return $this->userHasAnyPermission(Auth::user(), $permission, [UserRole::Admin]);
    }

    /**
     * The page size the list should use.
     */
    private function resolveClassPerPage(mixed $perPage): int
    {
        $perPage = (int) $perPage;
        $perPage = in_array($perPage, self::CLASS_PAGE_SIZES, true) ? $perPage : 20;

        return ApiPagination::perPage($perPage);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildClassFormData(Classes $class): array
    {
        $settings = array_merge(Classes::getDefaultSettings(), (array) ($class->settings ?? []));
        $classification = $class->classification === 'shs' ? 'shs' : 'college';

        $subjectIds = Arr::wrap($class->subject_ids ?: ($class->subject_id ? [$class->subject_id] : []));
        $bannerUrl = $this->resolveBannerUrl($settings['banner_image'] ?? null);

        return [
            'classification' => $classification,
            'course_codes' => array_map(intval(...), Arr::wrap($class->course_codes ?? [])),
            'subject_ids' => array_map(intval(...), $subjectIds),
            'subject_code' => $classification === 'college' ? (string) ($class->subject_code ?? '') : '',
            'academic_year' => (int) ($class->academic_year ?? 1),
            'shs_track_id' => $class->shs_track_id,
            'shs_strand_id' => $class->shs_strand_id,
            'subject_code_shs' => $classification === 'shs' ? (string) ($class->subject_code ?? '') : '',
            'grade_level' => (string) ($class->grade_level ?? 'Grade 11'),
            'faculty_id' => $class->faculty_id,
            'semester' => (string) ($class->semester ?? '1'),
            'school_year' => (string) ($class->school_year ?? ''),
            'start_date' => $class->start_date?->format('Y-m-d'),
            'section' => (string) ($class->section ?? 'A'),
            'delivery_mode' => $class->delivery_mode ?? Classes::DELIVERY_FACE_TO_FACE,
            'delivery_summary' => $class->deliverySummary(),
            'room_id' => $class->room_id !== null ? (int) $class->room_id : null,
            'maximum_slots' => (int) ($class->maximum_slots ?? 40),
            'schedules' => $class->schedules
                ->sortBy([
                    fn (Schedule $schedule) => $schedule->day_of_week,
                    fn (Schedule $schedule) => $schedule->start_time,
                ])
                ->values()
                ->map(fn (Schedule $schedule): array => [
                    'id' => $schedule->id,
                    'day_of_week' => $schedule->day_of_week,
                    'start_time' => $schedule->start_time?->format('H:i') ?? '08:00',
                    'end_time' => $schedule->end_time?->format('H:i') ?? '09:00',
                    'room_id' => $schedule->room_id,
                    'room_name' => $schedule->room?->name,
                    'delivery_mode' => $schedule->delivery_mode,
                    'component_type' => $this->normalizeComponentType($schedule->component_type),
                    'pattern_group_id' => $schedule->pattern_group_id,
                    'status' => $schedule->status === 'draft' ? 'draft' : 'published',
                    'subject_id' => $schedule->subject_id,
                ])
                ->all(),
            'settings' => [
                'background_color' => (string) ($settings['background_color'] ?? '#ffffff'),
                'accent_color' => (string) ($settings['accent_color'] ?? '#3b82f6'),
                'theme' => (string) ($settings['theme'] ?? 'default'),
                'enable_announcements' => (bool) ($settings['enable_announcements'] ?? true),
                'enable_grade_visibility' => (bool) ($settings['enable_grade_visibility'] ?? true),
                'enable_attendance_tracking' => (bool) ($settings['enable_attendance_tracking'] ?? false),
                'enable_online_resources' => (bool) ($settings['enable_online_resources'] ?? true),
                'allow_late_submissions' => (bool) ($settings['allow_late_submissions'] ?? false),
                'enable_discussion_board' => (bool) ($settings['enable_discussion_board'] ?? false),
                'notify_students_on_schedule_changes' => (bool) ($settings['notify_students_on_schedule_changes'] ?? true),
                'custom' => (array) ($settings['custom'] ?? []),
                'banner_image' => null,
                'banner_image_url' => $bannerUrl,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildClassFormOptions(?string $semester = null, ?string $schoolYear = null): array
    {
        $generalSettings = app(GeneralSettingsService::class);
        $resolvedSemester = filled($semester) ? $semester : (string) $generalSettings->getCurrentSemester();
        $resolvedSchoolYear = filled($schoolYear) ? $schoolYear : $generalSettings->getCurrentSchoolYearString();
        $schedulingPolicy = $generalSettings->getSchedulingConfig();

        $courses = Course::query()
            ->where('is_active', true)
            ->orderBy('code')
            ->orderBy('curriculum_year')
            ->get(['id', 'code', 'curriculum_year']);

        $courseLabelById = $courses
            ->mapWithKeys(fn (Course $course): array => [
                $course->id => $course->curriculum_year
                    ? sprintf('%s (%s)', $course->code, $course->curriculum_year)
                    : $course->code,
            ])
            ->all();

        return [
            'classifications' => [
                ['value' => 'college', 'label' => 'College'],
                ['value' => 'shs', 'label' => 'Senior High School (SHS)'],
            ],
            'delivery_modes' => ScheduleDelivery::options(),
            'scheduling_policy' => $schedulingPolicy,
            'sections' => [
                ['value' => 'A', 'label' => 'Section A'],
                ['value' => 'B', 'label' => 'Section B'],
                ['value' => 'C', 'label' => 'Section C'],
                ['value' => 'D', 'label' => 'Section D'],
            ],
            'semesters' => [
                ['value' => '1', 'label' => '1st Semester'],
                ['value' => '2', 'label' => '2nd Semester'],
                ['value' => 'summer', 'label' => 'Summer'],
            ],
            'grade_levels' => [
                ['value' => 'Grade 11', 'label' => 'Grade 11'],
                ['value' => 'Grade 12', 'label' => 'Grade 12'],
            ],
            'academic_years' => [
                ['value' => '1', 'label' => '1st Year'],
                ['value' => '2', 'label' => '2nd Year'],
                ['value' => '3', 'label' => '3rd Year'],
                ['value' => '4', 'label' => '4th Year'],
            ],
            'day_of_week' => array_map(
                static fn (string $day): array => ['value' => $day, 'label' => $day],
                ScheduleDelivery::allowedDays($schedulingPolicy),
            ),
            'courses' => $courses->map(fn (Course $course): array => [
                'id' => $course->id,
                'label' => $courseLabelById[$course->id],
            ])->values()->all(),
            'faculties' => $this->buildFacultyOptions($resolvedSemester, $resolvedSchoolYear),
            'rooms' => Room::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'capacity', 'type'])
                ->map(fn (Room $room): array => [
                    'id' => $room->id,
                    'label' => $room->name,
                    'capacity' => $room->capacity !== null ? (int) $room->capacity : null,
                    'type' => $room->type,
                ])->values()->all(),
            'shs_tracks' => ShsTrack::query()
                ->orderBy('track_name')
                ->get(['id', 'track_name'])
                ->map(fn (ShsTrack $track): array => [
                    'id' => $track->id,
                    'label' => $track->track_name,
                ])->values()->all(),
        ];
    }

    /**
     * @return list<array{id: int, section: string, record_title: string, faculty: string|null, students_count: int, maximum_slots: int, is_current: bool}>
     */
    private function buildSiblingSections(Classes $class, bool $includeSelf = false): array
    {
        $schoolYearVariants = $this->schoolYearVariants($class->school_year);

        $query = Classes::query()
            ->where('semester', $class->semester)
            ->when($schoolYearVariants !== [], fn (Builder $q) => $q->whereIn('school_year', $schoolYearVariants))
            ->when(
                $class->isShs(),
                fn (Builder $q) => $q->where('classification', 'shs'),
                fn (Builder $q) => $q->where(fn (Builder $sq) => $sq->whereNull('classification')->orWhere('classification', 'college')),
            )
            ->when(! $includeSelf, fn (Builder $q) => $q->where('id', '!=', $class->id))
            ->where(function (Builder $subjectQuery) use ($class): void {
                if ($class->isShs()) {
                    if (filled($class->subject_code)) {
                        $subjectQuery->where('subject_code', $class->subject_code);
                    } else {
                        $subjectQuery->whereKey($class->id);
                    }
                } elseif ($class->subject_id && filled($class->subject_code)) {
                    $subjectQuery->where('subject_id', $class->subject_id)
                        ->orWhere('subject_code', $class->subject_code);
                } elseif ($class->subject_id) {
                    $subjectQuery->where('subject_id', $class->subject_id);
                } elseif (filled($class->subject_code)) {
                    $subjectQuery->where('subject_code', $class->subject_code);
                } else {
                    $subjectQuery->whereKey($class->id);
                }
            })
            ->with([
                'faculty:id,first_name,last_name,middle_name',
                'subjects:id,code',
            ])
            ->withCount([
                'class_enrollments as active_students_count' => fn (Builder $q) => $this->applyActiveEnrollmentConstraint($q),
            ])
            ->orderBy('section')
            ->orderBy('id');

        return $query
            ->get(['id', 'subject_code', 'subject_ids', 'section', 'faculty_id', 'maximum_slots', 'school_year', 'semester', 'classification'])
            ->map(fn (Classes $sibling): array => [
                'id' => (int) $sibling->id,
                'section' => (string) ($sibling->section ?? 'N/A'),
                'record_title' => $sibling->record_title,
                'faculty' => $sibling->faculty?->full_name,
                'students_count' => (int) ($sibling->active_students_count ?? 0),
                'maximum_slots' => (int) ($sibling->maximum_slots ?? 0),
                'is_current' => (int) $sibling->id === (int) $class->id,
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array{id: string, label: string, load_hours: float, max_load_hours: int}>
     */
    private function buildFacultyOptions(?string $semester = null, ?string $schoolYear = null): array
    {
        $generalSettings = app(GeneralSettingsService::class);
        $resolvedSemester = filled($semester) ? $semester : (string) $generalSettings->getCurrentSemester();
        $resolvedSchoolYear = filled($schoolYear) ? $schoolYear : $generalSettings->getCurrentSchoolYearString();
        $schoolYearVariants = $this->schoolYearVariants($resolvedSchoolYear);

        $facultyLoadMinutes = [];
        $termClassFacultyMap = Classes::query()
            ->whereNotNull('faculty_id')
            ->where('semester', $resolvedSemester)
            ->when($schoolYearVariants !== [], fn (Builder $q) => $q->whereIn('school_year', $schoolYearVariants))
            ->pluck('faculty_id', 'id');

        if ($termClassFacultyMap->isNotEmpty()) {
            $termSchedules = Schedule::query()
                ->whereIn('class_id', $termClassFacultyMap->keys())
                ->get(['class_id', 'start_time', 'end_time']);

            foreach ($termSchedules as $sched) {
                if (! $sched->start_time || ! $sched->end_time) {
                    continue;
                }

                $facultyId = (string) ($termClassFacultyMap[$sched->class_id] ?? '');
                if ($facultyId === '') {
                    continue;
                }

                $startMinutes = (int) $sched->start_time->format('H') * 60 + (int) $sched->start_time->format('i');
                $endMinutes = (int) $sched->end_time->format('H') * 60 + (int) $sched->end_time->format('i');

                if ($endMinutes > $startMinutes) {
                    $facultyLoadMinutes[$facultyId] = ($facultyLoadMinutes[$facultyId] ?? 0) + ($endMinutes - $startMinutes);
                }
            }
        }

        return Faculty::query()
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get(['id', 'first_name', 'last_name', 'middle_name'])
            ->map(fn (Faculty $faculty): array => [
                'id' => (string) $faculty->id,
                'label' => $faculty->full_name,
                'load_hours' => round(($facultyLoadMinutes[(string) $faculty->id] ?? 0) / 60, 1),
                'max_load_hours' => 24,
            ])
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, ClassEnrollment>  $activeEnrollments
     * @param  array<string, mixed>  $impact
     * @param  list<string>  $schoolYearVariants
     * @return array<string, mixed>
     */
    private function buildDossierProps(
        Classes $class,
        Collection $activeEnrollments,
        ClassScheduleWorkspaceService $scheduleWorkspace,
        array $impact,
        array $schoolYearVariants,
    ): array {
        $siblingSections = $this->buildSiblingSections($class, includeSelf: true);
        $siblingIds = array_values(array_unique(array_merge([(int) $class->id], array_column($siblingSections, 'id'))));

        $subjectIdsForClass = array_values(array_unique(array_filter(array_map(
            intval(...),
            array_merge(
                is_array($class->subject_ids) ? $class->subject_ids : [],
                $class->subject_id ? [$class->subject_id] : [],
            ),
        ))));

        // Active waitlist entries + per-sibling compatibility
        $dossierWaitlist = [];
        if (Schema::hasTable('enrollment_class_waitlists')) {
            $hasWaitingEntries = EnrollmentClassWaitlist::query()
                ->waiting()
                ->where('semester', $class->semester)
                ->when($schoolYearVariants !== [], fn (Builder $q) => $q->whereIn('school_year', $schoolYearVariants))
                ->where(function (Builder $q) use ($siblingIds, $subjectIdsForClass, $class): void {
                    if ($siblingIds !== []) {
                        $q->orWhereIn('preferred_class_id', $siblingIds);
                    }
                    if ($subjectIdsForClass !== []) {
                        $q->orWhereIn('subject_id', $subjectIdsForClass);
                    }
                    if (filled($class->subject_code)) {
                        $q->orWhereHas('subject', fn (Builder $sq) => $sq->where('code', $class->subject_code));
                    }
                })
                ->exists();

            if ($hasWaitingEntries) {
                $parallelWorkspace = $scheduleWorkspace->parallelSectionsWorkspace($class);
                $dossierWaitlist = array_map(
                    static fn (array $entry): array => [
                        'id' => (int) $entry['id'],
                        'position' => isset($entry['position']) ? (int) $entry['position'] : null,
                        'student_id' => (int) $entry['student_id'],
                        'student_number' => $entry['student_number'] ?? null,
                        'student_name' => (string) ($entry['student_name'] ?? 'Unknown Student'),
                        'queued_at' => $entry['created_at'] ?? null,
                        'compatibility' => $entry['section_compatibility'] ?? [],
                    ],
                    $parallelWorkspace['waitlist'] ?? [],
                );
            }
        }

        // Unassigned cohort count (students enrolled in this subject for this term without a class section)
        $unassignedCohort = 0;
        if ($subjectIdsForClass !== [] && Schema::hasTable('subject_enrollments')) {
            $unassignedCohort = (int) SubjectEnrollment::query()
                ->whereIn('subject_id', $subjectIdsForClass)
                ->whereNull('class_id')
                ->where('semester', $class->semester)
                ->when($schoolYearVariants !== [], fn (Builder $q) => $q->whereIn('school_year', $schoolYearVariants))
                ->count();
        }

        // Schedule health & schedule change history
        $summary = $scheduleWorkspace->summarizeForIndex(collect([$class]))[(int) $class->id] ?? null;
        $scheduleHealth = $summary['schedule_health'] ?? null;

        $dossierScheduleChanges = [];
        $totalScheduleChanges = 0;
        $pendingNotices = 0;

        if (Schema::hasTable('class_schedule_changes')) {
            $totalScheduleChanges = ClassScheduleChange::query()
                ->where('class_id', $class->id)
                ->count();

            $changesQuery = ClassScheduleChange::query()
                ->where('class_id', $class->id)
                ->with('changedBy:id,name')
                ->latest('created_at')
                ->limit(15);

            if (Schema::hasTable('class_schedule_change_student_notifications')) {
                $changesQuery->withCount([
                    'studentNotifications as notified_count' => fn (Builder $q) => $q->whereNotNull('notified_at'),
                    'studentNotifications as pending_count' => fn (Builder $q) => $q->whereNull('notified_at'),
                ]);

                $pendingNotices = ClassScheduleChangeStudentNotification::query()
                    ->pending()
                    ->whereHas('scheduleChange', fn (Builder $q) => $q->where('class_id', $class->id))
                    ->count();
            }

            $dossierScheduleChanges = $changesQuery
                ->get()
                ->map(fn (ClassScheduleChange $change): array => [
                    'id' => (int) $change->id,
                    'changed_at' => $change->created_at?->toIso8601String(),
                    'changed_by' => $change->changedBy?->name,
                    'summary' => $this->summarizeScheduleChange($change),
                    'notified_count' => (int) ($change->notified_count ?? 0),
                    'pending_count' => (int) ($change->pending_count ?? 0),
                ])
                ->values()
                ->all();
        }

        $conflictCount = count($impact['room_conflicts'] ?? []) + count($impact['faculty_conflicts'] ?? []);
        if ($conflictCount === 0 && (bool) ($scheduleHealth['has_conflict'] ?? false)) {
            $conflictCount = 1;
        }

        // Grades health across active enrolled students
        $gradesTotal = $activeEnrollments->count();
        $gradesVerified = 0;
        $gradesFinalized = 0;
        $gradesInProgress = 0;
        $gradesPending = 0;

        foreach ($activeEnrollments as $enrollment) {
            $isVerified = (bool) $enrollment->is_grades_verified;
            $isFinalized = (bool) $enrollment->is_grades_finalized;
            $hasAnyGrade = $enrollment->prelim_grade !== null
                || $enrollment->midterm_grade !== null
                || $enrollment->finals_grade !== null
                || $enrollment->total_average !== null
                || filled($enrollment->grade_symbol);

            if ($isVerified) {
                $gradesVerified++;
            }
            if ($isFinalized) {
                $gradesFinalized++;
            }
            if (! $isVerified && ! $isFinalized) {
                if ($hasAnyGrade) {
                    $gradesInProgress++;
                } else {
                    $gradesPending++;
                }
            }
        }

        // Attendance sessions & rate
        $sessionsHeld = 0;
        $sessionsExpected = null;
        $averageRate = null;
        $dossierAttendanceSessions = [];

        if (Schema::hasTable('class_attendance_sessions')) {
            $sessionsHeld = (int) $class->attendanceSessions()
                ->where('is_no_meeting', false)
                ->count();

            $recentSessions = $class->attendanceSessions()
                ->with(['records:id,class_attendance_session_id,status'])
                ->latestFirst()
                ->limit(20)
                ->get();

            $dossierAttendanceSessions = $recentSessions
                ->map(function (ClassAttendanceSession $session): array {
                    $counts = is_array($session->summary) ? $session->summary : [];

                    if ($counts === [] && $session->records->isNotEmpty()) {
                        $counts = $session->records
                            ->groupBy(fn (ClassAttendanceRecord $r): string => $r->status instanceof AttendanceStatus ? $r->status->value : (string) $r->status)
                            ->map(fn (Collection $group): int => $group->count())
                            ->all();
                    }

                    return [
                        'id' => (int) $session->id,
                        'date' => $session->session_date?->toDateString(),
                        'title' => $session->topic ?: ($session->is_no_meeting ? ($session->no_meeting_reason ?: 'No meeting') : null),
                        'present' => (int) ($counts['present'] ?? 0),
                        'absent' => (int) ($counts['absent'] ?? 0),
                        'late' => (int) ($counts['late'] ?? 0),
                        'excused' => (int) ($counts['excused'] ?? 0),
                        'is_locked' => (bool) $session->is_locked,
                    ];
                })
                ->values()
                ->all();

            if (Schema::hasTable('class_attendance_records')) {
                $statusTotals = ClassAttendanceRecord::query()
                    ->where('class_id', $class->id)
                    ->select('status', DB::raw('count(*) as total'))
                    ->groupBy('status')
                    ->pluck('total', 'status')
                    ->all();

                $totalMarked = array_sum(array_map(intval(...), $statusTotals));
                if ($totalMarked > 0) {
                    $attended = (int) ($statusTotals['present'] ?? 0) + (int) ($statusTotals['late'] ?? 0);
                    $averageRate = round(($attended / $totalMarked) * 100, 1);
                }
            }

            if ($class->start_date !== null && $class->schedules->isNotEmpty()) {
                $scheduleDays = $class->schedules->pluck('day_of_week')
                    ->map(fn ($day): string => mb_strtolower((string) $day))
                    ->countBy();
                $cursor = $class->start_date->copy()->startOfDay();
                $today = now()->startOfDay();
                if ($cursor->lte($today)) {
                    $expected = 0;
                    $safetyCap = 365;
                    while ($cursor->lte($today) && $safetyCap-- > 0) {
                        $expected += $scheduleDays->get(mb_strtolower($cursor->format('l')), 0);
                        $cursor->addDay();
                    }
                    $sessionsExpected = $expected;
                } else {
                    $sessionsExpected = 0;
                }
            }
        }

        // Class posts
        $postsCount = 0;
        $dossierPosts = [];

        if (Schema::hasTable('class_posts')) {
            $postsCount = (int) $class->classPosts()->count();
            $dossierPosts = $class->classPosts()
                ->with('assignedFaculty:id,first_name,last_name,middle_name')
                ->latest('created_at')
                ->limit(15)
                ->get(['id', 'class_id', 'title', 'type', 'created_at', 'assigned_faculty_id'])
                ->map(fn (ClassPost $post): array => [
                    'id' => (int) $post->id,
                    'title' => (string) ($post->title ?? 'Untitled'),
                    'type' => $post->type instanceof ClassPostType ? $post->type->value : (string) ($post->type ?? 'announcement'),
                    'created_at' => $post->created_at?->toIso8601String(),
                    'author' => $post->assignedFaculty?->full_name ?? $class->faculty?->full_name,
                ])
                ->values()
                ->all();
        }

        // Spatie Activity log for this class and its enrollments/posts
        $dossierActivity = [];
        if (Schema::hasTable('activity_log')) {
            $classId = (int) $class->id;
            $hasPostsTable = Schema::hasTable('class_posts');

            $activities = Activity::query()
                ->with('causer')
                ->where(function (Builder $query) use ($classId, $hasPostsTable): void {
                    $query->where(function (Builder $q) use ($classId): void {
                        $q->where('subject_type', Classes::class)
                            ->where('subject_id', $classId);
                    })
                        ->orWhere(function (Builder $q) use ($classId): void {
                            $q->where('subject_type', ClassEnrollment::class)
                                ->whereIn('subject_id', function ($subQuery) use ($classId): void {
                                    $subQuery->select('id')
                                        ->from('class_enrollments')
                                        ->where('class_id', $classId);
                                });
                        });

                    if ($hasPostsTable) {
                        $query->orWhere(function (Builder $q) use ($classId): void {
                            $q->where('subject_type', ClassPost::class)
                                ->whereIn('subject_id', function ($subQuery) use ($classId): void {
                                    $subQuery->select('id')
                                        ->from('class_posts')
                                        ->where('class_id', $classId);
                                });
                        });
                    }
                })
                ->latest('created_at')
                ->limit(30)
                ->get();

            $dossierActivity = $activities
                ->map(static function (Activity $activity): array {
                    $causer = $activity->causer;
                    $actor = null;
                    if ($causer !== null) {
                        $actor = is_string($causer->name ?? null)
                            ? $causer->name
                            : (is_string($causer->full_name ?? null) ? $causer->full_name : null);
                    }

                    return [
                        'id' => (int) $activity->id,
                        'at' => $activity->created_at?->toIso8601String(),
                        'actor' => $actor,
                        'description' => (string) $activity->description,
                        'log' => (string) ($activity->log_name ?? match ($activity->subject_type) {
                            ClassEnrollment::class => 'class_enrollments',
                            ClassPost::class => 'class_posts',
                            default => 'classes',
                        }),
                    ];
                })
                ->values()
                ->all();
        }

        $activityTimestamps = array_values(array_filter([
            $dossierActivity[0]['at'] ?? null,
            $dossierScheduleChanges[0]['changed_at'] ?? null,
            $dossierPosts[0]['created_at'] ?? null,
            $class->updated_at?->toIso8601String(),
        ]));
        rsort($activityTimestamps);
        $lastActivityAt = $activityTimestamps[0] ?? null;

        $activeCount = $activeEnrollments->count();
        $maxSlots = (int) ($class->maximum_slots ?? 0);

        return [
            'health' => [
                'seats' => [
                    'active' => $activeCount,
                    'max' => $maxSlots,
                    'over' => $maxSlots > 0 ? max(0, $activeCount - $maxSlots) : 0,
                    'waitlisted' => count($dossierWaitlist),
                    'unassigned_cohort' => $unassignedCohort,
                ],
                'schedule' => [
                    'scheduled_hours' => (float) ($scheduleHealth['scheduled_hours'] ?? 0.0),
                    'required_hours' => isset($scheduleHealth['required_hours']) ? (float) $scheduleHealth['required_hours'] : null,
                    'status' => $scheduleHealth['status'] ?? ($class->schedules->isEmpty() ? 'unscheduled' : 'published'),
                    'conflict_count' => $conflictCount,
                    'conflict_summary' => $scheduleHealth['conflict_summary'] ?? null,
                    'pending_notices' => (int) $pendingNotices,
                    'revision_label' => 'Rev '.($totalScheduleChanges + 1),
                ],
                'grades' => [
                    'total' => $gradesTotal,
                    'verified' => $gradesVerified,
                    'finalized' => $gradesFinalized,
                    'in_progress' => $gradesInProgress,
                    'pending' => $gradesPending,
                ],
                'attendance' => [
                    'average_rate' => $averageRate,
                    'sessions_held' => $sessionsHeld,
                    'sessions_expected' => $sessionsExpected,
                ],
                'posts' => [
                    'count' => $postsCount,
                ],
                'last_activity_at' => $lastActivityAt,
            ],
            'sibling_sections' => $siblingSections,
            'waitlist' => $dossierWaitlist,
            'schedule_changes' => $dossierScheduleChanges,
            'activity' => $dossierActivity,
            'attendance_sessions' => $dossierAttendanceSessions,
            'posts' => $dossierPosts,
        ];
    }

    private function applyActiveEnrollmentConstraint(Builder $query): void
    {
        $query
            ->where(fn (Builder $q) => $q->whereNull('status')->orWhere('status', true))
            ->where(fn (Builder $q) => $q->whereNull('enrollment_status')->orWhereNotIn('enrollment_status', [
                ClassEnrollment::STATUS_DROPPED,
                ClassEnrollment::STATUS_WITHDRAWN,
            ]));
    }

    private function isActiveEnrollment(ClassEnrollment $enrollment): bool
    {
        if ($enrollment->trashed()) {
            return false;
        }

        if ($enrollment->status === false) {
            return false;
        }

        return ! in_array($enrollment->enrollment_status, [
            ClassEnrollment::STATUS_DROPPED,
            ClassEnrollment::STATUS_WITHDRAWN,
        ], true);
    }

    /**
     * @return list<string>
     */
    private function schoolYearVariants(?string $schoolYear): array
    {
        $raw = is_string($schoolYear) ? mb_trim($schoolYear) : '';
        if ($raw === '') {
            return [];
        }

        $normalized = GeneralSettingsService::normalizeSchoolYear($raw);
        $compact = str_replace(' ', '', $normalized);

        return array_values(array_unique([$raw, $normalized, $compact]));
    }

    private function normalizeComponentType(mixed $type): string
    {
        if (! is_string($type)) {
            return 'lecture';
        }

        $normalized = mb_strtolower(mb_trim($type));

        return in_array($normalized, ['lab', 'laboratory'], true) ? 'lab' : 'lecture';
    }

    /**
     * @param  array<int, mixed>  $schedules
     * @return array<int, array<string, mixed>>
     */
    private function normalizeIncomingSchedules(array $schedules): array
    {
        $normalized = [];

        foreach ($schedules as $block) {
            if (! is_array($block)) {
                continue;
            }

            if (array_key_exists('component_type', $block) && is_string($block['component_type'])) {
                $block['component_type'] = $this->normalizeComponentType($block['component_type']);
            }

            $normalized[] = $block;
        }

        return $normalized;
    }

    private function summarizeScheduleChange(ClassScheduleChange $change): string
    {
        $old = is_array($change->old_schedule) ? $change->old_schedule : [];
        $new = is_array($change->new_schedule) ? $change->new_schedule : [];

        if ($new === []) {
            return 'Cleared all scheduled meetings';
        }

        $parts = [];
        foreach (array_slice($new, 0, 3) as $block) {
            if (! is_array($block)) {
                continue;
            }
            $day = mb_substr((string) ($block['day_of_week'] ?? ''), 0, 3);
            $start = (string) ($block['start_time'] ?? '');
            $end = (string) ($block['end_time'] ?? '');
            $room = is_string($block['room_name'] ?? null) && $block['room_name'] !== '' ? ' · '.$block['room_name'] : '';
            $parts[] = mb_trim(sprintf('%s %s-%s%s', $day, $start, $end, $room));
        }

        $extra = count($new) > 3 ? sprintf(' (+%d more)', count($new) - 3) : '';
        $prefix = $old === [] ? 'Scheduled: ' : 'Updated to ';

        return $prefix.implode(', ', $parts).$extra;
    }

    private function resolveBannerUrl(mixed $bannerPath): ?string
    {
        if (! is_string($bannerPath) || mb_trim($bannerPath) === '') {
            return null;
        }

        return str_starts_with($bannerPath, 'http')
            ? $bannerPath
            : Storage::disk('public')->url($bannerPath);
    }

    private function buildSelectedClassProps(int $classId, array $courseCodeById, array $courseLabelById): ?array
    {
        $class = Classes::withTrashed()
            ->withCount('class_enrollments')
            ->with([
                'faculty:id,first_name,last_name,middle_name,email',
                'Room:id,name',
                'subject:id,code,title',
                'subjectByCodeFallback:id,code,title',
                'shsSubject:code,title,grade_year,strand_id',
                'shsTrack:id,track_name',
                'shsStrand:id,strand_name',
                'schedules.room:id,name',
            ])
            ->find($classId);

        if (! $class) {
            return null;
        }

        $subjects = $class->subjects;

        $primarySubject = $subjects->first();
        if (! $primarySubject) {
            $primarySubject = $class->isShs()
                ? $class->ShsSubject
                : ($class->Subject ?: $class->SubjectByCodeFallback);
        }

        $enrollments = $class->class_enrollments()
            ->with(['student:id,student_id,first_name,last_name,course_id,academic_year', 'student.course:id,code'])
            ->latest('created_at')
            ->limit(250)
            ->get();

        $posts = $class->classPosts()
            ->latest('created_at')
            ->limit(20)
            ->get(['id', 'title', 'type', 'created_at']);

        $courseCodes = array_values(array_unique(array_filter(array_map(
            fn ($id) => $courseLabelById[(int) $id] ?? null,
            is_array($class->course_codes) ? $class->course_codes : []
        ))));

        $courseAbbreviations = array_values(array_unique(array_filter(array_map(
            fn ($id) => $courseCodeById[(int) $id] ?? null,
            is_array($class->course_codes) ? $class->course_codes : []
        ))));

        return [
            'id' => $class->id,
            'record_title' => $class->record_title,
            'classification' => $class->classification ?? 'college',
            'subject_code' => $primarySubject?->code ?? $class->subject_code ?? 'N/A',
            'subject_title' => $primarySubject?->title ?? 'N/A',
            'section' => $class->section ?? 'N/A',
            'school_year' => $class->school_year ?? 'N/A',
            'semester' => $class->semester ?? 'N/A',
            'academic_year' => $class->academic_year,
            'grade_level' => $class->grade_level,
            'shs_track' => $class->shsTrack ? [
                'id' => $class->shsTrack->id,
                'label' => $class->shsTrack->track_name,
            ] : null,
            'shs_strand' => $class->shsStrand ? [
                'id' => $class->shsStrand->id,
                'label' => $class->shsStrand->strand_name,
            ] : null,
            'faculty' => $class->faculty ? [
                'id' => $class->faculty->id,
                'label' => $class->faculty->full_name,
                'email' => $class->faculty->email,
            ] : null,
            'delivery_mode' => $class->delivery_mode ?? Classes::DELIVERY_FACE_TO_FACE,
            'delivery_summary' => $class->deliverySummary(),
            'room' => $class->Room ? [
                'id' => $class->Room->id,
                'label' => $class->Room->name,
            ] : null,
            'course_codes' => $courseCodes,
            'course_abbreviations' => $courseAbbreviations === [] ? null : $courseAbbreviations,
            'course_ids' => is_array($class->course_codes) ? $class->course_codes : [],
            'subjects' => $subjects->map(fn (Subject $subject): array => [
                'id' => $subject->id,
                'code' => $subject->code,
                'title' => $subject->title,
            ])->values(),
            'subject_ids' => is_array($class->subject_ids) ? $class->subject_ids : [],
            'maximum_slots' => (int) ($class->maximum_slots ?? 0),
            'students_count' => (int) ($class->class_enrollments_count ?? 0),
            'settings' => array_merge(Classes::getDefaultSettings(), (array) ($class->settings ?? [])),
            'schedule_revision' => app(SchedulingService::class)->revisionFor($class->schedules),
            'schedules' => $class->schedules
                ->sortBy([
                    fn ($schedule) => $schedule->day_of_week,
                    fn ($schedule) => $schedule->start_time,
                ])
                ->values()
                ->map(fn ($schedule): array => [
                    'id' => $schedule->id,
                    'day_of_week' => $schedule->day_of_week,
                    'start_time' => $schedule->start_time?->format('H:i'),
                    'end_time' => $schedule->end_time?->format('H:i'),
                    'room_id' => $schedule->room_id,
                    'delivery_mode' => $schedule->delivery_mode,
                    'room' => $schedule->room ? [
                        'id' => $schedule->room->id,
                        'label' => $schedule->room->name,
                    ] : null,
                ]),
            'enrollments' => $enrollments->map(fn ($enrollment): array => [
                'id' => $enrollment->id,
                'student' => $enrollment->student ? [
                    'id' => $enrollment->student->id,
                    'student_id' => $enrollment->student->student_id,
                    'name' => $enrollment->student->full_name,
                    'course' => $enrollment->student->course?->code,
                    'academic_year' => $enrollment->student->academic_year,
                ] : null,
                'status' => $enrollment->resolvedEnrollmentStatus(),
                'is_active' => (bool) $enrollment->status,
                'is_grades_verified' => (bool) $enrollment->is_grades_verified,
                'is_grades_finalized' => (bool) $enrollment->is_grades_finalized,
                'is_grade_protected' => $enrollment->isGradeProtected(),
                'prelim_grade' => $enrollment->prelim_grade,
                'midterm_grade' => $enrollment->midterm_grade,
                'finals_grade' => $enrollment->finals_grade,
                'total_average' => $enrollment->total_average,
                'remarks' => $enrollment->remarks,
            ])->values(),
            'posts' => $posts->map(fn ($post): array => [
                'id' => $post->id,
                'title' => $post->title,
                'type' => $post->type,
                'created_at' => format_timestamp($post->created_at),
            ])->values(),
            'filament' => [
                'view_url' => route('filament.admin.resources.classes.view', $class),
                'edit_url' => route('filament.admin.resources.classes.edit', $class),
            ],
        ];
    }

    private function nullableString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = mb_trim($value);

        return $trimmed === '' ? null : $trimmed;
    }

    private function nullableInt(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value;
        }

        if (! is_string($value)) {
            return null;
        }

        if (mb_trim($value) === '') {
            return null;
        }

        return is_numeric($value) ? (int) $value : null;
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
