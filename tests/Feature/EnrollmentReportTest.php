<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Jobs\GenerateEnrollmentReportPreviewPdfJob;
use App\Models\Course;
use App\Models\Department;
use App\Models\GeneralSetting;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\Subject;
use App\Models\SubjectEnrollment;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Schema;

beforeEach(function (): void {
    if (! Schema::hasTable('additional_fees')) {
        Schema::create('additional_fees', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('enrollment_id');
            $table->string('fee_name');
            $table->decimal('amount', 10, 2);
            $table->boolean('is_separate_transaction')->default(false);
            $table->string('transaction_number')->nullable();
            $table->timestamps();
        });
    }

    GeneralSetting::factory()->create([
        'school_starting_date' => '2024-08-01',
        'school_ending_date' => '2025-05-30',
        'semester' => 1,
    ]);
});

it('returns enrolled by course report data', function (): void {
    $user = User::factory()->create(['role' => UserRole::Admin]);

    $course = Course::factory()->create(['code' => 'BSIT', 'department' => 'IT']);
    $student = Student::factory()->create(['course_id' => $course->id]);

    StudentEnrollment::factory()->create([
        'student_id' => $student->id,
        'course_id' => $course->id,
        'school_year' => '2024 - 2025',
        'semester' => 1,
        'academic_year' => 1,
        'status' => 'Pending',
    ]);

    $response = $this->actingAs($user)
        ->get(portalUrlForAdministrators('/administrators/enrollments/reports/data?report_type=enrolled_by_course&course_filter=BSIT&department_filter=all&year_level_filter=all&subject_filter=all&status_filter=active'));

    $response->assertOk()
        ->assertJsonPath('report.type', 'enrolled_by_course')
        ->assertJsonPath('report.total_count', 1)
        ->assertJsonCount(1, 'report.students')
        ->assertJsonPath('report.students.0.course', 'BSIT')
        ->assertJsonStructure([
            'report' => ['type', 'title', 'subtitle', 'total_count', 'students', 'columns'],
            'school' => ['name', 'logo', 'contact', 'email', 'address'],
            'school_year',
            'semester',
            'generated_at',
            'generated_by',
        ]);
});

