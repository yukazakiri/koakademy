<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\NotificationChannel;
use App\Http\Requests\Administrators\StoreSchoolRequest;
use App\Http\Requests\Administrators\UpdateApiManagementRequest;
use App\Http\Requests\Administrators\UpdateSchoolRequest;
use App\Http\Requests\Administrators\UpdateSchoolStatusRequest;
use App\Models\GeneralSetting;
use App\Models\School;
use App\Models\User;
use App\Services\AnalyticsSettingsService;
use App\Services\EnrollmentPipelineService;
use App\Services\GeneralSettingsService;
use App\Services\GradingSystemService;
use App\Services\LogoConversionService;
use App\Settings\SiteSettings;
use App\Support\SystemManagementPermissions;
use Exception;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;
use Spatie\Permission\Models\Role;

final class AdministratorSystemManagementController extends Controller
{
    public function __construct(
        private readonly SiteSettings $siteSettings,
        private readonly EnrollmentPipelineService $enrollmentPipelineService
    ) {}

    public function index(): RedirectResponse
    {
        $user = Auth::user();

        abort_unless($user instanceof User, 403);

        $firstAccessibleSection = $this->resolveFirstAccessibleSection($user);

        abort_if($firstAccessibleSection === null, 403);

        return Redirect::route("administrators.system-management.{$firstAccessibleSection}.index");
    }

    public function school(): Response
    {
        return $this->renderSystemManagementPage('administrators/system-management/school', 'school', 'viewSchool');
    }

    public function enrollmentPipeline(): Response
    {
        return $this->renderSystemManagementPage('administrators/system-management/enrollment-pipeline', 'pipeline', 'viewEnrollmentPipeline');
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

    public function pulse(): Response
    {
        return $this->renderSystemManagementPage('administrators/system-management/pulse', 'pulse', 'viewPulse');
    }

    public function grading(): Response
    {
        return $this->renderSystemManagementPage('administrators/system-management/grading', 'grading', 'viewGrading');
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
            'description' => 'nullable|string',
            'location' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:255',
        ]);

        $school = School::findOrFail($validated['school_id']);
        $school->update([
            'name' => $validated['name'],
            'code' => $validated['code'],
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
            'description' => $validated['description'] ?? null,
            'location' => $validated['location'] ?? null,
            'phone' => $validated['phone'] ?? null,
            'email' => $validated['email'] ?? null,
            'dean_name' => $validated['dean_name'] ?? null,
            'dean_email' => $validated['dean_email'] ?? null,
        ]);

