<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Http\Controllers\Api\V1\GeneralSettingController;
use App\Models\GeneralSetting;
use App\Models\User;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[CoversClass(GeneralSettingController::class)]
final class GeneralSettingApiTest extends TestCase
{
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        Sanctum::actingAs($this->user);
    }

    #[Test]
    public function it_can_list_general_settings(): void
    {
        $settings = GeneralSetting::factory()->create();

        $response = $this->getJson('/api/settings');

        $response->assertOk()
            ->assertJsonStructure([
                '*' => [
                    'id',
                    'site_name',
                    'site_description',
                    'theme_color',
                    'support_email',
                    'support_phone',
                    'google_analytics_id',
                    'posthog_html_snippet',
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
                    'school_year',
                    'school_year_string',
                    'semester_name',
                ],
            ]);
    }

    #[Test]
    public function it_returns_404_when_no_settings_exist(): void
    {
        $response = $this->getJson('/api/settings');

        $response->assertNotFound()
            ->assertJson([
                'message' => 'No general settings found',
                'data' => null,
            ]);
    }

    #[Test]
    public function it_can_create_general_settings(): void
    {
        $data = GeneralSetting::factory()->raw();

        $response = $this->postJson('/api/settings', $data);

        $response->assertCreated()
            ->assertJson([
                'message' => 'General settings created successfully',
            ]);

        $this->assertDatabaseHas('general_settings', [
            'site_name' => $data['site_name'],
            'support_email' => $data['support_email'],
        ]);
    }

    #[Test]
    public function it_validates_when_creating_general_settings(): void
    {
        $response = $this->postJson('/api/settings', [
            'email_from_address' => 'invalid-email',
            'school_ending_date' => '2023-01-01',
            'school_starting_date' => '2023-12-31',
            'semester' => 3,
            'enrollment_courses' => [999999],
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors([
                'email_from_address',
                'school_ending_date',
                'semester',
                'enrollment_courses.0',
            ]);
    }

    #[Test]
    public function it_can_show_general_settings(): void
    {
        $settings = GeneralSetting::factory()->create();

        $response = $this->getJson("/api/settings/{$settings->id}");

        $response->assertOk()
            ->assertJson([
                'message' => 'General settings retrieved successfully',
                'data' => [
                    'id' => $settings->id,
                    'site_name' => $settings->site_name,
                ],
            ]);
    }

    #[Test]
    public function it_returns_404_when_showing_nonexistent_settings(): void
    {
        $response = $this->getJson('/api/settings/999999');

        $response->assertNotFound()
            ->assertJson([
                'message' => 'General settings not found',
                'data' => null,
            ]);
    }

    #[Test]
    public function it_can_update_general_settings(): void
    {
        $settings = GeneralSetting::factory()->create();

        $updateData = [
            'site_name' => 'Updated Site Name',
            'support_email' => 'updated@example.com',
            'semester' => 2,
        ];

        $response = $this->putJson("/api/settings/{$settings->id}", $updateData);

        $response->assertOk()
            ->assertJson([
                'message' => 'General settings updated successfully',
            ]);

        $this->assertDatabaseHas('general_settings', [
            'id' => $settings->id,
            'site_name' => 'Updated Site Name',
            'support_email' => 'updated@example.com',
            'semester' => 2,
        ]);
    }

    #[Test]
    public function it_can_delete_general_settings(): void
    {
        $settings = GeneralSetting::factory()->create();

        $response = $this->deleteJson("/api/settings/{$settings->id}");

        $response->assertOk()
            ->assertJson([
                'message' => 'General settings deleted successfully',
            ]);

        $this->assertSoftDeleted('general_settings', [
            'id' => $settings->id,
        ]);
    }

    #[Test]
    public function it_can_restore_deleted_general_settings(): void
    {
        $settings = GeneralSetting::factory()->create();
        $settings->delete();

        $response = $this->postJson("/api/settings/{$settings->id}/restore");

        $response->assertOk()
            ->assertJson([
                'message' => 'General settings restored successfully',
            ]);

        $this->assertNotSoftDeleted('general_settings', [
            'id' => $settings->id,
        ]);
    }

    #[Test]
    public function it_can_force_delete_general_settings(): void
    {
        $settings = GeneralSetting::factory()->create();
        $settings->delete();

        $response = $this->deleteJson("/api/settings/{$settings->id}/force");

        $response->assertOk()
            ->assertJson([
                'message' => 'General settings permanently deleted',
            ]);

        $this->assertDatabaseMissing('general_settings', [
            'id' => $settings->id,
        ]);
    }

    #[Test]
    public function it_can_get_current_settings(): void
    {
        $settings = GeneralSetting::factory()->create();

        $response = $this->getJson('/api/settings/current');

        $response->assertOk()
            ->assertJson([
                'message' => 'Current general settings retrieved successfully',
                'data' => [
                    'id' => $settings->id,
                    'site_name' => $settings->site_name,
                ],
            ]);
    }

    #[Test]
    public function it_returns_404_when_no_current_settings_exist(): void
    {
        $response = $this->getJson('/api/settings/current');

        $response->assertNotFound()
            ->assertJson([
                'message' => 'No general settings found',
                'data' => null,
            ]);
    }

    #[Test]
    public function it_can_get_specific_setting_by_key(): void
    {
        $settings = GeneralSetting::factory()->create([
            'site_name' => 'Test Site',
            'semester' => 1,
            'school_starting_date' => '2023-06-01',
            'school_ending_date' => '2024-05-31',
        ]);

        $response = $this->getJson('/api/settings/key/site_name');

        $response->assertOk()
            ->assertJson([
                'message' => 'Setting retrieved successfully',
                'data' => [
                    'key' => 'site_name',
                    'value' => 'Test Site',
                ],
            ]);
    }

    #[Test]
    public function it_can_get_computed_settings_by_key(): void
    {
        $settings = GeneralSetting::factory()->create([
            'semester' => 1,
            'school_starting_date' => '2023-06-01',
            'school_ending_date' => '2024-05-31',
        ]);

        $response = $this->getJson('/api/settings/key/school_year_string');

        $response->assertOk()
            ->assertJson([
                'message' => 'School year string retrieved successfully',
                'data' => [
                    'key' => 'school_year_string',
                    'value' => '2023 - 2024',
                ],
            ]);
    }

    #[Test]
    public function it_returns_404_when_getting_nonexistent_setting_key(): void
    {
        $settings = GeneralSetting::factory()->create();

        $response = $this->getJson('/api/settings/key/nonexistent_key');

        $response->assertNotFound()
            ->assertJson([
                'message' => "Setting 'nonexistent_key' not found",
                'data' => null,
            ]);
    }

    #[Test]
    public function it_can_get_service_settings(): void
    {
        $settings = GeneralSetting::factory()->create([
            'semester' => 1,
            'school_starting_date' => '2023-06-01',
            'school_ending_date' => '2024-05-31',
            'school_portal_url' => 'https://portal.example.com',
        ]);

        $response = $this->getJson('/api/settings/service');

        $response->assertOk()
            ->assertJsonStructure([
                'message',
                'data' => [
                    'current_semester',
                    'current_school_year_start',
                    'current_school_year_string',
                    'available_semesters',
                    'available_school_years',
                    'student_portal_url',
                    'global_school_starting_date',
                    'global_school_ending_date',
                ],
            ])
            ->assertJson([
                'message' => 'Service settings retrieved successfully',
                'data' => [
                    'current_semester' => 1,
                    'student_portal_url' => 'https://portal.example.com',
                ],
            ]);
    }

    #[Test]
    public function it_requires_authentication(): void
    {
        Sanctum::actingAs(null);

        $response = $this->getJson('/api/settings');

        $response->assertUnauthorized();
    }

    #[Test]
    public function it_can_include_trashed_records(): void
    {
        $settings = GeneralSetting::factory()->create();
        $settings->delete();

        $response = $this->getJson('/api/settings?with_trashed=true');

        $response->assertOk()
            ->assertJsonCount(1);
    }
}
