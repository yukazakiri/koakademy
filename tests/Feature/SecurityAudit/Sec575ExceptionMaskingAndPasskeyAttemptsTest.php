<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\User;
use Laravel\Passkeys\Actions\VerifyPasskey;

it('masks raw passkey exception messages and locks out repeated failures in TwoFactorChallengeController::passkeyVerify', function (): void {
    config(['security.two_factor_max_attempts' => 2]);

    $user = User::factory()->create([
        'role' => UserRole::Student,
    ]);

    $mockVerify = Mockery::mock(VerifyPasskey::class);
    $mockVerify->shouldReceive('__invoke')
        ->twice()
        ->andThrow(new RuntimeException('INTERNAL_WEBAUTHN_SECRET_DETAILS'));
    app()->instance(VerifyPasskey::class, $mockVerify);

    $b64url = static fn (string $raw): string => mb_rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
    $clientDataJson = json_encode([
        'type' => 'webauthn.get',
        'challenge' => 'dGVzdC1jaGFsbGVuZ2U',
        'origin' => 'http://localhost',
    ], JSON_THROW_ON_ERROR);

    $dummyPasskey = json_encode([
        'id' => $b64url('test-cred-id'),
        'rawId' => $b64url('test-cred-id'),
        'type' => 'public-key',
        'response' => [
            'clientDataJSON' => $b64url($clientDataJson),
            'authenticatorData' => $b64url(str_repeat("\x01", 37)),
            'signature' => $b64url('signature-bytes'),
        ],
    ], JSON_THROW_ON_ERROR);

    $dummyOptions = json_encode([
        'challenge' => 'dGVzdC1jaGFsbGVuZ2U',
        'rpId' => 'localhost',
    ], JSON_THROW_ON_ERROR);

    $first = $this->withSession([
        'auth.2fa.id' => $user->id,
        'passkey.two_factor_verification_options' => $dummyOptions,
    ])->postJson(route('two-factor.passkey-verify'), [
        'passkey' => $dummyPasskey,
    ]);

    $first->assertStatus(400)
        ->assertJson(['error' => 'Passkey verification failed.']);
    expect($first->getContent())->not->toContain('INTERNAL_WEBAUTHN_SECRET_DETAILS');

    // Second failure exhausts the 2FA attempt limit and locks out with 429
    $second = $this->withSession([
        'auth.2fa.id' => $user->id,
        'passkey.two_factor_verification_options' => $dummyOptions,
    ])->postJson(route('two-factor.passkey-verify'), [
        'passkey' => $dummyPasskey,
    ]);

    $second->assertStatus(429);
    expect(session('auth.2fa.id'))->toBeNull();
});

it('masks raw exception messages behind app.debug in API controllers and continuing enrollment', function (): void {
    $files = [
        app_path('Http/Controllers/Api/V1/ClassEnrollmentController.php'),
        app_path('Http/Controllers/Api/V1/ClassPostController.php'),
        app_path('Http/Controllers/EnrollmentRegistrationController.php'),
    ];

    foreach ($files as $file) {
        $source = (string) file_get_contents($file);
        expect($source)->not->toContain("'error' => \$e->getMessage()");
    }
});
