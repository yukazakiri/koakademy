<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\ConnectedAccount;
use App\Models\GeneralSetting;
use App\Models\User;
use Laravel\Socialite\Contracts\Provider;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteTwoUser;

beforeEach(function (): void {
    GeneralSetting::factory()->create([
        'school_starting_date' => '2026-06-01',
        'school_ending_date' => '2027-03-31',
        'semester' => 1,
        'social_network' => [
            'google_enabled' => true,
            'google_client_id' => 'test-client-id',
            'google_client_secret' => 'test-client-secret',
            'github_enabled' => true,
            'github_client_id' => 'gh-client-id',
            'github_client_secret' => 'gh-client-secret',
        ],
    ]);
});

it('requires two-factor challenge after OAuth login when user has 2FA enabled', function (): void {
    $user = User::factory()->create([
        'role' => UserRole::Admin,
        'email' => 'mfa.oauth@example.com',
        'app_authentication_secret' => 'JBSWY3DPEHPK3PXP',
        'security_two_factor_enabled' => true,
    ]);

    ConnectedAccount::create([
        'user_id' => $user->id,
        'provider' => 'google',
        'provider_id' => 'google-123',
        'name' => $user->name,
        'email' => $user->email,
        'token' => 'oauth-token',
    ]);

    $socialUser = new SocialiteTwoUser;
    $socialUser->map([
        'id' => 'google-123',
        'name' => $user->name,
        'email' => $user->email,
    ])->setRaw(['email_verified' => true])->setToken('oauth-token');

    $driver = Mockery::mock(Provider::class);
    $driver->shouldReceive('with')->andReturnSelf();
    $driver->shouldReceive('user')->andReturn($socialUser);
    Socialite::shouldReceive('driver')->with('google')->andReturn($driver);

    $this->get('/auth/google/callback')
        ->assertRedirect(route('two-factor.login'))
        ->assertSessionHas('auth.2fa.id', $user->id);

    $this->assertGuest();
});

it('rejects auto-linking an existing user when OAuth provider does not assert email_verified', function (): void {
    $user = User::factory()->create([
        'role' => UserRole::Admin,
        'email' => 'victim@example.com',
    ]);

    $socialUser = new SocialiteTwoUser;
    $socialUser->map([
        'id' => 'gh-attacker-999',
        'name' => 'Attacker',
        'email' => 'victim@example.com',
    ])->setRaw(['email_verified' => false])->setToken('gh-token');

    $driver = Mockery::mock(Provider::class);
    $driver->shouldReceive('user')->andReturn($socialUser);
    Socialite::shouldReceive('driver')->with('github')->andReturn($driver);

    $this->get('/auth/github/callback')
        ->assertRedirect('/login')
        ->assertSessionHasErrors('email');

    $this->assertGuest();
    expect(ConnectedAccount::query()->where('provider', 'github')->where('user_id', $user->id)->exists())->toBeFalse();
});
