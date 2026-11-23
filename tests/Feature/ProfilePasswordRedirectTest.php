<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Features\Toggles\StudentInformationUpdates;
use App\Models\Student;
use App\Models\User;
use Laravel\Pennant\Feature;

it('redirects student password route to the profile security tab', function (): void {
    config(['activitylog.enabled' => false]);

    $user = User::factory()->create([
        'role' => UserRole::Student,
        'email' => 'student-password-redirect@example.com',
    ]);

    Student::factory()->create([
        'user_id' => $user->id,
        'email' => $user->email,
        'document_location_id' => null,
        'student_contact_id' => null,
        'student_parent_info' => null,
        'student_education_id' => null,
        'student_personal_id' => null,
    ]);

    Feature::activateForEveryone(StudentInformationUpdates::class);

    $this->actingAs($user)
        ->get(route('student.profile.password'))
        ->assertRedirect(route('student.profile', ['tab' => 'accounts']));
});

it('redirects faculty password route to the profile security tab', function (): void {
    $user = User::factory()->create([
        'role' => UserRole::Instructor,
        'email' => 'faculty-password-redirect@example.com',
        'faculty_id_number' => 'F987654',
    ]);

    $this->actingAs($user)
        ->get(route('faculty.profile.password'))
        ->assertRedirect(route('faculty.profile', ['tab' => 'accounts']));
});
