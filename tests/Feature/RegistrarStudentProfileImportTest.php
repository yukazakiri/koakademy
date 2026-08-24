<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Exports\RegistrarAnalyticsExport;
use App\Models\Course;
use App\Models\Department;
use App\Models\GeneralSetting;
use App\Models\RegistrarStudentProfileImport;
use App\Models\School;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\User;
use App\Services\RegistrarAnalyticsService;
use App\Services\TenantContext;
use Illuminate\Http\UploadedFile;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Spatie\Permission\Models\Permission;

beforeEach(function (): void {
    foreach ([
        'ViewAny:StudentEnrollment',
        'View:StudentEnrollment',
        'Update:StudentEnrollment',
        'ViewAny:Student',
        'Update:Student',
    ] as $permission) {
        Permission::findOrCreate($permission, 'web');
    }

    GeneralSetting::factory()->create([
        'school_starting_date' => '2024-08-01',
        'school_ending_date' => '2025-05-30',
        'semester' => 1,
    ]);
});

function registrarProfileImportUser(School $school): User
{
    $user = User::factory()->create([
        'role' => UserRole::Registrar,
        'school_id' => $school->id,
    ]);
    $user->givePermissionTo([
        'ViewAny:StudentEnrollment',
        'View:StudentEnrollment',
        'Update:StudentEnrollment',
        'ViewAny:Student',
        'Update:Student',
    ]);

    return $user;
}

/** @return array{school: School, course: Course, student: Student, enrollment: StudentEnrollment} */
function registrarProfileImportEnrollment(array $studentAttributes = [], array $enrollmentAttributes = []): array
{
    $department = Department::factory()->create(['code' => 'IT']);
    $school = $department->school;
    $course = Course::factory()->create([
        'code' => 'BSIT',
        'title' => 'Bachelor of Science in Information Technology',
        'department_id' => $department->id,
        'school_id' => $school->id,
    ]);
    $student = Student::factory()->create([
        'course_id' => $course->id,
        'school_id' => $school->id,
        ...$studentAttributes,
    ]);
    $enrollment = StudentEnrollment::factory()->create([
        'student_id' => $student->id,
        'course_id' => $course->id,
        'school_year' => '2024 - 2025',
        'semester' => 1,
        'academic_year' => 1,
        'intake_category' => null,
        'status' => 'Enrolled',
        'school_id' => $school->id,
        ...$enrollmentAttributes,
    ]);

    return compact('school', 'course', 'student', 'enrollment');
}

/** @param array<string, array<string, scalar|null>> $editsByStudentNumber */
function registrarProfileWorkbookUpload(School $school, array $editsByStudentNumber): UploadedFile
{
    app(TenantContext::class)->setCurrentSchool($school);
    $report = app(RegistrarAnalyticsService::class)->build([], includeDetails: true);
    $path = tempnam(sys_get_temp_dir(), 'registrar-profile-import-');
    file_put_contents(
        $path,
        Excel::raw(
            new RegistrarAnalyticsExport(
                $report['analytics'],
                $report['report'],
                $school->id,
                '2026-08-24T12:00:00+08:00',
            ),
            Maatwebsite\Excel\Excel::XLSX,
        ),
    );

    $workbook = IOFactory::load($path);
    $details = $workbook->getSheetByName('Enrollment Details');
    expect($details)->not->toBeNull();

    $headingColumns = [];
    $lastColumn = Coordinate::columnIndexFromString($details->getHighestDataColumn());
    for ($column = 1; $column <= $lastColumn; $column++) {
        $headingColumns[(string) $details->getCell([$column, 3])->getValue()] = $column;
    }

    for ($row = 4; $row <= $details->getHighestDataRow(); $row++) {
        $studentNumber = (string) $details->getCell([2, $row])->getValue();
        foreach ($editsByStudentNumber[$studentNumber] ?? [] as $heading => $value) {
            expect($headingColumns)->toHaveKey($heading);
            $details->setCellValue([$headingColumns[$heading], $row], $value);
        }
    }

    (new Xlsx($workbook))->save($path);
    $workbook->disconnectWorksheets();

    return new UploadedFile(
        $path,
        'registrar-student-information.xlsx',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        null,
        true,
    );
}

