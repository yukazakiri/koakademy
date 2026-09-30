<?php

declare(strict_types=1);

namespace App\Services\Ai;

use App\Models\ClassEnrollment;
use App\Models\Classes;
use App\Models\EnrollmentWorkflowEvent;
use App\Models\Schedule;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\Subject;
use App\Models\SubjectEnrollment;
use App\Models\User;
use App\Services\ClassEnrollmentService;
use App\Services\EnrollmentBillingService;
use DateTimeInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * SectionTransferService
 *
 * Moves a student between class sections of the same subject while keeping the
 * rest of their academic record coherent.
 *
 * Moving a student is not a single-column update. It touches three things that
 * can silently drift apart:
 *   - `subject_enrollments.class_id` (the curricular record the assessment and
 *     transcript read from)
 *   - `class_enrollments` (the roster row that drives seat counts and
 *     attendance)
 *   - `student_tuition` (recomputed from the subject set, since lecture/lab
 *     units can differ between sections)
 *
 * The service always offers a dry run first. That dry run reports the student's
 * resulting timetable, any clash with their other classes, seat availability in
 * the destination, and the tuition delta — so the agent can tell the
 * administrator what will happen *before* anything is written, and can
 * recommend a concrete alternative when a conflict appears.
 */
final class SectionTransferService
{
    public function __construct(
        private readonly InstitutionEntityResolver $entities = new InstitutionEntityResolver,
        private readonly ClassEnrollmentService $classEnrollments = new ClassEnrollmentService,
        private readonly EnrollmentBillingService $billing = new EnrollmentBillingService,
    ) {}

    /**
     * Dry run. Answers "what would happen if I moved this student?" without
     * writing anything.
     *
     * @return array<string, mixed>
     */
    public function preview(
        Student $student,
        mixed $sourceIdentifier,
        mixed $destinationIdentifier,
        ?int $fromClassId = null,
    ): array {
        [$subjectEnrollment, $sourceClass, $resolutionError] = $this->locateSubjectEnrollment($student, $sourceIdentifier, $fromClassId);

        if ($resolutionError !== null || ! $subjectEnrollment instanceof SubjectEnrollment) {
            return $this->failure($resolutionError ?? 'The subject enrollment could not be located.', $subjectEnrollment);
        }

        $destination = $this->resolveDestination($destinationIdentifier);

        if ($destination['error'] !== null) {
            return $this->failure($destination['error'], $subjectEnrollment);
        }

        $destinationClass = $destination['class'];

        if (! $destinationClass instanceof Classes) {
            return $this->unassignPreview($student, $subjectEnrollment, $sourceClass);
        }

        $period = $this->periodFor($subjectEnrollment);
        $seatCheck = $this->seatAvailability($destinationClass, $student);
        $subjectCheck = $this->subjectMatch($subjectEnrollment, $destinationClass);
        $scheduleConflicts = $this->scheduleConflicts($student, $subjectEnrollment, $destinationClass);
        $timetable = $this->projectedTimetable($student, $subjectEnrollment, $destinationClass);
        $financial = $this->financialImpact($subjectEnrollment, $destinationClass);

        $blockers = $this->blockers($seatCheck, $subjectCheck, $scheduleConflicts, $period);

        return [
            'success' => true,
            'preview' => true,
            'can_transfer' => $blockers === [],
            'blockers' => $blockers,
            'warnings' => $this->warnings($seatCheck, $subjectCheck, $scheduleConflicts, $financial, $period),
            'recommendation' => $this->recommendation($blockers, $seatCheck, $subjectCheck, $scheduleConflicts, $destinationClass),
            'student' => $this->studentSummary($student),
            'subject' => [
                'subject_enrollment_id' => $subjectEnrollment->id,
                'subject_id' => $subjectEnrollment->subject_id,
                'code' => $subjectEnrollment->subject?->code ?? $subjectEnrollment->external_subject_code,
                'title' => $subjectEnrollment->subject?->title ?? $subjectEnrollment->external_subject_title,
                'units' => $subjectEnrollment->subject?->units,
                'lecture_units' => (int) $subjectEnrollment->enrolled_lecture_units,
                'laboratory_units' => (int) $subjectEnrollment->enrolled_laboratory_units,
            ],
            'enrollment_id' => $subjectEnrollment->enrollment_id,
            'current' => $this->classState($sourceClass, $subjectEnrollment->section),
            'proposed' => $this->classState($destinationClass, $destinationClass->section),
            'seats' => $seatCheck,
            'subject_match' => $subjectCheck,
            'academic_period' => $period,
            'schedule_conflicts' => $scheduleConflicts,
            'resulting_timetable' => $timetable,
            'financial_impact' => $financial,
        ];
    }

