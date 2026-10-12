<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Laravel\Pennant\Feature;

final class FeatureManagementService
{
    /** Upper bound on users resolved for an impact estimate. */
    private const int IMPACT_SAMPLE = 500;

    public function __construct(private readonly FeatureAccessService $access, private readonly LegacyFeaturePreview $legacyPreview) {}

    public static function canManage(?User $user): bool
    {
        return $user && in_array($user->role, [UserRole::Admin, UserRole::SuperAdmin, UserRole::Developer], true);
    }

    /** @return list<array<string, mixed>> */
    public function catalog(): array
    {
        $counts = DB::table('feature_assignments')->selectRaw('feature_key, count(*) as total')->groupBy('feature_key')->pluck('total', 'feature_key');

        return collect(FeatureToggleRegistry::all())->map(function ($toggle) use ($counts): array {
            $key = $toggle->key();
            $definition = $this->access->definition($key);

            return [
                'key' => $key, 'name' => $toggle->name(), 'summary' => $toggle->summary(),
                'audience' => $toggle->audience(), 'category' => $toggle->category(),
                'class' => $toggle::class, 'catalog' => FeatureToggleRegistry::definition($key),
                'policy' => $definition ? $this->policyPayload($definition) : null,
                'release_status' => $definition ? $this->releaseStatus($definition) : 'uninitialized',
                'assignment_count' => (int) ($counts[$key] ?? 0),
            ];
        })->values()->all();
    }

    /** @return array<string, mixed> */
    public function policyPayload(object $row): array
    {
        return [
            'enabled' => (bool) $row->enabled,
            'mode' => $row->mode,
            'strategy' => $row->strategy,
            'percentage' => (int) $row->percentage,
            'allow_opt_in' => (bool) $row->allow_opt_in,
            'activate_at' => empty($row->activate_at) ? null : Carbon::parse($row->activate_at)->utc()->toIso8601String(),
            'retire_at' => empty($row->retire_at) ? null : Carbon::parse($row->retire_at)->utc()->toIso8601String(),
            'revision' => (int) $row->revision,
        ];
    }

    /**
     * Who adopting managed policy would change for. Bounded so a large
     * installation cannot turn an admin screen into a full-table scan.
     *
     * @return array<string, mixed>
     */
    public function impact(string $key, User $actor, ?array $proposal = null): array
    {
        abort_unless(self::canManage($actor), 403);
        $key = FeatureToggleRegistry::canonicalKey($key);
        $this->known($key);
        $catalog = FeatureToggleRegistry::definition($key);
        $definition = $this->access->definition($key);
        abort_unless($definition !== null, 404);
        $proposal ??= $this->policyPayload($definition);
        $proposal = $this->normalizePolicy($key, $proposal);
        if ((int) $definition->revision !== (int) $proposal['revision']) {
            throw ValidationException::withMessages(['revision' => 'Policy changed. Refresh before reviewing impact.']);
        }
        $result = [
            'mode' => $definition->mode,
            'revision' => (int) $definition->revision,
            'evaluated_at' => now()->utc()->toIso8601String(),
            'policy' => $this->policyPayload((object) $proposal),
            'scope_note' => 'User counts cover eligible primary roles in their primary school. Old grants outside those roles will not carry over; action permissions are unchanged.',
        ];

        if ($catalog['public']) {
            return [...$result, 'eligible' => null, 'sampled' => 0, 'gaining' => 0, 'losing' => 0, 'unchanged' => 0,
                'public_current' => $this->currentState($key, null),
                'public_proposed' => $this->access->decide($key, null, proposedPolicy: $proposal)->active,
                'note' => 'Public enrollment is installation-wide.'];
        }

        $eligible = User::query()->whereNull('deleted_at')->whereIn('role', $catalog['roles']);
        $eligibleTotal = (clone $eligible)->count();
        $sample = (clone $eligible)->orderBy('id')->limit(self::IMPACT_SAMPLE)->get();
        $legacyValues = $definition->mode === 'legacy' ? $this->legacyPreview->values($key, $sample->all()) : [];

        $gaining = $losing = $unchanged = 0;
        foreach ($sample as $user) {
            $managed = $this->access->decide($key, $user, $user->school_id, $proposal)->active;
            $current = $definition->mode === 'legacy'
                ? $legacyValues[Feature::serializeScope($user)]
                : $this->access->decide($key, $user, $user->school_id)->active;

            match (true) {
                $managed && ! $current => $gaining++,
                ! $managed && $current => $losing++,
                default => $unchanged++,
            };
        }

        return [
            ...$result,
            'eligible' => $eligibleTotal,
            'sampled' => $sample->count(),
            'gaining' => $gaining,
            'losing' => $losing,
            'unchanged' => $unchanged,
            'note' => $eligibleTotal > $sample->count()
                ? "Counted the first {$sample->count()} of {$eligibleTotal} eligible users."
                : null,
        ];
    }

