<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Filament\Auth\MultiFactor\SecurityAwareAppAuthentication;
use App\Filament\Auth\MultiFactor\SecurityAwareEmailAuthentication;
use App\Models\User;
use App\Services\Auth\TwoFactorAttemptService;
use App\Services\UserSecurityActivityService;
use App\Support\ConfiguresPasskeysForRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Laravel\Passkeys\Actions\GenerateVerificationOptions;
use Laravel\Passkeys\Actions\VerifyPasskey;
use Laravel\Passkeys\Support\WebAuthn;
use Throwable;
use Webauthn\PublicKeyCredential;
use Webauthn\PublicKeyCredentialRequestOptions;

final class TwoFactorChallengeController extends Controller
{
    use ConfiguresPasskeysForRequest;

    private const string VERIFICATION_OPTIONS_SESSION_KEY = 'passkey.two_factor_verification_options';

    public function __construct(
        private readonly SecurityAwareAppAuthentication $appAuthentication,
        private readonly SecurityAwareEmailAuthentication $emailAuthentication,
        private readonly TwoFactorAttemptService $attempts,
    ) {}

    public function create(Request $request)
    {
        if (! $request->session()->has('auth.2fa.id')) {
            return redirect()->route('login');
        }

        $user = User::find($request->session()->get('auth.2fa.id'));

        if (! $user) {
            return redirect()->route('login');
        }

        return Inertia::render('auth/two-factor-challenge', [
            'has_app_auth' => $this->appAuthentication->isEnabled($user),
            'has_email_auth' => $this->emailAuthentication->isEnabled($user),
            'has_passkeys' => $user->passkeys()->exists(),
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'code' => 'nullable|string',
            'recovery_code' => 'nullable|string',
        ]);

        if (! $request->session()->has('auth.2fa.id')) {
            return redirect()->route('login');
        }

        $user = User::find($request->session()->get('auth.2fa.id'));

        if (! $user) {
            return redirect()->route('login');
        }

        $challenge = $this->challengeKey($user);

        // A six digit code has a million possible values, so the challenge
        // itself has to be the thing that is bounded.
        if ($this->attempts->tooManyAttempts($challenge)) {
            return $this->destroyChallenge($request);
        }

        if ($request->filled('recovery_code')) {
            if ($this->appAuthentication->isEnabled($user) && $this->verifyRecoveryCode($user, $request->recovery_code)) {
                $this->attempts->recordSuccess($challenge);

                return $this->loginUser($request, $user);
            }

            app(UserSecurityActivityService::class)->record($user, 'mfa_failed', 'failure');

            return $this->failedAttempt($request, $challenge, 'recovery_code', 'The provided recovery code was invalid.');
        }

        $code = $request->code;

        if (blank($code)) {
            return back()->withErrors(['code' => 'The authentication code is required.']);
        }

        if ($this->appAuthentication->isEnabled($user)) {
            $secret = $user->getAppAuthenticationSecret();

            if (filled($secret) && $this->appAuthentication->verifyCode($code, $secret, shouldPreventCodeReuse: true)) {
                $this->attempts->recordSuccess($challenge);

                return $this->loginUser($request, $user);
            }
        }

        if ($this->emailAuthentication->isEnabled($user) && $this->emailAuthentication->verifyCode($code)) {
            $this->attempts->recordSuccess($challenge);

            return $this->loginUser($request, $user);
        }

        app(UserSecurityActivityService::class)->record($user, 'mfa_failed', 'failure');

        return $this->failedAttempt($request, $challenge, 'code', 'The provided authentication code was invalid.');
    }

    public function sendEmailCode(Request $request)
    {
        if (! $request->session()->has('auth.2fa.id')) {
            return redirect()->route('login');
        }
        $user = User::find($request->session()->get('auth.2fa.id'));

        if (! $user) {
            return redirect()->route('login');
        }

        if (! $this->emailAuthentication->isEnabled($user)) {
            return back()->withErrors(['code' => 'Email authentication is not enabled for this account.']);
        }

        if (! $this->emailAuthentication->sendCode($user)) {
            return back()->withErrors(['code' => 'Please wait before requesting another email code.']);
        }

        return back()->with('flash', ['success' => 'Code sent to your email.']);
    }

    public function passkeyOptions(Request $request, GenerateVerificationOptions $generate): JsonResponse
    {
        if (! $request->session()->has('auth.2fa.id')) {
            return response()->json(['error' => 'No active two-factor session.'], 403);
        }

        $user = User::find($request->session()->get('auth.2fa.id'));

        if (! $user) {
            return response()->json(['error' => 'User not found.'], 404);
        }

        if (! $user->passkeys()->exists()) {
            return response()->json(['error' => 'No passkeys registered.'], 404);
        }

        $this->configurePasskeysForRequest($request);

        $options = $generate($user);

        $request->session()->put(self::VERIFICATION_OPTIONS_SESSION_KEY, WebAuthn::toJson($options));

        return response()->json([
            'options' => json_decode(WebAuthn::toJson($options), true),
        ]);
    }

