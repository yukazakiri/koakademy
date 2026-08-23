<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\Course;
use App\Models\Department;
use App\Models\GeneralSetting;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\User;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\Models\Permission;

beforeEach(function (): void {
    config(['inertia.testing.ensure_pages_exist' => false]);

    Permission::firstOrCreate([
        'name' => 'ViewAny:StudentEnrollment',
        'guard_name' => 'web',
    ]);
    Permission::firstOrCreate([
        'name' => 'View:StudentEnrollment',
        'guard_name' => 'web',
    ]);

    GeneralSetting::factory()->create([
        'school_starting_date' => '2024-08-01',
        'school_ending_date' => '2025-05-30',
        'semester' => 1,
        'more_configs' => [
            'enrollment_pipeline' => [
                'steps' => [
                    [
                        'key' => 'pending',
                        'status' => 'Pending',
                        'label' => 'Pending',
                        'color' => 'amber',
                        'allowed_roles' => ['registrar'],
                        'action_type' => 'standard',
                    ],
                    [
                        'key' => 'completed',
                        'status' => 'Enrolled',
                        'label' => 'Enrolled',
                        'color' => 'green',
                        'allowed_roles' => ['registrar'],
                        'action_type' => 'standard',
                    ],
                ],
                'entry_step_key' => 'pending',
                'completion_step_key' => 'completed',
            ],
        ],
    ]);
});

it('renders dedicated registrar analytics with aggregate enrollment data', function (): void {
    $user = User::factory()->create(['role' => UserRole::Registrar]);
    $user->givePermissionTo('ViewAny:StudentEnrollment');

    $department = Department::factory()->create(['code' => 'IT']);
    $course = Course::factory()->create([
        'code' => 'BSIT',
        'department_id' => $department->id,
        'school_id' => $department->school_id,
    ]);
    $student = Student::factory()->create(['course_id' => $course->id, 'school_id' => $department->school_id]);

    StudentEnrollment::factory()->create([
        'student_id' => $student->id,
        'course_id' => $course->id,
        'school_year' => '2024 - 2025',
        'semester' => 1,
        'academic_year' => 1,
        'status' => 'Enrolled',
        'school_id' => $department->school_id,
    ]);

    $this->actingAs($user)
        ->get(route('administrators.registrar.analytics.index'))
        ->assertSuccessful()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->component('administrators/registrar/analytics', false)
            ->where('analytics.current_semester_count', 1)
            ->where('analytics.active_count', 1)
            ->where('analytics.by_department.0.department', 'IT')
            ->where('analytics.by_year_level.0.year_level', 1)
            ->where('quality.missing_department_count', 0)
            ->where('quality.missing_course_count', 0)
            ->where('report.values.school_year', '2024 - 2025')
            ->where('report.values.semester', 1)
            ->has('analytics.form_bc_matrix')
            ->missing('analytics.detailed_enrollments')
            ->has('generatedAt')
        );
});

it('renders a dedicated registrar reports workspace without changing report endpoints', function (): void {
    $user = User::factory()->create(['role' => UserRole::Registrar]);
    $user->givePermissionTo('ViewAny:StudentEnrollment');

    $this->actingAs($user)
        ->get(route('administrators.registrar.reports.index'))
        ->assertSuccessful()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->component('administrators/registrar/reports', false)
            ->has('filters.currentSemester')
            ->has('filters.currentSchoolYear')
            ->has('assessment_export_options.student_limits')
        );

    expect(route('administrators.enrollments.reports.data'))->toContain('/administrators/enrollments/reports/data')
        ->and(route('administrators.enrollments.reports.preview-pdf'))->toContain('/administrators/enrollments/reports/preview-pdf')
        ->and(route('administrators.enrollments.reports.export'))->toContain('/administrators/enrollments/reports/export');
});

