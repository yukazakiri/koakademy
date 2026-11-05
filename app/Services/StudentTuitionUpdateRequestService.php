<?php

declare(strict_types=1);

namespace App\Services;

use App\Finance\RecordFinancePayment;
use App\Models\AdminTransaction;
use App\Models\PaymentAllocation;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\StudentTransaction;
use App\Models\StudentTuition;
use App\Models\StudentTuitionUpdateRequest;
use App\Models\Transaction;
use App\Models\TuitionAdjustment;
use App\Models\User;
use App\Notifications\StudentTuitionUpdateRequestReviewedNotification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class StudentTuitionUpdateRequestService
{
    /** @param array{school_year: string, semester: int, concern_type: string, amount?: float|int|string|null, receipt_number?: string|null, details: string, receipts?: array<int, array<string, mixed>>|null, receipt_image?: mixed} $data */
    public function submit(User $user, array $data, array $files = []): StudentTuitionUpdateRequest
    {
        $student = $this->studentFor($user);
        if (! $student instanceof Student) {
            throw ValidationException::withMessages(['school_year' => 'Your student record could not be found. Please contact the registrar.']);
        }

        return DB::transaction(function () use ($user, $student, $data, $files): StudentTuitionUpdateRequest {
            [$enrollment, $tuition] = $this->periodRecords($student, $data['school_year'], (int) $data['semester']);
            if (! $enrollment instanceof StudentEnrollment && ! $tuition instanceof StudentTuition) {
                throw ValidationException::withMessages(['school_year' => 'No enrollment or tuition record exists for the selected academic period.']);
            }

            $isPayment = ($data['concern_type'] ?? '') === StudentTuitionUpdateRequest::ConcernMissingPayment;

            $receiptEntries = [];
            if ($isPayment && ! empty($data['receipts']) && is_array($data['receipts'])) {
                foreach ($data['receipts'] as $index => $item) {
                    if (! is_array($item)) {
                        continue;
                    }
                    $rNum = filled($item['receipt_number'] ?? null) ? mb_trim((string) $item['receipt_number']) : null;
                    $rAmt = filled($item['amount'] ?? null) ? round((float) $item['amount'], 2) : null;
                    $rImg = $item['image'] ?? $files['receipts'][$index]['image'] ?? null;
                    if ($rNum !== null || $rAmt !== null || $rImg instanceof UploadedFile) {
                        $receiptEntries[] = [
                            'receipt_number' => $rNum ?? 'OR-'.($index + 1),
                            'amount' => $rAmt,
                            'image' => $rImg instanceof UploadedFile ? $rImg : null,
                        ];
                    }
                }
            }

            $singleImg = $data['receipt_image'] ?? $files['receipt_image'] ?? null;
            if ($isPayment && empty($receiptEntries) && (filled($data['receipt_number'] ?? null) || $singleImg instanceof UploadedFile)) {
                $receiptEntries[] = [
                    'receipt_number' => filled($data['receipt_number'] ?? null) ? mb_trim((string) $data['receipt_number']) : 'OR-1',
                    'amount' => filled($data['amount'] ?? null) ? round((float) $data['amount'], 2) : null,
                    'image' => $singleImg instanceof UploadedFile ? $singleImg : null,
                ];
            }

            $amount = filled($data['amount'] ?? null) ? round((float) $data['amount'], 2) : null;
            if ($amount === null && ! empty($receiptEntries)) {
                $amounts = array_filter(array_column($receiptEntries, 'amount'));
                if (! empty($amounts)) {
                    $amount = round(array_sum($amounts), 2);
                }
            }

            $primaryReceiptNumber = null;
            if ($isPayment) {
                if (filled($data['receipt_number'] ?? null)) {
                    $primaryReceiptNumber = mb_trim((string) $data['receipt_number']);
                } elseif (! empty($receiptEntries)) {
                    $primaryReceiptNumber = implode(', ', array_unique(array_column($receiptEntries, 'receipt_number')));
                }
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
                    'amount' => $amount,
                    'receipt_number' => $primaryReceiptNumber,
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

            foreach ($receiptEntries as $entry) {
                $storedPath = null;
                $fileName = null;
                $mimeType = null;
                $fileSize = null;

                if ($entry['image'] instanceof UploadedFile && $entry['image']->isValid()) {
                    $storedPath = $entry['image']->storePublicly('tuition-request-receipts', 'public');
                    $fileName = $entry['image']->getClientOriginalName();
                    $mimeType = $entry['image']->getClientMimeType();
                    $fileSize = $entry['image']->getSize();
                }

                $request->receipts()->create([
                    'receipt_number' => $entry['receipt_number'],
                    'amount' => $entry['amount'],
                    'image_path' => $storedPath,
                    'file_name' => $fileName,
                    'mime_type' => $mimeType,
                    'file_size' => $fileSize,
                ]);
            }

            $this->event($request, $user, 'submitted', null, StudentTuitionUpdateRequest::StatusPending, null, [
                'amount' => $amount,
                'receipts_count' => count($receiptEntries),
            ]);

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

    public function resolveWithPayment(StudentTuitionUpdateRequest $request, Transaction $transaction, User $actor, string $note, bool $allowMismatch = false): StudentTuitionUpdateRequest
    {
        return $this->complete($request, $actor, $note, function (StudentTuitionUpdateRequest $locked) use ($transaction, $allowMismatch): array {
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
            if (! $allowMismatch && $locked->receipt_number !== null && mb_strtolower(mb_trim((string) $payment->invoicenumber)) !== mb_strtolower(mb_trim($locked->receipt_number))) {
                throw ValidationException::withMessages(['transaction_id' => 'The selected transaction does not match the submitted official receipt number.']);
            }

            if ($payment->invoicenumber && $locked->receipt_number !== $payment->invoicenumber) {
                $locked->receipt_number = $payment->invoicenumber;
            }

            return ['resolved_transaction_id' => $payment->id, 'tuition_adjustment_id' => null, 'event' => 'resolved_with_payment'];
        });
    }

    /**
     * Record a new verified payment transaction for the student and resolve this request in one action.
     *
     * @param  array{amount: float|int|string, payment_method: string, reference_number: string}  $paymentData
     */
    public function recordAndResolvePayment(
        StudentTuitionUpdateRequest $request,
        User $actor,
        array $paymentData,
        string $note,
        RecordFinancePayment $payments,
    ): StudentTuitionUpdateRequest {
        return DB::transaction(function () use ($request, $actor, $paymentData, $note, $payments): StudentTuitionUpdateRequest {
            $locked = $this->reviewable($request, $actor);
            if ($locked->concern_type !== StudentTuitionUpdateRequest::ConcernMissingPayment) {
                throw ValidationException::withMessages(['amount' => 'Only missing-payment requests can be resolved with a new payment recording.']);
            }

            $tuition = $locked->tuition;
            if (! $tuition instanceof StudentTuition && $locked->student instanceof Student) {
                [, $periodTuition] = $this->periodRecords($locked->student, $locked->school_year, (int) $locked->semester);
                $tuition = $periodTuition;
            }

            if (! $tuition instanceof StudentTuition) {
                throw ValidationException::withMessages(['amount' => 'No active tuition assessment found for this student and academic period.']);
            }

            $amount = round((float) ($paymentData['amount'] ?? 0), 2);
            if ($amount <= 0) {
                throw ValidationException::withMessages(['amount' => 'Payment amount must be greater than zero.']);
            }

            $referenceNumber = mb_trim((string) ($paymentData['reference_number'] ?? $locked->receipt_number ?? ''));
            $payload = [
                'student_id' => $locked->student_id,
                'payment_method' => $paymentData['payment_method'],
                'reference_number' => $referenceNumber ?: null,
                'remarks' => Str::limit("Tuition request #{$locked->id}: {$note}", 2000),
                'items' => [
                    [
                        'type' => 'tuition',
                        'tuition_id' => $tuition->id,
                        'amount' => $amount,
                    ],
                ],
            ];

            $recorded = $payments->record($actor, $payload);
            $transaction = $recorded->transaction;

            $from = $locked->status;
            $locked->forceFill([
                'status' => StudentTuitionUpdateRequest::StatusResolved,
                'resolution_note' => mb_trim($note),
                'resolved_transaction_id' => $transaction->id,
                'tuition_adjustment_id' => null,
                'receipt_number' => $referenceNumber ?: $locked->receipt_number,
                'resolved_at' => now(),
                'open_key' => null,
            ])->save();

            $this->event(
                $locked,
                $actor,
                'resolved_with_recorded_payment',
                $from,
                StudentTuitionUpdateRequest::StatusResolved,
                $locked->resolution_note,
                [
                    'transaction_id' => $transaction->id,
                    'transaction_number' => $transaction->transaction_number,
                    'amount' => $amount,
                    'invoicenumber' => $transaction->invoicenumber,
                ],
            );
            $this->notifyAfterCommit($locked);

            return $locked->refresh();
        });
    }

    /**
     * Adjust existing cashier payment transaction(s) without closing the tuition update request.
     * Status remains in_review so the reviewer can make additional adjustments.
     *
     * @param  array{transaction_id?: int|string|null, amount?: float|int|string|null, reference_number?: string|null, adjustments?: array<int, array{transaction_id: int|string, amount: float|int|string, reference_number?: string|null}>|null}  $adjustmentData
     */
    public function adjustPayment(
        StudentTuitionUpdateRequest $request,
        User $actor,
        array $adjustmentData,
        ?string $note,
        EnrollmentBillingService $billing,
    ): StudentTuitionUpdateRequest {
        return DB::transaction(function () use ($request, $actor, $adjustmentData, $note, $billing): StudentTuitionUpdateRequest {
            $locked = $this->reviewable($request, $actor);
            if ($locked->concern_type !== StudentTuitionUpdateRequest::ConcernMissingPayment) {
                throw ValidationException::withMessages(['transaction_id' => 'Only missing-payment requests can be modified with transaction adjustment.']);
            }

            $adjustments = [];
            if (! empty($adjustmentData['adjustments']) && is_array($adjustmentData['adjustments'])) {
                $adjustments = $adjustmentData['adjustments'];
            } elseif (! empty($adjustmentData['transaction_id'])) {
                $adjustments[] = [
                    'transaction_id' => (int) $adjustmentData['transaction_id'],
                    'amount' => $adjustmentData['amount'] ?? 0,
                    'reference_number' => $adjustmentData['reference_number'] ?? null,
                ];
            }

            if (empty($adjustments)) {
                throw ValidationException::withMessages(['transaction_id' => 'Please select at least one transaction to adjust.']);
            }

            $tuition = $locked->tuition;
            if (! $tuition instanceof StudentTuition && $locked->student instanceof Student) {
                [, $periodTuition] = $this->periodRecords($locked->student, $locked->school_year, (int) $locked->semester);
                $tuition = $periodTuition;
            }

            if (! $tuition instanceof StudentTuition) {
                throw ValidationException::withMessages(['amount' => 'No active tuition assessment found for this student and academic period.']);
            }
            $tuition = StudentTuition::query()->whereKey($tuition->id)->lockForUpdate()->firstOrFail();

            $totalNetVariance = 0.0;
            $adjustedTransactionsMeta = [];
            $primaryTxId = null;

            foreach ($adjustments as $idx => $adj) {
                $txId = (int) ($adj['transaction_id'] ?? 0);
                $payment = Transaction::query()->whereKey($txId)->lockForUpdate()->first();
                if (! $payment instanceof Transaction) {
                    throw ValidationException::withMessages(["adjustments.{$idx}.transaction_id" => 'Transaction not found.']);
                }

                $studentTx = StudentTransaction::query()
                    ->where('transaction_id', $payment->id)
                    ->where('student_id', $locked->student_id)
                    ->lockForUpdate()
                    ->first();
                if (! $studentTx instanceof StudentTransaction) {
                    throw ValidationException::withMessages(["adjustments.{$idx}.transaction_id" => 'Transaction does not belong to this student.']);
                }

                $newAmount = round((float) ($adj['amount'] ?? 0), 2);
                if ($newAmount <= 0) {
                    throw ValidationException::withMessages(["adjustments.{$idx}.amount" => 'Adjusted amount must be greater than zero.']);
                }

                $oldAmount = (float) $payment->raw_total_amount;
                $variance = round($newAmount - $oldAmount, 2);
                $totalNetVariance += $variance;

                // Update settlements
                $settlements = $payment->settlements;
                if (is_string($settlements)) {
                    $settlements = json_decode($settlements, true);
                }
                if (! is_array($settlements)) {
                    $settlements = [];
                }
                $settlements['tuition_fee'] = $newAmount;
                $payment->settlements = $settlements;

                // Update invoice number if supplied
                if (filled($adj['reference_number'] ?? null)) {
                    $payment->invoicenumber = mb_trim((string) $adj['reference_number']);
                }
                $payment->save();

                // Update StudentTransaction
                $studentTx->amount = $newAmount;
                $studentTx->save();

                // Update PaymentAllocation
                PaymentAllocation::query()
                    ->where('transaction_id', $payment->id)
                    ->where('student_id', $locked->student_id)
                    ->update(['amount' => $newAmount]);

                // Update AdminTransaction
                AdminTransaction::query()
                    ->where('transaction_id', $payment->id)
                    ->update(['amount' => $newAmount]);

                if ($primaryTxId === null) {
                    $primaryTxId = $payment->id;
                }

                $adjustedTransactionsMeta[] = [
                    'transaction_id' => $payment->id,
                    'transaction_number' => $payment->transaction_number,
                    'invoicenumber' => $payment->invoicenumber,
                    'old_amount' => $oldAmount,
                    'new_amount' => $newAmount,
                    'variance' => $variance,
                ];
            }

            // Sync tuition balance and paid amounts
            $oldPaid = (float) $tuition->paid;
            $oldBalance = (float) $tuition->total_balance;

            if ($tuition->paid_transaction_baseline === null) {
                $tuition->paid = max(0, round($oldPaid + $totalNetVariance, 2));
                $tuition->save();
            }
            $billing->syncTuitionBalance($tuition);

            if ($primaryTxId !== null) {
                $locked->forceFill([
                    'resolved_transaction_id' => $primaryTxId,
                ])->save();
            }

            $eventNote = filled($note) ? mb_trim((string) $note) : 'Adjusted transaction amount and reconciled tuition balance.';
            $this->event(
                $locked,
                $actor,
                'transaction_adjusted',
                StudentTuitionUpdateRequest::StatusInReview,
                StudentTuitionUpdateRequest::StatusInReview,
                $eventNote,
                [
                    'adjusted_transactions' => $adjustedTransactionsMeta,
                    'net_variance' => $totalNetVariance,
                    'balance_before' => $oldBalance,
                    'balance_after' => (float) $tuition->total_balance,
                    'paid_after' => (float) $tuition->paid,
                ],
            );

            return $locked->refresh();
        });
    }

    /**
     * Adjust existing cashier payment transaction(s) and resolve the tuition update request in one action.
     * Supports single transaction or multiple transactions (e.g. for dual receipts).
     *
     * @param  array{transaction_id?: int|string|null, amount?: float|int|string|null, reference_number?: string|null, adjustments?: array<int, array{transaction_id: int|string, amount: float|int|string, reference_number?: string|null}>|null}  $adjustmentData
     */
    public function adjustAndResolvePayment(
        StudentTuitionUpdateRequest $request,
        User $actor,
        array $adjustmentData,
        string $note,
        EnrollmentBillingService $billing,
    ): StudentTuitionUpdateRequest {
        return DB::transaction(function () use ($request, $actor, $adjustmentData, $note, $billing): StudentTuitionUpdateRequest {
            $adjusted = $this->adjustPayment($request, $actor, $adjustmentData, $note, $billing);

            $locked = $this->reviewable($adjusted, $actor);
            $from = $locked->status;
            $locked->forceFill([
                'status' => StudentTuitionUpdateRequest::StatusResolved,
                'resolution_note' => mb_trim($note),
                'resolved_transaction_id' => $adjusted->resolved_transaction_id,
                'tuition_adjustment_id' => null,
                'resolved_at' => now(),
                'open_key' => null,
            ])->save();

            $this->event(
                $locked,
                $actor,
                'resolved_with_adjusted_transaction',
                $from,
                StudentTuitionUpdateRequest::StatusResolved,
                $locked->resolution_note,
                [
                    'resolved_transaction_id' => $locked->resolved_transaction_id,
                ],
            );
            $this->notifyAfterCommit($locked);

            return $locked->refresh();
        });
    }

    /**
     * Resolve a claimed request after all reviews and adjustments are complete.
     */
    public function resolve(
        StudentTuitionUpdateRequest $request,
        User $actor,
        string $note,
        ?int $transactionId = null,
        ?int $tuitionAdjustmentId = null,
    ): StudentTuitionUpdateRequest {
        return DB::transaction(function () use ($request, $actor, $note, $transactionId, $tuitionAdjustmentId): StudentTuitionUpdateRequest {
            $locked = $this->reviewable($request, $actor);
            $from = $locked->status;
            $locked->forceFill([
                'status' => StudentTuitionUpdateRequest::StatusResolved,
                'resolution_note' => mb_trim($note),
                'resolved_transaction_id' => $transactionId ?? $locked->resolved_transaction_id,
                'tuition_adjustment_id' => $tuitionAdjustmentId ?? $locked->tuition_adjustment_id,
                'resolved_at' => now(),
                'open_key' => null,
            ])->save();

            $this->event(
                $locked,
                $actor,
                'resolved',
                $from,
                StudentTuitionUpdateRequest::StatusResolved,
                $locked->resolution_note,
                [
                    'resolved_transaction_id' => $locked->resolved_transaction_id,
                    'tuition_adjustment_id' => $locked->tuition_adjustment_id,
                ],
            );
            $this->notifyAfterCommit($locked);

            return $locked->refresh();
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
            ->where(function (Builder $query) use ($user): void {
                $query->where('user_id', $user->id)
                    ->orWhere('email', $user->email);
            })
            ->first();
    }

    public function sendReviewedNotification(StudentTuitionUpdateRequest $request): void
    {
        $request->loadMissing(['submitter', 'student']);
        $recipient = $request->submitter;
        if (! $recipient instanceof User && $request->student?->user_id) {
            $recipient = User::query()->find($request->student->user_id);
        }

        if ($recipient instanceof User) {
            $recipient->notify(new StudentTuitionUpdateRequestReviewedNotification($request));
        } elseif (filled($request->student?->email)) {
            Notification::route('mail', $request->student->email)
                ->notify(new StudentTuitionUpdateRequestReviewedNotification($request));
        }
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
            $this->sendReviewedNotification($request);
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
