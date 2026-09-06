<?php

declare(strict_types=1);

use App\Enums\CurriculumFramework;
use App\Enums\StudentStatus;
use App\Enums\UserRole;
use App\Models\Course;
use App\Models\CourseType;
use App\Models\Department;
use App\Models\School;
use App\Models\SchoolCurriculumCapability;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\User;
use App\Services\ChedFormBcExportService;
use PhpOffice\PhpSpreadsheet\Cell\DataType;

uses(Tests\TestCase::class);

beforeEach(function (): void {
    Spatie\Permission\Models\Permission::firstOrCreate([
        'name' => 'ViewAny:StudentEnrollment',
        'guard_name' => 'web',
    ]);
    Spatie\Permission\Models\Permission::firstOrCreate([
        'name' => 'ExportDetailed:StudentEnrollment',
        'guard_name' => 'web',
    ]);
});

test('ched form bc export service generates populated workbook and preview', function (): void {
    $school = School::factory()->create();

    $collegeType = CourseType::firstOrCreate(['name' => 'College Undergraduate']);

    $course = Course::factory()->create([
        'school_id' => $school->id,
        'course_type_id' => $collegeType->id,
        'code' => 'BSIT',
        'title' => 'Bachelor of Science in Information Technology',
        'ched_program_code' => 'CHED-IT-001',
        'ched_major' => 'Network and Security',
        'ched_major_code' => 'NETSEC',
        'ched_has_thesis' => false,
        'ched_program_status' => 'CO',
        'ched_year_implemented' => 2018,
        'ched_authority_category' => 'GR',
        'ched_authority_serial' => 'GR-2018-042',
        'ched_authority_year' => 2018,
        'ched_delivery_mode' => 'SE',
        'ched_normal_length_years' => 4.0,
        'ched_program_credit_units' => 140,
        'ched_tuition_per_unit' => 450.00,
        'ched_program_fee' => 5000.00,
        'is_active' => true,
    ]);
    $alternateCourse = Course::factory()->create([
        'school_id' => $school->id,
        'code' => 'BSZZ',
        'title' => 'Alternate Program',
        'is_active' => true,
    ]);

    // Student 1: Fresh male, PWD (physical), solo parent
    $student1 = Student::factory()->create([
        'school_id' => $school->id,
        'course_id' => $course->id,
        'gender' => 'Male',
        'is_pwd' => true,
        'pwd_type' => 'Apparent physical disability',
        'is_solo_parent' => true,
        'is_indigenous_person' => false,
        'status' => StudentStatus::Enrolled->value,
    ]);

    StudentEnrollment::factory()->create([
        'school_id' => $school->id,
        'student_id' => $student1->id,
        'course_id' => $course->id,
        'academic_year' => 1,
        'intake_category' => 'new_freshman',
        'school_year' => '2026-2027',
        'semester' => 1,
    ]);

    // Student 2: 2nd year female, Indigenous person
    $student2 = Student::factory()->create([
        'school_id' => $school->id,
        'course_id' => $course->id,
        'gender' => 'Female',
        'is_indigenous_person' => true,
        'indigenous_group' => 'Aeta',
        'status' => StudentStatus::Enrolled->value,
    ]);

    StudentEnrollment::factory()->create([
        'school_id' => $school->id,
        'student_id' => $student2->id,
        'course_id' => $course->id,
        'academic_year' => 2,
        'school_year' => '2026-2027',
        'semester' => 1,
    ]);

    $student2->update(['course_id' => $alternateCourse->id]);

    // Student 3: 5th year male
    $student3 = Student::factory()->create([
        'school_id' => $school->id,
        'course_id' => $course->id,
        'gender' => 'Male',
        'status' => StudentStatus::Enrolled->value,
        'is_indigenous_person' => false,
        'indigenous_group' => null,
    ]);

    StudentEnrollment::factory()->create([
        'school_id' => $school->id,
        'student_id' => $student3->id,
        'course_id' => $course->id,
        'academic_year' => 5,
        'school_year' => '2026-2027',
        'semester' => 1,
    ]);

    // Student 4: 6th year female
    $student4 = Student::factory()->create([
        'school_id' => $school->id,
        'course_id' => $course->id,
        'gender' => 'Female',
        'status' => StudentStatus::Enrolled->value,
        'is_indigenous_person' => false,
        'indigenous_group' => null,
    ]);

    StudentEnrollment::factory()->create([
        'school_id' => $school->id,
        'student_id' => $student4->id,
        'course_id' => $course->id,
        'academic_year' => 6,
        'school_year' => '2026-2027',
        'semester' => 1,
    ]);

    // Student 5: 7th year male
    $student5 = Student::factory()->create([
        'school_id' => $school->id,
        'course_id' => $course->id,
        'gender' => 'Male',
        'status' => StudentStatus::Enrolled->value,
        'is_indigenous_person' => false,
        'indigenous_group' => null,
    ]);

    StudentEnrollment::factory()->create([
        'school_id' => $school->id,
        'student_id' => $student5->id,
        'course_id' => $course->id,
        'academic_year' => 7,
        'school_year' => '2026-2027',
        'semester' => 1,
    ]);

    // Student 6: Graduated female
    $student6 = Student::factory()->create([
        'school_id' => $school->id,
        'course_id' => $course->id,
        'gender' => 'Female',
        'status' => StudentStatus::Graduated->value,
        'graduation_school_year' => '2026-2027',
        'graduation_semester' => 1,
        'is_indigenous_person' => false,
    ]);

    $service = app(ChedFormBcExportService::class);

    // Test preview data
    $preview = $service->buildPreviewData([
        'school_year' => '2026-2027',
        'semester' => 1,
        'school_id' => $school->id,
    ]);

    expect($preview['type'])->toBe('ched_eform_bc')
        ->and($preview['summary']['total_enrolled'])->toBe(5)
        ->and($preview['summary']['total_graduates'])->toBe(1)
        ->and($preview['sheets'])->toHaveKey('Baccalaureate');

    $spacedPreview = $service->buildPreviewData([
        'school_year' => '2026 - 2027',
        'semester' => 1,
        'school_id' => $school->id,
    ]);

    expect($spacedPreview['summary']['total_enrolled'])->toBe(5)
        ->and($spacedPreview['summary']['total_graduates'])->toBe(1)
        ->and($spacedPreview['subtitle'])->toContain('School Year: 2026 - 2027');

    $baccRow = $preview['sheets']['Baccalaureate'][0];
    expect($baccRow['program_code'])->toBe('CHED-IT-001')
        ->and($baccRow['enrolment']['new_freshmen']['male'])->toBe(1)
        ->and($baccRow['enrolment']['year_2']['female'])->toBe(1)
        ->and($baccRow['enrolment']['year_5'])->toBe(['male' => 1, 'female' => 0])
        ->and($baccRow['enrolment']['year_6'])->toBe(['male' => 0, 'female' => 1])
        ->and($baccRow['enrolment']['year_7'])->toBe(['male' => 1, 'female' => 0])
        ->and($baccRow['graduates']['female'])->toBe(1);

    // Test Excel workbook generation
    $spreadsheet = $service->generate([
        'school_year' => '2026-2027',
        'semester' => 1,
        'school_id' => $school->id,
    ]);

    $baccSheet = $spreadsheet->getSheetByName('Baccalaureate');
    expect($baccSheet)->not->toBeNull()
        ->and($baccSheet->getCell('A10')->getValue())->toBe('Bachelor of Science in Information Technology')
        ->and($baccSheet->getCell('B10')->getValue())->toBe('CHED-IT-001')
        ->and((int) $baccSheet->getCell('R10')->getValue())->toBe(1) // Fresh male
        ->and((int) $baccSheet->getCell('W10')->getValue())->toBe(1) // Year 2 female
        ->and((int) $baccSheet->getCell('AB10')->getValue())->toBe(1) // Year 5 male
        ->and((int) $baccSheet->getCell('AE10')->getValue())->toBe(1) // Year 6 female
        ->and((int) $baccSheet->getCell('AF10')->getValue())->toBe(1) // Year 7 male
        ->and((int) $baccSheet->getCell('AL10')->getValue())->toBe(1); // Graduate female

    // Special Equity Sheet check
    $equitySheet = $spreadsheet->getSheetByName('NEW Special Equity Groups Form ');
    expect($equitySheet)->not->toBeNull()
        ->and($equitySheet->getCell('A10')->getValue())->toBe('Bachelor of Science in Information Technology')
        ->and((int) $equitySheet->getCell('C10')->getValue())->toBe(1) // Apparent physical
        ->and((int) $equitySheet->getCell('M10')->getValue())->toBe(1) // Indigenous
        ->and((int) $equitySheet->getCell('N10')->getValue())->toBe(1); // Solo parent
});