    public function passkeyVerify(Request $request, VerifyPasskey $verify): JsonResponse
    {
        $request->validate([
            'passkey' => ['required_without:credential', 'string'],
            'credential' => ['nullable', 'array'],
        ]);

        if (! $request->session()->has('auth.2fa.id')) {
            return response()->json(['error' => 'No active two-factor session.'], 403);
        }

        $user = User::find($request->session()->get('auth.2fa.id'));

        if (! $user) {
            return response()->json(['error' => 'User not found.'], 404);
        }

        $this->configurePasskeysForRequest($request);

        $serializedOptions = $request->session()->pull(self::VERIFICATION_OPTIONS_SESSION_KEY);

        if (! is_string($serializedOptions) || $serializedOptions === '') {
            return response()->json(['error' => 'Authentication options not found or expired.'], 400);
        }

        try {
            $verify(
                $this->credentialFromRequest($request),
                WebAuthn::fromJson($serializedOptions, PublicKeyCredentialRequestOptions::class),
                $user,
            );

            app(UserSecurityActivityService::class)->record($user, 'mfa_passed', 'success');
            Auth::login($user, $request->session()->get('auth.2fa.remember', false));
            $request->session()->forget('auth.2fa.id');
            $request->session()->forget('auth.2fa.remember');
            $request->session()->regenerate();
            $request->session()->save();

            $defaultRedirect = $this->getRedirectForUser($user);

            return response()->json(['url' => $defaultRedirect, 'redirect' => $defaultRedirect]);
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            return response()->json(['error' => 'Passkey verification failed: '.$exception->getMessage()], 400);
        }
    }

    /**
     * Record a wrong code and end the challenge once the attempts run out.
     */
    private function failedAttempt(Request $request, string $challenge, string $field, string $message): RedirectResponse
    {
        $this->attempts->recordFailure($challenge);

        if ($this->attempts->tooManyAttempts($challenge)) {
            return $this->destroyChallenge($request);
        }

        return back()->withErrors([$field => $message]);
    }

    /**
     * End a challenge that used up its attempts.
     */
    private function destroyChallenge(Request $request): RedirectResponse
    {
        $request->session()->forget('auth.2fa.id');
        $request->session()->forget('auth.2fa.remember');
        $request->session()->regenerate();

        return redirect()->route('login')->withErrors([
            'code' => 'Too many incorrect verification attempts. Please sign in again.',
        ]);
    }

    /**
     * The counter key for a challenge.
     *
     * The counter follows the account rather than the challenge, because a
     * new challenge can be started as soon as the password is known. Keying
     * it on the session would hand the attacker a fresh set of guesses every
     * time they started a challenge.
     */
    private function challengeKey(User $user): string
    {
        return (string) $user->getKey();
    }

    private function verifyRecoveryCode(User $user, string $recoveryCode): bool
    {
        try {
            if ($this->appAuthentication->verifyRecoveryCode($recoveryCode, $user)) {
                return true;
            }
        } catch (Throwable) {
            // Fall through to legacy recovery code handling below.
        }

        $recoveryCodes = $user->app_authentication_recovery_codes;

        if (! is_array($recoveryCodes) || ! in_array($recoveryCode, $recoveryCodes, true)) {
            return false;
        }

        $user->app_authentication_recovery_codes = array_values(array_diff($recoveryCodes, [$recoveryCode]));
        $user->save();

        return true;
    }

    private function loginUser(Request $request, User $user)
    {
        app(UserSecurityActivityService::class)->record($user, 'mfa_passed', 'success');
        Auth::login($user, $request->session()->get('auth.2fa.remember', false));
        $request->session()->forget('auth.2fa.id');
        $request->session()->forget('auth.2fa.remember');
        $request->session()->regenerate();

        $defaultRedirect = $this->getRedirectForUser($user);

        return redirect()->intended($defaultRedirect);
    }

    private function getRedirectForUser(User $user): string
    {
        return $user->isAdministrative()
            ? '/administrators'
            : '/dashboard';
    }

    private function credentialFromRequest(Request $request): PublicKeyCredential
    {
        $credential = $request->input('credential');

        if (! is_array($credential) && is_string($request->input('passkey'))) {
            $credential = json_decode($request->input('passkey'), true);
        }

        if (! is_array($credential)) {
            throw ValidationException::withMessages([
                'credential' => __('Invalid credential format.'),
            ]);
        }

        try {
            return WebAuthn::fromJson(
                json_encode($credential, JSON_THROW_ON_ERROR),
                PublicKeyCredential::class,
            );
        } catch (Throwable) {
            throw ValidationException::withMessages([
                'credential' => __('Invalid credential format.'),
            ]);
        }
    }
}
