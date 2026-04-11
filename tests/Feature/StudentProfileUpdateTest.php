<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Features\Onboarding\StudentSchedule;
use App\Models\Student;
use App\Models\User;

beforeEach(function (): void {
    config(['activitylog.enabled' => false]);

    // Create a user with Student role
    $this->user = User::factory()->create([
        'role' => UserRole::Student,
        'email' => 'student@example.com',
    ]);

    // Create a student record associated with the user
    $this->student = Student::factory()->create([
        'user_id' => $this->user->id,
        'email' => 'student@example.com',
        'document_location_id' => null,
        'student_contact_id' => null,
        'student_parent_info' => null,
        'student_education_id' => null,
        'student_personal_id' => null,
    ]);
});

it('can access student profile page', function (): void {
    $response = $this
        ->actingAs($this->user)
        ->get(route('student.profile'));

    $response->assertSuccessful();
});

it('returns correct endpoints for student portal', function (): void {
    $response = $this
        ->actingAs($this->user)
        ->get(route('student.profile'));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->has('endpoints')
        ->where('endpoints.profile_update', '/student/profile')
        ->where('endpoints.password_update', '/student/profile/password')
        ->where('endpoints.student_update', '/student/profile/student')
        ->where('endpoints.passkeys', '/student/profile/passkeys')
        ->where('endpoints.two_factor_enable', '/student/profile/two-factor-authentication/enable')
        ->where('endpoints.two_factor_confirm', '/student/profile/two-factor-authentication/confirm')
        ->where('endpoints.two_factor_disable', '/student/profile/two-factor-authentication')
        ->where('endpoints.email_auth_toggle', '/student/profile/email-authentication')
        ->where('endpoints.experimental_features', '/student/profile/experimental-features')
        ->where('endpoints.browser_sessions_logout', '/student/profile/other-browser-sessions')
    );
});

it('can update student profile information', function (): void {
    $response = $this
        ->actingAs($this->user)
        ->put(route('student.profile.student.update'), [
            'first_name' => 'Updated First',
            'last_name' => 'Updated Last',
            'email' => 'updated_student@example.com',
            'phone' => '+63 912 345 6789',
            'address' => 'New Address',
            'birth_date' => '2000-01-01',
            'gender' => 'male',
            'contacts' => [
                'emergency_contact_name' => 'Emer Gency',
                'emergency_contact_phone' => '09123456789',
            ],
            'education' => [
                'elementary_school' => 'Elementary School',
                'elementary_year_graduated' => '2012',
            ],
            'parents' => [
                'father_name' => 'Father Name',
                'mother_name' => 'Mother Name',
            ],
        ]);

    $response->assertRedirect();
    $response->assertSessionHas('flash.success');

    $this->student->refresh();
    expect($this->student->first_name)->toBe('Updated First');
    expect($this->student->last_name)->toBe('Updated Last');
    expect($this->student->email)->toBe('updated_student@example.com');
    expect($this->student->phone)->toBe('+63 912 345 6789');
    expect($this->student->address)->toBe('New Address');
    expect($this->student->birth_date->format('Y-m-d'))->toBe('2000-01-01');
    expect($this->student->gender)->toBe('male');

    // Refresh student to check related models
    $this->student->refresh();

    // Verify Contacts
    expect($this->student->studentContactsInfo)->not->toBeNull();
    expect($this->student->studentContactsInfo->emergency_contact_name)->toBe('Emer Gency');
    expect($this->student->studentContactsInfo->emergency_contact_phone)->toBe('09123456789');

    // Verify Education
    expect($this->student->studentEducationInfo)->not->toBeNull();
    expect($this->student->studentEducationInfo->elementary_school)->toBe('Elementary School');
    expect($this->student->studentEducationInfo->elementary_year_graduated)->toBe('2012');

    // Verify Parents
    expect($this->student->studentParentInfo)->not->toBeNull();
    expect($this->student->studentParentInfo->father_name)->toBe('Father Name');
    expect($this->student->studentParentInfo->mother_name)->toBe('Mother Name');

    // Check if user email is also updated
    $this->user->refresh();
    expect($this->user->email)->toBe('updated_student@example.com');
});

it('validates required fields when updating student details', function (): void {
    $response = $this
        ->actingAs($this->user)
        ->put(route('student.profile.student.update'), [
            'first_name' => '',
            'last_name' => '',
            'email' => '',
        ]);

    $response->assertSessionHasErrors(['first_name', 'last_name', 'email']);
});

it('returns student data when student record exists', function (): void {
    $response = $this
        ->actingAs($this->user)
        ->get(route('student.profile'));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->has('student')
        ->where('student.first_name', $this->student->first_name)
        ->where('student.last_name', $this->student->last_name)
        ->where('student.email', $this->student->email)
    );
});

it('can toggle experimental features', function (): void {
    config(['onboarding.experimental_feature_keys' => ['onboarding-student-schedule']]);

    $response = $this
        ->actingAs($this->user)
        ->post(route('student.profile.experimental-features'), [
            'features' => ['onboarding-student-schedule'],
        ]);

    $response->assertRedirect();
    expect(Laravel\Pennant\Feature::for($this->user)->active(StudentSchedule::class))->toBeTrue();
});
