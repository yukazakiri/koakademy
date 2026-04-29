<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\Course;
use App\Models\User;
use Inertia\Testing\AssertableInertia;

it('only includes active courses in scheduling class creation options', function () {
    $this->actingAs(User::factory()->create(['role' => UserRole::Admin]));

    $activeCourse = Course::factory()->create([
        'code' => 'ACTIVE',
        'is_active' => true,
    ]);

    $inactiveCourse = Course::factory()->create([
        'code' => 'INACTIVE',
        'is_active' => false,
    ]);

    $this->get(route('administrators.scheduling-analytics.index'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->component('administrators/scheduling-analytics', false)
            ->where('creation_options.courses', fn ($courses): bool => collect($courses)->contains('id', $activeCourse->id)
                && ! collect($courses)->contains('id', $inactiveCourse->id))
        );
});
