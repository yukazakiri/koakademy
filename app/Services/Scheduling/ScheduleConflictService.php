<?php

declare(strict_types=1);

namespace App\Services\Scheduling;

use App\Models\Classes;
use App\Models\Room;
use App\Models\Schedule;
use App\Support\ScheduleDelivery;
use Illuminate\Support\Collection;

final class ScheduleConflictService
{
    /**
     * Detect room, faculty, and cohort-section conflicts across a collection of classes.
     *
     * @param  iterable<int, Classes>  $classes
     * @param  array<string, mixed>|null  $policy
     * @return array<int, array<string, mixed>>
     */
    public function detectConflictsForClasses(iterable $classes, bool $includeSectionConflicts = true, ?array $policy = null): array
    {
        $resolvedPolicy = ScheduleDelivery::policy($policy);
        $conflicts = [];
        $allSchedules = [];

        foreach ($classes as $class) {
            $schedules = $class->relationLoaded('Schedule')
                ? $class->Schedule
                : ($class->relationLoaded('schedules') ? $class->schedules : $class->Schedule()->with('room')->get());

            foreach ($schedules as $schedule) {
                if (! $schedule instanceof Schedule || ! $schedule->start_time || ! $schedule->end_time) {
                    continue;
                }

                $allSchedules[] = [
                    'class' => $class,
                    'schedule' => $schedule,
                    'day' => $schedule->day_of_week,
                    'start_min' => (int) $schedule->start_time->format('H') * 60 + (int) $schedule->start_time->format('i'),
                    'end_min' => (int) $schedule->end_time->format('H') * 60 + (int) $schedule->end_time->format('i'),
                ];
            }
        }

        $count = count($allSchedules);
        for ($i = 0; $i < $count; $i++) {
            for ($j = $i + 1; $j < $count; $j++) {
                $a = $allSchedules[$i];
                $b = $allSchedules[$j];

                if ($a['day'] !== $b['day']) {
                    continue;
                }

                if ($a['schedule']->id === $b['schedule']->id) {
                    continue;
                }

                // Check for overlapping times: StartA < EndB and EndA > StartB
                if ($a['start_min'] >= $b['end_min'] || $a['end_min'] <= $b['start_min']) {
                    continue;
                }

                $sameClass = (int) $a['class']->id === (int) $b['class']->id;

                $roomConflict = ScheduleDelivery::checksRoomConflicts($a['schedule']->delivery_mode, $resolvedPolicy)
                    && ScheduleDelivery::checksRoomConflicts($b['schedule']->delivery_mode, $resolvedPolicy)
                    && $a['schedule']->room_id
                    && $b['schedule']->room_id
                    && (int) $a['schedule']->room_id === (int) $b['schedule']->room_id;

                $facultyConflict = ! $sameClass
                    && ScheduleDelivery::checksFacultyConflicts($a['schedule']->delivery_mode, $resolvedPolicy)
                    && ScheduleDelivery::checksFacultyConflicts($b['schedule']->delivery_mode, $resolvedPolicy)
                    && $a['class']->faculty_id
                    && $b['class']->faculty_id
                    && (string) $a['class']->faculty_id === (string) $b['class']->faculty_id;

                $sectionConflict = $includeSectionConflicts
                    && ! $sameClass
                    && ScheduleDelivery::checksStudentCohortConflicts($a['schedule']->delivery_mode, $resolvedPolicy)
                    && ScheduleDelivery::checksStudentCohortConflicts($b['schedule']->delivery_mode, $resolvedPolicy)
                    && $this->sharesStudentCohort($a['class'], $b['class']);

                if ($roomConflict || $facultyConflict || $sectionConflict) {
                    $timeA = $a['schedule']->time_range;
                    $timeB = $b['schedule']->time_range;
                    $timeStr = $timeA === $timeB ? $timeA : "{$timeA} vs {$timeB}";

                    $conflictTypes = [];
                    if ($roomConflict) {
                        $conflictTypes[] = 'room';
                    }
                    if ($facultyConflict) {
                        $conflictTypes[] = 'faculty';
                    }
                    if ($sectionConflict) {
                        $conflictTypes[] = 'section';
                    }

                    $overlapStart = max($a['start_min'], $b['start_min']);
                    $overlapEnd = min($a['end_min'], $b['end_min']);

                    $conflicts[] = [
                        'day' => $a['day'],
                        'time' => $timeStr,
                        'overlap_start' => $this->formatMinutes12h($overlapStart),
                        'overlap_end' => $this->formatMinutes12h($overlapEnd),
                        'overlap_minutes' => max(0, $overlapEnd - $overlapStart),
                        'schedule_1_id' => $a['schedule']->id,
                        'schedule_2_id' => $b['schedule']->id,
                        'class_1' => [
                            'id' => $a['class']->id,
                            'subject_code' => $a['class']->subject_code,
                            'section' => $a['class']->section,
                            'delivery_mode' => $a['schedule']->delivery_mode,
                            'room_id' => $a['schedule']->room_id,
                            'room' => $a['schedule']->isOnline()
                                ? ScheduleDelivery::shortLabel($a['schedule']->delivery_mode)
                                : $a['schedule']->room?->name,
                            'faculty_id' => $a['class']->faculty_id,
                            'faculty' => $a['class']->Faculty
                                ? $a['class']->Faculty->first_name.' '.$a['class']->Faculty->last_name
                                : null,
                        ],
                        'class_2' => [
                            'id' => $b['class']->id,
                            'subject_code' => $b['class']->subject_code,
                            'section' => $b['class']->section,
                            'delivery_mode' => $b['schedule']->delivery_mode,
                            'room_id' => $b['schedule']->room_id,
                            'room' => $b['schedule']->isOnline()
                                ? ScheduleDelivery::shortLabel($b['schedule']->delivery_mode)
                                : $b['schedule']->room?->name,
                            'faculty_id' => $b['class']->faculty_id,
                            'faculty' => $b['class']->Faculty
                                ? $b['class']->Faculty->first_name.' '.$b['class']->Faculty->last_name
                                : null,
                        ],
                        'conflict_type' => $conflictTypes[0],
                        'conflict_types' => $conflictTypes,
                    ];
                }
            }
        }

        return $conflicts;
    }

