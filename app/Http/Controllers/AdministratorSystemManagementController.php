<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enrollment\EnrollmentPolicyPreset;
use App\Enrollment\EnrollmentPolicyRegistry;
use App\Enrollment\EnrollmentPolicyRolloutService;
use App\Enums\NotificationChannel;
use App\Enums\PaymentMethod;
use App\Enums\SchoolLevel;
use App\Enums\StudentType;
use App\Features\DynamicEnrollmentPolicies;
use App\Http\Requests\Administrators\StoreSchoolRequest;
use App\Http\Requests\Administrators\UpdateApiManagementRequest;
use App\Http\Requests\Administrators\UpdateEnrollmentPipelineRequest;
use App\Http\Requests\Administrators\UpdateFinanceDocumentSettingsRequest;
use App\Http\Requests\Administrators\UpdateNewsletterSettingsRequest;
use App\Http\Requests\Administrators\UpdateSchoolLevelRequest;
use App\Http\Requests\Administrators\UpdateSchoolRequest;
use App\Http\Requests\Administrators\UpdateSchoolStatusRequest;
use App\Http\Requests\Administrators\UpdateTuitionPaymentScheduleSettingsRequest;
use App\Models\Course;
use App\Models\EnrollmentPolicy;
use App\Models\EnrollmentPolicyVersion;
use App\Models\GeneralSetting;
use App\Models\School;
use App\Models\User;
use App\Services\AnalyticsSettingsService;
use App\Services\EnrollmentPipelineService;
use App\Services\FinanceDocumentSettingsService;
use App\Services\GeneralSettingsService;
use App\Services\GradingSystemService;
use App\Services\IdentifierGenerator;
use App\Services\LogoConversionService;
use App\Services\Newsletter\NewsletterProviderManager;
use App\Services\Newsletter\NewsletterSettingsService;
use App\Services\SocialiteProviderService;
use App\Services\TuitionPaymentScheduleSettingsService;
use App\Settings\SiteSettings;
use App\Support\SystemManagementPermissions;
use Exception;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Pennant\Feature;
use RuntimeException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

final class AdministratorSystemManagementController extends Controller
{
    public function __construct(
        private readonly SiteSettings $siteSettings,
        private readonly EnrollmentPipelineService $enrollmentPipelineService
    ) {}

    public function index(): Response
    {
        $user = Auth::user();

        abort_unless($user instanceof User, 403);

        $this->authorize('viewAny', GeneralSetting::class);

        return Inertia::render('administrators/system-management/index', [
            'user' => [
                'name' => $user->name,
                'email' => $user->email,
                'avatar' => $user->avatar_url ?? null,
                'role' => $user->role?->getLabel() ?? 'Administrator',
                'permissions' => $user->getAllPermissions()->pluck('name')->values()->all(),
            ],
            'access' => [
                'active_section' => null,
                'sections' => $this->getSectionAccessMap($user),
            ],
        ]);
    }

    public function school(): Response
    {
        return $this->renderSystemManagementPage('administrators/system-management/school', 'school', 'viewSchool');
    }

    public function enrollmentPipeline(
        EnrollmentPolicyRegistry $registry,
        EnrollmentPolicyRolloutService $rollout,
        GeneralSettingsService $generalSettings,
    ): Response {
        $optionList = static fn (array $options): array => collect($options)
            ->map(fn (string $label, string|int $value): array => ['value' => (string) $value, 'label' => $label])
            ->values()
            ->all();
        $policyModels = EnrollmentPolicy::query()
            ->with(['school:id,name', 'course:id,code,title', 'activeVersion', 'versions' => fn ($query) => $query->latest('version')])
            ->orderBy('name')
            ->get();
        $permissionNames = $policyModels
            ->flatMap(fn (EnrollmentPolicy $policy) => $policy->versions)
            ->flatMap(fn (EnrollmentPolicyVersion $version) => collect(data_get($version->configuration, 'workflow.steps', []))->pluck('permission'))
            ->filter(fn (mixed $permission): bool => is_string($permission) && $permission !== '')
            ->unique()
            ->values();
        $rolesWithPolicyPermissions = $permissionNames->isEmpty()
            ? collect()
            : Role::query()
                ->whereHas('permissions', fn ($query) => $query->whereIn('name', $permissionNames))
                ->with(['permissions' => fn ($query) => $query->whereIn('name', $permissionNames)])
                ->get();
        $permissionRoles = collect();
        foreach ($rolesWithPolicyPermissions as $role) {
            foreach ($role->permissions as $permission) {
                $permissionRoles->put(
                    $permission->name,
                    [...$permissionRoles->get($permission->name, []), (string) $role->id],
                );
            }
        }
        $serializeVersion = function (?EnrollmentPolicyVersion $version) use ($permissionRoles): ?array {
            if (! $version instanceof EnrollmentPolicyVersion) {
                return null;
            }

            $serialized = $version->toArray();
            $configuration = $version->configuration;
            foreach (data_get($configuration, 'workflow.steps', []) as $index => $step) {
                $permission = $step['permission'] ?? null;
                if (! isset($step['authorized_role_ids']) && is_string($permission)) {
                    $configuration['workflow']['steps'][$index]['authorized_role_ids'] = $permissionRoles->get($permission, []);
                }
            }
            $serialized['configuration'] = $configuration;

            return $serialized;
        };
        $policies = $policyModels
            ->map(fn (EnrollmentPolicy $policy): array => [
                'id' => $policy->id,
                'name' => $policy->name,
                'scope' => $policy->scopeLabels(),
                'scope_values' => [
                    'school_id' => $policy->school_id,
                    'student_type' => $policy->student_type,
                    'course_id' => $policy->course_id,
                    'school_year' => $policy->school_year,
                    'semester' => $policy->semester,
                ],
                'is_enabled' => $policy->is_enabled,
                'active_version_id' => $policy->active_version_id,
                'active_version' => $serializeVersion($policy->activeVersion),
                'versions' => $policy->versions->map($serializeVersion)->values(),
            ]);

        return $this->renderSystemManagementPage(
            'administrators/system-management/enrollment-pipeline',
            'pipeline',
            'viewEnrollmentPipeline',
            [
                'enrollment_policies' => $policies,
                'enrollment_registry' => $registry->manifest(),
                'enrollment_rollout' => $rollout->report(),
                'enrollment_presets' => EnrollmentPolicyPreset::catalog(),
                'has_global_published_policy' => $policyModels->contains(fn (EnrollmentPolicy $policy): bool => $policy->scope_key === EnrollmentPolicy::scopeKey([]) && $policy->active_version_id !== null),
                'enrollment_documentation_url' => mb_rtrim((string) config('app.documentation_url'), '/'),
                'enrollment_operator_options' => [
                    'enrollment_channels' => $optionList(['public' => 'Public registration', 'administrator' => 'Administrator', 'continuing' => 'Continuing student', 'api' => 'API']),
                    'student_types' => $optionList(StudentType::asSelectOptions()),
                    'schools' => School::query()->orderBy('name')->pluck('name', 'id')->mapWithKeys(fn (string $label, int $id): array => [(string) $id => $label])->map(fn (string $label, string $value): array => ['value' => $value, 'label' => $label])->values(),
                    'programs' => Course::query()->orderBy('code')->get(['id', 'code', 'title'])->map(fn (Course $course): array => ['value' => (string) $course->id, 'label' => "{$course->code} · {$course->title}"]),
                    'periods' => [[
                        'value' => $generalSettings->getCurrentSchoolYearString().'|'.$generalSettings->getCurrentSemester(),
                        'label' => $generalSettings->getCurrentSchoolYearString().' · Semester '.$generalSettings->getCurrentSemester(),
                    ]],
                    'year_levels' => collect(range(1, 6))->map(fn (int $year): array => ['value' => (string) $year, 'label' => "Year {$year}"]),
                    'payment_methods' => collect(PaymentMethod::cases())->map(fn (PaymentMethod $method): array => ['value' => $method->value, 'label' => $method->value]),
                    'roles' => Role::query()->orderBy('name')->get(['id', 'name'])->map(fn (Role $role): array => ['value' => (string) $role->id, 'label' => str($role->name)->headline()->toString()]),
                    'permissions' => Permission::query()->orderBy('name')->get(['id', 'name'])->map(fn (Permission $permission): array => ['value' => $permission->name, 'label' => str($permission->name)->headline()->toString()]),
                    'notification_channels' => collect(NotificationChannel::cases())
                        ->where('value', NotificationChannel::Mail->value)
                        ->map(fn (NotificationChannel $channel): array => ['value' => $channel->value, 'label' => $channel->getLabel() ?? $channel->value])
                        ->values(),
                ],
            ],
        );
    }

