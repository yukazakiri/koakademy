<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Features\Onboarding\FacultyToolkit;
use App\Models\Faculty;
use App\Models\User;

beforeEach(function (): void {
    // Create a user with Instructor role and verified faculty ID
    $this->user = User::factory()->create([
        'role' => UserRole::Instructor,
        'email' => 'faculty@example.com',
        'faculty_id_number' => 'F123456', // Required to pass faculty.verified middleware
    ]);
});

it('can access faculty profile page', function (): void {
    $response = $this
        ->actingAs($this->user)
        ->get(portalUrlForAdministrators('/faculty/profile'));

    $response->assertSuccessful();
});

it('returns correct endpoints for faculty portal', function (): void {
    $response = $this
        ->actingAs($this->user)
        ->get(portalUrlForAdministrators('/faculty/profile'));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->has('endpoints')
        ->where('endpoints.profile_update', '/faculty/profile')
        ->where('endpoints.password_update', '/faculty/profile/password')
        ->where('endpoints.faculty_update', '/faculty/profile/faculty')
        ->where('endpoints.passkeys', '/faculty/profile/passkeys')
        ->where('endpoints.two_factor_enable', '/faculty/profile/two-factor-authentication/enable')
        ->where('endpoints.two_factor_confirm', '/faculty/profile/two-factor-authentication/confirm')
        ->where('endpoints.two_factor_disable', '/faculty/profile/two-factor-authentication')
        ->where('endpoints.email_auth_toggle', '/faculty/profile/email-authentication')
        ->where('endpoints.experimental_features', '/faculty/profile/experimental-features')
        ->where('endpoints.browser_sessions_logout', '/faculty/profile/other-browser-sessions')
    );
});

it('can update user profile information', function (): void {
    $response = $this
        ->actingAs($this->user)
        ->put(portalUrlForAdministrators('/faculty/profile'), [
            'name' => 'Updated Name',
            'email' => 'updated@example.com',
            'phone' => '+63 912 345 6789',
            'department' => 'Computer Science',
            'position' => 'Senior Instructor',
        ]);

    $response->assertRedirect();
    $response->assertSessionHas('flash.success');

    $this->user->refresh();
    expect($this->user->name)->toBe('Updated Name');
    expect($this->user->email)->toBe('updated@example.com');
    expect($this->user->phone)->toBe('+63 912 345 6789');
    expect($this->user->department)->toBe('Computer Science');
    expect($this->user->position)->toBe('Senior Instructor');
});

it('can update faculty specific fields including birth date and gender', function (): void {
    $faculty = Faculty::factory()->create([
        'email' => 'faculty@example.com',
    ]);

    $response = $this
        ->actingAs($this->user)
        ->put(portalUrlForAdministrators('/faculty/profile/faculty'), [
            'first_name' => 'Updated',
            'last_name' => 'Faculty',
            'email' => 'faculty@example.com',
            'birth_date' => '1980-01-01',
            'gender' => 'male',
            'phone_number' => '+63 912 345 6789',
        ]);

    $response->assertRedirect();
    $response->assertSessionHas('flash.success', 'Faculty information updated successfully!');

    $faculty->refresh();
    expect($faculty->birth_date->format('Y-m-d'))->toBe('1980-01-01');
    expect($faculty->gender)->toBe('male');
});

it('can create faculty record when updating faculty details without existing record', function (): void {
    $response = $this
        ->actingAs($this->user)
        ->put(portalUrlForAdministrators('/faculty/profile/faculty'), [
            'first_name' => 'John',
            'middle_name' => 'Michael',
            'last_name' => 'Doe',
            'email' => 'faculty@example.com',
            'phone_number' => '+63 912 345 6789',
            'department' => 'CCS',
            'office_hours' => 'Mon-Fri 9AM-5PM',
            'biography' => 'A dedicated educator with 10 years of experience.',
            'education' => 'PhD in Computer Science',
            'courses_taught' => 'CS101, CS201, CS301',
        ]);

    $response->assertRedirect();
    $response->assertSessionHas('flash.success', 'Faculty information created successfully!');

    $faculty = Faculty::where('email', 'faculty@example.com')->first();
    expect($faculty)->not->toBeNull();
    expect($faculty->first_name)->toBe('John');
    expect($faculty->middle_name)->toBe('Michael');
    expect($faculty->last_name)->toBe('Doe');
    expect($faculty->department)->toBe('CCS');
    expect($faculty->office_hours)->toBe('Mon-Fri 9AM-5PM');
});

