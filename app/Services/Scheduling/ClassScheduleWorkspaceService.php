<?php

declare(strict_types=1);

namespace App\Services\Scheduling;

use App\Models\ClassEnrollment;
use App\Models\Classes;
use App\Models\EnrollmentClassWaitlist;
use App\Models\Room;
use App\Models\Schedule;
use App\Models\User;
use App\Services\EnrollmentWaitlistService;
use App\Services\GeneralSettingsService;
use App\Support\ScheduleDelivery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

final readonly class ClassScheduleWorkspaceService
{
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
        private SchedulingService $schedulingService,
        private ScheduleConflictService $conflictService,
        private GeneralSettingsService $settingsService,
        private EnrollmentWaitlistService $waitlistService,
    ) {}

    /**
     * Summarize schedule health, room assignments, conflict status, and waitlist counts
     * for a page of Classes models using batched queries with zero N+1 overhead.
     *
     * @param  Collection<int, Classes>  $classes
     * @return array<int, array{
     *     room: string|null,
     *     room_capacity: int|null,
     *     schedules: list<array{
     *         id: int,
     *         day_of_week: string,
     *         start_time: string|null,
     *         end_time: string|null,
     *         component_type: string,
     *         delivery_mode: string,
     *         room_id: int|null,
     *         room_name: string|null,
     *         room_capacity: int|null,
     *         status: string,
     *         has_conflict: bool,
     *         is_overcrowded: bool
     *     }>,
     *     schedule_health: array{
     *         scheduled_hours: float,
     *         required_hours: float,
     *         is_complete: bool,
     *         status: 'published'|'draft'|'unscheduled',
     *         has_conflict: bool,
     *         has_room_conflict: bool,
     *         has_faculty_conflict: bool,
     *         is_room_overcrowded: bool,
     *         conflict_summary: string|null
     *     },
     *     waitlist_count: int,
     *     schedule_revision: string
     * }>
     */
    public function summarizeForIndex(Collection $classes): array
    {
        if ($classes->isEmpty()) {
            return [];
        }

        $eloquentClasses = $classes instanceof EloquentCollection
            ? $classes
            : EloquentCollection::make($classes->values()->all());

        $eloquentClasses->loadMissing([
            'schedules.room:id,name,capacity',
            'Room:id,name,capacity',
            'Subject:id,code,title,lecture,laboratory,units',
        ]);

        $termPairs = $this->extractTermPairs($eloquentClasses);
        $policy = ScheduleDelivery::policy();

        // Single batched query for all term classes needed to detect room/faculty/cohort conflicts
        $termClasses = $this->loadTermClasses($termPairs);

        $conflictsByScheduleId = [];
        $conflictsByClassId = [];

        foreach ($termClasses->groupBy(fn (Classes $c): string => $this->termKey($c->school_year, $c->semester)) as $termGroup) {
            $detected = $this->conflictService->detectConflictsForClasses($termGroup, includeSectionConflicts: true, policy: $policy);

            foreach ($detected as $conflict) {
                $s1 = isset($conflict['schedule_1_id']) ? (int) $conflict['schedule_1_id'] : null;
                $s2 = isset($conflict['schedule_2_id']) ? (int) $conflict['schedule_2_id'] : null;
                $c1 = isset($conflict['class_1']['id']) ? (int) $conflict['class_1']['id'] : null;
                $c2 = isset($conflict['class_2']['id']) ? (int) $conflict['class_2']['id'] : null;

                if ($s1 !== null) {
                    $conflictsByScheduleId[$s1][] = $conflict;
                }
                if ($s2 !== null) {
                    $conflictsByScheduleId[$s2][] = $conflict;
                }
                if ($c1 !== null) {
                    $conflictsByClassId[$c1][] = $conflict;
                }
                if ($c2 !== null && $c2 !== $c1) {
                    $conflictsByClassId[$c2][] = $conflict;
                }
            }
        }

        // Single batched query for active waitlist entries matching this page's classes/subjects
        $waitlistRows = $this->loadActiveWaitlistsForClasses($eloquentClasses, $termPairs);

        $summaries = [];

        foreach ($eloquentClasses as $class) {
            $classId = (int) $class->id;
            $schedules = $this->sortSchedules($this->classSchedules($class));
            $studentCount = $this->resolvedStudentCount($class);
            $classConflicts = $conflictsByClassId[$classId] ?? [];

            $serializedSchedules = [];
            $isAnyBlockOvercrowded = false;

            foreach ($schedules as $schedule) {
                $isOnline = $schedule->isOnline();
                $roomName = $isOnline
                    ? ScheduleDelivery::locationLabel($schedule->delivery_mode)
                    : ($schedule->room?->name ?? null);
                $roomCapacity = (! $isOnline && $schedule->room?->capacity !== null)
                    ? (int) $schedule->room->capacity
                    : null;
                $isOvercrowded = ! $isOnline
                    && $roomCapacity !== null
                    && $roomCapacity > 0
                    && $studentCount > $roomCapacity;

                if ($isOvercrowded) {
                    $isAnyBlockOvercrowded = true;
                }

                $serializedSchedules[] = [
                    'id' => (int) $schedule->id,
                    'day_of_week' => (string) $schedule->day_of_week,
                    'start_time' => $schedule->start_time?->format('H:i'),
                    'end_time' => $schedule->end_time?->format('H:i'),
                    'component_type' => $schedule->component_type ?? 'lecture',
                    'delivery_mode' => $schedule->delivery_mode ?? Classes::DELIVERY_FACE_TO_FACE,
                    'room_id' => $schedule->room_id !== null ? (int) $schedule->room_id : null,
                    'room_name' => $roomName,
                    'room_capacity' => $roomCapacity,
                    'status' => $schedule->status ?? 'published',
                    'has_conflict' => isset($conflictsByScheduleId[(int) $schedule->id]),
                    'is_overcrowded' => $isOvercrowded,
                ];
            }

            [$resolvedRoom, $resolvedRoomCapacity] = $this->resolvePrimaryRoomSummary($class, $schedules);

            if (! $isAnyBlockOvercrowded && $schedules->isEmpty() && $resolvedRoomCapacity !== null && $resolvedRoomCapacity > 0 && $studentCount > $resolvedRoomCapacity) {
                $isAnyBlockOvercrowded = true;
            }

            $hours = $this->conflictService->calculateClassHours($class);
            $scheduleStatus = $this->resolveAggregateScheduleStatus($schedules);
            $hasRoomConflict = $this->hasConflictOfType($classConflicts, 'room');
            $hasFacultyConflict = $this->hasConflictOfType($classConflicts, 'faculty');
            $conflictSummary = $this->buildConflictSummary($classId, $classConflicts);

            $summaries[$classId] = [
                'room' => $resolvedRoom,
                'room_capacity' => $resolvedRoomCapacity,
                'schedules' => $serializedSchedules,
                'schedule_health' => [
                    'scheduled_hours' => $hours['scheduled_hours'],
                    'required_hours' => $hours['required_hours'],
                    'is_complete' => $hours['is_complete'],
                    'status' => $scheduleStatus,
                    'has_conflict' => $classConflicts !== [],
                    'has_room_conflict' => $hasRoomConflict,
                    'has_faculty_conflict' => $hasFacultyConflict,
                    'is_room_overcrowded' => $isAnyBlockOvercrowded,
                    'conflict_summary' => $conflictSummary,
                ],
                'waitlist_count' => $this->countWaitlistsForClass($class, $waitlistRows),
                'schedule_revision' => $this->schedulingService->revisionFor($schedules),
            ];
        }

        return $summaries;
    }

    /**
     * Load busy weekly blocks for the class's assigned faculty, assigned rooms, and student cohort
     * from other classes in the same term in a single batched query.
     *
     * @return array{
     *     faculty_busy: list<array{class_id: int, subject_code: string, section: string, day_of_week: string, start_time: string, end_time: string, room_name: string|null, kind: 'faculty'}>,
     *     room_busy: list<array{class_id: int, subject_code: string, section: string, day_of_week: string, start_time: string, end_time: string, room_name: string|null, kind: 'room'}>,
     *     cohort_busy: list<array{class_id: int, subject_code: string, section: string, day_of_week: string, start_time: string, end_time: string, room_name: string|null, kind: 'cohort'}>
     * }
     */
    public function weeklyBusyOverlay(Classes $class): array
    {
        $class->loadMissing(['schedules:id,class_id,room_id,delivery_mode']);

        $targetRoomIds = $this->classSchedules($class)
            ->filter(fn (Schedule $s): bool => ! $s->isOnline() && $s->room_id !== null)
            ->pluck('room_id')
            ->when(
                ! $class->isOnline() && $class->room_id !== null,
                fn (Collection $ids): Collection => $ids->push($class->room_id),
            )
            ->map(fn (mixed $id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values()
            ->all();

        $schoolYearVariants = $this->schoolYearVariants($class->school_year);
        $semester = $this->resolveSemester($class->semester);
        $policy = ScheduleDelivery::policy();

        $otherClasses = Classes::query()
            ->where('id', '!=', $class->id)
            ->whereIn('school_year', $schoolYearVariants)
            ->where('semester', $semester)
            ->with(['schedules.room:id,name'])
            ->get([
                'id',
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
            ]);

        $facultyBusy = [];
        $roomBusy = [];
        $cohortBusy = [];

        foreach ($otherClasses as $other) {
            $sameFaculty = filled($class->faculty_id)
                && filled($other->faculty_id)
                && (string) $class->faculty_id === (string) $other->faculty_id;

            $sameCohort = $this->sharesCohortForOverlay($class, $other);

            foreach ($this->sortSchedules($other->schedules) as $schedule) {
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
     * Dry-run proposed schedule blocks without persisting: detects room conflicts, faculty conflicts,
     * room overcrowding, and per-student timetable clashes in constant batched queries.
     *
     * @param  array<int, array<string, mixed>>  $proposedSchedules
     * @return array{
     *     room_conflicts: list<array<string, mixed>>,
     *     faculty_conflicts: list<array<string, mixed>>,
     *     overcrowded_blocks: list<array<string, mixed>>,
     *     student_impact: array{
     *         total_enrolled: int,
     *         clear_count: int,
     *         clashing_count: int,
     *         clashing_students: list<array{
     *             student_id: int,
     *             student_number: string|int|null,
     *             name: string,
     *             enrollment_status: string,
     *             conflicting_subject: string,
     *             conflicting_section: string,
     *             conflicting_slot: string
     *         }>
     *     },
     *     enrolled_timetable_map: array<int, array{status: 'clear'|'clash', conflicts: list<string>}>
     * }
     */
    public function previewRescheduleImpact(Classes $class, array $proposedSchedules): array
    {
        $normalizedProposed = $this->normalizeProposedBlocks($proposedSchedules, $class);

        $proposedRoomIds = collect($normalizedProposed)
            ->pluck('room_id')
            ->filter(fn (mixed $id): bool => is_int($id) && $id > 0)
            ->unique()
            ->values()
            ->all();

        $context = $this->loadImpactContext($class, $proposedRoomIds);

        return $this->evaluateImpactWithContext($class, $normalizedProposed, $context);
    }

    /**
     * Suggest ranked conflict-free rooms, time slots, and online conversion for a target schedule
     * block, enriched with live enrolled student clash counts.
     *
     * @return array{
     *     schedule_id: int|null,
     *     target_schedule: array<string, mixed>|null,
     *     available_rooms: list<array<string, mixed>>,
     *     available_slots: list<array<string, mixed>>,
     *     can_convert_to_online: bool,
     *     online_student_clashes_count: int,
     *     online_student_clear_count: int,
     *     online_conversion: array{
     *         can_convert_to_online: bool,
     *         delivery_mode: string,
     *         student_clashes_count: int,
     *         student_clear_count: int
     *     }
     * }
     */
    public function rankedResolutions(Classes $class, ?int $scheduleId = null): array
    {
        $class->loadMissing(['schedules.room:id,name,capacity,type,building', 'Faculty:id,first_name,last_name']);
        $schedules = $this->sortSchedules($this->classSchedules($class));

        if ($schedules->isEmpty()) {
            return [
                'schedule_id' => null,
                'target_schedule' => null,
                'available_rooms' => [],
                'available_slots' => [],
                'can_convert_to_online' => false,
                'online_student_clashes_count' => 0,
                'online_student_clear_count' => 0,
                'online_conversion' => [
                    'can_convert_to_online' => false,
                    'delivery_mode' => Classes::DELIVERY_ONLINE,
                    'student_clashes_count' => 0,
                    'student_clear_count' => 0,
                ],
            ];
        }

        $schoolYearVariants = $this->schoolYearVariants($class->school_year);
        $semester = $this->resolveSemester($class->semester);
        $policy = ScheduleDelivery::policy();

        $termClasses = Classes::query()
            ->whereIn('school_year', $schoolYearVariants)
            ->where('semester', $semester)
            ->with(['schedules.room:id,name,capacity,type,building', 'Faculty:id,first_name,last_name'])
            ->withCount([
                'class_enrollments as class_students_count' => fn (Builder $q) => $q
                    ->where(fn (Builder $sq) => $sq->whereNull('status')->orWhere('status', true)),
            ])
            ->get();

        $rooms = Room::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'class_code', 'capacity', 'type', 'building', 'is_active']);

        $targetSchedule = $this->resolveTargetScheduleForResolution($class, $schedules, $termClasses, $policy, $scheduleId);

        if (! $targetSchedule instanceof Schedule) {
            return [
                'schedule_id' => null,
                'target_schedule' => null,
                'available_rooms' => [],
                'available_slots' => [],
                'can_convert_to_online' => false,
                'online_student_clashes_count' => 0,
                'online_student_clear_count' => 0,
                'online_conversion' => [
                    'can_convert_to_online' => false,
                    'delivery_mode' => Classes::DELIVERY_ONLINE,
                    'student_clashes_count' => 0,
                    'student_clear_count' => 0,
                ],
            ];
        }

        $suggestions = $this->conflictService->suggestResolutions($targetSchedule, $termClasses, $rooms, $policy);

        // Preload impact context ONCE so evaluating every suggestion uses 0 additional queries
        $otherTermClasses = $termClasses->filter(fn (Classes $c): bool => (int) $c->id !== (int) $class->id)->values();
        $context = $this->loadImpactContext(
            $class,
            $rooms->pluck('id')->map(fn ($id): int => (int) $id)->all(),
            $otherTermClasses,
            $rooms->keyBy('id'),
        );

        $baseBlocks = $schedules->map(fn (Schedule $s): array => [
            'id' => (int) $s->id,
            'day_of_week' => (string) $s->day_of_week,
            'start_time' => $s->start_time?->format('H:i') ?? '',
            'end_time' => $s->end_time?->format('H:i') ?? '',
            'delivery_mode' => $s->delivery_mode ?? Classes::DELIVERY_FACE_TO_FACE,
            'room_id' => $s->room_id !== null ? (int) $s->room_id : null,
            'component_type' => $s->component_type ?? 'lecture',
        ])->values()->all();

        $enrichedRooms = [];
        foreach ($suggestions['available_rooms'] as $roomSuggestion) {
            $candidateBlocks = $this->mutateBlockById($baseBlocks, (int) $targetSchedule->id, [
                'room_id' => (int) $roomSuggestion['room_id'],
                'delivery_mode' => Classes::DELIVERY_FACE_TO_FACE,
            ]);
            $impact = $this->evaluateImpactWithContext(
                $class,
                $this->normalizeProposedBlocks($candidateBlocks, $class),
                $context,
            );
            $enrichedRooms[] = [
                ...$roomSuggestion,
                'student_clashes_count' => $impact['student_impact']['clashing_count'],
                'student_clear_count' => $impact['student_impact']['clear_count'],
            ];
        }

        $enrichedSlots = [];
        foreach ($suggestions['available_slots'] as $index => $slotSuggestion) {
            $candidateBlocks = $this->mutateBlockById($baseBlocks, (int) $targetSchedule->id, [
                'day_of_week' => (string) $slotSuggestion['day_of_week'],
                'start_time' => (string) $slotSuggestion['start_time'],
                'end_time' => (string) $slotSuggestion['end_time'],
                'room_id' => $slotSuggestion['room_id'],
            ]);
            $impact = $this->evaluateImpactWithContext(
                $class,
                $this->normalizeProposedBlocks($candidateBlocks, $class),
                $context,
            );
            $enrichedSlots[] = [
                ...$slotSuggestion,
                'student_clashes_count' => $impact['student_impact']['clashing_count'],
                'student_clear_count' => $impact['student_impact']['clear_count'],
                '_rank_index' => $index,
            ];
        }

        usort($enrichedSlots, static fn (array $a, array $b): int => [$a['student_clashes_count'], $a['_rank_index']] <=> [$b['student_clashes_count'], $b['_rank_index']]);
        $enrichedSlots = array_map(
            static fn (array $slot): array => array_diff_key($slot, ['_rank_index' => true]),
            $enrichedSlots,
        );

        $canConvertOnline = (bool) $suggestions['can_convert_to_online'];
        $onlineClashes = 0;
        $onlineClear = $context['total_enrolled'];

        if ($canConvertOnline) {
            $onlineBlocks = $this->mutateBlockById($baseBlocks, (int) $targetSchedule->id, [
                'delivery_mode' => Classes::DELIVERY_ONLINE,
                'room_id' => null,
            ]);
            $onlineImpact = $this->evaluateImpactWithContext(
                $class,
                $this->normalizeProposedBlocks($onlineBlocks, $class),
                $context,
            );
            $onlineClashes = $onlineImpact['student_impact']['clashing_count'];
            $onlineClear = $onlineImpact['student_impact']['clear_count'];
        }

        return [
            'schedule_id' => (int) $targetSchedule->id,
            'target_schedule' => [
                'id' => (int) $targetSchedule->id,
                'day_of_week' => (string) $targetSchedule->day_of_week,
                'start_time' => $targetSchedule->start_time?->format('H:i'),
                'end_time' => $targetSchedule->end_time?->format('H:i'),
                'delivery_mode' => $targetSchedule->delivery_mode ?? Classes::DELIVERY_FACE_TO_FACE,
                'room_id' => $targetSchedule->room_id !== null ? (int) $targetSchedule->room_id : null,
                'room_name' => $targetSchedule->isOnline()
                    ? ScheduleDelivery::locationLabel($targetSchedule->delivery_mode)
                    : ($targetSchedule->room?->name ?? null),
            ],
            'available_rooms' => $enrichedRooms,
            'available_slots' => $enrichedSlots,
            'can_convert_to_online' => $canConvertOnline,
            'online_student_clashes_count' => $onlineClashes,
            'online_student_clear_count' => $onlineClear,
            'online_conversion' => [
                'can_convert_to_online' => $canConvertOnline,
                'delivery_mode' => Classes::DELIVERY_ONLINE,
                'student_clashes_count' => $onlineClashes,
                'student_clear_count' => $onlineClear,
            ],
        ];
    }

    /**
     * Build the parallel sections workspace for a subject across the same academic term,
     * including roster grade-protection flags, merge candidates, FIFO waitlist compatibility,
     * and optional inspected student weekly overlay.
     *
     * @return array{
     *     subject_code: string,
     *     subject_title: string,
     *     school_year: string,
     *     semester: int|string,
     *     total_enrolled: int,
     *     total_capacity: int,
     *     sections: list<array<string, mixed>>,
     *     waitlist: list<array<string, mixed>>,
     *     inspected_student_timetable: array{
     *         student_id: int,
     *         blocks: list<array<string, mixed>>,
     *         section_checks: array<int, array{status: 'clear'|'clash', conflicts: list<string>}>
     *     }|null
     * }
     */
    public function parallelSectionsWorkspace(Classes $class, ?int $inspectStudentId = null): array
    {
        $schoolYearVariants = $this->schoolYearVariants($class->school_year);
        $semester = $this->resolveSemester($class->semester);
        $policy = ScheduleDelivery::policy();

        $siblingsQuery = Classes::query()
            ->whereIn('school_year', $schoolYearVariants)
            ->where('semester', $semester)
            ->with([
                'schedules.room:id,name,capacity',
                'Room:id,name,capacity',
                'Faculty:id,first_name,last_name,middle_name,email',
                'Subject:id,code,title,lecture,laboratory,units',
                'SubjectByCodeFallback:id,code,title',
                'ShsSubject:code,title,grade_year,strand_id',
                'class_enrollments' => fn ($query) => $query
                    ->where(fn ($sq) => $sq->whereNull('status')->orWhere('status', true))
                    ->with([
                        'student:id,student_id,first_name,last_name,middle_name,course_id,academic_year',
                        'student.Course:id,code',
                    ])
                    ->orderBy('id'),
            ])
            ->withCount([
                'class_enrollments as class_enrollments_count' => fn (Builder $query) => $query
                    ->where(fn (Builder $sq) => $sq->whereNull('status')->orWhere('status', true)),
            ])
            ->orderBy('section')
            ->orderBy('id');

        if (filled($class->subject_code)) {
            $siblingsQuery->where('subject_code', $class->subject_code);
        } elseif ($class->subject_id) {
            $siblingsQuery->where('subject_id', $class->subject_id);
        } else {
            $siblingsQuery->whereKey($class->id);
        }

        /** @var EloquentCollection<int, Classes> $siblings */
        $siblings = $siblingsQuery->get();

        if ($siblings->where('id', $class->id)->isEmpty()) {
            $class->loadMissing([
                'schedules.room:id,name,capacity',
                'Room:id,name,capacity',
                'Faculty:id,first_name,last_name,middle_name,email',
                'Subject:id,code,title,lecture,laboratory,units',
                'SubjectByCodeFallback:id,code,title',
                'ShsSubject:code,title,grade_year,strand_id',
                'class_enrollments' => fn ($query) => $query
                    ->where(fn ($sq) => $sq->whereNull('status')->orWhere('status', true))
                    ->with([
                        'student:id,student_id,first_name,last_name,middle_name,course_id,academic_year',
                        'student.Course:id,code',
                    ])
                    ->orderBy('id'),
            ]);
            $class->setAttribute('class_enrollments_count', $class->class_enrollments->count());
            $siblings->prepend($class);
        }

        $siblingIds = $siblings->pluck('id')->map(fn ($id): int => (int) $id)->all();
        $subjectIds = $this->extractSubjectIds($siblings);
        $indexSummaries = $this->summarizeForIndex($siblings);

        // Load FIFO waiting entries for this subject in the same term
        $waitlistEntries = collect();
        if (Schema::hasTable('enrollment_class_waitlists')) {
            $waitlistQuery = EnrollmentClassWaitlist::query()
                ->waiting()
                ->whereIn('school_year', $schoolYearVariants)
                ->where('semester', $semester)
                ->with([
                    'student:id,student_id,first_name,last_name,middle_name,course_id',
                    'student.Course:id,code',
                    'subject:id,code,title',
                ])
                ->orderBy('position')
                ->orderBy('id');

            $waitlistQuery->where(function (Builder $q) use ($siblingIds, $subjectIds, $class): void {
                if ($siblingIds !== []) {
                    $q->orWhereIn('preferred_class_id', $siblingIds);
                }
                if ($subjectIds !== []) {
                    $q->orWhereIn('subject_id', $subjectIds);
                }
                if (filled($class->subject_code)) {
                    $q->orWhereHas('subject', fn (Builder $sq) => $sq->where('code', $class->subject_code));
                }
            });

            $waitlistEntries = $waitlistQuery->get();
        }

        // Gather all student IDs whose OTHER term classes we need for clash checks
        $allStudentIds = $siblings
            ->flatMap(fn (Classes $s): Collection => $s->class_enrollments->pluck('student_id'))
            ->merge($waitlistEntries->pluck('student_id'))
            ->when($inspectStudentId !== null, fn (Collection $ids): Collection => $ids->push($inspectStudentId))
            ->filter(fn (mixed $id): bool => is_numeric($id) && (int) $id > 0)
            ->map(fn (mixed $id): int => (int) $id)
            ->unique()
            ->values()
            ->all();

        // Single batched query for all OTHER active term schedules of every relevant student
        $otherBlocksByStudentId = $this->loadOtherTermBlocksByStudent($allStudentIds, $siblingIds, $schoolYearVariants, $semester);

        // Precompute time-checked schedule arrays per sibling section
        $sectionTimeBlocks = [];
        foreach ($siblings as $sibling) {
            $sectionTimeBlocks[(int) $sibling->id] = $this->extractTimeCheckedBlocksFromSchedules($sibling->schedules, $policy);
        }

        $totalEnrolled = 0;
        $totalCapacity = 0;
        $sectionsPayload = [];

        foreach ($siblings as $sibling) {
            $secId = (int) $sibling->id;
            $summary = $indexSummaries[$secId] ?? null;
            $secEnrollments = $sibling->class_enrollments->unique('student_id')->values();
            $enrolledCount = $secEnrollments->count();
            $maxSlots = (int) ($sibling->maximum_slots ?? 0);

            $totalEnrolled += $enrolledCount;
            $totalCapacity += $maxSlots;

            $mergeCandidate = $this->findMergeCandidate(
                $sibling,
                $secEnrollments,
                $siblings,
                $sectionTimeBlocks,
                $otherBlocksByStudentId,
                $policy,
            );

            $serializedEnrollments = $secEnrollments->map(function (ClassEnrollment $enrollment): array {
                $hasGrades = $enrollment->prelim_grade !== null
                    || $enrollment->midterm_grade !== null
                    || $enrollment->finals_grade !== null
                    || $enrollment->total_average !== null
                    || filled($enrollment->grade_symbol);
                $isFinalized = (bool) $enrollment->is_grades_finalized;
                $isVerified = (bool) $enrollment->is_grades_verified;

                return [
                    'id' => (int) $enrollment->id,
                    'student_id' => (int) $enrollment->student_id,
                    'student_number' => $enrollment->student?->student_id,
                    'name' => $enrollment->student?->full_name ?? 'Unknown Student',
                    'course' => $enrollment->student?->Course?->code,
                    'academic_year' => $enrollment->student?->academic_year,
                    'status' => $enrollment->status === false ? 'inactive' : 'active',
                    'enrollment_status' => $enrollment->resolvedEnrollmentStatus(),
                    'has_grades' => $hasGrades,
                    'is_grades_finalized' => $isFinalized,
                    'is_grades_verified' => $isVerified,
                    'is_grade_protected' => $enrollment->isGradeProtected() || filled($enrollment->grade_symbol),
                ];
            })->values()->all();

            $sectionsPayload[] = [
                'id' => $secId,
                'section' => (string) ($sibling->section ?? 'N/A'),
                'record_title' => $sibling->record_title,
                'faculty' => $sibling->Faculty ? [
                    'id' => (string) $sibling->Faculty->id,
                    'name' => $sibling->Faculty->full_name,
                    'email' => $sibling->Faculty->email,
                ] : null,
                'students_count' => $enrolledCount,
                'maximum_slots' => $maxSlots,
                'room' => $summary['room'] ?? null,
                'room_capacity' => $summary['room_capacity'] ?? null,
                'schedules' => $summary['schedules'] ?? [],
                'schedule_health' => $summary['schedule_health'] ?? null,
                'schedule_revision' => $summary['schedule_revision'] ?? $this->schedulingService->revisionFor($sibling->schedules),
                'enrollments' => $serializedEnrollments,
                'merge_candidate' => $mergeCandidate,
            ];
        }

        $waitlistPayload = $waitlistEntries->map(function (EnrollmentClassWaitlist $entry) use ($siblings, $sectionTimeBlocks, $otherBlocksByStudentId, $policy): array {
            $studentId = (int) $entry->student_id;
            $studentOtherBlocks = $otherBlocksByStudentId[$studentId] ?? [];
            $compatibility = [];

            foreach ($siblings as $sibling) {
                $secId = (int) $sibling->id;
                $enrolledCount = $sibling->class_enrollments->unique('student_id')->count();
                $maxSlots = (int) ($sibling->maximum_slots ?? 0);
                $isFull = $maxSlots > 0 && $enrolledCount >= $maxSlots;
                $clashes = $this->findStudentConflictsAgainstBlocks($studentOtherBlocks, $sectionTimeBlocks[$secId] ?? [], $policy);

                if ($clashes !== []) {
                    $compatibility[$secId] = [
                        'status' => 'clash',
                        'reason' => 'Clashes with '.$clashes[0]['label'],
                    ];
                } elseif ($isFull) {
                    $compatibility[$secId] = [
                        'status' => 'full',
                        'reason' => sprintf('Section %s is full (%d/%d)', (string) ($sibling->section ?? $secId), $enrolledCount, $maxSlots),
                    ];
                } else {
                    $compatibility[$secId] = [
                        'status' => 'clear',
                        'reason' => null,
                    ];
                }
            }

            return [
                'id' => (int) $entry->id,
                'position' => (int) $entry->position,
                'student_id' => $studentId,
                'student_number' => $entry->student?->student_id,
                'student_name' => $entry->student?->full_name ?? 'Unknown Student',
                'course_code' => $entry->student?->Course?->code,
                'subject_enrollment_id' => (int) $entry->subject_enrollment_id,
                'preferred_class_id' => $entry->preferred_class_id !== null ? (int) $entry->preferred_class_id : null,
                'notes' => $entry->notes,
                'created_at' => $entry->created_at?->toIso8601String(),
                'section_compatibility' => $compatibility,
            ];
        })->values()->all();

        $inspectedTimetable = null;
        if ($inspectStudentId !== null) {
            $studentOtherBlocks = $otherBlocksByStudentId[$inspectStudentId] ?? [];
            $sectionChecks = [];

            foreach ($siblings as $sibling) {
                $secId = (int) $sibling->id;
                $clashes = $this->findStudentConflictsAgainstBlocks($studentOtherBlocks, $sectionTimeBlocks[$secId] ?? [], $policy);
                $sectionChecks[$secId] = [
                    'status' => $clashes === [] ? 'clear' : 'clash',
                    'conflicts' => array_values(array_unique(array_column($clashes, 'label'))),
                ];
            }

            $inspectedTimetable = [
                'student_id' => $inspectStudentId,
                'blocks' => array_map(static fn (array $b): array => [
                    'class_id' => $b['class_id'],
                    'subject_code' => $b['subject_code'],
                    'section' => $b['section'],
                    'day_of_week' => $b['day_of_week'],
                    'start_time' => $b['start_time'],
                    'end_time' => $b['end_time'],
                    'room_name' => $b['room_name'],
                    'delivery_mode' => $b['delivery_mode'],
                ], $studentOtherBlocks),
                'section_checks' => $sectionChecks,
            ];
        }

        $primaryClass = $siblings->firstWhere('id', $class->id) ?? $class;
        $primarySubject = $primaryClass->isShs()
            ? $primaryClass->ShsSubject
            : ($primaryClass->Subject ?: $primaryClass->SubjectByCodeFallback);

        return [
            'subject_code' => (string) ($primarySubject?->code ?? $primaryClass->subject_code ?? 'N/A'),
            'subject_title' => (string) ($primarySubject?->title ?? $primaryClass->subject_title ?? 'N/A'),
            'school_year' => (string) ($primaryClass->school_year ?? $this->settingsService->getCurrentSchoolYearString()),
            'semester' => $semester,
            'total_enrolled' => $totalEnrolled,
            'total_capacity' => $totalCapacity,
            'sections' => $sectionsPayload,
            'waitlist' => $waitlistPayload,
            'inspected_student_timetable' => $inspectedTimetable,
        ];
    }

    /**
     * Execute multi-class bulk actions with conflict protection and structured warnings/errors.
     *
     * @param  list<int|string>  $classIds
     * @param  array<string, mixed>  $payload
     * @return array{
     *     action: string,
     *     updated_count: int,
     *     skipped_count: int,
     *     warnings: list<array<string, mixed>>,
     *     errors: list<array<string, mixed>>
     * }
     */
    public function batchApply(array $classIds, string $action, array $payload, ?User $actor = null): array
    {
        $ids = collect($classIds)
            ->filter(fn (mixed $id): bool => is_numeric($id) && (int) $id > 0)
            ->map(fn (mixed $id): int => (int) $id)
            ->unique()
            ->values()
            ->all();

        if ($ids === []) {
            return [
                'action' => $action,
                'updated_count' => 0,
                'skipped_count' => 0,
                'warnings' => [],
                'errors' => [],
            ];
        }

        /** @var EloquentCollection<int, Classes> $classes */
        $classes = Classes::query()
            ->whereIn('id', $ids)
            ->with(['schedules.room:id,name,capacity', 'Faculty:id,first_name,last_name'])
            ->withCount([
                'class_enrollments as active_enrollments_count' => fn (Builder $q) => $q
                    ->where(fn (Builder $sq) => $sq->whereNull('status')->orWhere('status', true)),
            ])
            ->get();

        return match ($action) {
            'assign_faculty' => $this->batchAssignFaculty($classes, $payload),
            'set_capacity' => $this->batchSetCapacity($classes, $payload),
            'publish_drafts' => $this->batchPublishDrafts($classes, $payload, $actor),
            'apply_pattern' => $this->batchApplyPattern($classes, $payload, $actor),
            default => throw new InvalidArgumentException("Unsupported batch action: {$action}"),
        };
    }

    /**
     * Expose the injected waitlist service when callers coordinate workspace waitlist actions.
     */
    public function waitlistService(): EnrollmentWaitlistService
    {
        return $this->waitlistService;
    }

    /**
     * @param  EloquentCollection<int, Classes>  $classes
     * @param  array<string, mixed>  $payload
     * @return array{action: string, updated_count: int, skipped_count: int, warnings: list<array<string, mixed>>, errors: list<array<string, mixed>>}
     */
    private function batchAssignFaculty(EloquentCollection $classes, array $payload): array
    {
        $rawFacultyId = $payload['faculty_id'] ?? null;
        $facultyId = is_scalar($rawFacultyId) && mb_trim((string) $rawFacultyId) !== ''
            ? mb_trim((string) $rawFacultyId)
            : null;

        DB::transaction(function () use ($classes, $facultyId): void {
            foreach ($classes as $class) {
                $class->faculty_id = $facultyId;
                $class->save();
            }
        });

        $warnings = [];
        if ($facultyId !== null) {
            $termPairs = $this->extractTermPairs($classes);
            $termClasses = $this->loadTermClasses($termPairs);
            $targetIds = $classes->pluck('id')->map(fn ($id): int => (int) $id)->flip();
            $policy = ScheduleDelivery::policy();

            foreach ($termClasses->groupBy(fn (Classes $c): string => $this->termKey($c->school_year, $c->semester)) as $termGroup) {
                $conflicts = $this->conflictService->detectConflictsForClasses($termGroup, includeSectionConflicts: false, policy: $policy);
                foreach ($conflicts as $conflict) {
                    if (! in_array('faculty', (array) ($conflict['conflict_types'] ?? []), true)) {
                        continue;
                    }
                    $c1Id = (int) ($conflict['class_1']['id'] ?? 0);
                    $c2Id = (int) ($conflict['class_2']['id'] ?? 0);
                    if (! $targetIds->has($c1Id) && ! $targetIds->has($c2Id)) {
                        continue;
                    }

                    $warnings[] = [
                        'type' => 'faculty_conflict',
                        'class_1_id' => $c1Id,
                        'class_2_id' => $c2Id,
                        'message' => sprintf(
                            'Faculty clash on %s (%s): %s - %s overlaps with %s - %s.',
                            (string) ($conflict['day'] ?? ''),
                            (string) ($conflict['time'] ?? ''),
                            (string) ($conflict['class_1']['subject_code'] ?? 'Class'),
                            (string) ($conflict['class_1']['section'] ?? ''),
                            (string) ($conflict['class_2']['subject_code'] ?? 'Class'),
                            (string) ($conflict['class_2']['section'] ?? ''),
                        ),
                    ];
                }
            }
        }

        return [
            'action' => 'assign_faculty',
            'updated_count' => $classes->count(),
            'skipped_count' => 0,
            'warnings' => $warnings,
            'errors' => [],
        ];
    }

    /**
     * @param  EloquentCollection<int, Classes>  $classes
     * @param  array<string, mixed>  $payload
     * @return array{action: string, updated_count: int, skipped_count: int, warnings: list<array<string, mixed>>, errors: list<array<string, mixed>>}
     */
    private function batchSetCapacity(EloquentCollection $classes, array $payload): array
    {
        $maximumSlots = max(0, (int) ($payload['maximum_slots'] ?? 0));
        DB::transaction(function () use ($classes, $maximumSlots): void {
            $lockedClasses = Classes::query()->whereKey($classes->modelKeys())->orderBy('id')->lockForUpdate()->get();
            foreach ($lockedClasses as $class) {
                $current = (int) ($class->maximum_slots ?? 0);
                if ($maximumSlots > 0 && ($current === 0 || $maximumSlots < $current)) {
                    $enrolled = $class->class_enrollments()
                        ->where(fn (Builder $query) => $query->whereNull('status')->orWhere('status', true))
                        ->where(fn (Builder $query) => $query->whereNull('enrollment_status')->orWhereNotIn('enrollment_status', [ClassEnrollment::STATUS_DROPPED, ClassEnrollment::STATUS_WITHDRAWN]))
                        ->count();
                    if ($maximumSlots < $enrolled) {
                        throw ValidationException::withMessages([
                            'maximum_slots' => sprintf('%s has %d active students; capacity cannot be reduced to %d.', $class->record_title, $enrolled, $maximumSlots),
                        ]);
                    }
                }

                $class->maximum_slots = $maximumSlots;
                $class->save();
            }
        });

        return [
            'action' => 'set_capacity',
            'updated_count' => $classes->count(),
            'skipped_count' => 0,
            'warnings' => [],
            'errors' => [],
        ];
    }

    /**
     * @param  EloquentCollection<int, Classes>  $classes
     * @param  array<string, mixed>  $payload
     * @return array{action: string, updated_count: int, skipped_count: int, warnings: list<array<string, mixed>>, errors: list<array<string, mixed>>}
     */
    private function batchPublishDrafts(EloquentCollection $classes, array $payload, ?User $actor): array
    {
        $notifyEnrolled = (bool) ($payload['notify_enrolled'] ?? true);
        $updatedCount = 0;
        $skippedCount = 0;

        foreach ($classes as $class) {
            $published = $this->schedulingService->publishDraftSchedules($class, $actor, $notifyEnrolled);
            if ($published > 0) {
                $updatedCount++;
            } else {
                $skippedCount++;
            }
        }

        return [
            'action' => 'publish_drafts',
            'updated_count' => $updatedCount,
            'skipped_count' => $skippedCount,
            'warnings' => [],
            'errors' => [],
        ];
    }

    /**
     * @param  EloquentCollection<int, Classes>  $classes
     * @param  array<string, mixed>  $payload
     * @return array{action: string, updated_count: int, skipped_count: int, warnings: list<array<string, mixed>>, errors: list<array<string, mixed>>}
     */
    private function batchApplyPattern(EloquentCollection $classes, array $payload, ?User $actor): array
    {
        $preset = is_string($payload['pattern'] ?? null) ? mb_strtolower(mb_trim($payload['pattern'])) : 'custom';

        [$defaultDays, $defaultStart, $defaultEnd] = match ($preset) {
            'mwf' => [['Monday', 'Wednesday', 'Friday'], '08:00', '09:00'],
            'tth' => [['Tuesday', 'Thursday'], '10:00', '11:30'],
            default => [[], '', ''],
        };

        $days = isset($payload['days']) && is_array($payload['days']) && $payload['days'] !== []
            ? array_values(array_unique(array_filter(array_map(
                static fn (mixed $d): string => is_string($d) ? mb_trim($d) : '',
                $payload['days'],
            ))))
            : $defaultDays;

        $startTime = is_string($payload['start_time'] ?? null) && mb_trim($payload['start_time']) !== ''
            ? mb_trim($payload['start_time'])
            : $defaultStart;
        $endTime = is_string($payload['end_time'] ?? null) && mb_trim($payload['end_time']) !== ''
            ? mb_trim($payload['end_time'])
            : $defaultEnd;

        if ($days === [] || $startTime === '' || $endTime === '') {
            throw new InvalidArgumentException('A valid schedule pattern (mwf, tth, or custom days/start_time/end_time) is required.');
        }

        $componentType = is_string($payload['component_type'] ?? null) && in_array($payload['component_type'], ['lecture', 'lab'], true)
            ? $payload['component_type']
            : 'lecture';
        $explicitDeliveryMode = is_string($payload['delivery_mode'] ?? null) && mb_trim($payload['delivery_mode']) !== ''
            ? ScheduleDelivery::canonicalize($payload['delivery_mode'])
            : null;
        $explicitRoomId = isset($payload['room_id']) && is_numeric($payload['room_id']) && (int) $payload['room_id'] > 0
            ? (int) $payload['room_id']
            : null;
        $scheduleStatus = is_string($payload['schedule_status'] ?? null) && in_array($payload['schedule_status'], ['draft', 'published'], true)
            ? $payload['schedule_status']
            : 'published';
        $notifyEnrolled = (bool) ($payload['notify_enrolled'] ?? true);

        $updatedCount = 0;
        $skippedCount = 0;
        $errors = [];

        foreach ($classes as $class) {
            $deliveryMode = $explicitDeliveryMode ?? ScheduleDelivery::canonicalize($class->delivery_mode);
            $roomId = ScheduleDelivery::isOnline($deliveryMode)
                ? null
                : ($explicitRoomId ?? ($class->room_id !== null ? (int) $class->room_id : null));

            $blocks = [];
            foreach ($days as $day) {
                $blocks[] = [
                    'day_of_week' => $day,
                    'start_time' => $startTime,
                    'end_time' => $endTime,
                    'delivery_mode' => $deliveryMode,
                    'room_id' => $roomId,
                    'component_type' => $componentType,
                ];
            }

            try {
                $this->schedulingService->replaceChecked(
                    $class,
                    $blocks,
                    $this->schedulingService->revision($class),
                    $actor,
                    $scheduleStatus,
                    $notifyEnrolled,
                );
                $updatedCount++;
            } catch (ValidationException $exception) {
                $skippedCount++;
                $firstMessage = collect($exception->errors())->flatten()->first() ?? $exception->getMessage();
                $errors[] = [
                    'class_id' => (int) $class->id,
                    'record_title' => $class->record_title,
                    'message' => (string) $firstMessage,
                ];
            }
        }

        return [
            'action' => 'apply_pattern',
            'updated_count' => $updatedCount,
            'skipped_count' => $skippedCount,
            'warnings' => [],
            'errors' => $errors,
        ];
    }

    /**
     * @param  list<int>  $proposedRoomIds
     * @param  Collection<int, Classes>|null  $preloadedOtherTermClasses
     * @param  Collection<int, Room>|null  $preloadedRoomsById
     * @return array{
     *     policy: array<string, mixed>,
     *     active_enrollments: Collection<int, ClassEnrollment>,
     *     total_enrolled: int,
     *     other_term_classes: Collection<int, Classes>,
     *     other_blocks_by_student: array<int, list<array<string, mixed>>>,
     *     rooms_by_id: Collection<int, Room>
     * }
     */
    private function loadImpactContext(
        Classes $class,
        array $proposedRoomIds,
        ?Collection $preloadedOtherTermClasses = null,
        ?Collection $preloadedRoomsById = null,
    ): array {
        $schoolYearVariants = $this->schoolYearVariants($class->school_year);
        $semester = $this->resolveSemester($class->semester);
        $policy = ScheduleDelivery::policy();

        $activeEnrollments = ClassEnrollment::query()
            ->where('class_id', $class->id)
            ->where(fn (Builder $q) => $q->whereNull('status')->orWhere('status', true))
            ->with(['student:id,student_id,first_name,last_name,middle_name'])
            ->get()
            ->unique('student_id')
            ->values();

        $otherTermClasses = $preloadedOtherTermClasses ?? Classes::query()
            ->where('id', '!=', $class->id)
            ->whereIn('school_year', $schoolYearVariants)
            ->where('semester', $semester)
            ->with(['schedules.room:id,name,capacity', 'Faculty:id,first_name,last_name,middle_name'])
            ->get([
                'id',
                'subject_code',
                'section',
                'faculty_id',
                'classification',
                'academic_year',
                'course_codes',
                'shs_strand_id',
                'grade_level',
                'school_year',
                'semester',
                'delivery_mode',
                'room_id',
            ]);

        $studentIds = $activeEnrollments->pluck('student_id')->map(fn ($id): int => (int) $id)->all();
        $otherClassesById = $otherTermClasses->keyBy('id');
        $otherClassIds = $otherClassesById->keys()->map(fn ($id): int => (int) $id)->all();

        $otherBlocksByStudent = [];
        if ($studentIds !== [] && $otherClassIds !== []) {
            // Single batched Eloquent query for all enrolled students' OTHER active term enrollments
            $otherStudentEnrollments = ClassEnrollment::query()
                ->whereIn('student_id', $studentIds)
                ->whereIn('class_id', $otherClassIds)
                ->where(fn (Builder $q) => $q->whereNull('status')->orWhere('status', true))
                ->get(['student_id', 'class_id']);

            foreach ($otherStudentEnrollments as $enrollment) {
                $studentId = (int) $enrollment->student_id;
                $otherClass = $otherClassesById->get((int) $enrollment->class_id);
                if (! $otherClass instanceof Classes) {
                    continue;
                }

                foreach ($this->classSchedules($otherClass) as $schedule) {
                    if (! $schedule->start_time || ! $schedule->end_time) {
                        continue;
                    }

                    $startMin = $this->timeToMinutes($schedule->start_time->format('H:i'));
                    $endMin = $this->timeToMinutes($schedule->end_time->format('H:i'));
                    if ($startMin === null || $endMin === null || $endMin <= $startMin) {
                        continue;
                    }

                    $otherBlocksByStudent[$studentId][] = [
                        'class_id' => (int) $otherClass->id,
                        'subject_code' => (string) ($otherClass->subject_code ?? 'Class'),
                        'section' => (string) ($otherClass->section ?? ''),
                        'day_of_week' => (string) $schedule->day_of_week,
                        'start_time' => $schedule->start_time->format('H:i'),
                        'end_time' => $schedule->end_time->format('H:i'),
                        'start_min' => $startMin,
                        'end_min' => $endMin,
                        'delivery_mode' => $schedule->delivery_mode ?? Classes::DELIVERY_FACE_TO_FACE,
                        'room_name' => $schedule->isOnline()
                            ? ScheduleDelivery::locationLabel($schedule->delivery_mode)
                            : ($schedule->room?->name ?? null),
                    ];
                }
            }
        }

        if ($preloadedRoomsById !== null) {
            $roomsById = $preloadedRoomsById;
        } elseif ($proposedRoomIds !== []) {
            $roomsById = Room::query()
                ->whereIn('id', $proposedRoomIds)
                ->get(['id', 'name', 'capacity'])
                ->keyBy('id');
        } else {
            $roomsById = collect();
        }

        return [
            'policy' => $policy,
            'active_enrollments' => $activeEnrollments,
            'total_enrolled' => $activeEnrollments->count(),
            'other_term_classes' => $otherTermClasses,
            'other_blocks_by_student' => $otherBlocksByStudent,
            'rooms_by_id' => $roomsById,
        ];
    }

    /**
     * @param  list<array{index: int, day_of_week: string, start_time: string, end_time: string, start_min: int, end_min: int, delivery_mode: string, room_id: int|null, component_type: string}>  $proposedBlocks
     * @param  array{
     *     policy: array<string, mixed>,
     *     active_enrollments: Collection<int, ClassEnrollment>,
     *     total_enrolled: int,
     *     other_term_classes: Collection<int, Classes>,
     *     other_blocks_by_student: array<int, list<array<string, mixed>>>,
     *     rooms_by_id: Collection<int, Room>
     * }  $context
     * @return array{
     *     room_conflicts: list<array<string, mixed>>,
     *     faculty_conflicts: list<array<string, mixed>>,
     *     overcrowded_blocks: list<array<string, mixed>>,
     *     student_impact: array{
     *         total_enrolled: int,
     *         clear_count: int,
     *         clashing_count: int,
     *         clashing_students: list<array{
     *             student_id: int,
     *             student_number: string|int|null,
     *             name: string,
     *             enrollment_status: string,
     *             conflicting_subject: string,
     *             conflicting_section: string,
     *             conflicting_slot: string
     *         }>
     *     },
     *     enrolled_timetable_map: array<int, array{status: 'clear'|'clash', conflicts: list<string>}>
     * }
     */
    private function evaluateImpactWithContext(Classes $class, array $proposedBlocks, array $context): array
    {
        $policy = $context['policy'];
        $enrolledCount = $context['total_enrolled'];
        $roomsById = $context['rooms_by_id'];

        $roomConflicts = [];
        $facultyConflicts = [];
        $overcrowdedBlocks = [];

        foreach ($proposedBlocks as $proposed) {
            $checksRoom = ScheduleDelivery::checksRoomConflicts($proposed['delivery_mode'], $policy)
                && $proposed['room_id'] !== null;
            $checksFaculty = ScheduleDelivery::checksFacultyConflicts($proposed['delivery_mode'], $policy)
                && filled($class->faculty_id);

            if (! ScheduleDelivery::isOnline($proposed['delivery_mode']) && $proposed['room_id'] !== null) {
                $room = $roomsById->get($proposed['room_id']);
                $roomCap = $room?->capacity !== null ? (int) $room->capacity : 0;
                if ($room instanceof Room && $roomCap > 0 && $enrolledCount > $roomCap) {
                    $overcrowdedBlocks[] = [
                        'block_index' => $proposed['index'],
                        'day_of_week' => $proposed['day_of_week'],
                        'start_time' => $proposed['start_time'],
                        'end_time' => $proposed['end_time'],
                        'room_id' => (int) $room->id,
                        'room_name' => $room->name,
                        'room_capacity' => $roomCap,
                        'enrolled_count' => $enrolledCount,
                        'overflow' => $enrolledCount - $roomCap,
                    ];
                }
            }

            if (! $checksRoom && ! $checksFaculty) {
                continue;
            }

            foreach ($context['other_term_classes'] as $otherClass) {
                $sameFaculty = $checksFaculty
                    && filled($otherClass->faculty_id)
                    && (string) $otherClass->faculty_id === (string) $class->faculty_id;

                foreach ($this->classSchedules($otherClass) as $otherSchedule) {
                    if (! $otherSchedule->start_time || ! $otherSchedule->end_time) {
                        continue;
                    }
                    if ((string) $otherSchedule->day_of_week !== $proposed['day_of_week']) {
                        continue;
                    }

                    $otherStart = $this->timeToMinutes($otherSchedule->start_time->format('H:i'));
                    $otherEnd = $this->timeToMinutes($otherSchedule->end_time->format('H:i'));
                    if ($otherStart === null || $otherEnd === null || $proposed['start_min'] >= $otherEnd || $proposed['end_min'] <= $otherStart) {
                        continue;
                    }

                    if (
                        $checksRoom
                        && ScheduleDelivery::checksRoomConflicts($otherSchedule->delivery_mode, $policy)
                        && $otherSchedule->room_id !== null
                        && (int) $otherSchedule->room_id === $proposed['room_id']
                    ) {
                        $roomConflicts[] = [
                            'block_index' => $proposed['index'],
                            'day_of_week' => $proposed['day_of_week'],
                            'proposed_slot' => $this->formatSlotLabel($proposed['day_of_week'], $proposed['start_min'], $proposed['end_min']),
                            'room_id' => $proposed['room_id'],
                            'room_name' => $otherSchedule->room?->name ?? $roomsById->get($proposed['room_id'])?->name,
                            'conflicting_class_id' => (int) $otherClass->id,
                            'conflicting_subject_code' => (string) ($otherClass->subject_code ?? 'Class'),
                            'conflicting_section' => (string) ($otherClass->section ?? ''),
                            'conflicting_slot' => $this->formatSlotLabel((string) $otherSchedule->day_of_week, $otherStart, $otherEnd),
                            'conflicting_schedule_id' => (int) $otherSchedule->id,
                        ];
                    }

                    if ($sameFaculty && ScheduleDelivery::checksFacultyConflicts($otherSchedule->delivery_mode, $policy)) {
                        $facultyConflicts[] = [
                            'block_index' => $proposed['index'],
                            'day_of_week' => $proposed['day_of_week'],
                            'proposed_slot' => $this->formatSlotLabel($proposed['day_of_week'], $proposed['start_min'], $proposed['end_min']),
                            'faculty_id' => (string) $class->faculty_id,
                            'faculty_name' => $otherClass->Faculty?->full_name,
                            'conflicting_class_id' => (int) $otherClass->id,
                            'conflicting_subject_code' => (string) ($otherClass->subject_code ?? 'Class'),
                            'conflicting_section' => (string) ($otherClass->section ?? ''),
                            'conflicting_slot' => $this->formatSlotLabel((string) $otherSchedule->day_of_week, $otherStart, $otherEnd),
                            'conflicting_schedule_id' => (int) $otherSchedule->id,
                        ];
                    }
                }
            }
        }

        // Student timetable impact (skips asynchronous online blocks)
        $timeCheckedProposed = array_values(array_filter(
            $proposedBlocks,
            static fn (array $block): bool => ! ScheduleDelivery::isAsynchronousOnline($block['delivery_mode'])
                && ScheduleDelivery::checksTimeConflicts($block['delivery_mode'], $policy),
        ));

        $clearCount = 0;
        $clashingCount = 0;
        $clashingStudents = [];
        $enrolledTimetableMap = [];

        foreach ($context['active_enrollments'] as $enrollment) {
            $studentId = (int) $enrollment->student_id;
            $otherBlocks = $context['other_blocks_by_student'][$studentId] ?? [];
            $clashes = $this->findStudentConflictsAgainstBlocks($otherBlocks, $timeCheckedProposed, $policy);

            if ($clashes === []) {
                $clearCount++;
                $enrolledTimetableMap[$studentId] = [
                    'status' => 'clear',
                    'conflicts' => [],
                ];

                continue;
            }

            $clashingCount++;
            $enrolledTimetableMap[$studentId] = [
                'status' => 'clash',
                'conflicts' => array_values(array_unique(array_column($clashes, 'label'))),
            ];

            $first = $clashes[0];
            $clashingStudents[] = [
                'student_id' => $studentId,
                'student_number' => $enrollment->student?->student_id,
                'name' => $enrollment->student?->full_name ?? 'Unknown Student',
                'enrollment_status' => $enrollment->resolvedEnrollmentStatus(),
                'conflicting_subject' => $first['subject_code'],
                'conflicting_section' => $first['section'],
                'conflicting_slot' => $first['slot'],
            ];
        }

        return [
            'room_conflicts' => $roomConflicts,
            'faculty_conflicts' => $facultyConflicts,
            'overcrowded_blocks' => $overcrowdedBlocks,
            'student_impact' => [
                'total_enrolled' => $enrolledCount,
                'clear_count' => $clearCount,
                'clashing_count' => $clashingCount,
                'clashing_students' => $clashingStudents,
            ],
            'enrolled_timetable_map' => $enrolledTimetableMap,
        ];
    }

    /**
     * @param  list<int>  $studentIds
     * @param  list<int>  $excludedClassIds
     * @param  list<string>  $schoolYearVariants
     * @return array<int, list<array{class_id: int, subject_code: string, section: string, day_of_week: string, start_time: string, end_time: string, start_min: int, end_min: int, delivery_mode: string, room_name: string|null}>>
     */
    private function loadOtherTermBlocksByStudent(
        array $studentIds,
        array $excludedClassIds,
        array $schoolYearVariants,
        int|string $semester,
    ): array {
        if ($studentIds === []) {
            return [];
        }

        $query = ClassEnrollment::query()
            ->whereIn('student_id', $studentIds)
            ->where(fn (Builder $q) => $q->whereNull('status')->orWhere('status', true))
            ->whereHas('class', function (Builder $q) use ($schoolYearVariants, $semester): void {
                $q->whereIn('school_year', $schoolYearVariants)
                    ->where('semester', $semester);
            })
            ->with([
                'class:id,subject_code,section,delivery_mode',
                'class.schedules:id,class_id,day_of_week,start_time,end_time,room_id,delivery_mode',
                'class.schedules.room:id,name',
            ]);

        if ($excludedClassIds !== []) {
            $query->whereNotIn('class_id', $excludedClassIds);
        }

        $enrollments = $query->get(['id', 'student_id', 'class_id']);
        $byStudent = [];

        foreach ($enrollments as $enrollment) {
            $otherClass = $enrollment->class;
            if (! $otherClass instanceof Classes) {
                continue;
            }

            $studentId = (int) $enrollment->student_id;

            foreach ($this->sortSchedules($otherClass->schedules) as $schedule) {
                if (! $schedule->start_time || ! $schedule->end_time) {
                    continue;
                }

                $startMin = $this->timeToMinutes($schedule->start_time->format('H:i'));
                $endMin = $this->timeToMinutes($schedule->end_time->format('H:i'));
                if ($startMin === null || $endMin === null || $endMin <= $startMin) {
                    continue;
                }

                $byStudent[$studentId][] = [
                    'class_id' => (int) $otherClass->id,
                    'subject_code' => (string) ($otherClass->subject_code ?? 'Class'),
                    'section' => (string) ($otherClass->section ?? ''),
                    'day_of_week' => (string) $schedule->day_of_week,
                    'start_time' => $schedule->start_time->format('H:i'),
                    'end_time' => $schedule->end_time->format('H:i'),
                    'start_min' => $startMin,
                    'end_min' => $endMin,
                    'delivery_mode' => $schedule->delivery_mode ?? Classes::DELIVERY_FACE_TO_FACE,
                    'room_name' => $schedule->isOnline()
                        ? ScheduleDelivery::locationLabel($schedule->delivery_mode)
                        : ($schedule->room?->name ?? null),
                ];
            }
        }

        return $byStudent;
    }

    /**
     * @param  list<array<string, mixed>>  $studentOtherBlocks
     * @param  list<array{day_of_week: string, start_min: int, end_min: int, delivery_mode: string}>  $targetBlocks
     * @param  array<string, mixed>  $policy
     * @return list<array{subject_code: string, section: string, slot: string, label: string}>
     */
    private function findStudentConflictsAgainstBlocks(array $studentOtherBlocks, array $targetBlocks, array $policy): array
    {
        if ($studentOtherBlocks === [] || $targetBlocks === []) {
            return [];
        }

        $clashes = [];

        foreach ($studentOtherBlocks as $other) {
            $otherMode = (string) ($other['delivery_mode'] ?? Classes::DELIVERY_FACE_TO_FACE);
            if (ScheduleDelivery::isAsynchronousOnline($otherMode) || ! ScheduleDelivery::checksTimeConflicts($otherMode, $policy)) {
                continue;
            }

            foreach ($targetBlocks as $target) {
                if ((string) $other['day_of_week'] !== (string) $target['day_of_week']) {
                    continue;
                }
                if ($target['start_min'] >= $other['end_min'] || $target['end_min'] <= $other['start_min']) {
                    continue;
                }

                $slot = $this->formatSlotLabel((string) $other['day_of_week'], (int) $other['start_min'], (int) $other['end_min']);
                $subjectCode = (string) $other['subject_code'];
                $section = (string) $other['section'];
                $label = $section !== ''
                    ? sprintf('%s (%s) · %s', $subjectCode, $section, $slot)
                    : sprintf('%s · %s', $subjectCode, $slot);

                $clashes[] = [
                    'subject_code' => $subjectCode,
                    'section' => $section,
                    'slot' => $slot,
                    'label' => $label,
                ];
            }
        }

        return $clashes;
    }

    /**
     * @param  Collection<int, ClassEnrollment>  $sourceEnrollments
     * @param  EloquentCollection<int, Classes>  $siblings
     * @param  array<int, list<array{day_of_week: string, start_min: int, end_min: int, delivery_mode: string}>>  $sectionTimeBlocks
     * @param  array<int, list<array<string, mixed>>>  $otherBlocksByStudentId
     * @param  array<string, mixed>  $policy
     * @return array<string, mixed>|null
     */
    private function findMergeCandidate(
        Classes $source,
        Collection $sourceEnrollments,
        EloquentCollection $siblings,
        array $sectionTimeBlocks,
        array $otherBlocksByStudentId,
        array $policy,
    ): ?array {
        $maxSlots = (int) ($source->maximum_slots ?? 0);
        $enrolledCount = $sourceEnrollments->count();

        if ($maxSlots <= 0 || ($enrolledCount / $maxSlots) >= 0.35) {
            return null;
        }

        $bestTarget = null;
        $bestHeadroom = -1;

        foreach ($siblings as $candidate) {
            if ((int) $candidate->id === (int) $source->id) {
                continue;
            }

            $candEnrolled = $candidate->class_enrollments->unique('student_id')->count();
            $candMax = (int) ($candidate->maximum_slots ?? 0);
            $candHeadroom = $candMax > 0 ? ($candMax - $candEnrolled) : PHP_INT_MAX;

            if ($candMax > 0 && ($candEnrolled + $enrolledCount) > $candMax) {
                continue;
            }

            $candBlocks = $sectionTimeBlocks[(int) $candidate->id] ?? [];
            $hasAnyClash = false;

            foreach ($sourceEnrollments as $enrollment) {
                $otherBlocks = $otherBlocksByStudentId[(int) $enrollment->student_id] ?? [];
                if ($this->findStudentConflictsAgainstBlocks($otherBlocks, $candBlocks, $policy) !== []) {
                    $hasAnyClash = true;
                    break;
                }
            }

            if ($hasAnyClash) {
                continue;
            }

            if ($candHeadroom > $bestHeadroom) {
                $bestHeadroom = $candHeadroom;
                $bestTarget = [
                    'is_candidate' => true,
                    'fill_pct' => (int) round(($enrolledCount / $maxSlots) * 100),
                    'target_section_id' => (int) $candidate->id,
                    'target_section' => (string) ($candidate->section ?? 'N/A'),
                    'target_available_slots' => $candMax > 0 ? max(0, $candMax - $candEnrolled) : null,
                    'clashing_students_count' => 0,
                ];
            }
        }

        return $bestTarget;
    }

    /**
     * @param  Collection<int, Schedule>  $schedules
     * @param  EloquentCollection<int, Classes>  $termClasses
     * @param  array<string, mixed>  $policy
     */
    private function resolveTargetScheduleForResolution(
        Classes $class,
        Collection $schedules,
        EloquentCollection $termClasses,
        array $policy,
        ?int $scheduleId,
    ): ?Schedule {
        if ($scheduleId !== null) {
            $explicit = $schedules->firstWhere('id', $scheduleId);
            if ($explicit instanceof Schedule) {
                return $explicit;
            }
        }

        $conflicts = $this->conflictService->detectConflictsForClasses($termClasses, includeSectionConflicts: true, policy: $policy);
        $conflictingIds = [];

        foreach ($conflicts as $conflict) {
            if (isset($conflict['schedule_1_id'])) {
                $conflictingIds[(int) $conflict['schedule_1_id']] = true;
            }
            if (isset($conflict['schedule_2_id'])) {
                $conflictingIds[(int) $conflict['schedule_2_id']] = true;
            }
        }

        $firstConflicting = $schedules->first(fn (Schedule $s): bool => isset($conflictingIds[(int) $s->id]));
        if ($firstConflicting instanceof Schedule) {
            return $firstConflicting;
        }

        $termClass = $termClasses->firstWhere('id', $class->id);
        $studentCount = (int) ($termClass?->class_students_count ?? $this->resolvedStudentCount($class));

        $firstOvercrowded = $schedules->first(function (Schedule $s) use ($studentCount): bool {
            if ($s->isOnline() || ! $s->room) {
                return false;
            }
            $capacity = (int) ($s->room->capacity ?? 0);

            return $capacity > 0 && $studentCount > $capacity;
        });

        if ($firstOvercrowded instanceof Schedule) {
            return $firstOvercrowded;
        }

        return $schedules->first();
    }

    /**
     * @param  Collection<int, Classes>  $classes
     * @return list<array{variants: list<string>, semester: int|string}>
     */
    private function extractTermPairs(Collection $classes): array
    {
        $pairs = [];

        foreach ($classes as $class) {
            $key = $this->termKey($class->school_year, $class->semester);
            if (! isset($pairs[$key])) {
                $pairs[$key] = [
                    'variants' => $this->schoolYearVariants($class->school_year),
                    'semester' => $this->resolveSemester($class->semester),
                ];
            }
        }

        return array_values($pairs);
    }

    /**
     * @param  list<array{variants: list<string>, semester: int|string}>  $termPairs
     * @return EloquentCollection<int, Classes>
     */
    private function loadTermClasses(array $termPairs): EloquentCollection
    {
        if ($termPairs === []) {
            return EloquentCollection::make();
        }

        return Classes::query()
            ->where(function (Builder $query) use ($termPairs): void {
                foreach ($termPairs as $pair) {
                    $query->orWhere(function (Builder $sub) use ($pair): void {
                        $sub->whereIn('school_year', $pair['variants'])
                            ->where('semester', $pair['semester']);
                    });
                }
            })
            ->with([
                'schedules.room:id,name,capacity',
                'Faculty:id,first_name,last_name',
            ])
            ->get([
                'id',
                'subject_code',
                'section',
                'faculty_id',
                'classification',
                'academic_year',
                'course_codes',
                'shs_strand_id',
                'grade_level',
                'school_year',
                'semester',
                'delivery_mode',
                'room_id',
                'maximum_slots',
            ]);
    }

    /**
     * @param  EloquentCollection<int, Classes>  $classes
     * @param  list<array{variants: list<string>, semester: int|string}>  $termPairs
     * @return Collection<int, EnrollmentClassWaitlist>
     */
    private function loadActiveWaitlistsForClasses(EloquentCollection $classes, array $termPairs): Collection
    {
        if ($termPairs === [] || ! Schema::hasTable('enrollment_class_waitlists')) {
            return collect();
        }

        $classIds = $classes->pluck('id')->map(fn ($id): int => (int) $id)->all();
        $subjectIds = $this->extractSubjectIds($classes);

        if ($classIds === [] && $subjectIds === []) {
            return collect();
        }

        return EnrollmentClassWaitlist::query()
            ->waiting()
            ->where(function (Builder $query) use ($termPairs): void {
                foreach ($termPairs as $pair) {
                    $query->orWhere(function (Builder $sub) use ($pair): void {
                        $sub->whereIn('school_year', $pair['variants'])
                            ->where('semester', $pair['semester']);
                    });
                }
            })
            ->where(function (Builder $query) use ($classIds, $subjectIds): void {
                if ($classIds !== []) {
                    $query->orWhereIn('preferred_class_id', $classIds);
                }
                if ($subjectIds !== []) {
                    $query->orWhereIn('subject_id', $subjectIds);
                }
            })
            ->get(['id', 'subject_id', 'preferred_class_id', 'school_year', 'semester']);
    }

    /**
     * @param  Collection<int, EnrollmentClassWaitlist>  $waitlistRows
     */
    private function countWaitlistsForClass(Classes $class, Collection $waitlistRows): int
    {
        if ($waitlistRows->isEmpty()) {
            return 0;
        }

        $classId = (int) $class->id;
        $termKey = $this->termKey($class->school_year, $class->semester);
        $subjectIds = $this->extractSubjectIds(collect([$class]));

        return $waitlistRows->filter(function (EnrollmentClassWaitlist $row) use ($classId, $termKey, $subjectIds): bool {
            if ($this->termKey($row->school_year, $row->semester) !== $termKey) {
                return false;
            }

            if ($row->preferred_class_id !== null) {
                return (int) $row->preferred_class_id === $classId;
            }

            return $subjectIds !== [] && in_array((int) $row->subject_id, $subjectIds, true);
        })->count();
    }

    /**
     * @param  Collection<int, Classes>  $classes
     * @return list<int>
     */
    private function extractSubjectIds(Collection $classes): array
    {
        $ids = [];

        foreach ($classes as $class) {
            if ($class->subject_id !== null && (int) $class->subject_id > 0) {
                $ids[] = (int) $class->subject_id;
            }
            if (is_array($class->subject_ids)) {
                foreach ($class->subject_ids as $subjectId) {
                    if (is_numeric($subjectId) && (int) $subjectId > 0) {
                        $ids[] = (int) $subjectId;
                    }
                }
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * @param  Collection<int, Schedule>  $schedules
     * @return array{0: string|null, 1: int|null}
     */
    private function resolvePrimaryRoomSummary(Classes $class, Collection $schedules): array
    {
        foreach ($schedules as $schedule) {
            if (! $schedule->isOnline() && $schedule->room instanceof Room) {
                return [
                    $schedule->room->name,
                    $schedule->room->capacity !== null ? (int) $schedule->room->capacity : null,
                ];
            }
        }

        if (! $class->isOnline() && $class->Room instanceof Room) {
            return [
                $class->Room->name,
                $class->Room->capacity !== null ? (int) $class->Room->capacity : null,
            ];
        }

        if ($schedules->isNotEmpty()) {
            $allOnline = $schedules->every(fn (Schedule $s): bool => $s->isOnline());
            if ($allOnline) {
                $modes = $schedules->pluck('delivery_mode')->map(fn ($m): string => ScheduleDelivery::canonicalize($m))->unique();
                $label = $modes->count() === 1
                    ? ScheduleDelivery::locationLabel($modes->first())
                    : 'Online';

                return [$label, null];
            }
        } elseif ($class->isOnline()) {
            return [ScheduleDelivery::locationLabel($class->delivery_mode), null];
        }

        return [null, null];
    }

    /**
     * @param  Collection<int, Schedule>  $schedules
     * @return 'published'|'draft'|'unscheduled'
     */
    private function resolveAggregateScheduleStatus(Collection $schedules): string
    {
        if ($schedules->isEmpty()) {
            return 'unscheduled';
        }

        $hasDraft = $schedules->contains(fn (Schedule $s): bool => ($s->status ?? 'published') === 'draft');

        return $hasDraft ? 'draft' : 'published';
    }

    /**
     * @param  list<array<string, mixed>>  $conflicts
     */
    private function hasConflictOfType(array $conflicts, string $type): bool
    {
        foreach ($conflicts as $conflict) {
            if (in_array($type, (array) ($conflict['conflict_types'] ?? []), true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<array<string, mixed>>  $conflicts
     */
    private function buildConflictSummary(int $classId, array $conflicts): ?string
    {
        if ($conflicts === []) {
            return null;
        }

        $first = $conflicts[0];
        $other = ((int) ($first['class_1']['id'] ?? 0) === $classId)
            ? ($first['class_2'] ?? [])
            : ($first['class_1'] ?? []);

        $type = match ($first['conflict_type'] ?? 'room') {
            'faculty' => 'Faculty conflict',
            'section' => 'Cohort conflict',
            default => 'Room conflict',
        };

        $otherLabel = mb_trim(sprintf(
            '%s %s',
            (string) ($other['subject_code'] ?? 'Class'),
            filled($other['section'] ?? null) ? '- '.$other['section'] : '',
        ));

        $summary = sprintf(
            '%s with %s (%s, %s)',
            $type,
            $otherLabel,
            (string) ($first['day'] ?? ''),
            (string) ($first['time'] ?? ''),
        );

        $extra = count($conflicts) - 1;
        if ($extra > 0) {
            $summary .= sprintf(' (+%d more)', $extra);
        }

        return $summary;
    }

    private function sharesCohortForOverlay(Classes $classA, Classes $classB): bool
    {
        if ((int) $classA->id === (int) $classB->id) {
            return false;
        }

        if (filled($classA->section) && filled($classB->section)) {
            return $this->conflictService->sharesStudentCohort($classA, $classB);
        }

        if ((string) $classA->classification !== (string) $classB->classification) {
            return false;
        }

        if ($classA->classification === 'shs') {
            return ! empty($classA->shs_strand_id)
                && (int) $classA->shs_strand_id === (int) $classB->shs_strand_id
                && ! empty($classA->grade_level)
                && (string) $classA->grade_level === (string) $classB->grade_level;
        }

        if (empty($classA->academic_year) || empty($classB->academic_year)) {
            return false;
        }

        if ((int) $classA->academic_year !== (int) $classB->academic_year) {
            return false;
        }

        $coursesA = collect(is_array($classA->course_codes) ? $classA->course_codes : [])
            ->map(fn (mixed $id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->all();

        $coursesB = collect(is_array($classB->course_codes) ? $classB->course_codes : [])
            ->map(fn (mixed $id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->all();

        return $coursesA !== [] && $coursesB !== [] && array_intersect($coursesA, $coursesB) !== [];
    }

    /**
     * @param  array<int, array<string, mixed>>  $proposedSchedules
     * @return list<array{index: int, day_of_week: string, start_time: string, end_time: string, start_min: int, end_min: int, delivery_mode: string, room_id: int|null, component_type: string}>
     */
    private function normalizeProposedBlocks(array $proposedSchedules, Classes $class): array
    {
        $normalized = [];
        $defaultMode = ScheduleDelivery::canonicalize($class->delivery_mode);

        foreach (array_values($proposedSchedules) as $index => $raw) {
            if (! is_array($raw)) {
                continue;
            }

            $day = is_string($raw['day_of_week'] ?? null) ? mb_trim($raw['day_of_week']) : '';
            $startMin = $this->timeToMinutes($raw['start_time'] ?? null);
            $endMin = $this->timeToMinutes($raw['end_time'] ?? null);

            if ($day === '' || $startMin === null || $endMin === null || $endMin <= $startMin) {
                continue;
            }

            $deliveryMode = ScheduleDelivery::canonicalize($raw['delivery_mode'] ?? $defaultMode, $defaultMode);
            $roomId = ScheduleDelivery::isOnline($deliveryMode)
                ? null
                : (isset($raw['room_id']) && is_numeric($raw['room_id']) && (int) $raw['room_id'] > 0
                    ? (int) $raw['room_id']
                    : null);

            $normalized[] = [
                'index' => $index,
                'day_of_week' => $day,
                'start_time' => $this->formatMinutes24h($startMin),
                'end_time' => $this->formatMinutes24h($endMin),
                'start_min' => $startMin,
                'end_min' => $endMin,
                'delivery_mode' => $deliveryMode,
                'room_id' => $roomId,
                'component_type' => is_string($raw['component_type'] ?? null) ? $raw['component_type'] : 'lecture',
            ];
        }

        return $normalized;
    }

    /**
     * @param  Collection<int, Schedule>  $schedules
     * @param  array<string, mixed>  $policy
     * @return list<array{day_of_week: string, start_min: int, end_min: int, delivery_mode: string}>
     */
    private function extractTimeCheckedBlocksFromSchedules(Collection $schedules, array $policy): array
    {
        $blocks = [];

        foreach ($schedules as $schedule) {
            if (! $schedule->start_time || ! $schedule->end_time) {
                continue;
            }

            $mode = $schedule->delivery_mode ?? Classes::DELIVERY_FACE_TO_FACE;
            if (ScheduleDelivery::isAsynchronousOnline($mode) || ! ScheduleDelivery::checksTimeConflicts($mode, $policy)) {
                continue;
            }

            $startMin = $this->timeToMinutes($schedule->start_time->format('H:i'));
            $endMin = $this->timeToMinutes($schedule->end_time->format('H:i'));
            if ($startMin === null || $endMin === null || $endMin <= $startMin) {
                continue;
            }

            $blocks[] = [
                'day_of_week' => (string) $schedule->day_of_week,
                'start_min' => $startMin,
                'end_min' => $endMin,
                'delivery_mode' => $mode,
            ];
        }

        return $blocks;
    }

    /**
     * @param  list<array<string, mixed>>  $blocks
     * @param  array<string, mixed>  $overrides
     * @return list<array<string, mixed>>
     */
    private function mutateBlockById(array $blocks, int $targetScheduleId, array $overrides): array
    {
        return array_map(
            static fn (array $block): array => (int) ($block['id'] ?? 0) === $targetScheduleId
                ? array_merge($block, $overrides)
                : $block,
            $blocks,
        );
    }

    /**
     * @return Collection<int, Schedule>
     */
    private function classSchedules(Classes $class): Collection
    {
        if ($class->relationLoaded('schedules')) {
            return $class->schedules;
        }

        if ($class->relationLoaded('Schedule')) {
            return $class->Schedule;
        }

        return collect();
    }

    /**
     * @param  Collection<int, Schedule>  $schedules
     * @return Collection<int, Schedule>
     */
    private function sortSchedules(Collection $schedules): Collection
    {
        return $schedules
            ->sortBy([
                fn (Schedule $s): int => self::DAY_ORDER[(string) $s->day_of_week] ?? 99,
                fn (Schedule $s): string => $s->start_time?->format('H:i') ?? '99:99',
                fn (Schedule $s): int => (int) $s->id,
            ])
            ->values();
    }

    private function resolvedStudentCount(Classes $class): int
    {
        if (isset($class->class_enrollments_count)) {
            return (int) $class->class_enrollments_count;
        }

        if (isset($class->class_students_count)) {
            return (int) $class->class_students_count;
        }

        if (isset($class->active_enrollments_count)) {
            return (int) $class->active_enrollments_count;
        }

        if ($class->relationLoaded('class_enrollments')) {
            return $class->class_enrollments->count();
        }

        return 0;
    }

    /**
     * @return list<string>
     */
    private function schoolYearVariants(mixed $schoolYear): array
    {
        $raw = is_string($schoolYear) && mb_trim($schoolYear) !== ''
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

    private function termKey(mixed $schoolYear, mixed $semester): string
    {
        $raw = is_string($schoolYear) && mb_trim($schoolYear) !== ''
            ? mb_trim($schoolYear)
            : $this->settingsService->getCurrentSchoolYearString();

        return GeneralSettingsService::normalizeSchoolYear($raw).'|'.(string) $this->resolveSemester($semester);
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

    private function formatMinutes12h(int $totalMinutes): string
    {
        $hours = intdiv($totalMinutes, 60) % 24;
        $minutes = $totalMinutes % 60;
        $suffix = $hours >= 12 ? 'PM' : 'AM';
        $hour12 = $hours % 12 ?: 12;

        return sprintf('%02d:%02d %s', $hour12, $minutes, $suffix);
    }

    private function formatSlotLabel(string $dayOfWeek, int $startMin, int $endMin): string
    {
        return sprintf('%s %s - %s', $dayOfWeek, $this->formatMinutes12h($startMin), $this->formatMinutes12h($endMin));
    }
}