    public function seo(): Response
    {
        return $this->renderSystemManagementPage('administrators/system-management/seo', 'seo', 'viewSeo');
    }

    public function analytics(): Response
    {
        return $this->renderSystemManagementPage('administrators/system-management/analytics', 'analytics', 'viewAnalytics');
    }

    public function brand(): Response
    {
        return $this->renderSystemManagementPage('administrators/system-management/brand', 'brand', 'viewBrand');
    }

    public function socialite(): Response
    {
        return $this->renderSystemManagementPage('administrators/system-management/socialite', 'socialite', 'viewSocialite');
    }

    public function mail(): Response
    {
        return $this->renderSystemManagementPage('administrators/system-management/mail', 'mail', 'viewMail');
    }

    public function newsletter(NewsletterSettingsService $newsletterSettings): Response
    {
        return $this->renderSystemManagementPage(
            'administrators/system-management/newsletter',
            'newsletter',
            'viewNewsletter',
            ['newsletter_config' => $newsletterSettings->forAdministration()],
        );
    }

    public function pulse(): Response
    {
        return $this->renderSystemManagementPage('administrators/system-management/pulse', 'pulse', 'viewPulse');
    }

    public function grading(): Response
    {
        return $this->renderSystemManagementPage('administrators/system-management/grading', 'grading', 'viewGrading');
    }

    public function identifiers(): Response
    {
        return $this->renderSystemManagementPage('administrators/system-management/identifiers', 'identifiers', 'viewIdentifiers');
    }

    public function updateIdentifiers(Request $request, IdentifierGenerator $identifierGenerator): RedirectResponse
    {
        $this->authorize('updateIdentifiers', GeneralSetting::class);

        $validated = $request->validate([
            'student' => ['required', 'array:start_number,next_number,increment_by,padding'],
            'student.start_number' => ['required', 'integer', 'min:1', 'max:999999'],
            'student.next_number' => ['required', 'integer', 'min:1', 'max:999999'],
            'student.increment_by' => ['required', 'integer', 'min:1', 'max:1000'],
            'student.padding' => ['nullable', 'integer', 'min:1', 'max:12'],
            'staff' => ['required', 'array:start_number,next_number,increment_by,padding'],
            'staff.start_number' => ['required', 'integer', 'min:1', 'max:999999999'],
            'staff.next_number' => ['required', 'integer', 'min:1', 'max:999999999'],
            'staff.increment_by' => ['required', 'integer', 'min:1', 'max:1000'],
            'staff.padding' => ['nullable', 'integer', 'min:1', 'max:12'],
        ]);

        $identifierGenerator->updateConfiguration([
            IdentifierGenerator::Student => $validated['student'],
            IdentifierGenerator::Staff => $validated['staff'],
        ]);

        return Redirect::back()->with('success', 'Identifier sequences updated successfully.');
    }

    public function updateGrading(Request $request): RedirectResponse
    {
        $this->authorize('updateGrading', GeneralSetting::class);

        $validated = $request->validate([
            'scale' => 'required|in:point,percent,auto',
            'point_passing_grade' => 'required|numeric|min:1|max:5',
            'percent_passing_grade' => 'required|numeric|min:0|max:100',
            'point_decimal_places' => 'required|integer|min:0|max:6',
            'percent_decimal_places' => 'required|integer|min:0|max:6',
            'include_failed_in_gwa' => 'required|boolean',
            'excluded_keywords' => 'array',
            'excluded_keywords.*' => 'string|max:64',
            'excluded_subject_ids' => 'array',
            'excluded_subject_ids.*' => 'integer|min:1',
        ]);

        app(GradingSystemService::class)->update($validated);

        return Redirect::back()->with('success', 'Grading system updated successfully.');
    }

