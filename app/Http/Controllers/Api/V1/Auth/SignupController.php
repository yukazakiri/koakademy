<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Enums\StudentType;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Mail\SignupOtpMail;
use App\Models\User;
use App\Services\Auth\SignupOtpService;
use App\Services\FacultySignupService;
use App\Services\StudentOrganizationAssignmentService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;
use Throwable;

final class SignupController extends Controller
{
    public function __construct(
        private readonly StudentOrganizationAssignmentService $studentOrganizations,
        private readonly FacultySignupService $facultySignups,
        private readonly SignupOtpService $signupOtps,
    ) {}

    /**
     * Check if an email exists in the Student or Faculty tables
     * and return the appropriate user type.
     */
    public function emailLookup(Request $request): JsonResponse
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

            $existingUser = User::whereRaw('LOWER(email) = ?', [$email])->first();
            if ($existingUser) {
                return response()->json([
                    'found' => false,
                    'message' => 'An account with this email already exists. Please login.',
                    'account_exists' => true,
                ]);
            }

            $faculty = $this->facultySignups->findByEmail($email);

            if ($faculty) {
                if (! $faculty->isRegisterable()) {
                    return response()->json([
                        'found' => false,
                        'message' => 'This faculty record is not currently active. Please contact the registrar.',
                    ]);
                }

                return response()->json([
                    'found' => true,
                    'type' => 'faculty',
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

                return response()->json([
                    'found' => true,
                    'type' => 'student',
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

        $this->signupOtps->issue($email, $otp);

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
        $request->merge([
            'email' => $request->string('email')->trim()->lower()->toString(),
        ]);

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
            'email' => 'required|string|email|max:255',
            'password' => ['required', 'confirmed', Password::defaults()],
            'user_type' => 'required|string|in:student',
            'student_type' => ['required', 'string', \Illuminate\Validation\Rule::enum(StudentType::class)],
            'record_id' => 'nullable|integer',
            'otp' => 'required|string',
        ];

        $identifierField = $request->filled('student_id') ? 'student_id' : 'lrn';
        $validationRules[$identifierField] = 'required|string|max:64';

        $validator = Validator::make($request->all(), $validationRules);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $email = $request->string('email')->trim()->lower()->toString();

        if (User::whereRaw('LOWER(email) = ?', [$email])->exists()) {
            return response()->json([
                'message' => 'An account with this email already exists.',
                'errors' => ['email' => ['An account with this email already exists.']],
            ], 422);
        }

        if (! $this->signupOtps->verify($email, (string) $request->otp)) {
            return response()->json([
                'message' => $this->signupOtpError($email),
                'errors' => ['otp' => [$this->signupOtpError($email)]],
            ], 422);
        }

        $role = $isShs ? UserRole::ShsStudent : UserRole::Student;
        $user = DB::transaction(function () use ($request, $role, $studentType, $email, $identifierField): User {
            $student = $this->studentOrganizations->resolveForSignup(
                email: $email,
                studentType: StudentType::from($studentType),
                identifier: $request->string($identifierField)->toString(),
                recordId: $request->input('record_id'),
                lockForUpdate: true,
                inputField: $identifierField,
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

        $this->signupOtps->forget($email);

        $token = $user->createToken(
            'auth_token',
            [config('api.abilities.read'), config('api.abilities.write')],
            now()->addMinutes((int) config('sanctum.expiration')),
        )->plainTextToken;

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
            'email' => 'required|string|email|max:255',
            'password' => ['required', 'confirmed', Password::defaults()],
            'faculty_id_number' => 'nullable|string|max:255',
            'otp' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $email = $request->string('email')->trim()->lower()->toString();

        if (User::whereRaw('LOWER(email) = ?', [$email])->exists()) {
            return response()->json([
                'message' => 'An account with this email already exists.',
                'errors' => ['email' => ['An account with this email already exists.']],
            ], 422);
        }

        if (! $this->signupOtps->verify($email, (string) $request->otp)) {
            return response()->json([
                'message' => $this->signupOtpError($email),
                'errors' => ['otp' => [$this->signupOtpError($email)]],
            ], 422);
        }

        try {
            $user = DB::transaction(function () use ($request, $email): User {
                // The record is resolved inside the transaction so the roster row stays
                // locked while the account is written against it.
                $faculty = $this->facultySignups->resolveForSignup(
                    email: $email,
                    facultyIdNumber: $request->string('faculty_id_number')->trim()->toString() ?: null,
                    lockForUpdate: true,
                );

                // The role is read from the institutional record, never from the request.
                $role = $this->facultySignups->roleFor($faculty);

                $spatieRole = Role::firstOrCreate(
                    ['name' => $role->value, 'guard_name' => 'web'],
                    ['name' => $role->value, 'guard_name' => 'web']
                );

                $user = User::create([
                    'name' => $request->name,
                    'email' => $email,
                    'password' => Hash::make($request->password),
                    'role' => $role,
                    'email_verified_at' => now(),
                ]);

                $user->assignRole($spatieRole);

                $this->facultySignups->assign($user, $faculty);

                return $user;
            });
        } catch (ValidationException $exception) {
            return response()->json([
                'message' => 'Validation failed.',
                'errors' => $exception->errors(),
            ], 422);
        }

        $this->signupOtps->forget($email);

        $token = $user->createToken(
            'auth_token',
            [config('api.abilities.read'), config('api.abilities.write')],
            now()->addMinutes((int) config('sanctum.expiration')),
        )->plainTextToken;

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
     * The message shown when a signup verification code is not accepted.
     */
    private function signupOtpError(string $email): string
    {
        if ($this->signupOtps->exhausted($email)) {
            return 'Too many incorrect verification codes. Please request a new code.';
        }

        return 'Invalid or expired verification code.';
    }

    /**
     * Validate student details before sending OTP.
     */
    private function validateStudentForOtp(Request $request): ?JsonResponse
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

    /**
     * Validate faculty details before sending OTP.
     */
    private function validateFacultyForOtp(Request $request): ?JsonResponse
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
