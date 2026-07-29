<?php

declare(strict_types=1);

namespace App\Enrollment\Actions;

use App\Contracts\Enrollment\EnrollmentActionHandler;
use App\Contracts\Enrollment\EnrollmentOperatorSchemaProvider;
use App\Contracts\Enrollment\RuntimeEnrollmentAssignmentStrategy;
use App\Contracts\Enrollment\RuntimeEnrollmentBillingStrategy;
use App\Data\Enrollment\ActionResult;
use App\Data\Enrollment\EnrollmentContext;
use App\Enrollment\EnrollmentAssignmentService;
use App\Enrollment\EnrollmentPaymentService;
use App\Enrollment\EnrollmentPolicyRegistry;
use App\Jobs\GenerateAssessmentPdfJob;
use App\Jobs\SendAssessmentNotificationJob;
use App\Models\StudentEnrollment;
use Illuminate\Support\Facades\DB;

final readonly class EnrollmentIntegrationActionHandler implements EnrollmentActionHandler, EnrollmentOperatorSchemaProvider
{
    public function __construct(
        private string $handlerKey,
        private string $label,
        private EnrollmentAssignmentService $assignment,
        private EnrollmentPaymentService $payments,
        private EnrollmentPolicyRegistry $registry,
    ) {}

    public function key(): string
    {
        return $this->handlerKey;
    }

    public function metadata(): array
    {
        return [
            'key' => $this->handlerKey,
            'label' => $this->label,
            'category' => 'integration',
            'requires_configuration' => false,
        ];
    }

    public function payloadSchema(): array
    {
        return match ($this->handlerKey) {
            'enrollment.verify_academic' => ['type' => 'object'],
            'enrollment.verify_payment' => [
                'type' => 'object',
                'properties' => [
                    'invoicenumber' => ['type' => 'string'],
                    'settlements' => ['type' => 'object'],
                    'payment_method' => ['type' => 'string'],
                    'without_receipt' => ['type' => 'boolean'],
                    'reason' => ['type' => 'string'],
                ],
            ],
            'enrollment.assign_subjects' => [
                'type' => 'object',
                'properties' => [
                    'subjects' => [
                        'type' => 'array',
                        'items' => [
                            'type' => 'object',
                            'required' => ['subject_id'],
                            'properties' => [
                                'subject_id' => ['type' => 'integer'],
                                'is_modular' => ['type' => 'boolean'],
                                'exclude_from_tuition' => ['type' => 'boolean'],
                                'lecture_fee' => ['type' => 'number', 'minimum' => 0],
                                'laboratory_fee' => ['type' => 'number', 'minimum' => 0],
                            ],
                        ],
                    ],
                ],
            ],
            'enrollment.assign_classes' => [
                'type' => 'object',
                'properties' => [
                    'assignments' => [
                        'type' => 'array',
                        'items' => [
                            'type' => 'object',
                            'required' => ['subject_id', 'class_id'],
                            'properties' => [
                                'subject_id' => ['type' => 'integer'],
                                'class_id' => ['type' => 'integer'],
                            ],
                        ],
                    ],
                ],
            ],
            'enrollment.assign_additional_fees' => [
                'type' => 'object',
                'properties' => [
                    'fees' => [
                        'type' => 'array',
                        'items' => [
                            'type' => 'object',
                            'required' => ['fee_name', 'amount'],
                            'properties' => [
                                'fee_name' => ['type' => 'string'],
                                'amount' => ['type' => 'number', 'minimum' => 0],
                                'is_separate_transaction' => ['type' => 'boolean'],
                            ],
                        ],
                    ],
                ],
            ],
            'enrollment.calculate_tuition' => [
                'type' => 'object',
                'properties' => [
                    'discount_percentage' => ['type' => 'integer', 'minimum' => 0, 'maximum' => 100],
                    'downpayment' => ['type' => 'number', 'minimum' => 0],
                ],
            ],
            'enrollment.generate_assessment' => [
                'type' => 'object',
                'properties' => ['create_new_file' => ['type' => 'boolean']],
            ],
            'enrollment.notify' => [
                'type' => 'object',
                'properties' => ['notification' => ['type' => 'string', 'enum' => ['assessment']]],
            ],
            default => ['type' => 'object'],
        };
    }

    public function operatorSchema(): array
    {
        return [
            'description' => match ($this->handlerKey) {
                'enrollment.verify_academic' => 'Record the configured academic verification action inside the policy workflow.',
                'enrollment.verify_payment' => 'Validate and record an idempotent payment linked directly to this enrollment.',
                'enrollment.assign_subjects' => 'Create missing subject enrollments from the curriculum or transition payload.',
                'enrollment.assign_classes' => 'Reserve explicitly selected classes or the first class with an available seat.',
                'enrollment.assign_additional_fees' => 'Attach submitted additional fees to the enrollment.',
                'enrollment.calculate_tuition' => 'Create tuition from enrollment subjects using the billing behavior pinned in the policy snapshot.',
                'enrollment.generate_assessment' => 'Queue assessment PDF generation after the workflow transaction commits.',
                'enrollment.notify' => 'Queue an assessment notification after the workflow transaction commits.',
                default => 'Run the registered enrollment integration.',
            },
            'fields' => match ($this->handlerKey) {
                'enrollment.assign_subjects' => [
                    [
                        'key' => 'source', 'label' => 'Subject source override', 'control' => 'select',
                        'options' => [
                            ['value' => 'curriculum', 'label' => 'Matching curriculum'],
                            ['value' => 'runtime_payload', 'label' => 'Transition payload'],
                        ],
                    ],
                    [
                        'key' => 'allow_cross_program_subjects', 'label' => 'Allow subjects from another program',
                        'control' => 'boolean',
                    ],
                ],
                'enrollment.assign_classes' => [[
                    'key' => 'mode', 'label' => 'Class selection', 'control' => 'select', 'required' => true,
                    'options' => [
                        ['value' => 'first_available', 'label' => 'First available class'],
                        ['value' => 'runtime_payload', 'label' => 'Transition payload'],
                    ],
                ]],
                'enrollment.verify_payment' => [
                    [
                        'key' => 'receipt_mode',
                        'label' => 'Receipt mode',
                        'control' => 'select',
                        'required' => true,
                        'options' => [
                            ['value' => 'required', 'label' => 'Receipt required'],
                            ['value' => 'optional', 'label' => 'Receipt optional'],
                            ['value' => 'none', 'label' => 'No receipt; audited reason required'],
                        ],
                    ],
                    ['key' => 'record_transaction', 'label' => 'Create a payment transaction', 'control' => 'boolean'],
                    ['key' => 'allow_no_receipt', 'label' => 'Allow staff no-receipt override', 'control' => 'boolean'],
                ],
                'enrollment.calculate_tuition' => [[
                    'key' => 'discount_percentage', 'label' => 'Default discount', 'control' => 'percentage',
                    'minimum' => 0, 'maximum' => 100,
                ]],
                'enrollment.generate_assessment' => [[
                    'key' => 'create_new_file', 'label' => 'Always create a new assessment file', 'control' => 'boolean',
                ]],
                'enrollment.notify' => [[
                    'key' => 'notification', 'label' => 'Notification', 'control' => 'select', 'required' => true,
                    'options' => [['value' => 'assessment', 'label' => 'Assessment email']],
                ]],
                default => [],
            },
        ];
    }

    public function execute(EnrollmentContext $context, array $configuration, string $idempotencyKey): ActionResult
    {
        $enrollment = $context->enrollment;
        if (! $enrollment instanceof StudentEnrollment || ! $enrollment->exists) {
            return ActionResult::failure('This integration requires a persisted enrollment.');
        }

        return match ($this->handlerKey) {
            'enrollment.verify_academic' => $this->verifyAcademic($enrollment),
            'enrollment.verify_payment' => $this->payments->record($context, $configuration, $idempotencyKey),
            'enrollment.assign_subjects' => $this->assignSubjects($context, $configuration, $idempotencyKey),
            'enrollment.assign_classes' => $this->assignClasses($context, $configuration, $idempotencyKey),
            'enrollment.assign_additional_fees' => $this->assignment->assignAdditionalFees($context, $configuration),
            'enrollment.calculate_tuition' => $this->calculateTuition($context, $configuration, $idempotencyKey),
            'enrollment.generate_assessment' => $this->generateAssessment($enrollment, $configuration, $idempotencyKey),
            'enrollment.notify' => $this->notify($enrollment, $configuration, $idempotencyKey),
            default => ActionResult::failure("No integration executor is configured for [{$this->handlerKey}]."),
        };
    }

    private function verifyAcademic(StudentEnrollment $enrollment): ActionResult
    {
        return ActionResult::success([
            'verified' => 'academic',
            'subjects' => $enrollment->subjectsEnrolled()->count(),
        ]);
    }

    /** @param array<string, mixed> $configuration */
    private function assignSubjects(EnrollmentContext $context, array $configuration, string $idempotencyKey): ActionResult
    {
        if (is_string($configuration['source'] ?? null) && $configuration['source'] !== '') {
            return $this->assignment->assignSubjects($context, $configuration);
        }

        $strategyKey = (string) data_get($context->pinnedPolicyConfiguration, 'assignment.strategy', 'assignment.manual');
        $strategy = $this->registry->assignmentStrategy($strategyKey);
        if (! $strategy instanceof RuntimeEnrollmentAssignmentStrategy) {
            return ActionResult::failure("Enrollment assignment strategy [{$strategyKey}] cannot execute at runtime.");
        }

        return $strategy->execute($context, [
            ...data_get($context->pinnedPolicyConfiguration, 'assignment.configuration', []),
            ...$configuration,
            'operation' => 'subjects',
        ], $idempotencyKey);
    }

    /** @param array<string, mixed> $configuration */
    private function assignClasses(EnrollmentContext $context, array $configuration, string $idempotencyKey): ActionResult
    {
        if (is_string($configuration['mode'] ?? null) && $configuration['mode'] !== '') {
            return $this->assignment->assignClasses($context, $configuration);
        }

        $strategyKey = (string) data_get($context->pinnedPolicyConfiguration, 'assignment.strategy', 'assignment.manual');
        $strategy = $this->registry->assignmentStrategy($strategyKey);
        if (! $strategy instanceof RuntimeEnrollmentAssignmentStrategy) {
            return ActionResult::failure("Enrollment assignment strategy [{$strategyKey}] cannot execute at runtime.");
        }

        return $strategy->execute($context, [
            ...data_get($context->pinnedPolicyConfiguration, 'assignment.configuration', []),
            ...$configuration,
            'operation' => 'classes',
        ], $idempotencyKey);
    }

    /** @param array<string, mixed> $configuration */
    private function calculateTuition(EnrollmentContext $context, array $configuration, string $idempotencyKey): ActionResult
    {
        $assignmentStrategy = (string) data_get($context->pinnedPolicyConfiguration, 'assignment.strategy', 'assignment.manual');
        if ($context->channel === 'public'
            && $assignmentStrategy === 'assignment.manual'
            && $context->enrollment?->subjectsEnrolled()->doesntExist()) {
            return ActionResult::success([
                'skipped' => true,
                'reason' => 'awaiting_staff_subject_selection',
            ]);
        }

        $strategyKey = (string) data_get($context->pinnedPolicyConfiguration, 'billing.strategy', 'billing.course_rate');
        $strategy = $this->registry->billingStrategy($strategyKey);
        if (! $strategy instanceof RuntimeEnrollmentBillingStrategy) {
            return ActionResult::failure("Enrollment billing strategy [{$strategyKey}] cannot execute at runtime.");
        }

        return $strategy->execute($context, [
            ...data_get($context->pinnedPolicyConfiguration, 'billing.configuration', []),
            ...$configuration,
        ], $idempotencyKey);
    }

    /** @param array<string, mixed> $configuration */
    private function generateAssessment(StudentEnrollment $enrollment, array $configuration, string $idempotencyKey): ActionResult
    {
        $runtimePayload = $this->runtimePayload($configuration);
        $createNewFile = (bool) ($runtimePayload['create_new_file'] ?? $configuration['create_new_file'] ?? false);
        $jobId = $this->jobId('policy_assessment', $idempotencyKey);

        DB::afterCommit(
            fn () => GenerateAssessmentPdfJob::dispatch($enrollment->id, $jobId, $createNewFile),
        );

        return ActionResult::success(['job_id' => $jobId, 'queued_after_commit' => true]);
    }

    /** @param array<string, mixed> $configuration */
    private function notify(StudentEnrollment $enrollment, array $configuration, string $idempotencyKey): ActionResult
    {
        $runtimePayload = $this->runtimePayload($configuration);
        $notification = (string) ($runtimePayload['notification'] ?? $configuration['notification'] ?? 'assessment');
        if ($notification !== 'assessment') {
            return ActionResult::failure("Notification [{$notification}] is not supported by the core enrollment integration.");
        }

        $enrollment->loadMissing('student');
        if (! is_string($enrollment->student?->email) || $enrollment->student->email === '') {
            return ActionResult::failure('The student must have an email address before an assessment notification can be queued.');
        }

        $jobId = $this->jobId('policy_notification', $idempotencyKey);
        DB::afterCommit(
            fn () => SendAssessmentNotificationJob::dispatch($enrollment->id, $jobId),
        );

        return ActionResult::success(['job_id' => $jobId, 'notification' => $notification, 'queued_after_commit' => true]);
    }

    /** @param array<string, mixed> $configuration @return array<string, mixed> */
    private function runtimePayload(array $configuration): array
    {
        return is_array($configuration['runtime_payload'] ?? null) ? $configuration['runtime_payload'] : [];
    }

    private function jobId(string $prefix, string $idempotencyKey): string
    {
        return $prefix.'_'.mb_substr(hash('sha256', $idempotencyKey), 0, 32);
    }
}