    public function notifications(): Response
    {
        return $this->renderSystemManagementPage('administrators/system-management/notifications', 'notifications', 'viewNotifications');
    }

    public function financeDocuments(): Response
    {
        return $this->renderSystemManagementPage(
            'administrators/system-management/finance-documents',
            'finance_documents',
            'viewFinanceDocuments',
        );
    }

    public function updateFinanceDocuments(
        UpdateFinanceDocumentSettingsRequest $request,
        FinanceDocumentSettingsService $settings,
    ): RedirectResponse {
        $settings->update($request->safe()->only([
            'automatic_receipts_enabled',
            'require_paper_or_reference',
            'manual_invoices_enabled',
        ]));

        return Redirect::back()->with('success', 'Finance document settings updated successfully.');
    }

    public function tuitionPaymentSchedule(): Response
    {
        return $this->renderSystemManagementPage(
            'administrators/system-management/tuition-payment-schedule',
            'tuition_payment_schedule',
            'viewTuitionPaymentSchedule',
        );
    }

    public function updateTuitionPaymentSchedule(
        UpdateTuitionPaymentScheduleSettingsRequest $request,
        TuitionPaymentScheduleSettingsService $settings,
    ): RedirectResponse {
        $settings->update($request->validated());

        return Redirect::back()->with('success', 'Tuition payment schedule settings updated successfully.');
    }

    public function api(): Response
    {
        return $this->renderSystemManagementPage('administrators/system-management/api', 'api', 'viewApi');
    }

    public function storeSchool(StoreSchoolRequest $request)
    {
        $validated = $request->validated();

        School::create([
            'name' => $validated['name'],
            'code' => $validated['code'],
            'school_level' => $validated['school_level'],
            'description' => $validated['description'] ?? null,
            'location' => $validated['location'] ?? null,
            'phone' => $validated['phone'] ?? null,
            'email' => $validated['email'] ?? null,
            'dean_name' => $validated['dean_name'] ?? null,
            'dean_email' => $validated['dean_email'] ?? null,
            'is_active' => false, // New schools are inactive by default
        ]);

        return Redirect::back()->with('success', 'New school created successfully.');
    }

    public function updateSchool(Request $request)
    {
        $this->authorize('updateSchool', GeneralSetting::class);

        $request->validate([
            'school_id' => 'required|exists:schools,id',
        ]);

        $schoolId = (int) $request->school_id;
        $generalSettingsService = app(GeneralSettingsService::class);
        $generalSettingsService->updateActiveSchoolId($schoolId);

        try {
            $tenantContext = app(\App\Services\TenantContext::class);
            $tenantContext->setCurrentSchoolId($schoolId);
        } catch (Exception) {
            // Ignore if service not available
        }

        return Redirect::back()->with('success', 'Active school updated successfully.');
    }

    public function updateSchoolDetails(Request $request)
    {
        $this->authorize('updateSchool', GeneralSetting::class);

        $validated = $request->validate([
            'school_id' => 'required|exists:schools,id',
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:50',
            'school_level' => ['required', Rule::enum(SchoolLevel::class)],
            'description' => 'nullable|string',
            'location' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:255',
        ]);

        $school = School::findOrFail($validated['school_id']);
        $school->update([
            'name' => $validated['name'],
            'code' => $validated['code'],
            'school_level' => $validated['school_level'],
            'description' => $validated['description'] ?? null,
            'location' => $validated['location'] ?? null,
            'phone' => $validated['phone'] ?? null,
            'email' => $validated['email'] ?? null,
        ]);

        return Redirect::back()->with('success', 'School details updated successfully.');
    }

    public function updateManagedSchool(UpdateSchoolRequest $request, School $school)
    {
        $validated = $request->validated();

        $school->update([
            'name' => $validated['name'],
            'code' => $validated['code'],
            'school_level' => $validated['school_level'],
            'description' => $validated['description'] ?? null,
            'location' => $validated['location'] ?? null,
            'phone' => $validated['phone'] ?? null,
            'email' => $validated['email'] ?? null,
            'dean_name' => $validated['dean_name'] ?? null,
            'dean_email' => $validated['dean_email'] ?? null,
        ]);

        return Redirect::back()->with('success', 'School record updated successfully.');
    }

    public function updateSchoolLevel(UpdateSchoolLevelRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $school = School::query()->findOrFail($validated['school_id']);
        $school->update(['school_level' => $validated['school_level']]);

        app(GeneralSettingsService::class)->updateActiveSchoolId($school->id);
        app(\App\Services\TenantContext::class)->setCurrentSchoolId($school->id);

        return Redirect::back()->with('success', 'Institution school level configured successfully.');
    }

    public function updateSchoolStatus(UpdateSchoolStatusRequest $request, School $school)
    {
        $validated = $request->validated();
        $isActive = (bool) $validated['is_active'];

        if (! $isActive) {
            $generalSettingsService = app(GeneralSettingsService::class);
            $currentActiveSchoolId = $generalSettingsService->getActiveSchoolId();

            if ($currentActiveSchoolId === $school->id) {
                return Redirect::back()->withErrors([
                    'school' => 'Please switch your active school before deactivating this school.',
                ]);
            }
        }

        $school->update([
            'is_active' => $isActive,
        ]);

        return Redirect::back()->with('success', 'School status updated successfully.');
    }

    public function destroySchool(School $school)
    {
        $this->authorize('updateSchool', GeneralSetting::class);

        if (School::query()->count() <= 1) {
            return Redirect::back()->withErrors([
                'school' => 'At least one school must remain in the system.',
            ]);
        }

        $replacementSchoolId = School::query()->whereKeyNot($school->id)->value('id');
        $generalSettingsService = app(GeneralSettingsService::class);

        if ($generalSettingsService->getActiveSchoolId() === $school->id) {
            $generalSettingsService->updateActiveSchoolId($replacementSchoolId ? (int) $replacementSchoolId : null);
        }

        if (Schema::hasTable('user_settings') && Schema::hasColumn('user_settings', 'active_school_id')) {
            DB::table('user_settings')
                ->where('active_school_id', $school->id)
                ->update(['active_school_id' => $replacementSchoolId]);
        }

        $school->delete();

        return Redirect::back()->with('success', 'School archived successfully.');
    }

