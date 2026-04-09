<?php

declare(strict_types=1);

use App\Models\GeneralSetting;
use Illuminate\Support\Facades\Cache;

it('returns only the selected public website settings fields', function (): void {
    $settings = GeneralSetting::query()->firstOrCreate([
        'site_name' => 'KoAkademy',
    ]);

    $settings->update([
        'site_name' => 'Public Site',
        'theme_color' => '#123456',
        'support_email' => 'support@example.com',
        'email_from_address' => 'internal@example.com',
        'more_configs' => [
            'api_management' => [
                'public_api_enabled' => true,
                'public_settings_enabled' => true,
                'public_settings_fields' => ['site_name', 'theme_color', 'support_email'],
            ],
        ],
    ]);

    Cache::forget('general_settings_id');

    $response = $this->getJson('/api/v1/public/settings');

    $response->assertOk()
        ->assertJson([
            'message' => 'Public website settings retrieved successfully',
            'data' => [
                'site_name' => 'Public Site',
                'theme_color' => '#123456',
                'support_email' => 'support@example.com',
            ],
        ])
        ->assertJsonMissingPath('data.email_from_address');
});

it('returns not found when the public website settings api is disabled', function (): void {
    $settings = GeneralSetting::query()->firstOrCreate([
        'site_name' => 'KoAkademy',
    ]);

    $settings->update([
        'more_configs' => [
            'api_management' => [
                'public_api_enabled' => false,
                'public_settings_enabled' => false,
                'public_settings_fields' => ['site_name'],
            ],
        ],
    ]);

    Cache::forget('general_settings_id');

    $response = $this->getJson('/api/v1/public/settings');

    $response->assertNotFound()
        ->assertJson([
            'message' => 'Public website settings API is disabled',
            'data' => null,
        ]);
});
