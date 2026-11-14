<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\StudentType;
use App\Mail\SignupOtpMail;
use App\Models\User;
use App\Services\Auth\SignupOtpService;
use App\Services\FacultySignupService;
use App\Services\StudentOrganizationAssignmentService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class SignupOtpController extends Controller
{
    public function __construct(
        private readonly StudentOrganizationAssignmentService $studentOrganizations,
        private readonly FacultySignupService $facultySignups,
        private readonly SignupOtpService $signupOtps,
    ) {}

    public function store(Request $request): JsonResponse
    {
        $request->merge([
            'email' => $request->string('email')->trim()->lower()->toString(),
        ]);

        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'user_type' => 'required|in:student,faculty',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $email = $request->string('email')->trim()->lower()->toString();

        if (User::whereRaw('LOWER(email) = ?', [$email])->exists()) {
            return response()->json([
                'message' => 'An account with this email already exists.',
                'errors' => ['email' => ['An account with this email already exists.']],
            ], 422);
        }

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
        $this->signupOtps->issue($email, $otp);

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

        $rules = [
            'student_type' => ['required', 'string', \Illuminate\Validation\Rule::enum(StudentType::class)],
            'record_id' => 'nullable|integer',
        ];

        $identifierField = $request->filled('student_id') ? 'student_id' : 'lrn';
        $rules[$identifierField] = 'required|string|max:64';

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $this->studentOrganizations->resolveForSignup(
            email: $request->string('email')->toString(),
            studentType: StudentType::from($studentType),
            identifier: $request->string($identifierField)->toString(),
            recordId: $request->input('record_id'),
            inputField: $identifierField,
        );

        return null;
    }

    private function validateFaculty(Request $request): ?JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'faculty_id_number' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            $this->facultySignups->resolveForSignup(
                email: $request->string('email')->trim()->lower()->toString(),
                facultyIdNumber: $request->string('faculty_id_number')->trim()->toString() ?: null,
            );
        } catch (ValidationException $exception) {
            return response()->json(['errors' => $exception->errors()], 422);
        }

        return null;
    }
}
