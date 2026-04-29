<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\GeneralSetting;
use App\Models\UserSetting;
use Exception;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

final class GeneralSettingsService
{
    private const string API_MANAGEMENT_CONFIG_KEY = 'api_management';

    /**
     * @var array<string, array{label: string, description: string, input: string, editable: bool}>
     */
    private const array PUBLIC_API_FIELD_DEFINITIONS = [
        'site_name' => [
            'label' => 'Site name',
            'description' => 'Primary website title shown in headers and browser tabs.',
            'input' => 'text',
            'editable' => true,
        ],
        'site_description' => [
            'label' => 'Site description',
            'description' => 'Short introductory copy for the public website.',
            'input' => 'textarea',
            'editable' => true,
        ],
        'theme_color' => [
            'label' => 'Theme color',
            'description' => 'Brand accent color that frontend clients can reuse.',
            'input' => 'text',
            'editable' => true,
        ],
        'support_email' => [
            'label' => 'Support email',
            'description' => 'Public contact email for help and inquiries.',
            'input' => 'email',
            'editable' => true,
        ],
        'support_phone' => [
            'label' => 'Support phone',
            'description' => 'Public phone number for general support.',
            'input' => 'text',
            'editable' => true,
        ],
        'social_network' => [
            'label' => 'Social links',
            'description' => 'Configured public social media profiles.',
            'input' => 'social_links',
            'editable' => true,
        ],
        'school_portal_url' => [
            'label' => 'School portal URL',
            'description' => 'Login or portal URL that the website can link to.',
            'input' => 'url',
            'editable' => true,
        ],
        'school_portal_enabled' => [
            'label' => 'Portal enabled flag',
            'description' => 'Lets the website know whether the portal should be promoted.',
            'input' => 'boolean',
            'editable' => true,
        ],
        'online_enrollment_enabled' => [
            'label' => 'Online enrollment enabled',
            'description' => 'Signals whether enrollment calls-to-action should be visible.',
            'input' => 'boolean',
            'editable' => true,
        ],
        'school_portal_maintenance' => [
            'label' => 'Portal maintenance mode',
            'description' => 'Useful for showing maintenance banners on the website.',
            'input' => 'boolean',
            'editable' => true,
        ],
        'school_portal_title' => [
            'label' => 'Portal title',
            'description' => 'Friendly public label for the student portal.',
            'input' => 'text',
            'editable' => true,
        ],
        'school_portal_description' => [
            'label' => 'Portal description',
            'description' => 'Short helper text describing the portal.',
            'input' => 'textarea',
            'editable' => true,
        ],
        'school_year_string' => [
            'label' => 'School year string',
            'description' => 'Computed school year in a human-readable format.',
            'input' => 'computed',
            'editable' => false,
        ],
        'semester_name' => [
            'label' => 'Semester name',
            'description' => 'Computed current semester label.',
            'input' => 'computed',
            'editable' => false,
        ],
    ];

    /**
     * @var list<string>
     */
    private const array DEFAULT_PUBLIC_API_FIELDS = [
        'site_name',
        'site_description',
        'theme_color',
        'support_email',
        'support_phone',
        'social_network',
        'school_portal_url',
        'school_portal_enabled',
        'online_enrollment_enabled',
        'school_portal_maintenance',
        'school_portal_title',
        'school_portal_description',
    ];

    private ?GeneralSetting $generalSetting = null;

    private ?UserSetting $userSetting = null;

    private bool $userSettingLoaded = false;

    public function __construct()
    {
        try {
            // Cache only the ID, not the whole model
            $globalSettingsId = Cache::rememberForever(
                'general_settings_id',
                fn () => optional(GeneralSetting::query()->first())->id
            );

            $cachedSetting = $globalSettingsId ? GeneralSetting::query()->find($globalSettingsId) : null;

            if ($cachedSetting !== null) {
                $this->generalSetting = $cachedSetting;

                return;
            }

            $freshSetting = GeneralSetting::query()->first();

            if ($freshSetting !== null) {
                Cache::forever('general_settings_id', $freshSetting->id);
            } else {
                Cache::forget('general_settings_id');
            }

            $this->generalSetting = $freshSetting;
        } catch (Exception $exception) {
            Log::error('Failed to initialize GeneralSettingsService', [
                'error' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString(),
            ]);

            throw $exception;
        }
    }

