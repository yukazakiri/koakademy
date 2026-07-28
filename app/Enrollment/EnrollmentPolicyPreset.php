<?php

declare(strict_types=1);

namespace App\Enrollment;

use App\Enums\EnrollStat;

final class EnrollmentPolicyPreset
{
    /** @return array<string, array{label:string, description:string}> */
    public static function catalog(): array
    {
        return [
            'legacy' => ['label' => 'Current legacy process', 'description' => 'Department review followed by cashier verification.'],
            'registrar_cashier' => ['label' => 'Registrar and cashier', 'description' => 'Registrar approval followed by cashier verification.'],
            'manual' => ['label' => 'Manual enrollment', 'description' => 'Staff review and assign subjects manually.'],
            'automatic_curriculum' => ['label' => 'Automatic curriculum', 'description' => 'Automatically assign curriculum subjects before review.'],
            'no_payment' => ['label' => 'No-payment enrollment', 'description' => 'Complete enrollment after academic approval without a payment gate.'],
        ];
    }

    /** @return array<string, mixed> */
    public static function configuration(string $preset): array
    {
        return match ($preset) {
            'registrar_cashier' => self::registrarAndCashier(),
            'manual' => self::manual(),
            'automatic_curriculum' => self::automaticCurriculum(),
            'no_payment' => self::noPayment(),
            default => self::standard(),
        };
    }

    /** @return array<string, mixed> */
    public static function standard(): array
    {
        return [
            'schema_version' => 1,
            'rules' => [
                ['key' => 'enrollment_channels', 'handler' => 'availability.channel', 'configuration' => ['allowed' => ['public', 'administrator', 'continuing', 'api']]],
                ['key' => 'duplicate_period', 'handler' => 'eligibility.duplicate_enrollment', 'configuration' => []],
            ],
            'requirements' => [],
            'assignment' => ['strategy' => 'assignment.manual', 'configuration' => []],
            'billing' => [
                'strategy' => 'billing.course_rate',
                'configuration' => [
                    'nstp_lecture_multiplier' => 0.5,
                    'modular_laboratory_multiplier' => 0.5,
                    'modular_fee' => 2400,
                    'miscellaneous_fee_fallback' => 3500,
                    'course_lecture_rate_per_unit' => null,
                    'course_laboratory_rate_per_unit' => null,
                    'course_miscellaneous_fee' => null,
                    'discount_scope' => 'lecture_only',
                    'allow_overall_override' => true,
                    'receipt_mode' => 'required',
                    'minimum_payment' => ['type' => 'none', 'value' => 0],
                ],
                'allowed_payment_methods' => [],
            ],
            'workflow' => [
                'steps' => [
                    [
                        'key' => 'submitted', 'label' => 'Submitted', 'entry' => true, 'terminal' => false,
                        'status' => EnrollStat::Pending->value,
                        'permission' => 'Update:StudentEnrollment',
                        'actions' => [
                            [
                                'key' => 'assign_subjects',
                                'handler' => 'enrollment.assign_subjects',
                                'configuration' => ['allow_cross_program_subjects' => false],
                            ],
                            [
                                'key' => 'assign_additional_fees',
                                'handler' => 'enrollment.assign_additional_fees',
                                'configuration' => [],
                            ],
                            [
                                'key' => 'reserve_selected_classes',
                                'handler' => 'enrollment.assign_classes',
                                'configuration' => ['mode' => 'runtime_payload', 'optional' => true],
                            ],
                            [
                                'key' => 'calculate_tuition',
                                'handler' => 'enrollment.calculate_tuition',
                                'configuration' => [],
                            ],
                        ],
                        'transitions' => [['key' => 'academic_review', 'label' => 'Verify academics', 'to' => 'academic_verified', 'fallback' => true, 'conditions' => []]],
                    ],
                    [
                        'key' => 'academic_verified', 'label' => 'Academic verification', 'entry' => false, 'terminal' => false,
                        'status' => EnrollStat::VerifiedByDeptHead->value, 'permission' => 'Update:StudentEnrollment',
                        'actions' => [['key' => 'academic_status', 'handler' => 'enrollment.verify_academic', 'configuration' => ['status' => EnrollStat::VerifiedByDeptHead->value]]],
                        'transitions' => [['key' => 'payment_review', 'label' => 'Verify payment', 'to' => 'completed', 'fallback' => true, 'conditions' => []]],
                    ],
                    [
                        'key' => 'completed', 'label' => 'Completed', 'entry' => false, 'terminal' => true,
                        'status' => EnrollStat::VerifiedByCashier->value, 'outcome' => 'completed', 'permission' => 'Update:StudentEnrollment',
                        'actions' => [
                            [
                                'key' => 'payment_status',
                                'handler' => 'enrollment.verify_payment',
                                'configuration' => [
                                    'receipt_mode' => 'required',
                                    'record_transaction' => true,
                                    'allow_no_receipt' => false,
                                ],
                            ],
                            [
                                'key' => 'assign_remaining_classes',
                                'handler' => 'enrollment.assign_classes',
                                'configuration' => ['mode' => 'first_available', 'optional' => true],
                            ],
                            [
                                'key' => 'sync_student',
                                'handler' => 'enrollment.sync_student',
                                'configuration' => ['attribute' => 'status', 'value' => 'enrolled', 'sync_account' => true],
                            ],
                            ['key' => 'complete_outcome', 'handler' => 'enrollment.set_outcome', 'configuration' => ['outcome' => 'completed']],
                            [
                                'key' => 'generate_assessment',
                                'handler' => 'enrollment.generate_assessment',
                                'configuration' => ['create_new_file' => false],
                            ],
                            [
                                'key' => 'notify_student',
                                'handler' => 'enrollment.notify',
                                'configuration' => ['notification' => 'assessment'],
                            ],
                        ],
                        'transitions' => [],
                    ],
                ],
            ],
            'notifications' => [],
        ];
    }

    /** @return array<string, mixed> */
    private static function registrarAndCashier(): array
    {
        $configuration = self::standard();
        $configuration['workflow']['steps'][1]['label'] = 'Registrar approval';

        return $configuration;
    }

    /** @return array<string, mixed> */
    private static function manual(): array
    {
        $configuration = self::standard();
        $configuration['workflow']['steps'][0]['transitions'][0]['label'] = 'Complete staff review';
        $configuration['workflow']['steps'][1]['label'] = 'Staff review';
        $configuration['assignment'] = ['strategy' => 'assignment.manual', 'configuration' => []];

        return $configuration;
    }

    /** @return array<string, mixed> */
    private static function automaticCurriculum(): array
    {
        $configuration = self::standard();
        $configuration['assignment'] = [
            'strategy' => 'assignment.curriculum_automatic',
            'configuration' => ['include_irregular_subjects' => false],
        ];

        return $configuration;
    }

    /** @return array<string, mixed> */
    private static function noPayment(): array
    {
        $configuration = self::standard();
        $configuration['billing']['configuration']['minimum_payment'] = ['type' => 'none', 'value' => 0];
        $configuration['workflow']['steps'][1]['transitions'][0]['label'] = 'Complete enrollment';
        $configuration['workflow']['steps'][2]['actions'] = array_values(array_filter(
            $configuration['workflow']['steps'][2]['actions'],
            fn (array $action): bool => $action['handler'] !== 'enrollment.verify_payment',
        ));

        return $configuration;
    }
}