    /**
     * Perform the move. Idempotent: replaying the same `idempotency_key`
     * returns the original outcome instead of transferring twice.
     *
     * @return array<string, mixed>
     */
    public function transfer(
        Student $student,
        mixed $sourceIdentifier,
        mixed $destinationIdentifier,
        User $actor,
        ?string $reason = null,
        ?int $fromClassId = null,
        ?string $idempotencyKey = null,
        bool $force = false,
    ): array {
        $idempotencyKey ??= (string) Str::uuid();
        $scopedKey = hash('sha256', "ai:section-transfer:{$student->id}:{$idempotencyKey}");

        $replay = EnrollmentWorkflowEvent::query()
            ->where('idempotency_key', $scopedKey)
            ->first();

        if ($replay instanceof EnrollmentWorkflowEvent && is_array($replay->result)) {
            return [...$replay->result, 'replayed' => true];
        }

        $assessment = $this->preview($student, $sourceIdentifier, $destinationIdentifier, $fromClassId);

        if (($assessment['success'] ?? false) !== true) {
            return $assessment;
        }

        if (($assessment['can_transfer'] ?? false) !== true && ! $force) {
            return [
                ...$assessment,
                'transferred' => false,
                'message' => 'Transfer blocked. Resolve the blockers above, or retry with force=true to override schedule and seat warnings.',
            ];
        }

        $subjectEnrollmentId = (int) $assessment['subject']['subject_enrollment_id'];
        $destinationClassId = $assessment['proposed']['class_id'] ?? null;

        $outcome = DB::transaction(function () use ($subjectEnrollmentId, $destinationClassId, $actor, $reason, $scopedKey, $student, $assessment, $force): array {
            /** @var SubjectEnrollment $subjectEnrollment */
            $subjectEnrollment = SubjectEnrollment::query()->lockForUpdate()->findOrFail($subjectEnrollmentId);

            $before = [
                'class_id' => $subjectEnrollment->class_id,
                'section' => $subjectEnrollment->section,
            ];

            $sourceClassId = $subjectEnrollment->class_id;

            if ($destinationClassId === null) {
                $subjectEnrollment->update(['class_id' => null, 'section' => null]);
            } else {
                $destinationClass = Classes::query()->findOrFail($destinationClassId);
                $subjectEnrollment->update([
                    'class_id' => $destinationClass->id,
                    'section' => $destinationClass->section,
                ]);
            }

            $this->syncRoster($student, $sourceClassId, $destinationClassId);

            $financial = $this->applyTuitionRecalculation($subjectEnrollment, $destinationClassId, $assessment);

            EnrollmentWorkflowEvent::query()->create([
                'student_enrollment_id' => $subjectEnrollment->enrollment_id,
                'actor_id' => $actor->id,
                'event_type' => 'section_transferred',
                'idempotency_key' => $scopedKey,
                'reason' => $reason,
                'result' => [
                    'transferred' => true,
                    'student_id' => (int) $student->id,
                    'subject_enrollment_id' => $subjectEnrollment->id,
                    'subject_code' => $assessment['subject']['code'] ?? null,
                    'from' => $assessment['current'],
                    'to' => $assessment['proposed'],
                    'before' => $before,
                    'forced' => $force && ! empty($assessment['blockers']),
                    'financial_impact' => $financial,
                ],
            ]);

            return [
                'success' => true,
                'transferred' => true,
                'replayed' => false,
                'student' => $assessment['student'],
                'subject' => $assessment['subject'],
                'enrollment_id' => $subjectEnrollment->enrollment_id,
                'from' => $assessment['current'],
                'to' => $assessment['proposed'],
                'warnings' => $assessment['warnings'] ?? [],
                'overridden_blockers' => $force ? ($assessment['blockers'] ?? []) : [],
                'financial_impact' => $financial,
                'resulting_timetable' => $this->projectedTimetable($student->refresh(), $subjectEnrollment, $destinationClassId === null ? null : Classes::query()->find($destinationClassId)),
                'message' => $destinationClassId === null
                    ? sprintf('Removed the class section for %s while keeping the subject enrollment.', (string) ($assessment['subject']['code'] ?? 'the subject'))
                    : sprintf(
                        'Moved %s from %s to %s.',
                        (string) ($assessment['subject']['code'] ?? 'the subject'),
                        (string) ($assessment['current']['label'] ?? 'the previous section'),
                        (string) ($assessment['proposed']['label'] ?? 'the new section'),
                    ),
            ];
        }, 3);

        return $outcome;
    }

