<?php

declare(strict_types=1);

namespace App\Enrollment\Strategies;

use App\Contracts\Enrollment\EnrollmentOperatorSchemaProvider;
use App\Contracts\Enrollment\RuntimeEnrollmentAssignmentStrategy;
use App\Data\Enrollment\ActionResult;
use App\Data\Enrollment\EnrollmentContext;
use App\Enrollment\EnrollmentAssignmentService;

final readonly class ConfiguredAssignmentStrategy implements EnrollmentOperatorSchemaProvider, RuntimeEnrollmentAssignmentStrategy
{
    public function __construct(
        private string $strategyKey,
        private string $label,
        private EnrollmentAssignmentService $assignment,
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
            'description' => match ($this->strategyKey) {
                'assignment.manual' => 'Staff select subjects and classes during enrollment.',
                'assignment.recommendation' => 'Recommend curriculum subjects without saving them automatically.',
                'assignment.curriculum_automatic' => 'Add the matching curriculum subjects automatically.',
                'assignment.class_first_available' => 'Reserve the first class section with an available seat.',
                default => 'Assignment is managed by the registered extension.',
            },
            'fields' => match ($this->strategyKey) {
                'assignment.curriculum_automatic' => [[
                    'key' => 'curriculum_selection', 'label' => 'Curriculum selection', 'control' => 'select', 'default' => 'current_year',
                    'description' => 'Eligible back subjects includes this and earlier year levels in the same semester. Excludes completed subjects and requires verified prior prerequisites. Does not cap units, reserve classes, or infer course equivalences.',
                    'options' => [
                        ['value' => 'current_year', 'label' => 'Current year and semester (existing behavior)'],
                        ['value' => 'eligible_back_subjects', 'label' => 'Eligible incomplete subjects through current year'],
                    ],
                ]],
                'assignment.class_first_available' => [[
                    'key' => 'prefer_least_filled', 'label' => 'Prefer the class with the most available seats', 'description' => 'Ranks finite sections by remaining active seats; unlimited sections rank first. Ties use class ID. Capacity is checked again under a row lock.', 'control' => 'boolean',
                ]],
                default => [],
            },
        ];
    }

    public function recommend(EnrollmentContext $context, array $configuration): array
    {
        return $this->assignment->recommend($context, $this->strategyKey, $configuration);
    }

    public function execute(EnrollmentContext $context, array $configuration, string $idempotencyKey): ActionResult
    {
        $operation = (string) ($configuration['operation'] ?? 'subjects');

        return match ([$this->strategyKey, $operation]) {
            ['assignment.curriculum_automatic', 'subjects'] => $this->assignment->assignSubjects($context, [
                ...$configuration,
                'source' => 'curriculum',
            ]),
            ['assignment.class_first_available', 'classes'] => $this->assignment->assignClasses($context, [
                ...$configuration,
                'mode' => 'first_available',
            ]),
            ['assignment.manual', 'subjects'], ['assignment.class_first_available', 'subjects'] => $this->assignment->assignSubjects($context, [
                ...$configuration,
                'source' => 'runtime_payload',
            ]),
            ['assignment.manual', 'classes'], ['assignment.curriculum_automatic', 'classes'] => $this->assignment->assignClasses($context, [
                ...$configuration,
                'mode' => 'runtime_payload',
            ]),
            ['assignment.recommendation', 'subjects'], ['assignment.recommendation', 'classes'] => ActionResult::success($this->recommend($context, $configuration)),
            default => ActionResult::failure("Assignment strategy [{$this->strategyKey}] has no runtime executor."),
        };
    }
}