    public function currentState(string $key, ?User $user): bool
    {
        $this->known($key);

        return $this->access->definition($key)?->mode === 'enforced'
            ? $this->access->decide($key, $user, $user?->school_id)->active
            : $this->legacyPreview->values($key, [$user])[Feature::serializeScope($user)];
    }

    /** @param array<string, mixed> $data */
    public function updatePolicy(string $key, array $data, User $actor): void
    {
        abort_unless(self::canManage($actor), 403);
        $key = FeatureToggleRegistry::canonicalKey($key);
        $this->known($key);
        $data = $this->normalizePolicy($key, $data);
        DB::transaction(function () use ($key, $data, $actor): void {
            $before = DB::table('feature_definitions')->where('key', $key)->lockForUpdate()->first();
            abort_unless($before !== null, 404);
            if ((int) $before->revision !== (int) $data['revision']) {
                throw ValidationException::withMessages(['revision' => 'Policy changed in another session. Refresh before saving.']);
            }
            $after = [...$data, 'revision' => $before->revision + 1, 'updated_at' => now()];
            DB::table('feature_definitions')->where('key', $key)->update($after);
            $this->audit($actor, $key, 'policy_updated', $this->policyPayload($before), $this->policyPayload((object) $after));
        });
        $this->flush();
    }

    public function assign(string $key, string $type, string $subject, string $effect, User $actor, string $source = 'administrator'): void
    {
        $key = FeatureToggleRegistry::canonicalKey($key);
        abort_unless(in_array($source, ['administrator', 'self_service'], true) && in_array($effect, ['allow', 'deny'], true), 422);
        if ($source === 'self_service') {
            $catalog = FeatureToggleRegistry::definition($key);
            abort_unless($type === 'user' && $subject === (string) $actor->id && $catalog['opt_in']
                && $this->access->definition($key)?->allow_opt_in, 403);
        } else {
            abort_unless(self::canManage($actor), 403);
        }
        $this->known($key);
        $this->validateSubject($key, $type, $subject);
        DB::transaction(function () use ($key, $type, $subject, $effect, $actor, $source): void {
            $identity = ['feature_key' => $key, 'subject_type' => $type, 'subject_key' => $subject, 'source' => $source];
            $before = DB::table('feature_assignments')->where($identity)->first();
            $data = [...$identity, 'effect' => $effect, 'created_by' => $actor->id, 'created_at' => now(), 'updated_at' => now()];
            DB::table('feature_assignments')->upsert([$data], ['feature_key', 'subject_type', 'subject_key', 'source'], ['effect', 'created_by', 'updated_at']);
            $this->audit($actor, $key, $source === 'self_service' ? 'beta_preference_updated' : 'target_updated', $before ? (array) $before : null, $data);
        });
        $this->flush();
    }

    public function removeAssignment(string $key, int $id, User $actor): void
    {
        abort_unless(self::canManage($actor), 403);
        $key = FeatureToggleRegistry::canonicalKey($key);
        $this->known($key);
        DB::transaction(function () use ($key, $id, $actor): void {
            $before = DB::table('feature_assignments')->where('feature_key', $key)->where('id', $id)->first();
            abort_unless($before !== null, 404);
            DB::table('feature_assignments')->where('id', $id)->delete();
            $this->audit($actor, $key, 'target_removed', (array) $before, null);
        });
        $this->flush();
    }

