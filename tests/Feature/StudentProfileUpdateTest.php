<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Features\Toggles\StudentDeveloperMode;
use App\Features\Toggles\StudentInformationUpdates;
use App\Features\Toggles\StudentSchedule;
use App\Models\Student;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Pennant\Feature;

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

    Feature::activateForEveryone(StudentInformationUpdates::class);
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
        ->where('endpoints.school_options', '/student/profile/school-options')
        ->where('endpoints.passkeys', '/student/profile/passkeys')
        ->where('endpoints.two_factor_enable', '/student/profile/two-factor-authentication/enable')
        ->where('endpoints.two_factor_confirm', '/student/profile/two-factor-authentication/confirm')
        ->where('endpoints.two_factor_disable', '/student/profile/two-factor-authentication')
        ->where('endpoints.security_two_factor_toggle', '/student/profile/two-factor-authentication/login-challenges')
        ->where('endpoints.email_auth_toggle', '/student/profile/email-authentication')
        ->where('endpoints.experimental_features', '/student/profile/experimental-features')
        ->where('endpoints.browser_sessions_logout', '/student/profile/other-browser-sessions')
    );
});

it('returns privacy-safe school options for the student profile', function (): void {
    $education = [
        'elementary_school' => 'Privacy Test Academy',
        'created_at' => now(),
        'updated_at' => now(),
    ];

    if (Schema::hasColumn('student_education_info', 'elementary_school_address')) {
        $education['elementary_school_address'] = 'Davao City';
    }

    DB::table('student_education_info')->insert($education);

    $response = $this
        ->actingAs($this->user)
        ->getJson(route('student.profile.school-options', [
            'field' => 'elementary_school',
            'search' => 'privacy test',
        ]));

    $response->assertOk()->assertJsonFragment([
        'name' => 'Privacy Test Academy',
        'address' => Schema::hasColumn('student_education_info', 'elementary_school_address') ? 'Davao City' : null,
    ]);

    expect($response->json('0'))->toHaveKeys(['name', 'address'])
        ->not->toHaveKeys(['student_id', 'first_name', 'last_name', 'email']);
});

it('blocks student school options when information updates are inactive', function (): void {
    Feature::deactivateForEveryone(StudentInformationUpdates::class);

    $this
        ->actingAs($this->user)
        ->getJson(route('student.profile.school-options', [
            'field' => 'elementary_school',
            'search' => 'school',
        ]))
        ->assertForbidden();
});

it('preserves the portfolio link while student developer mode is inactive', function (): void {
    $this->user->update(['website' => 'https://existing.example.com']);
    Feature::for($this->user)->deactivate(StudentDeveloperMode::class);

    $this
        ->actingAs($this->user)
        ->put(route('student.profile.update'), [
            'name' => $this->user->name,
            'email' => $this->user->email,
            'website' => 'https://blocked.example.com',
        ])
        ->assertRedirect();

    expect($this->user->refresh()->website)->toBe('https://existing.example.com');
});

