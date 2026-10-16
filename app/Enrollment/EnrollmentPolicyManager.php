<?php

declare(strict_types=1);

namespace App\Enrollment;

use App\Models\EnrollmentPolicy;
use App\Models\EnrollmentPolicyVersion;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

final readonly class EnrollmentPolicyManager
{
    public function __construct(
        private EnrollmentPolicyCompiler $compiler,
        private EnrollmentPolicyInheritanceService $inheritance,
    ) {}

    /** @param array<string, mixed> $attributes */
    public function create(array $attributes, User $author): EnrollmentPolicy
    {
        $scope = [
            'school_id' => $attributes['school_id'] ?? null,
            'student_type' => $attributes['student_type'] ?? null,
            'course_id' => $attributes['course_id'] ?? null,
            'school_year' => $attributes['school_year'] ?? null,
            'semester' => $attributes['semester'] ?? null,
        ];
        $isScoped = collect($scope)->contains(fn (mixed $value): bool => $value !== null);
        $inherit = $isScoped && ($attributes['inherit'] ?? false) === true;
        if ($inherit && ! $this->inheritance->hasPublishedGlobalPolicy($scope)) {
            throw ValidationException::withMessages([
                'scope' => 'Publish the global enrollment policy before creating an inheriting policy.',
            ]);
        }

        $configuration = $attributes['configuration']
            ?? ($inherit
                ? ['schema_version' => EnrollmentPolicyCompiler::CurrentSchemaVersion]
                : EnrollmentPolicyPreset::configuration((string) ($attributes['preset'] ?? 'legacy')));
        $this->compiler->validateAuthoringConfiguration($configuration);
        $this->inheritance->compileForScope($scope, $configuration);

        try {
            return DB::transaction(function () use ($attributes, $author, $configuration, $scope): EnrollmentPolicy {
                $policy = EnrollmentPolicy::query()->create([
                    'name' => $attributes['name'],
                    ...$scope,
                    'scope_key' => EnrollmentPolicy::scopeKey($scope),
                    'created_by' => $author->id,
                ]);

                $policy->versions()->create([
                    'version' => 1,
                    'state' => EnrollmentPolicyVersion::Draft,
                    'schema_version' => EnrollmentPolicyCompiler::CurrentSchemaVersion,
                    'configuration' => $configuration,
                    'change_notes' => $attributes['change_notes'] ?? 'Initial draft',
                    'created_by' => $author->id,
                ]);

                return $policy->load(['versions', 'activeVersion']);
            });
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['scope' => 'An enrollment policy already exists for this exact scope.']);
        }
    }

    /** @param array<string, mixed> $configuration */
    public function saveDraft(EnrollmentPolicy $policy, array $configuration, ?string $changeNotes, User $author): EnrollmentPolicyVersion
    {
        $this->compiler->validateAuthoringConfiguration($configuration);
        $this->inheritance->compileForPolicy($policy, $configuration);

        return DB::transaction(function () use ($policy, $configuration, $changeNotes, $author): EnrollmentPolicyVersion {
            $locked = EnrollmentPolicy::query()->lockForUpdate()->findOrFail($policy->id);
            $draft = $locked->versions()->draft()->latest('version')->first();

            if (! $draft) {
                $draft = $locked->versions()->create([
                    'version' => ((int) $locked->versions()->max('version')) + 1,
                    'state' => EnrollmentPolicyVersion::Draft,
                    'schema_version' => EnrollmentPolicyCompiler::CurrentSchemaVersion,
                    'configuration' => $locked->activeVersion?->configuration ?? EnrollmentPolicyPreset::standard(),
                    'created_by' => $author->id,
                ]);
            }

            $draft->update(['configuration' => $configuration, 'change_notes' => $changeNotes]);

            return $draft->refresh();
        });
    }

    public function publish(EnrollmentPolicy $policy, EnrollmentPolicyVersion $version, User $publisher): EnrollmentPolicyVersion
    {
        if ($version->enrollment_policy_id !== $policy->id || $version->state !== EnrollmentPolicyVersion::Draft) {
            throw ValidationException::withMessages(['version' => 'Only a draft belonging to this policy can be published.']);
        }

        $this->inheritance->compileForPolicy(
            $policy,
            $this->publicationCandidate($policy, $version, $version->configuration),
        );

        return DB::transaction(function () use ($policy, $version, $publisher): EnrollmentPolicyVersion {
            $locked = EnrollmentPolicy::query()->lockForUpdate()->findOrFail($policy->id);
            $draft = EnrollmentPolicyVersion::query()->lockForUpdate()->findOrFail($version->id);
            $this->compiler->validateAuthoringConfiguration($draft->configuration);
            $configuration = $this->materializeStepPermissions($policy, $draft, $draft->configuration);
            $this->inheritance->compileForPolicy($locked, $configuration);

            $draft->update([
                'state' => EnrollmentPolicyVersion::Published,
                'configuration' => $configuration,
                'checksum' => $this->compiler->checksumConfiguration($configuration),
                'published_by' => $publisher->id,
                'published_at' => now(),
            ]);
            $locked->update(['active_version_id' => $draft->id]);

            return $draft->refresh();
        });
    }

    /** @param array<string, mixed> $configuration @return array<string, mixed> */
    public function publicationCandidate(
        EnrollmentPolicy $policy,
        EnrollmentPolicyVersion $version,
        array $configuration,
    ): array {
        $this->compiler->validateAuthoringConfiguration($configuration);
        $steps = data_get($configuration, 'workflow.steps', []);

        foreach ($steps as $index => $step) {
            if (! array_key_exists('authorized_role_ids', $step)) {
                continue;
            }

            $steps[$index]['permission'] = $this->stepPermissionName($policy, $version, $step, $index);
            unset($steps[$index]['authorized_role_ids']);
        }

        if (array_key_exists('workflow', $configuration)) {
            data_set($configuration, 'workflow.steps', $steps);
        }

        return $configuration;
    }

    public function rollback(EnrollmentPolicy $policy, EnrollmentPolicyVersion $version): void
    {
        if ($version->enrollment_policy_id !== $policy->id || $version->state !== EnrollmentPolicyVersion::Published) {
            throw ValidationException::withMessages(['version' => 'Rollback requires a published version from this policy.']);
        }

        DB::transaction(function () use ($policy, $version): void {
            EnrollmentPolicy::query()->lockForUpdate()->findOrFail($policy->id)
                ->update(['active_version_id' => $version->id]);
        });
    }

    /** @param array<string, mixed> $configuration @return array<string, mixed> */
    private function materializeStepPermissions(EnrollmentPolicy $policy, EnrollmentPolicyVersion $version, array $configuration): array
    {
        $steps = data_get($configuration, 'workflow.steps', []);

        foreach ($steps as $index => $step) {
            if (! array_key_exists('authorized_role_ids', $step)) {
                continue;
            }

            $roleIds = collect($step['authorized_role_ids'] ?? [])->map(fn (mixed $id): int => (int) $id)->filter()->values();
            $permissionName = $this->stepPermissionName($policy, $version, $step, $index);
            $permission = Permission::findOrCreate($permissionName);
            Role::query()->whereKey($roleIds)->each(fn (Role $role) => $role->givePermissionTo($permission));
            $steps[$index]['permission'] = $permissionName;
            unset($steps[$index]['authorized_role_ids']);
        }

        data_set($configuration, 'workflow.steps', $steps);

        return $configuration;
    }

    /** @param array<string, mixed> $step */
    private function stepPermissionName(EnrollmentPolicy $policy, EnrollmentPolicyVersion $version, array $step, int $index): string
    {
        return "EnrollmentPolicy:{$policy->id}:Version:{$version->version}:Step:".($step['key'] ?? $index);
    }
}
