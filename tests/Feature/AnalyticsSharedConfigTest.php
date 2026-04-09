<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Http\Middleware\HandleInertiaRequests;
use App\Models\GeneralSetting;
use App\Models\User;
use App\Services\AnalyticsSettingsService;
use Illuminate\Http\Request;

it('shares analytics configuration and renders openpanel markup', function (): void {
    GeneralSetting::factory()->create([
        'analytics_enabled' => true,
        'analytics_provider' => 'openpanel',
        'analytics_settings' => [
            'openpanel_script_url' => 'https://openpanel.dev/op1.js',
            'openpanel_client_id' => 'client-id-123',
            'openpanel_api_url' => 'https://openpanel.koakademy.edu/api',
            'openpanel_track_screen_views' => true,
            'openpanel_track_outgoing_links' => true,
            'openpanel_track_attributes' => true,
            'openpanel_session_replay' => false,
        ],
    ]);

    $user = User::factory()->create([
        'role' => UserRole::Student,
    ]);

    $request = Request::create('/student/dashboard');
    $request->setUserResolver(static fn (): User => $user);

    $shared = app(HandleInertiaRequests::class)->share($request);
    $markup = app(AnalyticsSettingsService::class)->renderHeadMarkup();

    expect($shared['analytics'])->toMatchArray([
        'enabled' => true,
        'provider' => 'openpanel',
        'script' => '',
    ])
        ->and($shared['analytics']['settings'])->toMatchArray([
            'openpanel_script_url' => 'https://openpanel.dev/op1.js',
            'openpanel_client_id' => 'client-id-123',
            'openpanel_api_url' => 'https://openpanel.koakademy.edu/api',
            'openpanel_track_screen_views' => true,
            'openpanel_track_outgoing_links' => true,
            'openpanel_track_attributes' => true,
            'openpanel_session_replay' => false,
        ])
        ->and($markup)->toContain("window.op('init'")
        ->and($markup)->toContain('https://openpanel.dev/op1.js')
        ->and($markup)->toContain('https://openpanel.koakademy.edu/api')
        ->and($markup)->toContain('client-id-123');
});
