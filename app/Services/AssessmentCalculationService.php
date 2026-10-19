<?php

declare(strict_types=1);

namespace App\Services;

use App\Enrollment\EnrollmentDiscountCalculation;
use App\Enrollment\EnrollmentFeeCalculator;
use App\Enrollment\EnrollmentFeePolicy;
use App\Models\FeeScheduleVersion;
use App\Models\StudentEnrollment;
use App\Models\StudentTuition;
use Illuminate\Support\Collection;

final readonly class AssessmentCalculationService
{
    public function __construct(private EnrollmentFeeCalculator $feeCalculator, private EnrollmentFeePolicy $feePolicy) {}

    /**
     * @param  Collection<int, mixed>  $subjects
     * @param  array<int, array<string, mixed>>  $additionalFees
     * @return array{
     *     gross_lecture: float,
     *     discount_percentage: int,
     *     discount_amount: float,
     *     discounted_lecture: float,
     *     laboratory: float,
     *     modular: float,
     *     total_tuition: float,
     *     miscellaneous: float,
     *     additional_fees: float,
     *     calculated_total: float,
     *     overall_tuition: float,
     *     assessment_adjustment: float,
     *     calculation_inputs: array<string, mixed>
     * }
     */
    public function calculateFromEnrolledSubjects(
        StudentEnrollment $enrollment,
        ?FeeScheduleVersion $feeSchedule = null,
        int $discountPercentage = 0,
        ?float $miscellaneousOverride = null,
        ?float $approvedTotal = null,
    ): array {
        $enrollment->loadMissing(['course', 'subjectsEnrolled.subject', 'additionalFees']);
        $course = $enrollment->course;

        $configuration = $this->feePolicy->forEnrollment($enrollment);
        if ($feeSchedule) {
            $configuration = [
                ...$configuration,
                'course_lecture_rate_per_unit' => (float) $feeSchedule->lecture_rate_per_unit,
                'course_laboratory_rate_per_unit' => (float) $feeSchedule->laboratory_rate_per_unit,
                'modular_fee' => (float) $feeSchedule->modular_fee,
                'nstp_lecture_multiplier' => (float) $feeSchedule->nstp_multiplier,
                'modular_laboratory_multiplier' => (float) $feeSchedule->modular_lab_multiplier,
            ];
        }
        $lectureRate = (float) $configuration['course_lecture_rate_per_unit'];
        $laboratoryRate = (float) $configuration['course_laboratory_rate_per_unit'];
        $modularFee = (float) $configuration['modular_fee'];
        $nstpMultiplier = (float) $configuration['nstp_lecture_multiplier'];
        $modularLabMultiplier = (float) $configuration['modular_laboratory_multiplier'];

        $grossLecture = 0.0;
        $laboratory = 0.0;
        $modular = 0.0;
        $subjectBreakdown = [];

        foreach ($enrollment->subjectsEnrolled as $subjectEnrollment) {
            if ($subjectEnrollment->exclude_from_tuition) {
                continue;
            }

            $subject = $subjectEnrollment->subject;
            if (! $subject) {
                continue;
            }

            $fees = $this->feeCalculator->subject($subject->toArray(), $configuration, (bool) $subjectEnrollment->is_modular, $feeSchedule ? [] : $subjectEnrollment->toArray());
            $subLecture = $fees['lecture_fee'];
            $subLaboratory = $fees['laboratory_fee'];
            $subModular = $fees['modular_fee'];
            $modular += $subModular;

            $subLecture = round($subLecture, 2);
            $subLaboratory = round($subLaboratory, 2);
            $grossLecture += $subLecture;
            $laboratory += $subLaboratory;

            $subjectBreakdown[] = [
                'subject_id' => $subject->id,
                'code' => (string) $subject->code,
                'lecture' => $subLecture,
                'laboratory' => $subLaboratory,
                'modular' => $subModular,
                'is_modular' => (bool) $subjectEnrollment->is_modular,
            ];
        }

        $grossLecture = round($grossLecture, 2);
        $laboratory = round($laboratory, 2);
        $modular = round($modular, 2);

        $miscellaneous = $miscellaneousOverride !== null
            ? round($miscellaneousOverride, 2)
            : ($feeSchedule
                ? round((float) $feeSchedule->miscellaneous_fee, 2)
                : round((float) ($configuration['miscellaneous_fee_override'] ?? $configuration['course_miscellaneous_fee'] ?? $course?->getMiscellaneousFee() ?? 0), 2));

        $additionalFees = round((float) $enrollment->additionalFees->sum('amount'), 2);

        $components = $this->calculateComponents(
            grossLecture: $grossLecture,
            laboratory: $laboratory,
            modular: $modular,
            miscellaneous: $miscellaneous,
            additionalFees: $additionalFees,
            discountPercentage: $discountPercentage,
            totalFees: $approvedTotal,
            discountScope: (string) ($configuration['discount_scope'] ?? 'lecture_only'),
        );

        return [
            ...$components,
            'calculation_inputs' => [
                'fee_schedule_version_id' => $feeSchedule?->id,
                'rates' => [
                    'lecture_rate' => $lectureRate,
                    'laboratory_rate' => $laboratoryRate,
                    'modular_fee' => $modularFee,
                    'nstp_multiplier' => $nstpMultiplier,
                    'modular_lab_multiplier' => $modularLabMultiplier,
                ],
                'subjects' => $subjectBreakdown,
            ],
        ];
    }

    /**
     * Assessment component calculation with explicit discount scope.
     *
     * @return array{
     *     gross_lecture: float,
     *     discount_percentage: int,
     *     discount_amount: float,
     *     discounted_lecture: float,
     *     laboratory: float,
     *     modular: float,
     *     total_tuition: float,
     *     miscellaneous: float,
     *     additional_fees: float,
     *     calculated_total: float,
     *     overall_tuition: float,
     *     assessment_adjustment: float
     * }
     */
    public function calculateComponents(
        float $grossLecture,
        float $laboratory,
        float $modular,
        float $miscellaneous,
        float $additionalFees,
        int $discountPercentage = 0,
        ?float $totalFees = null,
        string $discountScope = 'lecture_only',
    ): array {
        $discountPercentage = max(0, min(100, $discountPercentage));
        $grossLecture = round($grossLecture, 2);
        $laboratory = round($laboratory, 2);
        $modular = round($modular, 2);
        $miscellaneous = round($miscellaneous, 2);
        $additionalFees = round($additionalFees, 2);

        $discounted = (new EnrollmentDiscountCalculation)->calculate($grossLecture, $laboratory, $discountPercentage, $discountScope);
        $discountAmount = $discounted['amount'];
        $discountedLecture = $discounted['lecture'];
        $laboratory = $discounted['laboratory'];

        $totalTuition = round($discountedLecture + $laboratory + $modular, 2);
        $calculatedTotal = round($totalTuition + $miscellaneous + $additionalFees, 2);

        $overallTuition = $totalFees !== null
            ? round($totalFees, 2)
            : $calculatedTotal;

        $assessmentAdjustment = round($overallTuition - $calculatedTotal, 2);

        return [
            'gross_lecture' => $grossLecture,
            'discount_percentage' => $discountPercentage,
            'discount_amount' => $discountAmount,
            'discounted_lecture' => $discountedLecture,
            'laboratory' => $laboratory,
            'modular' => $modular,
            'total_tuition' => $totalTuition,
            'miscellaneous' => $miscellaneous,
            'additional_fees' => $additionalFees,
            'calculated_total' => $calculatedTotal,
            'overall_tuition' => $overallTuition,
            'assessment_adjustment' => $assessmentAdjustment,
        ];
    }

    /**
     * Resolves gross and discounted lecture charges without double discounting.
     *
     * @return array{gross_lecture: float, discount_amount: float, discounted_lecture: float}
     */
    public function resolveLecture(
        ?float $providedGross,
        ?float $providedLecture,
        int $discountPercentage,
        ?StudentTuition $existingTuition = null,
    ): array {
        $discountPercentage = max(0, min(100, $discountPercentage));

        if ($providedGross !== null && $providedGross >= 0) {
            $gross = round($providedGross, 2);
            $discountAmount = round($gross * ($discountPercentage / 100), 2);

            return [
                'gross_lecture' => $gross,
                'discount_amount' => $discountAmount,
                'discounted_lecture' => round($gross - $discountAmount, 2),
            ];
        }

        if ($providedLecture !== null) {
            $lecture = round($providedLecture, 2);

            // If existing tuition matches provided lecture and has the same discount,
            // the provided lecture is already the net/discounted lecture.
            if ($existingTuition && abs((float) $existingTuition->total_lectures - $lecture) < 0.005) {
                $gross = (float) ($existingTuition->gross_lecture ?? 0);
                if ($gross <= 0.0) {
                    $gross = $existingTuition->discount > 0 && $existingTuition->discount < 100
                        ? round($lecture / (1 - $existingTuition->discount / 100), 2)
                        : $lecture;
                }
            } else {
                // If provided as a separate gross input
                $gross = $lecture;
            }

            $discountAmount = round($gross * ($discountPercentage / 100), 2);

            return [
                'gross_lecture' => $gross,
                'discount_amount' => $discountAmount,
                'discounted_lecture' => round($gross - $discountAmount, 2),
            ];
        }

        // Fall back to existing tuition
        if ($existingTuition) {
            $gross = (float) ($existingTuition->gross_lecture ?? 0);
            if ($gross <= 0.0) {
                $gross = $existingTuition->discount > 0 && $existingTuition->discount < 100
                    ? round((float) $existingTuition->total_lectures / (1 - $existingTuition->discount / 100), 2)
                    : (float) $existingTuition->total_lectures;
            }

            $discountAmount = round($gross * ($discountPercentage / 100), 2);

            return [
                'gross_lecture' => $gross,
                'discount_amount' => $discountAmount,
                'discounted_lecture' => round($gross - $discountAmount, 2),
            ];
        }

        return [
            'gross_lecture' => 0.0,
            'discount_amount' => 0.0,
            'discounted_lecture' => 0.0,
        ];
    }

    /**
     * Compute a SHA-256 fingerprint of all assessment financial inputs.
     *
     * @param  array<string, mixed>  $data
     */
    public function fingerprint(array $data): string
    {
        $normalized = [
            'enrollment_id' => (int) ($data['enrollment_id'] ?? 0),
            'tuition_id' => (int) ($data['tuition_id'] ?? 0),
            'total_fees' => round((float) ($data['total_fees'] ?? 0), 2),
            'lecture' => round((float) ($data['lecture'] ?? $data['discounted_lecture'] ?? 0), 2),
            'laboratory' => round((float) ($data['laboratory'] ?? 0), 2),
            'miscellaneous' => round((float) ($data['miscellaneous'] ?? 0), 2),
            'discount' => (int) ($data['discount'] ?? 0),
            'paid' => round((float) ($data['paid'] ?? $data['opening_paid'] ?? 0), 2),
            'balance' => round((float) ($data['balance'] ?? 0), 2),
        ];

        return hash('sha256', json_encode($normalized, JSON_THROW_ON_ERROR));
    }
}