    /**
     * @return array<string, array{label: string, description: string, input: string, editable: bool}>
     */
    public static function publicApiFieldDefinitions(): array
    {
        return self::PUBLIC_API_FIELD_DEFINITIONS;
    }

    /**
     * @return list<string>
     */
    public static function editablePublicApiFields(): array
    {
        return array_keys(array_filter(
            self::PUBLIC_API_FIELD_DEFINITIONS,
            static fn (array $definition): bool => $definition['editable'] === true
        ));
    }

    /**
     * Normalize a school year string to the canonical spaced format.
     * Accepts both "2024-2025" and "2024 - 2025" and returns "2024 - 2025".
     */
    public static function normalizeSchoolYear(string $schoolYear): string
    {
        // Strip all spaces then re-insert around the dash
        $compact = str_replace(' ', '', $schoolYear);

        if (str_contains($compact, '-')) {
            [$start, $end] = explode('-', $compact, 2);

            return $start.' - '.$end;
        }

        return $schoolYear;
    }

    /**
     * Get the effective current semester.
     * Prioritizes user's setting, then global, then a default.
     */
    public function getCurrentSemester(): int
    {
        $userSetting = $this->getUserSetting();

        if ($userSetting && ! is_null($userSetting->semester)) {
            return (int) $userSetting->semester;
        }

        if (
            $this->generalSetting &&
            ! is_null($this->generalSetting->semester)
        ) {
            return (int) $this->generalSetting->semester;
        }

        return 1; // Default semester
    }

    /**
     * Get the effective current school year start.
     * Prioritizes user's setting, then global, then a default.
     */
    public function getCurrentSchoolYearStart(): int
    {
        $userSetting = $this->getUserSetting();

        if (
            $userSetting &&
            ! is_null($userSetting->school_year_start)
        ) {
            return (int) $userSetting->school_year_start;
        }

        if (
            $this->generalSetting &&
            $this->generalSetting->school_starting_date
        ) {
            $year = $this->generalSetting->getSchoolYearStarting();

            return $year !== 'N/A' ? (int) $year : (int) date('Y');
        }

        return (int) date('Y'); // Default to current year
    }

    /**
     * Get the display string for the current school year.
     * e.g., "2023 - 2024"
     */
    public function getCurrentSchoolYearString(): string
    {
        $startYear = $this->getCurrentSchoolYearStart();

        return $startYear.' - '.($startYear + 1);
    }

    /**
     * Get the global school starting date.
     */
    public function getGlobalSchoolStartingDate()
    {
        return $this->generalSetting?->school_starting_date;
    }

    /**
     * Get the global school ending date.
     */
    public function getGlobalSchoolEndingDate()
    {
        return $this->generalSetting?->school_ending_date;
    }

    /**
     * Update the user's preferred semester.
     */
    public function updateUserSemester(int $semester): bool
    {
        try {
            $userSetting = $this->getUserSetting();

            if (Auth::check() && $userSetting) {
                $userSetting->semester = $semester;
                $result = $userSetting->save();

                Log::info('User semester updated', [
                    'user_id' => Auth::id(),
                    'semester' => $semester,
                    'success' => $result,
                ]);

                return $result;
            }

            Log::warning('Failed to update user semester: User not authenticated');

            return false;
        } catch (Exception $exception) {
            Log::error('Error updating user semester', [
                'user_id' => Auth::id(),
                'semester' => $semester,
                'error' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString(),
            ]);

            return false;
        }
    }