test('administrator can preview and download ched form bc report', function (): void {
    $school = School::factory()->create([
        'name' => 'Baguio Technical College',
        'code' => 'BTC',
        'country_code' => 'PH',
        'curriculum_framework' => null,
        'location' => 'Session Road, Baguio City',
        'phone' => '(074) 444-5389',
        'email' => 'registrar@btc.edu.ph',
    ]);
    SchoolCurriculumCapability::factory()->for($school)->create([
        'curriculum_framework' => CurriculumFramework::ChedPsg,
        'is_enabled' => true,
    ]);
    $user = User::factory()->create([
        'school_id' => $school->id,
        'role' => UserRole::SuperAdmin,
    ]);

    $previewResponse = $this->actingAs($user)
        ->get(route('administrators.registrar.reports.ched.preview', [
            'school_year' => '2026 - 2027',
            'semester' => 1,
        ]));

    $previewResponse->assertOk()
        ->assertJsonPath('type', 'ched_eform_bc')
        ->assertJsonPath('school.name', 'Baguio Technical College')
        ->assertJsonPath('school.code', 'BTC')
        ->assertJsonPath('school.contact', '(074) 444-5389')
        ->assertJsonPath('school.phone', '(074) 444-5389')
        ->assertJsonPath('school.email', 'registrar@btc.edu.ph')
        ->assertJsonPath('school.address', 'Session Road, Baguio City')
        ->assertJsonPath('school.location', 'Session Road, Baguio City')
        ->assertJsonPath('school_year', '2026 - 2027')
        ->assertJsonPath('semester', '1st Semester')
        ->assertJsonPath('semester_label', '1st Semester')
        ->assertJsonPath('semester_value', 1)
        ->assertJsonPath('generated_by', $user->name)
        ->assertJsonStructure(['type', 'title', 'sheets', 'summary', 'school', 'school_year', 'semester', 'generated_at', 'generated_by']);

    expect($previewResponse->json('generated_at'))->toBeString()->not->toBeEmpty();

    $exportResponse = $this->actingAs($user)
        ->get(route('administrators.registrar.reports.ched.export', [
            'school_year' => '2026-2027',
            'semester' => 1,
        ]));

    $exportResponse->assertOk();
});

