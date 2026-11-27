<?php

declare(strict_types=1);

namespace App\Services\Scheduling;

use App\Models\Classes;
use App\Models\Faculty;
use App\Models\Room;
use App\Models\Schedule;
use App\Models\Subject;
use App\Models\User;
use App\Services\ClassCurriculumPlacementService;
use App\Services\ClassScheduleChangeNotificationService;
use App\Services\GeneralSettingsService;
use App\Support\ScheduleDelivery;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class ClassCrudPreflightService
{
    public const float DEFAULT_MAX_FACULTY_LOAD_HOURS = 24.0;

    /** @var list<string> */
    public const array POLICY_SETTING_KEYS = [
        'enable_announcements',
        'enable_grade_visibility',
        'enable_attendance_tracking',
        'enable_online_resources',
        'allow_late_submissions',
        'enable_discussion_board',
        'notify_students_on_schedule_changes',
    ];

    /** @var array<string, int> */
    private const array DAY_ORDER = [
        'Monday' => 0,
        'Tuesday' => 1,
        'Wednesday' => 2,
        'Thursday' => 3,
        'Friday' => 4,
        'Saturday' => 5,
        'Sunday' => 6,
    ];

    public function __construct(
        private ClassScheduleWorkspaceService $scheduleWorkspace,
        private SchedulingService $schedulingService,
        private GeneralSettingsService $settingsService,
        private ClassCurriculumPlacementService $curriculumPlacement,
        private ClassScheduleChangeNotificationService $notificationService,
    ) {}

    /**
     * Draft-safe schedule preflight for single-class create/edit composer.
     *
     * @param  array<string, mixed>  $payload
     * @return array{
     *     hours: array{scheduled: float, required: float|null, lecture: float, lab: float},
     *     conflicts: list<array<string, mixed>>,
     *     busy_overlay: array{faculty_busy: list<array<string, mixed>>, room_busy: list<array<string, mixed>>, cohort_busy: list<array<string, mixed>>},
     *     room_checks: list<array<string, mixed>>,
     *     suggestions: list<array<string, mixed>>,
     *     student_impact: array<string, mixed>|null,
     *     faculty_load: array{hours: float, max: int}|null,
     *     can_publish: bool
     * }
     */
    public function preflight(array $payload): array
    {
        $classId = isset($payload['class_id']) && is_numeric($payload['class_id']) && (int) $payload['class_id'] > 0
            ? (int) $payload['class_id']
            : null;

        $existingClass = $classId !== null
            ? Classes::query()->with(['schedules.room:id,name,capacity'])->find($classId)
            : null;

        $classification = (string) ($payload['classification'] ?? 'college');
        $facultyId = $this->normalizeFacultyId($payload['faculty_id'] ?? null);
        $defaultMode = ScheduleDelivery::canonicalize($payload['delivery_mode'] ?? Classes::DELIVERY_FACE_TO_FACE);
        $defaultRoomId = ScheduleDelivery::isOnline($defaultMode)
            ? null
            : $this->normalizePositiveInt($payload['room_id'] ?? null);
        $maximumSlots = max(0, (int) ($payload['maximum_slots'] ?? 0));
        $courseCodes = $this->normalizeIntList($payload['course_codes'] ?? []);
        $subjectIds = $this->normalizeIntList($payload['subject_ids'] ?? []);
        $subjectCode = $this->nullableTrimmed($payload['subject_code'] ?? $existingClass?->subject_code ?? null);
        $academicYear = $this->normalizePositiveInt($payload['academic_year'] ?? null);
        $gradeLevel = $this->nullableTrimmed($payload['grade_level'] ?? null);
        $shsStrandId = $this->normalizePositiveInt($payload['shs_strand_id'] ?? null);
        $schoolYear = (string) ($payload['school_year'] ?? '');
        $semester = $this->resolveSemester($payload['semester'] ?? null);
        $policy = ScheduleDelivery::policy();

        $blocks = $this->normalizeBlocks(
            is_array($payload['schedules'] ?? null) ? $payload['schedules'] : [],
            $defaultMode,
            $defaultRoomId,
        );

        [$subjects, $requiredHours] = $this->loadSubjectsAndRequiredHours($subjectIds);
        $derivedSubjectCodes = $this->resolveSubjectCodes($subjects, $subjectCode);

        $otherClasses = $this->loadTermClasses(
            $schoolYear,
            $semester,
            $classId !== null ? [$classId] : [],
        );

        $referencedRoomIds = collect($blocks)
            ->pluck('room_id')
            ->when($defaultRoomId !== null, fn (Collection $ids): Collection => $ids->push($defaultRoomId))
            ->filter(fn (mixed $id): bool => is_int($id) && $id > 0)
            ->unique()
            ->values()
            ->all();

        $activeRooms = Room::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'class_code', 'capacity', 'type', 'building']);

        $roomsById = $activeRooms->keyBy('id');
        $missingRoomIds = array_values(array_diff($referencedRoomIds, $roomsById->keys()->map(fn ($id): int => (int) $id)->all()));
        if ($missingRoomIds !== []) {
            Room::query()
                ->whereIn('id', $missingRoomIds)
                ->get(['id', 'name', 'class_code', 'capacity', 'type', 'building'])
                ->each(function (Room $room) use ($roomsById): void {
                    $roomsById->put((int) $room->id, $room);
                });
        }

        $facultyName = $facultyId !== null
            ? Faculty::query()->whereKey($facultyId)->first(['id', 'first_name', 'last_name', 'middle_name'])?->full_name
            : null;

        $hours = $this->computeHours($blocks, $requiredHours);

        $cohortSpec = [
            'classification' => $classification,
            'academic_year' => $academicYear,
            'course_codes' => $courseCodes,
            'grade_level' => $gradeLevel,
            'shs_strand_id' => $shsStrandId,
            'subject_ids' => $subjectIds,
            'subject_codes' => $derivedSubjectCodes,
        ];

        $conflicts = [];

        // 1. Self overlaps and invalid time ranges
        $conflicts = array_merge($conflicts, $this->detectSelfConflicts($blocks, $policy));

        // 2. External room, faculty, and cohort conflicts
        $conflicts = array_merge(
            $conflicts,
            $this->detectExternalConflicts(
                $blocks,
                $otherClasses,
                $facultyId,
                $facultyName,
                $cohortSpec,
                $roomsById,
                $policy,
            ),
        );

        // 3. Room checks + capacity warnings
        $roomChecks = [];
        foreach ($blocks as $block) {
            $idx = $block['index'];
            $isOnline = ScheduleDelivery::isOnline($block['delivery_mode']);
            $room = $block['room_id'] !== null ? $roomsById->get($block['room_id']) : null;
            $capacity = (! $isOnline && $room?->capacity !== null) ? (int) $room->capacity : null;
            $fits = ($isOnline || ! $room instanceof Room || $capacity === null || $capacity <= 0)
                ? null
                : ($maximumSlots <= 0 || $maximumSlots <= $capacity);

            $roomChecks[] = [
                'schedule_index' => $idx,
                'room_id' => $isOnline ? null : $block['room_id'],
                'room_name' => $isOnline
                    ? ScheduleDelivery::locationLabel($block['delivery_mode'])
                    : ($room?->name ?? null),
                'capacity' => $capacity,
                'fits' => $fits,
            ];

            if ($fits === false && $room instanceof Room) {
                $conflicts[] = [
                    'kind' => 'capacity',
                    'severity' => 'warning',
                    'schedule_index' => $idx,
                    'message' => sprintf(
                        'Maximum slots (%d) exceeds %s capacity (%d).',
                        $maximumSlots,
                        $room->name,
                        (int) $capacity,
                    ),
                    'day_of_week' => $block['day_of_week'],
                    'start_time' => $block['start_time'],
                    'end_time' => $block['end_time'],
                    'with_class' => null,
                ];
            }
        }

        if ($blocks === [] && $defaultRoomId !== null && $maximumSlots > 0) {
            $defaultRoom = $roomsById->get($defaultRoomId);
            if ($defaultRoom instanceof Room && $defaultRoom->capacity !== null && $defaultRoom->capacity > 0 && $maximumSlots > (int) $defaultRoom->capacity) {
                $conflicts[] = [
                    'kind' => 'capacity',
                    'severity' => 'warning',
                    'schedule_index' => null,
                    'message' => sprintf(
                        'Maximum slots (%d) exceeds %s capacity (%d).',
                        $maximumSlots,
                        $defaultRoom->name,
                        (int) $defaultRoom->capacity,
                    ),
                    'with_class' => null,
                ];
            }
        }

        // 4. Required hours warning
        if ($requiredHours !== null && $hours['scheduled'] > 0 && abs($hours['scheduled'] - $requiredHours) > 0.25) {
            $conflicts[] = [
                'kind' => 'hours',
                'severity' => 'warning',
                'schedule_index' => null,
                'message' => sprintf(
                    'Scheduled weekly hours (%.2gh) differ from required subject hours (%.2gh).',
                    $hours['scheduled'],
                    $requiredHours,
                ),
                'with_class' => null,
            ];
        }

        // 5. Faculty load warning & summary
        $facultyLoad = null;
        if ($facultyId !== null) {
            $existingMinutes = $this->computeFacultyTermMinutes($otherClasses, $facultyId);
            $projectedHours = round(($existingMinutes + $hours['scheduled_minutes']) / 60, 2);
            $maxLoad = (int) self::DEFAULT_MAX_FACULTY_LOAD_HOURS;

            $facultyLoad = [
                'hours' => $projectedHours,
                'max' => $maxLoad,
            ];

            if ($projectedHours > self::DEFAULT_MAX_FACULTY_LOAD_HOURS) {
                $conflicts[] = [
                    'kind' => 'faculty_load',
                    'severity' => 'warning',
                    'schedule_index' => null,
                    'message' => sprintf(
                        'Faculty weekly teaching load (%.2gh) exceeds the %dh guideline.',
                        $projectedHours,
                        $maxLoad,
                    ),
                    'with_class' => null,
                ];
            }
        }

        // 6. Weekly busy overlay (faculty, target rooms, cohort)
        $busyOverlay = $this->buildBusyOverlay(
            $otherClasses,
            $facultyId,
            $referencedRoomIds,
            $cohortSpec,
            $policy,
        );

        // 7. Student impact for existing class edits
        $studentImpact = null;
        $validImpactBlocks = $this->toImpactBlocks($blocks);
        if ($existingClass instanceof Classes) {
            $impact = $this->scheduleWorkspace->previewRescheduleImpact($existingClass, $validImpactBlocks);
            $studentImpact = $impact['student_impact'];
        }

        // 8. Suggestions for conflicting blocks
        $suggestions = $this->buildSuggestions(
            $blocks,
            $conflicts,
            $otherClasses,
            $activeRooms,
            $roomsById,
            $facultyId,
            $cohortSpec,
            $maximumSlots,
            $policy,
            $existingClass,
        );

        $hasBlocking = collect($conflicts)->contains(
            fn (array $conflict): bool => ($conflict['severity'] ?? '') === 'blocking'
        );

        return [
            'hours' => [
                'scheduled' => $hours['scheduled'],
                'required' => $hours['required'],
                'lecture' => $hours['lecture'],
                'lab' => $hours['lab'],
            ],
            'conflicts' => $conflicts,
            'busy_overlay' => $busyOverlay,
            'room_checks' => $roomChecks,
            'suggestions' => $suggestions,
            'student_impact' => $studentImpact,
            'faculty_load' => $facultyLoad,
            'can_publish' => ! $hasBlocking,
        ];
    }

    /**
     * Validate (dry_run) or atomically create/update parallel sections from a shared subject blueprint.
     *
     * @param  array<string, mixed>  $payload
     * @return array{status: int, body: array<string, mixed>}
     */
    public function batchBlueprint(array $payload, ?User $actor): array
    {
        if (! (bool) ($payload['dry_run'] ?? true)) {
            return DB::transaction(function () use ($payload, $actor): array {
                $this->schedulingService->lockScheduleWrites();

                return $this->evaluateBatchBlueprint($payload, $actor);
            });
        }

        return $this->evaluateBatchBlueprint($payload, $actor);
    }

    private function evaluateBatchBlueprint(array $payload, ?User $actor): array
    {
        /** @var array<string, mixed> $blueprint */
        $blueprint = (array) ($payload['blueprint'] ?? []);
        /** @var list<array<string, mixed>> $sections */
        $sections = array_values((array) ($payload['sections'] ?? []));
        $dryRun = (bool) ($payload['dry_run'] ?? true);
        $scheduleStatus = (string) ($payload['schedule_status'] ?? 'draft');
        $notifyEnrolled = (bool) ($payload['notify_enrolled'] ?? false);

        $classification = (string) ($blueprint['classification'] ?? 'college');
        $schoolYear = (string) ($blueprint['school_year'] ?? '');
        $semester = $this->resolveSemester($blueprint['semester'] ?? null);
        $defaultMode = ScheduleDelivery::canonicalize($blueprint['delivery_mode'] ?? Classes::DELIVERY_FACE_TO_FACE);
        $policy = ScheduleDelivery::policy();

        $courseCodes = $this->normalizeIntList($blueprint['course_codes'] ?? []);
        $subjectIds = $this->normalizeIntList($blueprint['subject_ids'] ?? []);
        $academicYear = $this->normalizePositiveInt($blueprint['academic_year'] ?? null);
        $shsTrackId = $this->normalizePositiveInt($blueprint['shs_track_id'] ?? null);
        $shsStrandId = $this->normalizePositiveInt($blueprint['shs_strand_id'] ?? null);
        $gradeLevel = $this->nullableTrimmed($blueprint['grade_level'] ?? null);
        $startDate = $this->nullableTrimmed($blueprint['start_date'] ?? null);

        [$subjects, $requiredHoursPerSection] = $this->loadSubjectsAndRequiredHours($subjectIds);

        $blueprintErrors = [];
        $normalizedCollege = [];

        if ($classification === 'college') {
            try {
                $normalizedCollege = $this->curriculumPlacement->normalizeCollegeAttributes([
                    'course_codes' => $courseCodes,
                    'subject_ids' => $subjectIds,
                    'academic_year' => $academicYear,
                ]);
                $academicYear = $this->normalizePositiveInt($normalizedCollege['academic_year'] ?? $academicYear);
            } catch (ValidationException $exception) {
                foreach ($exception->errors() as $messages) {
                    foreach ($messages as $message) {
                        $blueprintErrors[] = (string) $message;
                    }
                }
            }

            $subjectCode = $this->nullableTrimmed($blueprint['subject_code'] ?? null);
            if ($subjectCode === null && $subjects->isNotEmpty()) {
                $subjectCode = $subjects->pluck('code')->filter()->unique()->implode(', ');
            }
        } else {
            $subjectCode = $this->nullableTrimmed($blueprint['subject_code_shs'] ?? $blueprint['subject_code'] ?? null);
            if ($shsTrackId === null) {
                $blueprintErrors[] = 'Select an SHS track for this blueprint.';
            }
            if ($shsStrandId === null) {
                $blueprintErrors[] = 'Select an SHS strand for this blueprint.';
            }
            if ($gradeLevel === null) {
                $blueprintErrors[] = 'Select a grade level for this SHS blueprint.';
            }
            if ($subjectCode === null) {
                $blueprintErrors[] = 'Select an SHS subject for this blueprint.';
            }
        }

        $derivedSubjectCodes = $this->resolveSubjectCodes($subjects, $subjectCode);

        $batchClassIds = collect($sections)
            ->pluck('class_id')
            ->filter(fn (mixed $id): bool => is_numeric($id) && (int) $id > 0)
            ->map(fn (mixed $id): int => (int) $id)
            ->unique()
            ->values()
            ->all();

        $externalClasses = $this->loadTermClasses($schoolYear, $semester, $batchClassIds);

        // Normalize all staged sections up front
        $staged = [];
        $referencedRoomIds = [];
        $referencedFacultyIds = [];
        $totalSeats = 0;
        $totalScheduledMinutes = 0;

        foreach ($sections as $sectionIndex => $rawSection) {
            $clientKey = (string) ($rawSection['client_key'] ?? ('section-'.$sectionIndex));
            $classId = $this->normalizePositiveInt($rawSection['class_id'] ?? null);
            $sectionName = mb_trim((string) ($rawSection['section'] ?? ''));
            $facultyId = $this->normalizeFacultyId($rawSection['faculty_id'] ?? null);
            $maxSlots = max(0, (int) ($rawSection['maximum_slots'] ?? 0));
            $sectionRoomId = ScheduleDelivery::isOnline($defaultMode)
                ? null
                : $this->normalizePositiveInt($rawSection['room_id'] ?? null);

            $blocks = $this->normalizeBlocks(
                is_array($rawSection['schedules'] ?? null) ? $rawSection['schedules'] : [],
                $defaultMode,
                $sectionRoomId,
            );

            $hours = $this->computeHours($blocks, $requiredHoursPerSection);
            $totalSeats += $maxSlots;
            $totalScheduledMinutes += $hours['scheduled_minutes'];

            if ($sectionRoomId !== null) {
                $referencedRoomIds[] = $sectionRoomId;
            }
            foreach ($blocks as $block) {
                if ($block['room_id'] !== null) {
                    $referencedRoomIds[] = $block['room_id'];
                }
            }
            if ($facultyId !== null) {
                $referencedFacultyIds[] = $facultyId;
            }

            $staged[] = [
                'client_key' => $clientKey,
                'class_id' => $classId,
                'section' => $sectionName,
                'section_norm' => mb_strtoupper($sectionName),
                'faculty_id' => $facultyId,
                'maximum_slots' => $maxSlots,
                'room_id' => $sectionRoomId,
                'blocks' => $blocks,
                'hours' => $hours,
            ];
        }

        $referencedRoomIds = array_values(array_unique($referencedRoomIds));
        $referencedFacultyIds = array_values(array_unique($referencedFacultyIds));

        $roomsById = $referencedRoomIds !== []
            ? Room::query()
                ->whereIn('id', $referencedRoomIds)
                ->get(['id', 'name', 'class_code', 'capacity', 'type', 'building'])
                ->keyBy('id')
            : collect();

        $facultyNamesById = $referencedFacultyIds !== []
            ? Faculty::query()
                ->whereIn('id', $referencedFacultyIds)
                ->get(['id', 'first_name', 'last_name', 'middle_name'])
                ->mapWithKeys(fn (Faculty $f): array => [(string) $f->id => $f->full_name])
                ->all()
            : [];

        $cohortSpec = [
            'classification' => $classification,
            'academic_year' => $academicYear,
            'course_codes' => $courseCodes,
            'grade_level' => $gradeLevel,
            'shs_strand_id' => $shsStrandId,
            'subject_ids' => $subjectIds,
            'subject_codes' => $derivedSubjectCodes,
        ];

        /** @var list<array<string, mixed>> $allConflicts */
        $allConflicts = [];
        /** @var array<string, list<string>> $sectionErrors */
        $sectionErrors = [];
        /** @var array<string, bool> $sectionStructuralErrors */
        $sectionStructuralErrors = [];
        /** @var array<string, bool> $sectionHasBlocking */
        $sectionHasBlocking = [];

        foreach ($staged as $sec) {
            $key = $sec['client_key'];
            $sectionErrors[$key] = $blueprintErrors;
            $sectionStructuralErrors[$key] = $blueprintErrors !== [];
            $sectionHasBlocking[$key] = $blueprintErrors !== [];
        }

        // Precompute batch-wide scheduled minutes per faculty
        $batchFacultyMinutes = [];
        foreach ($staged as $sec) {
            if ($sec['faculty_id'] !== null) {
                $batchFacultyMinutes[$sec['faculty_id']] = ($batchFacultyMinutes[$sec['faculty_id']] ?? 0) + $sec['hours']['scheduled_minutes'];
            }
        }

        // 1. Per-section internal, external, capacity, hours, and faculty load checks
        foreach ($staged as $sec) {
            $key = $sec['client_key'];
            $facultyId = $sec['faculty_id'];
            $facultyName = $facultyId !== null ? ($facultyNamesById[$facultyId] ?? null) : null;

            $selfConflicts = $this->detectSelfConflicts($sec['blocks'], $policy);
            foreach ($selfConflicts as $conflict) {
                $allConflicts[] = [...$conflict, 'client_key' => $key];
                $sectionErrors[$key][] = $conflict['message'];
                $sectionHasBlocking[$key] = true;
                $sectionStructuralErrors[$key] = true;
            }

            $externalConflicts = $this->detectExternalConflicts(
                $sec['blocks'],
                $externalClasses,
                $facultyId,
                $facultyName,
                $cohortSpec,
                $roomsById,
                $policy,
            );

            foreach ($externalConflicts as $conflict) {
                $allConflicts[] = [...$conflict, 'client_key' => $key];
                if ($conflict['severity'] === 'blocking') {
                    $sectionErrors[$key][] = $conflict['message'];
                    $sectionHasBlocking[$key] = true;
                }
            }

            // Room capacity warnings
            foreach ($sec['blocks'] as $block) {
                if (ScheduleDelivery::isOnline($block['delivery_mode']) || $block['room_id'] === null) {
                    continue;
                }
                $room = $roomsById->get($block['room_id']);
                if ($room instanceof Room && $room->capacity !== null && $room->capacity > 0 && $sec['maximum_slots'] > (int) $room->capacity) {
                    $allConflicts[] = [
                        'client_key' => $key,
                        'kind' => 'capacity',
                        'severity' => 'warning',
                        'schedule_index' => $block['index'],
                        'message' => sprintf(
                            'Section %s maximum slots (%d) exceeds %s capacity (%d).',
                            $sec['section'],
                            $sec['maximum_slots'],
                            $room->name,
                            (int) $room->capacity,
                        ),
                        'day_of_week' => $block['day_of_week'],
                        'start_time' => $block['start_time'],
                        'end_time' => $block['end_time'],
                        'with_class' => null,
                    ];
                }
            }

            // Required hours warning
            if ($requiredHoursPerSection !== null && $sec['hours']['scheduled'] > 0 && abs($sec['hours']['scheduled'] - $requiredHoursPerSection) > 0.25) {
                $allConflicts[] = [
                    'client_key' => $key,
                    'kind' => 'hours',
                    'severity' => 'warning',
                    'schedule_index' => null,
                    'message' => sprintf(
                        'Section %s scheduled hours (%.2gh) differ from required subject hours (%.2gh).',
                        $sec['section'],
                        $sec['hours']['scheduled'],
                        $requiredHoursPerSection,
                    ),
                    'with_class' => null,
                ];
            }

            // Faculty load warning (external + entire batch)
            if ($facultyId !== null) {
                $externalMinutes = $this->computeFacultyTermMinutes($externalClasses, $facultyId);
                $totalFacultyHours = round(($externalMinutes + ($batchFacultyMinutes[$facultyId] ?? 0)) / 60, 2);
                if ($totalFacultyHours > self::DEFAULT_MAX_FACULTY_LOAD_HOURS) {
                    $allConflicts[] = [
                        'client_key' => $key,
                        'kind' => 'faculty_load',
                        'severity' => 'warning',
                        'schedule_index' => null,
                        'message' => sprintf(
                            '%s projected weekly teaching load (%.2gh) exceeds the %dh guideline.',
                            $facultyName ?? 'Assigned faculty',
                            $totalFacultyHours,
                            (int) self::DEFAULT_MAX_FACULTY_LOAD_HOURS,
                        ),
                        'with_class' => null,
                    ];
                }
            }
        }

        // 2. Cross-section collision detection across ALL staged sections in the batch
        $count = count($staged);
        for ($i = 0; $i < $count; $i++) {
            for ($j = $i + 1; $j < $count; $j++) {
                $secA = $staged[$i];
                $secB = $staged[$j];
                $keyA = $secA['client_key'];
                $keyB = $secB['client_key'];

                // Duplicate section code inside batch
                if ($secA['section_norm'] !== '' && $secA['section_norm'] === $secB['section_norm']) {
                    $dupMsg = sprintf('Duplicate section code "%s" in this batch.', $secA['section']);
                    $allConflicts[] = [
                        'client_key' => $keyA,
                        'kind' => 'self',
                        'severity' => 'blocking',
                        'schedule_index' => null,
                        'message' => $dupMsg,
                        'with_class' => ['id' => $secB['class_id'] ?? 0, 'label' => 'Sec '.$secB['section']],
                    ];
                    $allConflicts[] = [
                        'client_key' => $keyB,
                        'kind' => 'self',
                        'severity' => 'blocking',
                        'schedule_index' => null,
                        'message' => $dupMsg,
                        'with_class' => ['id' => $secA['class_id'] ?? 0, 'label' => 'Sec '.$secA['section']],
                    ];
                    $sectionErrors[$keyA][] = $dupMsg;
                    $sectionErrors[$keyB][] = $dupMsg;
                    $sectionHasBlocking[$keyA] = true;
                    $sectionHasBlocking[$keyB] = true;
                    $sectionStructuralErrors[$keyA] = true;
                    $sectionStructuralErrors[$keyB] = true;
                }

                // Schedule block pairwise collision check (Room + Faculty)
                foreach ($secA['blocks'] as $blockA) {
                    if ($blockA['start_min'] === null || $blockA['end_min'] === null || $blockA['end_min'] <= $blockA['start_min']) {
                        continue;
                    }

                    foreach ($secB['blocks'] as $blockB) {
                        if ($blockB['start_min'] === null || $blockB['end_min'] === null || $blockB['end_min'] <= $blockB['start_min']) {
                            continue;
                        }

                        if ($blockA['day_of_week'] !== $blockB['day_of_week']) {
                            continue;
                        }

                        if ($blockA['start_min'] >= $blockB['end_min'] || $blockA['end_min'] <= $blockB['start_min']) {
                            continue;
                        }

                        // Cross-section Room collision
                        if (
                            ScheduleDelivery::checksRoomConflicts($blockA['delivery_mode'], $policy)
                            && ScheduleDelivery::checksRoomConflicts($blockB['delivery_mode'], $policy)
                            && $blockA['room_id'] !== null
                            && $blockB['room_id'] !== null
                            && $blockA['room_id'] === $blockB['room_id']
                        ) {
                            $roomName = $roomsById->get($blockA['room_id'])?->name ?? ('Room #'.$blockA['room_id']);
                            $msgA = sprintf(
                                'Room %s overlaps with staged Sec %s (%s %s–%s).',
                                $roomName,
                                $secB['section'],
                                $blockB['day_of_week'],
                                $blockB['start_time'],
                                $blockB['end_time'],
                            );
                            $msgB = sprintf(
                                'Room %s overlaps with staged Sec %s (%s %s–%s).',
                                $roomName,
                                $secA['section'],
                                $blockA['day_of_week'],
                                $blockA['start_time'],
                                $blockA['end_time'],
                            );

                            $allConflicts[] = [
                                'client_key' => $keyA,
                                'kind' => 'room',
                                'severity' => 'blocking',
                                'schedule_index' => $blockA['index'],
                                'message' => $msgA,
                                'day_of_week' => $blockA['day_of_week'],
                                'start_time' => $blockA['start_time'],
                                'end_time' => $blockA['end_time'],
                                'with_class' => ['id' => $secB['class_id'] ?? 0, 'label' => 'Sec '.$secB['section']],
                            ];
                            $allConflicts[] = [
                                'client_key' => $keyB,
                                'kind' => 'room',
                                'severity' => 'blocking',
                                'schedule_index' => $blockB['index'],
                                'message' => $msgB,
                                'day_of_week' => $blockB['day_of_week'],
                                'start_time' => $blockB['start_time'],
                                'end_time' => $blockB['end_time'],
                                'with_class' => ['id' => $secA['class_id'] ?? 0, 'label' => 'Sec '.$secA['section']],
                            ];

                            $sectionErrors[$keyA][] = $msgA;
                            $sectionErrors[$keyB][] = $msgB;
                            $sectionHasBlocking[$keyA] = true;
                            $sectionHasBlocking[$keyB] = true;
                        }

                        // Cross-section Faculty collision
                        if (
                            $secA['faculty_id'] !== null
                            && $secB['faculty_id'] !== null
                            && $secA['faculty_id'] === $secB['faculty_id']
                            && ScheduleDelivery::checksFacultyConflicts($blockA['delivery_mode'], $policy)
                            && ScheduleDelivery::checksFacultyConflicts($blockB['delivery_mode'], $policy)
                        ) {
                            $facName = $facultyNamesById[$secA['faculty_id']] ?? 'Assigned faculty';
                            $msgA = sprintf(
                                '%s is double-booked with staged Sec %s (%s %s–%s).',
                                $facName,
                                $secB['section'],
                                $blockB['day_of_week'],
                                $blockB['start_time'],
                                $blockB['end_time'],
                            );
                            $msgB = sprintf(
                                '%s is double-booked with staged Sec %s (%s %s–%s).',
                                $facName,
                                $secA['section'],
                                $blockA['day_of_week'],
                                $blockA['start_time'],
                                $blockA['end_time'],
                            );

                            $allConflicts[] = [
                                'client_key' => $keyA,
                                'kind' => 'faculty',
                                'severity' => 'blocking',
                                'schedule_index' => $blockA['index'],
                                'message' => $msgA,
                                'day_of_week' => $blockA['day_of_week'],
                                'start_time' => $blockA['start_time'],
                                'end_time' => $blockA['end_time'],
                                'with_class' => ['id' => $secB['class_id'] ?? 0, 'label' => 'Sec '.$secB['section']],
                            ];
                            $allConflicts[] = [
                                'client_key' => $keyB,
                                'kind' => 'faculty',
                                'severity' => 'blocking',
                                'schedule_index' => $blockB['index'],
                                'message' => $msgB,
                                'day_of_week' => $blockB['day_of_week'],
                                'start_time' => $blockB['start_time'],
                                'end_time' => $blockB['end_time'],
                                'with_class' => ['id' => $secA['class_id'] ?? 0, 'label' => 'Sec '.$secA['section']],
                            ];

                            $sectionErrors[$keyA][] = $msgA;
                            $sectionErrors[$keyB][] = $msgB;
                            $sectionHasBlocking[$keyA] = true;
                            $sectionHasBlocking[$keyB] = true;
                        }
                    }
                }
            }
        }

        $totals = [
            'sections' => count($staged),
            'seats' => $totalSeats,
            'scheduled_hours' => round($totalScheduledMinutes / 60, 2),
            'required_hours' => $requiredHoursPerSection !== null
                ? round($requiredHoursPerSection * count($staged), 2)
                : null,
        ];

        $anyBlocking = in_array(true, $sectionHasBlocking, true);
        $anyStructuralError = in_array(true, $sectionStructuralErrors, true);
        $blockingCount = collect($allConflicts)
            ->filter(fn (array $conflict): bool => ($conflict['severity'] ?? '') === 'blocking')
            ->count();

        // Dry run: ZERO DB writes
        if ($dryRun) {
            $results = array_map(
                static fn (array $sec): array => [
                    'client_key' => $sec['client_key'],
                    'class_id' => $sec['class_id'],
                    'section' => $sec['section'],
                    'status' => $sectionHasBlocking[$sec['client_key']] ? 'error' : 'valid',
                    'errors' => array_values(array_unique($sectionErrors[$sec['client_key']])),
                ],
                $staged,
            );

            return [
                'status' => 200,
                'body' => [
                    'ok' => ! $anyBlocking,
                    'dry_run' => true,
                    'results' => $results,
                    'conflicts' => $allConflicts,
                    'totals' => $totals,
                    'message' => $anyBlocking
                        ? sprintf('Dry run found %d blocking issue(s) across %d section(s).', max(1, $blockingCount), count($staged))
                        : sprintf('All %d section(s) validated with no blocking conflicts.', count($staged)),
                ],
            ];
        }

        // Hard Publish Guard: block commit if publishing with any blocking conflict, or on any structural error
        if (($scheduleStatus === 'published' && $anyBlocking) || $anyStructuralError) {
            $results = array_map(
                static fn (array $sec): array => [
                    'client_key' => $sec['client_key'],
                    'class_id' => $sec['class_id'],
                    'section' => $sec['section'],
                    'status' => $sectionHasBlocking[$sec['client_key']] ? 'error' : 'valid',
                    'errors' => array_values(array_unique($sectionErrors[$sec['client_key']])),
                ],
                $staged,
            );

            return [
                'status' => 422,
                'body' => [
                    'ok' => false,
                    'dry_run' => false,
                    'results' => $results,
                    'conflicts' => $allConflicts,
                    'totals' => $totals,
                    'message' => sprintf(
                        'Cannot publish blueprint: %d blocking conflict(s) detected. Resolve conflicts or save as draft.',
                        max(1, $blockingCount),
                    ),
                ],
            ];
        }

        // Extract safe boolean policy settings from blueprint.settings (never touches banner_image or custom)
        $safePolicySettings = [];
        $rawBlueprintSettings = is_array($blueprint['settings'] ?? null) ? $blueprint['settings'] : [];
        foreach (self::POLICY_SETTING_KEYS as $policyKey) {
            if (array_key_exists($policyKey, $rawBlueprintSettings)) {
                $safePolicySettings[$policyKey] = (bool) $rawBlueprintSettings[$policyKey];
            }
        }

        $results = DB::transaction(function () use (
            $staged,
            $classification,
            $courseCodes,
            $subjectIds,
            $subjectCode,
            $academicYear,
            $shsTrackId,
            $shsStrandId,
            $gradeLevel,
            $schoolYear,
            $semester,
            $startDate,
            $defaultMode,
            $safePolicySettings,
            $scheduleStatus,
            $notifyEnrolled,
            $actor,
        ): array {
            $createdOrUpdated = [];

            foreach ($staged as $sec) {
                $sharedAttributes = [
                    'classification' => $classification,
                    'school_year' => $schoolYear,
                    'semester' => $semester,
                    'start_date' => $startDate,
                    'delivery_mode' => $defaultMode,
                    'section' => $sec['section'],
                    'faculty_id' => $sec['faculty_id'],
                    'maximum_slots' => $sec['maximum_slots'],
                    'room_id' => ScheduleDelivery::isOnline($defaultMode) ? null : $sec['room_id'],
                ];

                if ($classification === 'shs') {
                    $sharedAttributes['subject_code'] = $subjectCode;
                    $sharedAttributes['shs_track_id'] = $shsTrackId;
                    $sharedAttributes['shs_strand_id'] = $shsStrandId;
                    $sharedAttributes['grade_level'] = $gradeLevel;
                    $sharedAttributes['course_codes'] = null;
                    $sharedAttributes['subject_ids'] = null;
                    $sharedAttributes['subject_id'] = null;
                    $sharedAttributes['academic_year'] = null;
                } else {
                    $sharedAttributes['course_codes'] = $courseCodes;
                    $sharedAttributes['subject_ids'] = $subjectIds;
                    $sharedAttributes['subject_id'] = $subjectIds[0] ?? null;
                    $sharedAttributes['subject_code'] = $subjectCode;
                    $sharedAttributes['academic_year'] = $academicYear;
                    $sharedAttributes['shs_track_id'] = null;
                    $sharedAttributes['shs_strand_id'] = null;
                    $sharedAttributes['grade_level'] = null;
                }

                $schedulePayloads = array_map(
                    static fn (array $block): array => array_filter([
                        'id' => $block['id'],
                        'day_of_week' => $block['day_of_week'],
                        'start_time' => $block['start_time'],
                        'end_time' => $block['end_time'],
                        'room_id' => ScheduleDelivery::isOnline($block['delivery_mode']) ? null : $block['room_id'],
                        'delivery_mode' => $block['delivery_mode'],
                        'component_type' => $block['component_type'],
                        'status' => $scheduleStatus,
                    ], static fn (mixed $value, string $key): bool => $key !== 'id' || $value !== null, ARRAY_FILTER_USE_BOTH),
                    $sec['blocks'],
                );

                if ($sec['class_id'] === null) {
                    $sharedAttributes['settings'] = array_merge(Classes::getDefaultSettings(), $safePolicySettings);
                    $class = Classes::query()->create($sharedAttributes);

                    $this->schedulingService->syncClassSchedules(
                        $class,
                        $schedulePayloads,
                        $actor,
                        oldSnapshot: [],
                        matchLegacy: false,
                        scheduleStatus: $scheduleStatus,
                        notifyEnrolled: false,
                    );

                    $createdOrUpdated[] = [
                        'client_key' => $sec['client_key'],
                        'class_id' => (int) $class->id,
                        'section' => $sec['section'],
                        'status' => 'created',
                        'errors' => [],
                    ];
                } else {
                    $existing = Classes::query()->lockForUpdate()->findOrFail($sec['class_id']);
                    $oldSnapshot = $this->notificationService->snapshot($existing);

                    // Preserve existing banner_image, visual settings, and custom keys
                    $mergedSettings = array_merge(
                        Classes::getDefaultSettings(),
                        (array) ($existing->settings ?? []),
                        $safePolicySettings,
                    );

                    $existing->fill($sharedAttributes);
                    $existing->settings = $mergedSettings;
                    $existing->save();

                    $this->schedulingService->syncClassSchedules(
                        $existing,
                        $schedulePayloads,
                        $actor,
                        oldSnapshot: $oldSnapshot,
                        matchLegacy: false,
                        scheduleStatus: $scheduleStatus,
                        notifyEnrolled: $notifyEnrolled,
                    );

                    $createdOrUpdated[] = [
                        'client_key' => $sec['client_key'],
                        'class_id' => (int) $existing->id,
                        'section' => $sec['section'],
                        'status' => 'updated',
                        'errors' => [],
                    ];
                }
            }

            return $createdOrUpdated;
        });

        $createdCount = collect($results)->where('status', 'created')->count();
        $updatedCount = collect($results)->where('status', 'updated')->count();
        $parts = [];
        if ($createdCount > 0) {
            $parts[] = sprintf('%d created', $createdCount);
        }
        if ($updatedCount > 0) {
            $parts[] = sprintf('%d updated', $updatedCount);
        }

        return [
            'status' => 200,
            'body' => [
                'ok' => true,
                'dry_run' => false,
                'results' => $results,
                'conflicts' => $allConflicts,
                'totals' => $totals,
                'message' => sprintf(
                    'Blueprint saved (%s) as %s.',
                    implode(', ', $parts),
                    $scheduleStatus,
                ),
            ],
        ];
    }

    /**
     * @param  array<int, mixed>  $rawSchedules
     * @return list<array{
     *     index: int,
     *     id: int|null,
     *     day_of_week: string,
     *     start_time: string,
     *     end_time: string,
     *     start_min: int|null,
     *     end_min: int|null,
     *     room_id: int|null,
     *     delivery_mode: string,
     *     component_type: string,
     *     status: string|null
     * }>
     */
    private function normalizeBlocks(array $rawSchedules, string $defaultMode, ?int $defaultRoomId): array
    {
        $normalized = [];

        foreach (array_values($rawSchedules) as $index => $raw) {
            if (! is_array($raw)) {
                continue;
            }

            $day = is_string($raw['day_of_week'] ?? null) ? mb_trim($raw['day_of_week']) : '';
            $startMin = $this->timeToMinutes($raw['start_time'] ?? null);
            $endMin = $this->timeToMinutes($raw['end_time'] ?? null);
            $mode = ScheduleDelivery::canonicalize($raw['delivery_mode'] ?? $defaultMode, $defaultMode);
            $isOnline = ScheduleDelivery::isOnline($mode);
            $blockRoomId = $this->normalizePositiveInt($raw['room_id'] ?? null);
            $effectiveRoomId = $isOnline ? null : (array_key_exists('room_id', $raw) ? $blockRoomId : $defaultRoomId);
            $component = is_string($raw['component_type'] ?? null) && mb_strtolower($raw['component_type']) === 'lab'
                ? 'lab'
                : 'lecture';

            $normalized[] = [
                'index' => $index,
                'id' => $this->normalizePositiveInt($raw['id'] ?? null),
                'day_of_week' => $day,
                'start_time' => $startMin !== null ? $this->formatMinutes24h($startMin) : (string) ($raw['start_time'] ?? ''),
                'end_time' => $endMin !== null ? $this->formatMinutes24h($endMin) : (string) ($raw['end_time'] ?? ''),
                'start_min' => $startMin,
                'end_min' => $endMin,
                'room_id' => $effectiveRoomId,
                'delivery_mode' => $mode,
                'component_type' => $component,
                'status' => isset($raw['status']) && is_string($raw['status']) ? $raw['status'] : null,
            ];
        }

        return $normalized;
    }

    /**
     * @param  list<array<string, mixed>>  $blocks
     * @return array{scheduled: float, required: float|null, lecture: float, lab: float, scheduled_minutes: int}
     */
    private function computeHours(array $blocks, ?float $requiredHours): array
    {
        $lectureMinutes = 0;
        $labMinutes = 0;

        foreach ($blocks as $block) {
            if ($block['start_min'] === null || $block['end_min'] === null || $block['end_min'] <= $block['start_min']) {
                continue;
            }

            $duration = $block['end_min'] - $block['start_min'];
            if ($block['component_type'] === 'lab') {
                $labMinutes += $duration;
            } else {
                $lectureMinutes += $duration;
            }
        }

        $totalMinutes = $lectureMinutes + $labMinutes;

        return [
            'scheduled' => round($totalMinutes / 60, 2),
            'required' => $requiredHours,
            'lecture' => round($lectureMinutes / 60, 2),
            'lab' => round($labMinutes / 60, 2),
            'scheduled_minutes' => $totalMinutes,
        ];
    }

    /**
     * @param  list<int>  $subjectIds
     * @return array{0: Collection<int, Subject>, 1: float|null}
     */
    private function loadSubjectsAndRequiredHours(array $subjectIds): array
    {
        if ($subjectIds === []) {
            return [collect(), null];
        }

        $subjects = Subject::query()
            ->whereIn('id', $subjectIds)
            ->get(['id', 'code', 'title', 'lecture', 'laboratory', 'units']);

        if ($subjects->isEmpty()) {
            return [$subjects, null];
        }

        $total = 0.0;
        foreach ($subjects as $subject) {
            $lecture = (float) ($subject->lecture ?? 0);
            $lab = (float) ($subject->laboratory ?? 0);
            $units = (float) ($subject->units ?? 0);

            if ($lecture > 0 || $lab > 0) {
                $total += $lecture + $lab;
            } elseif ($units > 0) {
                $total += $units;
            }
        }

        return [$subjects, $total > 0 ? round($total, 2) : null];
    }

    /**
     * @param  Collection<int, Subject>  $subjects
     * @return list<string>
     */
    private function resolveSubjectCodes(Collection $subjects, ?string $subjectCode): array
    {
        $codes = $subjects
            ->pluck('code')
            ->filter(fn (mixed $code): bool => is_string($code) && mb_trim($code) !== '')
            ->map(fn (string $code): string => mb_strtolower(mb_trim($code)))
            ->all();

        if ($subjectCode !== null && $subjectCode !== '') {
            foreach (explode(',', $subjectCode) as $part) {
                $trimmed = mb_strtolower(mb_trim($part));
                if ($trimmed !== '') {
                    $codes[] = $trimmed;
                }
            }
        }

        return array_values(array_unique($codes));
    }

    /**
     * @param  list<array<string, mixed>>  $blocks
     * @return list<array<string, mixed>>
     */
    private function detectSelfConflicts(array $blocks, array $policy): array
    {
        $conflicts = [];
        $count = count($blocks);

        for ($i = 0; $i < $count; $i++) {
            $a = $blocks[$i];
            if (! in_array($a['day_of_week'], ScheduleDelivery::allowedDays($policy), true)) {
                $conflicts[] = [
                    'kind' => 'self',
                    'severity' => 'blocking',
                    'schedule_index' => $a['index'],
                    'message' => sprintf('Scheduling is not allowed on %s.', $a['day_of_week']),
                    'day_of_week' => $a['day_of_week'],
                    'start_time' => $a['start_time'],
                    'end_time' => $a['end_time'],
                    'with_class' => null,
                ];
            }
            if ($a['start_min'] === null || $a['end_min'] === null || $a['end_min'] <= $a['start_min']) {
                $conflicts[] = [
                    'kind' => 'self',
                    'severity' => 'blocking',
                    'schedule_index' => $a['index'],
                    'message' => 'End time must be after start time.',
                    'day_of_week' => $a['day_of_week'],
                    'start_time' => $a['start_time'],
                    'end_time' => $a['end_time'],
                    'with_class' => null,
                ];

                continue;
            }

            for ($j = $i + 1; $j < $count; $j++) {
                $b = $blocks[$j];
                if ($b['start_min'] === null || $b['end_min'] === null || $b['end_min'] <= $b['start_min']) {
                    continue;
                }

                if ($a['day_of_week'] !== $b['day_of_week']) {
                    continue;
                }

                if ($a['start_min'] < $b['end_min'] && $a['end_min'] > $b['start_min']) {
                    $conflicts[] = [
                        'kind' => 'self',
                        'severity' => 'blocking',
                        'schedule_index' => $b['index'],
                        'message' => sprintf(
                            'Overlaps with another session in this class on %s (%s–%s).',
                            $a['day_of_week'],
                            $a['start_time'],
                            $a['end_time'],
                        ),
                        'day_of_week' => $b['day_of_week'],
                        'start_time' => $b['start_time'],
                        'end_time' => $b['end_time'],
                        'with_class' => null,
                    ];
                }
            }
        }

        return $conflicts;
    }

    /**
     * @param  list<array<string, mixed>>  $blocks
     * @param  EloquentCollection<int, Classes>  $otherClasses
     * @param  array<string, mixed>  $cohortSpec
     * @param  Collection<int, Room>  $roomsById
     * @param  array<string, mixed>  $policy
     * @return list<array<string, mixed>>
     */
    private function detectExternalConflicts(
        array $blocks,
        EloquentCollection $otherClasses,
        ?string $facultyId,
        ?string $facultyName,
        array $cohortSpec,
        Collection $roomsById,
        array $policy,
    ): array {
        $conflicts = [];

        foreach ($blocks as $block) {
            if ($block['start_min'] === null || $block['end_min'] === null || $block['end_min'] <= $block['start_min']) {
                continue;
            }

            $checksRoom = ScheduleDelivery::checksRoomConflicts($block['delivery_mode'], $policy) && $block['room_id'] !== null;
            $checksFaculty = ScheduleDelivery::checksFacultyConflicts($block['delivery_mode'], $policy) && $facultyId !== null;
            $checksCohort = ScheduleDelivery::checksStudentCohortConflicts($block['delivery_mode'], $policy);

            foreach ($otherClasses as $other) {
                $otherLabel = $this->classLabel($other);
                $sameFaculty = $checksFaculty
                    && filled($other->faculty_id)
                    && (string) $other->faculty_id === $facultyId;
                $sameCohort = $checksCohort && $this->sharesCohortWithSpec($cohortSpec, $other);

                foreach ($other->schedules as $otherSchedule) {
                    if (! $otherSchedule instanceof Schedule || ! $otherSchedule->start_time || ! $otherSchedule->end_time) {
                        continue;
                    }

                    if ((string) $otherSchedule->day_of_week !== $block['day_of_week']) {
                        continue;
                    }

                    $otherStartMin = (int) $otherSchedule->start_time->format('H') * 60 + (int) $otherSchedule->start_time->format('i');
                    $otherEndMin = (int) $otherSchedule->end_time->format('H') * 60 + (int) $otherSchedule->end_time->format('i');

                    if ($block['start_min'] >= $otherEndMin || $block['end_min'] <= $otherStartMin) {
                        continue;
                    }

                    $otherStartStr = $otherSchedule->start_time->format('H:i');
                    $otherEndStr = $otherSchedule->end_time->format('H:i');

                    if (
                        $checksRoom
                        && ScheduleDelivery::checksRoomConflicts($otherSchedule->delivery_mode, $policy)
                        && $otherSchedule->room_id !== null
                        && (int) $otherSchedule->room_id === $block['room_id']
                    ) {
                        $roomName = $roomsById->get($block['room_id'])?->name ?? $otherSchedule->room?->name ?? ('Room #'.$block['room_id']);
                        $conflicts[] = [
                            'kind' => 'room',
                            'severity' => 'blocking',
                            'schedule_index' => $block['index'],
                            'message' => sprintf(
                                'Room %s is already booked by %s (%s %s–%s).',
                                $roomName,
                                $otherLabel,
                                $block['day_of_week'],
                                $otherStartStr,
                                $otherEndStr,
                            ),
                            'day_of_week' => $block['day_of_week'],
                            'start_time' => $block['start_time'],
                            'end_time' => $block['end_time'],
                            'with_class' => ['id' => (int) $other->id, 'label' => $otherLabel],
                        ];
                    }

                    if (
                        $sameFaculty
                        && ScheduleDelivery::checksFacultyConflicts($otherSchedule->delivery_mode, $policy)
                    ) {
                        $conflicts[] = [
                            'kind' => 'faculty',
                            'severity' => 'blocking',
                            'schedule_index' => $block['index'],
                            'message' => sprintf(
                                '%s is already teaching %s (%s %s–%s).',
                                $facultyName ?? 'Assigned instructor',
                                $otherLabel,
                                $block['day_of_week'],
                                $otherStartStr,
                                $otherEndStr,
                            ),
                            'day_of_week' => $block['day_of_week'],
                            'start_time' => $block['start_time'],
                            'end_time' => $block['end_time'],
                            'with_class' => ['id' => (int) $other->id, 'label' => $otherLabel],
                        ];
                    }

                    if (
                        $sameCohort
                        && ScheduleDelivery::checksStudentCohortConflicts($otherSchedule->delivery_mode, $policy)
                    ) {
                        $conflicts[] = [
                            'kind' => 'cohort',
                            'severity' => 'warning',
                            'schedule_index' => $block['index'],
                            'message' => sprintf(
                                'Overlaps with cohort class %s (%s %s–%s).',
                                $otherLabel,
                                $block['day_of_week'],
                                $otherStartStr,
                                $otherEndStr,
                            ),
                            'day_of_week' => $block['day_of_week'],
                            'start_time' => $block['start_time'],
                            'end_time' => $block['end_time'],
                            'with_class' => ['id' => (int) $other->id, 'label' => $otherLabel],
                        ];
                    }
                }
            }
        }

        return $conflicts;
    }

    /**
     * @param  EloquentCollection<int, Classes>  $otherClasses
     * @param  list<int>  $targetRoomIds
     * @param  array<string, mixed>  $cohortSpec
     * @param  array<string, mixed>  $policy
     * @return array{faculty_busy: list<array<string, mixed>>, room_busy: list<array<string, mixed>>, cohort_busy: list<array<string, mixed>>}
     */
    private function buildBusyOverlay(
        EloquentCollection $otherClasses,
        ?string $facultyId,
        array $targetRoomIds,
        array $cohortSpec,
        array $policy,
    ): array {
        $facultyBusy = [];
        $roomBusy = [];
        $cohortBusy = [];

        foreach ($otherClasses as $other) {
            $sameFaculty = $facultyId !== null
                && filled($other->faculty_id)
                && (string) $other->faculty_id === $facultyId;
            $sameCohort = $this->sharesCohortWithSpec($cohortSpec, $other);

            $sortedSchedules = $other->schedules
                ->sortBy([
                    fn (Schedule $s): int => self::DAY_ORDER[(string) $s->day_of_week] ?? 99,
                    fn (Schedule $s): string => $s->start_time?->format('H:i') ?? '99:99',
                    fn (Schedule $s): int => (int) $s->id,
                ])
                ->values();

            foreach ($sortedSchedules as $schedule) {
                if (! $schedule->start_time || ! $schedule->end_time) {
                    continue;
                }

                $baseBlock = [
                    'class_id' => (int) $other->id,
                    'subject_code' => (string) ($other->subject_code ?? 'Class'),
                    'section' => (string) ($other->section ?? ''),
                    'day_of_week' => (string) $schedule->day_of_week,
                    'start_time' => $schedule->start_time->format('H:i'),
                    'end_time' => $schedule->end_time->format('H:i'),
                    'room_name' => $schedule->isOnline()
                        ? ScheduleDelivery::locationLabel($schedule->delivery_mode)
                        : ($schedule->room?->name ?? null),
                ];

                if ($sameFaculty && ScheduleDelivery::checksFacultyConflicts($schedule->delivery_mode, $policy)) {
                    $facultyBusy[] = [...$baseBlock, 'kind' => 'faculty'];
                }

                if (
                    $targetRoomIds !== []
                    && ScheduleDelivery::checksRoomConflicts($schedule->delivery_mode, $policy)
                    && $schedule->room_id !== null
                    && in_array((int) $schedule->room_id, $targetRoomIds, true)
                ) {
                    $roomBusy[] = [...$baseBlock, 'kind' => 'room'];
                }

                if ($sameCohort && ScheduleDelivery::checksStudentCohortConflicts($schedule->delivery_mode, $policy)) {
                    $cohortBusy[] = [...$baseBlock, 'kind' => 'cohort'];
                }
            }
        }

        return [
            'faculty_busy' => $facultyBusy,
            'room_busy' => $roomBusy,
            'cohort_busy' => $cohortBusy,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $blocks
     * @param  list<array<string, mixed>>  $conflicts
     * @param  EloquentCollection<int, Classes>  $otherClasses
     * @param  Collection<int, Room>  $activeRooms
     * @param  Collection<int, Room>  $roomsById
     * @param  array<string, mixed>  $cohortSpec
     * @param  array<string, mixed>  $policy
     * @return list<array<string, mixed>>
     */
    private function buildSuggestions(
        array $blocks,
        array $conflicts,
        EloquentCollection $otherClasses,
        Collection $activeRooms,
        Collection $roomsById,
        ?string $facultyId,
        array $cohortSpec,
        int $maximumSlots,
        array $policy,
        ?Classes $existingClass,
    ): array {
        $conflictKindsByIdx = [];
        foreach ($conflicts as $conflict) {
            $idx = $conflict['schedule_index'] ?? null;
            $kind = $conflict['kind'] ?? null;
            if (is_int($idx) && in_array($kind, ['room', 'capacity', 'faculty'], true)) {
                $conflictKindsByIdx[$idx][$kind] = true;
            }
        }

        if ($conflictKindsByIdx === []) {
            return [];
        }

        // Flatten external term blocks once for fast free-room & free-slot evaluation
        $externalFlat = [];
        foreach ($otherClasses as $other) {
            $isSameFaculty = $facultyId !== null && filled($other->faculty_id) && (string) $other->faculty_id === $facultyId;
            $isSameCohort = $this->sharesCohortWithSpec($cohortSpec, $other);

            foreach ($other->schedules as $otherSchedule) {
                if (! $otherSchedule instanceof Schedule || ! $otherSchedule->start_time || ! $otherSchedule->end_time) {
                    continue;
                }

                $externalFlat[] = [
                    'day' => (string) $otherSchedule->day_of_week,
                    'start_min' => (int) $otherSchedule->start_time->format('H') * 60 + (int) $otherSchedule->start_time->format('i'),
                    'end_min' => (int) $otherSchedule->end_time->format('H') * 60 + (int) $otherSchedule->end_time->format('i'),
                    'room_id' => $otherSchedule->room_id !== null ? (int) $otherSchedule->room_id : null,
                    'delivery_mode' => $otherSchedule->delivery_mode ?? Classes::DELIVERY_FACE_TO_FACE,
                    'same_faculty' => $isSameFaculty,
                    'same_cohort' => $isSameCohort,
                ];
            }
        }

        $suggestions = [];
        $allowedDays = ScheduleDelivery::allowedDays($policy);
        $baseImpactBlocks = $existingClass instanceof Classes ? $this->toImpactBlocks($blocks) : [];

        foreach ($blocks as $block) {
            $idx = $block['index'];
            if (! isset($conflictKindsByIdx[$idx])) {
                continue;
            }
            if ($block['start_min'] === null || $block['end_min'] === null || $block['end_min'] <= $block['start_min']) {
                continue;
            }

            $kinds = $conflictKindsByIdx[$idx];
            $isOnline = ScheduleDelivery::isOnline($block['delivery_mode']);
            $isLab = $block['component_type'] === 'lab';

            // 1. Up to 3 free alternative rooms at the exact day/time fitting maximum_slots
            if (! $isOnline && (isset($kinds['room']) || isset($kinds['capacity']))) {
                $busyRoomIds = [];
                foreach ($externalFlat as $ext) {
                    if ($ext['day'] !== $block['day_of_week']) {
                        continue;
                    }
                    if ($ext['start_min'] >= $block['end_min'] || $ext['end_min'] <= $block['start_min']) {
                        continue;
                    }
                    if ($ext['room_id'] !== null && ScheduleDelivery::checksRoomConflicts($ext['delivery_mode'], $policy)) {
                        $busyRoomIds[$ext['room_id']] = true;
                    }
                }

                foreach ($blocks as $siblingBlock) {
                    if ($siblingBlock['index'] === $idx || $siblingBlock['room_id'] === null) {
                        continue;
                    }
                    if ($siblingBlock['day_of_week'] !== $block['day_of_week']) {
                        continue;
                    }
                    if ($siblingBlock['start_min'] === null || $siblingBlock['end_min'] === null) {
                        continue;
                    }
                    if ($siblingBlock['start_min'] < $block['end_min'] && $siblingBlock['end_min'] > $block['start_min']) {
                        $busyRoomIds[$siblingBlock['room_id']] = true;
                    }
                }

                $roomCandidates = [];
                foreach ($activeRooms as $room) {
                    if ($block['room_id'] !== null && (int) $room->id === $block['room_id']) {
                        continue;
                    }
                    if (isset($busyRoomIds[(int) $room->id])) {
                        continue;
                    }

                    $capacity = $room->capacity !== null ? (int) $room->capacity : null;
                    if ($maximumSlots > 0 && ($capacity === null || $capacity < $maximumSlots)) {
                        continue;
                    }

                    $roomType = mb_strtolower((string) ($room->type ?? 'lecture'));
                    $typeMatch = $isLab ? str_contains($roomType, 'lab') : ! str_contains($roomType, 'lab');
                    $score = ($typeMatch ? 0 : 250) + ($capacity !== null ? abs($capacity - $maximumSlots) : 500);

                    $roomCandidates[] = [
                        'room' => $room,
                        'capacity' => $capacity,
                        'score' => $score,
                    ];
                }

                usort($roomCandidates, static fn (array $a, array $b): int => [$a['score'], $a['room']->name] <=> [$b['score'], $b['room']->name]);

                foreach (array_slice($roomCandidates, 0, 3) as $candidate) {
                    /** @var Room $room */
                    $room = $candidate['room'];
                    $cap = $candidate['capacity'];
                    $suggestions[] = [
                        'schedule_index' => $idx,
                        'kind' => 'room',
                        'label' => $cap !== null ? sprintf('%s (%d seats)', $room->name, $cap) : $room->name,
                        'room_id' => (int) $room->id,
                        'room_name' => $room->name,
                        'capacity' => $cap,
                        'day_of_week' => $block['day_of_week'],
                        'start_time' => $block['start_time'],
                        'end_time' => $block['end_time'],
                    ];
                }
            }

            // 2. Up to 2 alternative free time slots when faculty clashes or editing an existing class
            if (isset($kinds['faculty']) || $existingClass instanceof Classes) {
                $duration = $block['end_min'] - $block['start_min'];
                $orderedDays = array_values(array_unique(array_merge(
                    in_array($block['day_of_week'], $allowedDays, true) ? [$block['day_of_week']] : [],
                    $allowedDays,
                )));

                $checksRoom = ! $isOnline && $block['room_id'] !== null && ScheduleDelivery::checksRoomConflicts($block['delivery_mode'], $policy);
                $checksFaculty = $facultyId !== null && ScheduleDelivery::checksFacultyConflicts($block['delivery_mode'], $policy);
                $checksCohort = ScheduleDelivery::checksStudentCohortConflicts($block['delivery_mode'], $policy);

                $slotCandidates = [];
                foreach ($orderedDays as $dayIndex => $candDay) {
                    for ($candStart = 7 * 60; $candStart <= 20 * 60; $candStart += 30) {
                        $candEnd = $candStart + $duration;
                        if ($candEnd > 21 * 60) {
                            continue;
                        }
                        if ($candDay === $block['day_of_week'] && $candStart === $block['start_min']) {
                            continue;
                        }

                        if ($this->candidateSlotConflicts(
                            $idx,
                            $candDay,
                            $candStart,
                            $candEnd,
                            $block['room_id'],
                            $checksRoom,
                            $checksFaculty,
                            $checksCohort,
                            $blocks,
                            $externalFlat,
                            $policy,
                        )) {
                            continue;
                        }

                        $sameDay = $candDay === $block['day_of_week'];
                        $score = ($sameDay ? 0 : 120 + ($dayIndex * 30)) + abs($candStart - $block['start_min']);

                        $slotCandidates[] = [
                            'day_of_week' => $candDay,
                            'start_time' => $this->formatMinutes24h($candStart),
                            'end_time' => $this->formatMinutes24h($candEnd),
                            'score' => $score,
                        ];
                    }
                }

                usort($slotCandidates, static fn (array $a, array $b): int => $a['score'] <=> $b['score']);
                $topSlots = array_slice($slotCandidates, 0, $existingClass instanceof Classes ? 6 : 2);
                $effectiveRoom = $block['room_id'] !== null ? $roomsById->get($block['room_id']) : null;

                if ($existingClass instanceof Classes && $topSlots !== []) {
                    $enriched = [];
                    foreach ($topSlots as $rankIdx => $slot) {
                        $mutatedBlocks = $baseImpactBlocks;
                        foreach ($mutatedBlocks as &$mBlock) {
                            if ((int) ($mBlock['_index'] ?? -1) === $idx) {
                                $mBlock['day_of_week'] = $slot['day_of_week'];
                                $mBlock['start_time'] = $slot['start_time'];
                                $mBlock['end_time'] = $slot['end_time'];
                            }
                        }
                        unset($mBlock);

                        $slotImpact = $this->scheduleWorkspace->previewRescheduleImpact($existingClass, $mutatedBlocks);
                        $enriched[] = [
                            ...$slot,
                            'student_clashes_count' => (int) ($slotImpact['student_impact']['clashing_count'] ?? 0),
                            'student_clear_count' => (int) ($slotImpact['student_impact']['clear_count'] ?? 0),
                            '_rank' => $rankIdx,
                        ];
                    }

                    usort($enriched, static fn (array $a, array $b): int => [$a['student_clashes_count'], $a['_rank']] <=> [$b['student_clashes_count'], $b['_rank']]);
                    $topSlots = array_slice($enriched, 0, 2);
                }

                foreach ($topSlots as $slot) {
                    $item = [
                        'schedule_index' => $idx,
                        'kind' => 'slot',
                        'label' => sprintf('%s %s–%s', $slot['day_of_week'], $slot['start_time'], $slot['end_time']),
                        'room_id' => $isOnline ? null : $block['room_id'],
                        'room_name' => $isOnline
                            ? ScheduleDelivery::locationLabel($block['delivery_mode'])
                            : ($effectiveRoom?->name ?? null),
                        'day_of_week' => $slot['day_of_week'],
                        'start_time' => $slot['start_time'],
                        'end_time' => $slot['end_time'],
                    ];

                    if (isset($slot['student_clashes_count'])) {
                        $item['student_clashes_count'] = $slot['student_clashes_count'];
                        $item['student_clear_count'] = $slot['student_clear_count'];
                    }

                    $suggestions[] = $item;
                }
            }
        }

        return $suggestions;
    }

    /**
     * @param  list<array<string, mixed>>  $blocks
     * @param  list<array<string, mixed>>  $externalFlat
     * @param  array<string, mixed>  $policy
     */
    private function candidateSlotConflicts(
        int $selfIdx,
        string $day,
        int $startMin,
        int $endMin,
        ?int $roomId,
        bool $checksRoom,
        bool $checksFaculty,
        bool $checksCohort,
        array $blocks,
        array $externalFlat,
        array $policy,
    ): bool {
        foreach ($blocks as $otherBlock) {
            if ($otherBlock['index'] === $selfIdx) {
                continue;
            }
            if ($otherBlock['day_of_week'] !== $day || $otherBlock['start_min'] === null || $otherBlock['end_min'] === null) {
                continue;
            }
            if ($startMin < $otherBlock['end_min'] && $endMin > $otherBlock['start_min']) {
                return true;
            }
        }

        foreach ($externalFlat as $ext) {
            if ($ext['day'] !== $day) {
                continue;
            }
            if ($ext['start_min'] >= $endMin || $ext['end_min'] <= $startMin) {
                continue;
            }

            if ($checksRoom && $roomId !== null && $ext['room_id'] === $roomId && ScheduleDelivery::checksRoomConflicts($ext['delivery_mode'], $policy)) {
                return true;
            }

            if ($checksFaculty && $ext['same_faculty'] && ScheduleDelivery::checksFacultyConflicts($ext['delivery_mode'], $policy)) {
                return true;
            }

            if ($checksCohort && $ext['same_cohort'] && ScheduleDelivery::checksStudentCohortConflicts($ext['delivery_mode'], $policy)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check whether a candidate class belongs to the same student cohort as the staged spec,
     * excluding sibling sections of the exact same subject.
     *
     * @param  array<string, mixed>  $spec
     */
    private function sharesCohortWithSpec(array $spec, Classes $other): bool
    {
        $otherClassification = (string) ($other->classification ?: 'college');
        if ($spec['classification'] !== $otherClassification) {
            return false;
        }

        // Exclude sibling sections of the exact same subject
        if ($this->isSameSubjectAsSpec($spec, $other)) {
            return false;
        }

        if ($spec['classification'] === 'shs') {
            return $spec['shs_strand_id'] !== null
                && (int) $other->shs_strand_id === (int) $spec['shs_strand_id']
                && $spec['grade_level'] !== null
                && (string) $other->grade_level === (string) $spec['grade_level'];
        }

        if ($spec['academic_year'] === null || empty($other->academic_year)) {
            return false;
        }

        if ((int) $spec['academic_year'] !== (int) $other->academic_year) {
            return false;
        }

        $specCourses = (array) ($spec['course_codes'] ?? []);
        if ($specCourses === []) {
            return false;
        }

        $otherCourses = $this->normalizeIntList($other->course_codes ?? []);

        return $otherCourses !== [] && array_intersect($specCourses, $otherCourses) !== [];
    }

    /**
     * @param  array<string, mixed>  $spec
     */
    private function isSameSubjectAsSpec(array $spec, Classes $other): bool
    {
        $specSubjectIds = (array) ($spec['subject_ids'] ?? []);
        $otherSubjectIds = $this->normalizeIntList($other->subject_ids ?? []);
        if ($other->subject_id !== null && (int) $other->subject_id > 0) {
            $otherSubjectIds[] = (int) $other->subject_id;
        }
        $otherSubjectIds = array_values(array_unique($otherSubjectIds));

        if ($specSubjectIds !== [] && $otherSubjectIds !== [] && array_intersect($specSubjectIds, $otherSubjectIds) !== []) {
            return true;
        }

        $specCodes = (array) ($spec['subject_codes'] ?? []);
        if ($specCodes !== [] && filled($other->subject_code)) {
            $otherCodes = array_values(array_filter(array_map(
                static fn (string $code): string => mb_strtolower(mb_trim($code)),
                explode(',', (string) $other->subject_code),
            )));

            if ($otherCodes !== [] && array_intersect($specCodes, $otherCodes) !== []) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  EloquentCollection<int, Classes>  $classes
     */
    private function computeFacultyTermMinutes(EloquentCollection $classes, string $facultyId): int
    {
        $minutes = 0;

        foreach ($classes as $class) {
            if (! filled($class->faculty_id) || (string) $class->faculty_id !== $facultyId) {
                continue;
            }

            foreach ($class->schedules as $schedule) {
                if (! $schedule instanceof Schedule || ! $schedule->start_time || ! $schedule->end_time) {
                    continue;
                }

                $startMin = (int) $schedule->start_time->format('H') * 60 + (int) $schedule->start_time->format('i');
                $endMin = (int) $schedule->end_time->format('H') * 60 + (int) $schedule->end_time->format('i');
                if ($endMin > $startMin) {
                    $minutes += ($endMin - $startMin);
                }
            }
        }

        return $minutes;
    }

    /**
     * @param  list<int>  $excludeClassIds
     * @return EloquentCollection<int, Classes>
     */
    private function loadTermClasses(string $schoolYear, int|string $semester, array $excludeClassIds = []): EloquentCollection
    {
        $variants = $this->schoolYearVariants($schoolYear);

        return Classes::query()
            ->when($excludeClassIds !== [], fn ($q) => $q->whereNotIn('id', $excludeClassIds))
            ->whereIn('school_year', $variants)
            ->where('semester', $semester)
            ->with([
                'schedules.room:id,name,capacity',
                'Faculty:id,first_name,last_name,middle_name',
            ])
            ->get([
                'id',
                'subject_id',
                'subject_ids',
                'subject_code',
                'section',
                'faculty_id',
                'classification',
                'academic_year',
                'course_codes',
                'shs_strand_id',
                'grade_level',
                'room_id',
                'delivery_mode',
                'school_year',
                'semester',
                'maximum_slots',
            ]);
    }

    /**
     * @param  list<array<string, mixed>>  $blocks
     * @return list<array<string, mixed>>
     */
    private function toImpactBlocks(array $blocks): array
    {
        $impactBlocks = [];
        foreach ($blocks as $block) {
            if ($block['start_min'] === null || $block['end_min'] === null || $block['end_min'] <= $block['start_min']) {
                continue;
            }

            $impactBlocks[] = [
                '_index' => $block['index'],
                'day_of_week' => $block['day_of_week'],
                'start_time' => $block['start_time'],
                'end_time' => $block['end_time'],
                'room_id' => $block['room_id'],
                'delivery_mode' => $block['delivery_mode'],
                'component_type' => $block['component_type'],
            ];
        }

        return $impactBlocks;
    }

    private function classLabel(Classes $class): string
    {
        $code = mb_trim((string) ($class->subject_code ?? ''));
        $section = mb_trim((string) ($class->section ?? ''));

        if ($code !== '' && $section !== '') {
            return $code.' - '.$section;
        }

        return $code !== '' ? $code : ($section !== '' ? 'Sec '.$section : 'Class #'.$class->id);
    }

    /**
     * @return list<string>
     */
    private function schoolYearVariants(string $schoolYear): array
    {
        $raw = mb_trim($schoolYear) !== ''
            ? mb_trim($schoolYear)
            : $this->settingsService->getCurrentSchoolYearString();

        $normalized = GeneralSettingsService::normalizeSchoolYear($raw);
        $compact = str_replace(' ', '', $normalized);

        return array_values(array_unique([$raw, $normalized, $compact]));
    }

    private function resolveSemester(mixed $semester): int|string
    {
        if (is_int($semester)) {
            return $semester;
        }

        if (is_string($semester)) {
            $trimmed = mb_trim($semester);
            if ($trimmed !== '' && ctype_digit($trimmed)) {
                return (int) $trimmed;
            }
            if ($trimmed === 'summer') {
                return 'summer';
            }
        }

        return $this->settingsService->getCurrentSemester();
    }

    private function normalizeFacultyId(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $trimmed = mb_trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
    }

    private function normalizePositiveInt(mixed $value): ?int
    {
        if (! is_numeric($value)) {
            return null;
        }

        $int = (int) $value;

        return $int > 0 ? $int : null;
    }

    /**
     * @return list<int>
     */
    private function normalizeIntList(mixed $values): array
    {
        if (! is_array($values)) {
            return [];
        }

        return collect($values)
            ->filter(fn (mixed $id): bool => is_numeric($id))
            ->map(fn (mixed $id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values()
            ->all();
    }

    private function nullableTrimmed(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = mb_trim($value);

        return $trimmed === '' ? null : $trimmed;
    }

    private function timeToMinutes(mixed $time): ?int
    {
        if (! is_string($time) || preg_match('/^(\d{1,2}):(\d{2})/', mb_trim($time), $matches) !== 1) {
            return null;
        }

        $hours = (int) $matches[1];
        $minutes = (int) $matches[2];

        if ($hours > 23 || $minutes > 59) {
            return null;
        }

        return ($hours * 60) + $minutes;
    }

    private function formatMinutes24h(int $totalMinutes): string
    {
        return sprintf('%02d:%02d', intdiv($totalMinutes, 60), $totalMinutes % 60);
    }
}
