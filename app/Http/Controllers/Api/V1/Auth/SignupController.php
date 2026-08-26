<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Enums\StudentType;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Mail\SignupOtpMail;
use App\Models\Faculty;
use App\Models\User;
use App\Services\StudentOrganizationAssignmentService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Spatie\Permission\Models\Role;
use Throwable;

final class SignupController extends Controller
{
    public function __construct(
        private readonly StudentOrganizationAssignmentService $studentOrganizations,
    ) {}

    /**
     * Check if an email exists in the Student or Faculty tables
     * and return the appropriate user type and details.
     */
    public function emailLookup(Request $request): JsonResponse
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

            $existingUser = User::where('email', $email)->first();
            if ($existingUser) {
                return response()->json([
                    'found' => false,
                    'message' => 'An account with this email already exists. Please login.',
                    'account_exists' => true,
                ]);
            }

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
                    'course' => $student->Course?->name ?? null,
                    'academic_year' => $student->academic_year,
                    'record_id' => $students->count() === 1 ? $student->id : null,
                ]);
            }

            return response()->json([
                'found' => false,
                'message' => 'Email not found in our records. Please use your registered school email.',
            ]);
        } catch (Throwable $e) {
            Log::error('API signup email lookup error: '.$e->getMessage());

            return response()->json([
                'found' => false,
                'message' => 'An error occurred while checking the email. Please try again later.',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Send an OTP to the user's email for signup verification.
     */
    public function sendOtp(Request $request): JsonResponse
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

        if ($userType === 'student') {
            $response = $this->validateStudentForOtp($request);
            if ($response instanceof JsonResponse) {
                return $response;
            }
        } else {
            $response = $this->validateFacultyForOtp($request);
            if ($response instanceof JsonResponse) {
                return $response;
            }
        }

        $otp = mb_strtoupper(Str::random(6));

        Cache::put('signup_otp_'.$email, $otp, 600);

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

    /**
     * Complete signup and create a user account.
     * Returns a Sanctum token for API authentication.
     */
    public function signup(Request $request): JsonResponse
    {
        $userType = $request->input('user_type');

        if ($userType === 'student') {
            return $this->signupStudent($request);
        }

        return $this->signupFaculty($request);
    }

    /**
     * Handle student signup via API.
     */
    private function signupStudent(Request $request): JsonResponse
    {
        $studentType = $request->input('student_type');
        $isShs = $studentType === 'shs';

        $validationRules = [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => ['required', 'confirmed', Password::defaults()],
            'user_type' => 'required|string|in:student',
            'student_type' => 'required|string|in:college,shs',
            'record_id' => 'nullable|integer',
            'otp' => 'required|string',
        ];

        if ($isShs) {
            $validationRules['lrn'] = 'required|string|max:12';
        } else {
            $validationRules['student_id'] = 'required|string|max:20';
        }

        $validator = Validator::make($request->all(), $validationRules);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $email = $request->string('email')->trim()->lower()->toString();
        $otpKey = 'signup_otp_'.$email;
        $cachedOtp = Cache::get($otpKey);

        if (! $cachedOtp || (string) $cachedOtp !== (string) $request->otp) {
            return response()->json([
                'message' => 'Invalid or expired verification code.',
                'errors' => ['otp' => ['Invalid or expired verification code.']],
            ], 422);
        }

        $role = $isShs ? UserRole::ShsStudent : UserRole::Student;
        $user = DB::transaction(function () use ($request, $role, $studentType, $isShs, $email): User {
            $student = $this->studentOrganizations->resolveForSignup(
                email: $email,
                studentType: StudentType::from($studentType),
                identifier: $request->string($isShs ? 'lrn' : 'student_id')->toString(),
                recordId: $request->input('record_id'),
                lockForUpdate: true,
            );

            $spatieRole = Role::firstOrCreate(
                ['name' => $role->value, 'guard_name' => 'web'],
                ['name' => $role->value, 'guard_name' => 'web'],
            );

            $user = User::create([
                'name' => $request->name,
                'email' => $email,
                'password' => Hash::make($request->password),
                'role' => $role,
                'record_id' => $student->id,
                'school_id' => $student->school_id,
                'email_verified_at' => now(),
            ]);

            $user->assignRole($spatieRole);
            $this->studentOrganizations->assign($user, $student);

            return $user;
        });

        Cache::forget($otpKey);

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'Account created successfully.',
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role?->value,
                    'student_type' => $studentType,
                ],
                'token' => $token,
                'token_type' => 'Bearer',
            ],
        ], 201);
    }

    /**
     * Handle faculty signup via API.
     */
    private function signupFaculty(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => ['required', 'confirmed', Password::defaults()],
            'faculty_id_number' => 'nullable|string|max:255',
            'role' => 'required|string|in:professor,associate_professor,assistant_professor,instructor,part_time_faculty',
            'otp' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $email = $request->string('email')->trim()->lower()->toString();
        $otpKey = 'signup_otp_'.$email;
        $cachedOtp = Cache::get($otpKey);

        if (! $cachedOtp || (string) $cachedOtp !== (string) $request->otp) {
            return response()->json([
                'message' => 'Invalid or expired verification code.',
                'errors' => ['otp' => ['Invalid or expired verification code.']],
            ], 422);
        }

        $roleString = $request->role;
        $role = UserRole::tryFrom($roleString);

        if (! $role) {
            $role = UserRole::Instructor;
        }

        $spatieRoleName = $role->value;
        $spatieRole = Role::firstOrCreate(
            ['name' => $spatieRoleName, 'guard_name' => 'web'],
            ['name' => $spatieRoleName, 'guard_name' => 'web']
        );

        $userData = [
            'name' => $request->name,
            'email' => $email,
            'password' => Hash::make($request->password),
            'role' => $role,
            'email_verified_at' => now(),
        ];

        if ($request->filled('faculty_id_number')) {
            $faculty = Faculty::where('faculty_id_number', $request->faculty_id_number)
                ->where('email', $email)
                ->first();

            if (! $faculty) {
                return response()->json([
                    'message' => 'The faculty ID number does not match our records.',
                    'errors' => ['faculty_id_number' => ['The faculty ID number does not match our records for this email address.']],
                ], 422);
            }

            $userData['faculty_id_number'] = $request->faculty_id_number;
            $userData['record_id'] = $faculty->id;
        }

        Cache::forget($otpKey);

        $user = User::create($userData);

        $user->assignRole($spatieRole);

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'Account created successfully.',
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role?->value,
                    'faculty_id_number' => $user->faculty_id_number ?? null,
                ],
                'token' => $token,
                'token_type' => 'Bearer',
            ],
        ], 201);
    }

    /**
     * Validate student details before sending OTP.
     */
    private function validateStudentForOtp(Request $request): ?JsonResponse
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

    /**
     * Validate faculty details before sending OTP.
     */
    private function validateFacultyForOtp(Request $request): ?JsonResponse
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
                return response()->json(['errors' => ['faculty_id_number' => ['The faculty ID number does not match our records for this email address.']]], 422);
            }
        }

        return null;
    }
}
