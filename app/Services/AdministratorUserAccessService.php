<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Jenssegers\Agent\Agent;

final class AdministratorUserAccessService
{
    /** @return array{supported: bool, data: array} */
    public function sessions(User $user): array
    {
        if (config('session.driver') !== 'database') {
            return ['supported' => false, 'data' => []];
        }
        $rows = $this->sessionQuery($user)->where('last_activity', '>=', now()->subMinutes((int) config('session.lifetime'))->timestamp)
            ->orderByDesc('last_activity')->limit(100)->get(['id', 'user_agent', 'last_activity']);

        return ['supported' => true, 'data' => $rows->map(function ($row): array {
            $agent = new Agent;
            $agent->setUserAgent((string) $row->user_agent);

            return [
                // Session IDs are bearer secrets; expose only an encrypted action reference.
                'reference' => Crypt::encryptString($row->id),
                'browser' => $agent->browser() ?: 'Unknown',
                'platform' => $agent->platform() ?: 'Unknown',
                'last_active_at' => \Illuminate\Support\Carbon::createFromTimestamp($row->last_activity)->toIso8601String(),
            ];
        })->all()];
    }

    public function revokeSession(User $user, string $reference): void
    {
        abort_unless(config('session.driver') === 'database', 409, 'Individual session revocation requires database sessions.');
        try {
            $id = Crypt::decryptString($reference);
        } catch (\Illuminate\Contracts\Encryption\DecryptException) {
            abort(404);
        }
        abort_unless($this->sessionQuery($user)->where('id', $id)->delete() > 0, 404);
        // Prevent the removed session's remember-me cookie from restoring access.
        $user->forceFill(['remember_token' => Str::random(60)])->save();
    }

    public function revokeAll(User $user): void
    {
        $user->getConnection()->transaction(function () use ($user): void {
            if ($user->getConnection()->getDriverName() === 'sqlite') {
                $user->getConnection()->table($user->getTable())->where('id', -1)->update(['session_version' => 0]);
            }
            $locked = User::query()->whereKey($user->getKey())->lockForUpdate()->firstOrFail();
            $locked->forceFill(['session_version' => $locked->session_version + 1, 'remember_token' => Str::random(60)])->save();
            $locked->tokens()->delete();
            $locked->revokeMcpOAuthTokens();
            if (config('session.driver') === 'database') {
                $this->sessionQuery($locked)->delete();
            }
        });
    }

    private function sessionQuery(User $user): \Illuminate\Database\Query\Builder
    {
        return DB::connection(config('session.connection'))->table(config('session.table', 'sessions'))->where('user_id', $user->id);
    }
}
