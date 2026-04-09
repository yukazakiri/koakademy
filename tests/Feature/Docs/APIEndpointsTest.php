<?php

declare(strict_types=1);

use App\Models\GeneralSetting;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->user = User::factory()->create();
    Sanctum::actingAs($this->user);
});

describe('API Authentication', function () {
    it('can get authenticated user', function () {
        $response = $this->getJson('/api/user');

        $response->assertOk()
            ->assertJsonStructure([
                'id',
                'name',
                'email',
            ]);
    });
});

describe('General Settings API', function () {
    beforeEach(function () {
        $this->setting = GeneralSetting::factory()->create([
            'site_name' => 'Test School',
            'site_description' => 'Test Description',
            'semester' => 1,
        ]);
    });

    it('lists all general settings', function () {
        $response = $this->getJson('/api/settings');

        $response->assertOk();
    });

    it('creates a new general setting', function () {
        $response = $this->postJson('/api/settings', [
            'site_name' => 'New School',
            'site_description' => 'New Description',
            'support_email' => 'admin@school.edu',
        ]);

        $response->assertCreated()
            ->assertJson([
                'message' => 'General settings created successfully',
            ]);
    });

    it('shows a specific general setting', function () {
        $response = $this->getJson("/api/settings/{$this->setting->id}");

        $response->assertOk()
            ->assertJson([
                'message' => 'General settings retrieved successfully',
                'data' => [
                    'site_name' => 'Test School',
                ],
            ]);
    });

    it('updates a general setting', function () {
        $response = $this->putJson("/api/settings/{$this->setting->id}", [
            'site_name' => 'Updated School',
        ]);

        $response->assertOk()
            ->assertJson([
                'message' => 'General settings updated successfully',
                'data' => [
                    'site_name' => 'Updated School',
                ],
            ]);
    });

    it('deletes a general setting', function () {
        $response = $this->deleteJson("/api/settings/{$this->setting->id}");

        $response->assertOk()
            ->assertJson([
                'message' => 'General settings deleted successfully',
            ]);
    });

    it('gets current settings', function () {
        $response = $this->getJson('/api/settings/current');

        $response->assertOk()
            ->assertJsonStructure([
                'message',
                'data',
            ]);
    });

    it('gets service settings', function () {
        $response = $this->getJson('/api/settings/service');

        $response->assertOk()
            ->assertJsonStructure([
                'message',
                'data' => [
                    'current_semester',
                    'current_school_year_start',
                    'current_school_year_string',
                ],
            ]);
    });
});
