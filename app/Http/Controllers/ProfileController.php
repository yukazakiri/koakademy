<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\PaymentMethod;
use App\Features\Toggles\AdminDeveloperMode;
use App\Features\Toggles\FacultyDeveloperMode;
use App\Features\Toggles\StudentDeveloperMode;
use App\Features\Toggles\StudentInformationUpdates;
use App\Filament\Auth\MultiFactor\SecurityAwareAppAuthentication;
use App\Http\Requests\ToggleExperimentalFeaturesRequest;
use App\Http\Requests\UpdateBetaAccessRequest;
use App\Http\Requests\UpdatePaymentWorkspacePreferencesRequest;
use App\Http\Requests\UpdateStudentProfileRequest;
use App\Http\Requests\UpdateTuitionAdjustmentWorkspacePreferencesRequest;
use App\Models\ConnectedAccount;
use App\Models\Faculty;
use App\Models\GeneralSetting;
use App\Models\Student;
use App\Models\User;
use App\Notifications\SecurityActivityNotification;
use App\Services\BetaAccessService;
use App\Services\DigitalIdCardService;
use App\Services\FeatureToggleRegistry;
use App\Services\StudentProfileCompletionService;
use App\Services\StudentSchoolOptionService;
use App\Services\UserSecurityActivityService;
use App\Settings\SiteSettings;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\RoundBlockSizeMode;
use Endroid\QrCode\Writer\PngWriter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Jenssegers\Agent\Agent;
use Laravel\Pennant\Feature;
use PragmaRX\Google2FA\Google2FA;
use Spatie\Activitylog\Models\Activity;

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
            ->with(['studentContactsInfo', 'studentEducationInfo', 'studentParentInfo', 'personalInfo', 'Course'])
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
            ->filter(function (string $featureKey) use ($user): bool {
                $key = FeatureToggleRegistry::canonicalKey($featureKey);
                if (str_ends_with($key, '-developer-mode') || FeatureToggleRegistry::classForKey($key) === null) {
                    return false;
                }
                $definition = app(\App\Services\FeatureAccessService::class)->definition($key);
                if ($definition?->mode !== 'enforced') {
                    return true;
                }
                $catalog = FeatureToggleRegistry::definition($key);

                return $definition->release_audience === null && $catalog['opt_in'] && $definition->allow_opt_in && in_array($user->role?->value, $catalog['roles'], true);
            })
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
        $canConfigureTuitionAdjustmentWorkspace = $request->is('administrators/*')
            && $isAdmin
            && $user instanceof User
            && $user->can('view_tuition_fees');

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

        $integrationSettings = app(\App\Services\ApplicationIntegrationSettingsService::class);
        $calendarFeedAllowed = $integrationSettings->isCalendarFeedAllowedFor($user);

        return Inertia::render('profile', [
            'beta_access' => app(BetaAccessService::class)->profile($user),
            'connected_accounts' => $connectedAccounts,
            'calendar_feed' => $calendarFeedAllowed ? [
                'enabled' => true,
                'feed_url' => app(\App\Services\CalendarFeedService::class)->getFeedUrl($user),
                'webcal_url' => app(\App\Services\CalendarFeedService::class)->getWebcalUrl($user),
                'download_url' => route('calendar.download', absolute: false),
            ] : [
                'enabled' => false,
                'feed_url' => null,
                'webcal_url' => null,
                'download_url' => null,
            ],
            'integrations_status' => [
                'google_calendar_enabled' => $integrationSettings->isGoogleCalendarEnabled(),
                'calendar_feed_enabled' => $calendarFeedAllowed,
                'microsoft_enabled' => $integrationSettings->isMicrosoftEnabled(),
                'google_drive_enabled' => $integrationSettings->isGoogleDriveEnabled(),
                'onedrive_enabled' => $integrationSettings->isOneDriveEnabled(),
            ],
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
            'can_configure_tuition_adjustment_workspace' => $canConfigureTuitionAdjustmentWorkspace,
            'tuition_adjustment_workspace' => $canConfigureTuitionAdjustmentWorkspace
                ? ['layout' => data_get($user->preferences, 'finance.tuition_adjustments.layout', 'inspector')]
                : null,
            'tuition_adjustment_workspace_url' => $canConfigureTuitionAdjustmentWorkspace
                ? route('administrators.settings.tuition-adjustment-workspace.update', absolute: false)
                : null,
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
                'has_password' => $user->hasProvisionedPassword(),
                'recovery_codes_count' => is_array($user->app_authentication_recovery_codes)
                    ? count($user->app_authentication_recovery_codes)
                    : 0,
            ],
            'newly_generated_recovery_codes' => session('flash.recovery_codes'),
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
            'income_modes' => $this->profileIncomeModes(),
            'default_income_mode' => (string) config('income_brackets.default_mode', 'annual'),
            'api_tokens' => $apiTokens,
            'sessions' => $this->getSessions($request),
            'security_activity' => $this->getSecurityActivity($user),
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
                'personal_info' => $this->studentProfilePersonalInfo($student),
                'ethnicity' => $student->ethnicity,
                'city_of_origin' => $student->city_of_origin,
                'province_of_origin' => $student->province_of_origin,
                'region_of_origin' => $student->region_of_origin,
                'is_indigenous_person' => $student->is_indigenous_person,
                'indigenous_group' => $student->indigenous_group,
                'is_pwd' => $student->is_pwd,
                'pwd_type' => $student->pwd_type,
                'is_solo_parent' => $student->is_solo_parent,
                'is_senior_citizen' => $student->is_senior_citizen,
                'is_magna_carta' => $student->is_magna_carta,
                'is_underprivileged' => $student->is_underprivileged,
                'is_first_generation' => $student->is_first_generation,
                'income_bracket_mode' => $student->income_bracket_mode,
                'use_same_parent_income' => $student->use_same_parent_income,
                'family_income_bracket' => $student->family_income_bracket,
                'father_income_bracket' => $student->father_income_bracket,
                'mother_income_bracket' => $student->mother_income_bracket,
                'profile_reporting_confirmed_at' => $student->profile_reporting_confirmed_at?->toIso8601String(),
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
    public function enableTwoFactor(Request $request)
    {
        $google2fa = new Google2FA();
        $secret = $google2fa->generateSecretKey();

        $user = Auth::user();
        $request->session()->put('two_factor.pending_secret', $secret);

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
        ]);

        $secret = $request->session()->get('two_factor.pending_secret');

        if (! is_string($secret) || $secret === '') {
            return back()->withErrors(['code' => 'Two-factor setup session expired. Please start setup again.']);
        }

        $code = preg_replace('/[\s-]+/', '', (string) $request->input('code')) ?? '';
        $google2fa = new Google2FA();
        $valid = $google2fa->verifyKey($secret, $code);

        if (! $valid) {
            return back()->withErrors(['code' => 'The provided two factor authentication code was invalid.']);
        }

        /** @var User $user */
        $user = Auth::user();
        $user->app_authentication_secret = $secret;
        $user->save();

        // Generate and hash recovery codes using Filament's AppAuthentication provider
        // so codes work identically across Web, Filament Admin, and Mobile API.
        $appAuth = app(SecurityAwareAppAuthentication::class);
        $recoveryCodes = $appAuth->generateRecoveryCodes();
        $appAuth->saveRecoveryCodes($user, $recoveryCodes);

        $request->session()->forget('two_factor.pending_secret');
        app(UserSecurityActivityService::class)->record($user, 'mfa_app_enabled', 'success');

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
            'password' => ['nullable', 'string'],
        ]);

        /** @var User $user */
        $user = Auth::user();
        $enabled = $request->boolean('enabled');

        if (! $enabled) {
            $password = (string) $request->input('password', '');
            if ($password === '' || ! Hash::check($password, $user->password)) {
                return back()->withErrors(['password' => 'Please confirm your current password to disable email authentication.']);
            }
        }

        $user->toggleEmailAuthentication($enabled);
        app(UserSecurityActivityService::class)->record(
            $user,
            $enabled ? 'mfa_email_enabled' : 'mfa_email_disabled',
            'success',
        );

        if (! $enabled) {
            $user->notify(new SecurityActivityNotification(
                actionTitle: 'Email Verification Codes Disabled',
                actionSummary: 'Email one-time verification codes were turned off for your account.',
                ipAddress: $request->ip(),
                userAgent: $request->userAgent(),
            ));
        }

        return back()->with('flash', [
            'success' => $enabled ? 'Email authentication enabled.' : 'Email authentication disabled.',
        ]);
    }

    public function toggleSecurityTwoFactor(Request $request)
    {
        $request->validate([
            'enabled' => 'required|boolean',
            'password' => ['nullable', 'string'],
        ]);

        /** @var User $user */
        $user = Auth::user();
        $enabled = $request->boolean('enabled');

        if (! $enabled) {
            $password = (string) $request->input('password', '');
            if ($password === '' || ! Hash::check($password, $user->password)) {
                return back()->withErrors(['password' => 'Please confirm your current password to pause two-factor login protection.']);
            }
        }

        $user->toggleSecurityTwoFactor($enabled);
        app(UserSecurityActivityService::class)->record(
            $user,
            $enabled ? 'mfa_challenges_enabled' : 'mfa_challenges_paused',
            'success',
        );

        if (! $enabled) {
            $user->notify(new SecurityActivityNotification(
                actionTitle: 'Two-Factor Login Protection Paused',
                actionSummary: 'Two-factor login challenges were paused on your account.',
                ipAddress: $request->ip(),
                userAgent: $request->userAgent(),
            ));
        }

        return back()->with('flash', [
            'success' => $enabled
                ? 'Two-factor login challenges enabled.'
                : 'Two-factor login challenges disabled.',
        ]);
    }

    public function updateBetaAccess(UpdateBetaAccessRequest $request, BetaAccessService $beta): RedirectResponse
    {
        $beta->updateEnrollment($request->user(), $request->boolean('enabled'));

        return back()->with('flash', ['success' => $request->boolean('enabled') ? 'You joined Beta Access.' : 'You left Beta Access. Stable features remain available.']);
    }

    public function updateBetaFeaturePreference(Request $request, BetaAccessService $beta): RedirectResponse
    {
        $data = $request->validate(['feature_key' => ['required', 'string', 'max:100'], 'enabled' => ['required', 'boolean']]);
        $beta->updatePreference($request->user(), $data['feature_key'], $request->boolean('enabled'));

        return back()->with('flash', ['success' => 'Beta feature preference saved.']);
    }

    public function toggleExperimentalFeatures(ToggleExperimentalFeaturesRequest $request)
    {
        $user = Auth::user();
        $allowedFeatures = config('onboarding.experimental_feature_keys', []);
        $requestedFeatures = $request->validated('features', []);

        foreach ($requestedFeatures as $featureKey) {
            $key = FeatureToggleRegistry::canonicalKey($featureKey);
            $catalog = FeatureToggleRegistry::definition($key);
            $definition = app(\App\Services\FeatureAccessService::class)->definition($key);
            $eligible = in_array($featureKey, $allowedFeatures, true)
                && FeatureToggleRegistry::classForKey($key) !== null
                && in_array($user->role?->value, $catalog['roles'], true)
                && ! str_ends_with($key, '-developer-mode');
            if (! $eligible || ($definition?->mode === 'enforced' && ($definition->release_audience !== null || ! $catalog['opt_in'] || ! $definition->allow_opt_in))) {
                throw \Illuminate\Validation\ValidationException::withMessages(['features' => 'One or more features are not available for self-service opt-in.']);
            }
        }

        foreach ($allowedFeatures as $featureKey) {
            $key = FeatureToggleRegistry::canonicalKey($featureKey);
            $featureRef = FeatureToggleRegistry::classForKey($key);
            $catalog = FeatureToggleRegistry::definition($key);
            if (! $featureRef || ! in_array($user->role?->value, $catalog['roles'], true) || str_ends_with($key, '-developer-mode')) {
                continue;
            }
            $definition = app(\App\Services\FeatureAccessService::class)->definition($key);
            if ($definition?->mode === 'enforced') {
                if ($definition->release_audience === null && $catalog['opt_in'] && $definition->allow_opt_in) {
                    app(\App\Services\FeatureManagementService::class)->assign($key, 'user', (string) $user->id,
                        in_array($featureKey, $requestedFeatures, true) ? 'allow' : 'deny', $user, 'self_service');
                }

                continue;
            }

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

        /** @var User $user */
        $user = Auth::user();
        $user->app_authentication_secret = null;
        $user->app_authentication_recovery_codes = null;
        $user->save();

        app(UserSecurityActivityService::class)->record($user, 'mfa_app_disabled', 'success');
        $user->notify(new SecurityActivityNotification(
            actionTitle: 'Authenticator App Removed',
            actionSummary: 'Authenticator app two-factor authentication was removed from your account.',
            ipAddress: $request->ip(),
            userAgent: $request->userAgent(),
        ));

        return back()->with('flash', [
            'success' => 'Two factor authentication has been disabled.',
        ]);
    }

    /**
     * Regenerate Recovery Codes
     */
    public function regenerateRecoveryCodes(Request $request)
    {
        $request->validate([
            'password' => 'required|string',
        ]);

        /** @var User $user */
        $user = Auth::user();

        if (! Hash::check((string) $request->input('password'), $user->password)) {
            return back()->withErrors(['password' => 'This password does not match our records.']);
        }

        if (! $user->app_authentication_secret) {
            return back()->withErrors(['error' => 'Two factor authentication is not enabled.']);
        }

        $appAuth = app(SecurityAwareAppAuthentication::class);
        $recoveryCodes = $appAuth->generateRecoveryCodes();
        $appAuth->saveRecoveryCodes($user, $recoveryCodes);

        app(UserSecurityActivityService::class)->record($user, 'mfa_recovery_codes_regenerated', 'success');

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
            $r2BaseUrl = mb_rtrim((string) config('filesystems.disks.r2.url', ''), '/');
            $avatarUrl = $r2BaseUrl !== '' ? $r2BaseUrl.'/'.$path : Storage::url($path);
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

        $previousEmail = (string) $user->email;
        $user->update($attributes);

        // If email changed, synchronize ONLY the authenticated user's own linked Faculty or Student record.
        if ($user->wasChanged('email')) {
            if ($user->isFaculty()) {
                $facultyQuery = Faculty::withoutSchoolScope();
                if (filled($user->record_id) && Str::isUuid((string) $user->record_id)) {
                    $facultyQuery->where('id', (string) $user->record_id);
                } else {
                    $facultyQuery->whereRaw('LOWER(email) = ?', [mb_strtolower(mb_trim($previousEmail))]);
                    if ($user->school_id !== null) {
                        $facultyQuery->where('school_id', $user->school_id);
                    }
                }
                $facultyQuery->update(['email' => $validated['email']]);
            }

            if ($user->role?->isStudent()) {
                Student::withoutSchoolScope()
                    ->where('user_id', $user->id)
                    ->update(['email' => $validated['email']]);
            }

            $this->notifyEmailChanged($user, $previousEmail, (string) $validated['email'], $request);
        }

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

    public function updateTuitionAdjustmentWorkspace(UpdateTuitionAdjustmentWorkspacePreferencesRequest $request)
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $preferences = is_array($user->preferences) ? $user->preferences : [];
        Arr::set($preferences, 'finance.tuition_adjustments', $request->validated());
        $user->forceFill(['preferences' => $preferences])->save();

        return back()->with('flash', [
            'success' => 'Tuition adjustment workspace preference saved.',
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
            'ethnicity' => $validated['ethnicity'] ?? null,
            'city_of_origin' => $validated['city_of_origin'] ?? null,
            'province_of_origin' => $validated['province_of_origin'] ?? null,
            'region_of_origin' => $validated['region_of_origin'] ?? null,
            'is_indigenous_person' => $validated['is_indigenous_person'] ?? false,
            'indigenous_group' => ($validated['is_indigenous_person'] ?? false) ? ($validated['indigenous_group'] ?? null) : null,
            'is_pwd' => $validated['is_pwd'] ?? false,
            'pwd_type' => ($validated['is_pwd'] ?? false) ? ($validated['pwd_type'] ?? null) : null,
            'is_solo_parent' => $validated['is_solo_parent'] ?? false,
            'is_senior_citizen' => $validated['is_senior_citizen'] ?? false,
            'is_magna_carta' => $validated['is_magna_carta'] ?? false,
            'is_underprivileged' => $validated['is_underprivileged'] ?? false,
            'is_first_generation' => $validated['is_first_generation'] ?? false,
            'income_bracket_mode' => $validated['income_bracket_mode'] ?? config('income_brackets.default_mode', 'annual'),
            'use_same_parent_income' => $validated['use_same_parent_income'] ?? true,
            'family_income_bracket' => ($validated['use_same_parent_income'] ?? true) ? ($validated['family_income_bracket'] ?? null) : null,
            'father_income_bracket' => ($validated['use_same_parent_income'] ?? true) ? null : ($validated['father_income_bracket'] ?? null),
            'mother_income_bracket' => ($validated['use_same_parent_income'] ?? true) ? null : ($validated['mother_income_bracket'] ?? null),
        ]);

        $emailChanged = $student->wasChanged('email');

        if ($request->boolean('reporting_confirmed')) {
            $student->forceFill(['profile_reporting_confirmed_at' => now()])->save();
        }

        $this->syncStudentProfileRelations($student, $validated);

        // If email changed, also update user record and alert the previous address
        if ($emailChanged) {
            $previousEmail = (string) $user->email;
            $user->update([
                'email' => $validated['email'],
            ]);

            if (mb_strtolower(mb_trim($previousEmail)) !== mb_strtolower(mb_trim((string) $validated['email']))) {
                $this->notifyEmailChanged($user, $previousEmail, (string) $validated['email'], $request);
            }
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

            $createdFaculty = Faculty::create([
                'id' => (string) Str::uuid(),
                'school_id' => $user->school_id,
                'faculty_id_number' => $user->faculty_id_number,
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

            $user->forceFill([
                'record_id' => $createdFaculty->id,
            ])->save();

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

        // If email changed, also update user record and alert the previous address
        if ($faculty->wasChanged('email')) {
            $previousEmail = (string) $user->email;
            $user->update([
                'email' => $validated['email'],
            ]);

            if (mb_strtolower(mb_trim($previousEmail)) !== mb_strtolower(mb_trim((string) $validated['email']))) {
                $this->notifyEmailChanged($user, $previousEmail, (string) $validated['email'], $request);
            }
        }

        return back()->with('flash', [
            'success' => 'Faculty information updated successfully!',
        ]);
    }

    /**
     * Redirect legacy change-password URLs to the profile Security & Access tab.
     */
    public function showChangePassword(Request $request): RedirectResponse
    {
        if ($request->routeIs('student.*')) {
            return redirect()->route('student.profile', ['tab' => 'accounts']);
        }

        if ($request->routeIs('faculty.*')) {
            return redirect()->route('faculty.profile', ['tab' => 'accounts']);
        }

        return redirect()->to('/profile?tab=accounts');
    }

    /**
     * Change or provision user password
     */
    public function changePassword(Request $request)
    {
        /** @var User $user */
        $user = Auth::user();
        $hasPassword = $user->hasProvisionedPassword();

        $rules = [
            'password' => ['required', 'confirmed', \Illuminate\Validation\Rules\Password::defaults()],
        ];

        if ($hasPassword) {
            $rules['current_password'] = 'required|string';
        }

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return back()
                ->withErrors($validator->errors());
        }

        // Verify current password when the account already has one provisioned.
        if ($hasPassword && ! Hash::check((string) $request->current_password, $user->password)) {
            return back()
                ->withErrors(['current_password' => 'The current password is incorrect.']);
        }

        $user->markPasswordProvisioned(true);
        $user->forceFill([
            'password' => Hash::make($request->password),
            'session_version' => ((int) ($user->session_version ?? 0)) + 1,
            'remember_token' => Str::random(60),
        ])->save();

        $user->tokens()->delete();
        $user->revokeMcpOAuthTokens();

        $request->session()->regenerate();
        $request->session()->put('user_session_version', (int) $user->session_version);
        app(UserSecurityActivityService::class)->record($user, 'password_changed', 'success');
        $user->notify(new SecurityActivityNotification(
            actionTitle: $hasPassword ? 'Password Changed' : 'Password Created',
            actionSummary: $hasPassword
                ? 'Your account password was changed and other active sessions/tokens were signed out.'
                : 'A portal password was created for your account. You can now sign in with your email and password as well.',
            ipAddress: $request->ip(),
            userAgent: $request->userAgent(),
        ));

        return back()->with('flash', [
            'success' => $hasPassword
                ? 'Password changed successfully!'
                : 'Password created successfully! You can now sign in with your email and password.',
        ]);
    }

    /**
     * Redirect to the appropriate profile page based on user role
     */
    public function redirect()
    {
        $user = Auth::user();
        $query = request()->getQueryString();
        $target = null;

        if ($user->isFaculty()) {
            $target = route('faculty.profile');
        } elseif ($user->isStudentRole()) {
            $target = route('student.profile');
        } elseif ($user->isAdministrative()) {
            $target = route('filament.admin.auth.profile');
        }

        if ($target) {
            return redirect($query ? "{$target}?{$query}" : $target);
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
            'twitter' => $contacts->twitter,
            'instagram' => $contacts->instagram,
            'linkedin' => $contacts->linkedin,
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
            'college_school' => $education->college_school,
            'college_course' => $education->college_course,
            'college_year_graduated' => $education->college_year_graduated,
            'vocational_school' => $education->vocational_school,
            'vocational_course' => $education->vocational_course,
            'vocational_year_graduated' => $education->vocational_year_graduated,
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
            'father_occupation' => $parents->father_occupation,
            'father_contact' => $parents->father_contact,
            'father_email' => $parents->father_email,
            'mother_occupation' => $parents->mother_occupation,
            'mother_contact' => $parents->mother_contact,
            'mother_email' => $parents->mother_email,
            'guardian_name' => $parents->guardian_name,
            'guardian_relationship' => $parents->guardian_relationship,
            'guardian_contact' => $parents->guardian_contact,
            'guardian_email' => $parents->guardian_email,
            'family_address' => $parents->family_address,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function studentProfilePersonalInfo(Student $student): ?array
    {
        $personalInfo = $student->personalInfo;

        if (! $personalInfo) {
            return null;
        }

        return [
            'birthplace' => $personalInfo->birthplace ?? $personalInfo->place_of_birth,
            'citizenship' => $personalInfo->citizenship,
            'weight' => $personalInfo->weight,
            'height' => $personalInfo->height,
            'current_address' => $personalInfo->current_adress,
            'permanent_address' => $personalInfo->permanent_address,
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

        $studentPersonalInfoId = $this->upsertProfileRelatedRecord(
            'students_personal_info',
            $student->student_personal_id,
            $this->studentPersonalInfoAttributes($validated),
        );

        if ($studentPersonalInfoId !== null) {
            $student->student_personal_id = $studentPersonalInfoId;
        }

        if ($student->isDirty(['student_contact_id', 'student_education_id', 'student_parent_info', 'student_personal_id'])) {
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
            'twitter' => $contactData['twitter'] ?? null,
            'instagram' => $contactData['instagram'] ?? null,
            'linkedin' => $contactData['linkedin'] ?? null,
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
            'college_school' => $educationData['college_school'] ?? null,
            'college_course' => $educationData['college_course'] ?? null,
            'college_year_graduated' => $educationData['college_year_graduated'] ?? null,
            'vocational_school' => $educationData['vocational_school'] ?? null,
            'vocational_course' => $educationData['vocational_course'] ?? null,
            'vocational_year_graduated' => $educationData['vocational_year_graduated'] ?? null,
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
            'father_occupation' => $parentData['father_occupation'] ?? null,
            'father_contact' => $parentData['father_contact'] ?? null,
            'father_email' => $parentData['father_email'] ?? null,
            'mother_occupation' => $parentData['mother_occupation'] ?? null,
            'mother_contact' => $parentData['mother_contact'] ?? null,
            'mother_email' => $parentData['mother_email'] ?? null,
            'guardian_name' => $parentData['guardian_name'] ?? null,
            'guardian_relationship' => $parentData['guardian_relationship'] ?? null,
            'guardian_contact' => $parentData['guardian_contact'] ?? null,
            'guardian_email' => $parentData['guardian_email'] ?? null,
            'family_address' => $parentData['family_address'] ?? null,
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function studentPersonalInfoAttributes(array $validated): array
    {
        $personalInfo = $validated['personal_info'] ?? [];

        return [
            'birthplace' => $personalInfo['birthplace'] ?? null,
            'place_of_birth' => $personalInfo['birthplace'] ?? null,
            'citizenship' => $personalInfo['citizenship'] ?? null,
            'weight' => $personalInfo['weight'] ?? null,
            'height' => $personalInfo['height'] ?? null,
            'current_adress' => $personalInfo['current_address'] ?? null,
            'permanent_address' => $personalInfo['permanent_address'] ?? null,
        ];
    }

    /**
     * @return array<int, array{value: string, label: string, brackets: array<int, array{value: string, label: string}>}>
     */
    private function profileIncomeModes(): array
    {
        $currency = app(SiteSettings::class)->getCurrency();
        $symbol = $currency === 'PHP' ? '₱' : ($currency === 'USD' ? '$' : $currency);

        return collect(config('income_brackets.modes', []))
            ->map(function (array $modeConfig, string $modeKey) use ($symbol): array {
                $brackets = collect($modeConfig['brackets'] ?? [])
                    ->map(fn (array $bracket, string $key): array => [
                        'value' => $key,
                        'label' => str_replace('{symbol}', $symbol, (string) ($bracket['label'] ?? '')),
                    ])
                    ->values()
                    ->all();

                return [
                    'value' => $modeKey,
                    'label' => (string) ($modeConfig['label'] ?? $modeKey),
                    'brackets' => $brackets,
                ];
            })
            ->values()
            ->all();
    }

    private function notifyEmailChanged(User $user, string $previousEmail, string $newEmail, Request $request): void
    {
        app(UserSecurityActivityService::class)->record($user, 'email_changed', 'success');

        if (filled($previousEmail) && mb_strtolower(mb_trim($previousEmail)) !== mb_strtolower(mb_trim($newEmail))) {
            Notification::route('mail', $previousEmail)->notify(new SecurityActivityNotification(
                actionTitle: 'Account Email Address Changed',
                actionSummary: "The email address on your account was changed from {$previousEmail} to {$newEmail}.",
                ipAddress: $request->ip(),
                userAgent: $request->userAgent(),
            ));
        }
    }

    /**
     * Get recent user-security activity log entries for the authenticated user.
     *
     * @return array<int, array{id: int, event: string, label: string, outcome: string, browser: string, platform: string, occurred_at: string, occurred_at_iso: string|null}>
     */
    private function getSecurityActivity(User $user): array
    {
        $table = (string) config('activitylog.table_name', 'activity_log');
        $connection = config('activitylog.database_connection');
        if (! Schema::connection(is_string($connection) && $connection !== '' ? $connection : null)->hasTable($table)) {
            return [];
        }

        $labels = [
            'signed_in' => 'Signed in',
            'signed_out' => 'Signed out',
            'sign_in_failed' => 'Failed sign-in attempt',
            'mfa_passed' => 'Two-factor verification passed',
            'mfa_failed' => 'Two-factor verification failed',
            'mfa_app_enabled' => 'Authenticator app enabled',
            'mfa_app_disabled' => 'Authenticator app removed',
            'mfa_email_enabled' => 'Email codes enabled',
            'mfa_email_disabled' => 'Email codes disabled',
            'mfa_challenges_enabled' => '2FA login protection enabled',
            'mfa_challenges_paused' => '2FA login protection paused',
            'mfa_recovery_codes_regenerated' => 'Recovery codes regenerated',
            'passkey_added' => 'Passkey added',
            'passkey_deleted' => 'Passkey removed',
            'password_changed' => 'Password changed',
            'password_reset' => 'Password reset',
            'email_changed' => 'Account email changed',
            'social_account_connected' => 'Social account connected',
            'social_account_disconnected' => 'Social account disconnected',
            'faculty_id_verified' => 'Faculty ID verified',
        ];

        return Activity::query()
            ->inLog('user-security')
            ->forSubject($user)
            ->latest('id')
            ->limit(10)
            ->get()
            ->map(function (Activity $activity) use ($labels): array {
                $event = (string) ($activity->event ?? 'security_event');

                return [
                    'id' => (int) $activity->id,
                    'event' => $event,
                    'label' => $labels[$event] ?? Str::headline($event),
                    'outcome' => (string) ($activity->getProperty('outcome') ?? 'success'),
                    'browser' => (string) ($activity->getProperty('browser') ?? 'Unknown browser'),
                    'platform' => (string) ($activity->getProperty('platform') ?? 'Unknown OS'),
                    'occurred_at' => $activity->created_at?->diffForHumans() ?? 'Recently',
                    'occurred_at_iso' => $activity->created_at?->toIso8601String(),
                ];
            })
            ->values()
            ->all();
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
            ->map(function ($session) use ($request): array {
                $agent = new Agent;
                $agent->setUserAgent((string) $session->user_agent);

                return [
                    'id' => $session->id,
                    'ip_address' => $session->ip_address,
                    'is_current_device' => $session->id === $request->session()->getId(),
                    'last_active' => Carbon::createFromTimestamp($session->last_activity)->diffForHumans(),
                    'user_agent' => $session->user_agent,
                    'browser' => $agent->browser() ?: 'Unknown browser',
                    'platform' => $agent->platform() ?: 'Unknown OS',
                    'is_desktop' => $agent->isDesktop(),
                ];
            });
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
