<?php

declare(strict_types=1);

namespace App\Enrollment;

use App\Data\Enrollment\ActionResult;
use App\Data\Enrollment\EnrollmentContext;
use App\Models\AdditionalFee;
use App\Models\ClassEnrollment;
use App\Models\Classes;
use App\Models\Course;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\Subject;
use App\Models\SubjectEnrollment;
use App\Services\ClassEnrollmentService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

final readonly class EnrollmentAssignmentService
{
    public function __construct(private ClassEnrollmentService $classes) {}

    /** @param array<string, mixed> $configuration */
    public function assignSubjects(EnrollmentContext $context, array $configuration): ActionResult
    {
        $enrollment = $context->enrollment;
        if (! $enrollment instanceof StudentEnrollment) {
            return ActionResult::failure('Subject assignment requires a persisted enrollment.');
        }

        $payload = $this->runtimePayload($configuration);
        $source = (string) ($configuration['source'] ?? (isset($payload['subjects']) ? 'runtime_payload' : 'curriculum'));
        if (! in_array($source, ['curriculum', 'runtime_payload'], true)) {
            return ActionResult::failure("Subject source [{$source}] is not supported.");
        }

        $subjects = $source === 'runtime_payload'
            ? $this->payloadSubjects($payload)
            : $this->curriculumSubjects($enrollment);
        if ($subjects->isEmpty()) {
            if ($source === 'runtime_payload' && $context->channel === 'public') {
                return ActionResult::success([
                    'subject_enrollment_ids' => [],
                    'source' => $source,
                    'skipped' => true,
                ]);
            }

            return ActionResult::failure($source === 'runtime_payload'
                ? 'The enrollment submission does not contain any subjects.'
                : 'No curriculum subjects match this enrollment.');
        }
        if ($source === 'runtime_payload' && $subjects->count() !== collect($payload['subjects'] ?? [])->count()) {
            return ActionResult::failure('One or more submitted subjects do not exist.');
        }

        $enrollment->loadMissing('course');
        $course = $enrollment->course()->first();
        if (! $course instanceof Course) {
            return ActionResult::failure('Subject assignment requires a valid enrollment program.');
        }
        $lectureRate = (float) data_get(
            $context->pinnedPolicyConfiguration,
            'billing.configuration.course_lecture_rate_per_unit',
            $course->lec_per_unit ?? 0,
        );
        $laboratoryRate = (float) data_get(
            $context->pinnedPolicyConfiguration,
            'billing.configuration.course_laboratory_rate_per_unit',
            $course->lab_per_unit ?? 0,
        );
        $allowCrossProgram = (bool) ($configuration['allow_cross_program_subjects'] ?? false);
        $created = [];
        foreach ($subjects as $item) {
            $subject = $item['subject'];
            if (! $allowCrossProgram && $enrollment->course_id !== null && (int) $subject->course_id !== (int) $enrollment->course_id) {
                return ActionResult::failure("Subject [{$subject->code}] does not belong to the enrollment program.");
            }

            $lecture = array_key_exists('lecture_fee', $item)
                ? (float) $item['lecture_fee']
                : ((int) $subject->lecture + (int) $subject->laboratory) * $lectureRate;
            if (! array_key_exists('lecture_fee', $item) && str_contains(mb_strtoupper((string) $subject->code), 'NSTP')) {
                $lecture *= (float) data_get($context->pinnedPolicyConfiguration, 'billing.configuration.nstp_lecture_multiplier', 0.5);
            }
            $laboratory = array_key_exists('laboratory_fee', $item)
                ? (float) $item['laboratory_fee']
                : ((int) $subject->laboratory > 0 ? $laboratoryRate : 0.0);

            $record = $enrollment->subjectsEnrolled()->firstOrCreate(
                ['subject_id' => $subject->id],
                [
                    'student_id' => $enrollment->student_id,
                    'school_id' => $enrollment->school_id,
                    'academic_year' => $enrollment->academic_year,
                    'school_year' => $enrollment->school_year,
                    'semester' => $enrollment->semester,
                    'is_modular' => (bool) ($item['is_modular'] ?? false),
                    'exclude_from_tuition' => (bool) ($item['exclude_from_tuition'] ?? false),
                    'lecture_fee' => $lecture,
                    'laboratory_fee' => $laboratory,
                    'enrolled_lecture_units' => (int) $subject->lecture,
                    'enrolled_laboratory_units' => (int) $subject->laboratory,
                ],
            );
            $created[] = $record->id;
        }

        return ActionResult::success(['subject_enrollment_ids' => $created, 'source' => $source]);
    }

    /** @param array<string, mixed> $configuration */
    public function assignClasses(EnrollmentContext $context, array $configuration): ActionResult
    {
        $enrollment = $context->enrollment;
        if (! $enrollment instanceof StudentEnrollment) {
            return ActionResult::failure('Class assignment requires a persisted enrollment.');
        }

        $payload = $this->runtimePayload($configuration);
        $mode = (string) ($configuration['mode'] ?? (isset($payload['assignments']) ? 'runtime_payload' : 'first_available'));
        if (! in_array($mode, ['first_available', 'runtime_payload'], true)) {
            return ActionResult::failure("Class selection mode [{$mode}] is not supported.");
        }
        $assignments = collect($payload['assignments'] ?? [])
            ->filter(fn (mixed $item): bool => is_array($item))
            ->keyBy(fn (array $item): int => (int) ($item['subject_id'] ?? 0));
        if ($mode === 'runtime_payload' && $assignments->isEmpty()) {
            if (($configuration['optional'] ?? false) === true) {
                return ActionResult::success(['assignments' => [], 'mode' => $mode, 'skipped' => true]);
            }

            return ActionResult::failure('The enrollment submission does not contain class assignments.');
        }

        $subjectEnrollments = $enrollment->subjectsEnrolled()->with('subject')->orderBy('id')->get();
        if ($subjectEnrollments->isEmpty()) {
            return ActionResult::failure('Subjects must be assigned before classes can be assigned.');
        }

        Student::query()->whereKey($enrollment->student_id)->lockForUpdate()->firstOrFail();
        $assigned = [];
        foreach ($subjectEnrollments as $subjectEnrollment) {
            if ($subjectEnrollment->class_id !== null
                && ClassEnrollment::query()->where('student_id', $enrollment->student_id)->where('class_id', $subjectEnrollment->class_id)->exists()) {
                $assigned[] = ['subject_enrollment_id' => $subjectEnrollment->id, 'class_id' => $subjectEnrollment->class_id];

                continue;
            }

            $requestedClassId = data_get($assignments->get((int) $subjectEnrollment->subject_id), 'class_id');
            if ($mode === 'runtime_payload' && $requestedClassId === null) {
                continue;
            }
            $class = $requestedClassId === null
                ? $this->lockFirstAvailableClass($enrollment, $subjectEnrollment)
                : Classes::query()->lockForUpdate()->find((int) $requestedClassId);
            if (! $class instanceof Classes || ! $this->classMatches($class, $enrollment, $subjectEnrollment)) {
                if (($configuration['optional'] ?? false) === true && $requestedClassId === null) {
                    continue;
                }

                return ActionResult::failure("No eligible class is available for subject [{$subjectEnrollment->subject?->code}].");
            }
            if ($this->classIsFull($class, (int) $enrollment->student_id)) {
                return ActionResult::failure("Class [{$class->section}] has no available seats.");
            }

            $this->classes->enrollOnceWhileLocked((int) $enrollment->student_id, (int) $class->id, [
                'school_id' => $enrollment->school_id,
                'status' => true,
            ]);
            $subjectEnrollment->update(['class_id' => $class->id]);
            $assigned[] = ['subject_enrollment_id' => $subjectEnrollment->id, 'class_id' => $class->id];
        }

        if ($assigned === []) {
            if (($configuration['optional'] ?? false) === true) {
                return ActionResult::success(['assignments' => [], 'mode' => $mode, 'skipped' => true]);
            }

            return ActionResult::failure('No classes were assigned.');
        }

        return ActionResult::success(['assignments' => $assigned, 'mode' => $mode]);
    }

    /** @param array<string, mixed> $configuration */
    public function assignAdditionalFees(EnrollmentContext $context, array $configuration): ActionResult
    {
        $enrollment = $context->enrollment;
        if (! $enrollment instanceof StudentEnrollment) {
            return ActionResult::failure('Additional-fee assignment requires a persisted enrollment.');
        }
        $fees = collect($this->runtimePayload($configuration)['fees'] ?? [])->filter(fn (mixed $fee): bool => is_array($fee));
        $ids = [];
        foreach ($fees as $fee) {
            $name = mb_trim((string) ($fee['fee_name'] ?? $fee['name'] ?? ''));
            $amount = (float) ($fee['amount'] ?? 0);
            if ($name === '' || $amount < 0) {
                return ActionResult::failure('Every additional fee requires a name and a non-negative amount.');
            }
            $record = AdditionalFee::query()->firstOrCreate(
                ['enrollment_id' => $enrollment->id, 'fee_name' => $name],
                [
                    'amount' => $amount,
                    'is_separate_transaction' => (bool) ($fee['is_separate_transaction'] ?? false),
                ],
            );
            $ids[] = $record->id;
        }

        return ActionResult::success(['additional_fee_ids' => $ids]);
    }

    /** @return array<string, mixed> */
    public function recommend(EnrollmentContext $context, string $strategy, array $configuration): array
    {
        return [
            'strategy' => $strategy,
            'configuration' => $configuration,
            'enrollment_id' => $context->enrollment?->id,
        ];
    }

    /** @return Collection<int, array{subject: Subject}> */
    private function curriculumSubjects(StudentEnrollment $enrollment): Collection
    {
        if ($enrollment->course_id === null) {
            return collect();
        }

        return Subject::query()
            ->where('course_id', $enrollment->course_id)
            ->where('academic_year', $enrollment->academic_year)
            ->where('semester', $enrollment->semester)
            ->orderBy('code')
            ->get()
            ->map(fn (Subject $subject): array => ['subject' => $subject]);
    }

    /** @param array<string, mixed> $payload @return Collection<int, array<string, mixed>> */
    private function payloadSubjects(array $payload): Collection
    {
        $items = collect($payload['subjects'] ?? [])->filter(fn (mixed $item): bool => is_array($item));
        $subjects = Subject::query()->whereKey($items->pluck('subject_id')->map(fn (mixed $id): int => (int) $id)->filter())->get()->keyBy('id');

        return $items->map(function (array $item) use ($subjects): ?array {
            $subject = $subjects->get((int) ($item['subject_id'] ?? 0));

            return $subject instanceof Subject ? [...$item, 'subject' => $subject] : null;
        })->filter()->values();
    }

    private function lockFirstAvailableClass(StudentEnrollment $enrollment, SubjectEnrollment $subjectEnrollment): ?Classes
    {
        $candidateIds = Classes::query()
            ->forAcademicPeriod((string) $enrollment->school_year, (int) $enrollment->semester)
            ->when($enrollment->school_id !== null, fn (Builder $query): Builder => $query->where('school_id', $enrollment->school_id))
            ->where(function (Builder $query) use ($subjectEnrollment): void {
                $query->where('subject_id', $subjectEnrollment->subject_id)
                    ->orWhereJsonContains('subject_ids', (int) $subjectEnrollment->subject_id)
                    ->orWhereJsonContains('subject_ids', (string) $subjectEnrollment->subject_id);
                $code = mb_trim((string) $subjectEnrollment->subject?->code);
                if ($code !== '') {
                    $query->orWhere('subject_code', $code);
                }
            })
            ->orderBy('id')
            ->pluck('id');

        foreach ($candidateIds as $candidateId) {
            $class = Classes::query()->lockForUpdate()->find($candidateId);
            if ($class instanceof Classes && ! $this->classIsFull($class, (int) $enrollment->student_id)) {
                return $class;
            }
        }

        return null;
    }

    private function classMatches(Classes $class, StudentEnrollment $enrollment, SubjectEnrollment $subjectEnrollment): bool
    {
        $period = Classes::query()->whereKey($class->id)
            ->forAcademicPeriod((string) $enrollment->school_year, (int) $enrollment->semester)
            ->exists();
        $school = $enrollment->school_id === null || (int) $class->school_id === (int) $enrollment->school_id;
        $classCode = mb_trim((string) $class->subject_code);
        $subjectCode = mb_trim((string) $subjectEnrollment->subject?->code);
        $subject = (int) $class->subject_id === (int) $subjectEnrollment->subject_id
            || ($classCode !== '' && $subjectCode !== '' && $classCode === $subjectCode)
            || in_array((int) $subjectEnrollment->subject_id, array_map(intval(...), $class->subject_ids ?? []), true);

        return $period && $school && $subject;
    }

    private function classIsFull(Classes $class, int $studentId): bool
    {
        if (ClassEnrollment::query()->where('class_id', $class->id)->where('student_id', $studentId)->exists()) {
            return false;
        }

        return (int) $class->maximum_slots > 0
            && $class->class_enrollments()->where('status', true)->count() >= (int) $class->maximum_slots;
    }

    /** @param array<string, mixed> $configuration @return array<string, mixed> */
    private function runtimePayload(array $configuration): array
    {
        return is_array($configuration['runtime_payload'] ?? null) ? $configuration['runtime_payload'] : [];
    }
}