it('updates the portfolio link while student developer mode is active', function (): void {
    $this->user->update(['website' => 'https://existing.example.com']);
    Feature::for($this->user)->activate(StudentDeveloperMode::class);

    $this
        ->actingAs($this->user)
        ->put(route('student.profile.update'), [
            'name' => $this->user->name,
            'email' => $this->user->email,
            'website' => 'https://portfolio.example.com',
        ])
        ->assertRedirect();

    expect($this->user->refresh()->website)->toBe('https://portfolio.example.com');
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
                'emergency_contact_relationship' => 'Mother',
                'facebook' => 'Mariane Jimenez',
                'personal_contact' => '+63 09511564252',
                'twitter' => 'https://x.com/student',
                'instagram' => 'https://instagram.com/student',
                'linkedin' => 'https://linkedin.com/in/student',
            ],
            'education' => [
                'elementary_school' => 'Elementary School',
                'elementary_year_graduated' => '2012',
                'college_school' => 'Previous College',
                'college_course' => 'BSIT',
                'college_year_graduated' => '2023',
                'vocational_school' => 'Technical Institute',
                'vocational_course' => 'Computer Systems',
                'vocational_year_graduated' => '2024',
            ],
            'parents' => [
                'father_name' => 'Father Name',
                'father_occupation' => 'Engineer',
                'father_contact' => '+63 912 111 1111',
                'father_email' => 'father@example.com',
                'mother_name' => 'Mother Name',
                'mother_occupation' => 'Teacher',
                'mother_contact' => '+63 912 222 2222',
                'mother_email' => 'mother@example.com',
                'guardian_name' => 'Guardian Name',
                'guardian_relationship' => 'Aunt',
                'guardian_contact' => '+63 912 333 3333',
                'guardian_email' => 'guardian@example.com',
                'family_address' => 'Family Address',
            ],
            'personal_info' => [
                'birthplace' => 'Davao City',
                'citizenship' => 'Filipino',
                'height' => '170',
                'weight' => '65',
                'current_address' => 'Current Address',
                'permanent_address' => 'Permanent Address',
            ],
            'ethnicity' => 'Cebuano',
            'city_of_origin' => 'Davao City',
            'province_of_origin' => 'Davao del Sur',
            'region_of_origin' => 'Region XI',
            'is_indigenous_person' => false,
            'is_pwd' => false,
            'is_solo_parent' => false,
            'is_senior_citizen' => false,
            'is_magna_carta' => false,
            'is_underprivileged' => false,
            'is_first_generation' => true,
            'income_bracket_mode' => 'annual',
            'use_same_parent_income' => true,
            'family_income_bracket' => 'below_250k',
            'reporting_confirmed' => true,
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
    expect($this->student->studentContactsInfo->emergency_contact_relationship)->toBe('Mother');
    expect($this->student->studentContactsInfo->personal_contact)->toBe('+63 09511564252');
    $facebookColumn = Schema::hasColumn('student_contacts', 'facebook') ? 'facebook' : 'facebook_contact';
    expect($this->student->studentContactsInfo->{$facebookColumn})->toBe('Mariane Jimenez');

    // Verify Education
    expect($this->student->studentEducationInfo)->not->toBeNull();
    expect($this->student->studentEducationInfo->elementary_school)->toBe('Elementary School');
    $elementaryYearColumn = Schema::hasColumn('student_education_info', 'elementary_graduate_year')
        ? 'elementary_graduate_year'
        : 'elementary_year_graduated';
    expect($this->student->studentEducationInfo->{$elementaryYearColumn})->toBe('2012');

    // Verify Parents
    expect($this->student->studentParentInfo)->not->toBeNull();
    $fatherColumn = Schema::hasColumn('student_parents_info', 'fathers_name') ? 'fathers_name' : 'father_name';
    $motherColumn = Schema::hasColumn('student_parents_info', 'mothers_name') ? 'mothers_name' : 'mother_name';
    expect($this->student->studentParentInfo->{$fatherColumn})->toBe('Father Name');
    expect($this->student->studentParentInfo->{$motherColumn})->toBe('Mother Name');
    expect($this->student->studentParentInfo->guardian_name)->toBe('Guardian Name');
    expect($this->student->studentEducationInfo->college_school)->toBe('Previous College');
    expect($this->student->studentContactsInfo->twitter)->toBe('https://x.com/student');
    expect($this->student->personalInfo?->birthplace ?? $this->student->personalInfo?->place_of_birth)->toBe('Davao City');
    expect($this->student->ethnicity)->toBe('Cebuano');
    expect($this->student->family_income_bracket)->toBe('below_250k');
    expect($this->student->profile_reporting_confirmed_at)->not->toBeNull();

    // Check if user email is also updated
    $this->user->refresh();
    expect($this->user->email)->toBe('updated_student@example.com');
});

