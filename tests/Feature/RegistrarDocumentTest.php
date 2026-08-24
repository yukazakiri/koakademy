<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\ClassEnrollment;
use App\Models\Classes;
use App\Models\Course;
use App\Models\Department;
use App\Models\GeneralSetting;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\Subject;
use App\Models\SubjectEnrollment;
use App\Models\User;
use Spatie\Permission\Models\Permission;

beforeEach(function (): void {
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
    ]);
});

it('builds a student-facing certificate preview from the selected academic period', function (): void {
    $user = User::factory()->create(['role' => UserRole::Admin]);
    $user->givePermissionTo(['ViewAny:StudentEnrollment', 'View:StudentEnrollment']);
    $department = Department::factory()->withNameAndCode('Information Technology', 'IT')->create();
    $course = Course::factory()->create(['code' => 'BSIT', 'title' => 'Bachelor of Science in Information Technology', 'department_id' => $department->id]);
    $student = Student::factory()->create(['student_id' => 20240001, 'course_id' => $course->id, 'academic_year' => 1]);
    $subject = Subject::factory()->create(['code' => 'IT101', 'title' => 'Introduction to Computing', 'units' => 3, 'course_id' => $course->id]);
    $enrollment = StudentEnrollment::factory()->create([
        'student_id' => $student->id,
        'course_id' => $course->id,
        'school_year' => '2024 - 2025',
        'semester' => 1,
        'academic_year' => 1,
        'status' => 'Verified By Cashier',
    ]);

    config(['activitylog.enabled' => false]);
    SubjectEnrollment::query()->create([
        'student_id' => $student->id,
        'subject_id' => $subject->id,
        'enrollment_id' => $enrollment->id,
        'school_year' => '2024 - 2025',
        'semester' => 1,
        'academic_year' => 1,
    ]);
    $enrollment->delete();

    $response = $this->actingAs($user)->getJson(portalUrlForAdministrators('/administrators/registrar/documents/preview?template=certificate_of_enrollment&variant=verification_letter&student_id='.$student->id.'&purpose=Scholarship'));

    $response->assertOk()
        ->assertJsonPath('template', 'certificate_of_enrollment')
        ->assertJsonPath('variant', 'verification_letter')
        ->assertJsonPath('student.student_number', 20240001)
        ->assertJsonPath('student.course_code', 'BSIT')
        ->assertJsonPath('enrollment.total_units', 3)
        ->assertJsonPath('enrollment.subjects.0.code', 'IT101')
        ->assertJsonPath('purpose', 'Scholarship');
});

it('validates the registrar document template and student', function (): void {
    $user = User::factory()->create(['role' => UserRole::Admin]);
    $user->givePermissionTo(['ViewAny:StudentEnrollment', 'View:StudentEnrollment']);

    $response = $this->actingAs($user)->getJson(portalUrlForAdministrators('/administrators/registrar/documents/preview'));

    $response->assertUnprocessable()->assertJsonValidationErrors(['template', 'student_id']);

    $student = Student::factory()->create();
    $unsupportedVariant = $this->actingAs($user)->getJson(portalUrlForAdministrators('/administrators/registrar/documents/preview?template=certificate_of_enrollment&variant=unknown&student_id='.$student->id));

    $unsupportedVariant->assertUnprocessable()->assertJsonValidationErrors(['variant']);
});

it('requires detailed enrollment permission for student documents', function (): void {
    $user = User::factory()->create(['role' => UserRole::Admin]);
    $user->givePermissionTo('ViewAny:StudentEnrollment');
    $student = Student::factory()->create();

    $this->actingAs($user)
        ->getJson(portalUrlForAdministrators('/administrators/registrar/documents/preview?template=grade_report&student_id='.$student->id))
        ->assertForbidden();
});

it('rejects certificates for students without a completed current enrollment', function (): void {
    $user = User::factory()->create(['role' => UserRole::Admin]);
    $user->givePermissionTo(['ViewAny:StudentEnrollment', 'View:StudentEnrollment']);
    $student = Student::factory()->create();

    StudentEnrollment::factory()->create([
        'student_id' => $student->id,
        'school_year' => '2024 - 2025',
        'semester' => 1,
        'status' => 'Pending',
    ]);

    $this->actingAs($user)
        ->getJson(portalUrlForAdministrators('/administrators/registrar/documents/preview?template=certificate_of_enrollment&student_id='.$student->id))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['student_id']);
});

it('excludes inactive class enrollments from grade reports', function (): void {
    $user = User::factory()->create(['role' => UserRole::Admin]);
    $user->givePermissionTo(['ViewAny:StudentEnrollment', 'View:StudentEnrollment']);
    $course = Course::factory()->create(['code' => 'BSIT']);
    $student = Student::factory()->create(['course_id' => $course->id]);
    $subject = Subject::factory()->create(['code' => 'IT101', 'title' => 'Introduction to Computing', 'units' => 3, 'course_id' => $course->id]);
    $class = Classes::factory()->create([
        'subject_id' => $subject->id,
        'school_year' => '2024 - 2025',
        'semester' => 1,
    ]);

    ClassEnrollment::factory()->create([
        'student_id' => $student->id,
        'class_id' => $class->id,
        'status' => true,
        'total_average' => 2.25,
    ]);
    ClassEnrollment::factory()->create([
        'student_id' => $student->id,
        'class_id' => $class->id,
        'status' => false,
        'total_average' => 1.0,
    ]);

    $this->actingAs($user)
        ->getJson(portalUrlForAdministrators('/administrators/registrar/documents/preview?template=grade_report&variant=official_record&student_id='.$student->id))
        ->assertOk()
        ->assertJsonCount(1, 'grades.subjects')
        ->assertJsonPath('grades.subjects.0.average', 2.25);
});
