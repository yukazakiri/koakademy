<?php

declare(strict_types=1);

namespace App\Enrollment\Rules;

use App\Contracts\Enrollment\EnrollmentOperatorSchemaProvider;
use App\Contracts\Enrollment\EnrollmentRuleHandler;
use App\Data\Enrollment\EnrollmentContext;
use App\Data\Enrollment\RuleResult;
use Carbon\CarbonImmutable;

final readonly class ConfiguredEnrollmentRuleHandler implements EnrollmentOperatorSchemaProvider, EnrollmentRuleHandler
{
    public function __construct(private string $handlerKey, private string $label, private string $category) {}

    public function key(): string
    {
        return $this->handlerKey;
    }

    public function metadata(): array
    {
        return ['key' => $this->handlerKey, 'label' => $this->label, 'category' => $this->category];
    }

    public function configurationSchema(): array
    {
        return $this->operatorSchema();
    }

    public function operatorSchema(): array
    {
        $schema = match ($this->handlerKey) {
            'availability.channel' => $this->schema('Choose where enrollment is available.', [
                $this->field('allowed', 'Enrollment channels', 'multi_select', true, optionSource: 'enrollment_channels'),
            ]),
            'availability.date_window' => $this->schema('Set the optional opening and closing dates.', [
                $this->field('starts_at', 'Opens on', 'date'),
                $this->field('ends_at', 'Closes on', 'date'),
            ]),
            'eligibility.student_type' => $this->allowedSchema('Choose eligible student types.', 'Student types', 'student_types'),
            'eligibility.school' => $this->allowedSchema('Choose eligible schools.', 'Schools', 'schools'),
            'eligibility.program' => $this->allowedSchema('Choose eligible programs.', 'Programs', 'programs'),
            'eligibility.period' => $this->allowedSchema('Choose eligible academic periods.', 'Academic periods', 'periods'),
            'eligibility.year_level' => $this->allowedSchema('Choose eligible year levels.', 'Year levels', 'year_levels'),
            'eligibility.duplicate_enrollment' => $this->schema('Prevent a second enrollment in the same academic period.', []),
            'billing.minimum_payment' => $this->schema('Require a minimum payment before completion.', [
                $this->field('type', 'Minimum payment', 'select', true, [
                    ['value' => 'none', 'label' => 'No minimum'],
                    ['value' => 'fixed', 'label' => 'Fixed amount'],
                    ['value' => 'percentage', 'label' => 'Percentage of tuition'],
                ]),
                [
                    ...$this->field('value', 'Amount or percentage', 'number', true),
                    'min' => 0,
                    'visible_when' => ['field' => 'type', 'in' => ['fixed', 'percentage']],
                ],
            ]),
            default => $this->schema('Use a verified student record value for this requirement.', [
                $this->field('fact_key', 'Student record field', 'text', true),
                $this->field('expected', 'Required result', 'boolean'),
                $this->field('message', 'Message when not satisfied', 'text'),
            ]),
        };

        return [
            ...$schema,
            'what_it_does' => $schema['description'],
            'impact' => match ($this->category) {
                'availability' => 'Students who do not meet this setting cannot start enrollment through the affected channel or period.',
                'billing' => 'Students must satisfy this payment rule before the workflow can finish.',
                default => 'Students who do not meet this requirement are shown as blocked during simulation and enrollment.',
            },
            'example' => match ($this->handlerKey) {
                'availability.channel' => 'Allow public registration and administrator-assisted enrollment, but disable API enrollment.',
                'availability.date_window' => 'Open enrollment on May 1 and close it on June 15.',
                'eligibility.duplicate_enrollment' => 'Keep this enabled to prevent a student from enrolling twice in one term.',
                'billing.minimum_payment' => 'Require 20% of tuition before the final approval step.',
                default => 'Use a representative student in Test and publish to confirm this requirement.',
            },
            'docs_anchor' => 'enrollment-policies/availability-eligibility-documents',
            'fields' => collect($schema['fields'])->map(fn (array $field): array => [
                'description' => $this->fieldDescription((string) $field['key']),
                ...$field,
            ])->all(),
        ];
    }

    public function evaluate(EnrollmentContext $context, array $configuration): RuleResult
    {
        return match ($this->handlerKey) {
            'availability.channel' => $this->allowed($context->channel, $configuration['allowed'] ?? [], 'Enrollment channel is not available.'),
            'availability.date_window' => $this->dateWindow($configuration),
            'eligibility.student_type' => $this->allowed($context->studentType, $configuration['allowed'] ?? [], 'Student type is not eligible.'),
            'eligibility.school' => $this->allowed($context->schoolId, $configuration['allowed'] ?? [], 'School is not eligible.'),
            'eligibility.program' => $this->allowed($context->courseId, $configuration['allowed'] ?? [], 'Program is not eligible.'),
            'eligibility.period' => $this->period($context, $configuration),
            'eligibility.year_level' => $this->allowed($context->yearLevel, $configuration['allowed'] ?? [], 'Year level is not eligible.'),
            'eligibility.duplicate_enrollment' => $this->duplicateEnrollment($context),
            'billing.minimum_payment' => $this->minimumPayment($context, $configuration),
            default => $this->fact($context, $configuration),
        };
    }

    /** @param array<int, mixed> $allowed */
    private function allowed(mixed $actual, array $allowed, string $message): RuleResult
    {
        if ($allowed === [] || in_array($actual, $allowed, true) || in_array((string) $actual, array_map(strval(...), $allowed), true)) {
            return RuleResult::pass(['actual' => $actual]);
        }

        return RuleResult::fail($message, ['actual' => $actual, 'allowed' => $allowed]);
    }

    /** @param array<string, mixed> $configuration */
    private function dateWindow(array $configuration): RuleResult
    {
        $now = CarbonImmutable::now();
        $startsAt = isset($configuration['starts_at']) ? CarbonImmutable::parse($configuration['starts_at']) : null;
        $endsAt = isset($configuration['ends_at']) ? CarbonImmutable::parse($configuration['ends_at']) : null;

        if (($startsAt && $now->isBefore($startsAt)) || ($endsAt && $now->isAfter($endsAt))) {
            return RuleResult::fail('Enrollment is outside the configured date window.');
        }

        return RuleResult::pass();
    }

    /** @param array<string, mixed> $configuration */
    private function period(EnrollmentContext $context, array $configuration): RuleResult
    {
        $periods = $configuration['allowed'] ?? [];
        $current = $context->schoolYear.'|'.$context->semester;

        return $this->allowed($current, is_array($periods) ? $periods : [], 'Academic period is not eligible.');
    }

    private function duplicateEnrollment(EnrollmentContext $context): RuleResult
    {
        if (! $context->enrollment instanceof \App\Models\StudentEnrollment) {
            return RuleResult::pass();
        }

        $query = $context->enrollment->newQuery()
            ->where('student_id', $context->enrollment->student_id)
            ->where('school_year', $context->schoolYear)
            ->where('semester', $context->semester);
        if ($context->enrollment->exists) {
            $query->whereKeyNot($context->enrollment->getKey());
        }
        $duplicateExists = $query->exists();

        return $duplicateExists
            ? RuleResult::fail('A matching enrollment already exists for this academic period.')
            : RuleResult::pass();
    }

    /** @param array<string, mixed> $configuration */
    private function minimumPayment(EnrollmentContext $context, array $configuration): RuleResult
    {
        $type = (string) ($configuration['type'] ?? 'none');
        if ($type === 'none') {
            return RuleResult::pass();
        }

        $enrollment = $context->enrollment;
        if (! $enrollment) {
            return RuleResult::fail('Enrollment payment data is unavailable.');
        }
        $paid = array_key_exists('payment_amount', $context->facts)
            ? (float) $context->facts['payment_amount']
            : (float) $enrollment->enrollmentTransactions()->sum('amount');
        $required = match ($type) {
            'fixed' => max(0.0, (float) ($configuration['value'] ?? 0)),
            'percentage' => (float) $enrollment->studentTuition()->value('overall_tuition')
                * min(100.0, max(0.0, (float) ($configuration['value'] ?? 0))) / 100,
            default => -1,
        };
        if ($required < 0) {
            return RuleResult::fail('Minimum payment configuration is invalid.');
        }

        return $paid >= $required
            ? RuleResult::pass(['paid' => $paid, 'required' => $required])
            : RuleResult::fail('The configured minimum payment has not been received.', ['paid' => $paid, 'required' => $required]);
    }

    /** @param array<string, mixed> $configuration */
    private function fact(EnrollmentContext $context, array $configuration): RuleResult
    {
        $factKey = (string) ($configuration['fact_key'] ?? str_replace('.', '_', $this->handlerKey));
        $expected = $configuration['expected'] ?? true;

        if (! array_key_exists($factKey, $context->facts)) {
            return RuleResult::fail("Required eligibility fact [{$factKey}] is unavailable.");
        }

        return $context->facts[$factKey] === $expected
            ? RuleResult::pass(['fact' => $factKey])
            : RuleResult::fail((string) ($configuration['message'] ?? "Eligibility requirement [{$this->label}] was not satisfied."));
    }

    /** @param array<int, array<string, mixed>> $fields @return array{description:string, fields:array<int, array<string, mixed>>} */
    private function schema(string $description, array $fields): array
    {
        return ['description' => $description, 'fields' => $fields];
    }

    /** @return array{description:string, fields:array<int, array<string, mixed>>} */
    private function allowedSchema(string $description, string $label, string $optionSource): array
    {
        return $this->schema($description, [
            $this->field('allowed', $label, 'multi_select', true, optionSource: $optionSource),
        ]);
    }

    /** @param array<int, array{value:string,label:string}> $options @return array<string, mixed> */
    private function field(
        string $key,
        string $label,
        string $control,
        bool $required = false,
        array $options = [],
        ?string $optionSource = null,
    ): array {
        return array_filter([
            'key' => $key,
            'label' => $label,
            'control' => $control,
            'required' => $required,
            'options' => $options,
            'option_source' => $optionSource,
        ], fn (mixed $value): bool => ! in_array($value, [null, [], false], true));
    }

    private function fieldDescription(string $key): string
    {
        return match ($key) {
            'allowed' => 'Select every option that should be accepted. An empty selection allows every option.',
            'starts_at' => 'Students cannot start before this date. Leave blank to open immediately.',
            'ends_at' => 'Students cannot start after this date. Leave blank for no closing date.',
            'type' => 'Choose whether the payment gate is disabled, a fixed amount, or a percentage.',
            'value' => 'Enter the amount or percentage required by the selected payment gate.',
            'fact_key' => 'Advanced: the verified student-record fact used by this requirement.',
            'expected' => 'Choose the result the student record must contain.',
            'message' => 'Explain what the student or staff member needs to correct.',
            default => 'Configure this value for students matched by the policy scope.',
        };
    }
}
