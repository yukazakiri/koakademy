<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Route;
use Spatie\LaravelPasskeys\Models\Passkey;

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

test('passkey options endpoint returns 404 for non-existent user when email provided', function () {
    $response = $this->postJson('/passkeys/options', [
        'email' => 'nonexistent@example.com',
    ]);

    $response->assertNotFound()
        ->assertJson(['error' => 'User not found.']);
});

test('passkey options endpoint returns options for valid user with passkey', function () {
    $user = User::factory()->create();

    // Create a passkey for the user
    Passkey::factory()->create([
        'authenticatable_id' => $user->id,
        'name' => 'Test Passkey',
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
    // Test that routes exist and don't require a specific domain
    $routes = collect(Route::getRoutes())
        ->filter(fn ($route) => in_array($route->uri(), ['passkeys/options', 'passkeys/login']))
        ->map(fn ($route) => [
            'uri' => $route->uri(),
            'domain' => $route->getDomain(),
        ]);

    expect($routes)->toHaveCount(2);
    $routes->each(fn ($route) => expect($route['domain'])->toBeNull());
});
