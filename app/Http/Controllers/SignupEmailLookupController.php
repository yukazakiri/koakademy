<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\FacultySignupService;
use App\Services\StudentOrganizationAssignmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Throwable;

final class SignupEmailLookupController extends Controller
{
    public function __construct(
        private readonly StudentOrganizationAssignmentService $studentOrganizations,
        private readonly FacultySignupService $facultySignups,
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

            // Check if user account already exists
            $existingUser = User::whereRaw('LOWER(email) = ?', [$email])->first();
            if ($existingUser) {
                return response()->json([
                    'found' => false,
                    'message' => 'An account with this email already exists. Please login.',
                    'account_exists' => true,
                ]);
            }

            $includeHints = $request->boolean('include_hints');

            // Check if email exists in Faculty table
            $facultyCandidates = $this->facultySignups->candidatesForEmail($email);
            $registerableFaculties = $facultyCandidates->filter(fn ($candidate): bool => $candidate->isRegisterable())->values();
            $studentConfig = app(\App\Services\StudentConfigurationService::class);

            if ($registerableFaculties->isNotEmpty()) {
                $selfSignupAllowedFaculties = $registerableFaculties->filter(
                    fn ($candidate): bool => (bool) ($studentConfig->get($candidate->school_id)['signup']['allow_faculty_self_signup'] ?? true),
                )->values();

                if ($selfSignupAllowedFaculties->isEmpty()) {
                    return response()->json([
                        'found' => false,
                        'message' => 'Self-service faculty account activation is currently disabled for your institution. Please contact your school administrator.',
                    ]);
                }

                $payload = [
                    'found' => true,
                    'type' => 'faculty',
                ];

                if ($includeHints) {
                    $payload['hints'] = $this->facultySignups->signupHints($selfSignupAllowedFaculties);
                }

                return response()->json($payload);
            }

            $students = $this->studentOrganizations->candidatesForEmail($email);
            $student = $students->first();

            if ($student) {
                $selfSignupAllowedStudents = $students->filter(
                    fn ($candidate): bool => (bool) ($studentConfig->get($candidate->school_id)['signup']['allow_student_self_signup'] ?? true),
                )->values();

                if ($selfSignupAllowedStudents->isEmpty()) {
                    return response()->json([
                        'found' => false,
                        'message' => 'Self-service student account activation is currently disabled for your institution. Please contact your school administrator.',
                    ]);
                }

                $payload = [
                    'found' => true,
                    'type' => 'student',
                ];

                if ($includeHints) {
                    $payload['hints'] = $this->studentOrganizations->signupHints($selfSignupAllowedStudents);
                }

                return response()->json($payload);
            }

            // Only report an inactive faculty record if no eligible student record exists for the same email.
            if ($facultyCandidates->isNotEmpty()) {
                return response()->json([
                    'found' => false,
                    'message' => 'This faculty record is not currently active. Please contact the registrar.',
                ]);
            }

            // Email not found in either table
            return response()->json([
                'found' => false,
                'message' => 'Email not found in our records. Please use your registered school email.',
            ]);
        } catch (Throwable $e) {
            Log::error('Signup email lookup error: '.$e->getMessage());

            return response()->json([
                'found' => false,
                'message' => 'An error occurred while checking the email. Please try again later.',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }
}
