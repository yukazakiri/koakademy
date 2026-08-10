<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\PaymentMethod;
use App\Features\Toggles\AdminDeveloperMode;
use App\Features\Toggles\FacultyDeveloperMode;
use App\Features\Toggles\StudentDeveloperMode;
use App\Features\Toggles\StudentInformationUpdates;
use App\Http\Requests\ToggleExperimentalFeaturesRequest;
use App\Http\Requests\UpdatePaymentWorkspacePreferencesRequest;
use App\Http\Requests\UpdateStudentProfileRequest;
use App\Models\ConnectedAccount;
use App\Models\Faculty;
use App\Models\GeneralSetting;
use App\Models\Student;
use App\Models\User;
use App\Services\DigitalIdCardService;
use App\Services\FeatureToggleRegistry;
use App\Services\StudentProfileCompletionService;
use App\Services\StudentSchoolOptionService;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\RoundBlockSizeMode;
use Endroid\QrCode\Writer\PngWriter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Pennant\Feature;
use PragmaRX\Google2FA\Google2FA;

final class ProfileController extends Controller
{
    /**
     * Display the profile page
     */
    public function index(Request $request): Response
    {
        $user = Auth::user();

        // Get faculty information if exists
        $faculty = Faculty::where('email', $user->email)->first();
        // Get student information if exists
        $student = Student::where('user_id', $user->id)
            ->with(['studentContactsInfo', 'studentEducationInfo', 'studentParentInfo', 'Course'])
            ->first();

        $connectedAccounts = [
            'providers' => [],
            'accounts' => [],
        ];
        if (Schema::hasTable('connected_accounts')) {
            $accounts = ConnectedAccount::query()
                ->where('user_id', $user->id)
                ->orderByDesc('created_at')
                ->get();

            $connectedAccounts = [
                'providers' => $accounts
                    ->pluck('provider')
                    ->unique()
                    ->mapWithKeys(fn ($provider): array => [$provider => true])
                    ->toArray(),
                'accounts' => $accounts
                    ->map(fn (ConnectedAccount $account): array => [
                        'id' => $account->id,
                        'provider' => $account->provider,
                        'provider_id' => $account->provider_id,
                        'name' => $account->name,
                        'nickname' => $account->nickname,
                        'email' => $account->email,
                        'avatar_path' => $account->avatar_path,
                        'created_at' => $account->created_at?->toIso8601String(),
                    ])
                    ->values()
                    ->all(),
            ];
        }

        // Generate ID card data
        $idCardService = app(DigitalIdCardService::class);
        $idCardData = $idCardService->generateIdCardForUser($user);

        $experimentalKeys = config('onboarding.experimental_feature_keys', []);
        $experimentalRoles = config('onboarding.experimental_features_roles', []);
        $userRole = $user->role?->value ?? 'user';

        $isFaculty = in_array($userRole, ['professor', 'associate_professor', 'assistant_professor', 'instructor', 'part_time_faculty'], true);
        $isStudent = in_array($userRole, ['student', 'graduate_student', 'shs_student'], true);
        $isAdmin = ! $isFaculty && ! $isStudent && $user->role?->canAccessAdminPortal();

        $roleType = match (true) {
            $isFaculty => 'faculty',
            $isStudent => 'student',
            $isAdmin => 'admin',
            default => 'other',
        };

        $availableForRole = collect($experimentalKeys)
            ->filter(fn (string $featureKey): bool => isset($experimentalRoles[$featureKey]) && in_array($roleType, $experimentalRoles[$featureKey], true))
            ->values()
            ->all();

        $developerModeFeature = $this->developerModeFeatureFor($user);

        $developerModeEnabled = $developerModeFeature !== null && Feature::for($user)->active($developerModeFeature);
        $studentInformationUpdatesEnabled = $isStudent && Feature::for($user)->active(StudentInformationUpdates::class);
        $studentProfileCompletion = app(StudentProfileCompletionService::class)->summarize($student);
        $canViewNewsletterSettings = $isAdmin && $user->can('viewNewsletter', GeneralSetting::class);
        $canConfigurePaymentWorkspace = $request->is('administrators/*')
            && $isAdmin
            && $user instanceof User
            && $user->can('View:Cashier');

        $apiTokens = [];
        if ($developerModeEnabled) {
            $apiTokens = $user->tokens()
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(fn ($token): array => [
                    'id' => $token->id,
                    'name' => $token->name,
                    'abilities' => $token->abilities ?? ['*'],
                    'last_used_at' => $token->last_used_at?->diffForHumans(),
                    'expires_at' => $token->expires_at?->format('Y-m-d H:i:s'),
                    'created_at' => $token->created_at->format('Y-m-d H:i:s'),
                ])
                ->values()
                ->all();
        }

        return Inertia::render('profile', [
            'connected_accounts' => $connectedAccounts,
            'can_view_newsletter_settings' => $canViewNewsletterSettings,
            'newsletter_settings_url' => $canViewNewsletterSettings
                ? route('administrators.settings.newsletter.index', absolute: false)
                : null,
            'can_configure_payment_workspace' => $canConfigurePaymentWorkspace,
            'payment_workspace' => $canConfigurePaymentWorkspace
                ? $this->paymentWorkspacePreferences($user)
                : null,
            'payment_workspace_url' => $canConfigurePaymentWorkspace && $request->is('administrators/*')
                ? route('administrators.settings.payment-workspace.update', absolute: false)
                : null,
            'payment_methods' => $canConfigurePaymentWorkspace ? PaymentMethod::options() : [],
            'id_card' => $idCardData,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'avatar_url' => $user->avatar_url ?? null,
                'role' => $user->role?->value ?? 'user',
                'phone' => $user->phone,
                'address' => $user->address,
                'city' => $user->city,
                'state' => $user->state,
                'country' => $user->country,
                'postal_code' => $user->postal_code,
                'bio' => $user->bio,
                'website' => $user->website,
                'security_two_factor_enabled' => $user->security_two_factor_enabled ?? true,
                'two_factor_enabled' => ! is_null($user->app_authentication_secret),
                'email_two_factor_enabled' => $user->hasEmailAuthentication(),
                'recovery_codes' => $user->app_authentication_recovery_codes,
            ],
            'feature_flags' => [
                'experimental' => collect($availableForRole)
                    ->filter(function (string $featureKey) use ($user): bool {
                        $featureClass = FeatureToggleRegistry::classForKey(str_replace('onboarding-', '', $featureKey));

                        return (bool) Feature::for($user)->active($featureClass ?? $featureKey);
                    })
                    ->values()
                    ->all(),
                'experimental_available' => $availableForRole,
                'developer_mode_enabled' => $developerModeEnabled,
                'student_information_updates' => $studentInformationUpdatesEnabled,
            ],
            'student_profile_completion' => $studentProfileCompletion,
            'api_tokens' => $apiTokens,
            'sessions' => $this->getSessions($request),
            'faculty' => $faculty ? [
                'id' => $faculty->id,
                'first_name' => $faculty->first_name,
                'last_name' => $faculty->last_name,
                'middle_name' => $faculty->middle_name,
                'email' => $faculty->email,
                'phone_number' => $faculty->phone_number,
                'department' => $faculty->department,
                'office_hours' => $faculty->office_hours,
                'birth_date' => $faculty->birth_date?->format('Y-m-d'),
                'address_line1' => $faculty->address_line1,
                'biography' => $faculty->biography,
                'education' => $faculty->education,
                'courses_taught' => $faculty->courses_taught,
                'photo_url' => $faculty->photo_url,
                'gender' => $faculty->gender,
                'age' => $faculty->age,
            ] : null,
            'student' => $student ? [
                'id' => $student->id,
                'student_id' => $student->student_id,
                'first_name' => $student->first_name,
                'last_name' => $student->last_name,
                'middle_name' => $student->middle_name,
                'email' => $student->email,
                'phone' => $student->phone,
                'address' => $student->address,
                'civil_status' => $student->civil_status,
                'nationality' => $student->nationality,
                'religion' => $student->religion,
                'emergency_contact' => $student->emergency_contact,
                'birth_date' => $student->birth_date?->format('Y-m-d'),
                'gender' => $student->gender,
                'academic_year' => $student->academic_year,
                'formatted_academic_year' => $student->formatted_academic_year,
                'course' => $student->Course ? [
                    'id' => $student->Course->id,
                    'code' => $student->Course->code,
                    'title' => $student->Course->title,
                ] : null,
                'contacts' => $this->studentProfileContacts($student),
                'education' => $this->studentProfileEducation($student),
                'parents' => $this->studentProfileParents($student),
            ] : null,
            'endpoints' => $this->getEndpoints($request),
        ]);
    }

    /**
     * Logout other browser sessions
     */
    public function logoutOtherBrowserSessions(Request $request)
    {
        $password = $request->input('password');

        if (! Hash::check($password, Auth::user()->password)) {
            return back()->withErrors(['password' => 'This password does not match our records.']);
        }

        Auth::logoutOtherDevices($password);

        return back()->with('flash', [
            'success' => 'Logged out of other browser sessions.',
        ]);
    }

    /**
     * Enable Two Factor Authentication
     */
    public function enableTwoFactor()
    {
        $google2fa = new Google2FA();
        $secret = $google2fa->generateSecretKey();

        $user = Auth::user();
        $qrCodeUrl = $google2fa->getQRCodeUrl(
            config('app.name'),
            $user->email,
            $secret
        );

        $result = new Builder(
            writer: new PngWriter(),
            writerOptions: [],
            validateResult: false,
            data: $qrCodeUrl,
            encoding: new Encoding('UTF-8'),
            errorCorrectionLevel: ErrorCorrectionLevel::High,
            size: 200,
            margin: 0,
            roundBlockSizeMode: RoundBlockSizeMode::Margin
        )->build();

        $qrCodeImage = 'data:image/png;base64,'.base64_encode($result->getString());

        return response()->json([
            'secret' => $secret,
            'qr_code' => $qrCodeImage,
        ]);
    }

    /**
     * Confirm and activate Two Factor Authentication
     */
    public function confirmTwoFactor(Request $request)
    {
        $request->validate([
            'code' => 'required|string',
            'secret' => 'required|string',
        ]);

        $google2fa = new Google2FA();
        $valid = $google2fa->verifyKey($request->secret, $request->code);

        if (! $valid) {
            return back()->withErrors(['code' => 'The provided two factor authentication code was invalid.']);
        }

        $user = Auth::user();
        $user->app_authentication_secret = $request->secret;

        // Generate recovery codes
        $recoveryCodes = Collection::times(8, fn (): string => Str::random(10).'-'.Str::random(10))->toArray();
        $user->app_authentication_recovery_codes = $recoveryCodes;

        $user->save();

        return back()->with('flash', [
            'success' => 'Two factor authentication has been enabled.',
            'recovery_codes' => $recoveryCodes,
        ]);
    }

    /**
     * Enable/Disable Email Authentication
     */
    public function toggleEmailAuthentication(Request $request)
    {
        $request->validate([
            'enabled' => 'required|boolean',
        ]);

        $user = Auth::user();
        $user->toggleEmailAuthentication($request->enabled);

        return back()->with('flash', [
            'success' => $request->enabled ? 'Email authentication enabled.' : 'Email authentication disabled.',
        ]);
    }

    public function toggleSecurityTwoFactor(Request $request)
    {
        $request->validate([
            'enabled' => 'required|boolean',
        ]);

        $user = Auth::user();
        $user->toggleSecurityTwoFactor($request->boolean('enabled'));

        return back()->with('flash', [
            'success' => $request->boolean('enabled')
                ? 'Two-factor login challenges enabled.'
                : 'Two-factor login challenges disabled.',
        ]);
    }

    public function toggleExperimentalFeatures(ToggleExperimentalFeaturesRequest $request)
    {
        $user = Auth::user();
        $allowedFeatures = config('onboarding.experimental_feature_keys', []);
        $requestedFeatures = array_values(array_intersect($request->input('features', []), $allowedFeatures));

        foreach ($allowedFeatures as $featureKey) {
            $featureRef = FeatureToggleRegistry::classForKey(str_replace('onboarding-', '', $featureKey)) ?? $featureKey;

            if (in_array($featureKey, $requestedFeatures, true)) {
                Feature::for($user)->activate($featureRef);

                continue;
            }

            Feature::for($user)->deactivate($featureRef);
        }

        return back()->with('flash', [
            'success' => 'Experimental features updated.',
        ]);
    }

    /**
     * Disable Two Factor Authentication
     */
    public function disableTwoFactor(Request $request)
    {
        $request->validate([
            'password' => 'required|string',
        ]);

        if (! Hash::check($request->password, Auth::user()->password)) {
            return back()->withErrors(['password' => 'This password does not match our records.']);
        }

        $user = Auth::user();
        $user->app_authentication_secret = null;
        $user->app_authentication_recovery_codes = null;
        $user->save();

        return back()->with('flash', [
            'success' => 'Two factor authentication has been disabled.',
        ]);
    }

    /**
     * Regenerate Recovery Codes
     */
    public function regenerateRecoveryCodes(Request $request)
    {
        $user = Auth::user();

        if (! $user->app_authentication_secret) {
            return back()->withErrors(['error' => 'Two factor authentication is not enabled.']);
        }

        $recoveryCodes = Collection::times(8, fn (): string => Str::random(10).'-'.Str::random(10))->toArray();
        $user->app_authentication_recovery_codes = $recoveryCodes;
        $user->save();

        return back()->with('flash', [
            'success' => 'Recovery codes have been regenerated.',
            'recovery_codes' => $recoveryCodes,
        ]);
    }

    /**
     * Update user profile information
     */
    public function updateUser(Request $request)
    {
        $user = Auth::user();
        $developerModeEnabled = $this->developerModeEnabledFor($user);

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique('users')->ignore($user->id),
            ],
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:100',
            'country' => 'nullable|string|max:100',
            'postal_code' => 'nullable|string|max:20',
            'bio' => 'nullable|string',
            'website' => [Rule::excludeIf(! $developerModeEnabled), 'nullable', 'url', 'max:255'],
            'avatar' => 'nullable|file|mimes:jpeg,png,jpg,gif,webp|max:5120',
        ]);

        if ($validator->fails()) {
            return back()
                ->withErrors($validator->errors())
                ->withInput($request->only([
                    'name', 'email', 'phone', 'address', 'city', 'state',
                    'country', 'postal_code', 'bio', 'website',
                ]));
        }

        $validated = $validator->validated();

        // Handle avatar upload
        $avatarUrl = $user->avatar_url;
        if ($request->hasFile('avatar')) {
            // Delete old avatar if exists
            if ($user->avatar_url) {
                $oldPath = parse_url((string) $user->avatar_url, PHP_URL_PATH);
                if ($oldPath) {
                    $oldPath = mb_ltrim($oldPath, '/');
                    Storage::delete($oldPath);
                }
            }

            // Upload new avatar
            $file = $request->file('avatar');
            $filename = 'avatar-'.$user->id.'-'.time().'.'.$file->getClientOriginalExtension();
            $path = "avatars/{$filename}";
            Storage::put($path, $file->getContent(), 'public');
            $avatarUrl = env('R2_URL').'/'.$path;
        }

        $attributes = [
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?? null,
            'address' => $validated['address'] ?? null,
            'city' => $validated['city'] ?? null,
            'state' => $validated['state'] ?? null,
            'country' => $validated['country'] ?? null,
            'postal_code' => $validated['postal_code'] ?? null,
            'bio' => $validated['bio'] ?? null,
            'avatar_url' => $avatarUrl,
        ];

        if ($developerModeEnabled) {
            $attributes['website'] = $validated['website'] ?? null;
        }

        $user->update($attributes);

        // If email changed, also update faculty record if exists
        if ($user->wasChanged('email')) {
            Faculty::where('email', $user->getOriginal('email'))->update([
                'email' => $validated['email'],
            ]);
        }

        // Get updated faculty information if exists
        Faculty::where('email', $user->email)->first();

        return back()->with('flash', [
            'success' => 'Profile updated successfully!',
        ]);
    }

    public function updatePaymentWorkspace(UpdatePaymentWorkspacePreferencesRequest $request)
    {
        $user = $request->user();

        if (! $user instanceof User) {
            abort(403);
        }

        $preferences = is_array($user->preferences) ? $user->preferences : [];
        Arr::set($preferences, 'finance.payment_workspace', $request->validated());

        $user->forceFill(['preferences' => $preferences])->save();

        return back()->with('flash', [
            'success' => 'Finance workspace preferences saved.',
        ]);
    }

    /**
     * Update student information
     */
    public function updateStudent(UpdateStudentProfileRequest $request)
    {
        $user = Auth::user();

        if (! Feature::for($user)->active(StudentInformationUpdates::class)) {
            abort(403, 'Student information updates are currently disabled.');
        }

        // Check if student record exists
        $student = Student::where('user_id', $user->id)->first();

        if (! $student) {
            return back()->withErrors(['error' => 'Student record not found.']);
        }

        $validated = $request->validated();

        $student->update([
            'first_name' => $validated['first_name'],
            'middle_name' => $validated['middle_name'] ?? null,
            'last_name' => $validated['last_name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?? null,
            'address' => $validated['address'] ?? null,
            'civil_status' => $validated['civil_status'] ?? null,
            'nationality' => $validated['nationality'] ?? null,
            'religion' => $validated['religion'] ?? null,
            'emergency_contact' => $validated['emergency_contact'] ?? null,
            'birth_date' => $validated['birth_date'] ?? null,
            'gender' => $validated['gender'] ?? null,
        ]);

        $emailChanged = $student->wasChanged('email');

        $this->syncStudentProfileRelations($student, $validated);

        // If email changed, also update user record
        if ($emailChanged) {
            $user->update([
                'email' => $validated['email'],
            ]);
        }

        return back()->with('flash', [
            'success' => 'Student information updated successfully!',
        ]);
    }

    public function studentSchoolOptions(Request $request, StudentSchoolOptionService $schoolOptions): JsonResponse
    {
        $user = Auth::user();

        if (! Feature::for($user)->active(StudentInformationUpdates::class)) {
            abort(403, 'Student information updates are currently disabled.');
        }

        return response()->json($schoolOptions->search(
            (string) $request->query('field', ''),
            (string) $request->query('search', ''),
        ));
    }

    /**
     * Update faculty information
     */
    public function updateFaculty(Request $request)
    {
        $user = Auth::user();

        // Check if faculty record exists
        $faculty = Faculty::where('email', $user->email)->first();

        if (! $faculty) {
            // Create faculty record if it doesn't exist
            $validator = Validator::make($request->all(), [
                'first_name' => 'required|string|max:255',
                'middle_name' => 'nullable|string|max:255',
                'last_name' => 'required|string|max:255',
                'email' => [
                    'required',
                    'string',
                    'email',
                    'max:255',
                    Rule::unique('faculty')->ignore($faculty?->id),
                ],
                'phone_number' => 'nullable|string|max:20',
                'department' => 'nullable|string|max:255',
                'office_hours' => 'nullable|string|max:255',
                'birth_date' => 'nullable|date',
                'address_line1' => 'nullable|string|max:255',
                'biography' => 'nullable|string',
                'education' => 'nullable|string',
                'courses_taught' => 'nullable|string',
                'gender' => 'nullable|in:male,female,other,prefer_not_to_say',
                'age' => 'nullable|integer|min:18|max:100',
            ]);

            if ($validator->fails()) {
                return back()
                    ->withErrors($validator->errors())
                    ->withInput($request->all());
            }

            $validated = $validator->validated();

            Faculty::create([
                'id' => (string) Str::uuid(),
                'first_name' => $validated['first_name'],
                'middle_name' => $validated['middle_name'] ?? null,
                'last_name' => $validated['last_name'],
                'email' => $validated['email'],
                'password' => $user->password, // Inherit password from user account
                'phone_number' => $validated['phone_number'] ?? null,
                'department' => $validated['department'] ?? null,
                'office_hours' => $validated['office_hours'] ?? null,
                'birth_date' => $validated['birth_date'] ?? null,
                'address_line1' => $validated['address_line1'] ?? null,
                'biography' => $validated['biography'] ?? null,
                'education' => $validated['education'] ?? null,
                'courses_taught' => $validated['courses_taught'] ?? null,
                'gender' => $validated['gender'] ?? null,
                'age' => $validated['age'] ?? null,
                'status' => 'active',
            ]);

            return back()->with('flash', [
                'success' => 'Faculty information created successfully!',
            ]);
        }

        // Update existing faculty record
        $validator = Validator::make($request->all(), [
            'first_name' => 'required|string|max:255',
            'middle_name' => 'nullable|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique('faculty')->ignore($faculty->id),
            ],
            'phone_number' => 'nullable|string|max:20',
            'department' => 'nullable|string|max:255',
            'office_hours' => 'nullable|string|max:255',
            'birth_date' => 'nullable|date',
            'address_line1' => 'nullable|string|max:255',
            'biography' => 'nullable|string',
            'education' => 'nullable|string',
            'courses_taught' => 'nullable|string',
            'gender' => 'nullable|in:male,female,other,prefer_not_to_say',
            'age' => 'nullable|integer|min:18|max:100',
        ]);

        if ($validator->fails()) {
            return back()
                ->withErrors($validator->errors())
                ->withInput($request->all());
        }

        $validated = $validator->validated();

        $faculty->update([
            'first_name' => $validated['first_name'],
            'middle_name' => $validated['middle_name'] ?? null,
            'last_name' => $validated['last_name'],
            'email' => $validated['email'],
            'phone_number' => $validated['phone_number'] ?? null,
            'department' => $validated['department'] ?? null,
            'office_hours' => $validated['office_hours'] ?? null,
            'birth_date' => $validated['birth_date'] ?? null,
            'address_line1' => $validated['address_line1'] ?? null,
            'biography' => $validated['biography'] ?? null,
            'education' => $validated['education'] ?? null,
            'courses_taught' => $validated['courses_taught'] ?? null,
            'gender' => $validated['gender'] ?? null,
            'age' => $validated['age'] ?? null,
        ]);

        // If email changed, also update user record
        if ($faculty->wasChanged('email')) {
            $user->update([
                'email' => $validated['email'],
            ]);
        }

        return back()->with('flash', [
            'success' => 'Faculty information updated successfully!',
        ]);
    }

    /**
     * Show change password form
     */
    public function showChangePassword()
    {
        return Inertia::render('profile/change-password');
    }

    /**
     * Change user password
     */
    public function changePassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'current_password' => 'required|string',
            'password' => ['required', 'confirmed', \Illuminate\Validation\Rules\Password::defaults()],
        ]);

        if ($validator->fails()) {
            return back()
                ->withErrors($validator->errors())
                ->withInput($request->only(['password', 'password_confirmation']));
        }

        $user = Auth::user();

        // Verify current password
        if (! Hash::check($request->current_password, $user->password)) {
            return back()
                ->withErrors(['current_password' => 'The current password is incorrect.'])
                ->withInput($request->only(['password', 'password_confirmation']));
        }

        $user->update([
            'password' => Hash::make($request->password),
        ]);

        return back()->with('flash', [
            'success' => 'Password changed successfully!',
        ]);
    }

    /**
     * Redirect to the appropriate profile page based on user role
     */
    public function redirect()
    {
        $user = Auth::user();

        if ($user->isFaculty()) {
            return redirect()->route('faculty.profile');
        }

        if ($user->isStudentRole()) {
            return redirect()->route('student.profile');
        }

        if ($user->isAdministrative()) {
            return redirect()->route('filament.admin.auth.profile');
        }

        // Fallback
        return redirect('/dashboard');
    }

    /**
     * @return array<string, mixed>|null
     */
    private function studentProfileContacts(Student $student): ?array
    {
        $contacts = $student->studentContactsInfo;

        if (! $contacts) {
            return null;
        }

        return [
            'emergency_contact_name' => $contacts->emergency_contact_name,
            'emergency_contact_phone' => $contacts->emergency_contact_phone,
            'emergency_contact_relationship' => $contacts->emergency_contact_relationship,
            'facebook' => $contacts->facebook ?? $contacts->facebook_contact,
            'personal_contact' => $contacts->personal_contact,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function studentProfileEducation(Student $student): ?array
    {
        $education = $student->studentEducationInfo;

        if (! $education) {
            return null;
        }

        return [
            'elementary_school' => $education->elementary_school,
            'elementary_year_graduated' => $education->elementary_year_graduated ?? $education->elementary_graduate_year,
            'high_school' => $education->high_school ?? $education->junior_high_school_name,
            'high_school_year_graduated' => $education->high_school_year_graduated ?? $education->junior_high_graduation_year,
            'senior_high_school' => $education->senior_high_school ?? $education->senior_high_name,
            'senior_high_year_graduated' => $education->senior_high_year_graduated ?? $education->senior_high_graduate_year,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function studentProfileParents(Student $student): ?array
    {
        $parents = $student->studentParentInfo;

        if (! $parents) {
            return null;
        }

        return [
            'father_name' => $parents->father_name ?? $parents->fathers_name,
            'mother_name' => $parents->mother_name ?? $parents->mothers_name,
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function syncStudentProfileRelations(Student $student, array $validated): void
    {
        $studentContactId = $this->upsertProfileRelatedRecord(
            'student_contacts',
            $student->student_contact_id,
            $this->studentContactAttributes($student, $validated),
        );

        if ($studentContactId !== null) {
            $student->student_contact_id = $studentContactId;
        }

        $studentEducationInfoId = $this->upsertProfileRelatedRecord(
            'student_education_info',
            $student->student_education_id,
            $this->studentEducationAttributes($validated),
        );

        if ($studentEducationInfoId !== null) {
            $student->student_education_id = $studentEducationInfoId;
        }

        $studentParentInfoId = $this->upsertProfileRelatedRecord(
            'student_parents_info',
            $student->student_parent_info,
            $this->studentParentAttributes($validated),
        );

        if ($studentParentInfoId !== null) {
            $student->student_parent_info = $studentParentInfoId;
        }

        if ($student->isDirty(['student_contact_id', 'student_education_id', 'student_parent_info'])) {
            $student->save();
        }
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function upsertProfileRelatedRecord(string $table, ?int $id, array $attributes): ?int
    {
        $attributes = $this->existingColumnAttributes($table, $attributes);

        if ($id !== null) {
            if ($attributes !== []) {
                if (Schema::hasColumn($table, 'updated_at')) {
                    $attributes['updated_at'] = now();
                }

                DB::table($table)->where('id', $id)->update($attributes);
            }

            return $id;
        }

        $attributes = $this->withoutBlankValues($attributes);

        if ($attributes === []) {
            return null;
        }

        if (Schema::hasColumn($table, 'created_at')) {
            $attributes['created_at'] = now();
        }

        if (Schema::hasColumn($table, 'updated_at')) {
            $attributes['updated_at'] = now();
        }

        return (int) DB::table($table)->insertGetId($attributes);
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function existingColumnAttributes(string $table, array $attributes): array
    {
        $filtered = [];

        foreach ($attributes as $key => $value) {
            if (! Schema::hasColumn($table, (string) $key)) {
                continue;
            }

            $filtered[(string) $key] = $value;
        }

        return $filtered;
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function withoutBlankValues(array $attributes): array
    {
        return array_filter(
            $attributes,
            static fn (mixed $value): bool => $value !== null && $value !== ''
        );
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function studentContactAttributes(Student $student, array $validated): array
    {
        $contactData = $validated['contacts'] ?? [];

        return [
            'student_id' => $student->id,
            'personal_contact' => $contactData['personal_contact'] ?? null,
            'facebook_contact' => $contactData['facebook'] ?? null,
            'facebook' => $contactData['facebook'] ?? null,
            'emergency_contact_name' => $contactData['emergency_contact_name'] ?? null,
            'emergency_contact_phone' => $contactData['emergency_contact_phone'] ?? null,
            'emergency_contact_address' => $validated['emergency_contact'] ?? null,
            'emergency_contact_relationship' => $contactData['emergency_contact_relationship'] ?? null,
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function studentEducationAttributes(array $validated): array
    {
        $educationData = $validated['education'] ?? [];

        return [
            'elementary_school' => $educationData['elementary_school'] ?? null,
            'elementary_graduate_year' => $educationData['elementary_year_graduated'] ?? null,
            'elementary_year_graduated' => $educationData['elementary_year_graduated'] ?? null,
            'high_school' => $educationData['high_school'] ?? null,
            'junior_high_school_name' => $educationData['high_school'] ?? null,
            'high_school_year_graduated' => $educationData['high_school_year_graduated'] ?? null,
            'junior_high_graduation_year' => $educationData['high_school_year_graduated'] ?? null,
            'senior_high_school' => $educationData['senior_high_school'] ?? null,
            'senior_high_name' => $educationData['senior_high_school'] ?? null,
            'senior_high_year_graduated' => $educationData['senior_high_year_graduated'] ?? null,
            'senior_high_graduate_year' => $educationData['senior_high_year_graduated'] ?? null,
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function studentParentAttributes(array $validated): array
    {
        $parentData = $validated['parents'] ?? [];

        return [
            'father_name' => $parentData['father_name'] ?? null,
            'fathers_name' => $parentData['father_name'] ?? null,
            'mother_name' => $parentData['mother_name'] ?? null,
            'mothers_name' => $parentData['mother_name'] ?? null,
        ];
    }

    /**
     * Get active sessions
     */
    private function getSessions(Request $request): Collection
    {
        if (config('session.driver') !== 'database') {
            return collect();
        }

        return DB::table('sessions')
            ->where('user_id', Auth::id())
            ->orderBy('last_activity', 'desc')
            ->get()
            ->map(fn ($session): array => [
                'id' => $session->id,
                'ip_address' => $session->ip_address,
                'is_current_device' => $session->id === $request->session()->getId(),
                'last_active' => Carbon::createFromTimestamp($session->last_activity)->diffForHumans(),
                'user_agent' => $session->user_agent,
            ]);
    }

    /**
     * @return array{layout: string, density: string, history_visibility: string, default_payment_method: string}
     */
    private function paymentWorkspacePreferences(User $user): array
    {
        $workspace = data_get($user->preferences, 'finance.payment_workspace', []);

        return [
            'layout' => in_array(data_get($workspace, 'layout'), ['guided', 'spreadsheet'], true)
                ? data_get($workspace, 'layout')
                : 'guided',
            'density' => in_array(data_get($workspace, 'density'), ['comfortable', 'compact'], true)
                ? data_get($workspace, 'density')
                : 'comfortable',
            'history_visibility' => in_array(data_get($workspace, 'history_visibility'), ['auto', 'open', 'hidden'], true)
                ? data_get($workspace, 'history_visibility')
                : 'auto',
            'default_payment_method' => PaymentMethod::tryFrom((string) data_get($workspace, 'default_payment_method'))?->value
                ?? PaymentMethod::Cash->value,
        ];
    }

    /**
     * Get the appropriate endpoints based on the portal context
     *
     * @return array<string, string>
     */
    private function getEndpoints(Request $request): array
    {
        // Determine the base path based on which portal is being accessed
        $basePath = match (true) {
            $request->is('administrators/*') => '/administrators/settings',
            $request->is('faculty/*') => '/faculty/profile',
            $request->is('student/*') => '/student/profile',
            default => '/profile',
        };

        // For administrators, use 'settings' as the base instead of 'profile'
        $request->is('administrators/*');
        $hasFacultyUpdateEndpoint = $request->is('administrators/*') || $request->is('faculty/*');
        $hasStudentUpdateEndpoint = $request->is('administrators/*') || $request->is('student/*');

        $endpoints = [
            'profile_update' => $basePath,
            'password_update' => $basePath.'/password',
            'passkeys' => $basePath.'/passkeys',
            'passkeys_options' => $basePath.'/passkeys/options',
            'two_factor_enable' => $basePath.'/two-factor-authentication/enable',
            'two_factor_confirm' => $basePath.'/two-factor-authentication/confirm',
            'two_factor_disable' => $basePath.'/two-factor-authentication',
            'two_factor_recovery_codes' => $basePath.'/two-factor-authentication/recovery-codes',
            'security_two_factor_toggle' => $basePath.'/two-factor-authentication/login-challenges',
            'email_auth_toggle' => $basePath.'/email-authentication',
            'experimental_features' => $basePath.'/experimental-features',
            'browser_sessions_logout' => $basePath.'/other-browser-sessions',
            'api_keys' => $basePath.'/api-keys',
        ];

        // Only faculty and admin portals have faculty update endpoint
        if ($hasFacultyUpdateEndpoint) {
            $endpoints['faculty_update'] = $basePath.'/faculty';
        }

        // Only student and admin portals have student update endpoint
        if ($hasStudentUpdateEndpoint) {
            $endpoints['student_update'] = $basePath.'/student';
        }

        if ($request->is('student/*')) {
            $endpoints['school_options'] = $basePath.'/school-options';
        }

        return $endpoints;
    }

    /**
     * @return class-string|null
     */
    private function developerModeFeatureFor(User $user): ?string
    {
        return match (true) {
            $user->isFaculty() => FacultyDeveloperMode::class,
            $user->isStudentRole() => StudentDeveloperMode::class,
            $user->role?->canAccessAdminPortal() === true => AdminDeveloperMode::class,
            default => null,
        };
    }

    private function developerModeEnabledFor(User $user): bool
    {
        $feature = $this->developerModeFeatureFor($user);

        return $feature !== null && Feature::for($user)->active($feature);
    }
}
