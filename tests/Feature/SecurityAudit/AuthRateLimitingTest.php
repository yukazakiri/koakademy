<?php

declare(strict_types=1);

use App\Models\User;
use App\Services\Auth\LoginAttemptService;
use App\Services\Auth\SignupOtpService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia;

/*
|--------------------------------------------------------------------------
| SEC-009 · Authentication endpoints have no rate limiting
|--------------------------------------------------------------------------
|
| The unauthenticated authentication surface is the one place where an
| attacker gets unlimited, cheap, automated attempts. These tests pin the
| limits down so a later change cannot quietly reopen the hole.
|
*/

beforeEach(function (): void {
    Cache::flush();
});

it('throttles the password login endpoint', function (): void {
    for ($attempt = 0; $attempt < 5; $attempt++) {
        $this->post('/login', [
            'email' => 'spray.target@example.com',
            'password' => 'not-the-password',
        ])->assertOk();
    }

    $this->post('/login', [
        'email' => 'spray.target@example.com',
        'password' => 'not-the-password',
    ])->assertTooManyRequests();
});

it('locks an account after repeated failed sign-ins', function (): void {
    $user = User::factory()->create(['email' => 'victim@example.com']);

    for ($attempt = 0; $attempt < 5; $attempt++) {
        $this->post('/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ])->assertOk();
    }

    // The lockout outlives the one minute rate limit window, so the correct
    // password is still refused once the throttle has expired.
    $this->travel(2)->minutes();

    $this->post('/login', [
        'email' => $user->email,
        'password' => 'correct-password',
    ])->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
        ->component('login')
        ->where('errors.email', fn (string $message): bool => str_contains($message, 'Too many failed sign-in attempts'))
    );

    $this->assertGuest();
});

it('clears the lockout counter after a successful sign-in', function (): void {
    $user = User::factory()->create([
        'email' => 'careful@example.com',
        'password' => Hash::make('correct-password'),
    ]);

    for ($attempt = 0; $attempt < 4; $attempt++) {
        $this->post('/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ])->assertOk();
    }

    $this->post('/login', [
        'email' => $user->email,
        'password' => 'correct-password',
    ])->assertRedirect();

    expect(app(LoginAttemptService::class)->attempts($user->email, '127.0.0.1'))->toBe(0);
});

it('never reveals whether an account exists when the password is wrong', function (): void {
    $user = User::factory()->create(['email' => 'known@example.com']);

    $known = $this->post('/login', [
        'email' => $user->email,
        'password' => 'wrong-password',
    ]);

    $unknown = $this->post('/login', [
        'email' => 'unknown@example.com',
        'password' => 'wrong-password',
    ]);

    $known->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
        ->where('errors.email', 'The provided credentials do not match our records.')
    );

    $unknown->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
        ->where('errors.email', 'The provided credentials do not match our records.')
    );
});

it('throttles the two-factor challenge endpoint', function (): void {
    $user = User::factory()->create(['email' => 'mfa@example.com']);

    $this->withSession(['auth.2fa.id' => $user->id]);

    // An empty code is not a guess, so it does not spend an attempt and only
    // the rate limiter can stop the request.
    for ($attempt = 0; $attempt < 10; $attempt++) {
        $this->post('/two-factor-challenge', [])->assertRedirect();
    }

    $this->post('/two-factor-challenge', [])->assertTooManyRequests();
});

it('ends a two-factor challenge after too many wrong codes', function (): void {
    $user = User::factory()->create(['email' => 'mfa.lockout@example.com']);

    $this->withSession(['auth.2fa.id' => $user->id]);

    for ($attempt = 0; $attempt < 4; $attempt++) {
        $this->post('/two-factor-challenge', ['code' => '000000'])->assertRedirect();
    }

    // The fifth wrong code spends the last attempt and ends the challenge.
    $this->post('/two-factor-challenge', ['code' => '000000'])
        ->assertRedirect(route('login'));

    // A new challenge cannot be used to reset the counter.
    $this->withSession(['auth.2fa.id' => $user->id]);

    $this->post('/two-factor-challenge', ['code' => '123456'])
        ->assertRedirect(route('login'));

    $this->assertGuest();
});

