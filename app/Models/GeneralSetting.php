<?php

declare(strict_types=1);

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * Class GeneralSetting
 *
 * @method static Builder<static>|GeneralSetting newModelQuery()
 * @method static Builder<static>|GeneralSetting newQuery()
 * @method static Builder<static>|GeneralSetting query()
 *
 * @mixin \Eloquent
 */
final class GeneralSetting extends Model
{
    use HasFactory;

    protected $table = 'general_settings';

    protected $fillable = [
        'site_name',
        'site_description',
        'theme_color',
        'currency',
        'support_email',
        'support_phone',
        'google_analytics_id',
        'posthog_html_snippet',
        'analytics_enabled',
        'analytics_provider',
        'analytics_script',
        'analytics_settings',
        'seo_title',
        'seo_keywords',
        'seo_metadata',
        'email_settings',
        'email_from_address',
        'email_from_name',
        'social_network',
        'more_configs',
        'school_starting_date',
        'school_ending_date',
        'school_portal_url',
        'school_portal_enabled',
        'online_enrollment_enabled',
        'school_portal_maintenance',
        'semester',
        'enrollment_courses',
        'school_portal_logo',
        'school_portal_favicon',
        'school_portal_title',
        'school_portal_description',
        'enable_clearance_check',
        'enable_signatures',
        'enable_qr_codes',
        'enable_public_transactions',
        'enable_support_page',
        'features',
        'curriculum_year',
        'inventory_module_enabled',
        'library_module_enabled',
        'enable_student_transfer_email_notifications',
        'enable_faculty_transfer_email_notifications',
        'is_setup',
    ];

    public static function clearCache(): void
    {
        Cache::forget('general_settings');
        Cache::forget('general_settings_id');
        Cache::forget('api_general_settings');
    }

    public function getSchoolYear(): string
    {
        return $this->getSchoolYearStarting().
            '-'.
            $this->getSchoolYearEnding();
    }

    public function getSchoolYearStarting(): string
    {
        return $this->school_starting_date?->format('Y') ?? 'N/A';
    }

    public function getSchoolYearEnding(): string
    {
        return $this->school_ending_date?->format('Y') ?? 'N/A';
    }

    public function getSchoolYearString(): string
    {
        return $this->getSchoolYearStarting().
            ' - '.
            $this->getSchoolYearEnding();
    }

    public function getSemester(): string
    {
        return match ($this->semester) {
            1 => '1st Semester',
            2 => '2nd Semester',
            default => '1st Semester',
        };
    }

    protected static function boot(): void
    {
        parent::boot();

        self::saved(function ($settings): void {
            self::clearCache();
        });
    }

    protected function casts(): array
    {
        return [
            'seo_metadata' => 'array',
            'email_settings' => 'array',
            'social_network' => 'array',
            'analytics_enabled' => 'boolean',
            'analytics_settings' => 'array',
            'more_configs' => 'array',
            'school_starting_date' => 'date',
            'school_ending_date' => 'date',
            'school_portal_enabled' => 'boolean',
            'online_enrollment_enabled' => 'boolean',
            'school_portal_maintenance' => 'boolean',
            'semester' => 'integer',
            'enrollment_courses' => 'array',
            'enable_signatures' => 'boolean',
            'enable_public_transactions' => 'boolean',
            'enable_qr_codes' => 'boolean',
            'enable_support_page' => 'boolean',
            'features' => 'array',
            'curriculum_year' => 'string',
            'inventory_module_enabled' => 'boolean',
            'library_module_enabled' => 'boolean',
            'enable_student_transfer_email_notifications' => 'boolean',
            'enable_faculty_transfer_email_notifications' => 'boolean',
            'is_setup' => 'boolean',
        ];
    }
}
