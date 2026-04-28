<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\GeneralSettingResource;
use App\Models\GeneralSetting;
use App\Services\GeneralSettingsService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final class GeneralSettingController extends Controller
{
    public function __construct(
        private readonly GeneralSettingsService $settingsService
    ) {}

    /**
     * Display a listing of general settings
     * Note: This system typically has only one general settings record
     */
    public function index(Request $request): AnonymousResourceCollection|JsonResponse
    {
        $query = GeneralSetting::query();

        // Include trashed records if requested
        if ($request->boolean('with_trashed', false)) {
            $query->withTrashed();
        }

        $settings = $query->get();

        if ($settings->isEmpty()) {
            return response()->json([
                'message' => 'No general settings found',
                'data' => null,
            ], 404);
        }

        return GeneralSettingResource::collection($settings);
    }

    /**
     * Store a newly created general setting
     *
     *
     * @throws ValidationException
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'site_name' => 'nullable|string|max:255',
            'site_description' => 'nullable|string',
            'theme_color' => 'nullable|string|max:50',
            'support_email' => 'nullable|email|max:255',
            'support_phone' => 'nullable|string|max:50',
            'google_analytics_id' => 'nullable|string|max:50',
            'posthog_html_snippet' => 'nullable|string',
            'analytics_enabled' => 'nullable|boolean',
            'analytics_provider' => 'nullable|string|in:google,ackee,umami,openpanel,custom',
            'analytics_script' => 'nullable|string',
            'analytics_settings' => 'nullable|array',
            'seo_title' => 'nullable|string|max:255',
            'seo_keywords' => 'nullable|string',
            'seo_metadata' => 'nullable|array',
            'email_settings' => 'nullable|array',
            'email_from_address' => 'nullable|email|max:255',
            'email_from_name' => 'nullable|string|max:255',
            'social_network' => 'nullable|array',
            'more_configs' => 'nullable|array',
            'school_starting_date' => 'nullable|date',
            'school_ending_date' => 'nullable|date|after_or_equal:school_starting_date',
            'school_portal_url' => 'nullable|url|max:255',
            'school_portal_enabled' => 'nullable|boolean',
            'online_enrollment_enabled' => 'nullable|boolean',
            'school_portal_maintenance' => 'nullable|boolean',
            'semester' => 'nullable|integer|in:1,2',
            'enrollment_courses' => 'nullable|array',
            'enrollment_courses.*' => 'exists:courses,id',
            'school_portal_logo' => 'nullable|string|max:255',
            'school_portal_favicon' => 'nullable|string|max:255',
            'school_portal_title' => 'nullable|string|max:255',
            'school_portal_description' => 'nullable|string',
            'enable_clearance_check' => 'nullable|boolean',
            'enable_signatures' => 'nullable|boolean',
            'enable_qr_codes' => 'nullable|boolean',
            'enable_public_transactions' => 'nullable|boolean',
            'enable_support_page' => 'nullable|boolean',
            'features' => 'nullable|array',
            'curriculum_year' => 'nullable|string|max:20',
            'inventory_module_enabled' => 'nullable|boolean',
            'library_module_enabled' => 'nullable|boolean',
            'enable_student_transfer_email_notifications' => 'nullable|boolean',
            'enable_faculty_transfer_email_notifications' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $setting = GeneralSetting::create($request->all());

            return response()->json([
                'message' => 'General settings created successfully',
                'data' => new GeneralSettingResource($setting),
            ], 201);

        } catch (Exception $e) {
            return response()->json([
                'message' => 'Failed to create general settings',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Display the specified general setting
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $query = GeneralSetting::query();

        // Include trashed if requested
        if ($request->boolean('with_trashed', false)) {
            $query->withTrashed();
        }

        $setting = $query->find($id);

        if (! $setting) {
            return response()->json([
                'message' => 'General settings not found',
                'data' => null,
            ], 404);
        }

        return response()->json([
            'message' => 'General settings retrieved successfully',
            'data' => new GeneralSettingResource($setting),
        ]);
    }

    /**
     * Update the specified general setting
     *
     *
     * @throws ValidationException
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $setting = GeneralSetting::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'site_name' => 'nullable|string|max:255',
            'site_description' => 'nullable|string',
            'theme_color' => 'nullable|string|max:50',
            'support_email' => 'nullable|email|max:255',
            'support_phone' => 'nullable|string|max:50',
            'google_analytics_id' => 'nullable|string|max:50',
            'posthog_html_snippet' => 'nullable|string',
            'analytics_enabled' => 'nullable|boolean',
            'analytics_provider' => 'nullable|string|in:google,ackee,umami,openpanel,custom',
            'analytics_script' => 'nullable|string',
            'analytics_settings' => 'nullable|array',
            'seo_title' => 'nullable|string|max:255',
            'seo_keywords' => 'nullable|string',
            'seo_metadata' => 'nullable|array',
            'email_settings' => 'nullable|array',
            'email_from_address' => 'nullable|email|max:255',
            'email_from_name' => 'nullable|string|max:255',
            'social_network' => 'nullable|array',
            'more_configs' => 'nullable|array',
            'school_starting_date' => 'nullable|date',
            'school_ending_date' => 'nullable|date|after_or_equal:school_starting_date',
            'school_portal_url' => 'nullable|url|max:255',
            'school_portal_enabled' => 'nullable|boolean',
            'online_enrollment_enabled' => 'nullable|boolean',
            'school_portal_maintenance' => 'nullable|boolean',
            'semester' => 'nullable|integer|in:1,2',
            'enrollment_courses' => 'nullable|array',
            'enrollment_courses.*' => 'exists:courses,id',
            'school_portal_logo' => 'nullable|string|max:255',
            'school_portal_favicon' => 'nullable|string|max:255',
            'school_portal_title' => 'nullable|string|max:255',
            'school_portal_description' => 'nullable|string',
            'enable_clearance_check' => 'nullable|boolean',
            'enable_signatures' => 'nullable|boolean',
            'enable_qr_codes' => 'nullable|boolean',
            'enable_public_transactions' => 'nullable|boolean',
            'enable_support_page' => 'nullable|boolean',
            'features' => 'nullable|array',
            'curriculum_year' => 'nullable|string|max:20',
            'inventory_module_enabled' => 'nullable|boolean',
            'library_module_enabled' => 'nullable|boolean',
            'enable_student_transfer_email_notifications' => 'nullable|boolean',
            'enable_faculty_transfer_email_notifications' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $setting->update($request->all());

            return response()->json([
                'message' => 'General settings updated successfully',
                'data' => new GeneralSettingResource($setting->fresh()),
            ], 200);

        } catch (Exception $e) {
            return response()->json([
                'message' => 'Failed to update general settings',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove the specified general setting (soft delete)
     */
    public function destroy(int $id): JsonResponse
    {
        try {
            $setting = GeneralSetting::findOrFail($id);
            $setting->delete();

            return response()->json([
                'message' => 'General settings deleted successfully',
            ], 200);

        } catch (Exception $e) {
            return response()->json([
                'message' => 'Failed to delete general settings',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Restore a soft-deleted general setting
     */
    public function restore(int $id): JsonResponse
    {
        try {
            $setting = GeneralSetting::onlyTrashed()->findOrFail($id);
            $setting->restore();

            return response()->json([
                'message' => 'General settings restored successfully',
                'data' => new GeneralSettingResource($setting->fresh()),
            ], 200);

        } catch (Exception $e) {
            return response()->json([
                'message' => 'Failed to restore general settings',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Force delete a general setting permanently
     */
    public function forceDestroy(int $id): JsonResponse
    {
        try {
            $setting = GeneralSetting::withTrashed()->findOrFail($id);
            $setting->forceDelete();

            return response()->json([
                'message' => 'General settings permanently deleted',
            ], 200);

        } catch (Exception $e) {
            return response()->json([
                'message' => 'Failed to permanently delete general settings',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get the current general settings (most commonly used endpoint)
     */
    public function current(Request $request): JsonResponse
    {
        $query = GeneralSetting::query();

        // Include trashed if requested
        if ($request->boolean('with_trashed', false)) {
            $query->withTrashed();
        }

        $setting = $query->first();

        if (! $setting) {
            return response()->json([
                'message' => 'No general settings found',
                'data' => null,
            ], 404);
        }

        return response()->json([
            'message' => 'Current general settings retrieved successfully',
            'data' => new GeneralSettingResource($setting),
        ]);
    }

    /**
     * Get specific setting value by key
     */
    public function getSetting(string $key, Request $request): JsonResponse
    {
        $query = GeneralSetting::query();

        if ($request->boolean('with_trashed', false)) {
            $query->withTrashed();
        }

        $setting = $query->first();

        if (! $setting) {
            return response()->json([
                'message' => 'No general settings found',
                'data' => null,
            ], 404);
        }

        $supportedKeys = array_merge(
            array_keys($setting->getAttributes()),
            ['school_year', 'school_year_string', 'semester_name']
        );

        if (! in_array($key, $supportedKeys, true)) {
            return response()->json([
                'message' => "Setting '{$key}' not found",
                'data' => null,
            ], 404);
        }

        $value = match ($key) {
            'school_year' => $setting->getSchoolYear(),
            'school_year_string' => $setting->getSchoolYearString(),
            'semester_name' => $setting->getSemester(),
            default => data_get($setting->toArray(), $key),
        };

        // Handle computed properties
        return match ($key) {
            'school_year' => response()->json([
                'message' => 'School year retrieved successfully',
                'data' => [
                    'key' => $key,
                    'value' => $setting->getSchoolYear(),
                ],
            ]),
            'school_year_string' => response()->json([
                'message' => 'School year string retrieved successfully',
                'data' => [
                    'key' => $key,
                    'value' => $setting->getSchoolYearString(),
                ],
            ]),
            'semester_name' => response()->json([
                'message' => 'Semester name retrieved successfully',
                'data' => [
                    'key' => $key,
                    'value' => $setting->getSemester(),
                ],
            ]),
            default => response()->json([
                'message' => 'Setting retrieved successfully',
                'data' => [
                    'key' => $key,
                    'value' => $value,
                ],
            ]),
        };
    }

    public function publicWebsiteSettings(): JsonResponse
    {
        if (! $this->settingsService->isPublicWebsiteSettingsApiEnabled()) {
            return response()->json([
                'message' => 'Public website settings API is disabled',
                'data' => null,
            ], 404);
        }

        if (! $this->settingsService->getGlobalSettingsModel() instanceof GeneralSetting) {
            return response()->json([
                'message' => 'No general settings found',
                'data' => null,
            ], 404);
        }

        return response()->json([
            'message' => 'Public website settings retrieved successfully',
            'data' => $this->settingsService->getPublicWebsiteSettings(),
        ]);
    }

    /**
     * Get settings from GeneralSettingsService
     */
    public function serviceSettings(): JsonResponse
    {
        return response()->json([
            'message' => 'Service settings retrieved successfully',
            'data' => [
                'current_semester' => $this->settingsService->getCurrentSemester(),
                'current_school_year_start' => $this->settingsService->getCurrentSchoolYearStart(),
                'current_school_year_string' => $this->settingsService->getCurrentSchoolYearString(),
                'available_semesters' => $this->settingsService->getAvailableSemesters(),
                'available_school_years' => $this->settingsService->getAvailableSchoolYears(),
                'student_portal_url' => $this->settingsService->getStudentPortalUrl(),
                'global_school_starting_date' => $this->settingsService->getGlobalSchoolStartingDate(),
                'global_school_ending_date' => $this->settingsService->getGlobalSchoolEndingDate(),
            ],
        ]);
    }

    /**
     * Update user's preferred semester
     */
    public function updateUserSemester(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'semester' => 'required|integer|in:1,2',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $success = $this->settingsService->updateUserSemester((int) $request->input('semester'));

        return response()->json([
            'message' => $success ? 'User semester updated successfully' : 'Failed to update user semester',
            'data' => [
                'current_semester' => $this->settingsService->getCurrentSemester(),
            ],
        ], $success ? 200 : 500);
    }

    /**
     * Update user's preferred school year
     */
    public function updateUserSchoolYear(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'school_year_start' => 'required|integer|min:2000|max:2100',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $success = $this->settingsService->updateUserSchoolYear((int) $request->input('school_year_start'));

        return response()->json([
            'message' => $success ? 'User school year updated successfully' : 'Failed to update user school year',
            'data' => [
                'current_school_year_start' => $this->settingsService->getCurrentSchoolYearStart(),
                'current_school_year_string' => $this->settingsService->getCurrentSchoolYearString(),
            ],
        ], $success ? 200 : 500);
    }

    /**
     * Get user's current preferences
     */
    public function userPreferences(): JsonResponse
    {
        return response()->json([
            'message' => 'User preferences retrieved successfully',
            'data' => [
                'current_semester' => $this->settingsService->getCurrentSemester(),
                'current_school_year_start' => $this->settingsService->getCurrentSchoolYearStart(),
                'current_school_year_string' => $this->settingsService->getCurrentSchoolYearString(),
                'user_settings_model' => $this->settingsService->getUserSettingsModel(),
            ],
        ]);
    }

    /**
     * Get global settings model with helper methods
     */
    public function globalSettings(): JsonResponse
    {
        return response()->json([
            'message' => 'Global settings retrieved successfully',
            'data' => [
                'settings_model' => $this->settingsService->getGlobalSettingsModel(),
                'student_portal_url' => $this->settingsService->getStudentPortalUrl(),
                'global_setting_example' => $this->settingsService->getGlobalSetting('site_name', 'Default Site Name'),
            ],
        ]);
    }

    /**
     * Update user preferences in bulk
     */
    public function updateUserPreferences(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'semester' => 'sometimes|integer|in:1,2',
            'school_year_start' => 'sometimes|integer|min:2000|max:2100',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $semesterUpdated = false;
        $schoolYearUpdated = false;

        if ($request->filled('semester')) {
            $semesterUpdated = $this->settingsService->updateUserSemester((int) $request->input('semester'));
        }

        if ($request->filled('school_year_start')) {
            $schoolYearUpdated = $this->settingsService->updateUserSchoolYear((int) $request->input('school_year_start'));
        }

        $success = $semesterUpdated || $schoolYearUpdated;

        return response()->json([
            'message' => $success ? 'User preferences updated successfully' : 'No changes made',
            'data' => [
                'current_semester' => $this->settingsService->getCurrentSemester(),
                'current_school_year_start' => $this->settingsService->getCurrentSchoolYearStart(),
                'current_school_year_string' => $this->settingsService->getCurrentSchoolYearString(),
                'updates' => [
                    'semester' => $semesterUpdated,
                    'school_year_start' => $schoolYearUpdated,
                ],
            ],
        ], $success ? 200 : 400);
    }
}