    /**
     * Move the roster row to match the subject enrollment.
     *
     * The roster is authoritative for seat counts and attendance, so a section
     * change that leaves the old roster row behind would report a phantom seat
     * in the source section and an undercount in the destination.
     */
    private function syncRoster(Student $student, ?int $sourceClassId, ?int $destinationClassId): void
    {
        $studentId = (int) $student->id;

        if ($sourceClassId !== null && $sourceClassId !== $destinationClassId) {
            ClassEnrollment::query()
                ->where('student_id', $studentId)
                ->where('class_id', $sourceClassId)
                ->delete();
        }

        if ($destinationClassId === null) {
            return;
        }

        Student::query()->whereKey($studentId)->lockForUpdate()->first();

        $this->classEnrollments->enrollOnceWhileLocked($studentId, $destinationClassId, [
            'status' => true,
            'school_id' => $student->school_id,
        ]);
    }

    /**
     * Recompute tuition when the section swap changes assessed units.
     *
     * @param  array<string, mixed>  $assessment
     * @return array<string, mixed>
     */
    private function applyTuitionRecalculation(SubjectEnrollment $subjectEnrollment, ?int $destinationClassId, array $assessment): array
    {
        $before = $assessment['financial_impact']['before'] ?? [];

        if ($subjectEnrollment->enrollment_id === null) {
            return ['recalculated' => false, 'reason' => 'The subject enrollment is not linked to a student enrollment record.'];
        }

        $enrollment = StudentEnrollment::withTrashed()->find($subjectEnrollment->enrollment_id);

        if (! $enrollment instanceof StudentEnrollment) {
            return ['recalculated' => false, 'reason' => 'The linked enrollment record no longer exists.'];
        }

        if (! $enrollment->studentTuition()->exists()) {
            return ['recalculated' => false, 'reason' => 'No tuition assessment is attached to this enrollment, so no fee changed.'];
        }

        $this->billing->recalculateEnrollmentTuition($enrollment);

        $tuition = $enrollment->studentTuition()->first();
        $after = $tuition === null ? [] : $this->billing->toSummaryArray($tuition);

        return [
            'recalculated' => true,
            'tuition_id' => $tuition?->id,
            'before' => $before,
            'after' => [
                'total_tuition' => $after['total_tuition'] ?? null,
                'overall_tuition' => $after['overall_tuition'] ?? null,
                'balance_due' => $after['balance_due'] ?? null,
            ],
            'balance_due_delta' => isset($after['balance_due'], $before['balance_due'])
                ? round((float) $after['balance_due'] - (float) $before['balance_due'], 2)
                : null,
            'destination_class_id' => $destinationClassId,
        ];
    }

