<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

it('rejects arbitrary sort_by and sort_direction payloads on the student enrollments API endpoint', function (): void {
    $admin = User::factory()->create(['role' => UserRole::SuperAdmin]);

    Sanctum::actingAs($admin, [config('api.abilities.read'), config('api.abilities.write')]);

    $this->getJson('/api/enrollments?sort_by=(SELECT+1)&sort_direction=desc;DROP+TABLE+users;--')
        ->assertOk();

    $this->getJson('/api/enrollments?sort_by=nonexistent_column&sort_direction=INVALID')
        ->assertOk();
});

it('normalizes invalid sort_order payloads on the administrator help tickets endpoint', function (): void {
    $admin = User::factory()->create(['role' => UserRole::Admin]);

    $this->actingAs($admin)
        ->get(route('administrators.help-tickets.index', [
            'sort_by' => 'created_at',
            'sort_order' => 'desc,(SELECT 1)',
        ]))
        ->assertOk();

    $this->actingAs($admin)
        ->get(route('administrators.help-tickets.index', [
            'sort_by' => 'invalid_column',
            'sort_order' => 'ASC',
        ]))
        ->assertOk();
});
