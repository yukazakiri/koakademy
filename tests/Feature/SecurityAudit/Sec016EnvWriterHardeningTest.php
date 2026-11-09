<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\GeneralSetting;
use App\Models\User;
use App\Support\EnvironmentFileWriter;
use Dotenv\Dotenv;
use Illuminate\Support\Facades\File;

/**
 * SEC-016 · .env writer newline injection, backreference expansion, and key allowlist hardening.
 */
beforeEach(function (): void {
    GeneralSetting::factory()->create([
        'semester' => 1,
        'school_starting_date' => '2026-08-01',
        'school_ending_date' => '2027-05-31',
    ]);
});

it('rejects newline and control characters in environment values', function (string $maliciousValue): void {
    $writer = new EnvironmentFileWriter;

    expect(fn () => $writer->validateValue('FACEBOOK_CLIENT_ID', $maliciousValue))
        ->toThrow(InvalidArgumentException::class);
})->with([
    "evil\nINJECTED_KEY=1",
    "evil\rINJECTED_KEY=1",
    "evil\r\nINJECTED_KEY=1",
    "evil\0truncated",
]);

it('rejects keys that are not on the explicit allowlist', function (string $disallowedKey): void {
    $writer = new EnvironmentFileWriter;

    expect(fn () => $writer->validateKey($disallowedKey))
        ->toThrow(InvalidArgumentException::class);
})->with([
    'APP_KEY',
    'DB_PASSWORD',
    'DB_HOST',
    'APP_ENV',
    'AWS_SECRET_ACCESS_KEY',
    'UNKNOWN_CUSTOM_KEY',
]);

it('permits all expected allowlisted keys', function (): void {
    $writer = new EnvironmentFileWriter;

    foreach (EnvironmentFileWriter::ALLOWED_KEYS as $allowedKey) {
        $writer->validateKey($allowedKey);
    }

    expect(count(EnvironmentFileWriter::ALLOWED_KEYS))->toBe(20);
});

it('does not expand regex backreferences during replacement', function (): void {
    $writer = new EnvironmentFileWriter;

    $initialContent = "FACEBOOK_CLIENT_ID=original_client_id\nOTHER_KEY=keep_me\n";

    // Values containing $0, $1, \1 which preg_replace would expand as backreferences
    $backrefValue = 'new_id_$0_$1_\1_backref';
    $values = [
        'FACEBOOK_CLIENT_ID' => $backrefValue,
    ];

    $updatedContent = $writer->updateContent($initialContent, $values);

    // The literal $0, $1, \1 must NOT expand into the matched text (original_client_id)
    expect($updatedContent)->not->toContain('original_client_id')
        ->and($updatedContent)->toContain('OTHER_KEY=keep_me');

    $tempFile = tempnam(sys_get_temp_dir(), 'env_backref_test_');
    try {
        File::put($tempFile, $updatedContent);
        $parsed = Dotenv::createArrayBacked(dirname($tempFile), basename($tempFile))->load();
        expect($parsed['FACEBOOK_CLIENT_ID'])->toBe($backrefValue);
    } finally {
        if (File::exists($tempFile)) {
            File::delete($tempFile);
        }
    }
});

it('quotes values and escapes special characters for valid dotenv parsing', function (): void {
    $writer = new EnvironmentFileWriter;

    $initialContent = "PUSHER_APP_KEY=old\n";
    $testVal = 'complex"secret\'value with spaces and #comments and $var \ path';

    $updatedContent = $writer->updateContent($initialContent, [
        'PUSHER_APP_KEY' => $testVal,
    ]);

    $tempFile = tempnam(sys_get_temp_dir(), 'env_test_');
    try {
        File::put($tempFile, $updatedContent);

        $parsed = Dotenv::createArrayBacked(dirname($tempFile), basename($tempFile))->load();

        expect($parsed['PUSHER_APP_KEY'])->toBe($testVal);
    } finally {
        if (File::exists($tempFile)) {
            File::delete($tempFile);
        }
    }
});

it('appends new keys if they do not exist in the file', function (): void {
    $writer = new EnvironmentFileWriter;

    $initialContent = "APP_NAME=\"KoAkademy\"\n";
    $updatedContent = $writer->updateContent($initialContent, [
        'BROADCAST_CONNECTION' => 'pusher',
        'PUSHER_APP_ID' => '123456',
    ]);

    expect($updatedContent)->toBe("APP_NAME=\"KoAkademy\"\nBROADCAST_CONNECTION=\"pusher\"\nPUSHER_APP_ID=\"123456\"\n");
});

it('writes updates to disk under exclusive lock when a custom file path is provided', function (): void {
    $writer = new EnvironmentFileWriter;

    $tempFile = tempnam(sys_get_temp_dir(), 'env_write_test_');
    try {
        File::put($tempFile, "FACEBOOK_CLIENT_ID=\"old_val\"\n");

        $writer->update([
            'FACEBOOK_CLIENT_ID' => 'updated_val',
        ], $tempFile);

        $contents = File::get($tempFile);
        expect($contents)->toBe("FACEBOOK_CLIENT_ID=\"updated_val\"\n");
    } finally {
        if (File::exists($tempFile)) {
            File::delete($tempFile);
        }
    }
});

