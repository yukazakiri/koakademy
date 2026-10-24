<?php

declare(strict_types=1);

namespace App\Services\Scheduling;

use App\Models\Classes;
use App\Models\Schedule;
use Illuminate\Support\Collection;

final class ScheduleConflictService
{
    /**
     * Detect room, faculty, and cohort-section conflicts across a collection of classes.
     *
     * @param  iterable<int, Classes>  $classes
     * @return array<int, array<string, mixed>>
     */
    public function detectConflictsForClasses(iterable $classes, bool $includeSectionConflicts = true): array
    {
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

                $roomConflict = ! $a['class']->isOnline()
                    && ! $b['class']->isOnline()
                    && $a['schedule']->room_id
                    && $b['schedule']->room_id
                    && (int) $a['schedule']->room_id === (int) $b['schedule']->room_id;

                $facultyConflict = $a['class']->faculty_id
                    && $b['class']->faculty_id
                    && (string) $a['class']->faculty_id === (string) $b['class']->faculty_id;

                $sectionConflict = $includeSectionConflicts
                    && ! $roomConflict
                    && ! $facultyConflict
                    && $this->sharesStudentCohort($a['class'], $b['class']);

                if ($roomConflict || $facultyConflict || $sectionConflict) {
                    $timeA = $a['schedule']->time_range;
                    $timeB = $b['schedule']->time_range;
                    $timeStr = $timeA === $timeB ? $timeA : "{$timeA} vs {$timeB}";

                    $conflictType = $roomConflict
                        ? 'room'
                        : ($facultyConflict ? 'faculty' : 'section');

                    $conflicts[] = [
                        'day' => $a['day'],
                        'time' => $timeStr,
                        'schedule_1_id' => $a['schedule']->id,
                        'schedule_2_id' => $b['schedule']->id,
                        'class_1' => [
                            'id' => $a['class']->id,
                            'subject_code' => $a['class']->subject_code,
                            'section' => $a['class']->section,
                            'room' => $a['schedule']->room?->name,
                            'faculty' => $a['class']->Faculty
                                ? $a['class']->Faculty->first_name.' '.$a['class']->Faculty->last_name
                                : null,
                        ],
                        'class_2' => [
                            'id' => $b['class']->id,
                            'subject_code' => $b['class']->subject_code,
                            'section' => $b['class']->section,
                            'room' => $b['schedule']->room?->name,
                            'faculty' => $b['class']->Faculty
                                ? $b['class']->Faculty->first_name.' '.$b['class']->Faculty->last_name
                                : null,
                        ],
                        'conflict_type' => $conflictType,
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
     */
    public function hasScheduleConflict(Classes $classA, Classes $classB): bool
    {
        $schedulesA = $classA->relationLoaded('schedules') ? $classA->schedules : $classA->schedules()->get();
        $schedulesB = $classB->relationLoaded('schedules') ? $classB->schedules : $classB->schedules()->get();

        foreach ($schedulesA as $schedA) {
            if (! $schedA->start_time || ! $schedA->end_time) {
                continue;
            }
            $startA = (int) $schedA->start_time->format('H') * 60 + (int) $schedA->start_time->format('i');
            $endA = (int) $schedA->end_time->format('H') * 60 + (int) $schedA->end_time->format('i');

            foreach ($schedulesB as $schedB) {
                if (! $schedB->start_time || ! $schedB->end_time) {
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
}