it('can update existing faculty record', function (): void {
    // Create an existing faculty record
    $faculty = Faculty::factory()->create([
        'email' => 'faculty@example.com',
        'first_name' => 'Original',
        'last_name' => 'Name',
    ]);

    $response = $this
        ->actingAs($this->user)
        ->put(portalUrlForAdministrators('/faculty/profile/faculty'), [
            'first_name' => 'Updated',
            'middle_name' => 'New',
            'last_name' => 'Faculty',
            'email' => 'faculty@example.com',
            'phone_number' => '+63 912 345 6789',
            'department' => 'CBA',
            'office_hours' => 'Tue-Thu 10AM-4PM',
            'biography' => 'Updated biography',
            'education' => 'Masters in Business Administration',
            'courses_taught' => 'MBA101, MBA202',
        ]);

    $response->assertRedirect();
    $response->assertSessionHas('flash.success', 'Faculty information updated successfully!');

    $faculty->refresh();
    expect($faculty->first_name)->toBe('Updated');
    expect($faculty->middle_name)->toBe('New');
    expect($faculty->last_name)->toBe('Faculty');
    expect($faculty->department)->toBe('CBA');
});

it('validates required fields when updating faculty details', function (): void {
    $response = $this
        ->actingAs($this->user)
        ->put(portalUrlForAdministrators('/faculty/profile/faculty'), [
            'first_name' => '',
            'last_name' => '',
            'email' => '',
        ]);

    $response->assertSessionHasErrors(['first_name', 'last_name', 'email']);
});

it('validates email uniqueness when updating faculty details', function (): void {
    // Create another faculty with a different email
    Faculty::factory()->create([
        'email' => 'existing@example.com',
    ]);

    // Create the current user's faculty record
    Faculty::factory()->create([
        'email' => 'faculty@example.com',
    ]);

    $response = $this
        ->actingAs($this->user)
        ->put(portalUrlForAdministrators('/faculty/profile/faculty'), [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'existing@example.com', // Try to use existing email
        ]);

    $response->assertSessionHasErrors(['email']);
});

it('can update password successfully', function (): void {
    $this->user->update(['password' => bcrypt('oldpassword')]);

    $response = $this
        ->actingAs($this->user)
        ->put(portalUrlForAdministrators('/faculty/profile/password'), [
            'current_password' => 'oldpassword',
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ]);

    $response->assertRedirect();
    $response->assertSessionHas('flash.success');
});

it('validates current password when changing password', function (): void {
    $this->user->update(['password' => bcrypt('correctpassword')]);

    $response = $this
        ->actingAs($this->user)
        ->put(portalUrlForAdministrators('/faculty/profile/password'), [
            'current_password' => 'wrongpassword',
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ]);

    $response->assertSessionHasErrors(['current_password']);
});

it('returns faculty data when faculty record exists', function (): void {
    $faculty = Faculty::factory()->create([
        'email' => 'faculty@example.com',
        'first_name' => 'Test',
        'last_name' => 'Faculty',
        'department' => 'CCS',
    ]);

    $response = $this
        ->actingAs($this->user)
        ->get(portalUrlForAdministrators('/faculty/profile'));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->has('faculty')
        ->where('faculty.first_name', 'Test')
        ->where('faculty.last_name', 'Faculty')
        ->where('faculty.department', 'CCS')
    );
});

it('returns null for faculty when no faculty record exists', function (): void {
    $response = $this
        ->actingAs($this->user)
        ->get(portalUrlForAdministrators('/faculty/profile'));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->where('faculty', null)
    );
});

it('can toggle experimental features', function (): void {
    config(['onboarding.experimental_feature_keys' => ['onboarding-faculty-toolkit']]);

    $response = $this
        ->actingAs($this->user)
        ->post(portalUrlForAdministrators('/faculty/profile/experimental-features'), [
            'features' => ['onboarding-faculty-toolkit'],
        ]);

    $response->assertRedirect();
    expect(Laravel\Pennant\Feature::for($this->user)->active(FacultyToolkit::class))->toBeTrue();
});
