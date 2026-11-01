<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Arr;

final class GeneralSettingResource extends JsonResource
{
    /**
     * Redact OAuth client secrets and credentials from the social network payload.
     *
     * @param  array<string, mixed>|null  $socialNetwork
     * @return array<string, mixed>|null
     */
    public static function sanitizeSocialNetwork(?array $socialNetwork): ?array
    {
        if ($socialNetwork === null) {
            return null;
        }

        return self::recursivelySanitizeSecrets($socialNetwork);
    }

    /**
     * Redact API keys, webhook secrets, and external credentials from more_configs.
     *
     * @param  array<string, mixed>|null  $moreConfigs
     * @return array<string, mixed>|null
     */
    public static function sanitizeMoreConfigs(?array $moreConfigs): ?array
    {
        if ($moreConfigs === null) {
            return null;
        }

        return self::recursivelySanitizeSecrets($moreConfigs);
    }

    /**
     * Recursively redact keys matching sensitive secret or credential patterns.
     *
     * @param  array<string|int, mixed>  $data
     * @return array<string|int, mixed>
     */
    public static function recursivelySanitizeSecrets(array $data): array
    {
        $sanitized = [];

        foreach ($data as $key => $value) {
            if (is_string($key) && self::isSecretKey($key)) {
                continue;
            }

            if (is_array($value)) {
                $sanitized[$key] = self::recursivelySanitizeSecrets($value);
            } else {
                $sanitized[$key] = $value;
            }
        }

        return $sanitized;
    }

    /**
     * Check if an attribute or array key holds sensitive credentials.
     */
    public static function isSecretKey(string $key): bool
    {
        $normalized = mb_strtolower($key);

        if (str_contains($normalized, 'secret') || str_contains($normalized, 'password')) {
            return true;
        }

        if ($normalized === 'api_key' || str_ends_with($normalized, '_api_key')) {
            return true;
        }

        return in_array($normalized, [
            'token',
            'auth_token',
            'access_token',
            'refresh_token',
            'private_key',
            'sequenzy_api_key',
        ], true);
    }

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'site_name' => $this->site_name,
            'site_description' => $this->site_description,
            'theme_color' => $this->theme_color,
            'support_email' => $this->support_email,
            'support_phone' => $this->support_phone,
            'google_analytics_id' => $this->google_analytics_id,
            'posthog_html_snippet' => $this->posthog_html_snippet,
            'analytics_enabled' => $this->analytics_enabled,
            'seo_title' => $this->seo_title,
            'seo_keywords' => $this->seo_keywords,
            'seo_metadata' => $this->seo_metadata,
            'email_settings' => Arr::except(self::recursivelySanitizeSecrets($this->email_settings ?? []), ['password', 'sequenzy_api_key']),
            'email_from_address' => $this->email_from_address,
            'email_from_name' => $this->email_from_name,
            'social_network' => self::sanitizeSocialNetwork($this->social_network),
            'more_configs' => self::sanitizeMoreConfigs($this->more_configs),
            'school_starting_date' => $this->school_starting_date,
            'school_ending_date' => $this->school_ending_date,
            'school_portal_url' => $this->school_portal_url,
            'school_portal_enabled' => $this->school_portal_enabled,
            'online_enrollment_enabled' => $this->online_enrollment_enabled,
            'school_portal_maintenance' => $this->school_portal_maintenance,
            'semester' => $this->semester,
            'enrollment_courses' => $this->enrollment_courses,
            'school_portal_logo' => $this->school_portal_logo,
            'school_portal_favicon' => $this->school_portal_favicon,
            'school_portal_title' => $this->school_portal_title,
            'school_portal_description' => $this->school_portal_description,
            'enable_clearance_check' => $this->enable_clearance_check,
            'enable_signatures' => $this->enable_signatures,
            'enable_qr_codes' => $this->enable_qr_codes,
            'enable_public_transactions' => $this->enable_public_transactions,
            'enable_support_page' => $this->enable_support_page,
            'features' => $this->features,
            'curriculum_year' => $this->curriculum_year,
            'inventory_module_enabled' => $this->inventory_module_enabled,
            'library_module_enabled' => $this->library_module_enabled,
            'enable_student_transfer_email_notifications' => $this->enable_student_transfer_email_notifications,
            'enable_faculty_transfer_email_notifications' => $this->enable_faculty_transfer_email_notifications,

            // Computed properties
            'school_year' => $this->when($this->school_starting_date && $this->school_ending_date, $this->getSchoolYear()),
            'school_year_string' => $this->when($this->school_starting_date && $this->school_ending_date, $this->getSchoolYearString()),
            'semester_name' => $this->when($this->semester, $this->getSemester()),
        ];
    }
}
