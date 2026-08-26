<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\StudentType;
use App\Models\Faculty;
use App\Models\User;
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
    ) {}

    /**
     * Check if an email exists in the Student or Faculty tables
     * and return the appropriate user type and details.
     */
    public function __invoke(Request $request): JsonResponse
    {
        try {
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
            $existingUser = User::where('email', $email)->first();
            if ($existingUser) {
                return response()->json([
                    'found' => false,
                    'message' => 'An account with this email already exists. Please login.',
                    'account_exists' => true,
                ]);
            }

            // Check if email exists in Faculty table
            $faculty = Faculty::where('email', $email)->first();

            if ($faculty) {
                return response()->json([
                    'found' => true,
                    'type' => 'faculty',
                    'name' => $faculty->full_name,
                    'faculty_id_number' => $faculty->faculty_id_number,
                    'department' => $faculty->department,
                    'record_id' => $faculty->id,
                ]);
            }

            $students = $this->studentOrganizations->candidatesForEmail($email);
            $student = $students->first();

            if ($student) {
                if ($students->pluck('student_type')->unique()->count() > 1) {
                    return response()->json([
                        'found' => false,
                        'message' => 'This email matches multiple student types. Please contact your school administrator.',
                    ]);
                }

                $studentType = $student->student_type;
                $isShs = $studentType === StudentType::SeniorHighSchool;

                return response()->json([
                    'found' => true,
                    'type' => 'student',
                    'student_type' => $studentType?->value ?? 'college',
                    'is_shs' => $isShs,
                    'name' => $student->full_name,
                    // 'student_id' => $student->student_id, // Removed for security/verification
                    // 'lrn' => $student->lrn, // Removed for security/verification
                    'course' => $student->Course?->name ?? null,
                    'academic_year' => $student->academic_year,
                    'record_id' => $students->count() === 1 ? $student->id : null,
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