it('throttles the two-factor email code endpoint', function (): void {
    $user = User::factory()->create(['email' => 'mfa.email@example.com']);

    $this->withSession(['auth.2fa.id' => $user->id]);

    for ($attempt = 0; $attempt < 3; $attempt++) {
        $this->post('/two-factor-challenge/send-email')->assertRedirect();
    }

    $this->post('/two-factor-challenge/send-email')->assertTooManyRequests();
});

it('throttles the passkey authentication endpoints', function (): void {
    for ($attempt = 0; $attempt < 10; $attempt++) {
        $this->postJson('/passkeys/options', [])->assertOk();
    }

    $this->postJson('/passkeys/options', [])->assertTooManyRequests();
});

it('throttles the passkey verification endpoint', function (): void {
    for ($attempt = 0; $attempt < 10; $attempt++) {
        $this->postJson('/passkeys/login', [])->assertUnprocessable();
    }

    $this->postJson('/passkeys/login', [])->assertTooManyRequests();
});

it('does not reveal whether an account exists through the passkey options endpoint', function (): void {
    $user = User::factory()->create(['email' => 'passkey.owner@example.com']);

    $unknown = $this->postJson('/passkeys/options', ['email' => 'nobody@example.com']);
    $known = $this->postJson('/passkeys/options', ['email' => $user->email]);

    $unknown->assertOk();
    $known->assertOk();

    expect($unknown->json())->toHaveKey('options');
    expect($known->json())->toHaveKey('options');

    // The response must not carry a credential list, because an empty list
    // for a known account would give the same hint as a missing account.
    expect($known->json('options.allowCredentials'))->toBeEmpty();
    expect($unknown->json('options.allowCredentials'))->toBeEmpty();
});

it('throttles the password reset endpoint', function (): void {
    for ($attempt = 0; $attempt < 5; $attempt++) {
        $this->post('/forgot-password', ['email' => 'reset.target@example.com'])->assertRedirect();
    }

    $this->post('/forgot-password', ['email' => 'reset.target@example.com'])->assertTooManyRequests();
});

it('burns a signup verification code after too many wrong guesses', function (): void {
    $service = app(SignupOtpService::class);
    $service->issue('otp.guesser@example.com', 'ABC123');

    for ($attempt = 0; $attempt < 4; $attempt++) {
        expect($service->verify('otp.guesser@example.com', 'WRONG'))->toBeFalse();
    }

    // The fifth wrong guess spends the challenge, so the right code no
    // longer works.
    expect($service->verify('otp.guesser@example.com', 'WRONG'))->toBeFalse();
    expect($service->exhausted('otp.guesser@example.com'))->toBeTrue();
    expect($service->verify('otp.guesser@example.com', 'ABC123'))->toBeFalse();
});

it('rejects a signup that guesses the verification code too often', function (): void {
    $service = app(SignupOtpService::class);
    $service->issue('otp.rejected@example.com', 'ABC123');

    for ($attempt = 0; $attempt < 5; $attempt++) {
        $service->verify('otp.rejected@example.com', 'WRONG');
    }

    $response = $this->post('/signup', [
        'name' => 'Test Student',
        'email' => 'otp.rejected@example.com',
        'password' => 'Password!12345',
        'password_confirmation' => 'Password!12345',
        'user_type' => 'student',
        'student_type' => 'college',
        'lrn' => '1234567890',
        'otp' => 'ABC123',
    ]);

    $response->assertSessionHasErrors('otp');
    expect(session('errors')->first('otp'))->toContain('Too many incorrect verification codes');
});

it('locks the mobile api login after repeated failed sign-ins', function (): void {
    $user = User::factory()->create(['email' => 'mobile@example.com']);

    for ($attempt = 0; $attempt < 5; $attempt++) {
        $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
            'device_name' => 'Test Device',
        ])->assertUnauthorized();
    }

    $this->postJson('/api/v1/auth/login', [
        'email' => $user->email,
        'password' => 'wrong-password',
        'device_name' => 'Test Device',
    ])->assertTooManyRequests();
});
