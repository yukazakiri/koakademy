<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ClassAttendanceRecord;
use App\Models\ClassEnrollment;
use App\Models\EnrollmentWorkflowEvent;
use App\Models\Student;
use App\Models\StudentClearance;
use App\Models\StudentEnrollment;
use App\Models\StudentIdChangeLog;
use App\Models\StudentTransaction;
use App\Models\StudentTuition;
use App\Models\SubjectEnrollment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

final class StudentDataTransferService
{
    public function __construct(
        private ?EnrollmentBillingService $billingService = null,
    ) {
        $this->billingService ??= app(EnrollmentBillingService::class);
    }

    /**
     * Preview what data will be moved from source student to target student.
     *
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    public function preview(Student $source, Student $target, array $options = []): array
    {
        $this->validateStudents($source, $target);

        $scopeEnrollmentId = isset($options['enrollment_id']) ? (int) $options['enrollment_id'] : null;

        $enrollmentsQuery = StudentEnrollment::withTrashed()
            ->where(function ($q) use ($source): void {
                $q->where('student_id', (string) $source->id)
                    ->orWhere('student_id', (string) $source->student_id);
            });

        if ($scopeEnrollmentId !== null) {
            $enrollmentsQuery->where('id', $scopeEnrollmentId);
        }

        $enrollments = $enrollmentsQuery->get();
        $enrollmentIds = $enrollments->pluck('id')->all();

        $subjectsQuery = SubjectEnrollment::query()->where('student_id', $source->id);
        if ($scopeEnrollmentId !== null) {
            $subjectsQuery->where('enrollment_id', $scopeEnrollmentId);
        }
        $subjects = $subjectsQuery->get();

        $classEnrollmentsQuery = ClassEnrollment::withTrashed()
            ->where(function ($q) use ($source): void {
                $q->where('student_id', $source->id)
                    ->orWhere('student_id', $source->student_id);
            });

        if ($scopeEnrollmentId !== null) {
            $classEnrollmentClassIds = SubjectEnrollment::query()
                ->whereIn('enrollment_id', $enrollmentIds)
                ->whereNotNull('class_id')
                ->pluck('class_id')
                ->all();

            if (! empty($classEnrollmentClassIds)) {
                $classEnrollmentsQuery->whereIn('class_id', $classEnrollmentClassIds);
            } else {
                $classEnrollmentsQuery->whereRaw('1 = 0');
            }
        }

        $classEnrollments = $classEnrollmentsQuery->get();
        $classEnrollmentIds = $classEnrollments->pluck('id')->all();

        $tuitionsQuery = StudentTuition::withTrashed()
            ->where(function ($q) use ($source): void {
                $q->where('student_id', $source->id)
                    ->orWhere('student_id', $source->student_id);
            });
        if ($scopeEnrollmentId !== null) {
            $tuitionsQuery->where('enrollment_id', $scopeEnrollmentId);
        }
        $tuitions = $tuitionsQuery->get();

        $transactionsQuery = StudentTransaction::query()->where('student_id', $source->id);
        if ($scopeEnrollmentId !== null) {
            $transactionsQuery->where('student_enrollment_id', $scopeEnrollmentId);
        }
        $transactions = $transactionsQuery->get();

        $clearances = $scopeEnrollmentId === null
            ? StudentClearance::query()->where('student_id', $source->id)->get()
            : collect();

        $attendancesQuery = ClassAttendanceRecord::query()->where('student_id', $source->id);
        if ($scopeEnrollmentId !== null) {
            if (! empty($classEnrollmentIds)) {
                $attendancesQuery->whereIn('class_enrollment_id', $classEnrollmentIds);
            } else {
                $attendancesQuery->whereRaw('1 = 0');
            }
        }
        $attendances = $attendancesQuery->get();

        $warnings = [];
        if ($target->trashed()) {
            $warnings[] = "Target student {$target->full_name} is currently soft-deleted/trashed.";
        }

        // Check for duplicate subject enrollments on target student
        $targetSubjectIds = SubjectEnrollment::query()
            ->where('student_id', $target->id)
            ->pluck('subject_id')
            ->all();

        $duplicateSubjects = $subjects->filter(fn ($s) => in_array($s->subject_id, $targetSubjectIds, true));
        if ($duplicateSubjects->isNotEmpty()) {
            $warnings[] = "Target student already has {$duplicateSubjects->count()} of the subjects being transferred (potential duplicates).";
        }

        return [
            'mode' => $scopeEnrollmentId !== null ? 'selective_enrollment' : 'all_records',
            'scoped_enrollment_id' => $scopeEnrollmentId,
            'source_student' => [
                'id' => $source->id,
                'student_id' => $source->student_id,
                'name' => $source->full_name,
                'email' => $source->email,
                'status' => $source->status,
            ],
            'target_student' => [
                'id' => $target->id,
                'student_id' => $target->student_id,
                'name' => $target->full_name,
                'email' => $target->email,
                'status' => $target->status,
            ],
            'records_to_transfer' => [
                'enrollments_count' => $enrollments->count(),
                'enrollment_ids' => $enrollmentIds,
                'subject_enrollments_count' => $subjects->count(),
                'class_enrollments_count' => $classEnrollments->count(),
                'tuition_ledgers_count' => $tuitions->count(),
                'transactions_count' => $transactions->count(),
                'clearances_count' => $clearances->count(),
                'attendance_records_count' => $attendances->count(),
            ],
            'warnings' => $warnings,
            'safe_to_proceed' => true,
        ];
    }

    /**
     * Transfer records from source student to target student.
     *
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    public function transfer(Student $source, Student $target, User $actor, array $options = []): array
    {
        $this->validateStudents($source, $target);

        $scopeEnrollmentId = isset($options['enrollment_id']) ? (int) $options['enrollment_id'] : null;
        $reason = (string) ($options['reason'] ?? 'Reassigned via administrative assistant.');

        return DB::transaction(function () use ($source, $target, $actor, $scopeEnrollmentId, $reason): array {
            $enrollmentsQuery = StudentEnrollment::withTrashed()
                ->where(function ($q) use ($source): void {
                    $q->where('student_id', (string) $source->id)
                        ->orWhere('student_id', (string) $source->student_id);
                });

            if ($scopeEnrollmentId !== null) {
                $enrollmentsQuery->where('id', $scopeEnrollmentId);
            }

            $enrollmentRecords = $enrollmentsQuery->get();
            $enrollmentIds = $enrollmentRecords->pluck('id')->all();

            $updatedEnrollments = 0;
            foreach ($enrollmentRecords as $enrollment) {
                $enrollment->update(['student_id' => (string) $target->id]);
                $updatedEnrollments++;

                EnrollmentWorkflowEvent::query()->create([
                    'student_enrollment_id' => $enrollment->id,
                    'actor_id' => $actor->id,
                    'event_type' => 'student_reassigned',
                    'reason' => "Reassigned from student #{$source->student_id} to #{$target->student_id}: {$reason}",
                    'from_step_key' => $enrollment->current_step_key,
                    'to_step_key' => $enrollment->current_step_key,
                    'status' => $enrollment->status,
                ]);
            }

            // Subject Enrollments
            $subjectsQuery = SubjectEnrollment::query()->where('student_id', $source->id);
            if ($scopeEnrollmentId !== null) {
                $subjectsQuery->where('enrollment_id', $scopeEnrollmentId);
            }
            $updatedSubjects = $subjectsQuery->update(['student_id' => $target->id]);

            // Class Enrollments
            $classEnrollmentsQuery = ClassEnrollment::withTrashed()
                ->where(function ($q) use ($source): void {
                    $q->where('student_id', $source->id)
                        ->orWhere('student_id', $source->student_id);
                });
            if ($scopeEnrollmentId !== null) {
                $classEnrollmentClassIds = SubjectEnrollment::query()
                    ->whereIn('enrollment_id', $enrollmentIds)
                    ->whereNotNull('class_id')
                    ->pluck('class_id')
                    ->all();
                if (! empty($classEnrollmentClassIds)) {
                    $classEnrollmentsQuery->whereIn('class_id', $classEnrollmentClassIds);
                } else {
                    $classEnrollmentsQuery->whereRaw('1 = 0');
                }
            }
            $transferredClassEnrollmentIds = (clone $classEnrollmentsQuery)->pluck('id')->all();
            $updatedClassEnrollments = $classEnrollmentsQuery->update(['student_id' => $target->id]);

            // Tuitions
            $tuitionsQuery = StudentTuition::withTrashed()
                ->where(function ($q) use ($source): void {
                    $q->where('student_id', $source->id)
                        ->orWhere('student_id', $source->student_id);
                });
            if ($scopeEnrollmentId !== null) {
                $tuitionsQuery->where('enrollment_id', $scopeEnrollmentId);
            }
            $tuitions = $tuitionsQuery->get();
            $updatedTuitions = 0;
            foreach ($tuitions as $tuition) {
                $tuition->update(['student_id' => $target->id]);
                $this->billingService->syncTuitionBalance($tuition);
                $updatedTuitions++;
            }

            // Transactions
            $transactionsQuery = StudentTransaction::query()->where('student_id', $source->id);
            if ($scopeEnrollmentId !== null) {
                $transactionsQuery->where('student_enrollment_id', $scopeEnrollmentId);
            }
            $updatedTransactions = $transactionsQuery->update(['student_id' => $target->id]);

            // Clearances
            $updatedClearances = 0;
            if ($scopeEnrollmentId === null) {
                $updatedClearances = StudentClearance::query()
                    ->where('student_id', $source->id)
                    ->update(['student_id' => $target->id]);
            }

            // Attendances
            $updatedAttendances = 0;
            if ($scopeEnrollmentId === null) {
                $updatedAttendances = ClassAttendanceRecord::query()
                    ->where('student_id', $source->id)
                    ->update(['student_id' => $target->id]);
            } elseif (! empty($transferredClassEnrollmentIds)) {
                $updatedAttendances = ClassAttendanceRecord::query()
                    ->whereIn('class_enrollment_id', $transferredClassEnrollmentIds)
                    ->update(['student_id' => $target->id]);
            }

            $affectedTotal = $updatedEnrollments
                + $updatedSubjects
                + $updatedClassEnrollments
                + $updatedTuitions
                + $updatedTransactions
                + $updatedClearances
                + $updatedAttendances;

            $changeLog = StudentIdChangeLog::query()->create([
                'old_student_id' => (string) $source->student_id,
                'new_student_id' => (string) $target->student_id,
                'student_name' => "{$source->full_name} -> {$target->full_name}",
                'changed_by' => $actor->email ?? 'System',
                'affected_records' => [
                    'enrollments' => $updatedEnrollments,
                    'subject_enrollments' => $updatedSubjects,
                    'class_enrollments' => $updatedClassEnrollments,
                    'tuition_ledgers' => $updatedTuitions,
                    'transactions' => $updatedTransactions,
                    'clearances' => $updatedClearances,
                    'attendances' => $updatedAttendances,
                    'total_updated' => $affectedTotal,
                ],
                'backup_data' => [
                    'source_id' => $source->id,
                    'source_student_id' => $source->student_id,
                    'target_id' => $target->id,
                    'target_student_id' => $target->student_id,
                    'scoped_enrollment_id' => $scopeEnrollmentId,
                    'timestamp' => now()->toIso8601String(),
                ],
                'reason' => "Record transfer: {$reason}",
            ]);

            Log::info('Student data reassignment completed', [
                'source_id' => $source->id,
                'target_id' => $target->id,
                'actor' => $actor->email,
                'affected_total' => $affectedTotal,
            ]);

            return [
                'success' => true,
                'message' => "Successfully transferred {$affectedTotal} records from {$source->full_name} (#{$source->student_id}) to {$target->full_name} (#{$target->student_id}).",
                'change_log_id' => $changeLog->id,
                'source_student' => [
                    'id' => $source->id,
                    'student_id' => $source->student_id,
                    'name' => $source->full_name,
                ],
                'target_student' => [
                    'id' => $target->id,
                    'student_id' => $target->student_id,
                    'name' => $target->full_name,
                ],
                'transferred' => [
                    'enrollments' => $updatedEnrollments,
                    'subject_enrollments' => $updatedSubjects,
                    'class_enrollments' => $updatedClassEnrollments,
                    'tuition_ledgers' => $updatedTuitions,
                    'transactions' => $updatedTransactions,
                    'clearances' => $updatedClearances,
                    'attendances' => $updatedAttendances,
                    'total' => $affectedTotal,
                ],
            ];
        });
    }

    private function validateStudents(Student $source, Student $target): void
    {
        if ((int) $source->id === (int) $target->id) {
            throw new InvalidArgumentException('Source student and target student cannot be the same record.');
        }

        $sourceSchoolId = $source->school_id ?: $source->institution_id;
        $targetSchoolId = $target->school_id ?: $target->institution_id;

        if ($sourceSchoolId && $targetSchoolId && (int) $sourceSchoolId !== (int) $targetSchoolId) {
            throw new InvalidArgumentException('Both students must belong to the same institution/school.');
        }
    }
}
