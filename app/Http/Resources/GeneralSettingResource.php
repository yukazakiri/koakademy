<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class GeneralSettingResource extends JsonResource
{
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
            'analytics_provider' => $this->analytics_provider,
            'analytics_script' => $this->analytics_script,
            'analytics_settings' => $this->analytics_settings,
            'seo_title' => $this->seo_title,
            'seo_keywords' => $this->seo_keywords,
            'seo_metadata' => $this->seo_metadata,
            'email_settings' => $this->email_settings,
            'email_from_address' => $this->email_from_address,
            'email_from_name' => $this->email_from_name,
            'social_network' => $this->social_network,
            'more_configs' => $this->more_configs,
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
