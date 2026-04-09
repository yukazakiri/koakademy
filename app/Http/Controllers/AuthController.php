<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\Faculty;
use App\Models\Student;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

final class AuthController extends Controller
{
    public function showLoginForm(): Response
    {
        return Inertia::render('login');
    }

    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
            'remember' => ['nullable', 'boolean'],
        ]);

        if ($validator->fails()) {
            return Inertia::render('login', [
                'errors' => $validator->errors(),
                'status' => 'Validation failed',
            ]);
        }

        $remember = $request->boolean('remember');

        // Get only the credentials for authentication (email and password)
        // Exclude 'remember' as it's not a database column
        $credentials = [
            'email' => $request->email,
            'password' => $request->password,
        ];

        if (Auth::attempt($credentials, $remember)) {
            $request->session()->regenerate();

            /** @var User|null $user */
            $user = $request->user();

            // Check for 2FA (app auth, email auth, or passkeys)
            if ($user->app_authentication_secret || $user->hasEmailAuthentication() || $user->passkeys()->exists()) {
                Auth::logout();
                $request->session()->put('auth.2fa.id', $user->id);
                $request->session()->put('auth.2fa.remember', $remember);

                return redirect()->route('two-factor.login');
            }

            // Determine redirect based on user role
            $defaultRedirect = $this->getRedirectForUser($user);

            return redirect()->intended($defaultRedirect);
        }

        return Inertia::render('login', [
            'errors' => ['email' => 'The provided credentials do not match our records.'],
            'status' => 'Authentication failed',
        ]);
    }

    public function logout(Request $request): \Illuminate\Routing\Redirector|\Illuminate\Http\RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }

    public function showSignupForm(): Response
    {
        return Inertia::render('signup');
    }

    public function signup(Request $request)
    {
        $userType = $request->input('user_type');

        // Different validation based on user type
        if ($userType === 'student') {
            return $this->signupStudent($request);
        }

        return $this->signupFaculty($request);
    }

    /**
     * Handle student signup
     */
    private function signupStudent(Request $request)
    {
        $studentType = $request->input('student_type');
        $isShs = $studentType === 'shs';

        $validationRules = [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => ['required', 'confirmed', Password::defaults()],
            'user_type' => 'required|string|in:student',
            'student_type' => 'required|string|in:college,shs',
            'record_id' => 'required',
            'otp' => 'required|string',
        ];

        // Add specific validation based on student type
        if ($isShs) {
            $validationRules['lrn'] = 'required|string|max:12';
        } else {
            $validationRules['student_id'] = 'required|string|max:20';
        }

        $validator = Validator::make($request->all(), $validationRules);

        if ($validator->fails()) {
            return back()
                ->withErrors($validator->errors())
                ->withInput($request->except(['password', 'password_confirmation']));
        }

        // Verify OTP
        $otpKey = 'signup_otp_'.$request->email;
        $cachedOtp = Cache::get($otpKey);

        if (! $cachedOtp || (string) $cachedOtp !== (string) $request->otp) {
            return back()
                ->withErrors(['otp' => 'Invalid or expired verification code.'])
                ->withInput($request->except(['password', 'password_confirmation']));
        }

        // Verify the student exists and matches the provided ID
        $student = Student::find($request->record_id);

        if (! $student) {
            return back()
                ->withErrors(['email' => 'Student record not found.'])
                ->withInput($request->only(['name', 'email', 'student_type']));
        }

        // Verify student ID or LRN matches
        if ($isShs) {
            if ($student->lrn !== $request->lrn) {
                return back()
                    ->withErrors(['lrn' => 'The LRN does not match our records for this email address.'])
                    ->withInput($request->only(['name', 'email', 'student_type', 'lrn']));
            }
        } elseif ((string) $student->student_id !== $request->student_id) {
            return back()
                ->withErrors(['student_id' => 'The Student ID does not match our records for this email address.'])
                ->withInput($request->only(['name', 'email', 'student_type', 'student_id']));
        }

        // Clear OTP after successful verification
        Cache::forget($otpKey);

        // Determine the role based on student type
        $role = $isShs ? UserRole::ShsStudent : UserRole::Student;

        // Create or get the spatie role
        $spatieRoleName = $role->value;
        $spatieRole = Role::firstOrCreate(
            ['name' => $spatieRoleName, 'guard_name' => 'web'],
            ['name' => $spatieRoleName, 'guard_name' => 'web']
        );

        // Create the user
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => $role,
            'record_id' => $student->id,
            'email_verified_at' => now(),
        ]);

        // Assign spatie role
        $user->assignRole($spatieRole);

        // Link student to user
        $student->user_id = $user->id;
        $student->save();

        Auth::login($user);

        return redirect('/student/dashboard');
    }

    /**
     * Handle faculty signup
     */
    private function signupFaculty(Request $request)
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
            return back()
                ->withErrors($validator->errors())
                ->withInput($request->except(['password', 'password_confirmation']));
        }

        // Verify OTP
        $otpKey = 'signup_otp_'.$request->email;
        $cachedOtp = Cache::get($otpKey);

        if (! $cachedOtp || (string) $cachedOtp !== (string) $request->otp) {
            return back()
                ->withErrors(['otp' => 'Invalid or expired verification code.'])
                ->withInput($request->except(['password', 'password_confirmation']));
        }

        // Get the role from the request and convert to UserRole enum
        $roleString = $request->role;
        $role = UserRole::tryFrom($roleString);

        // If invalid role, default to Instructor (safety check)
        if (! $role) {
            $role = UserRole::Instructor;
        }

        // Create or get the spatie role
        $spatieRoleName = $role->value;
        $spatieRole = Role::firstOrCreate(
            ['name' => $spatieRoleName, 'guard_name' => 'web'],
            ['name' => $spatieRoleName, 'guard_name' => 'web']
        );

        $userData = [
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => $role,
            'email_verified_at' => now(),
        ];

        // If faculty_id_number is provided, verify it matches the email
        if ($request->filled('faculty_id_number')) {
            $faculty = Faculty::where('faculty_id_number', $request->faculty_id_number)
                ->where('email', $request->email)
                ->first();

            if (! $faculty) {
                return back()
                    ->withErrors(['faculty_id_number' => 'The faculty ID number does not match our records for this email address.'])
                    ->withInput($request->only(['name', 'email', 'faculty_id_number', 'role']));
            }

            // Store faculty metadata for easy search
            $userData['faculty_id_number'] = $request->faculty_id_number;
            $userData['record_id'] = $faculty->id;
        }

        // Clear OTP after successful verification
        Cache::forget($otpKey);

        $user = User::create($userData);

        // Assign spatie role
        $user->assignRole($spatieRole);

        Auth::login($user);

        return redirect('/faculty/dashboard');
    }

    /**
     * Determine the redirect URL based on user role
     */
    private function getRedirectForUser(User $user): string
    {
        if ($user->isAdministrative()) {
            return '/administrators';
        }

        if ($user->role?->isStudent()) {
            return '/student/dashboard';
        }

        return '/faculty/dashboard';
    }
}
