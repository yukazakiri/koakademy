<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Features\Toggles\StudentDeveloperMode;
use App\Models\School;
use App\Models\User;
use Laravel\Pennant\Feature;

it('expires existing bearer tokens at the configured maximum age even without an expiry date', function (): void {
    $this->travelTo(now()->startOfSecond());
    config(['sanctum.expiration' => 60]);
    $user = User::factory()->create();
    $token = $user->createToken('legacy', ['mobile:read'])->plainTextToken;

    $this->withToken($token)->getJson('/api/user')->assertOk();
    $this->travel(61)->minutes();
    app('auth')->forgetGuards();
    $this->withToken($token)->getJson('/api/user')->assertUnauthorized();
});

it('rejects a bearer token with an earlier explicit expiry', function (): void {
    $this->travelTo(now()->startOfSecond());
    config(['sanctum.expiration' => 60]);
    $user = User::factory()->create();
    $token = $user->createToken('short-lived', ['mobile:read'], now()->addMinutes(5))->plainTextToken;

    $this->travel(6)->minutes();
    $this->withToken($token)->getJson('/api/user')->assertUnauthorized();
});

it('checks the correct mobile ability on API requests', function (array $abilities, string $method, string $url, int $expected): void {
    $school = School::factory()->create();
    $user = User::factory()->create(['school_id' => $school->id]);
    $token = $user->createToken('scoped', $abilities)->plainTextToken;

    $this->withToken($token)->json($method, $url)->assertStatus($expected);
})->with([
    'read can read' => [['mobile:read'], 'GET', '/api/user', 200],
    'write cannot read' => [['mobile:write'], 'GET', '/api/user', 403],
    'read cannot write' => [['mobile:read'], 'POST', '/api/students/verify', 403],
    'write reaches validation' => [['mobile:write'], 'POST', '/api/students/verify', 422],
    'unrelated ability cannot read' => [['mcp:read'], 'GET', '/api/user', 403],
    'wildcard can read' => [['*'], 'GET', '/api/user', 200],
]);

it('keeps first party sessions usable on sanctum routes', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user)->getJson('/api/user')->assertOk();
});

it('supports test callers using Sanctum actingAs with default abilities or explicit scopes', function (): void {
    $school = School::factory()->create();
    $user = User::factory()->create(['school_id' => $school->id]);
    Laravel\Sanctum\Sanctum::actingAs($user);
    $this->getJson('/api/user')->assertOk();

    Laravel\Sanctum\Sanctum::actingAs($user, ['mobile:read']);
    $this->getJson('/api/user')->assertOk();
    $this->postJson('/api/students/verify')->assertForbidden();
});

it('enforces abilities on sanctum API write routes in routes/api.php', function (): void {
    $school = School::factory()->create();
    $user = User::factory()->create(['school_id' => $school->id]);
    $token = $user->createToken('read only', ['mobile:read'])->plainTextToken;

    $this->withToken($token)->postJson('/api/students/verify')->assertForbidden();
});

it('issues bounded scoped mobile login tokens', function (): void {
    $this->travelTo(now()->startOfSecond());
    config(['sanctum.expiration' => 60]);
    $user = User::factory()->create(['role' => UserRole::Student, 'security_two_factor_enabled' => false]);

    $response = $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'password', 'device_name' => 'Phone'])->assertOk();
    $token = $user->tokens()->firstOrFail();

    expect($token->abilities)->toBe(['mobile:read', 'mobile:write'])
        ->and($token->expires_at->equalTo(now()->addHour()))->toBeTrue()
        ->and($response->json('data.expires_at'))->not->toBeNull();
});

it('bounds developer API key expiry while respecting an earlier requested date', function (?int $days, int $expectedDays): void {
    $this->travelTo(now()->startOfSecond());
    config(['sanctum.expiration' => 43200, 'onboarding.experimental_feature_keys' => ['onboarding-student-developer-mode'], 'onboarding.experimental_features_roles' => ['onboarding-student-developer-mode' => ['student']]]);
    $user = User::factory()->create(['role' => UserRole::Student]);
    Feature::for($user)->activate(StudentDeveloperMode::class);
    $payload = ['name' => 'Test key', 'abilities' => ['read']];
    if ($days !== null) {
        $payload['expires_at'] = now()->addDays($days)->toDateTimeString();
    }

    $this->actingAs($user)->postJson(route('student.api-keys.store'), $payload)->assertCreated();
    expect($user->tokens()->firstOrFail()->expires_at->equalTo(now()->addDays($expectedDays)))->toBeTrue();
})->with([[null, 30], [5, 5], [60, 30]]);
