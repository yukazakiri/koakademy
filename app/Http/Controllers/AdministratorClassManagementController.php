<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Exports\StudentListExport;
use App\Http\Requests\Administrators\CopyClassRequest;
use App\Http\Requests\Administrators\StoreClassRequest;
use App\Http\Requests\Administrators\UpdateClassRequest;
use App\Jobs\MoveStudentToSectionJob;
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
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;

final class AdministratorClassManagementController extends Controller
{
    public function index(
        Request $request,
        GeneralSettingsService $generalSettingsService,
        ClassCurriculumPlacementService $curriculumPlacement,
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
        ];

        $sort = $this->nullableString($request->input('sort')) ?? 'created_at';
        if (! in_array($sort, ['created_at', 'record_title', 'subject_code', 'students_count'], true)) {
            $sort = 'created_at';
        }

        $direction = $request->input('direction') === 'asc' ? 'asc' : 'desc';
        $perPage = $request->input('per_page', 20);

        if ($perPage === 'all') {
            $perPage = 100000;
        } else {
            $perPage = (int) $perPage;
            if (! in_array($perPage, [10, 20, 30, 40, 50], true)) {
                $perPage = 20;
            }
        }

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

        $classesQuery = Classes::query()
            ->currentAcademicPeriod()
            ->with([
                'Subject',
                'SubjectByCodeFallback',
                'ShsSubject',
                'subjects',
                'faculty',
                'shsTrack',
                'shsStrand',
            ])
            ->withCount('class_enrollments')
            ->orderByDesc('id');

        $classesCollection = $classesQuery->get()
            ->filter(function (Classes $class) use ($filters, $courseCodeById, $curriculumPlacement): bool {
                $subject = $class->subjects->first();

                if (! $subject) {
                    $subject = $class->isShs()
                        ? $class->ShsSubject
                        : ($class->Subject ?: $class->SubjectByCodeFallback);
                }

                if ($filters['classification'] && ($class->classification ?? 'college') !== $filters['classification']) {
                    return false;
                }

                if ($filters['course_id'] && ! in_array($filters['course_id'], array_map(intval(...), is_array($class->course_codes) ? $class->course_codes : []), true)) {
                    return false;
                }

                if ($filters['shs_track_id'] && (int) $class->shs_track_id !== $filters['shs_track_id']) {
                    return false;
                }

                if ($filters['shs_strand_id'] && (int) $class->shs_strand_id !== $filters['shs_strand_id']) {
                    return false;
                }

                if ($filters['room_id'] && (int) $class->room_id !== $filters['room_id']) {
                    return false;
                }

                if ($filters['faculty_id'] && (string) $class->faculty_id !== $filters['faculty_id']) {
                    return false;
                }

                if ($filters['academic_year']) {
                    $years = $filters['course_id']
                        ? $curriculumPlacement->yearsForCourse($class, $filters['course_id'])
                        : $curriculumPlacement->yearsForClass($class);

                    if ($years === []) {
                        $years = [(int) $class->academic_year];
                    }

                    if (! in_array($filters['academic_year'], $years, true)) {
                        return false;
                    }
                }

                if ($filters['grade_level'] && $class->grade_level !== $filters['grade_level']) {
                    return false;
                }

                if ($filters['semester'] && (string) $class->semester !== $filters['semester']) {
                    return false;
                }

                if ($filters['subject_code'] && ! str_contains(mb_strtolower((string) ($subject?->code ?? $class->subject_code)), mb_strtolower($filters['subject_code']))) {
                    return false;
                }

                $studentsCount = (int) ($class->class_enrollments_count ?? 0);
                $maximumSlots = (int) ($class->maximum_slots ?? 0);

                if ($filters['available_slots'] && ($maximumSlots <= 0 || $studentsCount >= $maximumSlots)) {
                    return false;
                }

                if ($filters['fully_enrolled'] !== null) {
                    $isFullyEnrolled = $maximumSlots > 0 && $studentsCount >= $maximumSlots;

                    if ($filters['fully_enrolled'] !== $isFullyEnrolled) {
                        return false;
                    }
                }

                if ($filters['search']) {
                    $courseAbbreviations = array_values(array_unique(array_filter(array_map(
                        fn ($id) => $courseCodeById[(int) $id] ?? null,
                        is_array($class->course_codes) ? $class->course_codes : []
                    ))));

                    $searchTerm = mb_strtolower($filters['search']);
                    $searchableValues = [
                        $class->record_title,
                        $subject?->code,
                        $subject?->title,
                        $class->section,
                        $class->school_year,
                        (string) $class->semester,
                        $class->classification,
                        $class->faculty?->full_name,
                        $class->shsTrack?->track_name,
                        $class->shsStrand?->strand_name,
                        implode(' ', $courseAbbreviations),
                    ];

                    $matchesSearch = collect($searchableValues)
                        ->filter(fn ($value): bool => filled($value))
                        ->contains(fn ($value): bool => str_contains(mb_strtolower((string) $value), $searchTerm));

                    if (! $matchesSearch) {
                        return false;
                    }
                }

                return true;
            });

        $sortCallbacks = [
            'created_at' => fn (Classes $class): int => $class->created_at?->getTimestamp() ?? 0,
            'record_title' => fn (Classes $class): string => $class->record_title,
            'subject_code' => fn (Classes $class): string => (string) ($class->subjects->first()?->code ?? $class->subject_code),
            'students_count' => fn (Classes $class): int => (int) ($class->class_enrollments_count ?? 0),
        ];