    /**
     * Update the user's preferred school year start.
     */
    public function updateUserSchoolYear(int $startYear): bool
    {
        try {
            $userSetting = $this->getUserSetting();

            if (Auth::check() && $userSetting) {
                $userSetting->school_year_start = $startYear;
                $result = $userSetting->save();

                Log::info('User school year updated', [
                    'user_id' => Auth::id(),
                    'school_year_start' => $startYear,
                    'success' => $result,
                ]);

                return $result;
            }

            Log::warning('Failed to update user school year: User not authenticated');

            return false;
        } catch (Exception $exception) {
            Log::error('Error updating user school year', [
                'user_id' => Auth::id(),
                'school_year_start' => $startYear,
                'error' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString(),
            ]);

            return false;
        }
    }

    /**
     * Get the effective active school ID.
     * Prioritizes user's setting, then user's primary school_id.
     */
    public function getActiveSchoolId(): ?int
    {
        $userSetting = $this->getUserSetting();

        if ($userSetting && ! is_null($userSetting->active_school_id)) {
            return (int) $userSetting->active_school_id;
        }

        if (Auth::check() && ! is_null(Auth::user()->school_id)) {
            return (int) Auth::user()->school_id;
        }

        return null;
    }

    /**
     * Update the user's preferred active school.
     */
    public function updateActiveSchoolId(?int $schoolId): bool
    {
        try {
            $userSetting = $this->getUserSetting();

            if (Auth::check() && $userSetting) {
                $userSetting->active_school_id = $schoolId;
                $result = $userSetting->save();

                Log::info('User active school updated', [
                    'user_id' => Auth::id(),
                    'active_school_id' => $schoolId,
                    'success' => $result,
                ]);

                return $result;
            }

            Log::warning('Failed to update user active school: User not authenticated');

            return false;
        } catch (Exception $exception) {
            Log::error('Error updating user active school', [
                'user_id' => Auth::id(),
                'active_school_id' => $schoolId,
                'error' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString(),
            ]);

            return false;
        }
    }

    /**
     * Update the global academic calendar settings.
     *
     * @param  array<string, mixed>  $data
     */
    public function updateGlobalAcademicCalendar(array $data): ?GeneralSetting
    {
        $settings = $this->getGlobalSettingsModel();

        if (! $settings instanceof GeneralSetting) {
            $settings = GeneralSetting::query()->create([
                'site_name' => config('app.name'),
            ]);

            $this->generalSetting = $settings;
        }

        $updates = [];

        if (array_key_exists('semester', $data)) {
            $updates['semester'] = (int) $data['semester'];
        }

        if (array_key_exists('school_starting_date', $data) && $data['school_starting_date'] !== null) {
            $updates['school_starting_date'] = $data['school_starting_date'];
        }

        if (array_key_exists('school_ending_date', $data) && $data['school_ending_date'] !== null) {
            $updates['school_ending_date'] = $data['school_ending_date'];
        }

        if ($updates !== []) {
            $settings->update($updates);
            $this->generalSetting = $settings->fresh();
            GeneralSetting::clearCache();
        }

        return $this->generalSetting;
    }

    /**
     * Get the system default semester (from global settings).
     */
    public function getSystemDefaultSemester(): int
    {
        if (
            $this->generalSetting &&
            ! is_null($this->generalSetting->semester)
        ) {
            return (int) $this->generalSetting->semester;
        }

        return 1; // Default semester
    }

    /**
     * Get the system default school year start (from global settings).
     */
    public function getSystemDefaultSchoolYearStart(): int
    {
        if (
            $this->generalSetting &&
            $this->generalSetting->school_starting_date
        ) {
            $year = $this->generalSetting->getSchoolYearStarting();

            return $year !== 'N/A' ? (int) $year : (int) date('Y');
        }

        return (int) date('Y'); // Default to current year
    }

    /**
     * Get available semesters (can be extended if semesters become dynamic).
     */
    public function getAvailableSemesters(): array
    {
        return [1 => '1st Semester', 2 => '2nd Semester'];
    }

