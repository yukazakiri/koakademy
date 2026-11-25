<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ClassEnrollment;
use App\Models\Classes;
use App\Models\EnrollmentLoadChange;
use App\Models\StudentEnrollment;
use App\Models\StudentTuition;
use App\Models\Subject;
use App\Models\SubjectEnrollment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

final readonly class EnrollmentLoadChangeService
{
    public function __construct(
        private EnrollmentPeriodService $periodService,
        private StudentHoldService $holdService,
    ) {}

    /**
     * Preview units and tuition impact before creating or applying an Add / Drop / Swap change.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function preview(StudentEnrollment $enrollment, array $data): array
    {
        $enrollment->loadMissing(['course', 'studentTuition', 'additionalFees', 'subjectsEnrolled.subject']);

        $changeType = (string) $data['change_type'];
        $addedSubject = isset($data['added_subject_id']) ? Subject::query()->find((int) $data['added_subject_id']) : null;
        $droppedEnrollment = isset($data['dropped_subject_enrollment_id'])
            ? $enrollment->subjectsEnrolled->firstWhere('id', (int) $data['dropped_subject_enrollment_id'])
            : null;

        $lecRate = (float) ($enrollment->course?->lec_per_unit ?? 0);
        $labRate = (float) ($enrollment->course?->lab_per_unit ?? 0);
        $discountPct = (float) ($enrollment->studentTuition?->discount ?? 0);
        $discountFactor = max(0.0, 1.0 - ($discountPct / 100.0));

        $addedUnits = 0;
        $addedGross = 0.0;
        if (in_array($changeType, [EnrollmentLoadChange::TypeAdd, EnrollmentLoadChange::TypeSwap], true) && $addedSubject instanceof Subject) {
            $lecU = (int) ($addedSubject->lecture ?? 0);
            $labU = (int) ($addedSubject->laboratory ?? 0);
            $addedUnits = $lecU + $labU;
            $addedGross = round(($lecU * $lecRate) + ($labU * $labRate), 2);
        }

        $droppedUnits = 0;
        $droppedGross = 0.0;
        if (in_array($changeType, [EnrollmentLoadChange::TypeDrop, EnrollmentLoadChange::TypeSwap], true) && $droppedEnrollment instanceof SubjectEnrollment) {
            $droppedLecU = (int) ($droppedEnrollment->enrolled_lecture_units ?? $droppedEnrollment->subject?->lecture ?? 0);
            $droppedLabU = (int) ($droppedEnrollment->enrolled_laboratory_units ?? $droppedEnrollment->subject?->laboratory ?? 0);
            $droppedUnits = $droppedLecU + $droppedLabU;
            $droppedGross = $droppedEnrollment->exclude_from_tuition
                ? 0.0
                : round((float) ($droppedEnrollment->lecture_fee ?? ($droppedLecU * $lecRate)) + (float) ($droppedEnrollment->laboratory_fee ?? ($droppedLabU * $labRate)), 2);
        }

        $previousTotal = (float) ($enrollment->studentTuition?->overall_tuition ?? 0);
        $grossDelta = round($addedGross - $droppedGross, 2);
        $tuitionDelta = round($grossDelta * $discountFactor, 2);
        $newTotal = max(0.0, round($previousTotal + $tuitionDelta, 2));

        $periodStatus = $this->periodService->describe($enrollment->school_year, $enrollment->semester);
        $blockingHolds = $enrollment->student_id
            ? $this->holdService->blockingAddDropHoldsForStudent((int) $enrollment->student_id)
            : collect();

        return [
            'change_type' => $changeType,
            'within_window' => (bool) $periodStatus['add_drop_open'],
            'add_drop_deadline_at' => $periodStatus['add_drop_deadline_at'],
            'blocking_holds_count' => $blockingHolds->count(),
            'added_subject' => $addedSubject ? [
                'id' => $addedSubject->id,
                'code' => $addedSubject->code,
                'title' => $addedSubject->title,
                'units' => $addedUnits,
                'gross_fee' => $addedGross,
            ] : null,
            'dropped_subject' => $droppedEnrollment ? [
                'subject_enrollment_id' => $droppedEnrollment->id,
                'subject_id' => $droppedEnrollment->subject_id,
                'code' => $droppedEnrollment->subject?->code ?? $droppedEnrollment->external_subject_code,
                'title' => $droppedEnrollment->subject?->title ?? $droppedEnrollment->external_subject_title,
                'units' => $droppedUnits,
                'gross_fee' => $droppedGross,
            ] : null,
            'units_delta' => $addedUnits - $droppedUnits,
            'tuition_delta' => $tuitionDelta,
            'previous_tuition_total' => $previousTotal,
            'new_tuition_total' => $newTotal,
        ];
    }

    /**
     * Create a tracked Add / Drop / Swap request and optionally approve & apply it immediately.
     *
     * @param  array<string, mixed>  $data
     */
    public function submit(StudentEnrollment $enrollment, array $data, User $actor): EnrollmentLoadChange
    {
        $preview = $this->preview($enrollment, $data);
        $periodStatus = $this->periodService->describe($enrollment->school_year, $enrollment->semester);

        $overrideWindow = (bool) ($data['override_window'] ?? false);
        if (($periodStatus['enforce_windows'] ?? false) && ! $preview['within_window'] && ! $overrideWindow) {
            throw new RuntimeException('The Add/Drop/Swap deadline for this term has passed. Enable administrator window override to proceed.');
        }

        if ($preview['blocking_holds_count'] > 0 && ! (bool) ($data['override_holds'] ?? false)) {
            throw new RuntimeException('Student has an active hold blocking Add/Drop changes. Resolve or override the hold first.');
        }

        $changeType = (string) $data['change_type'];
        if (in_array($changeType, [EnrollmentLoadChange::TypeAdd, EnrollmentLoadChange::TypeSwap], true) && ! $preview['added_subject']) {
            throw new RuntimeException('Select a subject to add.');
        }
        if (in_array($changeType, [EnrollmentLoadChange::TypeDrop, EnrollmentLoadChange::TypeSwap], true) && ! $preview['dropped_subject']) {
            throw new RuntimeException('Select an enrolled subject to drop.');
        }

        $change = EnrollmentLoadChange::query()->create([
            'reference_number' => sprintf('COL-%s-%s', now()->format('Y'), mb_strtoupper(Str::random(6))),
            'student_enrollment_id' => $enrollment->id,
            'student_id' => $enrollment->student_id,
            'change_type' => $changeType,
            'status' => EnrollmentLoadChange::StatusPending,
            'added_subject_id' => $preview['added_subject']['id'] ?? null,
            'added_class_id' => isset($data['added_class_id']) ? (int) $data['added_class_id'] : null,
            'added_subject_code' => $preview['added_subject']['code'] ?? null,
            'added_subject_title' => $preview['added_subject']['title'] ?? null,
            'dropped_subject_enrollment_id' => $preview['dropped_subject']['subject_enrollment_id'] ?? null,
            'dropped_subject_id' => $preview['dropped_subject']['subject_id'] ?? null,
            'dropped_subject_code' => $preview['dropped_subject']['code'] ?? null,
            'dropped_subject_title' => $preview['dropped_subject']['title'] ?? null,
            'units_delta' => (int) $preview['units_delta'],
            'tuition_delta' => (float) $preview['tuition_delta'],
            'previous_tuition_total' => (float) $preview['previous_tuition_total'],
            'new_tuition_total' => (float) $preview['new_tuition_total'],
            'within_window' => (bool) $preview['within_window'],
            'reason' => mb_trim((string) $data['reason']),
            'requested_by' => $actor->id,
        ]);

        if ((bool) ($data['apply_immediately'] ?? true)) {
            return $this->approve($change, $actor, (bool) ($data['force_overload'] ?? false));
        }

        return $change->fresh(['requestedBy', 'approvedBy', 'rejectedBy', 'addedClass']);
    }

    /**
     * Approve and atomically apply a pending Add / Drop / Swap request and recalculate tuition.
     */
    public function approve(EnrollmentLoadChange $change, User $actor, bool $forceOverload = false): EnrollmentLoadChange
    {
        if ($change->status !== EnrollmentLoadChange::StatusPending) {
            throw new RuntimeException('Only pending load-change requests can be approved.');
        }

        return DB::transaction(function () use ($change, $actor, $forceOverload): EnrollmentLoadChange {
            $enrollment = StudentEnrollment::query()
                ->with(['course', 'studentTuition', 'additionalFees', 'subjectsEnrolled.subject'])
                ->findOrFail($change->student_enrollment_id);

            $previousTotal = (float) ($enrollment->studentTuition?->overall_tuition ?? 0);

            // 1. Handle drop (for drop or swap)
            if (in_array($change->change_type, [EnrollmentLoadChange::TypeDrop, EnrollmentLoadChange::TypeSwap], true)) {
                $subjectEnrollment = SubjectEnrollment::query()
                    ->where('enrollment_id', $enrollment->id)
                    ->whereKey($change->dropped_subject_enrollment_id)
                    ->first();

                if (! $subjectEnrollment instanceof SubjectEnrollment) {
                    throw new RuntimeException('The subject selected to drop is no longer on this enrollment.');
                }

                $oldClassId = $subjectEnrollment->class_id;
                $subjectEnrollment->delete();

                if ($oldClassId) {
                    ClassEnrollment::query()
                        ->where('student_id', $enrollment->student_id)
                        ->where('class_id', $oldClassId)
                        ->delete();
                }
            }

            // 2. Handle add (for add or swap)
            if (in_array($change->change_type, [EnrollmentLoadChange::TypeAdd, EnrollmentLoadChange::TypeSwap], true)) {
                $subject = Subject::query()->findOrFail($change->added_subject_id);

                $alreadyEnrolled = SubjectEnrollment::query()
                    ->where('enrollment_id', $enrollment->id)
                    ->where('subject_id', $subject->id)
                    ->exists();

                if ($alreadyEnrolled) {
                    throw new RuntimeException(sprintf('Student is already enrolled in %s.', $subject->code));
                }

                $classId = $change->added_class_id;
                if ($classId) {
                    $class = Classes::query()->findOrFail($classId);
                    $enrolledCount = ClassEnrollment::query()->where('class_id', $class->id)->count();
                    if (! $forceOverload && $class->maximum_slots > 0 && $enrolledCount >= $class->maximum_slots) {
                        throw new RuntimeException(sprintf('Section %s for %s is full.', $class->section, $subject->code));
                    }
                }

                $lecRate = (float) ($enrollment->course?->lec_per_unit ?? 0);
                $labRate = (float) ($enrollment->course?->lab_per_unit ?? 0);
                $lecUnits = (int) ($subject->lecture ?? 0);
                $labUnits = (int) ($subject->laboratory ?? 0);

                $enrollment->subjectsEnrolled()->create([
                    'subject_id' => $subject->id,
                    'class_id' => $classId,
                    'student_id' => $enrollment->student_id,
                    'academic_year' => $enrollment->academic_year,
                    'school_year' => $enrollment->school_year,
                    'semester' => $enrollment->semester,
                    'enrolled_lecture_units' => $lecUnits,
                    'enrolled_laboratory_units' => $labUnits,
                    'lecture_fee' => round($lecUnits * $lecRate, 2),
                    'laboratory_fee' => round($labUnits * $labRate, 2),
                    'is_modular' => false,
                    'exclude_from_tuition' => false,
                ]);

                if ($classId) {
                    $classEnrollment = ClassEnrollment::query()->withTrashed()->firstOrNew([
                        'student_id' => $enrollment->student_id,
                        'class_id' => $classId,
                    ]);
                    if ($classEnrollment->trashed()) {
                        $classEnrollment->restore();
                    }
                    $classEnrollment->status = true;
                    $classEnrollment->save();
                }
            }

            // 3. Recalculate StudentTuition preserving already-paid amount
            $newTotal = $this->recalculateEnrollmentTuition($enrollment);

            $change->forceFill([
                'status' => EnrollmentLoadChange::StatusApproved,
                'previous_tuition_total' => $previousTotal,
                'new_tuition_total' => $newTotal,
                'tuition_delta' => round($newTotal - $previousTotal, 2),
                'approved_by' => $actor->id,
                'approved_at' => now(),
            ])->save();

            return $change->fresh(['requestedBy', 'approvedBy', 'rejectedBy', 'addedClass']);
        });
    }

    public function reject(EnrollmentLoadChange $change, User $actor, string $reason): EnrollmentLoadChange
    {
        if ($change->status !== EnrollmentLoadChange::StatusPending) {
            throw new RuntimeException('Only pending load-change requests can be rejected.');
        }

        $change->forceFill([
            'status' => EnrollmentLoadChange::StatusRejected,
            'rejected_by' => $actor->id,
            'rejected_at' => now(),
            'rejection_reason' => mb_trim($reason),
        ])->save();

        return $change->fresh(['requestedBy', 'approvedBy', 'rejectedBy', 'addedClass']);
    }

    /**
     * Recompute tuition ledger totals from active SubjectEnrollments while preserving payments already settled.
     */
    public function recalculateEnrollmentTuition(StudentEnrollment $enrollment): float
    {
        $enrollment->load(['subjectsEnrolled', 'additionalFees', 'studentTuition']);
        $tuition = $enrollment->studentTuition;

        if (! $tuition instanceof StudentTuition) {
            return 0.0;
        }

        $previousOverall = (float) $tuition->overall_tuition;
        $previousBalance = (float) $tuition->total_balance;
        $alreadyPaid = max(0.0, round($previousOverall - $previousBalance, 2));

        $billableSubjects = $enrollment->subjectsEnrolled->filter(fn (SubjectEnrollment $se): bool => ! $se->exclude_from_tuition);
        $totalLectures = round((float) $billableSubjects->sum('lecture_fee'), 2);
        $totalLaboratory = round((float) $billableSubjects->sum('laboratory_fee'), 2);
        $grossTuition = round($totalLectures + $totalLaboratory, 2);

        $discountPct = (float) ($tuition->discount ?? 0);
        $discountedTuition = round($grossTuition * max(0.0, 1.0 - ($discountPct / 100.0)), 2);

        $miscFees = (float) ($tuition->total_miscelaneous_fees ?? 0);
        $additionalTotal = round((float) $enrollment->additionalFees->sum('amount'), 2);
        $newOverall = round($discountedTuition + $miscFees + $additionalTotal, 2);
        $newBalance = max(0.0, round($newOverall - $alreadyPaid, 2));

        $tuition->forceFill([
            'total_lectures' => $totalLectures,
            'total_laboratory' => $totalLaboratory,
            'total_tuition' => $discountedTuition,
            'overall_tuition' => $newOverall,
            'total_balance' => $newBalance,
            'status' => $newBalance <= 0 ? 'fully_paid' : ($alreadyPaid > 0 ? 'partially_paid' : 'unpaid'),
        ])->save();

        return $newOverall;
    }

    /**
     * @return array<string, mixed>
     */
    public function serialize(EnrollmentLoadChange $change): array
    {
        return [
            'id' => $change->id,
            'reference_number' => $change->reference_number,
            'change_type' => $change->change_type,
            'status' => $change->status,
            'added_subject_id' => $change->added_subject_id,
            'added_class_id' => $change->added_class_id,
            'added_class_section' => $change->relationLoaded('addedClass') ? $change->addedClass?->section : null,
            'added_subject_code' => $change->added_subject_code,
            'added_subject_title' => $change->added_subject_title,
            'dropped_subject_enrollment_id' => $change->dropped_subject_enrollment_id,
            'dropped_subject_code' => $change->dropped_subject_code,
            'dropped_subject_title' => $change->dropped_subject_title,
            'units_delta' => (int) $change->units_delta,
            'tuition_delta' => round((float) $change->tuition_delta, 2),
            'previous_tuition_total' => round((float) $change->previous_tuition_total, 2),
            'new_tuition_total' => round((float) $change->new_tuition_total, 2),
            'within_window' => (bool) $change->within_window,
            'reason' => $change->reason,
            'requested_by' => $change->requestedBy?->name ?? 'Administrator',
            'approved_by' => $change->approvedBy?->name,
            'approved_at' => $change->approved_at?->toIso8601String(),
            'rejected_by' => $change->rejectedBy?->name,
            'rejected_at' => $change->rejected_at?->toIso8601String(),
            'rejection_reason' => $change->rejection_reason,
            'created_at' => $change->created_at?->toIso8601String(),
            'slip_url' => route('administrators.enrollments.load-changes.slip', [
                'enrollment' => $change->student_enrollment_id,
                'loadChange' => $change->id,
            ]),
        ];
    }
}
