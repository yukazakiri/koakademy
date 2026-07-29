<?php

declare(strict_types=1);

namespace App\Enrollment;

use App\Data\Enrollment\ActionResult;
use App\Data\Enrollment\EnrollmentContext;
use App\Enrollment\Exceptions\EnrollmentTransitionException;
use App\Models\Account;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\StudentStatusRecord;

final readonly class EnrollmentStudentSynchronizationService
{
    /** @param array<string, mixed> $configuration */
    public function synchronize(EnrollmentContext $context, array $configuration): ActionResult
    {
        $enrollment = $context->enrollment;
        $attribute = (string) ($configuration['attribute'] ?? 'student_status');
        $value = $configuration['value'] ?? null;

        if (! $enrollment instanceof StudentEnrollment || ! in_array($attribute, ['status', 'student_status'], true)) {
            return ActionResult::failure('Student synchronization attribute is not allowed.');
        }
        if ($value === null) {
            return ActionResult::failure('Student synchronization requires an attribute and value.');
        }

        $student = Student::query()->lockForUpdate()->find($enrollment->student_id);
        if (! $student instanceof Student) {
            return ActionResult::failure('Student synchronization requires an attribute and value.');
        }

        $column = $attribute === 'student_status' ? 'status' : $attribute;
        $previousStudentValue = $student->getRawOriginal($column);
        $student->forceFill([$column => $value])->save();

        $statusRecordQuery = StudentStatusRecord::query()
            ->where('student_id', $enrollment->student_id)
            ->where('academic_year', $enrollment->school_year)
            ->where('semester', $enrollment->semester);
        $statusRecord = (clone $statusRecordQuery)->lockForUpdate()->first();
        $statusRecordExisted = $statusRecord instanceof StudentStatusRecord;
        $previousStatus = $statusRecord?->getRawOriginal('status');
        if ($statusRecord instanceof StudentStatusRecord) {
            $statusRecord->forceFill(['status' => $value])->save();
        } else {
            $statusRecord = StudentStatusRecord::query()->create([
                'student_id' => $enrollment->student_id,
                'school_id' => $enrollment->school_id,
                'academic_year' => $enrollment->school_year,
                'semester' => $enrollment->semester,
                'status' => $value,
            ]);
        }

        $accountMetadata = null;
        if (($configuration['sync_account'] ?? false) === true && $student->email) {
            $account = Account::query()->where('email', $student->email)->lockForUpdate()->first();
            if ($account instanceof Account) {
                $accountMetadata = [
                    'id' => $account->id,
                    'previous_role' => $account->getRawOriginal('role'),
                    'previous_person_id' => $account->getRawOriginal('person_id'),
                    'previous_person_type' => $account->getRawOriginal('person_type'),
                    'applied_role' => 'student',
                    'applied_person_id' => $student->id,
                    'applied_person_type' => Student::class,
                ];
                $account->forceFill([
                    'role' => 'student',
                    'person_id' => $student->id,
                    'person_type' => Student::class,
                ])->save();
            }
        }

        return ActionResult::success([
            'attribute' => $column,
            'value' => $value,
            'account_id' => $accountMetadata['id'] ?? null,
            'reversal' => [
                'student' => [
                    'id' => $student->id,
                    'attribute' => $column,
                    'previous_value' => $previousStudentValue,
                    'applied_value' => $value,
                ],
                'status_record' => [
                    'id' => $statusRecord->id,
                    'existed' => $statusRecordExisted,
                    'previous_status' => $previousStatus,
                    'applied_status' => $value,
                ],
                'account' => $accountMetadata,
            ],
        ]);
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @return array<string, mixed>
     */
    public function reverse(StudentEnrollment $enrollment, array $metadata): array
    {
        $reversal = $metadata['reversal'] ?? null;
        $studentMetadata = is_array($reversal) ? ($reversal['student'] ?? null) : null;
        $statusMetadata = is_array($reversal) ? ($reversal['status_record'] ?? null) : null;
        if (! is_array($studentMetadata) || ! is_array($statusMetadata)) {
            throw new EnrollmentTransitionException('Student synchronization cannot be safely reversed because its audit snapshot is unavailable.');
        }
        if ((int) ($studentMetadata['id'] ?? 0) !== (int) $enrollment->student_id) {
            throw new EnrollmentTransitionException('Student synchronization reversal does not belong to this enrollment.');
        }

        $attribute = (string) ($studentMetadata['attribute'] ?? '');
        if ($attribute !== 'status' || ! array_key_exists('previous_value', $studentMetadata)) {
            throw new EnrollmentTransitionException('Student synchronization reversal contains an invalid student snapshot.');
        }

        $student = Student::query()->lockForUpdate()->findOrFail($enrollment->student_id);
        $student->forceFill([$attribute => $studentMetadata['previous_value']])->save();

        $statusRecord = StudentStatusRecord::query()
            ->whereKey((int) ($statusMetadata['id'] ?? 0))
            ->where('student_id', $enrollment->student_id)
            ->lockForUpdate()
            ->first();
        if (($statusMetadata['existed'] ?? false) === true) {
            if (! $statusRecord instanceof StudentStatusRecord || ! array_key_exists('previous_status', $statusMetadata)) {
                throw new EnrollmentTransitionException('The previous student status record is unavailable for reversal.');
            }
            $statusRecord->forceFill(['status' => $statusMetadata['previous_status']])->save();
        } else {
            $statusRecord?->delete();
        }

        $accountMetadata = $reversal['account'] ?? null;
        if (is_array($accountMetadata)) {
            $account = Account::query()->whereKey((int) ($accountMetadata['id'] ?? 0))->lockForUpdate()->first();
            if (! $account instanceof Account) {
                throw new EnrollmentTransitionException('The synchronized student account is unavailable for reversal.');
            }
            $account->forceFill([
                'role' => $accountMetadata['previous_role'] ?? null,
                'person_id' => $accountMetadata['previous_person_id'] ?? null,
                'person_type' => $accountMetadata['previous_person_type'] ?? null,
            ])->save();
        }

        return [
            'restored' => true,
            'student_id' => $student->id,
            'status_record_id' => $statusMetadata['id'],
            'account_id' => is_array($accountMetadata) ? ($accountMetadata['id'] ?? null) : null,
        ];
    }
}