    public function forceDestroySchool(int $school)
    {
        $this->authorize('updateSchool', GeneralSetting::class);

        $schoolToDelete = School::withTrashed()->findOrFail($school);

        if (School::query()->whereKeyNot($schoolToDelete->id)->count() <= 0) {
            return Redirect::back()->withErrors([
                'school' => 'At least one school must remain in the system.',
            ]);
        }

        DB::transaction(function () use ($schoolToDelete): void {
            $replacementSchoolId = School::query()
                ->whereKeyNot($schoolToDelete->id)
                ->value('id');

            $generalSettingsService = app(GeneralSettingsService::class);
            if ($generalSettingsService->getActiveSchoolId() === $schoolToDelete->id) {
                $generalSettingsService->updateActiveSchoolId($replacementSchoolId ? (int) $replacementSchoolId : null);
            }

            if (Schema::hasTable('user_settings') && Schema::hasColumn('user_settings', 'active_school_id')) {
                DB::table('user_settings')
                    ->where('active_school_id', $schoolToDelete->id)
                    ->update(['active_school_id' => $replacementSchoolId]);
            }

            if (Schema::hasTable('users') && Schema::hasColumn('users', 'school_id')) {
                DB::table('users')
                    ->where('school_id', $schoolToDelete->id)
                    ->update(['school_id' => $replacementSchoolId]);
            }

            $this->deleteSchoolScopedRecords($schoolToDelete->id);

            $schoolToDelete->forceDelete();
        });

        return Redirect::back()->with('success', 'School permanently deleted with related records.');
    }

    public function updateSeo(Request $request)
    {
        $this->authorize('updateSeo', GeneralSetting::class);

        $settings = GeneralSetting::query()->first();

        if (! $settings instanceof GeneralSetting) {
            $settings = GeneralSetting::query()->create([
                'site_name' => $this->siteSettings->getAppName(),
            ]);
        }

        $validated = $request->validate([
            'site_name' => 'required|string|max:255',
            'site_description' => 'nullable|string|max:500',
            'seo_title' => 'nullable|string|max:255',
            'seo_keywords' => 'nullable|string|max:500',
            'seo_metadata' => 'nullable|array',
            'seo_metadata.robots' => ['nullable', 'string', Rule::in(['index, follow', 'index, nofollow', 'noindex, follow', 'noindex, nofollow'])],
            'seo_metadata.og_image' => 'nullable|string|max:2048',
            'seo_metadata.twitter_handle' => 'nullable|string|max:255',
            'seo_metadata.twitter_card' => ['nullable', 'string', Rule::in(['summary', 'summary_large_image'])],
            'seo_metadata.canonical_url' => 'nullable|string|max:2048',
        ]);

        $metadata = $validated['seo_metadata'] ?? [];
        $normalize = static fn (mixed $value): ?string => is_string($value) && mb_trim($value) !== ''
            ? mb_trim($value)
            : null;

        $twitterHandle = $normalize($metadata['twitter_handle'] ?? null);
        if ($twitterHandle !== null && ! str_starts_with($twitterHandle, '@')) {
            $twitterHandle = '@'.$twitterHandle;
        }

        $settings->update([
            'site_name' => mb_trim($validated['site_name']),
            'site_description' => $normalize($validated['site_description'] ?? null),
            'seo_title' => $normalize($validated['seo_title'] ?? null),
            'seo_keywords' => $normalize($validated['seo_keywords'] ?? null),
            'seo_metadata' => [
                'robots' => $metadata['robots'] ?? 'index, follow',
                'og_image' => $normalize($metadata['og_image'] ?? null),
                'twitter_handle' => $twitterHandle,
                'twitter_card' => $metadata['twitter_card'] ?? 'summary_large_image',
                'canonical_url' => $normalize($metadata['canonical_url'] ?? null),
            ],
        ]);

        return Redirect::back()->with('success', 'SEO settings updated successfully.');
    }

    public function updateAnalytics(Request $request)
    {
        $this->authorize('updateAnalytics', GeneralSetting::class);

        $settings = GeneralSetting::query()->first();

        if (! $settings instanceof GeneralSetting) {
            $settings = GeneralSetting::query()->create([
                'site_name' => $this->siteSettings->getAppName(),
            ]);
        }

        $validated = $request->validate([
            'analytics_enabled' => 'required|boolean',
            'analytics_provider' => 'nullable|string|in:google,ackee,umami,openpanel,custom',
            'analytics_script' => 'nullable|string',
            'analytics_settings' => 'nullable|array',
            'analytics_settings.google_measurement_id' => 'nullable|string|max:50',
            'analytics_settings.ackee_script_url' => 'nullable|url|max:2048',
            'analytics_settings.ackee_server_url' => 'nullable|url|max:2048',
            'analytics_settings.ackee_domain_id' => 'nullable|string|max:255',
            'analytics_settings.umami_script_url' => 'nullable|url|max:2048',
            'analytics_settings.umami_website_id' => 'nullable|string|max:255',
            'analytics_settings.umami_host_url' => 'nullable|url|max:2048',
            'analytics_settings.umami_domains' => 'nullable|string|max:255',
            'analytics_settings.openpanel_script_url' => 'nullable|url|max:2048',
            'analytics_settings.openpanel_client_id' => 'nullable|string|max:255',
            'analytics_settings.openpanel_api_url' => 'nullable|url|max:2048',
            'analytics_settings.openpanel_track_screen_views' => 'nullable|boolean',
            'analytics_settings.openpanel_track_outgoing_links' => 'nullable|boolean',
            'analytics_settings.openpanel_track_attributes' => 'nullable|boolean',
            'analytics_settings.openpanel_session_replay' => 'nullable|boolean',
        ]);

        $analyticsSettings = $validated['analytics_settings'] ?? [];
        $validated['analytics_provider'] = filled($validated['analytics_provider'] ?? null)
            ? $validated['analytics_provider']
            : null;
        $validated['analytics_script'] = filled($validated['analytics_script'] ?? null)
            ? $validated['analytics_script']
            : null;
        $validated['google_analytics_id'] = ($validated['analytics_provider'] ?? null) === 'google'
            ? ($analyticsSettings['google_measurement_id'] ?? null)
            : null;

        $settings->update($validated);

        return Redirect::back()->with('success', 'Analytics settings updated successfully.');
    }

