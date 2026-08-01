<?php

declare(strict_types=1);

use App\Models\User;
use Laravel\Sanctum\Sanctum;

it('issues a sanctum token on successful login', function (): void {
    $user = User::factory()->create([
        'password' => 'correct-password',
    ]);

    $response = $this->postJson(route('api.v1.auth.login'), [
        'email' => $user->email,
        'password' => 'correct-password',
        'device_name' => 'Pixel 9',
    ]);

    $response->assertCreated()
        ->assertJsonStructure([
            'token',
            'user' => ['id', 'name', 'email', 'role'],
        ])
        ->assertJsonPath('user.email', $user->email);

    expect($user->tokens()->where('name', 'Pixel 9')->exists())->toBeTrue();
});

it('rejects login with an invalid password', function (): void {
    $user = User::factory()->create([
        'password' => 'correct-password',
    ]);

    $response = $this->postJson(route('api.v1.auth.login'), [
        'email' => $user->email,
        'password' => 'wrong-password',
        'device_name' => 'Pixel 9',
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['email']);

    expect($user->tokens()->count())->toBe(0);
});

it('rejects login for an unknown email', function (): void {
    $response = $this->postJson(route('api.v1.auth.login'), [
        'email' => 'nobody@example.test',
        'password' => 'whatever',
        'device_name' => 'Pixel 9',
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['email']);
});

it('requires a device name when logging in', function (): void {
    $this->postJson(route('api.v1.auth.login'), [
        'email' => 'someone@example.test',
        'password' => 'whatever',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['device_name']);
});

it('returns the authenticated user on the me endpoint', function (): void {
    $user = User::factory()->create();

    Sanctum::actingAs($user);

    $this->getJson(route('api.v1.auth.me'))
        ->assertOk()
        ->assertJsonPath('data.id', $user->id)
        ->assertJsonPath('data.email', $user->email);
});

it('revokes the current token on logout', function (): void {
    $user = User::factory()->create();
    $token = $user->createToken('mobile')->plainTextToken;

    $this->withToken($token)->postJson(route('api.v1.auth.logout'))
        ->assertOk()
        ->assertJson(['message' => 'Logged out.']);

    expect($user->tokens()->count())->toBe(0);
});

it('lists the tokens owned by the user', function (): void {
    $user = User::factory()->create();
    $user->createToken('phone');

    Sanctum::actingAs($user);

    $this->getJson(route('api.v1.auth.tokens.index'))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'phone');
});

it('creates an API key with abilities scoped to read by default', function (): void {
    $user = User::factory()->create();

    Sanctum::actingAs($user);

    $response = $this->postJson(route('api.v1.auth.tokens.store'), [
        'name' => 'Partner web app',
    ]);

    $response->assertCreated()
        ->assertJsonStructure(['token', 'token_name']);

    $token = $user->tokens()->where('name', 'Partner web app')->first();

    expect($token)->not->toBeNull()
        ->and($token->abilities)->toBe(['read']);
});

it('deletes an API key owned by the user', function (): void {
    $user = User::factory()->create();
    $accessToken = $user->createToken('removable')->accessToken;

    Sanctum::actingAs($user);

    $this->deleteJson(route('api.v1.auth.tokens.destroy', ['tokenId' => $accessToken->id]))
        ->assertOk()
        ->assertJson(['message' => 'API key deleted successfully.']);

    expect($user->tokens()->where('id', $accessToken->id)->exists())->toBeFalse();
});

it('cannot delete a token owned by another user', function (): void {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $otherToken = $other->createToken('theirs')->accessToken;

    Sanctum::actingAs($user);

    $this->deleteJson(route('api.v1.auth.tokens.destroy', ['tokenId' => $otherToken->id]))
        ->assertNotFound()
        ->assertJsonPath('code', 'NOT_FOUND');

    expect($other->tokens()->where('id', $otherToken->id)->exists())->toBeTrue();
});

it('rejects unauthenticated token management calls', function (): void {
    $this->getJson(route('api.v1.auth.tokens.index'))->assertUnauthorized();
    $this->postJson(route('api.v1.auth.tokens.store'), ['name' => 'x'])->assertUnauthorized();
    $this->postJson(route('api.v1.auth.logout'))->assertUnauthorized();
    $this->getJson(route('api.v1.auth.me'))->assertUnauthorized();
});