        $sortCallback = $sortCallbacks[$sort] ?? $sortCallbacks['created_at'];
        $classesCollection = $direction === 'asc'
            ? $classesCollection->sortBy($sortCallback)
            : $classesCollection->sortByDesc($sortCallback);

        $classes = $classesCollection->map(function (Classes $class) use ($courseCodeById): array {
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
                'academic_year' => $class->academic_year,
                'grade_level' => $class->grade_level,
                'course_abbreviations' => $courseAbbreviations === [] ? null : $courseAbbreviations,
                'shs_track' => $shsTrack,
                'shs_strand' => $shsStrand,
                'faculty' => $class->faculty?->full_name ?? 'TBA',
                'students_count' => (int) ($class->class_enrollments_count ?? 0),
                'maximum_slots' => (int) ($class->maximum_slots ?? 0),
                'filament' => [
                    'view_url' => route('filament.admin.resources.classes.view', $class),
                    'edit_url' => route('filament.admin.resources.classes.edit', $class),
                ],
            ];
        })->values();

        $selectedClass = null;
        $selectedId = $this->nullableInt($request->input('selected'));

        if (is_int($selectedId)) {
            $selectedClass = $this->buildSelectedClassProps($selectedId, $courseCodeById, $courseLabelById);
        }

        return Inertia::render('administrators/classes/index', [
            'user' => $this->getUserProps(),
            'filament' => [
                'classes' => [
                    'index_url' => route('filament.admin.resources.classes.index'),
                    'create_url' => route('filament.admin.resources.classes.create'),
                ],
            ],
            'classes' => $classes,
            'selected_class' => fn (): ?array => $selectedClass,
            'filters' => array_merge($filters, [
                'sort' => $sort,
                'direction' => $direction,
                'per_page' => $perPage,
            ]),
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

        $class = Classes::query()->create($validated);

        foreach ($schedules as $scheduleData) {
            $class->schedules()->create([
                'day_of_week' => $scheduleData['day_of_week'],
                'start_time' => $scheduleData['start_time'],
                'end_time' => $scheduleData['end_time'],
                'room_id' => $scheduleData['room_id'],
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
    ): \Illuminate\Http\RedirectResponse {
        $validated = $request->validated();
        $scheduleChangeNotifications = app(ClassScheduleChangeNotificationService::class);
        $oldScheduleSnapshot = null;

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
            $oldScheduleSnapshot = $scheduleChangeNotifications->snapshot($class);
            $schedules = Arr::wrap($validated['schedules']);
            unset($validated['schedules']);
        }

        $class->fill($validated);
        $class->save();

        if (is_array($schedules)) {
            $class->schedules()->delete();

            foreach ($schedules as $scheduleData) {
                $class->schedules()->create([
                    'day_of_week' => $scheduleData['day_of_week'],
                    'start_time' => $scheduleData['start_time'],
                    'end_time' => $scheduleData['end_time'],
                    'room_id' => $scheduleData['room_id'],
                ]);
            }

            $scheduleChangeNotifications->notifyIfChanged(
                $class,
                $oldScheduleSnapshot ?? [],
                Auth::user() instanceof User ? Auth::user() : null
            );
        }

        return redirect()->route('administrators.classes.index')->with('flash', [
            'type' => 'success',
            'message' => 'Class updated successfully.',
            'class_id' => $class->id,
        ]);
    }

    public function destroy(Classes $class): \Illuminate\Http\RedirectResponse
    {
        $class->delete();

        return redirect()->route('administrators.classes.index')->with('flash', [
            'type' => 'success',
            'message' => 'Class deleted.',
        ]);
    }

    public function copy(CopyClassRequest $request, Classes $class): \Illuminate\Http\RedirectResponse
    {
        $section = $request->validated()['section'];

        $newClass = $class->replicate(['section']);
        $newClass->section = $section;

        foreach ($newClass->getAttributes() as $key => $value) {
            if (str_ends_with((string) $key, '_count')) {
                unset($newClass->{$key});
            }
        }

        $newClass->push();

        return redirect()->route('administrators.classes.index')->with('flash', [
            'type' => 'success',
            'message' => 'Class copied successfully (schedules not copied).',
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
                'name' => $s->room?->name ?? 'TBA',
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
     * @return array<string, mixed>
     */
    private function buildClassFormData(Classes $class): array
    {
        $settings = array_merge(Classes::getDefaultSettings(), (array) ($class->settings ?? []));
        $classification = $class->classification === 'shs' ? 'shs' : 'college';
        $defaultRoomId = (int) ($class->room_id ?? Room::query()->where('is_active', true)->value('id') ?? 0);

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
            'room_id' => $defaultRoomId,
            'maximum_slots' => (int) ($class->maximum_slots ?? 40),
            'schedules' => $class->schedules
                ->sortBy([
                    fn (Schedule $schedule) => $schedule->day_of_week,
                    fn (Schedule $schedule) => $schedule->start_time,
                ])
                ->values()
                ->map(fn (Schedule $schedule): array => [
                    'day_of_week' => $schedule->day_of_week,
                    'start_time' => $schedule->start_time?->format('H:i') ?? '08:00',
                    'end_time' => $schedule->end_time?->format('H:i') ?? '09:00',
                    'room_id' => (int) ($schedule->room_id ?? $defaultRoomId),
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
        $class = Classes::query()
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
