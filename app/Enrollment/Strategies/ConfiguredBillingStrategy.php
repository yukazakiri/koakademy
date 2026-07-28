<?php

declare(strict_types=1);

namespace App\Enrollment\Strategies;

use App\Contracts\Enrollment\EnrollmentOperatorSchemaProvider;
use App\Contracts\Enrollment\RuntimeEnrollmentBillingStrategy;
use App\Data\Enrollment\ActionResult;
use App\Data\Enrollment\EnrollmentContext;
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
                    'key' => 'nstp_lecture_multiplier',
                    'label' => 'NSTP lecture multiplier',
                    'control' => 'number',
                    'minimum' => 0,
                    'maximum' => 1,
                    'default' => 0.5,
                ],
                [
                    'key' => 'modular_laboratory_multiplier',
                    'label' => 'Modular laboratory multiplier',
                    'control' => 'number',
                    'minimum' => 0,
                    'maximum' => 1,
                    'default' => 0.5,
                ],
                [
                    'key' => 'modular_fee',
                    'label' => 'Fee per modular subject',
                    'control' => 'money',
                    'minimum' => 0,
                    'default' => 2400,
                ],
                [
                    'key' => 'miscellaneous_fee_fallback',
                    'label' => 'Miscellaneous fee fallback',
                    'control' => 'money',
                    'minimum' => 0,
                    'default' => 3500,
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