it('stages and confirms profile fields and first-year classification from a signed export', function (): void {
    ['school' => $school, 'student' => $student, 'enrollment' => $enrollment] = registrarProfileImportEnrollment([
        'email' => 'old-email@example.test',
        'phone' => '09170000000',
        'religion' => 'Roman Catholic',
    ]);
    $user = registrarProfileImportUser($school);
    $this->actingAs($user);
    $upload = registrarProfileWorkbookUpload($school, [
        (string) $student->student_id => [
            'Email' => 'updated-email@example.test',
            "Father's Name" => 'Andres Example',
            'First-year Intake Classification' => 'New freshman',
            'Religion' => '',
        ],
    ]);

    $stageResponse = $this->post(
        route('administrators.registrar.analytics.student-profile-imports.store'),
        ['file' => $upload],
        ['Accept' => 'application/json'],
    )->assertCreated()
        ->assertJsonPath('import.summary.ready_students', 1)
        ->assertJsonPath('import.summary.invalid_students', 0)
        ->assertJsonPath('import.summary.changed_fields', 3)
        ->assertJsonPath('import.students.0.intake_category', 'new_freshman');

    expect($student->refresh()->email)->toBe('old-email@example.test')
        ->and($enrollment->refresh()->intake_category)->toBeNull();

    $importId = $stageResponse->json('import.id');
    $this->postJson(
        route('administrators.registrar.analytics.student-profile-imports.confirm', $importId),
        ['student_ids' => [$student->id]],
    )->assertOk()
        ->assertJsonPath('import.status', 'completed')
        ->assertJsonPath('import.summary.applied_students', 1)
        ->assertJsonPath('import.students.0.status', 'applied');

    $updatedStudent = $student->refresh();
    expect($updatedStudent->email)->toBe('updated-email@example.test')
        ->and($updatedStudent->phone)->toBe('09170000000')
        ->and($updatedStudent->religion)->toBe('Roman Catholic')
        ->and(data_get($updatedStudent->contacts, 'parents.father_name'))->toBe('Andres Example')
        ->and($enrollment->refresh()->intake_category)->toBe('new_freshman');
});

it('applies selected valid students while preserving invalid records', function (): void {
    ['school' => $school, 'course' => $course, 'student' => $validStudent] = registrarProfileImportEnrollment([
        'email' => 'valid-old@example.test',
        'gender' => 'other',
    ]);
    $invalidStudent = Student::factory()->create([
        'course_id' => $course->id,
        'school_id' => $school->id,
        'email' => 'invalid-old@example.test',
    ]);
    StudentEnrollment::factory()->create([
        'student_id' => $invalidStudent->id,
        'course_id' => $course->id,
        'school_year' => '2024 - 2025',
        'semester' => 1,
        'academic_year' => 1,
        'status' => 'Enrolled',
        'school_id' => $school->id,
    ]);
    $user = registrarProfileImportUser($school);
    $this->actingAs($user);
    $upload = registrarProfileWorkbookUpload($school, [
        (string) $validStudent->student_id => ['Email' => 'valid-new@example.test'],
        (string) $invalidStudent->student_id => ['Email' => 'not-an-email'],
    ]);

    $stageResponse = $this->post(
        route('administrators.registrar.analytics.student-profile-imports.store'),
        ['file' => $upload],
        ['Accept' => 'application/json'],
    )->assertCreated()
        ->assertJsonPath('import.summary.ready_students', 1)
        ->assertJsonPath('import.summary.invalid_students', 1);

    $this->postJson(
        route('administrators.registrar.analytics.student-profile-imports.confirm', $stageResponse->json('import.id')),
        ['student_ids' => [$validStudent->id]],
    )->assertOk()
        ->assertJsonPath('import.summary.applied_students', 1);

    expect($validStudent->refresh()->email)->toBe('valid-new@example.test')
        ->and($invalidStudent->refresh()->email)->toBe('invalid-old@example.test');
});

