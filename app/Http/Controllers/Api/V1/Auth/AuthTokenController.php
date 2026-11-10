<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Filament\Auth\MultiFactor\SecurityAwareAppAuthentication;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\MobileChallengeRequest;
use App\Http\Requests\Api\V1\MobileForgotPasswordRequest;
use App\Http\Requests\Api\V1\MobileLoginRequest;
use App\Http\Requests\Api\V1\MobileResetPasswordRequest;
use App\Http\Requests\Api\V1\UpdateMobilePasswordRequest;
use App\Http\Resources\Api\V1\UserResource;
use App\Models\User;
use App\Notifications\FilamentEmailAuthenticationCode;
use App\Services\OnlineUserPresenceService;
use App\Services\UserSecurityActivityService;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Laravel\Sanctum\PersonalAccessToken;

final class AuthTokenController extends Controller
{
    private const string CHALLENGE_PREFIX = 'mobile-api-auth-challenge:';

    public function __construct(private readonly SecurityAwareAppAuthentication $appAuthentication) {}

    public function login(MobileLoginRequest $request): JsonResponse
    {
        $user = User::query()->where('email', mb_strtolower((string) $request->validated('email')))->first();

        if (! $user instanceof User || ! Hash::check((string) $request->validated('password'), $user->password)) {
            if ($user instanceof User) {
                app(UserSecurityActivityService::class)->record($user, 'sign_in_failed', 'failure', guard: 'sanctum');
            }

            return response()->json([
                'error' => true,
                'message' => 'The provided credentials are invalid.',
                'code' => 'AUTHENTICATION_FAILED',
            ], 401);
        }

        abort_if($user->suspended_at !== null, 403, 'Account access is unavailable.');

        if (! $user->isStudentRole() && ! $user->isFaculty()) {
            return response()->json([
                'error' => true,
                'message' => 'This mobile application is available to Student and Faculty accounts only.',
                'code' => 'MOBILE_ROLE_UNSUPPORTED',
            ], 403);
        }

        if ($user->requiresTwoFactorChallenge()) {
            $challengeId = Str::random(64);
            Cache::put(self::CHALLENGE_PREFIX.$challengeId, [
                'user_id' => $user->id,
                'device_name' => $request->validated('device_name'),
            ], now()->addMinutes(5));

            $methods = [];
            if ($this->appAuthentication->isEnabled($user)) {
                $methods[] = 'authenticator';
                $methods[] = 'recovery_code';
            }
            if ($user->hasEmailAuthentication()) {
                $methods[] = 'email';
            }

            return response()->json([
                'message' => 'Additional authentication is required.',
                'data' => [
                    'challenge_id' => $challengeId,
                    'methods' => array_values(array_unique($methods)),
                    'expires_at' => now()->addMinutes(5)->toIso8601String(),
                ],
            ], 202);
        }

        return $this->tokenResponse($user, (string) $request->validated('device_name'));
    }

    public function sendEmailChallenge(MobileChallengeRequest $request): JsonResponse
    {
        $challenge = $this->challenge($request->validated('challenge_id'));
        $user = User::query()->find($challenge['user_id']);

        abort_unless($user instanceof User && $user->hasEmailAuthentication(), 404, 'Authentication challenge not found.');

        $code = mb_str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        Cache::put(self::CHALLENGE_PREFIX.$request->validated('challenge_id').':code', Hash::make($code), now()->addMinutes(4));
        $user->notify(new FilamentEmailAuthenticationCode($code, 4));

        return response()->json(['message' => 'An authentication code was sent to the account email address.']);
    }

    public function verifyChallenge(MobileChallengeRequest $request): JsonResponse
    {
        $challengeId = (string) $request->validated('challenge_id');
        $challenge = $this->challenge($challengeId);
        $user = User::query()->find($challenge['user_id']);
        abort_unless($user instanceof User, 404, 'Authentication challenge not found.');

        $verified = false;
        $recoveryCode = $request->validated('recovery_code');
        $code = $request->validated('code');

        if (is_string($recoveryCode) && $recoveryCode !== '') {
            $verified = $this->appAuthentication->verifyRecoveryCode($recoveryCode, $user);
        } elseif (is_string($code) && $code !== '') {
            if ($this->appAuthentication->isEnabled($user)) {
                $verified = $this->appAuthentication->verifyCode($code, $user->getAppAuthenticationSecret(), true);
            }

            if (! $verified) {
                $hash = Cache::get(self::CHALLENGE_PREFIX.$challengeId.':code');
                $verified = is_string($hash) && Hash::check($code, $hash);
            }
        }

        if (! $verified) {
            app(UserSecurityActivityService::class)->record($user, 'mfa_failed', 'failure', guard: 'sanctum');

            return response()->json([
                'error' => true,
                'message' => 'The authentication code is invalid or expired.',
                'code' => 'CHALLENGE_FAILED',
            ], 422);
        }

        app(UserSecurityActivityService::class)->record($user, 'mfa_passed', 'success', guard: 'sanctum');
        Cache::forget(self::CHALLENGE_PREFIX.$challengeId);
        Cache::forget(self::CHALLENGE_PREFIX.$challengeId.':code');

        return $this->tokenResponse($user, (string) ($request->validated('device_name') ?? $challenge['device_name']));
    }

