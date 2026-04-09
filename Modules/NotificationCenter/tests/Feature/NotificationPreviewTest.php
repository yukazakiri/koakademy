<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    Role::create(['name' => 'super_admin']);
    $this->admin = User::factory()->create();
    $this->admin->assignRole('super_admin');
});

it('can generate a preview for a generic notification', function () {
    $response = $this->actingAs($this->admin)->postJson(route('administrators.notifications.preview'), [
        'title' => 'Test Preview',
        'content' => 'This is a test preview',
        'channels' => ['mail', 'database'],
        'target_audience' => [],
    ]);

    $response->assertOk()
        ->assertJsonStructure([
            'email',
            'database',
        ]);
});
