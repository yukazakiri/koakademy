<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Laravel\Pennant\Feature;

/** Read-only comparison for built-in features; never asks Pennant to persist a resolution. */
final class LegacyFeaturePreview
{
    /** @param list<User|null> $users
     * @return array<string, bool>
     */
    public function values(string $key, array $users): array
    {
        $class = FeatureToggleRegistry::classForKey($key);
        abort_unless($class !== null, 404);
        $store = config('pennant.default', 'database');
        $config = config("pennant.stores.{$store}", []);
        abort_unless(($config['driver'] ?? null) === 'database', 422, 'Read-only legacy comparison requires the database Pennant store.');

        $scopes = array_map(fn (?User $user): string => Feature::serializeScope($user), $users);
        $stored = DB::connection($config['connection'] ?? null)->table($config['table'] ?? 'features')
            ->where('name', $class)->whereIn('scope', $scopes)->pluck('value', 'scope');
        $toggle = FeatureToggleRegistry::make($key);
        $values = [];
        foreach ($users as $index => $user) {
            $scope = $scopes[$index];
            // Preserve native Pennant rich-value semantics, including old JSON
            // envelopes. Correcting those here would understate adoption impact.
            $values[$scope] = $stored->has($scope)
                ? json_decode($stored[$scope], true, flags: JSON_THROW_ON_ERROR) !== false
                : ($user !== null && $toggle->resolve($user));
        }

        return $values;
    }
}
