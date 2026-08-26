<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\ConnectedAccount;
use App\Models\User;
use App\Services\SocialiteProviderService;
use App\Services\StudentOrganizationAssignmentService;
use Exception;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Laravel\Socialite\Contracts\User as SocialiteUser;
use Laravel\Socialite\Facades\Socialite;

final class SocialAuthController extends Controller
{
    public function __construct(
        private readonly SocialiteProviderService $socialiteProviders,
        private readonly StudentOrganizationAssignmentService $studentOrganizations,
    ) {}

    public function redirect(string $provider): RedirectResponse
    {
        abort_unless($this->socialiteProviders->isEnabled($provider), 404);

        return $this->driver($provider)->redirect();
    }

    public function callback(Request $request, string $provider): RedirectResponse
    {
        abort_unless($this->socialiteProviders->isEnabled($provider), 404);

        try {
            $socialUser = $this->driver($provider)->user();
            $email = $socialUser->getEmail();

            if (! filled($email)) {
                return redirect('/login')->withErrors([
                    'email' => ucfirst($provider).' did not provide an email address.',
                ]);
            }

            $email = mb_strtolower(mb_trim($email));

            $connectedAccount = ConnectedAccount::query()
                ->where('provider', $provider)
                ->where('provider_id', $socialUser->getId())
                ->first();

            if ($connectedAccount instanceof ConnectedAccount) {
                $user = $connectedAccount->user;

                if (! $user instanceof User) {
                    return redirect('/login')->withErrors([
                        'email' => 'This social account is no longer linked to a user.',
                    ]);
                }

                $this->storeConnectedAccount($user, $provider, $socialUser);
                if ($provider === 'google') {
                    $this->syncAvatar($user, $socialUser);
                }
                $this->studentOrganizations->reconcileExistingStudent($user);
                $this->login($request, $user);

                return redirect()->intended($this->redirectForUser($user));
            }

            $user = User::query()
                ->whereRaw('LOWER(email) = ?', [$email])
                ->first();

            if ($user instanceof User) {
                $this->storeConnectedAccount($user, $provider, $socialUser);
                $this->syncAvatar($user, $socialUser);
                $this->studentOrganizations->reconcileExistingStudent($user);
                $this->login($request, $user);

                return redirect()
                    ->intended($this->redirectForUser($user))
                    ->with('status', ucfirst($provider).' is now linked. You can use it to sign in next time.');
            }

            $request->session()->put('socialite_signup', [
                'provider' => $provider,
                'provider_id' => $socialUser->getId(),
                'name' => $socialUser->getName(),
                'nickname' => $socialUser->getNickname(),
                'email' => $email,
                'avatar_url' => $socialUser->getAvatar(),
                'token' => $socialUser->token ?? '',
                'secret' => $socialUser->tokenSecret ?? null,
                'refresh_token' => $socialUser->refreshToken ?? null,
                'expires_at' => $this->expiresAt($socialUser)?->toIso8601String(),
            ]);

            return redirect('/signup')->with('status', 'Finish creating your account to link '.ucfirst($provider).'.');
        } catch (Exception $e) {
            Log::error(ucfirst($provider).' OAuth login error: '.$e->getMessage());

            return redirect('/login')->withErrors([
                'email' => 'Failed to sign in with '.ucfirst($provider).'.',
            ]);
        }
    }

    public function connect(string $provider): RedirectResponse
    {
        abort_unless($this->socialiteProviders->isEnabled($provider), 404);

        return $this->driver($provider, true)->redirect();
    }

    public function connectCallback(string $provider): RedirectResponse
    {
        abort_unless($this->socialiteProviders->isEnabled($provider), 404);

        try {
            $socialUser = $this->driver($provider, true)->user();
            $user = Auth::user();

            abort_unless($user instanceof User, 403);

            $existingAccount = ConnectedAccount::query()
                ->where('provider', $provider)
                ->where('provider_id', $socialUser->getId())
                ->first();

            if ($existingAccount instanceof ConnectedAccount && (int) $existingAccount->user_id !== (int) $user->id) {
                return redirect('/profile')->withErrors([
                    'socialite' => 'That '.ucfirst($provider).' account is already linked to another user.',
                ]);
            }

            $this->storeConnectedAccount($user, $provider, $socialUser);

            if ($provider === 'google') {
                $this->syncAvatar($user, $socialUser);
            }

            return redirect('/profile')->with('status', ucfirst($provider).' connected successfully.');
        } catch (Exception $e) {
            Log::error(ucfirst($provider).' OAuth connect error: '.$e->getMessage());

            return redirect('/profile')->withErrors([
                'socialite' => 'Failed to connect '.ucfirst($provider).'.',
            ]);
        }
    }

    public function disconnect(Request $request, string $provider): RedirectResponse
    {
        $user = Auth::user();
        abort_unless($user instanceof User, 403);

        $query = ConnectedAccount::query()
            ->where('user_id', $user->id)
            ->where('provider', $provider);

        if ($provider === 'google' && $request->filled('account_id')) {
            $query->whereKey($request->integer('account_id'));
        }

        $query->delete();

        return redirect('/profile')->with('status', ucfirst($provider).' disconnected.');
    }

    public function storeConnectedAccount(User $user, string $provider, SocialiteUser $socialUser): ConnectedAccount
    {
        return ConnectedAccount::query()->updateOrCreate(
            [
                'provider' => $provider,
                'provider_id' => (string) $socialUser->getId(),
            ],
            [
                'user_id' => $user->id,
                'name' => $socialUser->getName(),
                'nickname' => $socialUser->getNickname(),
                'email' => $socialUser->getEmail(),
                'avatar_path' => $socialUser->getAvatar(),
                'token' => (string) ($socialUser->token ?? ''),
                'secret' => $socialUser->tokenSecret ?? null,
                'refresh_token' => $socialUser->refreshToken ?? null,
                'expires_at' => $this->expiresAt($socialUser),
            ]
        );
    }

    private function driver(string $provider, bool $forConnection = false): mixed
    {
        $this->socialiteProviders->applyRuntimeConfig(
            $provider,
            $forConnection ? url("/integrations/{$provider}/callback") : null,
        );

        $driver = Socialite::driver($provider);

        if ($provider === 'google') {
            $driver->with(['prompt' => 'select_account']);

            if ($forConnection) {
                $driver
                    ->scopes(['https://www.googleapis.com/auth/calendar'])
                    ->with(['access_type' => 'offline', 'prompt' => 'consent select_account']);
            }
        }

        return $driver;
    }

    private function syncAvatar(User $user, SocialiteUser $socialUser): void
    {
        $avatar = $socialUser->getAvatar();

        if (filled($avatar)) {
            $user->forceFill(['avatar_url' => $avatar])->save();
        }
    }

    private function login(Request $request, User $user): void
    {
        Auth::login($user);
        $request->session()->regenerate();
    }

    private function redirectForUser(User $user): string
    {
        if ($user->isAdministrative()) {
            return '/administrators';
        }

        if ($user->role === UserRole::User || $user->role?->isStudent()) {
            return $user->role?->isStudent() ? '/student/dashboard' : '/dashboard';
        }

        return '/faculty/dashboard';
    }

    private function expiresAt(SocialiteUser $socialUser): ?\Illuminate\Support\Carbon
    {
        $expiresIn = $socialUser->expiresIn ?? null;

        return is_numeric($expiresIn) ? now()->addSeconds((int) $expiresIn) : null;
    }
}
