<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\User;
use App\Services\ApplicationIntegrationSettingsService;
use App\Services\CalendarFeedService;

beforeEach(function (): void {
    $this->admin = User::factory()->create([
        'role' => UserRole::SuperAdmin,
        'email' => 'admin.integrations@example.com',
    ]);
});

it('renders system management integrations page with config payload', function (): void {
    $response = $this
        ->actingAs($this->admin)
        ->get(portalUrlForAdministrators('/administrators/system-management/integrations'));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->component('administrators/system-management/integrations')
        ->has('application_integrations')
        ->has('application_integrations.google_calendar')
        ->has('application_integrations.calendar_feed')
        ->has('application_integrations.microsoft')
        ->has('application_integrations.cloud_storage')
        ->has('application_integrations.conferencing_and_lms')
    );
});

it('allows authorized administrators to update runtime integration settings', function (): void {
    $payload = [
        'google_calendar' => [
            'enabled' => false,
            'client_id' => 'custom-google-id-12345.apps.googleusercontent.com',
            'client_secret' => 'custom-google-secret',
            'redirect_uri' => 'https://koakademy.test/integrations/google/callback',
            'auto_sync' => false,
        ],
        'calendar_feed' => [
            'enabled' => true,
            'students_enabled' => false,
            'faculty_enabled' => true,
            'title_prefix' => 'Custom Academy Timetable',
            'rrule_weeks' => 16,
        ],
        'microsoft' => [
            'enabled' => true,
            'client_id' => 'ms-client-uuid-9876',
            'client_secret' => 'ms-secret-value',
            'tenant_id' => 'custom-tenant-uuid',
            'redirect_uri' => 'https://koakademy.test/integrations/microsoft/callback',
        ],
        'cloud_storage' => [
            'google_drive_enabled' => true,
            'google_drive_api_key' => 'AIzaSyFakeKey123',
            'google_drive_client_id' => 'google-drive-client-id',
            'onedrive_enabled' => true,
            'onedrive_client_id' => 'onedrive-app-id',
        ],
        'conferencing_and_lms' => [
            'zoom_enabled' => true,
            'zoom_account_id' => 'zoom-acc-123',
            'zoom_client_id' => 'zoom-cli-456',
            'zoom_client_secret' => 'zoom-sec-789',
            'canvas_enabled' => true,
            'canvas_base_url' => 'https://canvas.example.edu',
            'canvas_client_id' => 'canvas-cli-101112',
        ],
    ];

    $response = $this
        ->actingAs($this->admin)
        ->put(portalUrlForAdministrators('/administrators/system-management/integrations'), $payload);

    $response->assertRedirect();

    $service = app(ApplicationIntegrationSettingsService::class);
    $config = $service->get();

    expect($config['google_calendar']['enabled'])->toBeFalse()
        ->and($config['google_calendar']['client_id'])->toBe('custom-google-id-12345.apps.googleusercontent.com')
        ->and($config['calendar_feed']['students_enabled'])->toBeFalse()
        ->and($config['calendar_feed']['faculty_enabled'])->toBeTrue()
        ->and($config['calendar_feed']['title_prefix'])->toBe('Custom Academy Timetable')
        ->and($config['calendar_feed']['rrule_weeks'])->toBe(16)
        ->and($config['microsoft']['enabled'])->toBeTrue()
        ->and($config['cloud_storage']['google_drive_enabled'])->toBeTrue();
});

it('blocks calendar feed at runtime when disabled in system settings', function (): void {
    $service = app(ApplicationIntegrationSettingsService::class);
    $service->update([
        'calendar_feed' => [
            'enabled' => false,
            'students_enabled' => true,
            'faculty_enabled' => true,
        ],
    ]);

    $facultyUser = User::factory()->create([
        'role' => UserRole::Instructor,
        'faculty_id_number' => 'FAC-SETTING-01',
    ]);
    $token = app(CalendarFeedService::class)->generateToken($facultyUser);

    $response = $this->get("/calendar/feed/{$facultyUser->id}/{$token}.ics");
    $response->assertForbidden();
});

it('respects role-specific feed access configured in system settings', function (): void {
    $service = app(ApplicationIntegrationSettingsService::class);
    $service->update([
        'calendar_feed' => [
            'enabled' => true,
            'students_enabled' => false,
            'faculty_enabled' => true,
        ],
    ]);

    $studentUser = User::factory()->create([
        'role' => UserRole::Student,
    ]);
    $studentToken = app(CalendarFeedService::class)->generateToken($studentUser);

    // Students blocked because students_enabled is false
    $this->get("/calendar/feed/{$studentUser->id}/{$studentToken}.ics")->assertForbidden();

    $facultyUser = User::factory()->create([
        'role' => UserRole::Instructor,
        'faculty_id_number' => 'FAC-SETTING-02',
    ]);
    $facultyToken = app(CalendarFeedService::class)->generateToken($facultyUser);

    // Faculty allowed because faculty_enabled is true
    $this->get("/calendar/feed/{$facultyUser->id}/{$facultyToken}.ics")->assertSuccessful();
});

it('forbids administrators without integrations permission', function (): void {
    $regularAdmin = User::factory()->create([
        'role' => UserRole::Admin,
    ]);

    $response = $this
        ->actingAs($regularAdmin)
        ->put(portalUrlForAdministrators('/administrators/system-management/integrations'), [
            'google_calendar' => ['enabled' => false],
        ]);

    $response->assertForbidden();
});