    /**
     * Find the subject enrollment the administrator means.
     *
     * @return array{0: ?SubjectEnrollment, 1: ?Classes, 2: ?string}
     */
    private function locateSubjectEnrollment(Student $student, mixed $identifier, ?int $fromClassId): array
    {
        $active = SubjectEnrollment::query()
            ->where('student_id', (int) $student->id)
            ->whereNull('grade');

        $all = (clone $active)->with(['subject', 'class'])->get();

        if ($all->isEmpty()) {
            return [null, null, 'This student has no active subject enrollments to transfer.'];
        }

        if ($fromClassId !== null) {
            $match = $all->first(fn (SubjectEnrollment $record): bool => (int) $record->class_id === $fromClassId);

            return $match instanceof SubjectEnrollment
                ? [$match, $match->class, null]
                : [null, null, "The student is not enrolled in class #{$fromClassId}."];
        }

        $subject = $this->entities->subject($identifier);

        if ($subject instanceof Subject) {
            $matches = $all
                ->filter(fn (SubjectEnrollment $record): bool => (int) $record->subject_id === (int) $subject->id)
                ->values();

            if ($matches->count() === 1) {
                $match = $matches->first();

                return [$match, $match->class, null];
            }

            // A student may only occupy one section of a subject. If the data
            // says otherwise, guessing would move an arbitrary record, so the
            // caller is told to disambiguate with from_class_id instead.
            if ($matches->count() > 1) {
                return [null, null, sprintf(
                    'The student has %d active enrollments for subject %s (sections: %s). Re-run with from_class_id to say which one to move.',
                    $matches->count(),
                    $subject->code,
                    $matches->map(fn (SubjectEnrollment $record): string => sprintf(
                        'class_id=%d (section %s)',
                        (int) $record->class_id,
                        (string) ($record->class?->section ?? $record->section ?? 'none'),
                    ))->implode(', '),
                )];
            }

            return [null, null, sprintf('The student is not enrolled in subject %s. Enrolled subjects: %s.', $subject->code, $this->enrolledSubjectCodes($all))];
        }

        $class = $all->first(fn (SubjectEnrollment $record): bool => $record->class !== null
            && $this->classLabelMatches($record, (string) $identifier));

        if ($class instanceof SubjectEnrollment) {
            return [$class, $class->class, null];
        }

        $normalized = mb_strtolower(mb_trim((string) $identifier));
        $bySection = $all->first(fn (SubjectEnrollment $record): bool => mb_strtolower((string) $record->section) === $normalized);

        if ($bySection instanceof SubjectEnrollment) {
            return [$bySection, $bySection->class, null];
        }

        if (mb_strtolower($normalized) === 'all' || $normalized === '*') {
            return [null, null, sprintf('This student has %d active subject enrollments. Name the subject or the current section to transfer: %s.', $all->count(), $this->enrolledSubjectCodes($all))];
        }

        return [null, null, sprintf('Could not identify which subject to transfer from "%s". The student is enrolled in: %s.', (string) $identifier, $this->enrolledSubjectCodes($all))];
    }

    /**
     * A blank destination means "keep the subject, drop the section".
     *
     * @return array<string, mixed>
     */
    private function unassignPreview(Student $student, SubjectEnrollment $subjectEnrollment, ?Classes $sourceClass): array
    {
        return [
            'success' => true,
            'preview' => true,
            'can_transfer' => true,
            'blockers' => [],
            'warnings' => ['No destination section given: the student keeps the subject enrollment but loses the class section, room, and instructor assignment.'],
            'recommendation' => 'Confirm the administrator intends to remove the section rather than move to another one.',
            'student' => $this->studentSummary($student),
            'subject' => [
                'subject_enrollment_id' => $subjectEnrollment->id,
                'subject_id' => $subjectEnrollment->subject_id,
                'code' => $subjectEnrollment->subject?->code,
                'title' => $subjectEnrollment->subject?->title,
            ],
            'enrollment_id' => $subjectEnrollment->enrollment_id,
            'current' => $this->classState($sourceClass, $subjectEnrollment->section),
            'proposed' => ['class_id' => null, 'label' => 'No section (subject retained)', 'room' => null, 'instructor' => null, 'schedules' => []],
            'seats' => ['available' => true, 'message' => 'Not applicable; the student is leaving a section.'],
            'subject_match' => ['matches' => true, 'message' => 'Not applicable.'],
            'schedule_conflicts' => [],
            'resulting_timetable' => $this->projectedTimetable($student, $subjectEnrollment, null),
            'financial_impact' => ['changed' => false, 'reason' => 'Removing a section does not change the assessed units.'],
        ];
    }