it('forbids registrar insights without enrollment visibility permission', function (): void {
    $user = User::factory()->create(['role' => UserRole::Admin]);

    $this->actingAs($user)
        ->get(route('administrators.registrar.analytics.index'))
        ->assertForbidden();

    $this->actingAs($user)
        ->get(route('administrators.registrar.reports.index'))
        ->assertForbidden();

    $this->actingAs($user)
        ->get(route('administrators.registrar.analytics.export'))
        ->assertForbidden();
});
it('exports registrar analytics as an excel workbook', function (): void {
    $user = User::factory()->create(['role' => UserRole::Registrar]);
    $user->givePermissionTo('ViewAny:StudentEnrollment');

    $department = Department::factory()->create(['code' => 'IT']);
    $course = Course::factory()->create([
        'code' => 'BSIT',
        'department_id' => $department->id,
        'school_id' => $department->school_id,
    ]);
    $student = Student::factory()->create([
        'course_id' => $course->id,
        'gender' => 'Male',
        'student_type' => 'college',
        'school_id' => $department->school_id,
    ]);

    StudentEnrollment::factory()->create([
        'student_id' => $student->id,
        'course_id' => $course->id,
        'school_year' => '2024 - 2025',
        'semester' => 1,
        'academic_year' => 1,
        'intake_category' => 'new_freshman',
        'status' => 'Enrolled',
        'school_id' => $department->school_id,
    ]);

    $this->actingAs($user)
        ->get(route('administrators.registrar.analytics.export'))
        ->assertForbidden();

    $user->givePermissionTo('View:StudentEnrollment');

    $this->actingAs($user)
        ->get(route('administrators.registrar.analytics.export'))
        ->assertSuccessful()
        ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
});

it('builds the Form B/C matrix and applies shared report filters', function (): void {
    $user = User::factory()->create(['role' => UserRole::Registrar]);
    $user->givePermissionTo('ViewAny:StudentEnrollment');

    $it = Department::factory()->create(['code' => 'IT']);
    $courseBsit = Course::factory()->create(['code' => 'BSIT', 'department_id' => $it->id, 'school_id' => $it->school_id]);
    $courseBscs = Course::factory()->create(['code' => 'BSCS', 'department_id' => $it->id, 'school_id' => $it->school_id]);

    $makeStudent = fn (Course $course, string $gender, int $year, ?string $intakeCategory = null) => tap(
        Student::factory()->create(['course_id' => $course->id, 'gender' => $gender, 'school_id' => $it->school_id]),
        fn (Student $s) => StudentEnrollment::factory()->create([
            'student_id' => $s->id,
            'course_id' => $course->id,
            'school_year' => '2024 - 2025',
            'semester' => 1,
            'academic_year' => $year,
            'intake_category' => $intakeCategory,
            'status' => 'Enrolled',
            'school_id' => $it->school_id,
        ])
    );

    $makeStudent($courseBsit, 'Male', 1, 'new_freshman');
    $makeStudent($courseBscs, 'Male', 2);
    $makeStudent($courseBsit, 'Female', 1);

    $this->actingAs($user)
        ->get(route('administrators.registrar.analytics.index', ['course_id' => $courseBsit->id, 'gender' => 'male']))
        ->assertSuccessful()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('analytics.current_semester_count', 1)
            ->has('analytics.form_bc_matrix', 1)
            ->where('analytics.form_bc_matrix.0.program_code', 'BSIT')
            ->where('analytics.form_bc_matrix.0.new_freshman_male', 1)
            ->where('analytics.form_bc_matrix.0.total', 1)
        );
});

it('includes pending enrollments when pending is the explicit report status filter', function (): void {
    $user = User::factory()->create(['role' => UserRole::Registrar]);
    $user->givePermissionTo('ViewAny:StudentEnrollment');

    $department = Department::factory()->create(['code' => 'IT']);
    $course = Course::factory()->create(['code' => 'BSIT', 'department_id' => $department->id, 'school_id' => $department->school_id]);
    $student = Student::factory()->create(['course_id' => $course->id, 'gender' => 'Female', 'school_id' => $department->school_id]);

    StudentEnrollment::factory()->create([
        'student_id' => $student->id,
        'course_id' => $course->id,
        'school_year' => '2024 - 2025',
        'semester' => 1,
        'academic_year' => 1,
        'intake_category' => 'new_freshman',
        'status' => 'Pending',
        'school_id' => $department->school_id,
    ]);

    $this->actingAs($user)
        ->get(route('administrators.registrar.analytics.index'))
        ->assertSuccessful()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('analytics.current_semester_count', 0)
        );

    $this->actingAs($user)
        ->get(route('administrators.registrar.analytics.index', ['status' => 'Pending']))
        ->assertSuccessful()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('analytics.current_semester_count', 1)
            ->where('analytics.by_status.0.status', 'Pending')
            ->where('analytics.form_bc_matrix.0.new_freshman_female', 1)
        );
});

it('rejects invalid Form B/C report filter values', function (): void {
    $user = User::factory()->create(['role' => UserRole::Registrar]);
    $user->givePermissionTo('ViewAny:StudentEnrollment');

    $this->actingAs($user)
        ->get(route('administrators.registrar.analytics.index', ['semester' => 9]))
        ->assertSessionHasErrors('semester');
});
