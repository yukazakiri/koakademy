<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Models\ConnectedAccount;
use App\Models\User;
use App\Services\SocialiteProviderService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Laravel\Socialite\Contracts\User as SocialiteUser;
use Throwable;

final class PendingSocialSignupService
{
    public const string SESSION_KEY = 'socialite_signup';

    public const int TTL_MINUTES = 15;

    public function __construct(
        private readonly SocialiteProviderService $providers,
    ) {}

    /**
     * Store a pending OAuth signup payload in the user's session.
     *
     * @return array<string, mixed>
     */
    public function store(
        Request $request,
        string $provider,
        SocialiteUser $socialUser,
        string $normalizedEmail,
        bool $emailVerified,
        ?Carbon $expiresAt = null,
    ): array {
        $payload = [
            'provider' => $provider,
            'provider_id' => (string) $socialUser->getId(),
            'name' => $socialUser->getName(),
            'nickname' => $socialUser->getNickname(),
            'email' => $normalizedEmail,
            'email_verified' => $emailVerified,
            'avatar_url' => $socialUser->getAvatar(),
            'token' => $socialUser->token ?? '',
            'secret' => $socialUser->tokenSecret ?? null,
            'refresh_token' => $socialUser->refreshToken ?? null,
            'expires_at' => $expiresAt?->toIso8601String(),
            'created_at' => now()->toIso8601String(),
            'link_on_login' => false,
        ];

        $request->session()->put(self::SESSION_KEY, $payload);

        return $payload;
    }

    /**
     * Retrieve the active pending social signup payload, automatically clearing expired entries.
     *
     * @return array<string, mixed>|null
     */
    public function get(Request $request): ?array
    {
        $pending = $request->session()->get(self::SESSION_KEY);

        if (! is_array($pending)) {
            return null;
        }

        $provider = (string) ($pending['provider'] ?? '');
        $providerId = (string) ($pending['provider_id'] ?? '');

        if (! $this->providers->isSupported($provider) || $providerId === '') {
            $this->clear($request);

            return null;
        }

        $createdAt = $pending['created_at'] ?? null;

        if (is_string($createdAt) && $createdAt !== '') {
            try {
                if (Carbon::parse($createdAt)->addMinutes(self::TTL_MINUTES)->isPast()) {
                    $this->clear($request);

                    return null;
                }
            } catch (Throwable) {
                $this->clear($request);

                return null;
            }
        }

        return $pending;
    }

    public function hasActive(Request $request): bool
    {
        return $this->get($request) !== null;
    }

    /**
     * Return only privacy-safe metadata for Inertia props — never expose OAuth tokens to the browser.
     *
     * @return array{
     *     provider: string,
     *     name: string|null,
     *     email: string|null,
     *     avatar_url: string|null,
     *     email_verified: bool,
     *     link_on_login: bool
     * }|null
     */
    public function frontendPayload(Request $request): ?array
    {
        $pending = $this->get($request);

        if ($pending === null) {
            return null;
        }

        $email = isset($pending['email']) && is_string($pending['email']) && mb_trim($pending['email']) !== ''
            ? mb_strtolower(mb_trim($pending['email']))
            : null;

        return [
            'provider' => (string) $pending['provider'],
            'name' => is_string($pending['name'] ?? null) ? $pending['name'] : null,
            'email' => $email,
            'avatar_url' => is_string($pending['avatar_url'] ?? null) && $pending['avatar_url'] !== '' ? $pending['avatar_url'] : null,
            'email_verified' => (bool) ($pending['email_verified'] ?? false),
            'link_on_login' => (bool) ($pending['link_on_login'] ?? false),
        ];
    }

    /**
     * Return the sanitized pending social payload when the user opted to sign in to an existing account to link it.
     *
     * @return array{
     *     provider: string,
     *     name: string|null,
     *     email: string|null,
     *     avatar_url: string|null,
     *     email_verified: bool,
     *     link_on_login: bool
     * }|null
     */
    public function pendingLoginLinkPayload(Request $request): ?array
    {
        $payload = $this->frontendPayload($request);

        if ($payload === null || ! $payload['link_on_login']) {
            return null;
        }

        return $payload;
    }

