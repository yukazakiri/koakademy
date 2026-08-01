<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Resources\ApiTokenResource;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Sanctum token management for external clients (mobile apps, third-party
 * web integrations). Issues personal access tokens against user credentials
 * and lets clients introspect and revoke them.
 */
final class AuthTokenController extends Controller
{
    /**
     * Issue a new personal access token for the given credentials.
     *
     * @throws ValidationException
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
            'device_name' => ['required', 'string', 'max:255'],
        ]);

        $user = User::query()->where('email', $validated['email'])->first();

        if (! $user instanceof User || ! Hash::check($validated['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => [__('auth.failed')],
            ]);
        }

        $token = $user->createToken($validated['device_name'])->plainTextToken;

        return response()->json([
            'message' => 'Authenticated.',
            'token' => $token,
            'user' => UserResource::make($user),
        ], 201);
    }

    /**
     * Return the currently authenticated user.
     */
    public function show(Request $request): UserResource
    {
        return UserResource::make($request->user());
    }

    /**
     * List the personal access tokens owned by the authenticated user.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $tokens = $request->user()->tokens()
            ->orderBy('created_at', 'desc')
            ->get();

        return ApiTokenResource::collection($tokens);
    }

    /**
     * Register an additional token (API key) for the authenticated user.
     * Intended for external web applications integrating read-only data.
     */
    public function storeToken(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'abilities' => ['sometimes', 'array'],
            'abilities.*' => ['string', 'max:255'],
            'expires_at' => ['sometimes', 'date', 'after:now'],
        ]);

        $abilities = $validated['abilities'] ?? ['read'];

        if (in_array('*', $abilities, true)) {
            $abilities = ['*'];
        }

        $expiresAt = isset($validated['expires_at'])
            ? Carbon::parse($validated['expires_at'])
            : null;

        $token = $request->user()->createToken($validated['name'], $abilities, $expiresAt);

        return response()->json([
            'message' => 'API key created successfully.',
            'token' => $token->plainTextToken,
            'token_name' => $validated['name'],
        ], 201);
    }

    /**
     * Revoke one of the authenticated user's tokens by id.
     */
    public function destroyToken(Request $request, int $tokenId): JsonResponse
    {
        $deleted = $request->user()->tokens()->where('id', $tokenId)->delete();

        if ($deleted === 0) {
            return response()->json([
                'error' => true,
                'message' => 'API key not found.',
                'code' => 'NOT_FOUND',
            ], 404);
        }

        return response()->json([
            'message' => 'API key deleted successfully.',
        ]);
    }

    /**
     * Revoke the token used to authenticate this request (sign out).
     */
    public function destroy(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()?->delete();

        return response()->json([
            'message' => 'Logged out.',
        ]);
    }
}
