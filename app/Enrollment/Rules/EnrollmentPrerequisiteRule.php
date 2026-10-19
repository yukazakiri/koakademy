<?php

declare(strict_types=1);

namespace App\Enrollment\Rules;

use App\Contracts\Enrollment\EnrollmentOperatorSchemaProvider;
use App\Contracts\Enrollment\EnrollmentRuleHandler;
use App\Data\Enrollment\EnrollmentContext;
use App\Data\Enrollment\RuleResult;
use App\Models\Subject;
use App\Models\SubjectEnrollment;
use App\Services\GradeEvaluationService;

final readonly class EnrollmentPrerequisiteRule implements EnrollmentOperatorSchemaProvider, EnrollmentRuleHandler
{
    public function __construct(private GradeEvaluationService $grades) {}

    public function key(): string
    {
        return 'academics.verified_prerequisites';
    }

    public function metadata(): array
    {
        return ['key' => $this->key(), 'label' => 'Completed prerequisites (verified records)', 'category' => 'academics'];
    }

    public function configurationSchema(): array
    {
        return $this->operatorSchema();
    }

    public function operatorSchema(): array
    {
        return [
            'description' => 'At completion, require every current curriculum prerequisite ID to have a passing attempt in another completed enrollment in this school. Same-period, future-period, current-enrollment, and unlinked attempts do not count. Stored grade outcomes or pinned grading versions determine pass; unevaluated legacy grades fail closed.',
            'fields' => [[
                'key' => 'accept_credited', 'label' => 'Accept recorded credited prerequisite rows', 'control' => 'boolean', 'default' => false,
                'description' => 'When enabled, an is_credited row mapped by subject_id or credited_subject_id may satisfy a prerequisite without a grade. This trusts the recorded credit flag, not a separate approval status. The other enrollment must be completed in an earlier period.',
            ]],
        ];
    }

    public function evaluate(EnrollmentContext $context, array $configuration): RuleResult
    {
        $acceptCredited = $configuration['accept_credited'] ?? false;
        if (! is_bool($acceptCredited)) {
            return RuleResult::fail('Credited prerequisite setting must be true or false.');
        }
        $enrollment = $context->enrollment;
        if (! $enrollment?->exists) {
            return RuleResult::fail('A saved enrollment is required for prerequisite checks.');
        }
        if ($enrollment->school_id === null) {
            return RuleResult::fail('A school is required for verified prerequisite checks.');
        }
        $assigned = $enrollment->subjectsEnrolled()->with('subject')->get();
        if ($assigned->isEmpty()) {
            return RuleResult::fail('Assign subjects before checking prerequisites.');
        }
        $requirements = [];
        foreach ($assigned as $row) {
            if (! $row->subject) {
                return RuleResult::fail('Assigned subject record is unavailable.');
            }
            $ids = $row->subject->pre_riquisite ?? [];
            if (! is_array($ids)) {
                return RuleResult::fail('Prerequisites must contain curriculum subject IDs.');
            }
            foreach ($ids as $id) {
                if (! (is_int($id) || (is_string($id) && ctype_digit($id))) || (int) $id <= 0) {
                    return RuleResult::fail('Prerequisites must contain valid curriculum subject IDs.');
                }
                if ((int) $id === (int) $row->subject_id) {
                    return RuleResult::fail('A subject cannot be its own prerequisite.');
                }
                $requirements[(int) $row->subject_id][] = (int) $id;
            }
        }
        $requiredIds = array_values(array_unique(array_merge([], ...array_values($requirements))));
        if ($requiredIds === []) {
            return RuleResult::pass(['required_subject_ids' => [], 'source' => 'server_records']);
        }
        // Subject IDs are global; explicitly constrain prerequisite curriculum to this school.
        $validIds = Subject::query()->whereIn('id', $requiredIds)
            ->whereHas('course', fn ($query) => $query->where('school_id', $enrollment->school_id))->pluck('id')->all();
        if (count($validIds) !== count($requiredIds)) {
            return RuleResult::fail('A prerequisite curriculum subject is missing or belongs to another school.');
        }
        $satisfied = $this->completedSubjectIds($enrollment, $requiredIds, $acceptCredited);
        $missing = array_values(array_diff($requiredIds, $satisfied));
        $metadata = ['required_subject_ids' => $requiredIds, 'missing_subject_ids' => $missing, 'source' => 'server_records'];

        return $missing === [] ? RuleResult::pass($metadata) : RuleResult::fail('Required prerequisite subjects have not been completed.', $metadata);
    }

    /**
     * Reused by automatic back-subject selection so assignment and completion agree.
     *
     * @param  list<int>  $requiredIds
     * @return list<int>
     */
    public function completedSubjectIds(\App\Models\StudentEnrollment $enrollment, array $requiredIds, bool $acceptCredited = false): array
    {
        $attempts = SubjectEnrollment::query()->where('student_id', $enrollment->student_id)
            ->where('enrollment_id', '!=', $enrollment->id)
            ->where(fn ($query) => $query->whereIn('subject_id', $requiredIds)->orWhereIn('credited_subject_id', $requiredIds))
            ->whereHas('enrollment', function ($query) use ($enrollment): void {
                $query->where('school_id', $enrollment->school_id)
                    ->where('terminal_outcome', 'completed')
                    ->where(fn ($query) => $query->where('school_year', '!=', $enrollment->school_year)->orWhere('semester', '!=', $enrollment->semester));
            })->with(['enrollment', 'gradingPolicyVersion'])->get();
        $satisfied = [];
        foreach ($attempts as $attempt) {
            $attemptPeriod = $this->period((string) $attempt->enrollment->school_year, (int) $attempt->enrollment->semester);
            $currentPeriod = $this->period((string) $enrollment->school_year, (int) $enrollment->semester);
            if ($attemptPeriod === null || $currentPeriod === null || $attemptPeriod >= $currentPeriod) {
                continue;
            }
            if ($acceptCredited && $attempt->is_credited) {
                $satisfied[] = (int) $attempt->subject_id;
                $satisfied[] = (int) $attempt->credited_subject_id;

                continue;
            }
            $outcome = $attempt->grade_outcome;
            if ($outcome === null && $attempt->gradingPolicyVersion?->state === 'published') {
                $policy = $attempt->gradingPolicyVersion->configuration;
                $grade = ($policy['input_type'] ?? 'numeric') === 'symbol' ? $attempt->grade_symbol : $attempt->grade;
                $outcome = $this->grades->evaluate($grade, $policy)['outcome'];
            }
            if (in_array($outcome, ['pass', 'passed'], true)) {
                $satisfied[] = (int) $attempt->subject_id;
            }
        }

        return array_values(array_unique($satisfied));
    }

    /** @return array{int, int}|null */
    private function period(string $schoolYear, int $semester): ?array
    {
        if (preg_match('/^(\d{4})\s*-\s*(\d{4})$/', mb_trim($schoolYear), $matches) !== 1
            || (int) $matches[2] !== (int) $matches[1] + 1 || $semester < 1) {
            return null;
        }

        return [(int) $matches[1], $semester];
    }
}
