<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\Course;
use App\Models\Student;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia;

use function Pest\Laravel\actingAs;

function studentCreatePayload(Course $course, array $overrides = []): array
{
    return array_merge([
        'student_type' => 'college',
        'student_id' => '212345',
        'status' => 'enrolled',
        'first_name' => 'Juan',
        'last_name' => 'Dela Cruz',
        'gender' => 'male',
        'birth_date' => '2004-05-10',
        'course_id' => (string) $course->id,
        'academic_year' => '1',
    ], $overrides);
}

function insertStudentEducationHistory(array $attributes): void
{
    $columns = array_flip(Schema::getColumnListing('student_education_info'));

    DB::table('student_education_info')->insert(array_intersect_key($attributes, $columns));
}

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

    Student::factory()->create(['religion' => 'Roman Catholic']);
    Student::factory()->create(['religion' => "Baha'i Faith"]);
    Student::factory()->create(['religion' => '   ']);

    actingAs($user)
        ->get(portalUrlForAdministrators('/administrators/students/create'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('administrators/students/create', false)
            ->where('options.courses', function ($courseGroups) use ($activeCourse, $inactiveCourse): bool {
                $courses = $courseGroups
                    ->flatMap(fn (array $group): array => $group['items'])
                    ->keyBy('value');

                return $courses->get($activeCourse->id)['is_active'] === true
                    && $courses->get($inactiveCourse->id)['is_active'] === false
                    && $courses->get($inactiveCourse->id)['label'] === 'INACTIVE - Inactive Course (Inactive)';
            })
            ->has('options.religions', 2)
            ->where('options.religions.0.value', "Baha'i Faith")
            ->where('options.religions.0.label', "Baha'i Faith")
            ->where('options.religions.1.value', 'Roman Catholic')
            ->where('options.default_income_mode', 'annual')
            ->has('options.income_modes', 2)
            ->where('options.income_modes.0.value', 'monthly')
            ->where('options.income_modes.1.value', 'annual')
            ->where('options.income_modes.1.brackets.0.value', 'below_250k')
        );
});