it('rejects newline injection in socialite settings update endpoint', function (): void {
    $superAdmin = User::factory()->create(['role' => UserRole::SuperAdmin]);

    $response = $this->actingAs($superAdmin)
        ->from(portalUrlForAdministrators('/administrators/system-management/socialite'))
        ->put(portalUrlForAdministrators('/administrators/system-management/socialite'), [
            'facebook_client_id' => "injected_id\nMALICIOUS=true",
            'facebook_client_secret' => 'safe_secret',
            'facebook_enabled' => false,
            'facebook_redirect_uri' => 'http://localhost/callback',
        ]);

    $response->assertSessionHasErrors(['facebook_client_id']);
});

it('rejects newline injection in notification channels update endpoint', function (): void {
    $superAdmin = User::factory()->create(['role' => UserRole::SuperAdmin]);

    $response = $this->actingAs($superAdmin)
        ->from(portalUrlForAdministrators('/administrators/system-management/notifications'))
        ->put(portalUrlForAdministrators('/administrators/system-management/notifications'), [
            'enabled_channels' => ['broadcast'],
            'pusher' => [
                'app_id' => "111222\nINJECTED_KEY=malicious",
                'key' => 'valid-key',
                'secret' => 'valid-secret',
                'cluster' => 'ap1',
            ],
            'sms' => [],
        ]);

    $response->assertSessionHasErrors(['pusher.app_id']);
});

it('aborts database save if inherited socialite settings contain invalid newline values', function (): void {
    $settings = GeneralSetting::first();
    $settings->update([
        'social_network' => [
            'facebook_client_id' => "pre_existing\nEVIL=1",
            'facebook_client_secret' => 'some_secret',
        ],
    ]);

    $superAdmin = User::factory()->create(['role' => UserRole::SuperAdmin]);

    $response = $this->actingAs($superAdmin)
        ->from(portalUrlForAdministrators('/administrators/system-management/socialite'))
        ->put(portalUrlForAdministrators('/administrators/system-management/socialite'), [
            'google_client_id' => 'valid_google_id',
            'google_client_secret' => 'valid_google_secret',
        ]);

    $response->assertSessionHasErrors(['socialite']);

    $settings->refresh();
    expect($settings->social_network['google_client_id'] ?? null)->toBeNull();
});

it('accepts valid socialite settings, saves to database, and delegates to environment writer', function (): void {
    $spy = Mockery::spy(new EnvironmentFileWriter);
    $this->app->instance(EnvironmentFileWriter::class, $spy);

    $superAdmin = User::factory()->create(['role' => UserRole::SuperAdmin]);

    $response = $this->actingAs($superAdmin)
        ->from(portalUrlForAdministrators('/administrators/system-management/socialite'))
        ->put(portalUrlForAdministrators('/administrators/system-management/socialite'), [
            'facebook_client_id' => 'valid_fb_id_123',
            'facebook_client_secret' => 'valid_fb_secret_456',
            'facebook_enabled' => true,
            'facebook_redirect_uri' => 'http://localhost/auth/facebook/callback',
        ]);

    $response->assertRedirect();
    $response->assertSessionHasNoErrors();

    $settings = GeneralSetting::first();
    expect($settings->social_network['facebook_client_id'])->toBe('valid_fb_id_123')
        ->and($settings->social_network['facebook_client_secret'])->toBe('valid_fb_secret_456');

    $spy->shouldHaveReceived('validate');
    $spy->shouldHaveReceived('update')->with(Mockery::on(function (array $updates): bool {
        return ($updates['FACEBOOK_CLIENT_ID'] ?? null) === 'valid_fb_id_123'
            && ($updates['FACEBOOK_CLIENT_SECRET'] ?? null) === 'valid_fb_secret_456'
            && ($updates['FACEBOOK_REDIRECT_URI'] ?? null) === 'http://localhost/auth/facebook/callback';
    }));
});

it('accepts valid notification settings, saves to database, and delegates to environment writer', function (): void {
    $spy = Mockery::spy(new EnvironmentFileWriter);
    $this->app->instance(EnvironmentFileWriter::class, $spy);

    $superAdmin = User::factory()->create(['role' => UserRole::SuperAdmin]);

    $response = $this->actingAs($superAdmin)
        ->from(portalUrlForAdministrators('/administrators/system-management/notifications'))
        ->put(portalUrlForAdministrators('/administrators/system-management/notifications'), [
            'enabled_channels' => ['broadcast'],
            'pusher' => [
                'app_id' => '111222',
                'key' => 'valid-key',
                'secret' => 'valid-secret',
                'cluster' => 'ap1',
            ],
            'sms' => [],
        ]);

    $response->assertRedirect();
    $response->assertSessionHasNoErrors();

    $settings = GeneralSetting::first();
    expect($settings->more_configs['notification_channels']['pusher']['app_id'])->toBe('111222');

    $spy->shouldHaveReceived('validate');
    $spy->shouldHaveReceived('update')->with(Mockery::on(function (array $updates): bool {
        return ($updates['BROADCAST_CONNECTION'] ?? null) === 'pusher'
            && ($updates['PUSHER_APP_ID'] ?? null) === '111222'
            && ($updates['PUSHER_APP_KEY'] ?? null) === 'valid-key';
    }));
});
