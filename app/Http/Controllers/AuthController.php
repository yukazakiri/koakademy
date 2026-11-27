<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\StudentType;
use App\Enums\UserRole;
use App\Models\Faculty;
use App\Models\User;
use App\Services\Auth\LoginAttemptService;
use App\Services\Auth\PendingSocialSignupService;
use App\Services\Auth\SignupEmailLookupService;
use App\Services\Auth\SignupOtpService;
use App\Services\FacultySignupService;
use App\Services\StudentConfigurationService;
use App\Services\StudentOrganizationAssignmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

final class AuthController extends Controller
{
    public function __construct(
        private readonly StudentOrganizationAssignmentService $studentOrganizations,
        private readonly FacultySignupService $facultySignups,
        private readonly LoginAttemptService $loginAttempts,
        private readonly SignupOtpService $signupOtps,
        private readonly PendingSocialSignupService $pendingSocial,
        private readonly SignupEmailLookupService $emailLookup,
        private readonly StudentConfigurationService $studentConfig,
    ) {}

    public function showLoginForm(Request $request): Response
    {
        return Inertia::render('login', [
            'demoMode' => $this->getDemoModeData(),
            'pendingSocialLink' => $this->pendingSocial->pendingLoginLinkPayload($request),
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
                'demoMode' => $this->getDemoModeData(),
                'pendingSocialLink' => $this->pendingSocial->pendingLoginLinkPayload($request),
            ]);
        }

        $email = mb_strtolower(mb_trim((string) $request->email));
        $ip = (string) $request->ip();

        // A rate limiter alone only slows guessing down for the length of one
        // window. The lockout is what actually bounds the number of passwords
        // that can be tried against a single account.
        if ($this->loginAttempts->tooManyAttempts($email, $ip)) {
            return $this->lockedOutResponse($request, $email, $ip);
        }

        $remember = $request->boolean('remember');

        // Resolve stored email casing so case-sensitive databases (e.g. PostgreSQL)
        // still authenticate mixed-case input consistently with normalized lockouts.
        $matchedUser = User::query()
            ->whereRaw('LOWER(email) = ?', [$email])
            ->first();

        // Get only the credentials for authentication (email and password)
        // Exclude 'remember' as it's not a database column
        $credentials = [
            'email' => $matchedUser?->email ?? $email,
            'password' => $request->password,
        ];

        if (Auth::attempt($credentials, $remember)) {
            $this->loginAttempts->recordSuccess($email, $ip);

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

            $linkedProvider = $this->pendingSocial->pendingLoginLinkPayload($request) !== null
                ? $this->pendingSocial->linkToUser($request, $user, allowDifferentEmail: true)
                : null;

            // Determine redirect based on user role
            $defaultRedirect = $this->getRedirectForUser($user);
            $redirect = redirect()->intended($defaultRedirect);

            if ($linkedProvider !== null) {
                return $redirect->with('status', ucfirst($linkedProvider).' is now linked to your account.');
            }

            return $redirect;
        }

        $this->loginAttempts->recordFailure($email, $ip);

