<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\User;
use App\Notifications\TwoFactorCode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Inertia\Inertia;
use PragmaRX\Google2FA\Google2FA;
use Spatie\LaravelPasskeys\Actions\FindPasskeyToAuthenticateAction;
use Spatie\LaravelPasskeys\Support\Config;
use Spatie\LaravelPasskeys\Support\Serializer;
use Throwable;
use Webauthn\PublicKeyCredentialRequestOptions;

final class TwoFactorChallengeController extends Controller
{
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
            'has_app_auth' => ! is_null($user->app_authentication_secret),
            'has_email_auth' => true, // Email code is always available as a fallback
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

        if ($request->filled('recovery_code')) {
            $recoveryCodes = $user->app_authentication_recovery_codes;

            if ($recoveryCodes && in_array($request->recovery_code, $recoveryCodes)) {
                $user->app_authentication_recovery_codes = array_diff($recoveryCodes, [$request->recovery_code]);
                $user->save();

                return $this->loginUser($request, $user);
            }

            return back()->withErrors(['recovery_code' => 'The provided recovery code was invalid.']);
        }

        $code = $request->code;

        // Try App Auth
        if ($user->app_authentication_secret) {
            $google2fa = new Google2FA();
            // verifyKey(secret, code, window?) window defaults to 4 (2 mins before/after)
            if ($google2fa->verifyKey($user->app_authentication_secret, $code)) {
                return $this->loginUser($request, $user);
            }
        }

        // Try Email Auth (always available as fallback)
        $cacheKey = '2fa_email_code_'.$user->id;
        $cachedCode = Cache::get($cacheKey);

        if ($cachedCode && $cachedCode === $code) {
            Cache::forget($cacheKey);

            return $this->loginUser($request, $user);
        }

        return back()->withErrors(['code' => 'The provided authentication code was invalid.']);
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

        $code = (string) random_int(100000, 999999);
        Cache::put('2fa_email_code_'.$user->id, $code, 300); // 5 minutes

        $user->notify(new TwoFactorCode($code));

        return back()->with('flash', ['success' => 'Code sent to your email.']);
    }

    /**
     * Generate WebAuthn authentication options for the user in the 2FA session.
     */
    public function passkeyOptions(Request $request): JsonResponse
    {
        if (! $request->session()->has('auth.2fa.id')) {
            return response()->json(['error' => 'No active two-factor session.'], 403);
        }

        $user = User::find($request->session()->get('auth.2fa.id'));

        if (! $user) {
            return response()->json(['error' => 'User not found.'], 404);
        }

        $passkeys = $user->passkeys;

        if ($passkeys->isEmpty()) {
            return response()->json(['error' => 'No passkeys registered.'], 404);
        }

        // Dynamic RP ID to match current domain
        config(['passkeys.relying_party.id' => $request->getHost()]);
        config(['app.url' => $request->getSchemeAndHttpHost()]);

        // Use discoverable credential flow (empty allowCredentials) to avoid
        // credential ID encoding issues. The passkey ownership is verified
        // server-side in passkeyVerify() after the ceremony completes.
        $options = new PublicKeyCredentialRequestOptions(
            challenge: Str::random(32),
            rpId: Config::getRelyingPartyId(),
            allowCredentials: [],
            userVerification: 'preferred',
        );

        $serializedOptions = Serializer::make()->toJson($options);
        $request->session()->put('passkey-authentication-options', $serializedOptions);

        return response()->json([
            'options' => json_decode($serializedOptions),
        ]);
    }

    /**
     * Verify a passkey assertion during the 2FA challenge and complete login.
     */
    public function passkeyVerify(Request $request): JsonResponse
    {
        $request->validate([
            'passkey' => 'required|string',
        ]);

        if (! $request->session()->has('auth.2fa.id')) {
            return response()->json(['error' => 'No active two-factor session.'], 403);
        }

        $user = User::find($request->session()->get('auth.2fa.id'));

        if (! $user) {
            return response()->json(['error' => 'User not found.'], 404);
        }

        // Dynamic RP ID to match current domain
        config(['passkeys.relying_party.id' => $request->getHost()]);
        config(['app.url' => $request->getSchemeAndHttpHost()]);

        $options = $request->session()->pull('passkey-authentication-options');

        if (! $options) {
            return response()->json(['error' => 'Authentication options not found or expired.'], 400);
        }

        $findPasskeyAction = Config::getAction('find_passkey', FindPasskeyToAuthenticateAction::class);

        try {
            $passkeyModel = $findPasskeyAction->execute(
                $request->input('passkey'),
                $options
            );

            if (! $passkeyModel || $passkeyModel->authenticatable_id !== $user->id) {
                return response()->json(['error' => 'Passkey verification failed.'], 400);
            }

            Auth::login($user, $request->session()->get('auth.2fa.remember', false));
            $request->session()->forget('auth.2fa.id');
            $request->session()->forget('auth.2fa.remember');
            $request->session()->regenerate();

            $defaultRedirect = $user->isAdministrative()
                ? '/administrators'
                : '/dashboard';

            return response()->json(['url' => $defaultRedirect]);
        } catch (Throwable $e) {
            return response()->json(['error' => 'Passkey verification failed: '.$e->getMessage()], 400);
        }
    }

    private function loginUser(Request $request, User $user)
    {
        Auth::login($user, $request->session()->get('auth.2fa.remember', false));
        $request->session()->forget('auth.2fa.id');
        $request->session()->forget('auth.2fa.remember');
        $request->session()->regenerate();

        $defaultRedirect = $user instanceof User && $user->isAdministrative()
                ? '/administrators'
                : '/dashboard';

        return redirect()->intended($defaultRedirect);
    }
}
