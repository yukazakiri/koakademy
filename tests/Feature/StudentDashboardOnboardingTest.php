<?php

declare(strict_types=1);

use App\Features\Toggles\StudentInformationUpdates;
use App\Models\OnboardingProgress;
use App\Models\Student;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Pennant\Feature;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

it('marks student users with no onboarding progress as new on dashboard props', function (): void {
    config(['inertia.testing.ensure_pages_exist' => false]);

    $user = User::factory()->create([
        'role' => 'student',
    ]);

    actingAs($user);

    $this->withoutMiddleware();

    get('/student/dashboard')
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('student/dashboard')
            ->where('is_new_user', true)
        );
});

it('marks student users with existing onboarding progress as not new on dashboard props', function (): void {
    config(['inertia.testing.ensure_pages_exist' => false]);

    $user = User::factory()->create([
        'role' => 'student',
    ]);

    OnboardingProgress::create([
        'user_id' => $user->id,
        'variant' => 'student',
        'started_at' => now(),
    ]);

    actingAs($user);

    $this->withoutMiddleware();

    get('/student/dashboard')
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('student/dashboard')
            ->where('is_new_user', false)
        );
});

it('shares an actionable profile completion prompt for students with missing reporting information', function (): void {
    config(['inertia.testing.ensure_pages_exist' => false]);

    $user = User::factory()->create([
        'role' => 'student',
        'email' => 'incomplete-profile@example.com',
    ]);
    Student::factory()->create([
        'user_id' => $user->id,
        'email' => $user->email,
        'phone' => null,
        'address' => null,
    ]);
    Feature::activateForEveryone(StudentInformationUpdates::class);

    actingAs($user);
    $this->withoutMiddleware();

    get('/student/dashboard')
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('student/dashboard')
            ->where('profile_completion_prompt.link', '/student/profile?guided=1#student-personal')
            ->where('profile_completion_prompt.missing.0.label', 'Phone number')
        );
});

it('does not prompt when student information updates are disabled', function (): void {
    config(['inertia.testing.ensure_pages_exist' => false]);

    $user = User::factory()->create([
        'role' => 'student',
        'email' => 'updates-disabled@example.com',
    ]);
    Student::factory()->create([
        'user_id' => $user->id,
        'email' => $user->email,
        'phone' => null,
    ]);
    Feature::deactivateForEveryone(StudentInformationUpdates::class);

    actingAs($user);
    $this->withoutMiddleware();

    get('/student/dashboard')
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('student/dashboard')
            ->where('profile_completion_prompt', null)
        );
});