it('groups metadata-based TESDA qualifications for TESDA student creation', function (): void {
    $user = User::factory()->create(['role' => UserRole::Admin]);
    $course = Course::factory()->create([
        'code' => 'CSS-NC2',
        'title' => 'Computer System Servicing NC II',
        'department_id' => null,
        'curriculum_kind' => 'tesda_qualification',
        'qualification_level' => 'NC II',
        'duration_hours' => 280,
    ]);

    actingAs($user)
        ->get(portalUrlForAdministrators('/administrators/students/create'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('options.courses', function ($groups) use ($course): bool {
                $tesdaGroup = collect($groups)->firstWhere('pathway', 'tesda_qualification');
                $item = collect($tesdaGroup['items'] ?? [])->firstWhere('value', $course->id);

                return ($tesdaGroup['label'] ?? null) === 'TESDA — Technical Qualifications'
                    && ($item['curriculum_kind'] ?? null) === 'tesda_qualification';
            })
        );
});

it('finds senior high autocomplete values from compatible senior high school columns', function (): void {
    $user = User::factory()->create(['role' => UserRole::Admin]);
    $hasLegacySeniorHighSchool = Schema::hasColumn('student_education_info', 'senior_high_school');
    $hasSeniorHighName = Schema::hasColumn('student_education_info', 'senior_high_name');

    insertStudentEducationHistory([
        ($hasLegacySeniorHighSchool ? 'senior_high_school' : 'senior_high_name') => 'Sample Legacy Senior High School',
    ]);
    insertStudentEducationHistory([
        ($hasSeniorHighName ? 'senior_high_name' : 'senior_high_school') => 'Sample University Senior High',
    ]);

    $response = actingAs($user)->getJson(route('administrators.students.field-values', [
        'field' => 'senior_high_name',
        'search' => 'sample',
    ]));

    $response
        ->assertOk()
        ->assertJsonFragment(['Sample Legacy Senior High School'])
        ->assertJsonFragment(['Sample University Senior High']);
});

it('returns school autocomplete options with paired addresses', function (): void {
    $user = User::factory()->create(['role' => UserRole::Admin]);
    $seniorHighColumn = Schema::hasColumn('student_education_info', 'senior_high_name')
        ? 'senior_high_name'
        : 'senior_high_school';

    insertStudentEducationHistory([
        $seniorHighColumn => 'Sample City National High School',
        'senior_high_address' => '123 Example Road, Sample City',
    ]);

    $response = actingAs($user)->getJson(route('administrators.students.education-school-options', [
        'field' => 'senior_high_name',
        'search' => 'sample',
    ]));

    $response->assertOk();

    if (! Schema::hasColumn('student_education_info', 'senior_high_address')) {
        $response->assertJsonFragment([
            'name' => 'Sample City National High School',
            'address' => null,
        ]);

        return;
    }

    $response->assertJsonFragment([
        'name' => 'Sample City National High School',
        'address' => '123 Example Road, Sample City',
    ]);
});

it('stores related student information from the create page', function (): void {
    $user = User::factory()->create(['role' => UserRole::Admin]);
    $course = Course::factory()->create(['is_active' => true]);

    $response = actingAs($user)
        ->post(portalUrlForAdministrators('/administrators/students'), studentCreatePayload($course, [
            'middle_name' => 'Santos',
            'suffix' => 'Jr.',
            'email' => 'juan.delacruz@example.test',
            'phone' => '09170000000',
            'civil_status' => 'single',
            'nationality' => 'filipino',
            'religion' => 'Pastafarian',
            'remarks' => 'Test student',
            'submit_action' => 'view',
            'personal_contact' => '09171111111',
            'facebook_contact' => 'facebook.com/juan.delacruz',
            'emergency_contact_name' => 'Maria Dela Cruz',
            'emergency_contact_phone' => '09172222222',
            'emergency_contact_address' => '123 Guardian Street',
            'fathers_name' => 'Pedro Dela Cruz',
            'mothers_name' => 'Maria Dela Cruz',
            'current_address' => '123 Current Street',
            'permanent_address' => '456 Permanent Street',
            'birthplace' => 'Sample City',
            'elementary_school' => 'Sample Elementary School',
            'elementary_graduate_year' => '2016',
            'elementary_school_address' => 'Elementary Address',
            'junior_high_school_name' => 'Sample Junior High School',
            'junior_high_graduation_year' => '2020',
            'junior_high_school_address' => 'Junior High Address',
            'senior_high_name' => 'Sample Senior High School',
            'senior_high_graduate_year' => '2022',
            'senior_high_address' => 'Senior High Address',
            'ethnicity' => 'Tagalog',
            'region_of_origin' => 'Sample Region',
            'province_of_origin' => 'Example Province',
            'city_of_origin' => 'Sample City',
            'is_indigenous_person' => false,
            'scholarship_type' => 'none',
            'employment_status' => 'not_applicable',
        ]));

    $student = Student::withoutGlobalScopes()
        ->where('student_id', 212345)
        ->firstOrFail();

    $response->assertRedirect(route('administrators.students.show', $student));

    expect($student->student_contact_id)->not->toBeNull()
        ->and($student->student_parent_info)->not->toBeNull()
        ->and($student->student_education_id)->not->toBeNull()
        ->and($student->student_personal_id)->not->toBeNull()
        ->and($student->phone)->toBe('09170000000')
        ->and($student->civil_status)->toBe('single')
        ->and($student->nationality)->toBe('filipino')
        ->and($student->religion)->toBe('Pastafarian')
        ->and($student->status->value)->toBe('enrolled');

    $contact = DB::table('student_contacts')->where('id', $student->student_contact_id)->first();
    expect($contact)->not->toBeNull()
        ->and($contact->personal_contact)->toBe('09171111111')
        ->and($contact->emergency_contact_name)->toBe('Maria Dela Cruz')
        ->and($contact->emergency_contact_phone)->toBe('09172222222');

    if (Schema::hasColumn('student_contacts', 'emergency_contact_address')) {
        expect($contact->emergency_contact_address)->toBe('123 Guardian Street');
    }

    if (Schema::hasColumn('student_contacts', 'facebook_contact')) {
        expect($contact->facebook_contact)->toBe('facebook.com/juan.delacruz');
    }

    $parent = DB::table('student_parents_info')->where('id', $student->student_parent_info)->first();
    $fatherColumn = Schema::hasColumn('student_parents_info', 'fathers_name') ? 'fathers_name' : 'father_name';
    $motherColumn = Schema::hasColumn('student_parents_info', 'mothers_name') ? 'mothers_name' : 'mother_name';

    expect($parent)->not->toBeNull()
        ->and($parent->{$fatherColumn})->toBe('Pedro Dela Cruz')
        ->and($parent->{$motherColumn})->toBe('Maria Dela Cruz');

    if (Schema::hasColumn('student_parents_info', 'guardian_name')) {
        expect($parent->guardian_name)->toBe('Maria Dela Cruz');
    }

    if (Schema::hasColumn('student_parents_info', 'guardian_contact')) {
        expect($parent->guardian_contact)->toBe('09172222222');
    }

    $education = DB::table('student_education_info')->where('id', $student->student_education_id)->first();
    $elementaryYearColumn = Schema::hasColumn('student_education_info', 'elementary_graduate_year')
        ? 'elementary_graduate_year'
        : 'elementary_year_graduated';
    $juniorHighColumn = Schema::hasColumn('student_education_info', 'junior_high_school_name')
        ? 'junior_high_school_name'
        : 'high_school';

    expect($education)->not->toBeNull()
        ->and($education->elementary_school)->toBe('Sample Elementary School')
        ->and($education->{$elementaryYearColumn})->toBe('2016')
        ->and($education->{$juniorHighColumn})->toBe('Sample Junior High School');

    $personal = DB::table('students_personal_info')->where('id', $student->student_personal_id)->first();
    $birthplaceColumn = Schema::hasColumn('students_personal_info', 'birthplace') ? 'birthplace' : 'place_of_birth';

    expect($personal)->not->toBeNull()
        ->and($personal->{$birthplaceColumn})->toBe('Sample City');

    if (Schema::hasColumn('students_personal_info', 'current_adress')) {
        expect($personal->current_adress)->toBe('123 Current Street');
    }

    if (Schema::hasColumn('students_personal_info', 'permanent_address')) {
        expect($personal->permanent_address)->toBe('456 Permanent Street');
    }

    expect(DB::table('student_statuses')->where('student_id', $student->id)->where('status', 'enrolled')->exists())->toBeTrue();
});

it('can redirect back to create another student after storing', function (): void {
    $user = User::factory()->create(['role' => UserRole::Admin]);
    $course = Course::factory()->create(['is_active' => true]);

    actingAs($user)
        ->post(portalUrlForAdministrators('/administrators/students'), studentCreatePayload($course, [
            'student_id' => '212346',
            'submit_action' => 'create_another',
        ]))
        ->assertRedirect(route('administrators.students.create'));
});

it('can redirect to create an enrollment after storing', function (): void {
    $user = User::factory()->create(['role' => UserRole::Admin]);
    $course = Course::factory()->create(['is_active' => true]);

    $response = actingAs($user)
        ->post(portalUrlForAdministrators('/administrators/students'), studentCreatePayload($course, [
            'student_id' => '212347',
            'submit_action' => 'create_enrollment',
        ]));

    $student = Student::withoutGlobalScopes()
        ->where('student_id', 212347)
        ->firstOrFail();

    $response->assertRedirect(route('administrators.enrollments.create', ['student_id' => $student->id]));

    expect(Student::withoutGlobalScopes()->whereKey($student->id)->exists())->toBeTrue();
});

it('updates facebook contact from the student edit payload', function (): void {
    $user = User::factory()->create(['role' => UserRole::Admin]);
    $course = Course::factory()->create(['is_active' => true]);

    $student = Student::factory()->create([
        'student_type' => 'college',
        'student_id' => 298765,
        'course_id' => $course->id,
        'academic_year' => 1,
        'first_name' => 'Old',
        'last_name' => 'Student',
        'gender' => 'male',
        'birth_date' => '2003-01-01',
        'status' => 'enrolled',
        'student_contact_id' => null,
    ]);

    actingAs($user)
        ->put(portalUrlForAdministrators('/administrators/students/'.$student->id), [
            'student_type' => 'college',
            'student_id' => '298765',
            'status' => 'enrolled',
            'first_name' => 'Old',
            'last_name' => 'Student',
            'middle_name' => '',
            'suffix' => '',
            'gender' => 'male',
            'birth_date' => '2003-01-01',
            'email' => 'old.student@example.test',
            'phone' => '09170000009',
            'course_id' => (string) $course->id,
            'academic_year' => '1',
            'facebook_contact' => 'facebook.com/updated.student',
        ])
        ->assertRedirect();

    $student->refresh();
    expect($student->student_contact_id)->not->toBeNull();

    if (! Schema::hasColumn('student_contacts', 'facebook_contact')) {
        return;
    }

    $contact = DB::table('student_contacts')->where('id', $student->student_contact_id)->first();
    expect($contact)->not->toBeNull()
        ->and($contact->facebook_contact)->toBe('facebook.com/updated.student');
});