    /** @return list<array<string, mixed>> */
    public function assignments(string $key): array
    {
        $this->known($key);
        $rows = DB::table('feature_assignments')->where('feature_key', $key)->orderBy('id')->get();
        $users = User::query()->whereIn('id', $rows->where('subject_type', 'user')->pluck('subject_key'))->pluck('name', 'id');
        $schools = DB::table('schools')->whereIn('id', $rows->where('subject_type', 'school')->pluck('subject_key'))->pluck('name', 'id');
        $groups = DB::table('feature_groups')->whereIn('id', $rows->where('subject_type', 'group')->pluck('subject_key'))->pluck('name', 'id');

        return $rows->map(fn ($row): array => [
            'id' => $row->id, 'subject_type' => $row->subject_type, 'subject_key' => $row->subject_key,
            'label' => match ($row->subject_type) {
                'role' => UserRole::tryFrom($row->subject_key)?->getLabel() ?? $row->subject_key,
                'user' => $users[$row->subject_key] ?? 'Removed user',
                'school' => $schools[$row->subject_key] ?? 'Removed school',
                'group' => $groups[$row->subject_key] ?? 'Removed group', default => $row->subject_key,
            }, 'effect' => $row->effect, 'source' => $row->source,
        ])->all();
    }

    public function known(string $key): void
    {
        abort_unless(FeatureToggleRegistry::classForKey($key) !== null, 404);
    }

    public function audit(User $actor, ?string $key, string $action, ?array $before, ?array $after): void
    {
        DB::table('feature_audit_logs')->insert([
            'feature_key' => $key, 'actor_id' => $actor->id, 'action' => $action,
            'before' => $before === null ? null : json_encode($before, JSON_THROW_ON_ERROR),
            'after' => $after === null ? null : json_encode($after, JSON_THROW_ON_ERROR), 'created_at' => now(),
        ]);
    }

    public function flush(): void
    {
        $this->access->reset();
        Feature::flushCache();
    }

    /** @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function normalizePolicy(string $key, array $data): array
    {
        $catalog = FeatureToggleRegistry::definition($key);
        if ($data['allow_opt_in'] && ! $catalog['opt_in']) {
            throw ValidationException::withMessages(['allow_opt_in' => 'Only catalog-approved beta features support self-service opt-in.']);
        }
        if ($catalog['public'] && $data['strategy'] !== 'defaults') {
            throw ValidationException::withMessages(['strategy' => 'Public enrollment uses installation-wide defaults, not authenticated-user targeting.']);
        }
        // DB timestamps use application timezone; all API timestamps have
        // explicit UTC offsets, avoiding browser/server timezone shifts.
        foreach (['activate_at', 'retire_at'] as $column) {
            $data[$column] = empty($data[$column]) ? null : Carbon::parse($data[$column])->setTimezone(config('app.timezone'))->toDateTimeString();
        }
        if ($data['activate_at'] !== null && $data['retire_at'] !== null && $data['retire_at'] <= $data['activate_at']) {
            throw ValidationException::withMessages(['retire_at' => 'Retirement must be after release time.']);
        }

        return $data;
    }

    private function releaseStatus(object $definition): string
    {
        if ($definition->mode === 'legacy') {
            return 'legacy';
        }
        if (! $definition->enabled) {
            return 'disabled';
        }
        if (! empty($definition->activate_at) && now()->lt(Carbon::parse($definition->activate_at))) {
            return 'scheduled';
        }
        if (! empty($definition->retire_at) && now()->gte(Carbon::parse($definition->retire_at))) {
            return 'retired';
        }

        return 'released';
    }

    private function validateSubject(string $key, string $type, string $subject): void
    {
        $roles = FeatureToggleRegistry::definition($key)['roles'];
        $valid = match ($type) {
            'role' => in_array($subject, $roles, true),
            'user' => User::query()->whereKey($subject)->whereIn('role', $roles)->exists(),
            'school' => DB::table('schools')->where('id', $subject)->exists(),
            'group' => DB::table('feature_groups')->where('id', $subject)->exists(),
            default => false,
        };
        if (! $valid || FeatureToggleRegistry::definition($key)['public']) {
            throw ValidationException::withMessages(['subject_key' => 'Select an eligible user, role, school, or beta group.']);
        }
    }
}
