<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;
use Laravel\Telescope\Telescope;

it('redacts sensitive request parameters and authorization headers in Telescope', function (): void {
    expect(Telescope::$hiddenRequestParameters)
        ->toContain('_token', 'password', 'password_confirmation', 'current_password', 'token', 'refresh_token', 'secret', 'client_secret', 'api_key', 'code', 'recovery_code', 'otp')
        ->and(Telescope::$hiddenRequestHeaders)
        ->toContain('authorization', 'proxy-authorization', 'x-api-key', 'cookie', 'x-csrf-token', 'x-xsrf-token');
});

it('denies non-developer users from subscribing to test-notifications in production', function (): void {
    $student = User::factory()->create(['role' => UserRole::Student]);
    $developer = User::factory()->create(['role' => UserRole::Developer]);

    $channels = Broadcast::getChannels();
    $callback = $channels['test-notifications'] ?? null;

    expect($callback)->toBeCallable();

    $originalEnv = app()->environment();
    app()->detectEnvironment(static fn (): string => 'production');

    try {
        expect($callback($student))->toBeFalse()
            ->and($callback($developer))->toBeTrue();
    } finally {
        app()->detectEnvironment(static fn (): string => $originalEnv);
    }
});
