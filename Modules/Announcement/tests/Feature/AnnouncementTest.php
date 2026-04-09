<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Announcement\Models\Announcement;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('requires authentication to view announcements page', function () {
    $response = $this->get(route('administrators.announcements.index'));

    $response->assertRedirect(route('login'));
});

it('can list announcements', function () {
    config(['inertia.testing.ensure_pages_exist' => false]);
    $role = Role::create(['name' => 'admin']);
    $admin = User::factory()->create(['role' => 'admin']);
    $admin->assignRole($role);
    Announcement::factory()->count(3)->create(['created_by' => $admin->id]);

    $response = $this->actingAs($admin)
        ->get(route('administrators.announcements.index'));

    $response->assertOk()
        ->assertInertia(
            fn ($page) => $page
                ->component('Announcement/Index')
                ->has('announcements.data', 3)
        );
});

it('can create an announcement', function () {
    $role = Role::create(['name' => 'admin']);
    $admin = User::factory()->create(['role' => 'admin']);
    $admin->assignRole($role);

    $payload = [
        'title' => 'Test Announcement',
        'content' => 'This is a test announcement content.',
        'type' => 'info',
        'is_active' => true,
    ];

    $response = $this->actingAs($admin)
        ->post(route('administrators.announcements.store'), $payload);

    $response->assertRedirect();

    $this->assertDatabaseHas('announcements', [
        'title' => 'Test Announcement',
        'created_by' => $admin->id,
    ]);
});

it('can update an announcement', function () {
    $role = Role::create(['name' => 'developer']);
    $admin = User::factory()->create(['role' => 'developer']);
    $admin->assignRole($role);

    $announcement = Announcement::factory()->create([
        'title' => 'Old Title',
        'created_by' => $admin->id,
    ]);

    $payload = [
        'title' => 'New Title',
        'content' => $announcement->content,
        'type' => $announcement->type,
        'is_active' => false,
    ];

    $response = $this->actingAs($admin)
        ->put(route('administrators.announcements.update', $announcement), $payload);

    $response->assertRedirect();

    $this->assertDatabaseHas('announcements', [
        'id' => $announcement->id,
        'title' => 'New Title',
        'is_active' => false,
    ]);
});

it('can delete an announcement', function () {
    $role = Role::create(['name' => 'super_admin']);
    $admin = User::factory()->create(['role' => 'super_admin']);
    $admin->assignRole($role);

    $announcement = Announcement::factory()->create([
        'created_by' => $admin->id,
    ]);

    $response = $this->actingAs($admin)
        ->delete(route('administrators.announcements.destroy', $announcement));

    $response->assertRedirect();

    $this->assertDatabaseMissing('announcements', [
        'id' => $announcement->id,
    ]);
});
