<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Mail\SignupOtpMail;
use App\Models\Faculty;
use App\Models\Student;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

final class SignupOtpController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|unique:users',
            'user_type' => 'required|in:student,faculty',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $email = $request->email;
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
            'record_id' => 'required',
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

        $student = Student::find($request->record_id);

        if (! $student) {
            return response()->json(['errors' => ['email' => 'Student record not found.']], 422);
        }

        // Verify student ID or LRN matches
        if ($isShs) {
            if ($student->lrn !== $request->lrn) {
                return response()->json(['errors' => ['lrn' => 'The LRN does not match our records for this email address.']], 422);
            }
        } elseif ((string) $student->student_id !== (string) $request->student_id) {
            // Compare as strings to handle potential type mismatches
            return response()->json(['errors' => ['student_id' => 'The Student ID does not match our records for this email address.']], 422);
        }

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
            $faculty = Faculty::where('faculty_id_number', $request->faculty_id_number)
                ->where('email', $request->email)
                ->first();

            if (! $faculty) {
                return response()->json(['errors' => ['faculty_id_number' => 'The faculty ID number does not match our records for this email address.']], 422);
            }
        }

        return null;
    }
}