    public function me(): JsonResponse
    {
        return response()->json(['data' => new UserResource(request()->user())]);
    }

    /**
     * Revoke the personal access token for the current authenticated device.
     */
    public function logout(): JsonResponse
    {
        $token = request()->user()?->currentAccessToken();
        abort_unless($token instanceof PersonalAccessToken, 403, 'A personal access token is required.');
        $token->delete();
        app(UserSecurityActivityService::class)->record(request()->user(), 'signed_out', 'success', guard: 'sanctum');

        return response()->json(['message' => 'The device was signed out.']);
    }

    public function logoutAll(): JsonResponse
    {
        request()->user()?->tokens()->delete();

        return response()->json(['message' => 'All mobile devices were signed out.']);
    }

    public function tokens(): JsonResponse
    {
        $tokens = request()->user()->tokens()->latest('id')->get()->map(fn ($token): array => [
            'id' => (int) $token->id,
            'name' => $token->name,
            'abilities' => $token->abilities,
            'last_used_at' => $token->last_used_at?->toIso8601String(),
            'expires_at' => $token->expires_at?->toIso8601String(),
            'created_at' => $token->created_at?->toIso8601String(),
        ]);

        return response()->json(['data' => $tokens]);
    }

    public function revokeToken(int $token): JsonResponse
    {
        $deleted = request()->user()->tokens()->whereKey($token)->delete();
        abort_unless($deleted > 0, 404, 'Token not found.');

        return response()->json(['message' => 'The device token was revoked.']);
    }

    public function forgotPassword(MobileForgotPasswordRequest $request): JsonResponse
    {
        Password::broker()->sendResetLink(['email' => $request->validated('email')]);

        return response()->json(['message' => 'If the account exists, a password reset link has been sent.']);
    }

    public function resetPassword(MobileResetPasswordRequest $request): JsonResponse
    {
        $status = Password::broker()->reset(
            $request->validated(),
            function (User $user, string $password): void {
                $user->forceFill(['password' => Hash::make($password)])->save();
                $user->tokens()->delete();
                event(new PasswordReset($user));
            },
        );

        if ($status !== Password::PASSWORD_RESET) {
            return response()->json([
                'error' => true,
                'message' => __($status),
                'code' => 'PASSWORD_RESET_FAILED',
            ], 422);
        }

        return response()->json(['message' => 'The password has been reset.']);
    }

    /**
     * Update account password and revoke other mobile access tokens.
     */
    public function updatePassword(UpdateMobilePasswordRequest $request): JsonResponse
    {
        $user = $request->user();
        $token = $user->currentAccessToken();
        $user->forceFill(['password' => Hash::make((string) $request->validated('password'))])->save();
        $tokens = $user->tokens();
        if ($token instanceof PersonalAccessToken) {
            $tokens->whereKeyNot($token->getKey());
        }
        $tokens->delete();

        return response()->json(['message' => 'The password has been updated.']);
    }

    /** @return array<string, mixed> */
    private function challenge(string $challengeId): array
    {
        $challenge = Cache::get(self::CHALLENGE_PREFIX.$challengeId);
        abort_unless(is_array($challenge) && isset($challenge['user_id']), 404, 'Authentication challenge not found.');

        return $challenge;
    }

    /**
     * Issue a scoped Sanctum personal access token and build the authentication response.
     */
    private function tokenResponse(User $user, string $deviceName): JsonResponse
    {
        abort_if($user->fresh()->suspended_at !== null, 403, 'Account access is unavailable.');
        $token = $user->createToken(
            $deviceName !== '' ? $deviceName : 'Flutter mobile app',
            [config('api.abilities.read'), config('api.abilities.write')],
            now()->addMinutes((int) config('sanctum.expiration')),
        );

        app(UserSecurityActivityService::class)->signedIn($user, 'sanctum');
        app(OnlineUserPresenceService::class)->recordActivity($user->id);

        return response()->json([
            'message' => 'Authenticated successfully.',
            'data' => [
                'token' => $token->plainTextToken,
                'token_type' => 'Bearer',
                'expires_at' => $token->accessToken->expires_at?->toIso8601String(),
                'user' => new UserResource($user),
            ],
        ]);
    }
}
