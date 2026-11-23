<?php

declare(strict_types=1);

namespace App\Enrollment\Rules;

use App\Contracts\Enrollment\EnrollmentOperatorSchemaProvider;
use App\Contracts\Enrollment\EnrollmentRuleHandler;
use App\Data\Enrollment\EnrollmentContext;
use App\Data\Enrollment\RuleResult;
use App\Models\Classes;
use App\Support\ScheduleDelivery;

final readonly class EnrollmentScheduleRule implements EnrollmentOperatorSchemaProvider, EnrollmentRuleHandler
{
    public function key(): string
    {
        return 'academics.verified_schedule';
    }

    public function metadata(): array
    {
        return ['key' => $this->key(), 'label' => 'Schedule conflicts (verified records)', 'category' => 'classes'];
    }

    public function configurationSchema(): array
    {
        return $this->operatorSchema();
    }

    public function operatorSchema(): array
    {
        return [
            'description' => 'At completion, check active roster sections in this school and enrollment period. Reject overlapping classes or gaps shorter than the configured minimum. Uses saved schedules, never caller facts. Subjects without assigned sections are not checked.',
            'fields' => [
                ['key' => 'minimum_gap_minutes', 'label' => 'Minimum gap between classes (minutes)', 'control' => 'number', 'minimum' => 0, 'maximum' => 1440, 'step' => 1, 'default' => 0],
                ['key' => 'missing_schedule', 'label' => 'Missing or invalid section schedule', 'control' => 'select', 'default' => 'block', 'options' => [
                    ['value' => 'block', 'label' => 'Block completion'],
                    ['value' => 'allow', 'label' => 'Allow unscheduled sections; report unchecked sections'],
                ]],
            ],
        ];
    }

    public function evaluate(EnrollmentContext $context, array $configuration): RuleResult
    {
        $gap = $configuration['minimum_gap_minutes'] ?? 0;
        $missing = $configuration['missing_schedule'] ?? 'block';
        if (! is_numeric($gap) || ! is_finite((float) $gap) || (float) $gap !== (float) (int) $gap
            || (int) $gap < 0 || (int) $gap > 1440 || ! in_array($missing, ['block', 'allow'], true)) {
            return RuleResult::fail('Schedule rule configuration is invalid.');
        }
        $enrollment = $context->enrollment;
        if (! $enrollment?->exists || ! $enrollment->school_year || ! $enrollment->semester) {
            return RuleResult::fail('A saved enrollment with an academic period is required for schedule checks.');
        }
        $classes = Classes::query()
            ->where('school_id', $enrollment->school_id)
            ->forAcademicPeriod((string) $enrollment->school_year, (int) $enrollment->semester)
            ->whereHas('class_enrollments', fn ($query) => $query->where('student_id', $enrollment->student_id)->where('status', true))
            ->with('schedules')->orderBy('id')->get();
        $policy = ScheduleDelivery::policy();
        $events = [];
        $unchecked = [];
        foreach ($classes as $class) {
            if ($class->schedules->isEmpty()) {
                if (ScheduleDelivery::checksStudentCohortConflicts($class->delivery_mode, $policy)) {
                    $unchecked[] = (int) $class->id;
                }

                continue;
            }

            $valid = 0;
            foreach ($class->schedules as $schedule) {
                if (! ScheduleDelivery::checksStudentCohortConflicts($schedule->delivery_mode, $policy)) {
                    $valid++;

                    continue;
                }

                $day = $this->day((string) $schedule->day_of_week);
                $start = $schedule->start_time;
                $end = $schedule->end_time;
                if ($day === null || $start === null || $end === null) {
                    $unchecked[] = (int) $class->id;

                    continue;
                }
                $startMinutes = $start->hour * 60 + $start->minute;
                $endMinutes = $end->hour * 60 + $end->minute;
                if ($endMinutes <= $startMinutes) {
                    $unchecked[] = (int) $class->id;

                    continue;
                }
                $valid++;
                $events[$day][] = ['class_id' => (int) $class->id, 'start' => $startMinutes, 'end' => $endMinutes];
            }
            if ($valid === 0) {
                $unchecked[] = (int) $class->id;
            }
        }
        $metadata = ['checked_classes' => $classes->count(), 'unchecked_class_ids' => array_values(array_unique($unchecked)), 'source' => 'server_records'];
        if ($classes->isEmpty()) {
            return RuleResult::fail('No active assigned sections are available for schedule checks.', $metadata);
        }
        if ($unchecked !== [] && $missing === 'block') {
            return RuleResult::fail('Assigned sections have missing or invalid schedules.', $metadata);
        }
        foreach ($events as $day => $items) {
            usort($items, static fn (array $a, array $b): int => [$a['start'], $a['class_id']] <=> [$b['start'], $b['class_id']]);
            foreach ($items as $index => $item) {
                for ($otherIndex = $index + 1; $otherIndex < count($items); $otherIndex++) {
                    $other = $items[$otherIndex];
                    if ($other['start'] >= $item['end'] + (int) $gap) {
                        break;
                    }
                    if ($item['class_id'] !== $other['class_id']) {
                        return RuleResult::fail('Assigned sections overlap or do not meet the minimum gap.', [
                            ...$metadata, 'day' => $day, 'class_ids' => [$item['class_id'], $other['class_id']], 'minimum_gap_minutes' => (int) $gap,
                        ]);
                    }
                }
            }
        }

        return RuleResult::pass($metadata);
    }

    private function day(string $value): ?string
    {
        return match (mb_strtolower(mb_trim($value))) {
            'mon', 'monday' => 'monday',
            'tue', 'tues', 'tuesday' => 'tuesday',
            'wed', 'wednesday' => 'wednesday',
            'thu', 'thur', 'thurs', 'thursday' => 'thursday',
            'fri', 'friday' => 'friday',
            'sat', 'saturday' => 'saturday',
            'sun', 'sunday' => 'sunday',
            default => null,
        };
    }
}
