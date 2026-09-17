<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\StudentType;
use App\Enums\UserRole;
use App\Models\ConnectedAccount;
use App\Models\Faculty;
use App\Models\User;
use App\Services\SocialiteProviderService;
use App\Services\StudentOrganizationAssignmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

final class AuthController extends Controller
{
    public function __construct(
        private readonly StudentOrganizationAssignmentService $studentOrganizations,
    ) {}

    public function showLoginForm(): Response
    {
        return Inertia::render('login', [
            'demoMode' => $this->getDemoModeData(),
        ]);
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

            // Check for enabled 2FA login challenges without removing configured credentials.
            if ($user->requiresTwoFactorChallenge()) {
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

    public function demoLogin(Request $request, string $role): RedirectResponse
    {
        abort_unless(app()->environment('demo'), 404);

        $account = config("demo.accounts.{$role}");

        abort_unless(is_array($account), 404);

        $email = $account['email'] ?? null;

        abort_unless(is_string($email) && $email !== '', 404);

        /** @var User|null $user */
        $user = User::query()
            ->where('email', $email)
            ->first();

        abort_unless($user instanceof User, 404);

        // If it's a faculty user, set faculty_id_number and record_id
        if ($user->role?->isFaculty()) {
            $faculty = Faculty::query()
                ->where('email', $email)
                ->first();

            if ($faculty) {
                $user->update([
                    'faculty_id_number' => $faculty->faculty_id_number,
                    'record_id' => $faculty->id,
                ]);
            }
        }

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->intended($this->getRedirectForUser($user));
    }

    public function logout(Request $request): \Illuminate\Routing\Redirector|RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }

    public function showSignupForm(): Response
    {
        return Inertia::render('signup', [
            'socialiteSignup' => session('socialite_signup'),
        ]);
    }

    public function signup(Request $request)
    {
        $request->merge([
            'email' => $request->string('email')->trim()->lower()->toString(),
        ]);

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
            'email' => 'required|string|email|max:255',
            'password' => ['required', 'confirmed', Password::defaults()],
            'user_type' => 'required|string|in:student',
            'student_type' => 'required|string|in:college,shs',
            'record_id' => 'nullable|integer',
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

        $email = $request->string('email')->trim()->lower()->toString();

        if (User::whereRaw('LOWER(email) = ?', [$email])->exists()) {
            return back()
                ->withErrors(['email' => 'An account with this email already exists.'])
                ->withInput($request->except(['password', 'password_confirmation']));
        }

        // Verify OTP
        $otpKey = 'signup_otp_'.$email;
        $cachedOtp = Cache::get($otpKey);

        if (! $cachedOtp || (string) $cachedOtp !== (string) $request->otp) {
            return back()
                ->withErrors(['otp' => 'Invalid or expired verification code.'])
                ->withInput($request->except(['password', 'password_confirmation']));
        }

        // Determine the role based on student type
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
                'avatar_url' => $this->pendingSocialiteAvatar($email),
            ]);

            $user->assignRole($spatieRole);
            $this->studentOrganizations->assign($user, $student);

            return $user;
        });

        Cache::forget($otpKey);

        $this->linkPendingSocialiteSignup($request, $user);

        Auth::login($user);
        $request->session()->regenerate();

        return redirect('/student/dashboard');
    }

    /**
     * Handle faculty signup
     */
    private function signupFaculty(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255',
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

        $email = $request->string('email')->trim()->lower()->toString();

        if (User::whereRaw('LOWER(email) = ?', [$email])->exists()) {
            return back()
                ->withErrors(['email' => 'An account with this email already exists.'])
                ->withInput($request->except(['password', 'password_confirmation']));
        }

        // Verify OTP
        $otpKey = 'signup_otp_'.$email;
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
            'email' => $email,
            'password' => Hash::make($request->password),
            'role' => $role,
            'email_verified_at' => now(),
            'avatar_url' => $this->pendingSocialiteAvatar($email),
        ];

        // If faculty_id_number is provided, verify it matches the email
        if ($request->filled('faculty_id_number')) {
            $faculty = Faculty::where('faculty_id_number', $request->faculty_id_number)
                ->whereRaw('LOWER(email) = ?', [$email])
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

        $this->linkPendingSocialiteSignup($request, $user);

        Auth::login($user);
        $request->session()->regenerate();

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

    /**
     * @return array{enabled: bool, accounts: array<int, array{role: string, label: string, description: string}>}
     */
    private function getDemoModeData(): array
    {
        $accounts = collect(config('demo.accounts', []))
            ->map(static fn (array $account): array => [
                'role' => (string) $account['role'],
                'label' => (string) $account['label'],
                'description' => (string) $account['description'],
            ])
            ->values()
            ->all();

        return [
            'enabled' => app()->environment('demo'),
            'accounts' => $accounts,
        ];
    }

    private function pendingSocialiteAvatar(string $email): ?string
    {
        $pending = session('socialite_signup');

        if (! is_array($pending) || mb_strtolower(mb_trim((string) ($pending['email'] ?? ''))) !== mb_strtolower(mb_trim($email))) {
            return null;
        }

        $avatar = $pending['avatar_url'] ?? null;

        return is_string($avatar) && $avatar !== '' ? $avatar : null;
    }

    private function linkPendingSocialiteSignup(Request $request, User $user): void
    {
        $pending = $request->session()->get('socialite_signup');

        if (! is_array($pending) || mb_strtolower(mb_trim((string) ($pending['email'] ?? ''))) !== mb_strtolower(mb_trim($user->email))) {
            return;
        }

        $provider = (string) ($pending['provider'] ?? '');
        $providerId = (string) ($pending['provider_id'] ?? '');

        if (! app(SocialiteProviderService::class)->isSupported($provider) || $providerId === '') {
            return;
        }

        $existingAccount = ConnectedAccount::query()
            ->where('provider', $provider)
            ->where('provider_id', $providerId)
            ->first();

        if ($existingAccount instanceof ConnectedAccount && (int) $existingAccount->user_id !== (int) $user->id) {
            $request->session()->forget('socialite_signup');

            return;
        }

        ConnectedAccount::query()->updateOrCreate(
            [
                'provider' => $provider,
                'provider_id' => $providerId,
            ],
            [
                'user_id' => $user->id,
                'name' => $pending['name'] ?? $user->name,
                'nickname' => $pending['nickname'] ?? null,
                'email' => $pending['email'] ?? $user->email,
                'avatar_path' => $pending['avatar_url'] ?? null,
                'token' => (string) ($pending['token'] ?? ''),
                'secret' => $pending['secret'] ?? null,
                'refresh_token' => $pending['refresh_token'] ?? null,
                'expires_at' => isset($pending['expires_at']) ? Carbon::parse((string) $pending['expires_at']) : null,
            ]
        );

        $request->session()->forget('socialite_signup');
    }
}
