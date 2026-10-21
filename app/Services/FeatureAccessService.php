<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\FeatureReleaseAudience;
use App\Features\FeatureDecision;
use App\Features\FeatureDenial;
use App\Features\StableRollout;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Request/job scoped. Managed decisions never use persisted Pennant evaluations. */
final class FeatureAccessService
{
    private ?Collection $definitions = null;

    private ?array $legacyStates = null;

    /** @var array<string, Collection> */
    private array $assignments = [];

    /** @var array<string, list<int>> */
    private array $groups = [];

    public function reset(): void
    {
        $this->definitions = null;
        $this->legacyStates = null;
        $this->assignments = [];
        $this->groups = [];
    }

    public function definition(string $key): ?object
    {
        $this->definitions ??= Schema::hasTable('feature_definitions')
            ? DB::table('feature_definitions')->get()->keyBy('key') : collect();

        return $this->definitions->get(FeatureToggleRegistry::canonicalKey($key));
    }

    /** Legacy global rows still matter to legacy resolvers; batch per execution. */
    public function legacyGlobalStates(): array
    {
        return $this->legacyStates ??= DB::table('features')->where('scope', '__laravel_null')->pluck('value', 'name')->all();
    }

    public function before(string $key, ?User $user): ?bool
    {
        if ($this->definition($key)?->mode !== 'enforced') {
            return null;
        }

        return $this->decideForCurrentContext($key, $user)->active;
    }

    public function decideForCurrentContext(string $key, ?User $user): FeatureDecision
    {
        $schoolId = $user?->school_id;
        // A preview for someone else never inherits the operator's tenant.
        if ($user && Auth::id() === $user->id && app()->bound(TenantContext::class)) {
            $schoolId = app(TenantContext::class)->getCurrentSchoolId() ?? $schoolId;
        }

        return $this->decide($key, $user, $schoolId);
    }

    /** @param array<string, mixed>|null $proposedPolicy Validated preview only. */
    public function decide(string $key, ?User $user, ?int $schoolId = null, ?array $proposedPolicy = null): FeatureDecision
    {
        $key = FeatureToggleRegistry::canonicalKey($key);
        if (FeatureToggleRegistry::classForKey($key) === null) {
            return new FeatureDecision(false, 'Unknown feature', FeatureDenial::Excluded);
        }
        $catalog = FeatureToggleRegistry::definition($key);
        $definition = $this->definition($key);
        if ($definition === null) {
            return new FeatureDecision(false, 'Feature policy not initialized', FeatureDenial::NotReleased);
        }
        if ($proposedPolicy !== null) {
            $definition = (object) [...(array) $definition, ...$proposedPolicy];
        }
        if (! $catalog['public'] && (! $user || ! in_array($user->role?->value, $catalog['roles'], true))) {
            return new FeatureDecision(false, 'Role not eligible', FeatureDenial::Excluded);
        }
        if (! $definition->enabled) {
            return new FeatureDecision(false, 'Disabled by administrator', FeatureDenial::Excluded);
        }
        $window = $this->releaseWindow($definition);
        if ($window === 'before') {
            return new FeatureDecision(false, 'Scheduled for a later release', FeatureDenial::NotReleased);
        }
        if ($window === 'after') {
            return new FeatureDecision(false, 'Retired by administrator', FeatureDenial::Excluded);
        }
        if ($catalog['public']) {
            return new FeatureDecision($definition->strategy === 'defaults', 'Public installation policy');
        }
        $schoolId ??= $user->school_id;
        if ($schoolId !== null && $schoolId !== $user->school_id && ! FeatureManagementService::canManage($user)
            && ! $user->organizations()->where('schools.id', $schoolId)->wherePivot('is_active', true)->exists()) {
            return new FeatureDecision(false, 'School context not authorized', FeatureDenial::Excluded);
        }
        $matches = $this->matchingAssignments($user, $schoolId)->get($key) ?? collect();
        if ($matches->contains(fn ($row): bool => $row->source === 'administrator' && $row->effect === 'deny')) {
            return new FeatureDecision(false, 'Explicit administrator exclusion', FeatureDenial::Excluded);
        }

        $audience = isset($definition->release_audience) ? FeatureReleaseAudience::tryFrom($definition->release_audience) : null;
        if (isset($definition->release_audience) && $audience === null) {
            return new FeatureDecision(false, 'Invalid release audience', FeatureDenial::NotReleased);
        }
        $betaAudience = in_array($audience, [FeatureReleaseAudience::Beta, FeatureReleaseAudience::Selected], true);
        if ($betaAudience) {
            if (! $catalog['opt_in'] || $user->beta_access_joined_at === null) {
                return new FeatureDecision(false, 'Beta Access enrollment required', FeatureDenial::NotReleased);
            }
            $selected = $matches->contains(fn ($row): bool => $row->source === 'administrator' && $row->effect === 'allow');
            if ($audience === FeatureReleaseAudience::Selected && ! $selected) {
                return new FeatureDecision(false, 'Not selected for private beta', FeatureDenial::NotReleased);
            }
            if ($matches->contains(fn ($row): bool => $row->source === 'self_service' && $row->effect === 'deny')) {
                return new FeatureDecision(false, 'Beta opt-out', FeatureDenial::NotReleased);
            }
        }
        if (($audience === null || $definition->strategy !== 'percentage')
            && $matches->contains(fn ($row): bool => $row->source === 'administrator' && $row->effect === 'allow')) {
            return new FeatureDecision(true, 'Explicit administrator assignment');
        }
        // Existing policies retain per-feature opt-in until explicitly moved to an audience.
        if ($audience === null && $catalog['opt_in'] && $definition->allow_opt_in) {
            $preference = $matches->first(fn ($row): bool => $row->source === 'self_service');
            if ($preference) {
                $optedIn = $preference->effect === 'allow';

                return new FeatureDecision(
                    $optedIn,
                    $optedIn ? 'Beta opt-in' : 'Beta opt-out',
                    $optedIn ? FeatureDenial::None : FeatureDenial::NotReleased,
                );
            }
        }

        return match ($definition->strategy) {
            'defaults' => $audience !== null && $catalog['tier'] !== 'restricted'
                ? new FeatureDecision(true, $betaAudience ? 'Included in Beta Access' : 'Stable release')
                : new FeatureDecision(
                    $catalog['default_enabled'],
                    $catalog['default_enabled'] ? 'Built-in role default' : 'Not enabled by default',
                    $catalog['default_enabled'] ? FeatureDenial::None : FeatureDenial::NotReleased,
                ),
            'targeted' => new FeatureDecision(false, 'No matching target', FeatureDenial::NotReleased),
            'percentage' => $this->rollout($definition, $user, $key, $schoolId),
            default => new FeatureDecision(false, 'Invalid release strategy', FeatureDenial::NotReleased),
        };
    }