it('returns enrolled by subject report data', function (): void {
    $user = User::factory()->create(['role' => UserRole::Admin]);

    $course = Course::factory()->create(['code' => 'BSIT', 'department' => 'IT']);
    $student = Student::factory()->create(['course_id' => $course->id]);
    $subject = Subject::factory()->create(['code' => 'IT101', 'title' => 'Intro to IT', 'course_id' => $course->id]);

    $enrollment = StudentEnrollment::factory()->create([
        'student_id' => $student->id,
        'course_id' => $course->id,
        'school_year' => '2024 - 2025',
        'semester' => 1,
        'academic_year' => 1,
        'status' => 'Pending',
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

    $response = $this->actingAs($user)
        ->get(portalUrlForAdministrators('/administrators/enrollments/reports/data?report_type=enrolled_by_subject&subject_filter='.$subject->code.'&course_filter=all&department_filter=all&year_level_filter=all&status_filter=active'));

    $response->assertOk()
        ->assertJsonPath('report.type', 'enrolled_by_subject')
        ->assertJsonPath('report.total_count', 1)
        ->assertJsonCount(1, 'report.subject_groups')
        ->assertJsonPath('report.subject_groups.0.subject_code', 'IT101');
});

it('exports enrolled by course report as excel', function (): void {
    $user = User::factory()->create(['role' => UserRole::Admin]);

    $course = Course::factory()->create(['code' => 'BSIT', 'department' => 'IT']);
    $student = Student::factory()->create(['course_id' => $course->id]);

    StudentEnrollment::factory()->create([
        'student_id' => $student->id,
        'course_id' => $course->id,
        'school_year' => '2024 - 2025',
        'semester' => 1,
        'academic_year' => 1,
        'status' => 'Pending',
    ]);

    $response = $this->actingAs($user)
        ->get(portalUrlForAdministrators('/administrators/enrollments/reports/export?report_type=enrolled_by_course&course_filter=BSIT&department_filter=all&year_level_filter=all&subject_filter=all&status_filter=active'));

    $response->assertOk();
    $response->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
});

it('returns enrollment summary report data', function (): void {
    $user = User::factory()->create(['role' => UserRole::Admin]);

    $bsit = Course::factory()->create(['code' => 'BSIT', 'department' => 'IT']);
    $bshm = Course::factory()->create(['code' => 'BSHM', 'department' => 'HM']);

    $student1 = Student::factory()->create(['course_id' => $bsit->id]);
    $student2 = Student::factory()->create(['course_id' => $bsit->id]);
    $student3 = Student::factory()->create(['course_id' => $bshm->id]);

    StudentEnrollment::factory()->create([
        'student_id' => $student1->id,
        'course_id' => $bsit->id,
        'school_year' => '2024 - 2025',
        'semester' => 1,
        'academic_year' => 1,
        'status' => 'Pending',
    ]);

    StudentEnrollment::factory()->create([
        'student_id' => $student2->id,
        'course_id' => $bsit->id,
        'school_year' => '2024 - 2025',
        'semester' => 1,
        'academic_year' => 2,
        'status' => 'Pending',
    ]);

    StudentEnrollment::factory()->create([
        'student_id' => $student3->id,
        'course_id' => $bshm->id,
        'school_year' => '2024 - 2025',
        'semester' => 1,
        'academic_year' => 1,
        'status' => 'Verified By Cashier',
    ]);

    $response = $this->actingAs($user)
        ->get(portalUrlForAdministrators('/administrators/enrollments/reports/data?report_type=enrollment_summary&course_filter=all&department_filter=all&year_level_filter=all&subject_filter=all&status_filter=active'));

    $response->assertOk()
        ->assertJsonPath('report.type', 'enrollment_summary')
        ->assertJsonPath('report.total_enrolled', 3)
        ->assertJsonCount(2, 'report.by_department')
        ->assertJsonCount(2, 'report.by_course')
        ->assertJsonCount(2, 'report.by_year_level');
});

it('returns available course options for report filters', function (): void {
    $user = User::factory()->create(['role' => UserRole::Admin]);

    $course = Course::factory()->create(['code' => 'BSIT', 'department' => 'IT']);
    $student = Student::factory()->create(['course_id' => $course->id]);

    StudentEnrollment::factory()->create([
        'student_id' => $student->id,
        'course_id' => $course->id,
        'school_year' => '2024 - 2025',
        'semester' => 1,
    ]);

    $response = $this->actingAs($user)
        ->get(portalUrlForAdministrators('/administrators/enrollments/reports/course-options'));

    $response->assertOk()
        ->assertJsonCount(1, 'courses')
        ->assertJsonPath('courses.0.code', 'BSIT');
});

it('returns available subject options for report filters', function (): void {
    $user = User::factory()->create(['role' => UserRole::Admin]);

    $course = Course::factory()->create(['code' => 'BSIT', 'department' => 'IT']);
    $subject = Subject::factory()->create(['code' => 'IT101', 'title' => 'Intro to IT', 'course_id' => $course->id]);
    $class = App\Models\Classes::factory()->create(['subject_id' => $subject->id]);
    $student = Student::factory()->create(['course_id' => $course->id]);

    $enrollment = StudentEnrollment::factory()->create([
        'student_id' => $student->id,
        'course_id' => $course->id,
        'school_year' => '2024 - 2025',
        'semester' => 1,
    ]);

    config(['activitylog.enabled' => false]);

    SubjectEnrollment::query()->create([
        'student_id' => $student->id,
        'subject_id' => $subject->id,
        'class_id' => $class->id,
        'enrollment_id' => $enrollment->id,
        'school_year' => '2024 - 2025',
        'semester' => 1,
        'academic_year' => 1,
    ]);

    $response = $this->actingAs($user)
        ->get(portalUrlForAdministrators('/administrators/enrollments/reports/subject-options'));

    $response->assertOk()
        ->assertJsonCount(1, 'subjects')
        ->assertJsonPath('subjects.0.code', 'IT101');
});

it('validates report type is required', function (): void {
    $user = User::factory()->create(['role' => UserRole::Admin]);

    $response = $this->actingAs($user)
        ->getJson(portalUrlForAdministrators('/administrators/enrollments/reports/data'));

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['report_type']);
});

it('filters enrolled by course report by department', function (): void {
    $user = User::factory()->create(['role' => UserRole::Admin]);

    $itDepartment = Department::factory()->withNameAndCode('Information Technology', 'IT')->create();
    $hmDepartment = Department::factory()->withNameAndCode('Hospitality Management', 'HM')->create();

    $bsit = Course::factory()->create(['code' => 'BSIT', 'department_id' => $itDepartment->id]);
    $bshm = Course::factory()->create(['code' => 'BSHM', 'department_id' => $hmDepartment->id]);

    $student1 = Student::factory()->create(['course_id' => $bsit->id]);
    $student2 = Student::factory()->create(['course_id' => $bshm->id]);

    StudentEnrollment::factory()->create([
        'student_id' => $student1->id,
        'course_id' => $bsit->id,
        'school_year' => '2024 - 2025',
        'semester' => 1,
        'academic_year' => 1,
    ]);

    StudentEnrollment::factory()->create([
        'student_id' => $student2->id,
        'course_id' => $bshm->id,
        'school_year' => '2024 - 2025',
        'semester' => 1,
        'academic_year' => 1,
    ]);

    $response = $this->actingAs($user)
        ->get(portalUrlForAdministrators('/administrators/enrollments/reports/data?report_type=enrolled_by_course&course_filter=all&department_filter=IT&year_level_filter=all&subject_filter=all&status_filter=active'));

    $response->assertOk()
        ->assertJsonPath('report.total_count', 1)
        ->assertJsonPath('report.students.0.department', 'IT');
});

it('returns department analytics by year level without SQL errors', function (): void {
    $user = User::factory()->create(['role' => UserRole::Admin]);

    $itDepartment = Department::factory()->withNameAndCode('Information Technology', 'IT')->create();
    $course = Course::factory()->create(['code' => 'BSIT', 'department_id' => $itDepartment->id]);
    $student = Student::factory()->create(['course_id' => $course->id]);

    StudentEnrollment::factory()->create([
        'student_id' => $student->id,
        'course_id' => $course->id,
        'school_year' => '2024 - 2025',
        'semester' => 1,
        'academic_year' => 1,
        'status' => 'Verified By Cashier',
    ]);

    $response = $this->actingAs($user)
        ->getJson(portalUrlForAdministrators('/administrators/enrollments/api/department-by-year-level?year_level=all'));

    $response->assertOk()
        ->assertJsonStructure([
            'by_department' => [['department', 'count']],
            'year_level',
        ]);
});

it('filters enrolled by course report by course and year level', function (): void {
    $user = User::factory()->create(['role' => UserRole::Admin]);

    $department = Department::factory()->withNameAndCode('Information Technology', 'IT')->create();
    $course = Course::factory()->create(['code' => 'BSIT', 'department_id' => $department->id]);

    $firstYearStudent = Student::factory()->create(['course_id' => $course->id]);
    $secondYearStudent = Student::factory()->create(['course_id' => $course->id]);

    StudentEnrollment::factory()->create([
        'student_id' => $firstYearStudent->id,
        'course_id' => $course->id,
        'school_year' => '2024 - 2025',
        'semester' => 1,
        'academic_year' => 1,
        'status' => 'Verified By Cashier',
    ]);

    StudentEnrollment::factory()->create([
        'student_id' => $secondYearStudent->id,
        'course_id' => $course->id,
        'school_year' => '2024 - 2025',
        'semester' => 1,
        'academic_year' => 2,
        'status' => 'Verified By Cashier',
    ]);

    $response = $this->actingAs($user)
        ->getJson(portalUrlForAdministrators('/administrators/enrollments/reports/data?report_type=enrolled_by_course&course_filter=BSIT&department_filter=all&year_level_filter=1&subject_filter=all&status_filter=active'));

    $response->assertOk()
        ->assertJsonPath('report.total_count', 1)
        ->assertJsonPath('report.students.0.year_level', 1)
        ->assertJsonPath('report.students.0.course', 'BSIT');
});

it('queues enrollment report preview PDF generation', function (): void {
    Bus::fake();

    $user = User::factory()->create(['role' => UserRole::Admin]);

    $department = Department::factory()->withNameAndCode('Information Technology', 'IT')->create();
    $course = Course::factory()->create(['code' => 'BSIT', 'department_id' => $department->id]);
    $student = Student::factory()->create(['course_id' => $course->id]);

    StudentEnrollment::factory()->create([
        'student_id' => $student->id,
        'course_id' => $course->id,
        'school_year' => '2024 - 2025',
        'semester' => 1,
        'academic_year' => 1,
        'status' => 'Verified By Cashier',
    ]);

    $response = $this->actingAs($user)
        ->getJson(portalUrlForAdministrators('/administrators/enrollments/reports/preview-pdf?report_type=enrolled_by_course&course_filter=all&department_filter=all&year_level_filter=all&subject_filter=all&status_filter=active'));

    $response->assertAccepted()
        ->assertJsonPath('message', 'Enrollment report preview queued. You will be notified when the PDF is ready.');

    Bus::assertDispatched(GenerateEnrollmentReportPreviewPdfJob::class);
});
