<?php

declare(strict_types=1);

use App\Models\User;
use Carbon\CarbonImmutable;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

it('marks newly created faculty users as new on dashboard props', function (): void {
    config(['inertia.testing.ensure_pages_exist' => false]);

    $user = User::factory()->create([
        'role' => 'faculty',
        'created_at' => CarbonImmutable::now()->subDays(2),
    ]);

    actingAs($user);

    $this->withoutMiddleware();

    get('/faculty/dashboard')
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('faculty/dashboard')
            ->where('is_new_user', true)
        );
});

it('marks older faculty users as not new on dashboard props', function (): void {
    config(['inertia.testing.ensure_pages_exist' => false]);

    $user = User::factory()->create([
        'role' => 'faculty',
        'created_at' => CarbonImmutable::now()->subDays(45),
    ]);

    actingAs($user);

    $this->withoutMiddleware();

    get('/faculty/dashboard')
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('faculty/dashboard')
            ->where('is_new_user', false)
        );
});