it('rejects a tampered protected baseline and a workbook from another school', function (): void {
    ['school' => $school, 'student' => $student] = registrarProfileImportEnrollment();
    $user = registrarProfileImportUser($school);
    $this->actingAs($user);
    $upload = registrarProfileWorkbookUpload($school, [
        (string) $student->student_id => ['Email' => 'changed@example.test'],
    ]);
    $path = $upload->getRealPath();
    $workbook = IOFactory::load($path);
    $workbook->getSheetByName('Import Baseline')?->setCellValue('I2', 'tampered-signature');
    (new Xlsx($workbook))->save($path);
    $workbook->disconnectWorksheets();

    $this->post(
        route('administrators.registrar.analytics.student-profile-imports.store'),
        ['file' => $upload],
        ['Accept' => 'application/json'],
    )->assertUnprocessable()
        ->assertJsonValidationErrors('file');

    $otherDepartment = Department::factory()->create(['code' => 'BUS']);
    $otherSchool = $otherDepartment->school;
    $otherUser = registrarProfileImportUser($otherSchool);
    $this->actingAs($otherUser);
    $foreignUpload = registrarProfileWorkbookUpload($school, [
        (string) $student->student_id => ['Email' => 'foreign@example.test'],
    ]);
    app(TenantContext::class)->setCurrentSchool($otherSchool);

    $this->post(
        route('administrators.registrar.analytics.student-profile-imports.store'),
        ['file' => $foreignUpload],
        ['Accept' => 'application/json'],
    )->assertUnprocessable()
        ->assertJsonValidationErrors('file');

    expect(RegistrarStudentProfileImport::query()->withoutSchoolScope()->count())->toBe(0);
});

it('skips a student whose profile changes after preview and prevents repeat confirmation', function (): void {
    ['school' => $school, 'student' => $student] = registrarProfileImportEnrollment([
        'email' => 'before-preview@example.test',
    ]);
    $user = registrarProfileImportUser($school);
    $this->actingAs($user);
    $upload = registrarProfileWorkbookUpload($school, [
        (string) $student->student_id => ['Email' => 'from-workbook@example.test'],
    ]);
    $stageResponse = $this->post(
        route('administrators.registrar.analytics.student-profile-imports.store'),
        ['file' => $upload],
        ['Accept' => 'application/json'],
    )->assertCreated()
        ->assertJsonPath('import.summary.ready_students', 1)
        ->assertJsonPath('import.students.0.student_id', $student->id);
    $importId = $stageResponse->json('import.id');

    $otherRegistrar = registrarProfileImportUser($school);
    $this->actingAs($otherRegistrar);
    app(TenantContext::class)->setCurrentSchool($school);
    $this->postJson(
        route('administrators.registrar.analytics.student-profile-imports.confirm', $importId),
        ['student_ids' => [$student->id]],
    )->assertNotFound();

    $this->actingAs($user);
    app(TenantContext::class)->setCurrentSchool($school);
    $student->update(['email' => 'changed-in-system@example.test']);
    $stagedImport = RegistrarStudentProfileImport::query()->where('public_id', $importId)->firstOrFail();
    expect($stagedImport->rows()->where('status', 'ready')->where('student_id', $student->id)->count())->toBe(1);

    $this->postJson(
        route('administrators.registrar.analytics.student-profile-imports.confirm', $importId),
        ['student_ids' => [$student->id]],
    )->assertOk()
        ->assertJsonPath('import.summary.applied_students', 0)
        ->assertJsonPath('import.summary.skipped_students', 1)
        ->assertJsonPath('import.students.0.status', 'skipped');

    expect($student->refresh()->email)->toBe('changed-in-system@example.test');

    $this->postJson(
        route('administrators.registrar.analytics.student-profile-imports.confirm', $importId),
        ['student_ids' => [$student->id]],
    )->assertUnprocessable();
});

it('requires update permissions before staging a workbook', function (): void {
    ['school' => $school, 'student' => $student] = registrarProfileImportEnrollment();
    $user = User::factory()->create(['role' => UserRole::Registrar, 'school_id' => $school->id]);
    $user->givePermissionTo(['ViewAny:StudentEnrollment', 'View:StudentEnrollment']);
    $this->actingAs($user);
    $upload = registrarProfileWorkbookUpload($school, [
        (string) $student->student_id => ['Email' => 'unauthorized@example.test'],
    ]);

    $this->post(
        route('administrators.registrar.analytics.student-profile-imports.store'),
        ['file' => $upload],
        ['Accept' => 'application/json'],
    )->assertForbidden();
});
