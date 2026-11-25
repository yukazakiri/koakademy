<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\Faculty;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia;
use PragmaRX\Google2FA\Google2FA;

it('authenticates mixed-case emails and does not flash an error message as status on failed login', function (): void {
    $user = User::factory()->create([
        'email' => 'Mixed.Case@School.edu',
        'password' => Hash::make('SecretPass123!'),
        'role' => UserRole::Student,
    ]);

    $failed = $this->post('/login', [
        'email' => 'mixed.case@school.edu',
        'password' => 'wrong-password',
    ]);

    $failed->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->component('login', false)
            ->where('errors.email', 'The provided credentials do not match our records.')
            ->where('status', null)
        );

    $this->post('/login', [
        'email' => 'mixed.case@school.edu',
        'password' => 'SecretPass123!',
    ])->assertRedirect('/student/dashboard');

    $this->assertAuthenticatedAs($user);
});

it('prevents non-faculty users and forged email claims on faculty verification', function (): void {
    $student = User::factory()->create([
        'role' => UserRole::Student,
        'email' => 'student@school.edu',
    ]);

    $facultyRecord = Faculty::factory()->create([
        'email' => 'prof@school.edu',
        'faculty_id_number' => 'FAC-2026-900',
    ]);

    // Students cannot access /faculty-verify
    $this->actingAs($student)
        ->post('/faculty-verify', [
            'email' => 'prof@school.edu',
            'faculty_id_number' => 'FAC-2026-900',
        ])
        ->assertForbidden();

    // Another faculty member cannot claim a colleague's ID by submitting their email
    $otherFacultyUser = User::factory()->create([
        'role' => UserRole::Instructor,
        'email' => 'other.prof@school.edu',
        'faculty_id_number' => null,
    ]);

    $this->actingAs($otherFacultyUser)
        ->post('/faculty-verify', [
            'email' => 'prof@school.edu',
            'faculty_id_number' => 'FAC-2026-900',
        ])
        ->assertSessionHasErrors('faculty_id_number');

    expect($otherFacultyUser->fresh()->faculty_id_number)->toBeNull();

    // Legitimate faculty member verifies against their own account email
    $realFacultyUser = User::factory()->create([
        'role' => UserRole::Instructor,
        'email' => 'PROF@school.edu',
        'faculty_id_number' => null,
    ]);

    $this->actingAs($realFacultyUser)
        ->post('/faculty-verify', [
            'faculty_id_number' => 'FAC-2026-900',
        ])
        ->assertRedirect('/dashboard');

    expect($realFacultyUser->fresh()->faculty_id_number)->toBe('FAC-2026-900')
        ->and((string) $realFacultyUser->fresh()->record_id)->toBe((string) $facultyRecord->id);
});

