<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Features\Onboarding\FacultyDeveloperMode;
use App\Features\Onboarding\FeatureClassRegistry;
use App\Features\Onboarding\StudentDeveloperMode;
use App\Http\Requests\ToggleExperimentalFeaturesRequest;
use App\Models\ConnectedAccount;
use App\Models\Faculty;
use App\Models\Student;
use App\Models\User;
use App\Services\DigitalIdCardService;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\RoundBlockSizeMode;
use Endroid\QrCode\Writer\PngWriter;
use Illuminate\Http\Request;
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

        $connectedAccounts = [];
        if (Schema::hasTable('connected_accounts')) {
            $connectedAccounts = ConnectedAccount::where('user_id', $user->id)
                ->get()
                ->pluck('provider')
                ->mapWithKeys(fn ($provider): array => [$provider => true])
                ->toArray();
        }

        // Generate ID card data
        $idCardService = app(DigitalIdCardService::class);
        $idCardData = $idCardService->generateIdCardForUser($user);

        $experimentalKeys = config('onboarding.experimental_feature_keys', []);
        $experimentalRoles = config('onboarding.experimental_features_roles', []);
        $userRole = $user->role?->value ?? 'user';

        $isFaculty = in_array($userRole, ['professor', 'associate_professor', 'assistant_professor', 'instructor', 'part_time_faculty'], true);
        $isStudent = in_array($userRole, ['student', 'graduate_student', 'shs_student'], true);

        $roleType = match (true) {
            $isFaculty => 'faculty',
            $isStudent => 'student',
            default => 'other',
        };

        $availableForRole = collect($experimentalKeys)
            ->filter(fn (string $featureKey): bool => isset($experimentalRoles[$featureKey]) && in_array($roleType, $experimentalRoles[$featureKey], true))
            ->values()
            ->all();

        $developerModeFeature = $isFaculty
            ? FacultyDeveloperMode::class
            : StudentDeveloperMode::class;

        $developerModeEnabled = Feature::for($user)->active($developerModeFeature);

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
                'department' => $user->department,
                'position' => $user->position,
                'two_factor_enabled' => ! is_null($user->app_authentication_secret),
                'email_two_factor_enabled' => $user->hasEmailAuthentication(),
                'recovery_codes' => $user->app_authentication_recovery_codes,
            ],
            'feature_flags' => [
                'experimental' => collect($availableForRole)
                    ->filter(function (string $featureKey) use ($user): bool {
                        $featureClass = FeatureClassRegistry::classForKey($featureKey);

                        return (bool) Feature::for($user)->active($featureClass ?? $featureKey);
                    })
                    ->values()
                    ->all(),
                'experimental_available' => $availableForRole,
                'developer_mode_enabled' => $developerModeEnabled,
            ],
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
                'contacts' => $student->studentContactsInfo,
                'education' => $student->studentEducationInfo,
                'parents' => $student->studentParentInfo,
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

    public function toggleExperimentalFeatures(ToggleExperimentalFeaturesRequest $request)
    {
        $user = Auth::user();
        $allowedFeatures = config('onboarding.experimental_feature_keys', []);
        $requestedFeatures = array_values(array_intersect($request->input('features', []), $allowedFeatures));

        foreach ($allowedFeatures as $featureKey) {
            $featureRef = FeatureClassRegistry::classForKey($featureKey) ?? $featureKey;

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
            'website' => 'nullable|url|max:255',
            'department' => 'nullable|string|max:255',
            'position' => 'nullable|string|max:255',
            'avatar' => 'nullable|file|mimes:jpeg,png,jpg,gif,webp|max:5120',
        ]);

        if ($validator->fails()) {
            return back()
                ->withErrors($validator->errors())
                ->withInput($request->only([
                    'name', 'email', 'phone', 'address', 'city', 'state',
                    'country', 'postal_code', 'bio', 'website', 'department', 'position',
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
                    Storage::disk('r2')->delete($oldPath);
                }
            }

            // Upload new avatar
            $file = $request->file('avatar');
            $filename = 'avatar-'.$user->id.'-'.time().'.'.$file->getClientOriginalExtension();
            $path = "avatars/{$filename}";
            Storage::disk('r2')->put($path, $file->getContent(), 'public');
            $avatarUrl = env('R2_URL').'/'.$path;
        }

        $user->update([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?? null,
            'address' => $validated['address'] ?? null,
            'city' => $validated['city'] ?? null,
            'state' => $validated['state'] ?? null,
            'country' => $validated['country'] ?? null,
            'postal_code' => $validated['postal_code'] ?? null,
            'bio' => $validated['bio'] ?? null,
            'website' => $validated['website'] ?? null,
            'department' => $validated['department'] ?? null,
            'position' => $validated['position'] ?? null,
            'avatar_url' => $avatarUrl,
        ]);

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

    /**
     * Update student information
     */
    public function updateStudent(Request $request)
    {
        $user = Auth::user();

        // Check if student record exists
        $student = Student::where('user_id', $user->id)->first();

        if (! $student) {
            return back()->withErrors(['error' => 'Student record not found.']);
        }

        // Update existing student record
        $validator = Validator::make($request->all(), [
            'first_name' => 'required|string|max:255',
            'middle_name' => 'nullable|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique('students')->ignore($student->id),
            ],
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string|max:255',
            'civil_status' => 'nullable|string|max:50',
            'nationality' => 'nullable|string|max:100',
            'religion' => 'nullable|string|max:100',
            'emergency_contact' => 'nullable|string|max:255',
            'birth_date' => 'nullable|date',
            'gender' => 'nullable|in:male,female,other,prefer_not_to_say',
            // Contact Info
            'contacts.emergency_contact_name' => 'nullable|string|max:255',
            'contacts.emergency_contact_phone' => 'nullable|string|max:20',
            'contacts.emergency_contact_relationship' => 'nullable|string|max:255',
            'contacts.facebook' => 'nullable|string|max:255',
            'contacts.personal_contact' => 'nullable|string|max:20',
            // Education Info
            'education.elementary_school' => 'nullable|string|max:255',
            'education.elementary_year_graduated' => 'nullable|string|max:20',
            'education.high_school' => 'nullable|string|max:255',
            'education.high_school_year_graduated' => 'nullable|string|max:20',
            'education.senior_high_school' => 'nullable|string|max:255',
            'education.senior_high_year_graduated' => 'nullable|string|max:20',
            // Parent Info
            'parents.father_name' => 'nullable|string|max:255',
            'parents.mother_name' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return back()
                ->withErrors($validator->errors())
                ->withInput($request->all());
        }

        $validated = $validator->validated();

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

        // Update Contacts
        if (isset($validated['contacts'])) {
            if ($student->studentContactsInfo) {
                $student->studentContactsInfo->update($validated['contacts']);
            } else {
                $contact = \App\Models\StudentContact::create($validated['contacts']);
                $student->student_contact_id = $contact->id;
            }
        }

        // Update Education
        if (isset($validated['education'])) {
            if ($student->studentEducationInfo) {
                $student->studentEducationInfo->update($validated['education']);
            } else {
                $education = \App\Models\StudentEducationInfo::create($validated['education']);
                $student->student_education_id = $education->id;
            }
        }

        // Update Parents
        if (isset($validated['parents'])) {
            if ($student->studentParentInfo) {
                $student->studentParentInfo->update($validated['parents']);
            } else {
                $parent = \App\Models\StudentParentsInfo::create($validated['parents']);
                $student->student_parent_info = $parent->id;
            }
        }

        $student->save();

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
            'email_auth_toggle' => $basePath.'/email-authentication',
            'experimental_features' => $basePath.'/experimental-features',
            'browser_sessions_logout' => $basePath.'/other-browser-sessions',
        ];

        // Only faculty and admin portals have faculty update endpoint
        if ($hasFacultyUpdateEndpoint) {
            $endpoints['faculty_update'] = $basePath.'/faculty';
        }

        // Only student and admin portals have student update endpoint
        if ($hasStudentUpdateEndpoint) {
            $endpoints['student_update'] = $basePath.'/student';
        }

        return $endpoints;
    }
}