    /** before | during | after, evaluated against the current time. */
    private function releaseWindow(object $definition): string
    {
        $now = now();
        $activateAt = ! empty($definition->activate_at) ? Carbon::parse($definition->activate_at) : null;
        $retireAt = ! empty($definition->retire_at) ? Carbon::parse($definition->retire_at) : null;

        if ($activateAt !== null && $now->lt($activateAt)) {
            return 'before';
        }

        return $retireAt !== null && $now->gte($retireAt) ? 'after' : 'during';
    }

    private function rollout(object $definition, User $user, string $key, ?int $schoolId): FeatureDecision
    {
        $active = StableRollout::includes($definition->rollout_seed, $key, (int) $user->id, $schoolId, (int) $definition->percentage);

        return new FeatureDecision(
            $active,
            $active ? 'Included in stable rollout' : 'Outside current rollout',
            $active ? FeatureDenial::None : FeatureDenial::NotReleased,
        );
    }

    private function matchingAssignments(User $user, ?int $schoolId): Collection
    {
        $context = $user->id.':'.$schoolId.':'.$user->role?->value;

        return $this->assignments[$context] ??= DB::table('feature_assignments')
            ->where(function ($query) use ($user, $schoolId): void {
                $query->where(fn ($match) => $match->where('subject_type', 'user')->where('subject_key', (string) $user->id))
                    ->orWhere(fn ($match) => $match->where('subject_type', 'role')->where('subject_key', $user->role?->value))
                    ->orWhere(fn ($match) => $match->where('subject_type', 'group')->whereIn('subject_key', array_map('strval', $this->groupIds($user, $schoolId))));
                if ($schoolId !== null) {
                    $query->orWhere(fn ($match) => $match->where('subject_type', 'school')->where('subject_key', (string) $schoolId));
                }
            })->get()->groupBy('feature_key');
    }

    /** @return list<int> */
    private function groupIds(User $user, ?int $schoolId): array
    {
        return $this->groups[$user->id.':'.$schoolId] ??= DB::table('feature_group_user')
            ->join('feature_groups', 'feature_groups.id', '=', 'feature_group_user.feature_group_id')
            ->where('user_id', $user->id)
            ->where(fn ($query) => $query->whereNull('school_id')->orWhere('school_id', $schoolId))
            ->pluck('feature_group_id')->map(fn ($id): int => (int) $id)->all();
    }
}
