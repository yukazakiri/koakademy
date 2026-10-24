<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\Administrators\StoreClassRequest;
use App\Http\Requests\UpdateScheduleRequest;
use App\Models\Classes;
use App\Models\Course;
use App\Models\Faculty;
use App\Models\Room;
use App\Models\Schedule;
use App\Models\ShsStrand;
use App\Models\ShsTrack;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use App\Services\ClassCurriculumPlacementService;
use App\Services\GeneralSettingsService;
use App\Services\Scheduling\ScheduleConflictService;
use App\Services\Scheduling\SchedulingService;
use App\Services\Scheduling\TermOfferingGeneratorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

final class AdministratorSchedulingAnalyticsController extends Controller
{
    public function index(
        Request $request,
        GeneralSettingsService $generalSettingsService,
        ClassCurriculumPlacementService $curriculumPlacement,
        ScheduleConflictService $conflictService,
    ): Response|\Illuminate\Http\RedirectResponse {
        $user = Auth::user();

        if (! $user instanceof User) {
            return redirect('/login');
        }

        $defaultSchoolYear = $generalSettingsService->getCurrentSchoolYearString();
        $defaultSemester = $generalSettingsService->getCurrentSemester();

        $selectedSchoolYear = is_string($request->input('school_year')) && mb_trim($request->input('school_year')) !== ''
            ? GeneralSettingsService::normalizeSchoolYear($request->input('school_year'))
            : $defaultSchoolYear;

        $requestedSemester = $request->input('semester');
        $selectedSemester = $requestedSemester !== null && $requestedSemester !== ''
            ? (is_numeric($requestedSemester) ? (int) $requestedSemester : 1)
            : $defaultSemester;

        // Base query for the selected academic period
        $query = ($request->filled('school_year') || $request->filled('semester'))
            ? Classes::forAcademicPeriod($selectedSchoolYear, $selectedSemester)
            : Classes::currentAcademicPeriod();

        $classes = $query
            ->with(['Subject', 'subjects', 'Faculty', 'Room', 'Schedule.room'])
            ->withCount('ClassStudents')
            ->get();

        // Get all options needed for filtering and class creation
        $allRooms = Room::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'class_code', 'capacity', 'type', 'building']);

        $allFaculty = Faculty::query()
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get(['id', 'first_name', 'last_name', 'department']);

        $allCourses = Course::query()
            ->where('is_active', true)
            ->orderBy('code')
            ->get(['id', 'code', 'title', 'curriculum_year']);

        $shsTracks = ShsTrack::query()
            ->orderBy('track_name')
            ->get(['id', 'track_name']);

        $shsStrands = ShsStrand::query()
            ->with('track:id,track_name')
            ->orderBy('strand_name')
            ->get(['id', 'strand_name', 'track_id']);

        // Process schedule data with normalized year levels and curriculum hour targets
        $scheduleData = $classes->map(
            fn (Classes $class): array => $this->formatClassForPlanner($class, $curriculumPlacement, $conflictService)
        );

        // Get available year levels from the processed data
        $availableYearLevels = $scheduleData->pluck('year_levels')
            ->flatten()
            ->unique()
            ->filter()
            ->sort(function ($a, $b): int {
                $isYearA = str_contains($a, 'Year');
                $isYearB = str_contains($b, 'Year');
                if ($isYearA && ! $isYearB) {
                    return -1;
                }
                if (! $isYearA && $isYearB) {
                    return 1;
                }

                return strnatcmp($a, $b);
            })
            ->values()
            ->toArray();

        // Get available sections from the processed data (or standard A-D)
        $availableSections = $scheduleData->pluck('section')
            ->merge(['A', 'B', 'C', 'D'])
            ->unique()
            ->filter()
            ->sort()
            ->values()
            ->toArray();

        $unscheduledCount = $scheduleData->filter(fn (array $item): bool => (bool) ($item['is_unscheduled'] ?? false))->count();
        $incompleteCount = $scheduleData->filter(
            fn (array $item): bool => ! ($item['is_unscheduled'] ?? false) && ! ($item['is_complete'] ?? true)
        )->count();
        $draftSchedulesCount = $classes->flatMap(fn (Classes $c) => $c->Schedule)
            ->filter(fn (Schedule $s): bool => ($s->status ?? 'published') === 'draft')
            ->count();

        // Get statistics
        $stats = [
            'total_classes' => $classes->count(),
            'total_students' => $classes->sum('class_students_count'),
            'unscheduled_classes_count' => $unscheduledCount,
            'incomplete_classes_count' => $incompleteCount,
            'draft_schedules_count' => $draftSchedulesCount,
            'classes_by_year_level' => $scheduleData->groupBy('grade_level')
                ->map(fn ($group): int => $group->count())
                ->toArray(),
            'classes_by_course' => $classes->groupBy('formatted_course_codes')
                ->map(fn ($group): int => $group->count())
                ->toArray(),
            'schedule_conflicts' => $conflictService->detectConflictsForClasses($classes),
        ];

        $availableSchoolYears = collect($generalSettingsService->getAvailableSchoolYears())
            ->values()
            ->push($selectedSchoolYear)
            ->unique()
            ->values()
            ->all();

        $semesterLabel = match ((string) $selectedSemester) {
            '1' => '1st Semester',
            '2' => '2nd Semester',
            'summer' => 'Summer Term',
            default => "Semester {$selectedSemester}",
        };

        return Inertia::render('administrators/scheduling-analytics', [
            'user' => [
                'name' => $user->name,
                'email' => $user->email,
                'avatar' => $user->avatar_url ?? null,
                'role' => $user->role?->getLabel() ?? 'Administrator',
            ],
            'schedule_data' => $scheduleData,
            'stats' => $stats,
            'term_context' => [
                'school_year' => $selectedSchoolYear,
                'semester' => (string) $selectedSemester,
                'semester_label' => $semesterLabel,
                'available_school_years' => $availableSchoolYears,
                'available_semesters' => [
                    ['value' => '1', 'label' => '1st Semester'],
                    ['value' => '2', 'label' => '2nd Semester'],
                    ['value' => 'summer', 'label' => 'Summer'],
                ],
            ],
            'filters' => [
                'available_courses' => $allCourses->map(fn ($course): array => [
                    'id' => $course->id,
                    'code' => $course->code,
                    'title' => $course->title,
                ])->values(),
                'available_year_levels' => $availableYearLevels,
                'available_sections' => $availableSections,
                'available_rooms' => $allRooms->map(fn ($room): array => [
                    'id' => $room->id,
                    'name' => $room->name,
                    'class_code' => $room->class_code,
                    'capacity' => $room->capacity ?? 40,
                    'type' => $room->type ?? 'lecture',
                    'building' => $room->building,
                ])->values(),
                'available_faculty' => $allFaculty->map(fn ($faculty): array => [
                    'id' => $faculty->id,
                    'name' => $faculty->first_name.' '.$faculty->last_name,
                    'department' => $faculty->department,
                ])->values(),
                'current_filters' => [
                    'course' => $request->input('course'),
                    'year_level' => $request->input('year_level'),
                    'section' => $request->input('section'),
                ],
            ],
            'creation_options' => [
                'rooms' => $allRooms->map(fn ($room): array => [
                    'id' => $room->id,
                    'name' => $room->name,
                    'class_code' => $room->class_code,
                    'capacity' => $room->capacity ?? 40,
                    'type' => $room->type ?? 'lecture',
                    'building' => $room->building,
                ])->values(),
                'faculty' => $allFaculty->map(fn ($faculty): array => [
                    'id' => $faculty->id,
                    'name' => $faculty->first_name.' '.$faculty->last_name,
                    'department' => $faculty->department,
                ])->values(),
                'courses' => $allCourses->map(fn ($course): array => [
                    'id' => $course->id,
                    'code' => $course->code,
                    'title' => $course->title,
                    'curriculum_year' => $course->curriculum_year,
                ])->values(),
                'shs_tracks' => $shsTracks->map(fn ($track): array => [
                    'id' => $track->id,
                    'track_name' => $track->track_name,
                ])->values(),
                'shs_strands' => $shsStrands->map(fn ($strand): array => [
                    'id' => $strand->id,
                    'strand_name' => $strand->strand_name,
                    'track_id' => $strand->track_id,
                    'track_name' => $strand->track?->track_name,
                ])->values(),
                'sections' => ['A', 'B', 'C', 'D'],
                'semesters' => [
                    ['value' => '1', 'label' => '1st Semester'],
                    ['value' => '2', 'label' => '2nd Semester'],
                    ['value' => 'summer', 'label' => 'Summer'],
                ],
            ],
            'defaults' => [
                'semester' => (string) $selectedSemester,
                'school_year' => $selectedSchoolYear,
            ],
        ]);
    }

    /**
     * Search for students and return their schedules
     */
    public function searchStudents(Request $request): JsonResponse
    {
        $request->validate([
            'query' => ['required', 'string', 'min:2'],
        ]);

        $query = $request->input('query');

        $students = Student::query()
            ->where(function ($q) use ($query): void {
                $q->whereLike('first_name', "%{$query}%")
                    ->orWhereLike('last_name', "%{$query}%")
                    ->orWhereLike('student_id', "%{$query}%")
                    ->orWhereLike('lrn', "%{$query}%")
                    ->orWhereLike('email', "%{$query}%");
            })
            ->limit(20)
            ->get(['id', 'student_id', 'first_name', 'last_name', 'middle_name', 'course_id', 'academic_year']);

        return response()->json([
            'students' => $students->map(fn ($student): array => [
                'id' => $student->id,
                'student_id' => $student->student_id,
                'name' => $student->full_name,
                'course_id' => $student->course_id,
                'academic_year' => $student->academic_year,
            ]),
        ]);
    }

    /**
     * Get a student's class schedule for the current academic period
     */
    public function getStudentSchedule(Request $request, int $studentId): JsonResponse
    {
        $student = Student::query()
            ->with(['Course'])
            ->find($studentId);

        if (! $student) {
            return response()->json(['error' => 'Student not found'], 404);
        }

        $classEnrollments = $student->getCurrentClasses();

        $scheduleData = $classEnrollments->map(function ($enrollment): ?array {
            $class = $enrollment->class;
            if (! $class) {
                return null;
            }

            $class->load(['Schedule.room', 'Faculty']);

            return [
                'id' => $class->id,
                'subject_code' => $class->subject_code,
                'subject_title' => $class->subject_title,
                'section' => $class->section,
                'faculty_name' => $class->Faculty ? $class->Faculty->first_name.' '.$class->Faculty->last_name : null,
                'schedules' => $class->Schedule->map(fn ($schedule): array => [
                    'day_of_week' => $schedule->day_of_week,
                    'start_time' => $schedule->formatted_start_time,
                    'end_time' => $schedule->formatted_end_time,
                    'time_range' => $schedule->time_range,
                    'room' => $schedule->room?->name,
                ]),
            ];
        })->filter();

        return response()->json([
            'student' => [
                'id' => $student->id,
                'student_id' => $student->student_id,
                'name' => $student->full_name,
                'course' => $student->Course?->code,
                'academic_year' => $student->academic_year,
            ],
            'schedule' => $scheduleData->values(),
        ]);
    }

    /**
     * Update a schedule entry's day, time, or room (drag-and-drop or inspector).
     */
    public function updateSchedule(
        UpdateScheduleRequest $request,
        Schedule $schedule,
        SchedulingService $schedulingService,
        ScheduleConflictService $conflictService,
    ): JsonResponse {
        $validated = $request->validated();
        $actor = Auth::user() instanceof User ? Auth::user() : null;

        $schedule = $schedulingService->updateScheduleBlock(
            $schedule,
            $validated,
            $actor,
            $request->boolean('apply_to_pattern_group'),
        );

        $class = Classes::query()
            ->with(['Subject', 'Faculty', 'Room', 'Schedule.room'])
            ->find($schedule->class_id);

        $conflicts = [];
        if ($class) {
            $allClasses = Classes::forAcademicPeriod((string) $class->school_year, (int) $class->semester)
                ->with(['Subject', 'Faculty', 'Room', 'Schedule.room'])
                ->get();

            $conflicts = $conflictService->detectConflictsForClasses($allClasses);
        }

        return response()->json([
            'schedule' => [
                'id' => $schedule->id,
                'day_of_week' => $schedule->day_of_week,
                'start_time' => $schedule->formatted_start_time,
                'end_time' => $schedule->formatted_end_time,
                'time_range' => $schedule->time_range,
                'room' => $schedule->room?->name,
                'room_id' => $schedule->room_id,
                'component_type' => $schedule->component_type ?? 'lecture',
                'pattern_group_id' => $schedule->pattern_group_id,
                'status' => $schedule->status ?? 'published',
            ],
            'conflicts' => $conflicts,
        ]);
    }

    /**
     * Delete a schedule entry from the scheduling analytics page.
     */
    public function destroySchedule(
        Request $request,
        Schedule $schedule,
        SchedulingService $schedulingService,
        ScheduleConflictService $conflictService,
    ): JsonResponse {
        $class = $schedule->class;
        $actor = Auth::user() instanceof User ? Auth::user() : null;

        $schedulingService->deleteScheduleBlock(
            $schedule,
            $actor,
            $request->boolean('delete_pattern_group'),
        );

        $allClasses = $class
            ? Classes::forAcademicPeriod((string) $class->school_year, (int) $class->semester)
                ->with(['Subject', 'Faculty', 'Room', 'Schedule.room'])
                ->get()
            : Classes::currentAcademicPeriod()
                ->with(['Subject', 'Faculty', 'Room', 'Schedule.room'])
                ->get();

        $conflicts = $conflictService->detectConflictsForClasses($allClasses);

        return response()->json([
            'conflicts' => $conflicts,
        ]);
    }

    /**
     * Create a new class (with or without initial schedule blocks) from the scheduling planner.
     */
    public function storeClass(
        StoreClassRequest $request,
        ClassCurriculumPlacementService $curriculumPlacement,
        ScheduleConflictService $conflictService,
    ): JsonResponse {
        $validated = $request->validated();

        $classification = $validated['classification'];

        /** @var array<string, mixed> $settings */
        $settings = (array) ($validated['settings'] ?? []);
        unset($validated['settings']);

        $settings = array_merge(Classes::getDefaultSettings(), $settings);
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

        /** @var Classes $class */
        $class = DB::transaction(function () use ($validated, $schedules): Classes {
            $class = Classes::query()->create($validated);

            app(SchedulingService::class)->syncClassSchedules(
                $class,
                $schedules,
                Auth::user() instanceof User ? Auth::user() : null,
            );

            return $class;
        });

        $class->load(['Subject', 'subjects', 'Faculty', 'Room', 'Schedule.room']);
        $class->loadCount('ClassStudents');

        $response = $this->formatClassForPlanner($class, $curriculumPlacement, $conflictService);

        $allClasses = Classes::forAcademicPeriod((string) $class->school_year, (int) $class->semester)
            ->with(['Subject', 'Faculty', 'Room', 'Schedule.room'])
            ->get();

        $conflicts = $conflictService->detectConflictsForClasses($allClasses);

        return response()->json([
            'class' => $response,
            'conflicts' => $conflicts,
        ]);
    }

    /**
     * Place one or more schedule blocks (e.g. MWF, TTh, Single) onto an existing class.
     */
    public function storeClassSchedules(
        Request $request,
        Classes $class,
        SchedulingService $schedulingService,
        ClassCurriculumPlacementService $curriculumPlacement,
        ScheduleConflictService $conflictService,
    ): JsonResponse {
        abort_unless($request->user()?->isAdministrative() ?? false, 403);

        if ($class->isOnline()) {
            $request->merge(['room_id' => null]);
        }

        $validated = $request->validate([
            'days' => ['required', 'array', 'min:1'],
            'days.*' => ['required', 'string', Rule::in(['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'])],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
            'room_id' => ['nullable', 'integer', 'exists:rooms,id'],
            'component_type' => ['nullable', 'string', Rule::in(['lecture', 'lab'])],
            'status' => ['nullable', 'string', Rule::in(['draft', 'published'])],
        ]);

        $actor = Auth::user() instanceof User ? Auth::user() : null;

        $schedulingService->placeSchedulePattern(
            $class,
            array_values($validated['days']),
            (string) $validated['start_time'],
            (string) $validated['end_time'],
            array_key_exists('room_id', $validated)
                ? ($validated['room_id'] !== null ? (int) $validated['room_id'] : null)
                : ($class->room_id !== null ? (int) $class->room_id : null),
            (string) ($validated['component_type'] ?? 'lecture'),
            (string) ($validated['status'] ?? 'published'),
            $actor,
        );

        $class->load(['Subject', 'subjects', 'Faculty', 'Room', 'Schedule.room']);
        $class->loadCount('ClassStudents');

        $allClasses = Classes::forAcademicPeriod((string) $class->school_year, (int) $class->semester)
            ->with(['Subject', 'Faculty', 'Room', 'Schedule.room'])
            ->get();

        return response()->json([
            'class' => $this->formatClassForPlanner($class, $curriculumPlacement, $conflictService),
            'conflicts' => $conflictService->detectConflictsForClasses($allClasses),
        ]);
    }

    /**
     * Bulk-generate cohort class offerings from the curriculum for the target term.
     */
    public function generateOfferings(
        Request $request,
        TermOfferingGeneratorService $generator,
        ClassCurriculumPlacementService $curriculumPlacement,
        ScheduleConflictService $conflictService,
    ): JsonResponse {
        abort_unless($request->user()?->isAdministrative() ?? false, 403);

        if ($request->input('delivery_mode') === Classes::DELIVERY_ONLINE) {
            $request->merge(['room_id' => null]);
        }

        $validated = $request->validate([
            'classification' => ['required', Rule::in(['college', 'shs'])],
            'delivery_mode' => ['sometimes', Rule::in([Classes::DELIVERY_FACE_TO_FACE, Classes::DELIVERY_ONLINE])],
            'school_year' => ['required', 'string', 'max:50'],
            'semester' => ['required', Rule::in(['1', '2', 1, 2, 'summer'])],
            'sections' => ['required', 'array', 'min:1'],
            'sections.*' => ['required', 'string', Rule::in(['A', 'B', 'C', 'D'])],
            'maximum_slots' => ['nullable', 'integer', 'min:1', 'max:500'],
            'room_id' => ['nullable', 'integer', 'exists:rooms,id'],
            'faculty_id' => ['nullable', 'string', 'exists:faculty,id'],
            'course_id' => ['required_if:classification,college', 'nullable', 'integer', 'exists:courses,id'],
            'academic_year' => ['required_if:classification,college', 'nullable', 'integer', 'min:1', 'max:4'],
            'shs_track_id' => ['nullable', 'integer'],
            'shs_strand_id' => ['required_if:classification,shs', 'nullable', 'integer', 'exists:shs_strands,id'],
            'grade_level' => ['required_if:classification,shs', 'nullable', Rule::in(['Grade 11', 'Grade 12'])],
        ]);

        $result = $generator->generateCohortOfferings($validated);

        $createdClasses = $result['created'];
        $createdClasses->load(['Subject', 'subjects', 'Faculty', 'Room', 'Schedule.room']);
        $createdClasses->loadCount('ClassStudents');

        $formattedCreated = $createdClasses->map(
            fn (Classes $class): array => $this->formatClassForPlanner($class, $curriculumPlacement, $conflictService)
        )->values();

        return response()->json([
            'created_count' => $createdClasses->count(),
            'skipped_count' => $result['skipped_count'],
            'subjects_found' => $result['subjects_found'],
            'classes' => $formattedCreated,
        ]);
    }

    /**
     * Validate and publish all draft schedules for the given academic term.
     */
    public function publishTerm(
        Request $request,
        ScheduleConflictService $conflictService,
    ): JsonResponse {
        abort_unless($request->user()?->isAdministrative() ?? false, 403);

        $validated = $request->validate([
            'school_year' => ['required', 'string', 'max:50'],
            'semester' => ['required', Rule::in(['1', '2', 1, 2, 'summer'])],
            'force' => ['nullable', 'boolean'],
        ]);

        $schoolYear = GeneralSettingsService::normalizeSchoolYear((string) $validated['school_year']);
        $semester = is_numeric($validated['semester']) ? (int) $validated['semester'] : 1;

        $classes = Classes::forAcademicPeriod($schoolYear, $semester)
            ->with(['Subject', 'Faculty', 'Room', 'Schedule.room'])
            ->get();

        $conflicts = $conflictService->detectConflictsForClasses($classes);

        if ($conflicts !== [] && ! $request->boolean('force')) {
            return response()->json([
                'message' => sprintf('Cannot publish schedule while %d conflict(s) remain unresolved.', count($conflicts)),
                'conflicts' => $conflicts,
            ], 422);
        }

        $classIds = $classes->pluck('id')->all();
        $publishedCount = $classIds === []
            ? 0
            : Schedule::query()
                ->whereIn('class_id', $classIds)
                ->where('status', 'draft')
                ->update(['status' => 'published']);

        return response()->json([
            'published_count' => $publishedCount,
            'conflicts' => $conflicts,
        ]);
    }

    /**
     * Format a class model for the Schedule Planner Inertia page and JSON responses.
     *
     * @return array<string, mixed>
     */
    private function formatClassForPlanner(
        Classes $class,
        ClassCurriculumPlacementService $curriculumPlacement,
        ScheduleConflictService $conflictService,
    ): array {
        $yearLevel = null;
        $yearLevels = [];
        $courseYearLevels = [];

        if ($class->classification === 'shs') {
            $yearLevel = $class->grade_level;
            $yearLevels = filled($yearLevel) ? [$yearLevel] : [];
        } else {
            $year = (int) $class->academic_year;
            $yearLevel = $curriculumPlacement->yearLabel($year);
            $yearLevels = collect($curriculumPlacement->yearsForClass($class))
                ->whenEmpty(fn ($years) => $year > 0 ? $years->push($year) : $years)
                ->map(fn (int $curriculumYear): string => $curriculumPlacement->yearLabel($curriculumYear))
                ->values()
                ->all();
            $courseYearLevels = collect(is_array($class->course_codes) ? $class->course_codes : [])
                ->filter(fn (mixed $courseId): bool => is_numeric($courseId))
                ->mapWithKeys(fn (mixed $courseId): array => [
                    (string) ((int) $courseId) => collect($curriculumPlacement->yearsForCourse($class, (int) $courseId))
                        ->map(fn (int $curriculumYear): string => $curriculumPlacement->yearLabel($curriculumYear))
                        ->values()
                        ->all(),
                ])
                ->all();
        }

        $hours = $conflictService->calculateClassHours($class);

        $normalizedSubjectCode = collect(explode(',', (string) $class->subject_code))
            ->map(fn (string $part): string => mb_trim($part))
            ->filter()
            ->unique()
            ->implode(', ');

        return [
            'id' => $class->id,
            'subject_code' => $normalizedSubjectCode !== '' ? $normalizedSubjectCode : $class->subject_code,
            'subject_title' => $class->subject_title,
            'section' => $class->section,
            'grade_level' => $yearLevel,
            'year_levels' => $yearLevels,
            'course_year_levels' => $courseYearLevels,
            'classification' => $class->classification,
            'delivery_mode' => $class->delivery_mode,
            'shs_strand_id' => $class->shs_strand_id,
            'faculty_id' => $class->faculty_id,
            'faculty_name' => $class->Faculty ? $class->Faculty->first_name.' '.$class->Faculty->last_name : null,
            'room_id' => $class->room_id,
            'room_name' => $class->Room?->name,
            'maximum_slots' => $class->maximum_slots ?? 40,
            'courses' => $class->formatted_course_codes,
            'course_ids' => $class->course_codes,
            'student_count' => $class->class_students_count ?? 0,
            'lecture_hours' => $hours['lecture_hours'],
            'lab_hours' => $hours['lab_hours'],
            'required_hours' => $hours['required_hours'],
            'scheduled_hours' => $hours['scheduled_hours'],
            'is_unscheduled' => $hours['is_unscheduled'],
            'is_complete' => $hours['is_complete'],
            'schedules' => $class->Schedule->map(fn (Schedule $schedule): array => [
                'id' => $schedule->id,
                'day_of_week' => $schedule->day_of_week,
                'start_time' => $schedule->formatted_start_time,
                'end_time' => $schedule->formatted_end_time,
                'time_range' => $schedule->time_range,
                'room' => $schedule->room?->name,
                'room_id' => $schedule->room_id,
                'component_type' => $schedule->component_type ?? 'lecture',
                'pattern_group_id' => $schedule->pattern_group_id,
                'status' => $schedule->status ?? 'published',
            ]),
        ];
    }
}