test('ched form bc preview keeps foreign department and course filters scoped to active school', function (): void {
    $school = School::factory()->create(['country_code' => 'PH']);
    SchoolCurriculumCapability::factory()->for($school)->create([
        'curriculum_framework' => CurriculumFramework::ChedPsg,
        'is_enabled' => true,
    ]);
    $user = User::factory()->create([
        'school_id' => $school->id,
        'role' => UserRole::SuperAdmin,
    ]);

    $foreignSchool = School::factory()->create(['country_code' => 'PH']);
    $foreignDepartment = Department::factory()->forSchool($foreignSchool)->create();
    $foreignCourse = Course::factory()->create([
        'school_id' => $foreignSchool->id,
        'department_id' => $foreignDepartment->id,
        'code' => 'BSFOREIGN',
        'title' => 'Bachelor of Foreign Records',
        'is_active' => true,
    ]);
    $foreignStudent = Student::factory()->create([
        'school_id' => $foreignSchool->id,
        'course_id' => $foreignCourse->id,
        'gender' => 'Male',
        'status' => StudentStatus::Enrolled->value,
    ]);
    StudentEnrollment::factory()->create([
        'school_id' => $foreignSchool->id,
        'student_id' => $foreignStudent->id,
        'course_id' => $foreignCourse->id,
        'academic_year' => 1,
        'intake_category' => 'new_freshman',
        'school_year' => '2026-2027',
        'semester' => 1,
    ]);

    $this->actingAs($user)
        ->getJson(route('administrators.registrar.reports.ched.preview', [
            'school_year' => '2026-2027',
            'semester' => 1,
            'department_filter' => (string) $foreignDepartment->id,
            'course_filter' => (string) $foreignCourse->id,
        ]))
        ->assertOk()
        ->assertJsonPath('summary.total_programs', 0)
        ->assertJsonPath('summary.total_enrolled', 0)
        ->assertJsonPath('summary.total_graduates', 0)
        ->assertJsonMissing(['program_title' => 'Bachelor of Foreign Records']);
});

