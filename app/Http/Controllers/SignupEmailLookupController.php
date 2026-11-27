<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\Auth\SignupEmailLookupService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Throwable;

final class SignupEmailLookupController extends Controller
{
    public function __construct(
        private readonly SignupEmailLookupService $emailLookup,
    ) {}

    /**
     * Check if an email exists in the Student or Faculty tables
     * and return the appropriate user type.
     */
    public function __invoke(Request $request): JsonResponse
    {
        try {
            $request->merge([
                'email' => $request->string('email')->trim()->lower()->toString(),
            ]);

            $validator = Validator::make($request->all(), [
                'email' => 'required|email',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'found' => false,
                    'errors' => $validator->errors(),
                ], 422);
            }

            $email = $request->string('email')->trim()->lower()->toString();

            return response()->json(
                $this->emailLookup->lookup($email, includeHints: $request->boolean('include_hints')),
            );
        } catch (Throwable $e) {
            Log::error('Signup email lookup error: '.$e->getMessage());

            return response()->json([
                'found' => false,
                'message' => 'An error occurred while checking the email. Please try again.',
            ], 500);
        }
    }
}
