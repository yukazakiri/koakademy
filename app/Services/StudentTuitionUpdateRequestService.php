<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\StudentTransaction;
use App\Models\StudentTuition;
use App\Models\StudentTuitionUpdateRequest;
use App\Models\Transaction;
use App\Models\TuitionAdjustment;
use App\Models\User;
use App\Notifications\StudentTuitionUpdateRequestReviewedNotification;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class StudentTuitionUpdateRequestService
{
    /** @param array{school_year: string, semester: int, concern_type: string, receipt_number?: string|null, details: string} $data */
    public function submit(User $user, array $data): StudentTuitionUpdateRequest
    {
        $student = $this->studentFor($user);
        if (! $student instanceof Student) {
            throw ValidationException::withMessages(['school_year' => 'Your student record could not be found. Please contact the registrar.']);
        }

        return DB::transaction(function () use ($user, $student, $data): StudentTuitionUpdateRequest {
            [$enrollment, $tuition] = $this->periodRecords($student, $data['school_year'], (int) $data['semester']);
            if (! $enrollment instanceof StudentEnrollment && ! $tuition instanceof StudentTuition) {
                throw ValidationException::withMessages(['school_year' => 'No enrollment or tuition record exists for the selected academic period.']);
            }

            $openKey = $this->openKey($student->id, $data['school_year'], (int) $data['semester'], $data['concern_type']);
            try {
                $request = StudentTuitionUpdateRequest::query()->create([
                    'submitted_by_user_id' => $user->id,
                    'student_id' => $student->id,
                    'student_enrollment_id' => $enrollment?->id,
                    'student_tuition_id' => $tuition?->id,
                    'school_year' => $data['school_year'],
                    'semester' => $data['semester'],
                    'concern_type' => $data['concern_type'],
                    'receipt_number' => filled($data['receipt_number'] ?? null) ? mb_trim((string) $data['receipt_number']) : null,
                    'details' => mb_trim($data['details']),
                    'status' => StudentTuitionUpdateRequest::StatusPending,
                    'open_key' => $openKey,
                ]);
            } catch (QueryException $exception) {
                if ($this->isUniqueViolation($exception)) {
                    throw ValidationException::withMessages(['concern_type' => 'You already have an active request for this concern and academic period.']);
                }

                throw $exception;
            }

            $this->event($request, $user, 'submitted', null, StudentTuitionUpdateRequest::StatusPending, null);

            return $request;
        });
    }

    public function claim(StudentTuitionUpdateRequest $request, User $actor): StudentTuitionUpdateRequest
    {
        return DB::transaction(function () use ($request, $actor): StudentTuitionUpdateRequest {
            $locked = $this->lock($request);
            if ($locked->status !== StudentTuitionUpdateRequest::StatusPending) {
                throw ValidationException::withMessages(['status' => 'Only pending requests can be claimed.']);
            }

            $locked->forceFill([
                'status' => StudentTuitionUpdateRequest::StatusInReview,
                'reviewed_by_user_id' => $actor->id,
                'reviewed_at' => now(),
            ])->save();
            $this->event($locked, $actor, 'claimed', StudentTuitionUpdateRequest::StatusPending, StudentTuitionUpdateRequest::StatusInReview, null);

            return $locked->refresh();
        });
    }

    public function resolveWithPayment(StudentTuitionUpdateRequest $request, Transaction $transaction, User $actor, string $note): StudentTuitionUpdateRequest
    {
        return $this->complete($request, $actor, $note, function (StudentTuitionUpdateRequest $locked) use ($transaction): array {
            if ($locked->concern_type !== StudentTuitionUpdateRequest::ConcernMissingPayment) {
                throw ValidationException::withMessages(['transaction_id' => 'Only missing-payment requests may be resolved with a payment transaction.']);
            }

            $payment = Transaction::query()->whereKey($transaction->id)->lockForUpdate()->firstOrFail();
            $matches = StudentTransaction::query()
                ->where('transaction_id', $payment->id)
                ->where('student_id', $locked->student_id)
                ->when(
                    $locked->student_enrollment_id !== null,
                    fn ($query) => $query->where('student_enrollment_id', $locked->student_enrollment_id),
                )
                ->whereIn('status', ['Paid', 'Completed', 'paid', 'completed'])
                ->exists();
            if (! $matches) {
                throw ValidationException::withMessages(['transaction_id' => 'The selected transaction is not a verified payment for this student and academic period.']);
            }
            if ($locked->receipt_number !== null && mb_strtolower(mb_trim((string) $payment->invoicenumber)) !== mb_strtolower(mb_trim($locked->receipt_number))) {
                throw ValidationException::withMessages(['transaction_id' => 'The selected transaction does not match the submitted official receipt number.']);
            }

            return ['resolved_transaction_id' => $payment->id, 'tuition_adjustment_id' => null, 'event' => 'resolved_with_payment'];
        });
    }

    public function resolveWithAdjustment(StudentTuitionUpdateRequest $request, TuitionAdjustment $adjustment, User $actor, string $note): StudentTuitionUpdateRequest
    {
        return $this->complete($request, $actor, $note, function (StudentTuitionUpdateRequest $locked) use ($adjustment): array {
            if ($locked->concern_type === StudentTuitionUpdateRequest::ConcernMissingPayment) {
                throw ValidationException::withMessages(['tuition_adjustment_id' => 'Missing-payment requests must be resolved with a verified payment transaction.']);
            }

            $record = TuitionAdjustment::query()->whereKey($adjustment->id)->lockForUpdate()->firstOrFail();
            if (($locked->student_enrollment_id !== null && $record->student_enrollment_id !== $locked->student_enrollment_id)
                || ($locked->student_tuition_id !== null && $record->student_tuition_id !== $locked->student_tuition_id)) {
                throw ValidationException::withMessages(['tuition_adjustment_id' => 'The selected adjustment does not belong to this tuition request.']);
            }

            return ['resolved_transaction_id' => null, 'tuition_adjustment_id' => $record->id, 'event' => 'resolved_with_adjustment'];
        });
    }

    public function reject(StudentTuitionUpdateRequest $request, User $actor, string $note): StudentTuitionUpdateRequest
    {
        return DB::transaction(function () use ($request, $actor, $note): StudentTuitionUpdateRequest {
            $locked = $this->reviewable($request, $actor);
            $from = $locked->status;
            $locked->forceFill([
                'status' => StudentTuitionUpdateRequest::StatusRejected,
                'resolution_note' => mb_trim($note),
                'resolved_at' => now(),
                'open_key' => null,
            ])->save();
            $this->event($locked, $actor, 'rejected', $from, StudentTuitionUpdateRequest::StatusRejected, $locked->resolution_note);
            $this->notifyAfterCommit($locked);

            return $locked->refresh();
        });
    }

    public function studentFor(User $user): ?Student
    {
        return Student::query()
            ->where('email', $user->email)
            ->orWhere('user_id', $user->id)
            ->first();
    }

    /** @return array{0: StudentEnrollment|null, 1: StudentTuition|null} */
    private function periodRecords(Student $student, string $schoolYear, int $semester): array
    {
        $enrollment = StudentEnrollment::query()
            ->where('student_id', $student->id)
            ->where('school_year', $schoolYear)
            ->where('semester', $semester)
            ->latest('id')
            ->first();
        $tuition = StudentTuition::query()
            ->where('student_id', $student->id)
            ->where('school_year', $schoolYear)
            ->where('semester', $semester)
            ->latest('id')
            ->first();

        if (! $tuition instanceof StudentTuition && $enrollment instanceof StudentEnrollment) {
            $tuition = StudentTuition::query()->where('enrollment_id', $enrollment->id)->first();
        }

        return [$enrollment, $tuition];
    }

    /** @param callable(StudentTuitionUpdateRequest): array{resolved_transaction_id: int|null, tuition_adjustment_id: int|null, event: string} $link */
    private function complete(StudentTuitionUpdateRequest $request, User $actor, string $note, callable $link): StudentTuitionUpdateRequest
    {
        return DB::transaction(function () use ($request, $actor, $note, $link): StudentTuitionUpdateRequest {
            $locked = $this->reviewable($request, $actor);
            $linked = $link($locked);
            $from = $locked->status;
            $locked->forceFill([
                'status' => StudentTuitionUpdateRequest::StatusResolved,
                'resolution_note' => mb_trim($note),
                'resolved_transaction_id' => $linked['resolved_transaction_id'],
                'tuition_adjustment_id' => $linked['tuition_adjustment_id'],
                'resolved_at' => now(),
                'open_key' => null,
            ])->save();
            $this->event($locked, $actor, $linked['event'], $from, StudentTuitionUpdateRequest::StatusResolved, $locked->resolution_note, $linked);
            $this->notifyAfterCommit($locked);

            return $locked->refresh();
        });
    }

    private function reviewable(StudentTuitionUpdateRequest $request, User $actor): StudentTuitionUpdateRequest
    {
        $locked = $this->lock($request);
        if ($locked->status !== StudentTuitionUpdateRequest::StatusInReview || $locked->reviewed_by_user_id !== $actor->id) {
            throw ValidationException::withMessages(['status' => 'Claim this request before recording its outcome.']);
        }

        return $locked;
    }

    private function lock(StudentTuitionUpdateRequest $request): StudentTuitionUpdateRequest
    {
        return StudentTuitionUpdateRequest::query()->lockForUpdate()->findOrFail($request->id);
    }

    private function event(StudentTuitionUpdateRequest $request, ?User $actor, string $event, ?string $fromStatus, ?string $toStatus, ?string $note, array $metadata = []): void
    {
        $request->events()->create([
            'actor_user_id' => $actor?->id,
            'event' => $event,
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'note' => $note,
            'metadata' => $metadata === [] ? null : $metadata,
        ]);
    }

    private function notifyAfterCommit(StudentTuitionUpdateRequest $request): void
    {
        DB::afterCommit(function () use ($request): void {
            $request->loadMissing('submitter');
            if ($request->submitter instanceof User) {
                $request->submitter->notify(new StudentTuitionUpdateRequestReviewedNotification($request));
            }
        });
    }

    private function openKey(int $studentId, string $schoolYear, int $semester, string $concernType): string
    {
        return hash('sha256', implode('|', [$studentId, $schoolYear, $semester, $concernType]));
    }

    private function isUniqueViolation(QueryException $exception): bool
    {
        return in_array((string) $exception->getCode(), ['23000', '23505'], true);
    }
}