it('stores TOTP setup secret in session, hashes recovery codes for cross-surface use, and hides them on profile load', function (): void {
    $user = User::factory()->create([
        'role' => UserRole::Student,
        'password' => Hash::make('ValidPass123!'),
    ]);

    // Attempting to confirm with an arbitrary client-chosen secret without server session fails
    $google2fa = new Google2FA;
    $attackerSecret = $google2fa->generateSecretKey();
    $this->actingAs($user)
        ->post(route('student.profile.two-factor.confirm'), [
            'secret' => $attackerSecret,
            'code' => $google2fa->getCurrentOtp($attackerSecret),
        ])
        ->assertSessionHasErrors('code');

    expect($user->fresh()->app_authentication_secret)->toBeNull();

    // Start setup server-side
    $enableResponse = $this->actingAs($user)
        ->postJson(route('student.profile.two-factor.enable'))
        ->assertOk();

    $secret = (string) $enableResponse->json('secret');
    $validCode = $google2fa->getCurrentOtp($secret);

    // Confirm using space-formatted code without needing to pass secret back
    $confirmResponse = $this->actingAs($user)
        ->post(route('student.profile.two-factor.confirm'), [
            'code' => mb_substr($validCode, 0, 3).' '.mb_substr($validCode, 3, 3),
        ])
        ->assertRedirect();

    $plainCodes = session('flash.recovery_codes');
    expect($plainCodes)->toBeArray()->toHaveCount(8);

    $user->refresh();
    expect($user->app_authentication_secret)->toBe($secret)
        ->and($user->app_authentication_recovery_codes)->toBeArray()->toHaveCount(8)
        ->and($user->app_authentication_recovery_codes[0])->not->toBe($plainCodes[0])
        ->and(Hash::check($plainCodes[0], $user->app_authentication_recovery_codes[0]))->toBeTrue();

    // Subsequent normal profile visit must NOT expose plaintext recovery codes
    $this->actingAs($user)
        ->get(route('student.profile'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('user.recovery_codes_count', 8)
            ->missing('user.recovery_codes')
        );

    // Recovery code generated from web UI works on Mobile API 2FA verification
    $challengeId = str_repeat('b', 64);
    Cache::put('mobile-api-auth-challenge:'.$challengeId, [
        'user_id' => $user->id,
        'device_name' => 'Test Phone',
    ], now()->addMinutes(5));

    $this->postJson('/api/v1/auth/challenge/verify', [
        'challenge_id' => $challengeId,
        'recovery_code' => $plainCodes[0],
    ])->assertOk()->assertJsonStructure(['data' => ['token']]);
});

it('requires password confirmation to disable email auth or regenerate recovery codes', function (): void {
    $user = User::factory()->create([
        'role' => UserRole::Student,
        'password' => Hash::make('ValidPass123!'),
        'has_email_authentication' => true,
        'app_authentication_secret' => 'JBSWY3DPEHPK3PXP',
    ]);

    // Disabling email auth without password fails
    $this->actingAs($user)
        ->post(route('student.profile.email-auth.toggle'), ['enabled' => false])
        ->assertSessionHasErrors('password');

    expect($user->fresh()->hasEmailAuthentication())->toBeTrue();

    // Disabling email auth with valid password succeeds
    $this->actingAs($user)
        ->post(route('student.profile.email-auth.toggle'), [
            'enabled' => false,
            'password' => 'ValidPass123!',
        ])
        ->assertSessionHasNoErrors();

    expect($user->fresh()->hasEmailAuthentication())->toBeFalse();

    // Regenerating recovery codes without password fails
    $this->actingAs($user)
        ->post(route('student.profile.two-factor.recovery-codes'), [])
        ->assertSessionHasErrors('password');

    // Regenerating recovery codes with valid password succeeds
    $this->actingAs($user)
        ->post(route('student.profile.two-factor.recovery-codes'), [
            'password' => 'ValidPass123!',
        ])
        ->assertSessionHasNoErrors();
});

it('enforces account-wide 2FA lockout across multiple mobile API challenges', function (): void {
    Cache::flush();
    config(['security.two_factor_max_attempts' => 2]);

    $user = User::factory()->create([
        'role' => UserRole::Student,
        'has_email_authentication' => true,
        'security_two_factor_enabled' => true,
    ]);

    $challenge1 = str_repeat('1', 64);
    $challenge2 = str_repeat('2', 64);

    foreach ([$challenge1, $challenge2] as $cid) {
        Cache::put('mobile-api-auth-challenge:'.$cid, [
            'user_id' => $user->id,
            'device_name' => 'Phone',
        ], now()->addMinutes(5));
        Cache::put('mobile-api-auth-challenge:'.$cid.':code', Hash::make('654321'), now()->addMinutes(5));
    }

    // Wrong guess on challenge 1
    $this->postJson('/api/v1/auth/challenge/verify', [
        'challenge_id' => $challenge1,
        'code' => '000001',
    ])->assertStatus(422);

    // Second wrong guess on a brand new challenge_id still triggers account-wide lockout
    $this->postJson('/api/v1/auth/challenge/verify', [
        'challenge_id' => $challenge2,
        'code' => '000002',
    ])->assertStatus(429)
        ->assertJsonPath('code', 'CHALLENGE_LOCKED');
});

it('returns JSON status codes from two-factor email code endpoint for AJAX clients', function (): void {
    $user = User::factory()->create([
        'role' => UserRole::Student,
        'has_email_authentication' => false,
    ]);

    $this->withSession(['auth.2fa.id' => $user->id])
        ->postJson(route('two-factor.send-email'))
        ->assertStatus(422)
        ->assertJsonPath('error', 'Email authentication is not enabled for this account.');
});