        return Inertia::render('login', [
            'errors' => ['email' => 'The provided credentials do not match our records.'],
            'demoMode' => $this->getDemoModeData(),
            'pendingSocialLink' => $this->pendingSocial->pendingLoginLinkPayload($request),
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

    public function showSignupForm(Request $request): Response
    {
        $this->studentConfig->resolvePublicSchool();

        $socialiteSignup = $this->pendingSocial->frontendPayload($request);
        $socialLookup = null;

        if ($socialiteSignup !== null && filled($socialiteSignup['email'])) {
            $socialLookup = $this->emailLookup->lookup($socialiteSignup['email'], includeHints: true);
        }

        return Inertia::render('signup', [
            'socialiteSignup' => $socialiteSignup,
            'socialLookup' => $socialLookup,
            'signupConfig' => $this->studentConfig->signupPresentation(),
        ]);
    }

    public function startSocialLoginLink(Request $request): RedirectResponse
    {
        $this->pendingSocial->enableLoginLink($request);

        return redirect()->route('login');
    }

    public function cancelSocialSignup(Request $request): RedirectResponse
    {
        $this->pendingSocial->clear($request);

        if ($request->input('redirect') === 'login') {
            return redirect()->route('login');
        }

        return redirect()->route('signup');
    }

    public function signup(Request $request)
    {
        $request->merge([
            'email' => $request->string('email')->trim()->lower()->toString(),
        ]);

        $userType = $request->input('user_type');

        if ($userType !== null && ! in_array($userType, ['student', 'faculty'], true)) {
            return back()
                ->withErrors(['user_type' => 'Invalid account type selected.'])
                ->withInput($request->except(['password', 'password_confirmation']));
        }

        // Different validation based on user type
        if ($userType === 'student') {
            return $this->signupStudent($request);
        }

        return $this->signupFaculty($request);
    }

    /**
     * Report a locked account without revealing whether the account exists.
     */
    private function lockedOutResponse(Request $request, string $email, string $ip): Response
    {
        $minutes = max(1, (int) ceil($this->loginAttempts->availableIn($email, $ip) / 60));

        return Inertia::render('login', [
            'errors' => ['email' => "Too many failed sign-in attempts. Please try again in {$minutes} minute(s)."],
            'demoMode' => $this->getDemoModeData(),
            'pendingSocialLink' => $this->pendingSocial->pendingLoginLinkPayload($request),
        ]);
    }

    /**
     * Handle student signup
     */
    private function signupStudent(Request $request)
    {
        $studentType = $request->input('student_type');
        $email = $request->string('email')->trim()->lower()->toString();
        $hasPendingSocial = $this->pendingSocial->hasActive($request);
        $canSkipOtp = $this->pendingSocial->canSkipEmailOtp($request, $email);
        $passwordSubmitted = $request->filled('password') || $request->filled('password_confirmation');

        $validationRules = [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255',
            'password' => $hasPendingSocial && ! $passwordSubmitted
                ? ['nullable', 'string']
                : ['required', 'confirmed', Password::defaults()],
            'user_type' => 'required|string|in:student',
            'student_type' => ['nullable', 'string', Rule::enum(StudentType::class)],
            'record_id' => 'nullable|integer',
            'otp' => $canSkipOtp ? ['nullable', 'string'] : ['required', 'string'],
        ];

        $identifierField = $request->filled('identifier')
            ? 'identifier'
            : ($request->filled('student_id') ? 'student_id' : ($request->filled('lrn') ? 'lrn' : 'identifier'));
        $validationRules[$identifierField] = 'required|string|max:64';

        $validator = Validator::make($request->all(), $validationRules);

        if ($validator->fails()) {
            return back()
                ->withErrors($validator->errors())
                ->withInput($request->except(['password', 'password_confirmation']));
        }

        if (User::whereRaw('LOWER(email) = ?', [$email])->exists()) {
            return back()
                ->withErrors(['email' => 'An account with this email already exists.'])
                ->withInput($request->except(['password', 'password_confirmation']));
        }

        // Verify OTP unless the active OAuth provider cryptographically verified this exact email.
        if (! $canSkipOtp) {
            $otp = preg_replace('/[\s-]+/', '', (string) $request->otp) ?? '';
            if (! $this->signupOtps->verify($email, $otp)) {
                return back()
                    ->withErrors(['otp' => $this->signupOtpError($email)])
                    ->withInput($request->except(['password', 'password_confirmation']));
            }
        }

        try {
            $user = DB::transaction(function () use ($request, $studentType, $email, $identifierField, $hasPendingSocial, $passwordSubmitted): User {
                if (User::whereRaw('LOWER(email) = ?', [$email])->lockForUpdate()->exists()) {
                    throw ValidationException::withMessages([
                        'email' => ['An account with this email already exists.'],
                    ]);
                }

                $student = $this->studentOrganizations->resolveForSignup(
                    email: $email,
                    studentType: filled($studentType) ? StudentType::from((string) $studentType) : null,
                    identifier: $request->string($identifierField)->toString(),
                    recordId: $request->input('record_id'),
                    lockForUpdate: true,
                    inputField: $identifierField,
                );

                $requirePasswordOnSocial = (bool) ($this->studentConfig->get($student->school_id)['signup']['require_password_on_social_signup'] ?? false);
                if (! $passwordSubmitted && (! $hasPendingSocial || $requirePasswordOnSocial)) {
                    throw ValidationException::withMessages([
                        'password' => ['Your institution requires creating a portal password during sign-up.'],
                    ]);
                }

                // Always derive role from the matched authoritative student record.
                $role = $student->student_type === StudentType::SeniorHighSchool
                    ? UserRole::ShsStudent
                    : UserRole::Student;

                $spatieRole = Role::firstOrCreate(
                    ['name' => $role->value, 'guard_name' => 'web'],
                    ['name' => $role->value, 'guard_name' => 'web'],
                );

                $rawPassword = $passwordSubmitted ? (string) $request->password : Str::password(40);

                $user = User::create([
                    'name' => $request->name,
                    'email' => $email,
                    'password' => Hash::make($rawPassword),
                    'role' => $role,
                    'record_id' => $student->id,
                    'school_id' => $student->school_id,
                    'email_verified_at' => now(),
                    'avatar_url' => $this->pendingSocial->pendingAvatar($request, $email, allowDifferentEmail: true),
                ]);

                if (! $passwordSubmitted) {
                    $user->markPasswordProvisioned(false);
                    $user->save();
                }

                $user->assignRole($spatieRole);
                $this->studentOrganizations->assign($user, $student);

                return $user;
            });
        } catch (ValidationException $exception) {
            return back()
                ->withErrors($exception->errors())
                ->withInput($request->except(['password', 'password_confirmation']));
        }

        if (! $canSkipOtp) {
            $this->signupOtps->forget($email);
        }

        $linkedProvider = $this->pendingSocial->linkToUser($request, $user, allowDifferentEmail: true);

        Auth::login($user);
        $request->session()->regenerate();

        $redirect = redirect('/student/dashboard');

        if ($linkedProvider !== null) {
            return $redirect->with('status', 'Welcome! Your account is active and '.ucfirst($linkedProvider).' is linked.');
        }

        return $redirect;
    }

    /**
     * Handle faculty signup
     */
    private function signupFaculty(Request $request)
    {
        $email = $request->string('email')->trim()->lower()->toString();
        $hasPendingSocial = $this->pendingSocial->hasActive($request);
        $canSkipOtp = $this->pendingSocial->canSkipEmailOtp($request, $email);
        $passwordSubmitted = $request->filled('password') || $request->filled('password_confirmation');

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255',
            'password' => $hasPendingSocial && ! $passwordSubmitted
                ? ['nullable', 'string']
                : ['required', 'confirmed', Password::defaults()],
            'faculty_id_number' => 'nullable|string|max:255',
            'otp' => $canSkipOtp ? ['nullable', 'string'] : ['required', 'string'],
        ]);

        if ($validator->fails()) {
            return back()
                ->withErrors($validator->errors())
                ->withInput($request->except(['password', 'password_confirmation']));
        }

        if (User::whereRaw('LOWER(email) = ?', [$email])->exists()) {
            return back()
                ->withErrors(['email' => 'An account with this email already exists.'])
                ->withInput($request->except(['password', 'password_confirmation']));
        }

        // Verify OTP unless the active OAuth provider cryptographically verified this exact email.
        if (! $canSkipOtp) {
            $otp = preg_replace('/[\s-]+/', '', (string) $request->otp) ?? '';
            if (! $this->signupOtps->verify($email, $otp)) {
                return back()
                    ->withErrors(['otp' => $this->signupOtpError($email)])
                    ->withInput($request->except(['password', 'password_confirmation']));
            }
        }

        try {
            $user = DB::transaction(function () use ($request, $email, $hasPendingSocial, $passwordSubmitted): User {
                if (User::whereRaw('LOWER(email) = ?', [$email])->lockForUpdate()->exists()) {
                    throw ValidationException::withMessages([
                        'email' => ['An account with this email already exists.'],
                    ]);
                }

                // The record is resolved inside the transaction so the roster row stays
                // locked while the account is written against it.
                $faculty = $this->facultySignups->resolveForSignup(
                    email: $email,
                    facultyIdNumber: $request->string('faculty_id_number')->trim()->toString() ?: null,
                    lockForUpdate: true,
                );

                $requirePasswordOnSocial = (bool) ($this->studentConfig->get($faculty->school_id)['signup']['require_password_on_social_signup'] ?? false);
                if (! $passwordSubmitted && (! $hasPendingSocial || $requirePasswordOnSocial)) {
                    throw ValidationException::withMessages([
                        'password' => ['Your institution requires creating a portal password during sign-up.'],
                    ]);
                }

                // The role is read from the institutional record, never from the request.
                $role = $this->facultySignups->roleFor($faculty);

                // Create or get the spatie role
                $spatieRole = Role::firstOrCreate(
                    ['name' => $role->value, 'guard_name' => 'web'],
                    ['name' => $role->value, 'guard_name' => 'web']
                );

                $rawPassword = $passwordSubmitted ? (string) $request->password : Str::password(40);

                $user = User::create([
                    'name' => $request->name,
                    'email' => $email,
                    'password' => Hash::make($rawPassword),
                    'role' => $role,
                    'email_verified_at' => now(),
                    'avatar_url' => $this->pendingSocial->pendingAvatar($request, $email, allowDifferentEmail: true),
                ]);

                if (! $passwordSubmitted) {
                    $user->markPasswordProvisioned(false);
                    $user->save();
                }

                // Assign spatie role
                $user->assignRole($spatieRole);

                $this->facultySignups->assign($user, $faculty);

                return $user;
            });
        } catch (ValidationException $exception) {
            return back()
                ->withErrors($exception->errors())
                ->withInput($request->except(['password', 'password_confirmation']));
        }

        // Clear OTP after successful verification
        if (! $canSkipOtp) {
            $this->signupOtps->forget($email);
        }

        $linkedProvider = $this->pendingSocial->linkToUser($request, $user, allowDifferentEmail: true);

        Auth::login($user);
        $request->session()->regenerate();

        $redirect = redirect('/faculty/dashboard');

        if ($linkedProvider !== null) {
            return $redirect->with('status', 'Welcome! Your account is active and '.ucfirst($linkedProvider).' is linked.');
        }

        return $redirect;
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
     * Determine the redirect URL based on user role
     */
    private function getRedirectForUser(User $user): string
    {
        return $user->homeUrl();
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
}
