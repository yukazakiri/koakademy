<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Exports\StudentListExport;
use App\Http\Controllers\Api\V1\ApiPagination;
use App\Http\Requests\Administrators\CopyClassRequest;
use App\Http\Requests\Administrators\StoreClassRequest;
use App\Http\Requests\Administrators\UpdateClassRequest;
use App\Jobs\MoveStudentToSectionJob;
use App\Models\ClassEnrollment;
use App\Models\Classes;
use App\Models\Course;
use App\Models\Faculty;
use App\Models\Room;
use App\Models\Schedule;
use App\Models\ShsStrand;
use App\Models\ShsTrack;
use App\Models\StrandSubject;
use App\Models\Subject;
use App\Models\User;
use App\Services\ClassCurriculumPlacementService;
use App\Services\ClassScheduleChangeNotificationService;
use App\Services\GeneralSettingsService;
use App\Support\Database\JsonArrayQuery;
use App\Support\Database\SqlDialect;
use Exception;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;

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

            $filamentClassIndexUrl = route('filament.admin.resources.classes.index');

            return [
                'data' => $paginator->getCollection()
                    ->map(function (Classes $class) use ($courseCodeById, $filamentClassIndexUrl): array {
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
                            'academic_year' => $class->academic_year,
                            'grade_level' => $class->grade_level,
                            'course_abbreviations' => $courseAbbreviations === [] ? null : $courseAbbreviations,
                            'shs_track' => $shsTrack,
                            'shs_strand' => $shsStrand,
                            'faculty' => $class->faculty?->full_name ?? 'TBA',
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
                    ->get(['id', 'name'])
                    ->map(fn (Room $room): array => [
                        'id' => $room->id,
                        'label' => $room->name,
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

    public function create(GeneralSettingsService $generalSettingsService): Response
    {
        return Inertia::render('administrators/classes/create', [
            'user' => $this->getUserProps(),
            'filament' => [
                'classes' => [
                    'index_url' => route('filament.admin.resources.classes.index'),
                ],
            ],
            'options' => $this->buildClassFormOptions(),
            'defaults' => [
                'semester' => (string) $generalSettingsService->getCurrentSemester(),
                'school_year' => $generalSettingsService->getCurrentSchoolYearString(),
                'delivery_mode' => Classes::DELIVERY_FACE_TO_FACE,
            ],
            'flash' => session('flash'),
        ]);
    }

    public function edit(Classes $class): Response
    {
        $class->loadMissing(['schedules']);

        return Inertia::render('administrators/classes/create', [
            'user' => $this->getUserProps(),
            'filament' => [
                'classes' => [
                    'index_url' => route('filament.admin.resources.classes.index'),
                ],
            ],
            'options' => $this->buildClassFormOptions(),
            'defaults' => $this->buildClassFormData($class),
            'mode' => 'edit',
            'class_id' => $class->id,
            'class_title' => $class->record_title,
            'flash' => session('flash'),
        ]);
    }

    public function store(StoreClassRequest $request, ClassCurriculumPlacementService $curriculumPlacement): \Illuminate\Http\RedirectResponse
    {
        $this->authorizeClassAction('Create:Classes');

        $validated = $request->validated();

        $classification = $validated['classification'];

        /** @var array<string, mixed> $settings */
        $settings = (array) ($validated['settings'] ?? []);
        unset($validated['settings']);

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

        $schedules = Arr::wrap($validated['schedules'] ?? []);
        unset($validated['schedules']);

        $class = DB::transaction(function () use ($validated, $schedules): Classes {
            $class = Classes::query()->create($validated);

            app(\App\Services\Scheduling\SchedulingService::class)->syncClassSchedules(
                $class,
                $schedules,
                Auth::user() instanceof User ? Auth::user() : null,
            );

            return $class;
        });

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
        $classification = $validated['classification'] ?? $class->classification ?? 'college';

        /** @var array<string, mixed> $settings */
        $settings = (array) ($validated['settings'] ?? []);
        unset($validated['settings']);

        $shouldRemoveBanner = (bool) ($validated['remove_banner_image'] ?? false);
        unset($validated['remove_banner_image']);

        if ($shouldRemoveBanner) {
            $currentBanner = $class->getSetting('banner_image');
            if (is_string($currentBanner) && $currentBanner !== '') {
                Storage::disk('public')->delete($currentBanner);
            }
            $settings['banner_image'] = null;
        }

        if (isset($settings['banner_image']) && $settings['banner_image'] instanceof \Illuminate\Http\UploadedFile) {
            $currentBanner = $class->getSetting('banner_image');
            if (is_string($currentBanner) && $currentBanner !== '') {
                Storage::disk('public')->delete($currentBanner);
            }
            $settings['banner_image'] = $settings['banner_image']->storePublicly('class-banners', 'public');
        }

        if ($settings !== []) {
            $class->settings = array_merge((array) ($class->settings ?? []), $settings);
        }

        if ($classification === 'shs' && isset($validated['subject_code_shs'])) {
            $validated['subject_code'] = $validated['subject_code_shs'];
            unset($validated['subject_code_shs']);
        }

        if ($classification === 'college') {
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
            $schedules = Arr::wrap($validated['schedules']);
            unset($validated['schedules']);
        }

        $actor = Auth::user() instanceof User ? Auth::user() : null;

        DB::transaction(function () use ($class, $validated, $schedules, $scheduleNotifications, $actor): void {
            // Saving an online class clears session rooms through the model hook.
            $oldSnapshot = $scheduleNotifications->snapshot($class);
            $class->fill($validated);
            $class->save();

            if (is_array($schedules)) {
                app(\App\Services\Scheduling\SchedulingService::class)->syncClassSchedules(
                    $class,
                    $schedules,
                    $actor,
                    $oldSnapshot,
                );
            } else {
                $scheduleNotifications->notifyIfChanged($class, $oldSnapshot, $actor);
            }
        });

        return redirect()->route('administrators.classes.index')->with('flash', [
            'type' => 'success',
            'message' => 'Class updated successfully.',
            'class_id' => $class->id,
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

        $copySchedules = $request->boolean('copy_schedules', true);
        $newClass = DB::transaction(function () use ($class, $section, $copySchedules): Classes {
            $newClass = $class->replicate(['section'])->unsetRelations();
            $newClass->section = $section;

            foreach ($newClass->getAttributes() as $key => $value) {
                if (str_ends_with((string) $key, '_count')) {
                    unset($newClass->{$key});
                }
            }

            $newClass->save();

            if ($copySchedules) {
                app(\App\Services\Scheduling\SchedulingService::class)->copyClassSchedules($class, $newClass);
            }

            return $newClass;
        });

        return redirect()->route('administrators.classes.index')->with('flash', [
            'type' => 'success',
            'message' => $copySchedules
                ? 'Class and schedules copied successfully.'
                : 'Class copied successfully (schedules not copied).',
            'class_id' => $newClass->id,
        ]);
    }

    public function show(Request $request, Classes $class, GeneralSettingsService $generalSettingsService): Response
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

        $class->loadCount('class_enrollments');

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
        $bannerPath = $settings['banner_image'] ?? null;
        $bannerUrl = null;

        if (is_string($bannerPath) && $bannerPath !== '') {
            $bannerUrl = str_starts_with($bannerPath, 'http')
                ? $bannerPath
                : Storage::url($bannerPath);
        }

        // Fetch all enrollments for this class (client-side filtering/sorting/pagination)
        $classEnrollments = $class->class_enrollments()
            ->with([
                'student:id,student_id,first_name,last_name,course_id,academic_year',
                'student.course:id,code',
            ])
            ->latest('created_at')
            ->get();

        // Map student IDs to their StudentEnrollment IDs for the current semester/school year
        $studentIds = $classEnrollments->pluck('student_id')->filter()->unique();
        $studentEnrollmentIds = \App\Models\StudentEnrollment::whereIn('student_id', $studentIds)
            ->where('semester', $class->semester)
            ->where('school_year', $class->school_year)
            ->pluck('id', 'student_id');

        $enrollments = $classEnrollments
            ->map(fn ($enrollment): array => [
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
                ] : null,
                'status' => $enrollment->status,
                'prelim_grade' => $enrollment->prelim_grade,
                'midterm_grade' => $enrollment->midterm_grade,
                'finals_grade' => $enrollment->finals_grade,
                'total_average' => $enrollment->total_average,
                'remarks' => $enrollment->remarks,
            ])
            ->values();

        // Fetch occupied schedules for all rooms in the current period to display conflicts
        $currentSemester = $generalSettingsService->getCurrentSemester();
        $roomSchedules = Schedule::query()
            ->whereHas('class', function ($query) use ($currentSemester): void {
                $query->where('semester', $currentSemester);
            })
            ->with('class:id,subject_code,section')
            ->get()
            ->groupBy('room_id')
            ->map(fn ($schedules) => $schedules->map(fn ($schedule): array => [
                'id' => $schedule->id,
                'class_id' => $schedule->class_id,
                'day_of_week' => $schedule->day_of_week,
                'start_time' => $schedule->start_time?->format('H:i'),
                'end_time' => $schedule->end_time?->format('H:i'),
                'title' => $schedule->class ? ($schedule->class->subject_code.' - '.$schedule->class->section) : 'Occupied',
            ]));

        // Fetch transferable classes (other sections of the same subject)
        $transferableClasses = Classes::query()
            ->where('id', '!=', $class->id)
            ->where('semester', $class->semester)
            ->where('school_year', $class->school_year)
            ->where(function ($query) use ($class): void {
                if ($class->subject_id) {
                    $query->where('subject_id', $class->subject_id);
                } elseif ($class->subject_code) {
                    $query->where('subject_code', $class->subject_code);
                } else {
                    // Prevent matching all classes with null subject info
                    $query->whereRaw('1 = 0');
                }
            })
            ->withCount('class_enrollments')
            ->get(['id', 'section', 'maximum_slots', 'subject_code'])
            ->map(function ($c): array {
                $available = max(0, $c->maximum_slots - ($c->class_enrollments_count ?? 0));
                $isFull = $available <= 0;

                return [
                    'id' => $c->id,
                    'section' => $c->section,
                    'label' => sprintf(
                        '%s - Section %s (Available: %s/%s)%s',
                        $c->subject_code ?? 'Unknown Subject',
                        $c->section,
                        $available,
                        $c->maximum_slots,
                        $isFull ? ' [FULL]' : ''
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
                'name' => $s->room?->name ?? ($class->isOnline() ? 'Online' : 'TBA'),
            ],
            'has_conflict' => false, // Conflict calculation is done in frontend or separate prop
        ]));

        $associatedCourses = null;
        if (! $class->isShs() && is_array($class->course_codes) && $class->course_codes !== []) {
            $courses = Course::whereIn('id', $class->course_codes)->get(['code', 'curriculum_year']);
            $associatedCourses = $courses->map(fn ($c) => $c->curriculum_year ? "$c->code ($c->curriculum_year)" : $c->code)->implode(', ');
        } elseif ($class->isShs()) {
            $associatedCourses = $class->formatted_track_strand;
        }

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
                'students_count' => (int) ($class->class_enrollments_count ?? 0),
                'maximum_slots' => (int) ($class->maximum_slots ?? 0),
                'faculty' => $class->faculty ? [
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
                    'features' => [
                        'enable_announcements' => (bool) ($settings['enable_announcements'] ?? false),
                        'enable_grade_visibility' => (bool) ($settings['enable_grade_visibility'] ?? false),
                        'enable_attendance_tracking' => (bool) ($settings['enable_attendance_tracking'] ?? false),
                        'allow_late_submissions' => (bool) ($settings['allow_late_submissions'] ?? false),
                        'enable_discussion_board' => (bool) ($settings['enable_discussion_board'] ?? false),
                        'notify_students_on_schedule_changes' => (bool) ($settings['notify_students_on_schedule_changes'] ?? true),
                    ],
                    'custom' => (array) ($settings['custom'] ?? []),
                ],
                'filament' => [
                    'view_url' => route('filament.admin.resources.classes.view', $class),
                    'edit_url' => route('filament.admin.resources.classes.edit', $class),
                ],
                'deleted_at' => $class->deleted_at?->toIso8601String(),
                'delivery_mode' => $class->delivery_mode ?? Classes::DELIVERY_FACE_TO_FACE,
                'is_archived' => $class->trashed(),
            ],
            'capabilities' => [
                'update' => $this->canManageClasses('Update:Classes'),
                'delete' => $this->canManageClasses('Delete:Classes'),
                'restore' => $this->canManageClasses('Restore:Classes'),
                'force_delete' => $this->canManageClasses('ForceDelete:Classes'),
            ],
            'enrollments' => $enrollments,
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
                    'temp_id' => (string) $schedule->id, // Ensure temp_id exists for keying
                ]),
            'room_schedules' => $roomSchedules,
            'transferable_classes' => $transferableClasses,
            'rooms' => Room::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (Room $room): array => [
                    'id' => $room->id,
                    'label' => $room->name,
                ])->values(),
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
            ->get(['id', 'code', 'title', 'course_id', 'academic_year', 'semester']);

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
                'Subject:id,code,title',
                'SubjectByCodeFallback:id,code,title',
                'ShsSubject:id,code,title',
                'subjects:id,code,title',
                'faculty:id,first_name,last_name,middle_name',
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
            'section' => (string) ($class->section ?? 'A'),
            'delivery_mode' => $class->delivery_mode ?? Classes::DELIVERY_FACE_TO_FACE,
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
                    'component_type' => $schedule->component_type,
                    'pattern_group_id' => $schedule->pattern_group_id,
                    'status' => $schedule->status,
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
                'allow_late_submissions' => (bool) ($settings['allow_late_submissions'] ?? false),
                'enable_discussion_board' => (bool) ($settings['enable_discussion_board'] ?? false),
                'notify_students_on_schedule_changes' => (bool) ($settings['notify_students_on_schedule_changes'] ?? true),
                'custom' => (array) ($settings['custom'] ?? []),
                'banner_image' => null,
            ],
        ];
    }

    /**
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function buildClassFormOptions(): array
    {
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
            'delivery_modes' => [
                ['value' => 'face_to_face', 'label' => 'Face to face'],
                ['value' => 'online', 'label' => 'Online'],
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
            'academic_years' => [
                ['value' => '1', 'label' => '1st Year'],
                ['value' => '2', 'label' => '2nd Year'],
                ['value' => '3', 'label' => '3rd Year'],
                ['value' => '4', 'label' => '4th Year'],
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
            ])->values()->all(),
            'faculties' => Faculty::query()
                ->orderBy('last_name')
                ->orderBy('first_name')
                ->get(['id', 'first_name', 'last_name', 'middle_name'])
                ->map(fn (Faculty $faculty): array => [
                    'id' => $faculty->id,
                    'label' => $faculty->full_name,
                ])->values()->all(),
            'rooms' => Room::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (Room $room): array => [
                    'id' => $room->id,
                    'label' => $room->name,
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
            ->limit(30)
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
                'status' => $enrollment->status,
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