test('ched form bc routes reject invalid query filters before report generation', function (array $query, array $errors): void {
    $school = School::factory()->create(['country_code' => 'PH']);
    SchoolCurriculumCapability::factory()->for($school)->create([
        'curriculum_framework' => CurriculumFramework::ChedPsg,
        'is_enabled' => true,
    ]);
    $user = User::factory()->create([
        'school_id' => $school->id,
        'role' => UserRole::SuperAdmin,
    ]);

    $this->actingAs($user)
        ->getJson(route('administrators.registrar.reports.ched.preview', $query))
        ->assertUnprocessable()
        ->assertJsonValidationErrors($errors);

    $this->actingAs($user)
        ->getJson(route('administrators.registrar.reports.ched.export', $query))
        ->assertUnprocessable()
        ->assertJsonValidationErrors($errors);
})->with([
    'free text school year' => [['school_year' => 'not-a-school-year'], ['school_year']],
    'concatenated school year digits' => [['school_year' => '20262027'], ['school_year']],
    'unsupported semester' => [['semester' => 4], ['semester']],
    'zero department filter' => [['department_filter' => '0'], ['department_filter']],
    'free text department filter' => [['department_filter' => 'IT'], ['department_filter']],
    'negative course filter' => [['course_filter' => '-1'], ['course_filter']],
    'free text course filter' => [['course_filter' => 'BSIT'], ['course_filter']],
]);

test('ched form bc routes deny philippine schools without ched capability', function (): void {
    $school = School::factory()->create(['country_code' => 'PH']);
    $user = User::factory()->create([
        'school_id' => $school->id,
        'role' => UserRole::SuperAdmin,
    ]);

    $this->actingAs($user)
        ->get(route('administrators.registrar.reports.ched.preview'))
        ->assertForbidden();

    $this->actingAs($user)
        ->get(route('administrators.registrar.reports.ched.export'))
        ->assertForbidden();
});

test('ched form bc routes honor a disabled capability over the legacy framework field', function (): void {
    $school = School::factory()->create([
        'country_code' => 'PH',
        'curriculum_framework' => CurriculumFramework::ChedPsg,
    ]);
    SchoolCurriculumCapability::factory()->for($school)->create([
        'curriculum_framework' => CurriculumFramework::ChedPsg,
        'is_enabled' => false,
    ]);
    $user = User::factory()->create([
        'school_id' => $school->id,
        'role' => UserRole::SuperAdmin,
    ]);

    $this->actingAs($user)
        ->getJson(route('administrators.registrar.reports.ched.preview'))
        ->assertForbidden();
});

test('ched form bc export writes course text as literal strings', function (): void {
    $school = School::factory()->create(['country_code' => 'PH']);
    SchoolCurriculumCapability::factory()->for($school)->create([
        'curriculum_framework' => CurriculumFramework::ChedPsg,
        'is_enabled' => true,
    ]);
    $course = Course::factory()->create([
        'school_id' => $school->id,
        'code' => 'BSX',
        'title' => '=HYPERLINK("https://attacker.example","click")',
        'is_active' => true,
    ]);

    $spreadsheet = app(ChedFormBcExportService::class)->generate([
        'school_year' => '2026-2027',
        'semester' => 1,
        'school_id' => $school->id,
        'course_id' => $course->id,
    ]);
    $cell = $spreadsheet->getSheetByName('Baccalaureate')?->getCell('A10');

    expect($cell)->not->toBeNull()
        ->and($cell?->getValue())->toBe('=HYPERLINK("https://attacker.example","click")')
        ->and($cell?->getDataType())->toBe(DataType::TYPE_STRING);
});

test('ched form bc routes deny non philippine schools even with ched capability', function (): void {
    $school = School::factory()->create(['country_code' => 'US']);
    SchoolCurriculumCapability::factory()->for($school)->create([
        'curriculum_framework' => CurriculumFramework::ChedPsg,
        'is_enabled' => true,
    ]);
    $user = User::factory()->create([
        'school_id' => $school->id,
        'role' => UserRole::SuperAdmin,
    ]);

    $this->actingAs($user)
        ->get(route('administrators.registrar.reports.ched.preview'))
        ->assertForbidden();

    $this->actingAs($user)
        ->get(route('administrators.registrar.reports.ched.export'))
        ->assertForbidden();
});

test('ched form bc routes deny missing school and missing country context', function (): void {
    $school = School::factory()->create(['country_code' => null]);
    SchoolCurriculumCapability::factory()->for($school)->create([
        'curriculum_framework' => CurriculumFramework::ChedPsg,
        'is_enabled' => true,
    ]);
    $userWithMissingCountry = User::factory()->create([
        'school_id' => $school->id,
        'role' => UserRole::SuperAdmin,
    ]);
    $userWithoutSchool = User::factory()->create([
        'school_id' => null,
        'role' => UserRole::SuperAdmin,
    ]);

    $this->actingAs($userWithMissingCountry)
        ->get(route('administrators.registrar.reports.ched.preview'))
        ->assertForbidden();

    $this->actingAs($userWithoutSchool)
        ->get(route('administrators.registrar.reports.ched.preview'))
        ->assertForbidden();
});
