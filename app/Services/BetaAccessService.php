<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\FeatureReleaseAudience;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class BetaAccessService
{
    public function __construct(private readonly FeatureManagementService $management, private readonly FeatureAccessService $access) {}

    public function updateEnrollment(User $user, bool $enabled): void
    {
        $joinedAt = DB::transaction(function () use ($user, $enabled): ?Carbon {
            $record = User::query()->lockForUpdate()->findOrFail($user->id);
            $before = $record->beta_access_joined_at;
            if (($before !== null) === $enabled) {
                return $before;
            }
            $record->beta_access_joined_at = $enabled ? now() : null;
            $record->save();
            $this->management->audit($user, null, $enabled ? 'beta_access_joined' : 'beta_access_left',
                ['joined_at' => $before?->toIso8601String()], ['joined_at' => $record->beta_access_joined_at?->toIso8601String()]);

            return $record->beta_access_joined_at;
        });
        $user->beta_access_joined_at = $joinedAt;
        $this->management->flush();
    }

    /** @return array{enrolled: bool, joined_at: string|null, features: list<array<string, mixed>>} */
    public function profile(User $user): array
    {
        $preferences = DB::table('feature_assignments')->where('subject_type', 'user')->where('subject_key', (string) $user->id)
            ->where('source', 'self_service')->pluck('effect', 'feature_key');
        $features = [];
        foreach (FeatureToggleRegistry::all() as $toggle) {
            if (! $this->supportsPreference($toggle->key(), $user)) {
                continue;
            }
            $decision = $this->access->decideForCurrentContext($toggle->key(), $user);
            $features[] = [
                'key' => $toggle->key(), 'name' => $toggle->name(), 'summary' => $toggle->summary(),
                'audience' => $this->access->definition($toggle->key())->release_audience,
                'enabled' => $preferences->get($toggle->key()) !== 'deny',
                'available' => $decision->active, 'reason' => $decision->reason,
            ];
        }

        return ['enrolled' => $user->beta_access_joined_at !== null, 'joined_at' => $user->beta_access_joined_at?->toIso8601String(), 'features' => $features];
    }

    public function updatePreference(User $user, string $key, bool $enabled): void
    {
        $key = FeatureToggleRegistry::canonicalKey($key);
        if ($user->beta_access_joined_at === null || ! $this->supportsPreference($key, $user)) {
            throw ValidationException::withMessages(['feature_key' => 'This feature is not available as a Beta Access preference.']);
        }
        $this->management->assign($key, 'user', (string) $user->id, $enabled ? 'allow' : 'deny', $user, 'self_service');
    }

    private function supportsPreference(string $key, User $user): bool
    {
        if (FeatureToggleRegistry::classForKey($key) === null) {
            return false;
        }
        $catalog = FeatureToggleRegistry::definition($key);
        $definition = $this->access->definition($key);

        return $definition?->mode === 'enforced' && $catalog['opt_in']
            && in_array($definition->release_audience, [FeatureReleaseAudience::Beta->value, FeatureReleaseAudience::Selected->value], true)
            && in_array($user->role?->value, $catalog['roles'], true);
    }
}