        return Redirect::back()->with('success', 'School record updated successfully.');
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
            'site_description' => 'nullable|string',
            'seo_title' => 'nullable|string|max:255',
            'seo_keywords' => 'nullable|string',
            'seo_metadata' => 'nullable|array',
            'seo_metadata.robots' => 'nullable|string',
            'seo_metadata.og_image' => 'nullable|string',
            'seo_metadata.twitter_handle' => 'nullable|string',
            'seo_metadata.twitter_card' => 'nullable|string',
            'seo_metadata.canonical_url' => 'nullable|string',
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
            'logo' => 'nullable|file|mimes:jpeg,png,gif,webp,svg|max:5120',
        ]);

        // Handle single logo upload — generates all formats automatically
        if ($request->hasFile('logo')) {
            $paths = app(LogoConversionService::class)->process($request->file('logo'));
            $this->siteSettings->logo = $paths['logo'];
            $this->siteSettings->favicon = $paths['favicon'];
            $this->siteSettings->og_image = $paths['og_image'];
        }

        // Update Spatie Settings
        $this->siteSettings->app_name = $validated['app_name'] ?? null;
        $this->siteSettings->app_short_name = $validated['app_short_name'] ?? null;
        $this->siteSettings->organization_name = $validated['organization_name'] ?? null;
        $this->siteSettings->organization_short_name = $validated['organization_short_name'] ?? null;
        $this->siteSettings->organization_address = $validated['organization_address'] ?? null;
        $this->siteSettings->support_email = $validated['support_email'] ?? null;
        $this->siteSettings->support_phone = $validated['support_phone'] ?? null;
        $this->siteSettings->tagline = $validated['tagline'] ?? null;
        $this->siteSettings->copyright_text = $validated['copyright_text'] ?? null;
        $this->siteSettings->theme_color = $validated['theme_color'] ?? null;
        $this->siteSettings->currency = $validated['currency'] ?? null;
        $this->siteSettings->auth_layout = $validated['auth_layout'] ?? 'split';
        $this->siteSettings->save();

        return Redirect::back()->with('success', 'Brand settings updated successfully. Logo has been converted for all formats — favicon, PWA icons, and OG image.');
    }

    public function updateSocialite(Request $request)
    {
        $this->authorize('updateSocialite', GeneralSetting::class);

        $settings = GeneralSetting::first();
        $validated = $request->validate([
            'facebook_client_id' => 'nullable|string',
            'facebook_client_secret' => 'nullable|string',
            'google_client_id' => 'nullable|string',
            'google_client_secret' => 'nullable|string',
            'twitter_client_id' => 'nullable|string',
            'twitter_client_secret' => 'nullable|string',
            'github_client_id' => 'nullable|string',
            'github_client_secret' => 'nullable|string',
            'linkedin_client_id' => 'nullable|string',
            'linkedin_client_secret' => 'nullable|string',
        ]);

        // Save to Database
        $settings->update(['social_network' => $validated]);

        // Update .env file
        $envUpdates = [
            'FACEBOOK_CLIENT_ID' => $validated['facebook_client_id'],
            'FACEBOOK_CLIENT_SECRET' => $validated['facebook_client_secret'],
            'GOOGLE_CLIENT_ID' => $validated['google_client_id'],
            'GOOGLE_CLIENT_SECRET' => $validated['google_client_secret'],
            'TWITTER_CLIENT_ID' => $validated['twitter_client_id'],
            'TWITTER_CLIENT_SECRET' => $validated['twitter_client_secret'],
            'GITHUB_CLIENT_ID' => $validated['github_client_id'],
            'GITHUB_CLIENT_SECRET' => $validated['github_client_secret'],
            'LINKEDIN_CLIENT_ID' => $validated['linkedin_client_id'],
            'LINKEDIN_CLIENT_SECRET' => $validated['linkedin_client_secret'],
        ];

        $this->updateEnvironmentFile($envUpdates);

        // Clear config cache to apply changes
        Artisan::call('config:clear');

        return Redirect::back()->with('success', 'Socialite configuration updated and environment synced.');
    }

    public function updateMail(Request $request)
    {
        $this->authorize('updateMail', GeneralSetting::class);

        $settings = GeneralSetting::first();
        $validated = $request->validate([
            'email_from_address' => 'required|email',
            'email_from_name' => 'required|string',
            'driver' => 'required|string',
            'host' => 'nullable|string',
            'port' => 'nullable|integer',
            'username' => 'nullable|string',
            'password' => 'nullable|string',
            'encryption' => 'nullable|string',
        ]);

        $settings->update([
            'email_from_address' => $validated['email_from_address'],
            'email_from_name' => $validated['email_from_name'],
            'email_settings' => array_diff_key($validated, array_flip(['email_from_address', 'email_from_name'])),
        ]);

        // Update .env file
        $envUpdates = [
            'MAIL_MAILER' => $validated['driver'],
            'MAIL_HOST' => $validated['host'],
            'MAIL_PORT' => $validated['port'],
            'MAIL_USERNAME' => $validated['username'],
            'MAIL_PASSWORD' => $validated['password'],
            'MAIL_ENCRYPTION' => $validated['encryption'],
            'MAIL_FROM_ADDRESS' => $validated['email_from_address'],
            'MAIL_FROM_NAME' => '"'.$validated['email_from_name'].'"',
        ];

        $this->updateEnvironmentFile($envUpdates);

        // Clear config cache
        Artisan::call('config:clear');

        return Redirect::back()->with('success', 'Mail configuration updated and environment synced.');
    }

    public function updateEnrollmentPipeline(Request $request)
    {
        $this->authorize('updateEnrollmentPipeline', GeneralSetting::class);

        $settings = GeneralSetting::firstOrCreate([
            'site_name' => $this->siteSettings->getAppName(),
        ]);

        $validated = $request->validate([
            'submitted_label' => ['required', 'string', 'max:100'],
            'steps' => ['nullable', 'array', 'min:1'],
            'steps.*.key' => ['nullable', 'string', 'max:100'],
            'steps.*.status' => ['required_with:steps', 'string', 'max:100'],
            'steps.*.label' => ['required_with:steps', 'string', 'max:100'],
            'steps.*.color' => ['required_with:steps', 'string', 'max:50'],
            'steps.*.allowed_roles' => ['nullable', 'array'],
            'steps.*.allowed_roles.*' => ['string', 'exists:roles,name'],
            'steps.*.action_type' => ['nullable', 'string', 'in:standard,department_verification,cashier_verification'],
            'entry_step_key' => ['nullable', 'string', 'max:100'],
            'completion_step_key' => ['nullable', 'string', 'max:100'],
            'pending_status' => ['required_without:steps', 'string', 'max:100'],
            'pending_label' => ['required_without:steps', 'string', 'max:100'],
            'pending_color' => ['required_without:steps', 'string', 'max:50'],
            'pending_roles' => ['nullable', 'array'],
            'pending_roles.*' => ['string', 'exists:roles,name'],
            'department_verified_status' => ['required_without:steps', 'string', 'max:100'],
            'department_verified_label' => ['required_without:steps', 'string', 'max:100'],
            'department_verified_color' => ['required_without:steps', 'string', 'max:50'],
            'department_verified_roles' => ['nullable', 'array'],
            'department_verified_roles.*' => ['string', 'exists:roles,name'],
            'cashier_verified_status' => ['required_without:steps', 'string', 'max:100'],
            'cashier_verified_label' => ['required_without:steps', 'string', 'max:100'],
            'cashier_verified_color' => ['required_without:steps', 'string', 'max:50'],
            'cashier_verified_roles' => ['nullable', 'array'],
            'cashier_verified_roles.*' => ['string', 'exists:roles,name'],
            'additional_steps' => ['nullable', 'array'],
            'additional_steps.*.status' => ['required', 'string', 'max:100'],
            'additional_steps.*.label' => ['required', 'string', 'max:100'],
            'additional_steps.*.color' => ['required', 'string', 'max:50'],
            'additional_steps.*.allowed_roles' => ['nullable', 'array'],
            'additional_steps.*.allowed_roles.*' => ['string', 'exists:roles,name'],
            'enrollment_stats' => ['nullable', 'array'],
            'enrollment_stats.cards' => ['nullable', 'array'],
            'enrollment_stats.cards.*.key' => ['nullable', 'string', 'max:100'],
            'enrollment_stats.cards.*.label' => ['required_with:enrollment_stats.cards', 'string', 'max:100'],
            'enrollment_stats.cards.*.metric' => ['required_with:enrollment_stats.cards', 'string', 'in:total_records,active_records,trashed_records,status_count,paid_count'],
            'enrollment_stats.cards.*.statuses' => ['nullable', 'array'],
            'enrollment_stats.cards.*.statuses.*' => ['string', 'max:100'],
            'enrollment_stats.cards.*.color' => ['nullable', 'string', 'max:50'],
        ]);

        $moreConfigs = $settings->more_configs ?? [];
        $moreConfigs['enrollment_pipeline'] = $this->enrollmentPipelineService->sanitizeForStorage($validated);
        $moreConfigs['enrollment_stats'] = $this->enrollmentPipelineService->sanitizeStatsForStorage(
            $validated['enrollment_stats'] ?? []
        );

        $settings->update(['more_configs' => $moreConfigs]);

        return Redirect::back()->with('success', 'Enrollment pipeline updated successfully.');
    }

    public function sendTestEmail(Request $request)
    {
        $this->authorize('updateMail', GeneralSetting::class);

        $request->validate([
            'to' => 'required|email',
        ]);

        try {
            Mail::raw('This is a test email from your system configuration.', function ($message) use ($request): void {
                $message->to($request->to)
                    ->subject('Test Email - System Configuration');
            });

            return response()->json(['message' => 'Test email sent successfully!'], 200);
        } catch (Exception $e) {
            return response()->json(['message' => 'Failed to send test email: '.$e->getMessage()], 500);
        }
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

    private function renderSystemManagementPage(string $component, string $section, string $ability): Response
    {
        $this->authorize($ability, GeneralSetting::class);

        return Inertia::render($component, $this->getSystemManagementPayload($section));
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

        // Merge config defaults if DB is empty
        $socialiteConfig = $settings->social_network ?? [];
        $defaults = [
            'facebook_client_id' => config('services.facebook.client_id'),
            'facebook_client_secret' => config('services.facebook.client_secret'),
            'google_client_id' => config('services.google.client_id'),
            'google_client_secret' => config('services.google.client_secret'),
            'twitter_client_id' => config('services.twitter.client_id'),
            'twitter_client_secret' => config('services.twitter.client_secret'),
            'github_client_id' => config('services.github.client_id'),
            'github_client_secret' => config('services.github.client_secret'),
            'linkedin_client_id' => config('services.linkedin.client_id'),
            'linkedin_client_secret' => config('services.linkedin.client_secret'),
        ];

        $socialiteConfig = array_merge($defaults, $socialiteConfig);

        // Merge mail defaults with DB
        $mailConfig = $settings->email_settings ?? [];
        $mailDefaults = [
            'driver' => config('mail.default'),
            'host' => config('mail.mailers.smtp.host'),
            'port' => config('mail.mailers.smtp.port'),
            'username' => config('mail.mailers.smtp.username'),
            'password' => config('mail.mailers.smtp.password'),
            'encryption' => config('mail.mailers.smtp.encryption'),
            'email_from_address' => config('mail.from.address'),
            'email_from_name' => config('mail.from.name'),
        ];
        $finalMailConfig = array_merge($mailDefaults, $mailConfig);
        if ($settings->email_from_address) {
            $finalMailConfig['email_from_address'] = $settings->email_from_address;
        }
        if ($settings->email_from_name) {
            $finalMailConfig['email_from_name'] = $settings->email_from_name;
        }

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
            'general_settings' => $settings,
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
            'courses_with_subjects' => app(GradingSystemService::class)->getCoursesWithSubjects(),
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
                'mail' => 'updateMail',
                'api' => 'updateApi',
                'notifications' => 'updateNotifications',
                'grading' => 'updateGrading',
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
                'api' => 'viewApi',
                'notifications' => 'viewNotifications',
                'grading' => 'viewGrading',
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

    private function resolveFirstAccessibleSection(User $user): ?string
    {
        foreach (SystemManagementPermissions::sectionKeys() as $section) {
            if ($this->getSectionAccessMap($user)[$section]['can_view']) {
                return $section;
            }
        }

        return null;
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
