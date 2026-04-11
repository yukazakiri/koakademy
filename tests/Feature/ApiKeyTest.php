<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Features\Onboarding\FacultyDeveloperMode;
use App\Features\Onboarding\StudentDeveloperMode;
use App\Models\User;
use Laravel\Pennant\Feature;

beforeEach(function (): void {
    config(['activitylog.enabled' => false]);
});

describe('API Keys - Student Portal', function (): void {
    beforeEach(function (): void {
        $this->user = User::factory()->create([
            'role' => UserRole::Student,
            'email' => 'student@example.com',
        ]);
    });

    it('cannot access api keys without developer mode enabled', function (): void {
        $response = $this
            ->actingAs($this->user)
            ->getJson(route('student.api-keys.index'));

        $response->assertStatus(403);
    });

    it('cannot create api keys without developer mode enabled', function (): void {
        $response = $this
            ->actingAs($this->user)
            ->postJson(route('student.api-keys.store'), [
                'name' => 'Test Key',
            ]);

        $response->assertStatus(403);
    });

    it('can access api keys with developer mode enabled', function (): void {
        config(['onboarding.experimental_feature_keys' => ['onboarding-student-developer-mode']]);
        config(['onboarding.experimental_features_roles' => ['onboarding-student-developer-mode' => ['student']]]);

        Feature::for($this->user)->activate(StudentDeveloperMode::class);

        $response = $this
            ->actingAs($this->user)
            ->getJson(route('student.api-keys.index'));

        $response->assertSuccessful();
        $response->assertJsonStructure(['tokens']);
    });

    it('can create api key with developer mode enabled', function (): void {
        config(['onboarding.experimental_feature_keys' => ['onboarding-student-developer-mode']]);
        config(['onboarding.experimental_features_roles' => ['onboarding-student-developer-mode' => ['student']]]);

        Feature::for($this->user)->activate(StudentDeveloperMode::class);

        $response = $this
            ->actingAs($this->user)
            ->postJson(route('student.api-keys.store'), [
                'name' => 'My Test Key',
                'abilities' => ['read'],
            ]);

        $response->assertStatus(201);
        $response->assertJsonStructure(['message', 'token', 'token_name']);
        $response->assertJson(['token_name' => 'My Test Key']);

        expect($this->user->tokens)->toHaveCount(1);
        expect($this->user->tokens->first()->name)->toBe('My Test Key');
    });

    it('can delete api key with developer mode enabled', function (): void {
        config(['onboarding.experimental_feature_keys' => ['onboarding-student-developer-mode']]);
        config(['onboarding.experimental_features_roles' => ['onboarding-student-developer-mode' => ['student']]]);

        Feature::for($this->user)->activate(StudentDeveloperMode::class);

        $token = $this->user->createToken('Test Token');

        $response = $this
            ->actingAs($this->user)
            ->deleteJson(route('student.api-keys.destroy', ['id' => $token->accessToken->id]));

        $response->assertSuccessful();
        expect($this->user->tokens)->toHaveCount(0);
    });

    it('validates api key creation request', function (): void {
        config(['onboarding.experimental_feature_keys' => ['onboarding-student-developer-mode']]);
        config(['onboarding.experimental_features_roles' => ['onboarding-student-developer-mode' => ['student']]]);

        Feature::for($this->user)->activate(StudentDeveloperMode::class);

        $response = $this
            ->actingAs($this->user)
            ->postJson(route('student.api-keys.store'), [
                'name' => '',
            ]);

        $response->assertStatus(422);
    });

    it('returns developer mode status', function (): void {
        $response = $this
            ->actingAs($this->user)
            ->getJson(route('student.api-keys.developer-mode'));

        $response->assertSuccessful();
        $response->assertJson(['enabled' => false]);

        config(['onboarding.experimental_feature_keys' => ['onboarding-student-developer-mode']]);
        config(['onboarding.experimental_features_roles' => ['onboarding-student-developer-mode' => ['student']]]);

        Feature::for($this->user)->activate(StudentDeveloperMode::class);

        $response = $this
            ->actingAs($this->user)
            ->getJson(route('student.api-keys.developer-mode'));

        $response->assertSuccessful();
        $response->assertJson(['enabled' => true]);
    });
});

describe('API Keys - Faculty Portal', function (): void {
    beforeEach(function (): void {
        $this->user = User::factory()->create([
            'role' => UserRole::Instructor,
            'email' => 'faculty@example.com',
            'faculty_id_number' => 'F123456',
        ]);
    });

    it('cannot access api keys without developer mode enabled', function (): void {
        $response = $this
            ->actingAs($this->user)
            ->getJson(portalUrlForAdministrators('/faculty/profile/api-keys'));

        $response->assertStatus(403);
    });

    it('can access api keys with developer mode enabled', function (): void {
        config(['onboarding.experimental_feature_keys' => ['onboarding-faculty-developer-mode']]);
        config(['onboarding.experimental_features_roles' => ['onboarding-faculty-developer-mode' => ['faculty']]]);

        Feature::for($this->user)->activate(FacultyDeveloperMode::class);

        $response = $this
            ->actingAs($this->user)
            ->getJson(portalUrlForAdministrators('/faculty/profile/api-keys'));

        $response->assertSuccessful();
        $response->assertJsonStructure(['tokens']);
    });

    it('can create api key with developer mode enabled', function (): void {
        config(['onboarding.experimental_feature_keys' => ['onboarding-faculty-developer-mode']]);
        config(['onboarding.experimental_features_roles' => ['onboarding-faculty-developer-mode' => ['faculty']]]);

        Feature::for($this->user)->activate(FacultyDeveloperMode::class);

        $response = $this
            ->actingAs($this->user)
            ->postJson(portalUrlForAdministrators('/faculty/profile/api-keys'), [
                'name' => 'Faculty Test Key',
                'abilities' => ['read', 'write'],
            ]);

        $response->assertStatus(201);
        $response->assertJsonStructure(['message', 'token', 'token_name']);
        $response->assertJson(['token_name' => 'Faculty Test Key']);

        expect($this->user->tokens)->toHaveCount(1);
        expect($this->user->tokens->first()->name)->toBe('Faculty Test Key');
    });
});

describe('API Keys - Non faculty/student users', function (): void {
    beforeEach(function (): void {
        $this->user = User::factory()->create([
            'role' => UserRole::Admin,
            'email' => 'admin@example.com',
        ]);
    });

    it('cannot access api keys endpoints', function (): void {
        $response = $this
            ->actingAs($this->user)
            ->get(route('faculty.api-keys.index'));

        $response->assertStatus(403);
    });
});