    public function updateBrand(Request $request)
    {
        $this->authorize('updateBrand', GeneralSetting::class);

        $this->ensureSiteSettingExists('auth_layout', 'split');
        app()->forgetInstance(SiteSettings::class);
        $siteSettings = app(SiteSettings::class);

        $validated = $request->validate([
            'app_name' => 'nullable|string|max:255',
            'app_short_name' => 'nullable|string|max:50',
            'organization_name' => 'nullable|string|max:255',
            'organization_short_name' => 'nullable|string|max:50',
            'organization_address' => 'nullable|string|max:500',
            'support_email' => 'nullable|email|max:255',
            'support_phone' => 'nullable|string|max:50',
            'tagline' => 'nullable|string|max:255',
            'copyright_text' => 'nullable|string|max:255',
            'theme_color' => 'nullable|string|max:50',
            'currency' => 'nullable|string|in:PHP,USD',
            'auth_layout' => 'nullable|string|in:card,split,minimal',
            'default_country_code' => 'nullable|string|max:10',
            'logo' => 'nullable|file|mimes:jpeg,png,gif,webp,svg|max:5120',
        ]);

        // Handle single logo upload — generates all formats automatically
        if ($request->hasFile('logo')) {
            $paths = app(LogoConversionService::class)->process($request->file('logo'));
            $siteSettings->logo = $paths['logo'];
            $siteSettings->favicon = $paths['favicon'];
            $siteSettings->og_image = $paths['og_image'];
        }

        // Update Spatie Settings
        $siteSettings->app_name = $validated['app_name'] ?? null;
        $siteSettings->app_short_name = $validated['app_short_name'] ?? null;
        $siteSettings->organization_name = $validated['organization_name'] ?? null;
        $siteSettings->organization_short_name = $validated['organization_short_name'] ?? null;
        $siteSettings->organization_address = $validated['organization_address'] ?? null;
        $siteSettings->support_email = $validated['support_email'] ?? null;
        $siteSettings->support_phone = $validated['support_phone'] ?? null;
        $siteSettings->tagline = $validated['tagline'] ?? null;
        $siteSettings->copyright_text = $validated['copyright_text'] ?? null;
        $siteSettings->theme_color = $validated['theme_color'] ?? null;
        $siteSettings->currency = $validated['currency'] ?? null;
        $siteSettings->auth_layout = $validated['auth_layout'] ?? 'split';
        $siteSettings->default_country_code = $validated['default_country_code'] ?? null;
        $siteSettings->save();

        return Redirect::back()->with('success', 'Brand settings updated successfully. Logo has been converted for all formats — favicon, PWA icons, and OG image.');
    }

    public function updateSocialite(Request $request)
    {
        $this->authorize('updateSocialite', GeneralSetting::class);

        $settings = GeneralSetting::query()->first();

        if (! $settings instanceof GeneralSetting) {
            $settings = GeneralSetting::query()->create([
                'site_name' => $this->siteSettings->getAppName(),
            ]);
        }

        $socialiteProviders = app(SocialiteProviderService::class);
        $validated = $request->validate([
            'facebook_client_id' => 'nullable|string',
            'facebook_client_secret' => 'nullable|string',
            'facebook_enabled' => 'nullable|boolean',
            'facebook_redirect_uri' => 'nullable|url',
            'google_client_id' => 'nullable|string',
            'google_client_secret' => 'nullable|string',
            'google_enabled' => 'nullable|boolean',
            'google_redirect_uri' => 'nullable|url',
            'twitter_client_id' => 'nullable|string',
            'twitter_client_secret' => 'nullable|string',
            'twitter_enabled' => 'nullable|boolean',
            'twitter_redirect_uri' => 'nullable|url',
            'github_client_id' => 'nullable|string',
            'github_client_secret' => 'nullable|string',
            'github_enabled' => 'nullable|boolean',
            'github_redirect_uri' => 'nullable|url',
            'linkedin_client_id' => 'nullable|string',
            'linkedin_client_secret' => 'nullable|string',
            'linkedin_enabled' => 'nullable|boolean',
            'linkedin_redirect_uri' => 'nullable|url',
        ]);

        $socialNetwork = array_merge($socialiteProviders->config(), $validated);

        foreach (array_keys($socialiteProviders->providers()) as $provider) {
            $isConfigured = filled($socialNetwork["{$provider}_client_id"] ?? null)
                && filled($socialNetwork["{$provider}_client_secret"] ?? null);

            $socialNetwork["{$provider}_enabled"] = $isConfigured
                && (bool) ($validated["{$provider}_enabled"] ?? false);
            $socialNetwork["{$provider}_redirect_uri"] = filled($socialNetwork["{$provider}_redirect_uri"] ?? null)
                ? $socialNetwork["{$provider}_redirect_uri"]
                : url("/auth/{$provider}/callback");
        }

        // Save to Database
        $settings->update(['social_network' => $socialNetwork]);

        // Update .env file
        $envUpdates = [
            'FACEBOOK_CLIENT_ID' => $socialNetwork['facebook_client_id'],
            'FACEBOOK_CLIENT_SECRET' => $socialNetwork['facebook_client_secret'],
            'FACEBOOK_REDIRECT_URI' => $socialNetwork['facebook_redirect_uri'],
            'GOOGLE_CLIENT_ID' => $socialNetwork['google_client_id'],
            'GOOGLE_CLIENT_SECRET' => $socialNetwork['google_client_secret'],
            'GOOGLE_REDIRECT_URI' => $socialNetwork['google_redirect_uri'],
            'TWITTER_CLIENT_ID' => $socialNetwork['twitter_client_id'],
            'TWITTER_CLIENT_SECRET' => $socialNetwork['twitter_client_secret'],
            'TWITTER_REDIRECT_URI' => $socialNetwork['twitter_redirect_uri'],
            'GITHUB_CLIENT_ID' => $socialNetwork['github_client_id'],
            'GITHUB_CLIENT_SECRET' => $socialNetwork['github_client_secret'],
            'GITHUB_REDIRECT_URI' => $socialNetwork['github_redirect_uri'],
            'LINKEDIN_CLIENT_ID' => $socialNetwork['linkedin_client_id'],
            'LINKEDIN_CLIENT_SECRET' => $socialNetwork['linkedin_client_secret'],
            'LINKEDIN_REDIRECT_URI' => $socialNetwork['linkedin_redirect_uri'],
        ];

        $this->updateEnvironmentFile($envUpdates);

        // Clear config cache to apply changes
        Artisan::call('config:clear');

        return Redirect::back()->with('success', 'Socialite configuration updated and environment synced.');
    }

