<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Filament\Auth\MultiFactor\SecurityAwareAppAuthentication;
use App\Filament\Auth\MultiFactor\SecurityAwareEmailAuthentication;
use App\Models\User;
use Spatie\LaravelPasskeys\Models\Passkey;

it('requires a two factor challenge when configured credentials are active for login', function (): void {
    $user = User::factory()->create([
        'app_authentication_secret' => 'authenticator-secret',
    ]);

    $response = $this->post('/login', [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $response->assertRedirect(route('two-factor.login'));
    $response->assertSessionHas('auth.2fa.id', $user->id);
    $this->assertGuest();
});

it('skips two factor login challenges when the security toggle is disabled', function (): void {
    $user = User::factory()->create([
        'app_authentication_secret' => 'authenticator-secret',
        'has_email_authentication' => true,
        'security_two_factor_enabled' => false,
    ]);

    $response = $this->post('/login', [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $response->assertRedirect('/faculty/dashboard');
    $response->assertSessionMissing('auth.2fa.id');
    $this->assertAuthenticatedAs($user);
});

it('disables login challenges without deleting existing two factor credentials', function (): void {
    $user = User::factory()->create([
        'role' => UserRole::Student,
        'app_authentication_secret' => 'authenticator-secret',
        'app_authentication_recovery_codes' => ['first-code', 'second-code'],
        'has_email_authentication' => true,
        'security_two_factor_enabled' => true,
    ]);

    Passkey::factory()->create([
        'authenticatable_id' => $user->id,
        'name' => 'Laptop Touch ID',
    ]);

    $response = $this
        ->actingAs($user)
        ->post(route('student.profile.two-factor.login-challenges'), [
            'enabled' => false,
        ]);

    $response->assertRedirect();
    $response->assertSessionHas('flash.success', 'Two-factor login challenges disabled.');

    $user->refresh();

    expect($user->security_two_factor_enabled)->toBeFalse()
        ->and($user->app_authentication_secret)->toBe('authenticator-secret')
        ->and($user->app_authentication_recovery_codes)->toBe(['first-code', 'second-code'])
        ->and($user->hasEmailAuthentication())->toBeTrue()
        ->and($user->passkeys()->count())->toBe(1);
});

it('disables Filament app and email multi factor providers when login challenges are paused', function (): void {
    $user = User::factory()->create([
        'app_authentication_secret' => 'authenticator-secret',
        'has_email_authentication' => true,
        'security_two_factor_enabled' => false,
    ]);

    expect(SecurityAwareAppAuthentication::make()->isEnabled($user))->toBeFalse()
        ->and(SecurityAwareEmailAuthentication::make()->isEnabled($user))->toBeFalse();

    $user->forceFill(['security_two_factor_enabled' => true])->save();
    $user->refresh();

    expect(SecurityAwareAppAuthentication::make()->isEnabled($user))->toBeTrue()
        ->and(SecurityAwareEmailAuthentication::make()->isEnabled($user))->toBeTrue();
});
