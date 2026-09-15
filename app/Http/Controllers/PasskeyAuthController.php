<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\ConfiguresPasskeysForRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Laravel\Passkeys\Actions\GenerateVerificationOptions;
use Laravel\Passkeys\Actions\VerifyPasskey;
use Laravel\Passkeys\Support\WebAuthn;
use Throwable;
use Webauthn\PublicKeyCredential;
use Webauthn\PublicKeyCredentialRequestOptions;

final class PasskeyAuthController extends Controller
{
    use ConfiguresPasskeysForRequest;

    private const string VERIFICATION_OPTIONS_SESSION_KEY = 'passkey.verification_options';

    public function generateAuthenticationOptions(Request $request, GenerateVerificationOptions $generate): JsonResponse
    {
        $this->configurePasskeysForRequest($request);

        $email = $request->input('email');
        $user = null;

        if ($email) {
            $user = User::where('email', $email)->first();

            if (! $user) {
                return response()->json(['error' => 'User not found.'], 404);
            }

            $request->session()->put('passkey-authentication-user-id', $user->id);
        }

        $options = $generate($user);

        $request->session()->put(self::VERIFICATION_OPTIONS_SESSION_KEY, WebAuthn::toJson($options));

        return response()->json([
            'options' => json_decode(WebAuthn::toJson($options), true),
        ]);
    }

    public function verifyAuthentication(Request $request, VerifyPasskey $verify): JsonResponse
    {
        $request->validate([
            'passkey' => ['required_without:credential', 'string'],
            'credential' => ['nullable', 'array'],
        ]);

        $this->configurePasskeysForRequest($request);

        $serializedOptions = $request->session()->pull(self::VERIFICATION_OPTIONS_SESSION_KEY);

        if (! is_string($serializedOptions) || $serializedOptions === '') {
            return response()->json(['error' => 'Authentication options not found or expired.'], 400);
        }

        try {
            $passkey = $verify(
                $this->credentialFromRequest($request),
                WebAuthn::fromJson($serializedOptions, PublicKeyCredentialRequestOptions::class),
            );

            $user = $passkey->user;

            if (! $user instanceof User) {
                return response()->json(['error' => 'User associated with passkey not found.'], 404);
            }

            Auth::login($user);
            $request->session()->regenerate();

            $defaultRedirect = $this->getRedirectForUser($user);

            return response()->json(['url' => $defaultRedirect, 'redirect' => $defaultRedirect]);
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            return response()->json(['error' => 'Passkey verification failed: '.$exception->getMessage()], 400);
        }
    }

    private function getRedirectForUser(User $user): string
    {
        if ($user->isAdministrative()) {
            return '/administrators/dashboard';
        }

        if ($user->role?->isStudent()) {
            return '/student/dashboard';
        }

        return '/faculty/dashboard';
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
