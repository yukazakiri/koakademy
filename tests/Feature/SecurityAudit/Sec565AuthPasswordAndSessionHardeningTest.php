<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password as PasswordBroker;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password as PasswordRule;

it('enforces baseline Password::defaults() complexity and stronger production rules', function (): void {
    expect(Validator::make(['password' => 'onlyletters'], ['password' => [PasswordRule::defaults()]])->fails())->toBeTrue()
        ->and(Validator::make(['password' => '12345678'], ['password' => [PasswordRule::defaults()]])->fails())->toBeTrue()
        ->and(Validator::make(['password' => 'Short1'], ['password' => [PasswordRule::defaults()]])->fails())->toBeTrue()
        ->and(Validator::make(['password' => 'ValidPass123'], ['password' => [PasswordRule::defaults()]])->passes())->toBeTrue();

    $originalEnv = app()->environment();
    app()->detectEnvironment(static fn (): string => 'production');

    try {
        expect(Validator::make(['password' => 'password1234'], ['password' => [PasswordRule::defaults()]])->fails())->toBeTrue()
            ->and(Validator::make(['password' => 'StrongPass#2026'], ['password' => [PasswordRule::defaults()]])->passes())->toBeTrue();
    } finally {
        app()->detectEnvironment(static fn (): string => $originalEnv);
    }
});

it('does not flash plaintext passwords on failed password change and revokes tokens and bumps session version on success', function (): void {
    $user = User::factory()->create([
        'role' => UserRole::Instructor,
        'faculty_id_number' => 'FAC-SEC-565',
        'password' => Hash::make('OldPass123!'),
        'session_version' => 1,
    ]);
    $user->createToken('mobile-device', ['mobile:read']);

    // Failed current password should NOT flash password or password_confirmation into session old input
    $failed = $this->actingAs($user)
        ->withSession(['user_session_version' => 1])
        ->from('/faculty/profile')
        ->put(portalUrlForAdministrators('/faculty/profile/password'), [
            'current_password' => 'WrongPass999',
            'password' => 'NewSecret123!',
            'password_confirmation' => 'NewSecret123!',
        ]);

    $failed->assertSessionHasErrors(['current_password']);
    expect(session()->getOldInput('password'))->toBeNull()
        ->and(session()->getOldInput('password_confirmation'))->toBeNull();

    // Valid password change bumps session_version and revokes all Sanctum tokens
    $this->actingAs($user)
        ->withSession(['user_session_version' => 1])
        ->from('/faculty/profile')
        ->put(portalUrlForAdministrators('/faculty/profile/password'), [
            'current_password' => 'OldPass123!',
            'password' => 'NewSecret123!',
            'password_confirmation' => 'NewSecret123!',
        ])
        ->assertSessionHasNoErrors();

    $user->refresh();
    expect($user->session_version)->toBe(2)
        ->and($user->tokens()->count())->toBe(0)
        ->and(session('user_session_version'))->toBe(2);
});

it('revokes Sanctum tokens and bumps session_version on web password reset', function (): void {
    $user = User::factory()->create([
        'role' => UserRole::Student,
        'session_version' => 3,
    ]);
    $user->createToken('stolen-device', ['mobile:read', 'mobile:write']);

    $token = PasswordBroker::broker('users')->createToken($user);

    $this->post('/reset-password', [
        'token' => $token,
        'email' => $user->email,
        'password' => 'ResetPass123!',
        'password_confirmation' => 'ResetPass123!',
    ])->assertRedirect('/login');

    $user->refresh();
    expect($user->session_version)->toBe(4)
        ->and($user->tokens()->count())->toBe(0);
});

it('locks and destroys mobile API MFA challenges after repeated invalid verification attempts', function (): void {
    config(['security.two_factor_max_attempts' => 3]);

    $user = User::factory()->create([
        'role' => UserRole::Student,
        'has_email_authentication' => true,
        'security_two_factor_enabled' => true,
    ]);

    $challengeId = str_repeat('a', 64);
    Cache::put('mobile-api-auth-challenge:'.$challengeId, [
        'user_id' => $user->id,
        'device_name' => 'Test Phone',
    ], now()->addMinutes(5));
    Cache::put('mobile-api-auth-challenge:'.$challengeId.':code', Hash::make('123456'), now()->addMinutes(5));

    $this->postJson('/api/v1/auth/challenge/verify', [
        'challenge_id' => $challengeId,
        'code' => '000001',
    ])->assertStatus(422);

    $this->postJson('/api/v1/auth/challenge/verify', [
        'challenge_id' => $challengeId,
        'code' => '000002',
    ])->assertStatus(422);

    // 3rd attempt exhausts the challenge and returns 429
    $this->postJson('/api/v1/auth/challenge/verify', [
        'challenge_id' => $challengeId,
        'code' => '000003',
    ])->assertStatus(429)
        ->assertJsonPath('code', 'CHALLENGE_LOCKED');

    // Even the real code is now rejected because the challenge was destroyed
    $this->postJson('/api/v1/auth/challenge/verify', [
        'challenge_id' => $challengeId,
        'code' => '123456',
    ])->assertNotFound();
});
