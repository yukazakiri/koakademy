<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Features\Onboarding\FacultyDeveloperMode;
use App\Features\Onboarding\StudentDeveloperMode;
use App\Http\Requests\ApiKeyRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Laravel\Pennant\Feature;

final class ApiKeyController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user) {
            return response()->json(['error' => 'Unauthenticated.'], 401);
        }

        if (! $this->hasDeveloperModeEnabled($user)) {
            return response()->json(['error' => 'Developer mode is not enabled.'], 403);
        }

        $tokens = $user->tokens()
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn ($token): array => [
                'id' => $token->id,
                'name' => $token->name,
                'abilities' => $token->abilities ?? ['*'],
                'last_used_at' => $token->last_used_at?->diffForHumans(),
                'expires_at' => $token->expires_at?->format('Y-m-d H:i:s'),
                'created_at' => $token->created_at->format('Y-m-d H:i:s'),
            ]);

        return response()->json(['tokens' => $tokens]);
    }

    public function store(ApiKeyRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $user = $request->user();

        if (! $user) {
            return response()->json(['error' => 'Unauthenticated.'], 401);
        }

        if (! $this->hasDeveloperModeEnabled($user)) {
            return response()->json(['error' => 'Developer mode is not enabled.'], 403);
        }

        $abilities = $validated['abilities'] ?? ['read'];

        if (in_array('*', $abilities)) {
            $abilities = ['*'];
        }

        $expiresAt = isset($validated['expires_at'])
            ? Carbon::parse($validated['expires_at'])
            : null;

        $plainTextToken = $user->createToken(
            $validated['name'],
            $abilities,
            $expiresAt
        )->plainTextToken;

        return response()->json([
            'message' => 'API key created successfully.',
            'token' => $plainTextToken,
            'token_name' => $validated['name'],
        ], 201);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        if (! $user) {
            return response()->json(['error' => 'Unauthenticated.'], 401);
        }

        if (! $this->hasDeveloperModeEnabled($user)) {
            return response()->json(['error' => 'Developer mode is not enabled.'], 403);
        }

        $token = $user->tokens()->where('id', $id)->first();

        if (! $token) {
            return response()->json(['error' => 'API key not found.'], 404);
        }

        $token->delete();

        return response()->json(['message' => 'API key deleted successfully.']);
    }

    public function checkDeveloperMode(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user) {
            return response()->json(['enabled' => false], 401);
        }

        $enabled = $this->hasDeveloperModeEnabled($user);

        return response()->json(['enabled' => $enabled]);
    }

    private function hasDeveloperModeEnabled(User $user): bool
    {
        $userRole = $user->role?->value ?? 'user';

        $isFaculty = in_array($userRole, ['professor', 'associate_professor', 'assistant_professor', 'instructor', 'part_time_faculty'], true);
        $isStudent = in_array($userRole, ['student', 'graduate_student', 'shs_student'], true);

        if (! $isFaculty && ! $isStudent) {
            return false;
        }

        $developerModeFeature = $isFaculty
            ? FacultyDeveloperMode::class
            : StudentDeveloperMode::class;

        return Feature::for($user)->active($developerModeFeature);
    }
}
