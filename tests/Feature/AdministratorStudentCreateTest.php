<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\Course;
use App\Models\User;
use Inertia\Testing\AssertableInertia;

use function Pest\Laravel\actingAs;

it('displays active and inactive courses correctly on student create page', function (): void {
    $user = User::factory()->create(['role' => UserRole::Admin]);

    $activeCourse = Course::factory()->create([
        'code' => 'ACTIVE',
        'title' => 'Active Course',
        'is_active' => true,
    ]);

    $inactiveCourse = Course::factory()->create([
        'code' => 'INACTIVE',
        'title' => 'Inactive Course',
        'is_active' => false,
    ]);

    actingAs($user)
        ->get(portalUrlForAdministrators('/administrators/students/create'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('administrators/students/create', false)
            ->has('options.courses', 2)
            ->where('options.courses.0.value', $activeCourse->id)
            ->where('options.courses.0.is_active', true)
            ->where('options.courses.1.value', $inactiveCourse->id)
            ->where('options.courses.1.is_active', false)
            ->where('options.courses.1.label', 'INACTIVE - Inactive Course (Inactive)')
        );
});