    public function updateNewsletter(
        UpdateNewsletterSettingsRequest $request,
        NewsletterSettingsService $newsletterSettings,
        NewsletterProviderManager $providers,
    ): RedirectResponse {
        $previous = $newsletterSettings->get();
        $candidate = $newsletterSettings->merge($request->validated());
        $provider = $providers->forSettings($candidate);

        if ((bool) $candidate['enabled'] && (! $provider->isConfigured() || ! $provider->testConnection())) {
            throw ValidationException::withMessages([
                'provider' => 'The selected newsletter provider could not be verified. Check its credentials and destination, then try again.',
            ]);
        }

        $newsletterSettings->save($candidate);
        $providerChanged = $previous['provider'] !== $candidate['provider'];

        $response = Redirect::back()->with('success', 'Newsletter settings updated successfully.');

        if ($providerChanged) {
            $response->with(
                'warning',
                'The provider was changed for future signups only. Existing subscribed and declined records were not synchronized.',
            );
        }

        return $response;
    }

    public function testNewsletterConnection(
        UpdateNewsletterSettingsRequest $request,
        NewsletterSettingsService $newsletterSettings,
        NewsletterProviderManager $providers,
    ): JsonResponse {
        $candidate = $newsletterSettings->merge($request->validated());
        $provider = $providers->forSettings($candidate);

        if (! $provider->isConfigured() || ! $provider->testConnection()) {
            return response()->json([
                'message' => 'Connection failed. Check the provider credentials and destination.',
            ], 422);
        }

        return response()->json([
            'message' => ucfirst($provider->name()->value).' connection verified.',
        ]);
    }

    public function updateEnrollmentPipeline(UpdateEnrollmentPipelineRequest $request): RedirectResponse
    {
        if (Feature::active(DynamicEnrollmentPolicies::class)) {
            return Redirect::back()->withErrors([
                'enrollment_pipeline' => 'The legacy enrollment pipeline is read-only while the policy engine is active.',
            ]);
        }

        $generalSettingsService = app(GeneralSettingsService::class);
        $settings = $generalSettingsService->getGlobalSettingsModel();
        if (! $settings instanceof GeneralSetting) {
            $settings = GeneralSetting::query()->create(['site_name' => $this->siteSettings->getAppName()]);
            $generalSettingsService->replaceGlobalSettings($settings);
        }

        $validated = $request->validated();
        $moreConfigs = $settings->more_configs ?? [];
        $moreConfigs['enrollment_pipeline'] = $this->enrollmentPipelineService->sanitizeForStorage($validated);
        $moreConfigs['enrollment_stats'] = $this->enrollmentPipelineService->sanitizeStatsForStorage($validated['enrollment_stats'] ?? []);
        $courseIds = collect($validated['enrollment_courses'] ?? [])->map(fn (mixed $id): int => (int) $id)->filter()->unique()->values();
        $existingCourseIds = Course::query()->whereIn('id', $courseIds->all())->pluck('id')->map(fn (mixed $id): int => (int) $id);

        $settings->update([
            'more_configs' => $moreConfigs,
            'enrollment_courses' => $courseIds->intersect($existingCourseIds)->values()->all(),
        ]);

        return Redirect::back()->with('success', 'Legacy enrollment settings updated successfully.');
    }

    public function updateNotificationChannels(Request $request)
    {
        $this->authorize('updateNotifications', GeneralSetting::class);

        $settings = GeneralSetting::first();
        $validated = $request->validate([
            'enabled_channels' => ['required', 'array'],
            'enabled_channels.*' => ['string', 'in:'.implode(',', NotificationChannel::values())],
            'pusher' => ['nullable', 'array'],
            'pusher.app_id' => ['nullable', 'string'],
            'pusher.key' => ['nullable', 'string'],
            'pusher.secret' => ['nullable', 'string'],
            'pusher.cluster' => ['nullable', 'string'],
            'sms' => ['nullable', 'array'],
            'sms.provider' => ['nullable', 'string'],
            'sms.api_key' => ['nullable', 'string'],
            'sms.sender_id' => ['nullable', 'string'],
            'third_party_services' => ['nullable', 'array'],
        ]);

        $moreConfigs = $settings->more_configs ?? [];

        $notificationChannelsConfig = $validated;
        unset($notificationChannelsConfig['third_party_services']);
        $moreConfigs['notification_channels'] = $notificationChannelsConfig;

        if (isset($validated['third_party_services'])) {
            $moreConfigs['third_party_services'] = $validated['third_party_services'];
        }

        $settings->update(['more_configs' => $moreConfigs]);

        // Update environment for Pusher if broadcast/pusher enabled
        $enabledChannels = $validated['enabled_channels'] ?? [];
        if (
            (in_array('broadcast', $enabledChannels) || in_array('pusher', $enabledChannels))
            && ! empty($validated['pusher'])
        ) {
            $pusher = $validated['pusher'];
            $envUpdates = [
                'BROADCAST_CONNECTION' => 'pusher',
                'PUSHER_APP_ID' => $pusher['app_id'] ?? null,
                'PUSHER_APP_KEY' => $pusher['key'] ?? null,
                'PUSHER_APP_SECRET' => $pusher['secret'] ?? null,
                'PUSHER_APP_CLUSTER' => $pusher['cluster'] ?? null,
            ];

            $this->updateEnvironmentFile($envUpdates);
        }

        Artisan::call('config:clear');

        return Redirect::back()->with('success', 'Notification channels updated successfully.');
    }

