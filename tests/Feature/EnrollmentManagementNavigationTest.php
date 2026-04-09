<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

it('renders the enrollment students page', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin->value]);

    config(['inertia.testing.ensure_pages_exist' => false]);

    $this->actingAs($admin)
        ->get(route('administrators.enrollments.index'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('administrators/enrollments/index')
            ->has('enrollments')
            ->has('analytics')
            ->has('applicantsCount')
            ->has('workflow_setup_required')
        );
});

it('renders the enrollment applicants page', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin->value]);

    config(['inertia.testing.ensure_pages_exist' => false]);

    $this->actingAs($admin)
        ->get(route('administrators.enrollments.applicants'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('administrators/enrollments/applicants')
            ->has('applicants.data')
            ->has('filters')
        );
});

it('renders the enrollment create page with a student_id query', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin->value]);

    config(['inertia.testing.ensure_pages_exist' => false]);

    $this->actingAs($admin)
        ->get(route('administrators.enrollments.create', ['student_id' => 12345]))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('administrators/enrollments/create')
            ->has('settings')
        );
});
