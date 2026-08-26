<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\StudentType;
use App\Mail\SignupOtpMail;
use App\Models\Faculty;
use App\Services\StudentOrganizationAssignmentService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

final class SignupOtpController extends Controller
{
    public function __construct(
        private readonly StudentOrganizationAssignmentService $studentOrganizations,
    ) {}

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|unique:users',
            'user_type' => 'required|in:student,faculty',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $email = $request->string('email')->trim()->lower()->toString();
        $userType = $request->user_type;

        // Perform specific validation based on user type (same as AuthController)
        if ($userType === 'student') {
            $response = $this->validateStudent($request);
            if ($response instanceof JsonResponse) {
                return $response;
            }
        } else {
            $response = $this->validateFaculty($request);
            if ($response instanceof JsonResponse) {
                return $response;
            }
        }

        // Generate OTP
        $otp = mb_strtoupper(Str::random(6));

        // Store in cache for 10 minutes
        Cache::put('signup_otp_'.$email, $otp, 600);

        // Send Email
        try {
            Mail::to($email)->send(new SignupOtpMail($otp));
        } catch (Exception $e) {
            return response()->json([
                'message' => 'Failed to send OTP. Please try again later.',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }

        return response()->json([
            'message' => 'Verification code sent to your email.',
        ]);
    }

    private function validateStudent(Request $request): ?JsonResponse
    {
        $studentType = $request->input('student_type');
        $isShs = $studentType === 'shs';

        $rules = [
            'student_type' => 'required|string|in:college,shs',
            'record_id' => 'nullable|integer',
        ];

        if ($isShs) {
            $rules['lrn'] = 'required|string';
        } else {
            $rules['student_id'] = 'required|string';
        }

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $this->studentOrganizations->resolveForSignup(
            email: $request->string('email')->toString(),
            studentType: StudentType::from($studentType),
            identifier: $request->string($isShs ? 'lrn' : 'student_id')->toString(),
            recordId: $request->input('record_id'),
        );

        return null;
    }

    private function validateFaculty(Request $request): ?JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'role' => 'required|string',
            'faculty_id_number' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        if ($request->filled('faculty_id_number')) {
            $email = $request->string('email')->trim()->lower()->toString();
            $faculty = Faculty::where('faculty_id_number', $request->faculty_id_number)
                ->where('email', $email)
                ->first();

            if (! $faculty) {
                return response()->json(['errors' => ['faculty_id_number' => 'The faculty ID number does not match our records for this email address.']], 422);
            }
        }

        return null;
    }
}
