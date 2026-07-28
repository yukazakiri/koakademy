<?php

declare(strict_types=1);

namespace App\Enrollment;

use App\Data\Enrollment\CompiledEnrollmentPolicy;
use App\Data\Enrollment\EnrollmentContext;
use App\Models\Course;
use App\Models\EnrollmentPolicy;
use App\Models\EnrollmentPolicySnapshot;
use App\Models\EnrollmentPolicyVersion;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;

final readonly class EnrollmentPolicyResolver
{
    private const int CompilerRevision = 3;

    public function __construct(private EnrollmentPolicyCompiler $compiler) {}

    public function resolve(EnrollmentContext $context): CompiledEnrollmentPolicy
    {
        $policies = EnrollmentPolicy::query()
            ->enabled()
            ->with('activeVersion')
            ->where(fn ($query) => $query->whereNull('school_id')->orWhere('school_id', $context->schoolId))
            ->where(fn ($query) => $query->whereNull('student_type')->orWhere('student_type', $context->studentType))
            ->where(fn ($query) => $query->whereNull('course_id')->orWhere('course_id', $context->courseId))
            ->where(fn ($query) => $query->whereNull('school_year')->orWhere('school_year', $context->schoolYear))
            ->where(fn ($query) => $query->whereNull('semester')->orWhere('semester', $context->semester))
            ->get()
            ->filter(fn (EnrollmentPolicy $policy): bool => $policy->activeVersion !== null)
            ->sortBy(fn (EnrollmentPolicy $policy): string => $this->precedenceKey($policy))
            ->values();

        $layers = $policies->map(fn (EnrollmentPolicy $policy): array => $this->layer($policy))->all();

        $sourceKey = hash('sha256', EnrollmentPolicyCompiler::CurrentSchemaVersion.'|'.self::CompilerRevision.'|'.implode(',', $policies->pluck('active_version_id')->all()));

        return Cache::remember(
            "enrollment-policy:compiled:{$sourceKey}",
            now()->addYear(),
            fn (): CompiledEnrollmentPolicy => $this->compiler->compile($layers),
        );
    }

    public function resolvePreview(EnrollmentPolicy $draftPolicy, EnrollmentPolicyVersion $draftVersion, EnrollmentContext $context): CompiledEnrollmentPolicy
    {
        if (! $this->matchesContext($draftPolicy, $context)) {
            throw ValidationException::withMessages([
                'simulation' => 'The sample student does not match the policy scope being tested.',
            ]);
        }

        $policies = $this->matchingPolicies($context)
            ->reject(fn (EnrollmentPolicy $policy): bool => $policy->id === $draftPolicy->id)
            ->push($draftPolicy->setRelation('activeVersion', $draftVersion))
            ->sortBy(fn (EnrollmentPolicy $policy): string => $this->precedenceKey($policy))
            ->values();

        return $this->compiler->compile($policies->map(fn (EnrollmentPolicy $policy): array => $this->layer($policy))->all());
    }

    public function snapshot(EnrollmentContext $context): EnrollmentPolicySnapshot
    {
        $compiled = $this->resolve($context);
        $configuration = $this->materializeContextDefaults($compiled->configuration, $context);
        $snapshotChecksum = $this->compiler->checksumConfiguration($configuration);

        try {
            return EnrollmentPolicySnapshot::query()->firstOrCreate(
                ['checksum' => $snapshotChecksum],
                [
                    'schema_version' => $compiled->schemaVersion,
                    'configuration' => $configuration,
                    'source_version_ids' => $compiled->sourceVersionIds,
                ],
            );
        } catch (UniqueConstraintViolationException) {
            return EnrollmentPolicySnapshot::query()->where('checksum', $snapshotChecksum)->firstOrFail();
        }
    }

    /** @param array<string, mixed> $configuration @return array<string, mixed> */
    private function materializeContextDefaults(array $configuration, EnrollmentContext $context): array
    {
        if ($context->courseId === null) {
            return $configuration;
        }

        $course = Course::query()->find($context->courseId);
        if (! $course instanceof Course) {
            return $configuration;
        }

        data_set($configuration, 'billing.configuration.course_lecture_rate_per_unit', (float) ($course->lec_per_unit ?? 0));
        data_set($configuration, 'billing.configuration.course_laboratory_rate_per_unit', (float) ($course->lab_per_unit ?? 0));
        data_set($configuration, 'billing.configuration.course_miscellaneous_fee', (float) $course->getMiscellaneousFee());

        return $configuration;
    }

    private function matchesContext(EnrollmentPolicy $policy, EnrollmentContext $context): bool
    {
        return ($policy->school_id === null || $policy->school_id === $context->schoolId)
            && ($policy->student_type === null || $policy->student_type === $context->studentType)
            && ($policy->course_id === null || $policy->course_id === $context->courseId)
            && ($policy->school_year === null || $policy->school_year === $context->schoolYear)
            && ($policy->semester === null || $policy->semester === $context->semester);
    }

    /** @return \Illuminate\Support\Collection<int, EnrollmentPolicy> */
    private function matchingPolicies(EnrollmentContext $context): \Illuminate\Support\Collection
    {
        return EnrollmentPolicy::query()
            ->enabled()
            ->with('activeVersion')
            ->where(fn ($query) => $query->whereNull('school_id')->orWhere('school_id', $context->schoolId))
            ->where(fn ($query) => $query->whereNull('student_type')->orWhere('student_type', $context->studentType))
            ->where(fn ($query) => $query->whereNull('course_id')->orWhere('course_id', $context->courseId))
            ->where(fn ($query) => $query->whereNull('school_year')->orWhere('school_year', $context->schoolYear))
            ->where(fn ($query) => $query->whereNull('semester')->orWhere('semester', $context->semester))
            ->get()
            ->filter(fn (EnrollmentPolicy $policy): bool => $policy->activeVersion !== null);
    }

    /** @return array<string, mixed> */
    private function layer(EnrollmentPolicy $policy): array
    {
        $version = $policy->activeVersion;
        if (! $version instanceof EnrollmentPolicyVersion) {
            throw ValidationException::withMessages(['policy' => 'A matched enrollment policy is missing its active version.']);
        }

        return [
            'version_id' => $version->id,
            'version' => $version->version,
            'policy_id' => $policy->id,
            'policy_name' => $policy->name,
            'scope' => $policy->scopeLabels(),
            'configuration' => $version->configuration,
        ];
    }

    private function precedenceKey(EnrollmentPolicy $policy): string
    {
        $period = (int) ($policy->school_year !== null) + (int) ($policy->semester !== null);
        $specificity = (int) ($policy->school_id !== null)
            + (int) ($policy->student_type !== null)
            + (int) ($policy->course_id !== null)
            + $period;

        return sprintf(
            '%02d|%d|%d|%d|%d|%020d',
            $specificity,
            $period,
            (int) ($policy->course_id !== null),
            (int) ($policy->student_type !== null),
            (int) ($policy->school_id !== null),
            $policy->id,
        );
    }
}
