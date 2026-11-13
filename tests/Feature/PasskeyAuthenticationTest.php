<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Route;

test('passkey options endpoint returns options without email (discoverable credentials)', function () {
    $response = $this->postJson('/passkeys/options', []);

    $response->assertOk()
        ->assertJsonStructure([
            'options' => [
                'challenge',
            ],
        ]);

    // Without email, allowCredentials should be empty for discoverable credentials
    $options = $response->json('options');
    expect($options['allowCredentials'])->toBeEmpty();
});

test('passkey options endpoint does not reveal whether an account exists', function () {
    $response = $this->postJson('/passkeys/options', [
        'email' => 'nonexistent@example.com',
    ]);

    // Answering 404 for unknown addresses turned this endpoint into an
    // account existence oracle, so unknown and known addresses get the same
    // answer.
    $response->assertOk()
        ->assertJsonStructure([
            'options' => [
                'challenge',
            ],
        ]);

    expect($response->json('options.allowCredentials'))->toBeEmpty();
});

test('passkey options endpoint uses configured app url for production origin', function () {
    config(['app.url' => 'https://portal.koakademy.edu.ph']);

    $response = $this->withServerVariables([
        'HTTP_HOST' => 'portal.koakademy.edu.ph',
        'HTTPS' => 'off',
    ])->postJson('/passkeys/options', []);

    $response->assertOk()
        ->assertJsonPath('options.rpId', 'portal.koakademy.edu.ph');

    expect(config('passkeys.allowed_origins'))->toContain('https://portal.koakademy.edu.ph');
});

test('passkey options endpoint returns options for valid user with passkey', function () {
    $user = User::factory()->create();

    $credentialId = mb_rtrim(strtr(base64_encode('test-credential-'.$user->id), '+/', '-_'), '=');

    $user->passkeys()->create([
        'name' => 'Test Passkey',
        'credential_id' => $credentialId,
        'credential' => ['publicKeyCredentialId' => $credentialId],
    ]);

    $response = $this->postJson('/passkeys/options', [
        'email' => $user->email,
    ]);

    $response->assertOk()
        ->assertJsonStructure([
            'options' => [
                'challenge',
            ],
        ]);
});

test('passkey login endpoint requires passkey parameter', function () {
    $response = $this->postJson('/passkeys/login', []);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['passkey']);
});

test('passkey login endpoint returns error when no options in session', function () {
    $response = $this->postJson('/passkeys/login', [
        'passkey' => json_encode(['id' => 'test', 'type' => 'public-key']),
    ]);

    $response->assertBadRequest()
        ->assertJson(['error' => 'Authentication options not found or expired.']);
});

test('passkey routes are accessible without domain restriction', function () {
    // Test that the app's custom passkey routes exist and don't require a specific domain.
    $routes = collect(['passkeys.login.options', 'passkeys.login.verify'])
        ->map(fn (string $name) => Route::getRoutes()->getByName($name));

    expect($routes)->not->toContain(null);
    $routes->each(fn ($route) => expect($route->getDomain())->toBeNull());
});