    public function updateApiManagement(UpdateApiManagementRequest $request)
    {
        $generalSettingsService = app(GeneralSettingsService::class);

        $validated = $request->validated();

        $generalSettingsService->updateApiManagementConfig($validated);
        $generalSettingsService->updatePublicWebsiteSettings($validated);

        return Redirect::back()->with('success', 'API management settings updated successfully.');
    }

    public function updateAcademicCalendar(Request $request)
    {
        $this->authorize('updateSchool', GeneralSetting::class);

        $validated = $request->validate([
            'semester' => ['required', 'integer', 'in:1,2'],
            'school_starting_date' => ['required', 'date'],
            'school_ending_date' => ['required', 'date', 'after_or_equal:school_starting_date'],
        ]);

        $generalSettingsService = app(GeneralSettingsService::class);
        $generalSettingsService->updateGlobalAcademicCalendar($validated);

        return Redirect::back()->with('success', 'Academic calendar defaults updated successfully.');
    }

    private function ensureSiteSettingExists(string $name, mixed $value): void
    {
        $exists = DB::table('settings')
            ->where('group', SiteSettings::group())
            ->where('name', $name)
            ->exists();

        if ($exists) {
            return;
        }

        DB::table('settings')->insert([
            'group' => SiteSettings::group(),
            'name' => $name,
            'locked' => false,
            'payload' => json_encode($value, JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /** @param array<string, mixed> $additional */
    private function renderSystemManagementPage(string $component, string $section, string $ability, array $additional = []): Response
    {
        $this->authorize($ability, GeneralSetting::class);

        return Inertia::render($component, [...$this->getSystemManagementPayload($section), ...$additional]);
    }

    /**
     * @return array<string, mixed>
     */
    private function getSystemManagementPayload(string $activeSection): array
    {
        $generalSettingsService = app(GeneralSettingsService::class);
        $settings = $generalSettingsService->getGlobalSettingsModel();

        if (! $settings) {
            $settings = GeneralSetting::query()->create([
                'site_name' => $this->siteSettings->getAppName(),
            ]);
            GeneralSetting::clearCache();
            $generalSettingsService->replaceGlobalSettings($settings);
        }
        $activeSchoolId = $generalSettingsService->getActiveSchoolId();
        $activeSchool = $activeSchoolId
            ? School::find($activeSchoolId)
            : School::where('is_active', true)->first();

        // Fallback if no active school is set and no active school found
        if (! $activeSchool) {
            $activeSchool = School::first();
        }

        $schools = School::all();

        $socialiteConfig = app(SocialiteProviderService::class)->config();

        // Mail transport belongs to the deployment. Do not read historical
        // database values: immutable Swarm tasks receive credentials as secrets.
        $finalMailConfig = [
            'driver' => config('mail.default'),
            'email_from_address' => config('mail.from.address'),
            'email_from_name' => config('mail.from.name'),
            'delivery_mode' => config('mail.default') === 'log' ? 'log' : 'external',
            'managed_by' => 'deployment',
        ];

        $frontendSettings = $settings->toArray();
        $frontendSettings['email_settings'] = Arr::except(
            $settings->email_settings ?? [],
            ['password', 'sequenzy_api_key'],
        );

        // Auto-detect third party services (excluding those configured elsewhere)
        $allServices = config('services', []);
        $excludedServices = ['github', 'facebook', 'twitter', 'linkedin', 'google'];
        $thirdPartyServices = array_diff_key($allServices, array_flip($excludedServices));
        $user = Auth::user();
        $analyticsService = app(AnalyticsSettingsService::class);

        abort_unless($user instanceof User, 403);

        $permissions = $user->getAllPermissions()->pluck('name')->values()->all();

        return [
            'user' => [
                'name' => $user->name,
                'email' => $user->email,
                'avatar' => $user->avatar_url ?? null,
                'role' => $user->role?->getLabel() ?? 'Administrator',
                'permissions' => $permissions,
            ],
            'general_settings' => $frontendSettings,
            'active_school' => $activeSchool,
            'schools' => $schools,
            'access' => [
                'active_section' => $activeSection,
                'sections' => $this->getSectionAccessMap($user),
            ],
            'socialite_config' => $socialiteConfig,
            'mail_config' => $finalMailConfig,
            'analytics' => $analyticsService->getFrontendConfig(),
            'enrollment_pipeline' => $this->enrollmentPipelineService->getConfiguration(),
            'enrollment_stats' => $this->enrollmentPipelineService->getStatsConfiguration(),
            'api_management' => $generalSettingsService->getApiManagementConfig(),
            'grading_config' => app(GradingSystemService::class)->getConfig(),
            'id_sequences' => app(IdentifierGenerator::class)->configuration(),
            'courses_with_subjects' => app(GradingSystemService::class)->getCoursesWithSubjects(),
            'available_enrollment_courses' => Course::query()
                ->where('is_active', true)
                ->orderBy('title')
                ->get(['id', 'code', 'title'])
                ->map(fn (Course $course): array => [
                    'id' => $course->id,
                    'code' => $course->code,
                    'title' => $course->title,
                ])
                ->values()
                ->all(),
            'system_semester' => $generalSettingsService->getSystemDefaultSemester(),
            'system_school_year_start' => $generalSettingsService->getSystemDefaultSchoolYearStart(),
            'system_school_year_end' => $generalSettingsService->getSystemDefaultSchoolYearStart() + 1,
            'system_school_starting_date' => $generalSettingsService->getGlobalSchoolStartingDate()?->format('Y-m-d'),
            'system_school_ending_date' => $generalSettingsService->getGlobalSchoolEndingDate()?->format('Y-m-d'),
            'available_semesters' => $generalSettingsService->getAvailableSemesters(),
            'available_school_years' => $generalSettingsService->getAvailableSchoolYears(),
            'public_api_url' => url('/api/v1/public/settings'),
            'public_api_fields' => GeneralSettingsService::publicApiFieldDefinitions(),
            'available_roles' => Role::query()->orderBy('name')->pluck('name')->values(),
            // Branding settings from Spatie Settings
            'notification_channels' => $settings->more_configs['notification_channels'] ?? [
                'enabled_channels' => array_map(
                    fn (NotificationChannel $channel): string => $channel->value,
                    NotificationChannel::defaultChannels()
                ),
                'pusher' => [
                    'app_id' => config('broadcasting.connections.pusher.app_id', ''),
                    'key' => config('broadcasting.connections.pusher.key', ''),
                    'secret' => config('broadcasting.connections.pusher.secret', ''),
                    'cluster' => config('broadcasting.connections.pusher.options.cluster', 'mt1'),
                ],
                'sms' => [
                    'provider' => '',
                    'api_key' => '',
                    'sender_id' => '',
                ],
            ],
            'finance_document_settings' => app(FinanceDocumentSettingsService::class)->get(),
            'tuition_payment_schedule_settings' => app(TuitionPaymentScheduleSettingsService::class)->get(),
            'third_party_services' => $settings->more_configs['third_party_services'] ?? $thirdPartyServices,
            'branding' => [
                'app_name' => $this->siteSettings->app_name,
                'app_short_name' => $this->siteSettings->app_short_name,
                'organization_name' => $this->siteSettings->organization_name,
                'organization_short_name' => $this->siteSettings->organization_short_name,
                'organization_address' => $this->siteSettings->organization_address,
                'support_email' => $this->siteSettings->support_email,
                'support_phone' => $this->siteSettings->support_phone,
                'tagline' => $this->siteSettings->tagline,
                'copyright_text' => $this->siteSettings->copyright_text,
                'theme_color' => $this->siteSettings->theme_color,
                'currency' => $this->siteSettings->currency,
                'auth_layout' => $this->siteSettings->getAuthLayout(),
                'default_country_code' => $this->siteSettings->getDefaultCountryCode(),
                'logo' => $this->siteSettings->getLogo(),
                'favicon' => $this->siteSettings->getFavicon(),
            ],
        ];
    }

    /**
     * @return array<string, array{can_view: bool, can_update: bool, view_permission: string, update_permission: string|null}>
     */
    private function getSectionAccessMap(User $user): array
    {
        $access = [];

        foreach (SystemManagementPermissions::sectionKeys() as $section) {
            $viewPermission = SystemManagementPermissions::viewPermission($section);
            $updatePermission = SystemManagementPermissions::updatePermission($section);
            $canUpdate = $updatePermission !== null && $user->can(match ($section) {
                'school' => 'updateSchool',
                'pipeline' => 'updateEnrollmentPipeline',
                'seo' => 'updateSeo',
                'analytics' => 'updateAnalytics',
                'brand' => 'updateBrand',
                'socialite' => 'updateSocialite',
                'newsletter' => 'updateNewsletter',
                'api' => 'updateApi',
                'notifications' => 'updateNotifications',
                'finance_documents' => 'updateFinanceDocuments',
                'tuition_payment_schedule' => 'updateTuitionPaymentSchedule',
                'grading' => 'updateGrading',
                'identifiers' => 'updateIdentifiers',
                default => 'viewAny',
            }, GeneralSetting::class);

            $canView = $user->can(match ($section) {
                'school' => 'viewSchool',
                'pipeline' => 'viewEnrollmentPipeline',
                'seo' => 'viewSeo',
                'analytics' => 'viewAnalytics',
                'brand' => 'viewBrand',
                'socialite' => 'viewSocialite',
                'mail' => 'viewMail',
                'newsletter' => 'viewNewsletter',
                'api' => 'viewApi',
                'notifications' => 'viewNotifications',
                'finance_documents' => 'viewFinanceDocuments',
                'tuition_payment_schedule' => 'viewTuitionPaymentSchedule',
                'grading' => 'viewGrading',
                'identifiers' => 'viewIdentifiers',
                'pulse' => 'viewPulse',
            }, GeneralSetting::class);

            $access[$section] = [
                'can_view' => $canView,
                'can_update' => $canUpdate,
                'view_permission' => $viewPermission,
                'update_permission' => $updatePermission,
            ];
        }

        return $access;
    }

    private function deleteSchoolScopedRecords(int $schoolId): void
    {
        $tables = collect(Schema::getTableListing())
            ->filter(fn (string $table): bool => $table !== 'schools' && Schema::hasColumn($table, 'school_id'))
            ->reject(fn (string $table): bool => $table === 'users')
            ->values();

        $pendingTables = $tables->all();

        while ($pendingTables !== []) {
            $remainingTables = [];
            $deletedAnyTable = false;

            foreach ($pendingTables as $table) {
                try {
                    DB::table($table)->where('school_id', $schoolId)->delete();
                    $deletedAnyTable = true;
                } catch (QueryException) {
                    $remainingTables[] = $table;
                }
            }

            if (! $deletedAnyTable && $remainingTables !== []) {
                $unresolved = implode(', ', $remainingTables);

                throw new RuntimeException("Unable to delete school-scoped records due to FK constraints for tables: {$unresolved}");
            }

            $pendingTables = $remainingTables;
        }
    }

    /**
     * Update .env file with given key-value pairs.
     */
    private function updateEnvironmentFile(array $values): void
    {
        if (app()->environment('testing')) {
            return;
        }

        $envPath = base_path('.env');
        $content = file_get_contents($envPath);

        foreach ($values as $key => $value) {
            if ($value === null) {
                continue;
            }

            // If key exists, replace it
            if (preg_match("/^{$key}=.*/m", $content)) {
                $content = preg_replace("/^{$key}=.*/m", "{$key}={$value}", $content);
            } else {
                // Append if not exists
                $content .= "\n{$key}={$value}";
            }
        }

        file_put_contents($envPath, $content);
    }
}