it('can save contact information when the existing student gender is capitalized', function (): void {
    $this->student->update([
        'gender' => 'Male',
    ]);

    $response = $this
        ->actingAs($this->user)
        ->put(route('student.profile.student.update'), [
            'first_name' => $this->student->first_name,
            'middle_name' => $this->student->middle_name,
            'last_name' => $this->student->last_name,
            'email' => $this->student->email,
            'phone' => $this->student->phone,
            'address' => $this->student->address,
            'civil_status' => $this->student->civil_status,
            'nationality' => $this->student->nationality,
            'religion' => $this->student->religion,
            'emergency_contact' => $this->student->emergency_contact,
            'birth_date' => $this->student->birth_date->format('Y-m-d'),
            'gender' => 'Male',
            'contacts' => [
                'emergency_contact_name' => 'Leslie P. Jimenez',
                'emergency_contact_phone' => '+63 9852521929',
                'emergency_contact_relationship' => 'Mother',
                'facebook' => 'Mariane Jimenez',
                'personal_contact' => '+63 09511564252',
            ],
            'parents' => [
                'father_name' => 'Luis Jr. D. Jimenez',
                'mother_name' => 'Leslie P. Jimenez',
            ],
        ]);

    $response->assertRedirect();
    $response->assertSessionHas('flash.success');
    $response->assertSessionDoesntHaveErrors();

    $this->student->refresh();

    expect($this->student->gender)->toBe('male');
    expect($this->student->studentContactsInfo)->not->toBeNull();
    expect($this->student->studentContactsInfo->emergency_contact_name)->toBe('Leslie P. Jimenez');
    expect($this->student->studentContactsInfo->emergency_contact_phone)->toBe('+63 9852521929');
    expect($this->student->studentContactsInfo->emergency_contact_relationship)->toBe('Mother');
    expect($this->student->studentContactsInfo->personal_contact)->toBe('+63 09511564252');
    $facebookColumn = Schema::hasColumn('student_contacts', 'facebook') ? 'facebook' : 'facebook_contact';
    expect($this->student->studentContactsInfo->{$facebookColumn})->toBe('Mariane Jimenez');

    expect($this->student->studentParentInfo)->not->toBeNull();
    $fatherColumn = Schema::hasColumn('student_parents_info', 'fathers_name') ? 'fathers_name' : 'father_name';
    $motherColumn = Schema::hasColumn('student_parents_info', 'mothers_name') ? 'mothers_name' : 'mother_name';
    expect($this->student->studentParentInfo->{$fatherColumn})->toBe('Luis Jr. D. Jimenez');
    expect($this->student->studentParentInfo->{$motherColumn})->toBe('Leslie P. Jimenez');
});

it('blocks student information updates when the feature is inactive', function (): void {
    Feature::deactivateForEveryone(StudentInformationUpdates::class);

    $response = $this
        ->actingAs($this->user)
        ->put(route('student.profile.student.update'), [
            'first_name' => 'Updated First',
            'last_name' => 'Updated Last',
            'email' => 'updated_student@example.com',
        ]);

    $response->assertForbidden();
});

it('keeps the student profile page accessible when information updates are inactive', function (): void {
    Feature::deactivateForEveryone(StudentInformationUpdates::class);

    $response = $this
        ->actingAs($this->user)
        ->get(route('student.profile'));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->where('feature_flags.student_information_updates', false)
        ->has('student')
    );
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

it('validates reporting details that depend on a selected support group or income split', function (): void {
    $response = $this
        ->actingAs($this->user)
        ->put(route('student.profile.student.update'), [
            'first_name' => $this->student->first_name,
            'last_name' => $this->student->last_name,
            'email' => $this->student->email,
            'is_indigenous_person' => true,
            'is_pwd' => true,
            'income_bracket_mode' => 'annual',
            'use_same_parent_income' => false,
        ]);

    $response->assertSessionHasErrors([
        'indigenous_group',
        'pwd_type',
        'father_income_bracket',
        'mother_income_bracket',
    ]);
});

it('clears dependent reporting details when the related category no longer applies', function (): void {
    $this->student->update([
        'is_indigenous_person' => true,
        'indigenous_group' => 'Manobo',
        'is_pwd' => true,
        'pwd_type' => 'Visual Disability',
    ]);

    $this
        ->actingAs($this->user)
        ->put(route('student.profile.student.update'), [
            'first_name' => $this->student->first_name,
            'middle_name' => $this->student->middle_name,
            'last_name' => $this->student->last_name,
            'email' => $this->student->email,
            'phone' => $this->student->phone,
            'address' => $this->student->address,
            'birth_date' => $this->student->birth_date->format('Y-m-d'),
            'gender' => $this->student->gender,
            'is_indigenous_person' => false,
            'is_pwd' => false,
        ])
        ->assertRedirect()
        ->assertSessionDoesntHaveErrors();

    $this->student->refresh();

    expect($this->student->indigenous_group)->toBeNull()
        ->and($this->student->pwd_type)->toBeNull();
});

it('returns student data when student record exists', function (): void {
    $response = $this
        ->actingAs($this->user)
        ->get(route('student.profile'));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->has('student')
        ->where('user.security_two_factor_enabled', true)
        ->where('student.first_name', $this->student->first_name)
        ->where('student.last_name', $this->student->last_name)
        ->where('student.email', $this->student->email)
    );
});

