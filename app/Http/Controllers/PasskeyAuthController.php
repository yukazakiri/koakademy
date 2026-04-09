<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Spatie\LaravelPasskeys\Actions\FindPasskeyToAuthenticateAction;
use Spatie\LaravelPasskeys\Support\Config;
use Spatie\LaravelPasskeys\Support\Serializer;
use Throwable;
use Webauthn\PublicKeyCredentialRequestOptions;

final class PasskeyAuthController extends Controller
{
    /**
     * Generate authentication options for passkey login.
     * If email is provided, generates options with allowCredentials for that user.
     * If no email, generates options for discoverable credentials (resident keys).
     */
    public function generateAuthenticationOptions(Request $request): JsonResponse
    {
        // Dynamic RP ID to match current domain
        config(['passkeys.relying_party.id' => $request->getHost()]);
        config(['app.url' => $request->getSchemeAndHttpHost()]);

        $email = $request->input('email');
        $allowCredentials = [];

        // If email is provided, look up user and their passkeys
        if ($email) {
            $user = User::where('email', $email)->first();

            if (! $user) {
                return response()->json(['error' => 'User not found.'], 404);
            }

            // Get user's passkeys for allowCredentials
            $passkeys = $user->passkeys ?? collect();
            foreach ($passkeys as $passkey) {
                $allowCredentials[] = [
                    'type' => 'public-key',
                    'id' => base64_encode((string) $passkey->credential_id),
                ];
            }

            $request->session()->put('passkey-authentication-user-id', $user->id);
        }

        // Generate options - empty allowCredentials enables discoverable credentials
        $options = new PublicKeyCredentialRequestOptions(
            challenge: Str::random(32),
            rpId: Config::getRelyingPartyId(),
            allowCredentials: $allowCredentials,
            userVerification: 'preferred',
        );

        $serializedOptions = Serializer::make()->toJson($options);

        $request->session()->put('passkey-authentication-options', $serializedOptions);

        return response()->json([
            'options' => json_decode($serializedOptions),
        ]);
    }

    /**
     * Verify the passkey authentication response and log the user in.
     */
    public function verifyAuthentication(Request $request): JsonResponse
    {
        $request->validate([
            'passkey' => 'required|string',
        ]);

        // Dynamic RP ID to match current domain
        config(['passkeys.relying_party.id' => $request->getHost()]);
        // Fix for FindPasskeyToAuthenticateAction usage of config('app.url')
        config(['app.url' => $request->getSchemeAndHttpHost()]);

        $passkey = $request->input('passkey');
        $options = $request->session()->pull('passkey-authentication-options');

        if (! $options) {
            return response()->json(['error' => 'Authentication options not found or expired.'], 400);
        }

        $findPasskeyAction = Config::getAction('find_passkey', FindPasskeyToAuthenticateAction::class);

        try {
            $passkeyModel = $findPasskeyAction->execute(
                $passkey,
                $options
            );

            if ($passkeyModel) {
                // Determine user from passkey
                $user = $passkeyModel->authenticatable;

                if (! $user) {
                    return response()->json(['error' => 'User associated with passkey not found.'], 404);
                }

                Auth::login($user);
                $request->session()->regenerate();

                $defaultRedirect = $user instanceof User && $user->isAdministrative()
                ? '/administrators'
                : '/dashboard';

                return response()->json(['url' => $defaultRedirect]);
            }

            return response()->json(['error' => 'Passkey verification failed.'], 400);
        } catch (Throwable $e) {
            return response()->json(['error' => 'Passkey verification failed: '.$e->getMessage()], 400);
        }
    }
}
