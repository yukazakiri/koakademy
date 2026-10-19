<?php

declare(strict_types=1);

namespace App\Enrollment\Strategies;

use App\Contracts\Enrollment\EnrollmentOperatorSchemaProvider;
use App\Contracts\Enrollment\RuntimeEnrollmentBillingStrategy;
use App\Data\Enrollment\ActionResult;
use App\Data\Enrollment\EnrollmentContext;
use App\Enrollment\EnrollmentFeeCalculator;
use App\Enrollment\EnrollmentTuitionService;

final readonly class ConfiguredBillingStrategy implements EnrollmentOperatorSchemaProvider, RuntimeEnrollmentBillingStrategy
{
    public function __construct(
        private string $strategyKey,
        private string $label,
        private EnrollmentTuitionService $tuition,
    ) {}

    public function key(): string
    {
        return $this->strategyKey;
    }

    public function metadata(): array
    {
        return ['key' => $this->strategyKey, 'label' => $this->label];
    }

    public function operatorSchema(): array
    {
        return [
            'description' => 'Use the program lecture, laboratory, miscellaneous fee, and existing discount rules.',
            'fields' => [
                [
                    'key' => 'discount_scope', 'label' => 'Discounted fee components', 'control' => 'select', 'default' => 'lecture_only',
                    'description' => 'Applies the enrollment discount once per eligible component. Modular surcharge, miscellaneous and additional fees remain excluded. Existing snapshots retain their scope.',
                    'options' => [
                        ['value' => 'lecture_only', 'label' => 'Lecture only (existing behavior)'],
                        ['value' => 'lecture_and_laboratory', 'label' => 'Lecture and laboratory'],
                    ],
                ],
                [
                    'key' => 'lecture_fee_basis', 'label' => 'Lecture fee basis', 'control' => 'select',
                    'default' => 'combined_units',
                    'description' => 'Combined units preserves the existing formula. Saved fee overrides are not repriced.',
                    'options' => [
                        ['value' => 'combined_units', 'label' => 'Lecture plus laboratory units'],
                        ['value' => 'lecture_units', 'label' => 'Lecture units only'],
                    ],
                ],
                [
                    'key' => 'laboratory_fee_basis', 'label' => 'Laboratory fee basis', 'control' => 'select',
                    'default' => 'flat_subject',
                    'options' => [
                        ['value' => 'flat_subject', 'label' => 'One charge per subject with a laboratory'],
                        ['value' => 'lab_units', 'label' => 'Rate multiplied by laboratory units'],
                    ],
                ],
                [
                    'key' => 'nstp_code_marker', 'label' => 'Reduced-lecture subject code marker', 'control' => 'text',
                    'default' => 'NSTP',
                    'description' => 'Case-insensitive code fragment receiving the lecture multiplier. Empty disables code matching. Use a registered billing strategy for category-based rules.',
                ],
                [
                    'key' => 'default_downpayment', 'label' => 'Default expected downpayment', 'control' => 'money',
                    'minimum' => 0, 'default' => EnrollmentFeeCalculator::Defaults['default_downpayment'],
                    'description' => 'Suggested amount for a new enrollment, not a recorded payment or a minimum-payment gate. Staff may override it; zero is allowed.',
                ],
                [
                    'key' => 'nstp_lecture_multiplier',
                    'step' => 0.01,
                    'label' => 'NSTP lecture multiplier',
                    'control' => 'number',
                    'minimum' => 0,
                    'maximum' => 1,
                    'default' => EnrollmentFeeCalculator::Defaults['nstp_lecture_multiplier'],
                ],
                [
                    'key' => 'modular_laboratory_multiplier',
                    'label' => 'Modular laboratory multiplier',
                    'description' => '1 keeps the full laboratory fee; 0.5 charges half; 0 waives it. Applied once when calculating a subject fee. Saved fee overrides are final amounts.',
                    'step' => 0.01,
                    'control' => 'number',
                    'minimum' => 0,
                    'maximum' => 1,
                    'default' => EnrollmentFeeCalculator::Defaults['modular_laboratory_multiplier'],
                ],
                [
                    'key' => 'modular_fee',
                    'label' => 'Fee per modular subject',
                    'description' => 'Added separately to lecture and laboratory charges. Set to 0 to disable. Publishing changes affects new policy enrollments, not existing snapshots.',
                    'control' => 'money',
                    'minimum' => 0,
                    'default' => EnrollmentFeeCalculator::Defaults['modular_fee'],
                ],
                [
                    'key' => 'miscellaneous_fee_override', 'label' => 'Policy miscellaneous fee override', 'control' => 'money',
                    'minimum' => 0,
                    'description' => 'Leave blank to use program/curriculum fees. Enter zero to waive the fee. Snapshot pins this amount for new policy enrollments; staff assessment overrides remain explicit.',
                ],
                [
                    'key' => 'miscellaneous_fee_fallback',
                    'label' => 'Miscellaneous fee fallback',
                    'control' => 'money',
                    'minimum' => 0,
                    'default' => EnrollmentFeeCalculator::Defaults['miscellaneous_fee_fallback'],
                ],
                [
                    'key' => 'receipt_mode',
                    'label' => 'Default receipt mode',
                    'control' => 'select',
                    'default' => 'required',
                    'options' => [
                        ['value' => 'required', 'label' => 'Receipt required'],
                        ['value' => 'optional', 'label' => 'Receipt optional'],
                        ['value' => 'none', 'label' => 'No receipt; audited reason required'],
                    ],
                ],
                [
                    'key' => 'discount_percentage', 'label' => 'Default discount', 'control' => 'percentage',
                    'minimum' => 0, 'maximum' => 100,
                ],
                [
                    'key' => 'minimum_payment_type', 'label' => 'Minimum payment', 'control' => 'select',
                    'options' => [
                        ['value' => 'none', 'label' => 'No minimum'],
                        ['value' => 'fixed', 'label' => 'Fixed amount'],
                        ['value' => 'percentage', 'label' => 'Percentage of tuition'],
                    ],
                ],
                [
                    'key' => 'minimum_payment_value',
                    'label' => 'Minimum amount or percentage',
                    'control' => 'number',
                    'required' => true,
                    'min' => 0,
                    'visible_when' => ['field' => 'minimum_payment_type', 'in' => ['fixed', 'percentage']],
                ],
            ],
        ];
    }

    public function calculate(EnrollmentContext $context, array $configuration): array
    {
        return $this->tuition->quote($context, $configuration);
    }

    public function execute(EnrollmentContext $context, array $configuration, string $idempotencyKey): ActionResult
    {
        return $this->tuition->persist($context, $configuration, $idempotencyKey);
    }
}