it('returns student profile completion data for incomplete information', function (): void {
    $this->student->update([
        'phone' => null,
        'address' => null,
        'emergency_contact' => null,
    ]);

    $response = $this
        ->actingAs($this->user)
        ->get(route('student.profile'));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->where('student_profile_completion.total', 33)
        ->where('student_profile_completion.missing.0.label', 'Phone number')
        ->where('student_profile_completion.missing.0.example', '+63 912 345 6789')
    );
});

it('shares a non-dismissible completion banner when important student information is missing', function (): void {
    $this->student->update([
        'phone' => null,
        'address' => null,
        'emergency_contact' => null,
    ]);

    $response = $this
        ->actingAs($this->user)
        ->get(route('student.profile'));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->where('announcements.0.title', 'Complete your student information')
        ->where('announcements.0.action_label', 'Complete student information')
        ->where('announcements.0.link', '/student/profile#student-personal')
        ->where('announcements.0.non_dismissible', true)
    );
});

it('does not share the completion banner when student information updates are inactive', function (): void {
    Feature::deactivateForEveryone(StudentInformationUpdates::class);

    $this->student->update([
        'phone' => null,
        'address' => null,
        'emergency_contact' => null,
    ]);

    $response = $this
        ->actingAs($this->user)
        ->get(route('student.profile'));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->has('announcements', 0)
    );
});

it('does not share the completion banner when important student information is complete', function (): void {
    $response = $this
        ->actingAs($this->user)
        ->put(route('student.profile.student.update'), [
            'first_name' => 'Complete',
            'last_name' => 'Student',
            'email' => 'complete_student@example.com',
            'phone' => '+63 912 345 6789',
            'address' => 'Davao City, Davao del Sur',
            'civil_status' => 'single',
            'nationality' => 'Filipino',
            'religion' => 'Roman Catholic',
            'emergency_contact' => 'Maria Dela Cruz - 09123456789',
            'birth_date' => '2000-01-01',
            'gender' => 'male',
            'contacts' => [
                'emergency_contact_name' => 'Maria Dela Cruz',
                'emergency_contact_phone' => '09123456789',
                'emergency_contact_relationship' => 'Mother',
                'personal_contact' => '+63 912 345 6789',
            ],
            'education' => [
                'elementary_school' => 'Davao Central Elementary School',
                'elementary_year_graduated' => '2016',
                'high_school' => 'Davao National High School',
                'high_school_year_graduated' => '2020',
                'senior_high_school' => 'Davao Senior High School',
                'senior_high_year_graduated' => '2022',
            ],
            'parents' => [
                'father_name' => 'Pedro Dela Cruz',
                'mother_name' => 'Maria Dela Cruz',
                'guardian_name' => 'Maria Dela Cruz',
                'guardian_relationship' => 'Mother',
                'guardian_contact' => '+63 912 345 6789',
            ],
            'personal_info' => [
                'birthplace' => 'Davao City',
                'citizenship' => 'Filipino',
                'current_address' => 'Davao City, Davao del Sur',
                'permanent_address' => 'Davao City, Davao del Sur',
            ],
            'ethnicity' => 'Cebuano',
            'city_of_origin' => 'Davao City',
            'province_of_origin' => 'Davao del Sur',
            'region_of_origin' => 'Region XI',
            'income_bracket_mode' => 'annual',
            'use_same_parent_income' => true,
            'family_income_bracket' => 'below_250k',
            'reporting_confirmed' => true,
        ]);

    $response->assertRedirect();

    $response = $this
        ->actingAs($this->user)
        ->get(route('student.profile'));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->where('student_profile_completion.percentage', 100)
        ->has('announcements', 0)
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
    expect(Feature::for($this->user)->active(StudentSchedule::class))->toBeTrue();
});