    /**
     * @return array{class: ?Classes, error: ?string}
     */
    private function resolveDestination(mixed $identifier): array
    {
        if ($identifier === null || (is_string($identifier) && mb_trim($identifier) === '')) {
            return ['class' => null, 'error' => null];
        }

        try {
            return ['class' => $this->entities->class($identifier), 'error' => null];
        } catch (Exceptions\EntityNotFoundException|Exceptions\AmbiguousEntityException $e) {
            return ['class' => null, 'error' => $e->getMessage()];
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function seatAvailability(Classes $class, Student $student): array
    {
        $enrolled = ClassEnrollment::query()->where('class_id', $class->id)->count();
        $alreadyIn = ClassEnrollment::query()
            ->where('class_id', $class->id)
            ->where('student_id', (int) $student->id)
            ->exists();

        $capacity = (int) $class->maximum_slots;
        $remaining = $capacity > 0 ? $capacity - $enrolled : null;

        return [
            'available' => $alreadyIn || $remaining === null || $remaining > 0,
            'already_enrolled' => $alreadyIn,
            'enrolled' => $enrolled,
            'capacity' => $capacity > 0 ? $capacity : null,
            'remaining_seats' => $remaining,
            'message' => $capacity <= 0
                ? 'This section has no seat limit set.'
                : sprintf('%d of %d seats taken; %d remaining.', $enrolled, $capacity, max(0, $remaining ?? 0)),
        ];
    }

    /**
     * @return array{matches: bool, message: string}
     */
    private function subjectMatch(SubjectEnrollment $subjectEnrollment, Classes $destination): array
    {
        $sourceCode = mb_strtoupper(mb_trim((string) ($subjectEnrollment->subject?->code ?? $subjectEnrollment->external_subject_code ?? '')));
        $destinationCode = mb_strtoupper(mb_trim((string) $destination->subject_code));

        $matches = $subjectEnrollment->subject_id !== null
            && $destination->subject_id !== null
            && (int) $subjectEnrollment->subject_id === (int) $destination->subject_id;

        if (! $matches && $sourceCode !== '' && $destinationCode !== '') {
            $matches = $sourceCode === $destinationCode;
        }

        return [
            'matches' => $matches,
            'message' => $matches
                ? 'The destination section covers the same subject.'
                : sprintf('Subject mismatch: the student is enrolled in %s but the destination section is %s. This changes what they are taking, so enroll them in the subject instead of transferring the section.', $sourceCode !== '' ? $sourceCode : 'an unknown subject', $destinationCode !== '' ? $destinationCode : 'an unknown subject'),
        ];
    }

    /**
     * Clashes between the destination section and the student's other classes.
     *
     * @return array<int, array<string, mixed>>
     */
    private function scheduleConflicts(Student $student, SubjectEnrollment $subjectEnrollment, Classes $destination): array
    {
        // Every class the student already sits in, including the one they are
        // leaving. The leaving section is dropped from the comparison so the
        // source class is never reported as a clash with itself, but the source
        // stays in the roster set for the projected timetable.
        $rosterClassIds = ClassEnrollment::query()
            ->where('student_id', (int) $student->id)
            ->pluck('class_id')
            ->filter()
            ->unique()
            ->values()
            ->reject(fn ($id): bool => (int) $id === (int) $destination->id)
            ->merge([(int) $subjectEnrollment->class_id])
            ->filter()
            ->unique()
            ->values();

        if ($rosterClassIds->isEmpty()) {
            return [];
        }

        $classes = Classes::query()
            ->whereIn('id', $rosterClassIds)
            ->where('id', '!=', $destination->id)
            ->with(['schedules.room', 'faculty:id,first_name,last_name'])
            ->get();

        return $classes
            ->flatMap(fn (Classes $class): array => $this->pairwiseClashes($class, $destination))
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function pairwiseClashes(Classes $left, Classes $right): array
    {
        $clashes = [];

        foreach ($left->schedules as $leftSchedule) {
            foreach ($right->schedules as $rightSchedule) {
                if (mb_strtolower((string) $leftSchedule->day_of_week) !== mb_strtolower((string) $rightSchedule->day_of_week)) {
                    continue;
                }

                if (! $this->timesOverlap($leftSchedule, $rightSchedule)) {
                    continue;
                }

                $clashes[] = [
                    'day' => $leftSchedule->day_of_week,
                    'time' => sprintf('%s - %s', $leftSchedule->formatted_start_time, $leftSchedule->formatted_start_time),
                    'overlap' => sprintf(
                        '%s %s-%s clashes with %s %s-%s',
                        $left->subject_code,
                        (string) $left->section,
                        $leftSchedule->formatted_start_time,
                        $right->subject_code,
                        (string) $right->section,
                        $rightSchedule->formatted_start_time,
                    ),
                    'existing_class' => $this->classState($left, $left->section),
                    'incoming_class' => $this->classState($right, $right->section),
                ];
            }
        }

        return $clashes;
    }

    private function timesOverlap(Schedule $left, Schedule $right): bool
    {
        $leftStart = $this->secondsOfDay($left->start_time);
        $leftEnd = $this->secondsOfDay($left->end_time);
        $rightStart = $this->secondsOfDay($right->start_time);
        $rightEnd = $this->secondsOfDay($right->end_time);

        if ($leftStart === null || $leftEnd === null || $rightStart === null || $rightEnd === null) {
            return false;
        }

        return $leftStart < $rightEnd && $rightStart < $leftEnd;
    }

    private function secondsOfDay(mixed $time): ?int
    {
        if ($time instanceof DateTimeInterface) {
            return ((int) $time->format('H')) * 3600 + ((int) $time->format('i')) * 60 + (int) $time->format('s');
        }

        if (! is_string($time) || preg_match('/^(\d{1,2}):(\d{2})/', $time, $matches) !== 1) {
            return null;
        }

        return ((int) $matches[1]) * 3600 + ((int) $matches[2]) * 60;
    }

    /**
     * The student's timetable as it would be after the move.
     *
     * @return array<int, array<string, mixed>>
     */
    private function projectedTimetable(Student $student, SubjectEnrollment $subjectEnrollment, ?Classes $destination): array
    {
        $studentId = (int) $student->id;

        $classIds = ClassEnrollment::query()
            ->where('student_id', $studentId)
            ->pluck('class_id')
            ->filter()
            ->unique()
            ->values();

        if ($destination instanceof Classes) {
            $classIds = $classIds->push($destination->id)->unique()->values();
        } else {
            $classIds = $classIds->reject(fn ($id): bool => (int) $id === (int) $subjectEnrollment->class_id)->values();
        }

        if ($classIds->isEmpty()) {
            return [];
        }

        $classes = Classes::query()
            ->whereIn('id', $classIds)
            ->with(['schedules.room', 'faculty:id,first_name,last_name'])
            ->get();

        $rows = [];

        foreach ($classes as $class) {
            foreach ($class->schedules as $schedule) {
                $rows[] = [
                    'subject_code' => (string) $class->subject_code,
                    'section' => (string) $class->section,
                    'day' => (string) $schedule->day_of_week,
                    'time' => sprintf('%s - %s', $schedule->formatted_start_time, $schedule->formatted_end_time),
                    'room' => $schedule->room?->name ?? $class->room?->name,
                    'instructor' => $class->faculty?->full_name,
                ];
            }
        }

        $dayOrder = ['Monday' => 1, 'Tuesday' => 2, 'Wednesday' => 3, 'Thursday' => 4, 'Friday' => 5, 'Saturday' => 6, 'Sunday' => 7];

        usort($rows, function (array $a, array $b) use ($dayOrder): int {
            return [($dayOrder[$a['day']] ?? 99), $a['time']] <=> [($dayOrder[$b['day']] ?? 99), $b['time']];
        });

        return $rows;
    }

    /**
     * Fee impact of the section change, measured against live ledger figures.
     *
     * @return array<string, mixed>
     */
    private function financialImpact(SubjectEnrollment $subjectEnrollment, ?Classes $destination): array
    {
        $enrollmentId = $subjectEnrollment->enrollment_id;

        if ($enrollmentId === null) {
            return ['changed' => false, 'reason' => 'The subject enrollment is not linked to a student enrollment record, so no fee changes.'];
        }

        $enrollment = StudentEnrollment::withTrashed()->find($enrollmentId);

        if (! $enrollment instanceof StudentEnrollment) {
            return ['changed' => false, 'reason' => 'The linked enrollment record no longer exists.'];
        }

        $tuition = $enrollment->studentTuition()->first();

        if ($tuition === null) {
            return ['changed' => false, 'reason' => 'No tuition assessment is attached to this enrollment.'];
        }

        $before = $this->billing->toSummaryArray($tuition);
        $otherClassIds = $destination instanceof Classes
            ? ClassEnrollment::query()->where('student_id', $subjectEnrollment->student_id)->pluck('class_id')->filter()->unique()
            : collect();
        $otherUnits = $otherClassIds
            ->map(fn ($id) => Classes::query()->with('subject:id,lecture,laboratory,course_id')->find($id)?->subject)
            ->filter()
            ->sum(fn ($subject): int => (int) $subject->lecture);

        return [
            'tuition_id' => $tuition->id,
            'before' => [
                'total_tuition' => $before['total_tuition'],
                'overall_tuition' => $before['overall_tuition'],
                'balance_due' => $before['balance_due'],
                'total_paid' => $before['total_paid'],
            ],
            'lectures_outside_this_subject' => $otherUnits,
            'note' => 'Lecture units can differ between sections of the same subject; the balance is recomputed and reported after the move.',
        ];
    }

    /**
     * @param  array<string, mixed>  $seatCheck
     * @param  array{matches: bool, message: string}  $subjectCheck
     * @param  array<int, array<string, mixed>>  $scheduleConflicts
     * @param  array<string, mixed>  $period
     * @return array<int, string>
     */
    private function blockers(array $seatCheck, array $subjectCheck, array $scheduleConflicts, array $period): array
    {
        $blockers = [];

        if ($subjectCheck['matches'] === false) {
            $blockers[] = $subjectCheck['message'];
        }

        if ($seatCheck['available'] === false) {
            $blockers[] = sprintf('The destination section is full (%d of %d seats taken).', (int) $seatCheck['enrolled'], (int) ($seatCheck['capacity'] ?? 0));
        }

        if ($scheduleConflicts !== []) {
            $blockers[] = sprintf(
                'The destination section clashes with %d of the student\'s existing class(es): %s',
                count($scheduleConflicts),
                implode('; ', array_map(fn (array $clash): string => (string) $clash['overlap'], $scheduleConflicts)),
            );
        }

        if ($period['mismatch'] !== null) {
            $blockers[] = (string) $period['mismatch'];
        }

        return $blockers;
    }

    /**
     * @param  array<string, mixed>  $seatCheck
     * @param  array{matches: bool, message: string}  $subjectCheck
     * @param  array<int, array<string, mixed>>  $scheduleConflicts
     * @param  array<string, mixed>  $financial
     * @param  array<string, mixed>  $period
     * @return array<int, string>
     */
    private function warnings(array $seatCheck, array $subjectCheck, array $scheduleConflicts, array $financial, array $period): array
    {
        $warnings = [];

        if (($seatCheck['remaining_seats'] ?? null) !== null && (int) $seatCheck['remaining_seats'] <= 3 && $seatCheck['available'] === true) {
            $warnings[] = sprintf('Only %d seat(s) remain in the destination section.', (int) $seatCheck['remaining_seats']);
        }

        if (($financial['changed'] ?? true) === false && isset($financial['reason'])) {
            $warnings[] = (string) $financial['reason'];
        }

        if (($financial['before']['balance_due'] ?? 0) > 0) {
            $warnings[] = 'The student has an outstanding balance on this term. Changing the section does not settle it.';
        }

        if ($period['mismatch'] === null && ($period['warning'] ?? null) !== null) {
            $warnings[] = (string) $period['warning'];
        }

        return $warnings;
    }

    /**
     * A concrete next step the agent can quote back to the administrator.
     *
     * @param  array<int, string>  $blockers
     * @param  array<string, mixed>  $seatCheck
     * @param  array{matches: bool, message: string}  $subjectCheck
     * @param  array<int, array<string, mixed>>  $scheduleConflicts
     */
    private function recommendation(array $blockers, array $seatCheck, array $subjectCheck, array $scheduleConflicts, Classes $destination): string
    {
        if ($blockers === []) {
            return sprintf('Safe to transfer into %s (%s).', (string) $destination->section, (string) $seatCheck['message']);
        }

        if ($subjectCheck['matches'] === false) {
            return 'Do not transfer. Use EnrollStudentSubjectTool for the destination subject instead, then resolve the duplicate with DropStudentSubjectEnrollmentTool if the student should not keep the old one.';
        }

        if ($seatCheck['available'] === false) {
            return sprintf('Do not transfer yet: %s Raise maximum_slots on the section, or pick another section of the same subject.', (string) $seatCheck['message']);
        }

        if ($scheduleConflicts !== []) {
            $alternatives = $this->openSections($destination, $scheduleConflicts);

            if ($alternatives !== []) {
                return sprintf(
                    'Do not transfer yet: the destination clashes with the student\'s existing schedule. Sections of %s with no clash: %s.',
                    (string) $destination->subject_code,
                    implode(', ', $alternatives),
                );
            }

            return 'Do not transfer yet: the destination clashes with the student\'s existing schedule and no alternative section of this subject is free at that time. Ask the administrator to drop or reschedule the clashing class first.';
        }

        return 'Resolve the reported blockers, then retry.';
    }

    /**
     * Sections of the same subject that would not clash.
     *
     * @param  array<int, array<string, mixed>>  $scheduleConflicts
     * @return array<int, string>
     */
    private function openSections(Classes $destination, array $scheduleConflicts): array
    {
        $candidate = Classes::query()
            ->where('subject_code', $destination->subject_code)
            ->where('school_year', $destination->school_year)
            ->where('semester', $destination->semester)
            ->where('id', '!=', $destination->id)
            ->with('schedules')
            ->get();

        $others = collect($scheduleConflicts)
            ->map(fn (array $clash): ?int => $clash['existing_class']['class_id'] ?? null)
            ->filter()
            ->unique()
            ->map(fn (int $id) => Classes::query()->with('schedules')->find($id))
            ->filter();

        return $candidate
            ->filter(function (Classes $section) use ($others): bool {
                foreach ($others as $other) {
                    if ($this->pairwiseClashes($other, $section) !== []) {
                        return false;
                    }
                }

                return true;
            })
            ->map(fn (Classes $section): string => sprintf('%s (class_id=%d)', (string) $section->section, $section->id))
            ->take(5)
            ->values()
            ->all();
    }

    /**
     * @return array{mismatch: ?string, warning: ?string, school_year: ?string, semester: ?int}
     */
    private function periodFor(SubjectEnrollment $subjectEnrollment): array
    {
        $schoolYear = $subjectEnrollment->school_year === null ? null : (string) $subjectEnrollment->school_year;
        $semester = $subjectEnrollment->semester === null ? null : (int) $subjectEnrollment->semester;

        return [
            'mismatch' => null,
            'warning' => $schoolYear === null
                ? 'This subject enrollment has no academic period recorded, so period validation was skipped.'
                : null,
            'school_year' => $schoolYear,
            'semester' => $semester,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function classState(?Classes $class, ?string $section): array
    {
        if (! $class instanceof Classes) {
            return [
                'class_id' => null,
                'label' => 'No section assigned',
                'subject_code' => null,
                'section' => $section,
                'room' => null,
                'instructor' => null,
                'schedules' => [],
            ];
        }

        return [
            'class_id' => $class->id,
            'label' => sprintf('%s %s', (string) $class->subject_code, (string) $class->section),
            'subject_code' => (string) $class->subject_code,
            'section' => (string) $class->section,
            'room' => $class->room?->name,
            'instructor' => $class->faculty?->full_name,
            'schedules' => $class->schedules->map(fn (Schedule $schedule): array => [
                'day' => (string) $schedule->day_of_week,
                'time' => sprintf('%s - %s', $schedule->formatted_start_time, $schedule->formatted_end_time),
                'room' => $schedule->room?->name ?? $class->room?->name,
            ])->values()->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function studentSummary(Student $student): array
    {
        return [
            'id' => (int) $student->id,
            'student_number' => (string) $student->student_id,
            'name' => $student->full_name,
            'course' => $student->Course?->name,
        ];
    }

    /**
     * @param  Collection<int, SubjectEnrollment>  $records
     */
    private function enrolledSubjectCodes(Collection $records): string
    {
        return $records
            ->map(fn (SubjectEnrollment $record): string => sprintf(
                '%s (section %s)',
                (string) ($record->subject?->code ?? $record->external_subject_code ?? 'subject #'.$record->subject_id),
                (string) ($record->class?->section ?? $record->section ?? 'none'),
            ))
            ->implode(', ');
    }

    private function classLabelMatches(SubjectEnrollment $record, string $identifier): bool
    {
        $class = $record->class;

        if (! $class instanceof Classes) {
            return false;
        }

        $candidates = [
            (string) $class->id,
            sprintf('%s %s', (string) $class->subject_code, (string) $class->section),
            sprintf('%s %s', str_replace('-', '', (string) $class->subject_code), (string) $class->section),
        ];

        $normalized = mb_strtolower(mb_trim($identifier));

        foreach ($candidates as $candidate) {
            if (mb_strtolower($candidate) === $normalized) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<string, mixed>
     */
    private function failure(string $message, ?SubjectEnrollment $subjectEnrollment = null): array
    {
        return [
            'success' => false,
            'preview' => true,
            'can_transfer' => false,
            'blockers' => [$message],
            'warnings' => [],
            'recommendation' => 'Resolve the identifier, then retry the preview before transferring.',
            'error' => true,
            'message' => $message,
            'subject' => $subjectEnrollment instanceof SubjectEnrollment ? [
                'subject_enrollment_id' => $subjectEnrollment->id,
                'code' => $subjectEnrollment->subject?->code,
            ] : null,
        ];
    }
}