    /**
     * Populate available school years based on a range around a given start year.
     * If no start year is provided, it uses the effective current school year start.
     */
    public function getAvailableSchoolYears(
        ?int $referenceStartYear = null,
        int $range = 3
    ): array {
        $effectiveStartYear =
            $referenceStartYear ?? $this->getCurrentSchoolYearStart();
        $startYearRange = $effectiveStartYear - $range;
        $endYearRange = $effectiveStartYear + $range;
        $availableSchoolYears = [];

        for ($year = $startYearRange; $year <= $endYearRange; $year++) {
            $displayYear = $year.' - '.($year + 1);
            $availableSchoolYears[$year] = $displayYear;
        }

        // Ensure the effective start year is in the list
        if (! array_key_exists($effectiveStartYear, $availableSchoolYears)) {
            $availableSchoolYears[$effectiveStartYear] =
                $effectiveStartYear.' - '.($effectiveStartYear + 1);
            ksort($availableSchoolYears);
        }

        return $availableSchoolYears;
    }

    /**
     * Get a specific global setting by key.
     */
    public function getGlobalSetting(string $key, mixed $default = null): mixed
    {
        if (! $this->generalSetting instanceof GeneralSetting) {
            return $default;
        }

        if (! $this->hasSettingKey($key)) {
            return $default;
        }

        return $this->resolveSettingValue($key);
    }

    /**
     * Get the entire global settings model instance.
     */
    public function getGlobalSettingsModel(): ?GeneralSetting
    {
        if (! $this->generalSetting instanceof GeneralSetting) {
            $this->generalSetting = GeneralSetting::query()->first();
        }

        return $this->generalSetting;
    }

    public function replaceGlobalSettings(?GeneralSetting $generalSetting): void
    {
        $this->generalSetting = $generalSetting;
    }

    /**
     * Get the entire user settings model instance for the current user.
     */
    public function getUserSettingsModel(): ?UserSetting
    {
        return $this->getUserSetting();
    }

    /**
     * Get the student portal URL from global settings.
     */
    public function getStudentPortalUrl(): ?string
    {
        return $this->getGlobalSetting('school_portal_url');
    }

    /**
     * Get the currency from global settings.
     */
    public function getCurrency(): string
    {
        return $this->getGlobalSetting('currency', 'PHP');
    }

    /**
     * Get the current school year in the canonical spaced format.
     * e.g., "2024 - 2025"
     */
    public function getCurrentSchoolYearSpaced(): string
    {
        $startYear = $this->getCurrentSchoolYearStart();

        return $startYear.' - '.($startYear + 1);
    }

    /**
     * Get the current school year in the compact dash-only format.
     * e.g., "2024-2025"
     */
    public function getCurrentSchoolYearCompact(): string
    {
        $startYear = $this->getCurrentSchoolYearStart();

        return $startYear.'-'.($startYear + 1);
    }

    /**
     * Get both school year formats as an array for use in whereIn() clauses.
     * Handles the inconsistency where some records were seeded with "2024-2025"
     * and others with "2024 - 2025".
     *
     * @return array{string, string}
     */
    public function getCurrentSchoolYearVariants(): array
    {
        $spaced = $this->getCurrentSchoolYearSpaced();
        $compact = $this->getCurrentSchoolYearCompact();

        return array_unique([$spaced, $compact]);
    }