    /**
     * Check whether two classes belong to the same student block section / cohort.
     */
    public function sharesStudentCohort(Classes $classA, Classes $classB): bool
    {
        if ((int) $classA->id === (int) $classB->id) {
            return false;
        }

        if (empty($classA->section) || empty($classB->section) || (string) $classA->section !== (string) $classB->section) {
            return false;
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

        // College cohort: same curriculum academic_year and at least one shared course_id
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

        if ($coursesA === [] || $coursesB === []) {
            return false;
        }

        return array_intersect($coursesA, $coursesB) !== [];
    }

    /**
     * Check if two classes have any overlapping schedule blocks on the same day.
     *
     * @param  array<string, mixed>|null  $policy
     */
    public function hasScheduleConflict(Classes $classA, Classes $classB, ?array $policy = null): bool
    {
        $resolvedPolicy = ScheduleDelivery::policy($policy);
        $schedulesA = $classA->relationLoaded('schedules') ? $classA->schedules : $classA->schedules()->get();
        $schedulesB = $classB->relationLoaded('schedules') ? $classB->schedules : $classB->schedules()->get();

        foreach ($schedulesA as $schedA) {
            if (! $schedA->start_time || ! $schedA->end_time || ! ScheduleDelivery::checksTimeConflicts($schedA->delivery_mode, $resolvedPolicy)) {
                continue;
            }
            $startA = (int) $schedA->start_time->format('H') * 60 + (int) $schedA->start_time->format('i');
            $endA = (int) $schedA->end_time->format('H') * 60 + (int) $schedA->end_time->format('i');

            foreach ($schedulesB as $schedB) {
                if (! $schedB->start_time || ! $schedB->end_time || ! ScheduleDelivery::checksTimeConflicts($schedB->delivery_mode, $resolvedPolicy)) {
                    continue;
                }
                if ($schedA->day_of_week !== $schedB->day_of_week) {
                    continue;
                }

                $startB = (int) $schedB->start_time->format('H') * 60 + (int) $schedB->start_time->format('i');
                $endB = (int) $schedB->end_time->format('H') * 60 + (int) $schedB->end_time->format('i');

                if ($startA < $endB && $endA > $startB) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Calculate required vs scheduled weekly hours for a class from its linked curriculum subject.
     *
     * @return array{
     *     lecture_hours: float,
     *     lab_hours: float,
     *     required_hours: float,
     *     scheduled_hours: float,
     *     required_minutes: int,
     *     scheduled_minutes: int,
     *     is_unscheduled: bool,
     *     is_complete: bool
     * }
     */
    public function calculateClassHours(Classes $class): array
    {
        $subject = $class->relationLoaded('Subject') ? $class->Subject : null;

        if (! $subject && $class->relationLoaded('subjects') && $class->subjects instanceof Collection && $class->subjects->isNotEmpty()) {
            $subject = $class->subjects->first();
        }

        $lectureHours = 0.0;
        $labHours = 0.0;

        if ($subject) {
            $lecture = (int) ($subject->lecture ?? 0);
            $lab = (int) ($subject->laboratory ?? 0);
            $units = (int) ($subject->units ?? 0);

            if ($lecture > 0 || $lab > 0) {
                $lectureHours = (float) $lecture;
                $labHours = (float) $lab;
            } elseif ($units > 0) {
                $lectureHours = (float) $units;
            } else {
                $lectureHours = 3.0;
            }
        } else {
            // Default for SHS or unlinked subject
            $lectureHours = 3.0;
        }

        $requiredHours = $lectureHours + $labHours;
        $requiredMinutes = (int) round($requiredHours * 60);

        $schedules = $class->relationLoaded('Schedule')
            ? $class->Schedule
            : ($class->relationLoaded('schedules') ? $class->schedules : collect());

        $scheduledMinutes = 0;
        foreach ($schedules as $schedule) {
            if (! $schedule instanceof Schedule || ! $schedule->start_time || ! $schedule->end_time) {
                continue;
            }
            $startMin = (int) $schedule->start_time->format('H') * 60 + (int) $schedule->start_time->format('i');
            $endMin = (int) $schedule->end_time->format('H') * 60 + (int) $schedule->end_time->format('i');
            if ($endMin > $startMin) {
                $scheduledMinutes += ($endMin - $startMin);
            }
        }

        $scheduledHours = round($scheduledMinutes / 60, 2);

        return [
            'lecture_hours' => $lectureHours,
            'lab_hours' => $labHours,
            'required_hours' => $requiredHours,
            'scheduled_hours' => $scheduledHours,
            'required_minutes' => $requiredMinutes,
            'scheduled_minutes' => $scheduledMinutes,
            'is_unscheduled' => $scheduledMinutes === 0,
            'is_complete' => $scheduledMinutes >= $requiredMinutes && $requiredMinutes > 0,
        ];
    }

    /**
     * Compute campus-wide Day x Hour utilization & capacity metrics for bird's-eye overview.
     *
     * @param  iterable<int, Classes>  $classes
     * @param  Collection<int, Room>  $rooms
     * @param  array<int, array<string, mixed>>|null  $precomputedConflicts
     * @return array<string, mixed>
     */
    public function computeUtilizationSummary(
        iterable $classes,
        Collection $rooms,
        ?array $precomputedConflicts = null,
    ): array {
        $conflicts = $precomputedConflicts ?? $this->detectConflictsForClasses($classes);
        $conflictScheduleIds = collect($conflicts)
            ->flatMap(fn (array $conflict): array => [
                $conflict['schedule_1_id'] ?? null,
                $conflict['schedule_2_id'] ?? null,
            ])
            ->filter()
            ->unique()
            ->flip();

        $roomsById = $rooms->keyBy('id');
        $totalActiveRooms = max(0, $rooms->count());
        $days = ScheduleDelivery::ALL_DAYS;
        $hours = range(7, 20);

        $cells = [];
        foreach ($days as $day) {
            foreach ($hours as $hour) {
                $cells["{$day}:{$hour}"] = [
                    'day' => $day,
                    'hour' => $hour,
                    'active_sessions' => 0,
                    'occupied_room_ids' => [],
                    'online_sessions' => 0,
                    'conflict_sessions' => 0,
                ];
            }
        }

        $overcrowdedCount = 0;
        $unassignedRoomSessions = 0;
        $totalScheduledSessions = 0;

        foreach ($classes as $class) {
            $schedules = $class->relationLoaded('Schedule')
                ? $class->Schedule
                : ($class->relationLoaded('schedules') ? $class->schedules : collect());

            $studentCount = (int) ($class->class_students_count ?? 0);

            foreach ($schedules as $schedule) {
                if (! $schedule instanceof Schedule || ! $schedule->start_time || ! $schedule->end_time) {
                    continue;
                }

                $totalScheduledSessions++;
                $isOnline = $schedule->isOnline();

                if (! $isOnline && empty($schedule->room_id)) {
                    $unassignedRoomSessions++;
                }

                if (! $isOnline && $schedule->room_id && $roomsById->has($schedule->room_id)) {
                    $roomCapacity = (int) ($roomsById->get($schedule->room_id)?->capacity ?? 0);
                    if ($roomCapacity > 0 && $studentCount > $roomCapacity) {
                        $overcrowdedCount++;
                    }
                }

                $startMin = (int) $schedule->start_time->format('H') * 60 + (int) $schedule->start_time->format('i');
                $endMin = (int) $schedule->end_time->format('H') * 60 + (int) $schedule->end_time->format('i');
                $isConflicting = $conflictScheduleIds->has($schedule->id);

                foreach ($hours as $hour) {
                    $hourStart = $hour * 60;
                    $hourEnd = ($hour + 1) * 60;
                    if ($startMin >= $hourEnd || $endMin <= $hourStart) {
                        continue;
                    }

                    $key = "{$schedule->day_of_week}:{$hour}";
                    if (! isset($cells[$key])) {
                        continue;
                    }

                    $cells[$key]['active_sessions']++;
                    if ($isOnline) {
                        $cells[$key]['online_sessions']++;
                    } elseif ($schedule->room_id) {
                        $cells[$key]['occupied_room_ids'][(int) $schedule->room_id] = true;
                    }
                    if ($isConflicting) {
                        $cells[$key]['conflict_sessions']++;
                    }
                }
            }
        }

        $heatmap = [];
        $peakCell = null;
        $occupiedSum = 0;
        $activeCellCount = 0;

        foreach ($cells as $cell) {
            $occupiedRooms = count($cell['occupied_room_ids']);
            $utilPct = $totalActiveRooms > 0
                ? (int) round(min(100, ($occupiedRooms / $totalActiveRooms) * 100))
                : 0;

            $entry = [
                'day' => $cell['day'],
                'hour' => $cell['hour'],
                'active_sessions' => $cell['active_sessions'],
                'occupied_rooms' => $occupiedRooms,
                'free_rooms' => max(0, $totalActiveRooms - $occupiedRooms),
                'online_sessions' => $cell['online_sessions'],
                'conflict_sessions' => $cell['conflict_sessions'],
                'room_utilization_pct' => $utilPct,
            ];

            $heatmap[] = $entry;

            if ($entry['active_sessions'] > 0) {
                $occupiedSum += $utilPct;
                $activeCellCount++;
            }

            if ($peakCell === null || $entry['active_sessions'] > $peakCell['active_sessions']) {
                $peakCell = $entry;
            }
        }

        return [
            'total_active_rooms' => $totalActiveRooms,
            'total_scheduled_sessions' => $totalScheduledSessions,
            'overcrowded_sessions_count' => $overcrowdedCount,
            'unassigned_room_sessions_count' => $unassignedRoomSessions,
            'average_room_utilization_pct' => $activeCellCount > 0 ? (int) round($occupiedSum / $activeCellCount) : 0,
            'peak_hour' => $peakCell && $peakCell['active_sessions'] > 0 ? $peakCell : null,
            'heatmap' => $heatmap,
        ];
    }

    /**
     * Suggest conflict-free replacement rooms and time slots for a given schedule block.
     *
     * @param  Collection<int, Classes>  $termClasses
     * @param  Collection<int, Room>  $rooms
     * @param  array<string, mixed>|null  $policy
     * @return array{
     *     schedule_id: int,
     *     available_rooms: list<array<string, mixed>>,
     *     available_slots: list<array<string, mixed>>,
     *     can_convert_to_online: bool
     * }
     */
    public function suggestResolutions(
        Schedule $schedule,
        Collection $termClasses,
        Collection $rooms,
        ?array $policy = null,
    ): array {
        $resolvedPolicy = ScheduleDelivery::policy($policy);
        $targetClass = $termClasses->firstWhere('id', $schedule->class_id) ?? $schedule->class;

        if (! $targetClass instanceof Classes || ! $schedule->start_time || ! $schedule->end_time) {
            return [
                'schedule_id' => (int) $schedule->id,
                'available_rooms' => [],
                'available_slots' => [],
                'can_convert_to_online' => false,
            ];
        }

        $startMin = (int) $schedule->start_time->format('H') * 60 + (int) $schedule->start_time->format('i');
        $endMin = (int) $schedule->end_time->format('H') * 60 + (int) $schedule->end_time->format('i');
        $duration = max(30, $endMin - $startMin);
        $studentCount = max((int) ($targetClass->class_students_count ?? 0), (int) ($targetClass->maximum_slots ?? 0));
        $isLab = ($schedule->component_type ?? 'lecture') === 'lab';
        $currentRoom = $rooms->firstWhere('id', $schedule->room_id);

        // Flatten all OTHER schedule blocks in the term once for fast conflict checks
        $otherBlocks = [];
        foreach ($termClasses as $termClass) {
            $classSchedules = $termClass->relationLoaded('Schedule')
                ? $termClass->Schedule
                : ($termClass->relationLoaded('schedules') ? $termClass->schedules : collect());

            foreach ($classSchedules as $other) {
                if (! $other instanceof Schedule || ! $other->start_time || ! $other->end_time) {
                    continue;
                }
                if ((int) $other->id === (int) $schedule->id) {
                    continue;
                }

                $otherBlocks[] = [
                    'class' => $termClass,
                    'schedule' => $other,
                    'day' => (string) $other->day_of_week,
                    'start_min' => (int) $other->start_time->format('H') * 60 + (int) $other->start_time->format('i'),
                    'end_min' => (int) $other->end_time->format('H') * 60 + (int) $other->end_time->format('i'),
                ];
            }
        }

        // 1. Available rooms for this exact day & time (face-to-face only)
        $availableRooms = [];
        if (! $schedule->isOnline()) {
            $busyRoomIds = [];
            foreach ($otherBlocks as $block) {
                if ($block['day'] !== $schedule->day_of_week) {
                    continue;
                }
                if ($block['start_min'] >= $endMin || $block['end_min'] <= $startMin) {
                    continue;
                }
                if (ScheduleDelivery::checksRoomConflicts($block['schedule']->delivery_mode, $resolvedPolicy) && $block['schedule']->room_id) {
                    $busyRoomIds[(int) $block['schedule']->room_id] = true;
                }
            }

            foreach ($rooms as $room) {
                if ((int) $room->id === (int) $schedule->room_id) {
                    continue;
                }
                if (isset($busyRoomIds[(int) $room->id])) {
                    continue;
                }

                $capacity = (int) ($room->capacity ?? 40);
                $capacityFit = $capacity >= $studentCount;
                $roomType = mb_strtolower((string) ($room->type ?? 'lecture'));
                $typeMatch = $isLab
                    ? (str_contains($roomType, 'lab'))
                    : (! str_contains($roomType, 'lab'));
                $sameBuilding = $currentRoom && filled($currentRoom->building) && $currentRoom->building === $room->building;

                // Lower score = better recommendation
                $score = ($capacityFit ? 0 : 1000)
                    + ($typeMatch ? 0 : 250)
                    + ($sameBuilding ? 0 : 50)
                    + abs($capacity - $studentCount);

                $reasons = [];
                if ($typeMatch) {
                    $reasons[] = $isLab ? 'Lab-ready' : 'Lecture room';
                }
                if ($capacityFit) {
                    $reasons[] = "Fits {$studentCount} ({$capacity} cap)";
                } else {
                    $reasons[] = "Smaller ({$capacity} cap)";
                }
                if ($sameBuilding) {
                    $reasons[] = 'Same building';
                }

                $availableRooms[] = [
                    'id' => (int) $room->id,
                    'room_id' => (int) $room->id,
                    'name' => $room->name,
                    'class_code' => $room->class_code,
                    'capacity' => $capacity,
                    'type' => $room->type ?? 'lecture',
                    'building' => $room->building,
                    'capacity_fit' => $capacityFit,
                    'fits_capacity' => $capacityFit,
                    'type_match' => $typeMatch,
                    'reason' => implode(' · ', $reasons),
                    '_score' => $score,
                ];
            }

            usort($availableRooms, fn (array $a, array $b): int => $a['_score'] <=> $b['_score']);
            $availableRooms = array_map(
                static fn (array $item): array => array_diff_key($item, ['_score' => true]),
                array_slice($availableRooms, 0, 6)
            );
        }

        // 2. Nearest conflict-free time slots (keeping duration & room/delivery mode)
        $allowedDays = ScheduleDelivery::allowedDays($resolvedPolicy);
        $orderedDays = array_values(array_unique(array_merge(
            in_array($schedule->day_of_week, $allowedDays, true) ? [(string) $schedule->day_of_week] : [],
            $allowedDays
        )));

        $candidateSlots = [];
        $candidateStarts = range(7 * 60, 20 * 60, 30);

        foreach ($orderedDays as $dayIndex => $day) {
            foreach ($candidateStarts as $candStart) {
                $candEnd = $candStart + $duration;
                if ($candEnd > 21 * 60) {
                    continue;
                }
                if ($day === $schedule->day_of_week && $candStart === $startMin) {
                    continue;
                }

                if ($this->wouldCandidateConflict(
                    $targetClass,
                    $schedule,
                    $day,
                    $candStart,
                    $candEnd,
                    $otherBlocks,
                    $resolvedPolicy
                )) {
                    continue;
                }

                $sameDay = $day === $schedule->day_of_week;
                $shiftMinutes = abs($candStart - $startMin);
                $score = ($sameDay ? 0 : 120 + ($dayIndex * 30)) + $shiftMinutes;

                $start24 = $this->formatMinutes24h($candStart);
                $end24 = $this->formatMinutes24h($candEnd);
                $timeRange = $this->formatMinutes12h($candStart).' - '.$this->formatMinutes12h($candEnd);

                $candidateSlots[] = [
                    'day_of_week' => $day,
                    'start_time' => $start24,
                    'end_time' => $end24,
                    'time_range' => $timeRange,
                    'room_id' => $schedule->isOnline() ? null : $schedule->room_id,
                    'room_name' => $schedule->isOnline()
                        ? ScheduleDelivery::shortLabel($schedule->delivery_mode)
                        : ($schedule->room?->name ?? null),
                    'same_day' => $sameDay,
                    'shift_minutes' => $candStart - $startMin,
                    'reason' => $sameDay
                        ? ($candStart > $startMin ? 'Later same day' : 'Earlier same day')
                        : ($shiftMinutes === 0 ? "Same time on {$day}" : "Open slot on {$day}"),
                    '_score' => $score,
                ];
            }
        }

        usort($candidateSlots, fn (array $a, array $b): int => $a['_score'] <=> $b['_score']);
        $availableSlots = array_map(
            static fn (array $item): array => array_diff_key($item, ['_score' => true]),
            array_slice($candidateSlots, 0, 8)
        );

        return [
            'schedule_id' => (int) $schedule->id,
            'available_rooms' => $availableRooms,
            'available_slots' => $availableSlots,
            'can_convert_to_online' => ! $schedule->isOnline(),
        ];
    }

    /**
     * @param  array<int, array{class: Classes, schedule: Schedule, day: string, start_min: int, end_min: int}>  $otherBlocks
     * @param  array<string, mixed>  $policy
     */
    private function wouldCandidateConflict(
        Classes $targetClass,
        Schedule $schedule,
        string $day,
        int $startMin,
        int $endMin,
        array $otherBlocks,
        array $policy,
    ): bool {
        $checksRoom = ScheduleDelivery::checksRoomConflicts($schedule->delivery_mode, $policy) && ! empty($schedule->room_id);
        $checksFaculty = ScheduleDelivery::checksFacultyConflicts($schedule->delivery_mode, $policy) && ! empty($targetClass->faculty_id);
        $checksCohort = ScheduleDelivery::checksStudentCohortConflicts($schedule->delivery_mode, $policy);

        foreach ($otherBlocks as $block) {
            if ($block['day'] !== $day) {
                continue;
            }
            if ($block['start_min'] >= $endMin || $block['end_min'] <= $startMin) {
                continue;
            }

            $sameClass = (int) $block['class']->id === (int) $targetClass->id;
            if ($sameClass) {
                return true;
            }

            if (
                $checksRoom
                && ScheduleDelivery::checksRoomConflicts($block['schedule']->delivery_mode, $policy)
                && $block['schedule']->room_id
                && (int) $block['schedule']->room_id === (int) $schedule->room_id
            ) {
                return true;
            }

            if (
                $checksFaculty
                && ScheduleDelivery::checksFacultyConflicts($block['schedule']->delivery_mode, $policy)
                && $block['class']->faculty_id
                && (string) $block['class']->faculty_id === (string) $targetClass->faculty_id
            ) {
                return true;
            }

            if (
                $checksCohort
                && ScheduleDelivery::checksStudentCohortConflicts($block['schedule']->delivery_mode, $policy)
                && $this->sharesStudentCohort($targetClass, $block['class'])
            ) {
                return true;
            }
        }

        return false;
    }

    private function formatMinutes24h(int $totalMinutes): string
    {
        $hours = intdiv($totalMinutes, 60);
        $minutes = $totalMinutes % 60;

        return sprintf('%02d:%02d', $hours, $minutes);
    }

    private function formatMinutes12h(int $totalMinutes): string
    {
        $hours = intdiv($totalMinutes, 60) % 24;
        $minutes = $totalMinutes % 60;
        $suffix = $hours >= 12 ? 'PM' : 'AM';
        $hour12 = $hours % 12 ?: 12;

        return sprintf('%02d:%02d %s', $hour12, $minutes, $suffix);
    }
}
