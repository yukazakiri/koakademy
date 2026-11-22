<?php

declare(strict_types=1);

use App\Models\Account;
use App\Models\ConnectedAccount;
use App\Models\GeneralSetting;
use App\Models\User;
use Illuminate\Support\Facades\DB;

it('encrypts ConnectedAccount OAuth tokens at rest and hides them from serialization', function (): void {
    $user = User::factory()->create();

    $connected = ConnectedAccount::create([
        'user_id' => $user->id,
        'provider' => 'google',
        'provider_id' => 'google-enc-1',
        'name' => $user->name,
        'email' => $user->email,
        'token' => 'ya29.super-secret-access-token',
        'secret' => 'oauth1-secret-value',
        'refresh_token' => '1//super-secret-refresh-token',
    ]);

    $raw = DB::table('connected_accounts')->where('id', $connected->id)->first();
    expect($raw->token)->not->toContain('ya29.super-secret-access-token')
        ->and($raw->secret)->not->toContain('oauth1-secret-value')
        ->and($raw->refresh_token)->not->toContain('1//super-secret-refresh-token');

    $reloaded = ConnectedAccount::findOrFail($connected->id);
    expect($reloaded->token)->toBe('ya29.super-secret-access-token')
        ->and($reloaded->secret)->toBe('oauth1-secret-value')
        ->and($reloaded->refresh_token)->toBe('1//super-secret-refresh-token');

    $serialized = $reloaded->toArray();
    expect($serialized)->not->toHaveKeys(['token', 'secret', 'refresh_token']);
});

it('encrypts GeneralSetting email_settings and social_network at rest while reading legacy plaintext rows', function (): void {
    $setting = GeneralSetting::factory()->create([
        'email_settings' => [
            'host' => 'smtp.example.com',
            'password' => 'smtp-super-secret-pass',
        ],
        'social_network' => [
            'google_client_id' => 'client-123',
            'google_client_secret' => 'google-secret-xyz',
        ],
    ]);

    $raw = DB::table('general_settings')->where('id', $setting->id)->first();
    expect((string) $raw->email_settings)->not->toContain('smtp-super-secret-pass')
        ->and((string) $raw->social_network)->not->toContain('google-secret-xyz');

    $fresh = $setting->fresh();
    expect($fresh->email_settings['password'])->toBe('smtp-super-secret-pass')
        ->and($fresh->social_network['google_client_secret'])->toBe('google-secret-xyz');

    // Legacy plaintext JSON remains readable during rolling upgrades
    DB::table('general_settings')->where('id', $setting->id)->update([
        'social_network' => json_encode(['google_client_secret' => 'legacy-secret'], JSON_THROW_ON_ERROR),
    ]);
    expect($setting->fresh()->social_network['google_client_secret'])->toBe('legacy-secret');
});

it('encrypts Account two_factor_secret and two_factor_recovery_codes at rest', function (): void {
    $account = new Account;
    $account->forceFill([
        'name' => 'Portal User',
        'username' => 'portal_enc_user',
        'email' => 'portal.enc@example.com',
        'password' => 'Password123!@#',
        'role' => 'student',
        'is_active' => true,
        'two_factor_secret' => 'TOTP-SECRET-123456',
        'two_factor_recovery_codes' => '["CODE-1","CODE-2"]',
    ])->save();

    $raw = DB::table('accounts')->where('id', $account->id)->first();
    expect($raw->two_factor_secret)->not->toContain('TOTP-SECRET-123456')
        ->and($raw->two_factor_recovery_codes)->not->toContain('CODE-1');

    $fresh = Account::findOrFail($account->id);
    expect($fresh->two_factor_secret)->toBe('TOTP-SECRET-123456')
        ->and($fresh->two_factor_recovery_codes)->toBe('["CODE-1","CODE-2"]');
});
