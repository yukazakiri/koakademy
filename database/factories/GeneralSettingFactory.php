<?php

declare(strict_types=1);

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\GeneralSetting>
 */
final class GeneralSettingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'site_name' => 'KoAkademy',
            'site_description' => 'KoAkademy Administrative System',
            'theme_color' => '#1e40af',
            'support_email' => 'support@koakademy.edu',
            'support_phone' => '+63 49 834 3251',
            'google_analytics_id' => null,
            'posthog_html_snippet' => null,
            'analytics_enabled' => false,
            'analytics_provider' => null,
            'analytics_script' => null,
            'analytics_settings' => [],
            'seo_title' => 'KoAkademy',
            'seo_keywords' => 'education, college, cabuyao, laguna, philippines',
            'seo_metadata' => [
                'description' => 'KoAkademy Administrative System',
                'keywords' => ['education', 'college', 'admin'],
                'author' => 'KoAkademy IT Department',
            ],
            'email_settings' => [
                'driver' => 'smtp',
                'host' => 'localhost',
                'port' => 587,
                'encryption' => 'tls',
            ],
            'email_from_address' => 'noreply@koakademy.edu',
            'email_from_name' => 'KoAkademy',
            'social_network' => [
                'facebook' => 'https://facebook.com/koakademy.edu',
                'twitter' => null,
                'instagram' => null,
            ],
            'more_configs' => [],
            'school_starting_date' => $this->faker->dateTimeBetween('2024-08-01', '2024-09-15')->format('Y-m-d'),
            'school_ending_date' => $this->faker->dateTimeBetween('2025-05-01', '2025-06-15')->format('Y-m-d'),
            'school_portal_url' => 'https://portal.koakademy.edu',
            'school_portal_enabled' => true,
            'online_enrollment_enabled' => true,
            'school_portal_maintenance' => false,
            'semester' => $this->faker->numberBetween(1, 2),
            'enrollment_courses' => [
                '1',   // BSIT
                '6',   // BSCS
                '10',  // BSIS
                '4',   // BSBA-MM
            ],
            'curriculum_year' => '2024 - 2025',
            'school_portal_logo' => null,
            'school_portal_favicon' => null,
            'school_portal_title' => 'KoAkademy Portal',
            'school_portal_description' => 'Student portal for KoAkademy',
            'enable_clearance_check' => true,
            'enable_signatures' => true,
            'enable_qr_codes' => false,
            'enable_public_transactions' => false,
            'enable_support_page' => true,
            'inventory_module_enabled' => true,
            'library_module_enabled' => true,
            'enable_student_transfer_email_notifications' => true,
            'enable_faculty_transfer_email_notifications' => true,
            'features' => [
                'enrollment' => true,
                'grades' => true,
                'scheduling' => true,
                'payments' => true,
            ],
        ];
    }

    /**
     * Create a factory state for a school in maintenance mode.
     */
    public function maintenance(): static
    {
        return $this->state(fn (array $attributes): array => [
            'school_portal_maintenance' => true,
            'online_enrollment_enabled' => false,
        ]);
    }

    /**
     * Create a factory state for first semester.
     */
    public function firstSemester(): static
    {
        return $this->state(fn (array $attributes): array => [
            'semester' => 1,
        ]);
    }

    /**
     * Create a factory state for second semester.
     */
    public function secondSemester(): static
    {
        return $this->state(fn (array $attributes): array => [
            'semester' => 2,
        ]);
    }

    /**
     * Create a factory state with disabled features.
     */
    public function minimalFeatures(): static
    {
        return $this->state(fn (array $attributes): array => [
            'enable_clearance_check' => false,
            'enable_signatures' => false,
            'enable_qr_codes' => false,
            'enable_public_transactions' => false,
            'enable_support_page' => false,
            'inventory_module_enabled' => false,
            'library_module_enabled' => false,
            'enable_student_transfer_email_notifications' => false,
            'enable_faculty_transfer_email_notifications' => false,
            'features' => [
                'enrollment' => true,
                'grades' => false,
                'scheduling' => false,
                'payments' => false,
            ],
        ]);
    }
}
