<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\DigitalIdCardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

final class DigitalIdCardController extends Controller
{
    public function __construct(
        private readonly DigitalIdCardService $idCardService
    ) {}

    /**
     * Get the current user's digital ID card data.
     */
    public function show(Request $request): JsonResponse
    {
        $user = Auth::user();

        if (! $user) {
            return response()->json([
                'error' => 'Unauthenticated',
            ], 401);
        }

        $idCardData = $this->idCardService->generateIdCardForUser($user);

        if (! $idCardData) {
            return response()->json([
                'error' => 'No ID card available for this user',
            ], 404);
        }

        return response()->json($idCardData);
    }

    /**
     * Refresh the QR code for the current user's ID card.
     * This generates a new QR code with an updated timestamp.
     */
    public function refresh(Request $request): JsonResponse
    {
        $user = Auth::user();

        if (! $user) {
            return response()->json([
                'error' => 'Unauthenticated',
            ], 401);
        }

        $idCardData = $this->idCardService->generateIdCardForUser($user);

        if (! $idCardData) {
            return response()->json([
                'error' => 'No ID card available for this user',
            ], 404);
        }

        return response()->json([
            'qr_code' => $idCardData['qr_code'],
            'refreshed_at' => format_timestamp_now(),
        ]);
    }

    /**
     * Verify an ID card QR code.
     * This endpoint is publicly accessible for scanning purposes.
     */
    public function verify(Request $request, string $token): Response|JsonResponse
    {
        $result = $this->idCardService->verifyToken($token);

        if (! $result) {
            if ($request->wantsJson()) {
                return response()->json([
                    'valid' => false,
                    'error' => 'Invalid or expired ID card',
                ], 400);
            }

            return Inertia::render('id-card/verify', [
                'valid' => false,
                'error' => 'Invalid or expired ID card',
            ]);
        }

        if ($request->wantsJson()) {
            return response()->json($result);
        }

        return Inertia::render('id-card/verify', $result);
    }

    /**
     * Display the ID card page (standalone view).
     */
    public function index(Request $request): Response
    {
        $user = Auth::user();

        $idCardData = $user ? $this->idCardService->generateIdCardForUser($user) : null;

        return Inertia::render('id-card/index', [
            'id_card' => $idCardData,
            'user' => $user ? [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'avatar_url' => $user->avatar_url ?? null,
                'role' => $user->role?->value ?? 'user',
            ] : null,
        ]);
    }
}