    /**
     * @return array{
     *     public_api_enabled: bool,
     *     public_settings_enabled: bool,
     *     public_settings_fields: list<string>
     * }
     */
    public function getApiManagementConfig(): array
    {
        $savedConfig = data_get(
            $this->getGlobalSettingsModel()?->more_configs,
            self::API_MANAGEMENT_CONFIG_KEY,
            []
        );

        $config = array_merge([
            'public_api_enabled' => true,
            'public_settings_enabled' => true,
            'public_settings_fields' => self::DEFAULT_PUBLIC_API_FIELDS,
        ], is_array($savedConfig) ? $savedConfig : []);

        $config['public_api_enabled'] = (bool) ($config['public_api_enabled'] ?? true);
        $config['public_settings_enabled'] = (bool) ($config['public_settings_enabled'] ?? true);
        $config['public_settings_fields'] = $this->sanitizePublicApiFields($config['public_settings_fields'] ?? []);

        return $config;
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array{
     *     public_api_enabled: bool,
     *     public_settings_enabled: bool,
     *     public_settings_fields: list<string>
     * }
     */
    public function updateApiManagementConfig(array $attributes): array
    {
        $settings = $this->getGlobalSettingsModel();

        if (! $settings instanceof GeneralSetting) {
            $settings = GeneralSetting::query()->create([
                'site_name' => config('app.name'),
            ]);

            $this->generalSetting = $settings;
        }

        $config = [
            'public_api_enabled' => (bool) ($attributes['public_api_enabled'] ?? true),
            'public_settings_enabled' => (bool) ($attributes['public_settings_enabled'] ?? true),
            'public_settings_fields' => $this->sanitizePublicApiFields($attributes['public_settings_fields'] ?? []),
        ];

        $moreConfigs = is_array($settings->more_configs) ? $settings->more_configs : [];
        $moreConfigs[self::API_MANAGEMENT_CONFIG_KEY] = $config;

        $settings->update([
            'more_configs' => $moreConfigs,
        ]);

        $this->generalSetting = $settings->fresh();

        return $this->getApiManagementConfig();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function updatePublicWebsiteSettings(array $attributes): ?GeneralSetting
    {
        $settings = $this->getGlobalSettingsModel();

        if (! $settings instanceof GeneralSetting) {
            $settings = GeneralSetting::query()->create([
                'site_name' => config('app.name'),
            ]);

            $this->generalSetting = $settings;
        }

        $editableFields = self::editablePublicApiFields();
        $updates = [];

        foreach ($editableFields as $field) {
            if (array_key_exists($field, $attributes)) {
                $updates[$field] = $attributes[$field];
            }
        }

        if ($updates !== []) {
            $settings->update($updates);
            $this->generalSetting = $settings->fresh();
        }

        return $this->generalSetting;
    }

    public function isPublicWebsiteSettingsApiEnabled(): bool
    {
        $config = $this->getApiManagementConfig();

        return $config['public_api_enabled'] && $config['public_settings_enabled'];
    }

    /**
     * @return array<string, mixed>
     */
    public function getPublicWebsiteSettings(): array
    {
        if (! $this->isPublicWebsiteSettingsApiEnabled()) {
            return [];
        }

        $payload = [];

        foreach ($this->getApiManagementConfig()['public_settings_fields'] as $field) {
            $payload[$field] = $this->resolveSettingValue($field);
        }

        return $payload;
    }

    public function hasSettingKey(string $key): bool
    {
        $setting = $this->getGlobalSettingsModel();

        if (! $setting instanceof GeneralSetting) {
            return false;
        }

        if (array_key_exists($key, self::PUBLIC_API_FIELD_DEFINITIONS)) {
            return true;
        }

        return array_key_exists($key, $setting->getAttributes());
    }

    public function resolveSettingValue(string $key): mixed
    {
        $setting = $this->getGlobalSettingsModel();

        if (! $setting instanceof GeneralSetting) {
            return null;
        }

        return match ($key) {
            'school_year' => $setting->getSchoolYear(),
            'school_year_string' => $setting->getSchoolYearString(),
            'semester_name' => $setting->getSemester(),
            default => data_get($setting->toArray(), $key),
        };
    }

    /**
     * Get the user setting for the currently authenticated user.
     * Loads lazily since the service might be instantiated before Auth.
     */
    private function getUserSetting(): ?UserSetting
    {
        if ($this->userSettingLoaded) {
            return $this->userSetting;
        }

        $this->userSettingLoaded = true;

        if (Auth::check()) {
            $this->userSetting = UserSetting::query()->firstOrNew([
                'user_id' => Auth::id(),
            ]);
        }

        return $this->userSetting;
    }

    /**
     * @return list<string>
     */
    private function sanitizePublicApiFields(mixed $fields): array
    {
        if (! is_array($fields)) {
            return self::DEFAULT_PUBLIC_API_FIELDS;
        }

        $supportedFields = array_keys(self::PUBLIC_API_FIELD_DEFINITIONS);

        $sanitizedFields = array_values(array_intersect(
            array_map(static fn (mixed $field): string => (string) $field, $fields),
            $supportedFields
        ));

        return array_values(array_unique($sanitizedFields));
    }
}