    /**
     * Mark the active pending social signup as ready to auto-link after the user signs into an existing account.
     */
    public function enableLoginLink(Request $request): bool
    {
        $pending = $this->get($request);

        if ($pending === null) {
            return false;
        }

        $pending['link_on_login'] = true;
        $request->session()->put(self::SESSION_KEY, $pending);

        return true;
    }

    public function clear(Request $request): void
    {
        $request->session()->forget(self::SESSION_KEY);
    }

    /**
     * Allow skipping the 6-digit email OTP ONLY when the OAuth provider cryptographically verified
     * the exact email address being registered in this active, unexpired session.
     */
    public function canSkipEmailOtp(Request $request, string $submittedEmail): bool
    {
        $pending = $this->get($request);

        if ($pending === null || ! (bool) ($pending['email_verified'] ?? false)) {
            return false;
        }

        $pendingEmail = mb_strtolower(mb_trim((string) ($pending['email'] ?? '')));

        return $pendingEmail !== '' && $pendingEmail === mb_strtolower(mb_trim($submittedEmail));
    }

    public function pendingAvatar(Request $request, string $email, bool $allowDifferentEmail = true): ?string
    {
        $pending = $this->get($request);

        if ($pending === null) {
            return null;
        }

        if (! $allowDifferentEmail) {
            $pendingEmail = mb_strtolower(mb_trim((string) ($pending['email'] ?? '')));

            if ($pendingEmail !== mb_strtolower(mb_trim($email))) {
                return null;
            }
        }

        $avatar = $pending['avatar_url'] ?? null;

        return is_string($avatar) && $avatar !== '' ? $avatar : null;
    }

    /**
     * Link the pending OAuth account to the newly activated or freshly authenticated user.
     *
     * Returns the linked provider key (e.g. 'google') on success, or null if skipped/rejected.
     */
    public function linkToUser(Request $request, User $user, bool $allowDifferentEmail = true): ?string
    {
        $pending = $this->get($request);

        if ($pending === null) {
            return null;
        }

        if (! $allowDifferentEmail) {
            $pendingEmail = mb_strtolower(mb_trim((string) ($pending['email'] ?? '')));

            if ($pendingEmail !== mb_strtolower(mb_trim((string) $user->email))) {
                return null;
            }
        }

        $provider = (string) $pending['provider'];
        $providerId = (string) $pending['provider_id'];

        $existingAccount = ConnectedAccount::query()
            ->where('provider', $provider)
            ->where('provider_id', $providerId)
            ->first();

        if ($existingAccount instanceof ConnectedAccount && (int) $existingAccount->user_id !== (int) $user->id) {
            $this->clear($request);

            return null;
        }

        ConnectedAccount::query()->updateOrCreate(
            [
                'provider' => $provider,
                'provider_id' => $providerId,
            ],
            [
                'user_id' => $user->id,
                'name' => $pending['name'] ?? $user->name,
                'nickname' => $pending['nickname'] ?? null,
                'email' => $pending['email'] ?? $user->email,
                'avatar_path' => $pending['avatar_url'] ?? null,
                'token' => (string) ($pending['token'] ?? ''),
                'secret' => $pending['secret'] ?? null,
                'refresh_token' => $pending['refresh_token'] ?? null,
                'expires_at' => ! empty($pending['expires_at']) ? Carbon::parse((string) $pending['expires_at']) : null,
            ],
        );

        $avatar = $pending['avatar_url'] ?? null;
        if (blank($user->avatar_url) && is_string($avatar) && $avatar !== '') {
            $user->forceFill(['avatar_url' => $avatar])->save();
        }

        $this->clear($request);

        return $provider;
    }
}
