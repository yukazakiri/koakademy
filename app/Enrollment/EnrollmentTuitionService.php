<?php

declare(strict_types=1);

namespace App\Enrollment;

use App\Data\Enrollment\ActionResult;
use App\Data\Enrollment\EnrollmentContext;
use App\Models\Course;
use App\Models\StudentEnrollment;
use App\Models\StudentTuition;
use App\Services\AssessmentCalculationService;
use App\Services\EnrollmentBillingService;

final readonly class EnrollmentTuitionService
{
    public function __construct(private EnrollmentBillingService $billing, private EnrollmentFeeCalculator $fees, private AssessmentCalculationService $assessments) {}

    /** @param array<string, mixed> $configuration */
    public function persist(
        EnrollmentContext $context,
        array $configuration,
        string $idempotencyKey,
    ): ActionResult {
        $enrollment = $context->enrollment;
        if (! $enrollment instanceof StudentEnrollment || ! $enrollment->exists) {
            return ActionResult::failure('Tuition calculation requires a persisted enrollment.');
        }

        $existing = $enrollment->studentTuition()->first();
        if ($existing) {
            return ActionResult::success([
                'tuition_id' => $existing->id,
                'overall_tuition' => (float) $existing->overall_tuition,
                'already_exists' => true,
            ]);
        }

        $runtime = is_array($configuration['runtime_payload'] ?? null)
            ? $configuration['runtime_payload']
            : [];
        $enrollment->loadMissing('course');
        $course = $enrollment->course()->first();
        if (! $course instanceof Course) {
            return ActionResult::failure('Tuition calculation requires a valid enrollment program.');
        }
        $lectureRate = (float) ($configuration['course_lecture_rate_per_unit'] ?? $course->lec_per_unit ?? 0);
        $laboratoryRate = (float) ($configuration['course_laboratory_rate_per_unit'] ?? $course->lab_per_unit ?? 0);
        $subjects = $enrollment->subjectsEnrolled()->with('subject')->orderBy('id')->get();
        if ($subjects->isEmpty()) {
            return ActionResult::failure('Tuition cannot be calculated before subjects are assigned.');
        }

        $lectureTotal = 0.0;
        $laboratoryTotal = 0.0;
        $modularTotal = 0.0;

        foreach ($subjects as $subjectEnrollment) {
            if ($subjectEnrollment->exclude_from_tuition) {
                continue;
            }

            $subject = $subjectEnrollment->subject;
            if (! $subject) {
                return ActionResult::failure("Subject enrollment [{$subjectEnrollment->id}] has no subject.");
            }

            $fees = $this->fees->subject($subject->toArray(), [
                ...$configuration,
                'course_lecture_rate_per_unit' => $lectureRate,
                'course_laboratory_rate_per_unit' => $laboratoryRate,
            ], (bool) $subjectEnrollment->is_modular, $subjectEnrollment->toArray());
            $lectureTotal += $fees['lecture_fee'];
            $laboratoryTotal += $fees['laboratory_fee'];
            $modularTotal += $fees['modular_fee'];
        }

        $discount = (int) ($runtime['discount_percentage'] ?? $runtime['discount'] ?? $configuration['discount_percentage'] ?? 0);
        if ($discount < 0 || $discount > 100) {
            return ActionResult::failure('Tuition discount must be between 0 and 100 percent.');
        }
        $discountId = isset($runtime['discount_id']) ? (int) $runtime['discount_id'] : null;
        $discounted = (new EnrollmentDiscountCalculation)->calculate($lectureTotal, $laboratoryTotal, $discount, (string) ($configuration['discount_scope'] ?? 'lecture_only'));
        $discountedLecture = $discounted['lecture'];
        $laboratoryTotal = $discounted['laboratory'];
        $tuitionTotal = $discountedLecture + $laboratoryTotal + $modularTotal;

        $miscellaneous = array_key_exists('miscellaneous_fee', $runtime)
            ? (float) $runtime['miscellaneous_fee']
            : (float) ($configuration['miscellaneous_fee_override']
                ?? $configuration['course_miscellaneous_fee']
                ?? $configuration['miscellaneous_fee_fallback']
                ?? $course->getMiscellaneousFee());
        $additionalFees = (float) $enrollment->additionalFees()->sum('amount');
        $overall = $tuitionTotal + $miscellaneous + $additionalFees;

        if (($configuration['allow_overall_override'] ?? false) === true
            && ($runtime['is_overall_manually_modified'] ?? false)
            && isset($runtime['overall_total'])) {
            $overall = (float) $runtime['overall_total'];
        }

        $downpayment = (float) ($runtime['downpayment'] ?? $enrollment->downpayment ?? 0);
        $tuition = StudentTuition::query()->create([
            'enrollment_id' => $enrollment->id,
            'student_id' => $enrollment->student_id,
            'total_tuition' => $tuitionTotal,
            'total_balance' => $overall,
            'total_lectures' => $discountedLecture,
            'total_laboratory' => $laboratoryTotal,
            'total_miscelaneous_fees' => $miscellaneous,
            'discount' => $discount,
            'discount_id' => $discountId,
            'downpayment' => $downpayment,
            'overall_tuition' => $overall,
            'semester' => $enrollment->semester,
            'school_year' => $enrollment->school_year,
            'academic_year' => $enrollment->academic_year,
        ]);
        $this->billing->syncTuitionBalance($tuition, $downpayment);

        return ActionResult::success([
            'tuition_id' => $tuition->id,
            'overall_tuition' => $overall,
            'discount_id' => $discountId,
            'discount_percentage' => $discount,
            'miscellaneous_fee' => $miscellaneous,
        ]);
    }

    /** @param array<string, mixed> $configuration @return array<string, mixed> */
    public function quote(EnrollmentContext $context, array $configuration): array
    {
        $enrollment = $context->enrollment;
        if (! $enrollment) {
            return [
                'strategy' => 'billing.course_rate',
                'configuration' => $configuration,
                'estimate_available' => false,
                'reason' => 'Select an existing enrollment with assigned subjects to calculate an estimate.',
            ];
        }
        $enrollment->loadMissing(['course', 'subjectsEnrolled.subject', 'additionalFees', 'studentTuition']);
        $configuration = [
            ...EnrollmentFeeCalculator::Defaults,
            'course_lecture_rate_per_unit' => (float) ($enrollment->course?->lec_per_unit ?? 0),
            'course_laboratory_rate_per_unit' => (float) ($enrollment->course?->lab_per_unit ?? 0),
            ...array_filter($configuration, static fn (mixed $value): bool => $value !== null),
        ];
        $lecture = $laboratory = $modular = 0.0;
        foreach ($enrollment->subjectsEnrolled as $subjectEnrollment) {
            if ($subjectEnrollment->exclude_from_tuition || ! $subjectEnrollment->subject) {
                continue;
            }
            $fees = $this->fees->subject($subjectEnrollment->subject->toArray(), $configuration, (bool) $subjectEnrollment->is_modular, $subjectEnrollment->toArray());
            $lecture += $fees['lecture_fee'];
            $laboratory += $fees['laboratory_fee'];
            $modular += $fees['modular_fee'];
        }

        return [
            'strategy' => 'billing.course_rate',
            'configuration' => $configuration,
            'enrollment_id' => $enrollment->id,
            'estimate_available' => $enrollment->subjectsEnrolled->isNotEmpty(),
            'estimate_basis' => 'Saved final subject fees; draft modular surcharge. Subject multipliers apply only when quoting new subject fees.',
            'estimate' => $this->assessments->calculateComponents(
                grossLecture: $lecture,
                laboratory: $laboratory,
                modular: $modular,
                miscellaneous: (float) ($configuration['miscellaneous_fee_override'] ?? $configuration['course_miscellaneous_fee'] ?? $enrollment->course?->getMiscellaneousFee() ?? $configuration['miscellaneous_fee_fallback']),
                additionalFees: (float) $enrollment->additionalFees->sum('amount'),
                discountPercentage: (int) ($configuration['discount_percentage'] ?? $enrollment->studentTuition?->discount ?? 0),
                discountScope: (string) ($configuration['discount_scope'] ?? 'lecture_only'),
            ),
        ];
    }
}
