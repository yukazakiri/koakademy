<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\User;
use Modules\Announcement\Models\Announcement;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertDatabaseHas;

test('administrators can create announcements', function (): void {
    $user = User::factory()->create(['role' => UserRole::Admin]);

    actingAs($user)
        ->post(route('administrators.announcements.store'), [
            'title' => 'System Maintenance',
            'content' => 'Scheduled maintenance tonight.',
            'type' => 'maintenance',
            'priority' => 'high',
            'display_mode' => 'banner',
            'requires_acknowledgment' => false,
            'link' => null,
            'is_active' => true,
            'starts_at' => null,
            'ends_at' => null,
        ])
        ->assertRedirect();

    assertDatabaseHas(Announcement::class, [
        'title' => 'System Maintenance',
        'slug' => 'system-maintenance',
        'type' => 'maintenance',
        'priority' => 'high',
        'display_mode' => 'banner',
        'is_active' => true,
        'is_global' => true,
        'status' => 'published',
        'created_by' => $user->id,
    ]);
});

test('announcement creation validates required fields', function (): void {
    $user = User::factory()->create(['role' => UserRole::Admin]);

    actingAs($user)
        ->post(route('administrators.announcements.store'), [])
        ->assertSessionHasErrors(['title', 'content', 'type']);
});

test('announcement creation stores starts_at and ends_at', function (): void {
    $user = User::factory()->create(['role' => UserRole::Admin]);

    actingAs($user)
        ->post(route('administrators.announcements.store'), [
            'title' => 'Scheduled Event',
            'content' => 'This is a scheduled announcement.',
            'type' => 'info',
            'is_active' => true,
            'starts_at' => now()->addDay()->toDateTimeString(),
            'ends_at' => now()->addWeek()->toDateTimeString(),
        ])
        ->assertRedirect();

    assertDatabaseHas(Announcement::class, [
        'title' => 'Scheduled Event',
        'starts_at' => now()->addDay(),
        'ends_at' => now()->addWeek(),
    ]);
});
