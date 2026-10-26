<?php

declare(strict_types=1);

use App\Jobs\BulkMoveStudentsToSectionJob;
use App\Jobs\GenerateAssessmentPdfJob;
use App\Jobs\GenerateAttendancePdfJob;
use App\Jobs\GenerateEnrollmentReportPreviewPdfJob;
use App\Jobs\GenerateStudentSoaPdfJob;
use App\Jobs\MoveStudentToSectionJob;
use App\Models\Student;
use App\Models\StudentEnrollment;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Schema;

/**
 * Functional defect proof of concept tests (DCC-35 / DB-002).
 *
 * Verifies that:
 * 1. The student_id repair migration runs across every driver (not pgsql-only).
 * 2. student_id is stored as an integer/bigint across all related tables.
 * 3. Performance indexes exist for student_id on all 5 tables and course_id on student_enrollment.
 * 4. studentEnrollments relationship returns an Eloquent relation and joins cleanly.
 */
it('PoC-A · BUG: the pgsql-only student_id type repair never runs on MySQL or SQLite', function (): void {
    $migrationContent = file_get_contents(base_path('database/migrations/2026_10_09_121000_alter_student_enrollment_student_id_to_bigint.php'));
    expect($migrationContent)->not->toContain("if (DB::getDriverName() !== 'pgsql') {");

    $courseMigrationContent = file_get_contents(base_path('database/migrations/2026_10_09_120000_alter_student_enrollment_course_id_to_bigint.php'));
    expect($courseMigrationContent)->not->toContain("if (DB::getDriverName() !== 'pgsql') {");

    // Check column types across all 5 tables
    $studentEnrollmentStudentIdType = Schema::getColumnType('student_enrollment', 'student_id');
    expect($studentEnrollmentStudentIdType)->toBeIn(['bigint', 'integer']);

    $studentEnrollmentCourseIdType = Schema::getColumnType('student_enrollment', 'course_id');
    expect($studentEnrollmentCourseIdType)->toBeIn(['bigint', 'integer']);

    $classEnrollmentsStudentIdType = Schema::getColumnType('class_enrollments', 'student_id');
    expect($classEnrollmentsStudentIdType)->toBeIn(['bigint', 'integer']);

    $subjectEnrollmentsStudentIdType = Schema::getColumnType('subject_enrollments', 'student_id');
    expect($subjectEnrollmentsStudentIdType)->toBeIn(['bigint', 'integer']);

    $studentTuitionStudentIdType = Schema::getColumnType('student_tuition', 'student_id');
    expect($studentTuitionStudentIdType)->toBeIn(['bigint', 'integer']);

    $studentTransactionsStudentIdType = Schema::getColumnType('student_transactions', 'student_id');
    expect($studentTransactionsStudentIdType)->toBeIn(['bigint', 'integer']);

    // Check performance indexes
    expect(Schema::hasIndex('student_enrollment', 'student_enrollment_student_id_index'))->toBeTrue()
        ->and(Schema::hasIndex('student_enrollment', 'student_enrollment_course_id_index'))->toBeTrue()
        ->and(Schema::hasIndex('class_enrollments', 'class_enrollments_student_id_index'))->toBeTrue()
        ->and(Schema::hasIndex('subject_enrollments', 'subject_enrollments_student_id_index'))->toBeTrue()
        ->and(Schema::hasIndex('student_tuition', 'student_tuition_student_id_index'))->toBeTrue()
        ->and(Schema::hasIndex('student_transactions', 'student_transactions_student_id_index'))->toBeTrue();

    // Verify model casts & relationships
    $student = Student::factory()->create();
    $enrollment = StudentEnrollment::factory()->create([
        'student_id' => $student->id,
    ]);

    expect($student->studentEnrollments())->toBeInstanceOf(HasMany::class)
        ->and($enrollment->student_id)->toBeInt()
        ->and(Student::whereHas('studentEnrollments')->whereKey($student->id)->exists())->toBeTrue()
        ->and(StudentEnrollment::whereHas('student')->whereKey($enrollment->id)->exists())->toBeTrue();
});

/**
 * Functional defect proof of concept tests (DCC-36 / PERF-005).
 *
 * Verifies that:
 * 1. The student-transfers and bulk-student-transfers queues have dedicated
 *    consumers configured in Horizon rather than being dropped silently.
 * 2. PDF jobs target the dedicated PDF Redis connection consumed by supervisor-pdf
 *    rather than landing on the default connection where no worker consumes them.
 */
it('PoC-B · BUG: the student-transfers and bulk-student-transfers queues have no consumer', function (): void {
    $singleMove = new MoveStudentToSectionJob(1, 2);
    $bulkMove = new BulkMoveStudentsToSectionJob([1], 2);

    expect($singleMove->queue)->toBe('student-transfers')
        ->and($bulkMove->queue)->toBe('bulk-student-transfers');

    $horizonDefaults = config('horizon.defaults');

    $allConfiguredQueues = collect($horizonDefaults)
        ->flatMap(fn (array $supervisor): array => (array) ($supervisor['queue'] ?? []))
        ->unique()
        ->values()
        ->all();

    expect($allConfiguredQueues)->toContain('student-transfers')
        ->and($allConfiguredQueues)->toContain('bulk-student-transfers')
        ->and($horizonDefaults)->toHaveKey('supervisor-transfers');

    $transferSupervisor = $horizonDefaults['supervisor-transfers'];
    expect($transferSupervisor['connection'])->toBe('redis')
        ->and($transferSupervisor['queue'])->toEqualCanonicalizing(['student-transfers', 'bulk-student-transfers'])
        ->and($transferSupervisor['tries'])->toBeGreaterThanOrEqual(3)
        ->and($transferSupervisor['timeout'])->toBeGreaterThanOrEqual(300);

    expect($horizonDefaults)->toHaveKey('supervisor-assessment');
    $assessmentSupervisor = $horizonDefaults['supervisor-assessment'];
    expect($assessmentSupervisor['connection'])->toBe('assessment-pdf')
        ->and($assessmentSupervisor['queue'])->toEqualCanonicalizing(['assessment-pdf', 'assessment-events'])
        ->and($assessmentSupervisor['timeout'])->toBeGreaterThanOrEqual(1900);
});

it('PoC-B · BUG: PDF jobs target the pdf-generation queue on the wrong Redis connection', function (): void {
    $expectedPdfConnection = (string) config('queue.assessment_notification_connection', config('queue.default'));

    $attendancePdfJob = new GenerateAttendancePdfJob(1, 1);
    $soaPdfJob = new GenerateStudentSoaPdfJob([], 'soa.pdf', 1);
    $enrollmentPdfJob = new GenerateEnrollmentReportPreviewPdfJob([], 'report.pdf', 1);
    $assessmentPdfJob = new GenerateAssessmentPdfJob(1);

    $pdfJobs = [
        'attendance' => $attendancePdfJob,
        'soa' => $soaPdfJob,
        'enrollment_preview' => $enrollmentPdfJob,
        'assessment' => $assessmentPdfJob,
    ];

    foreach ($pdfJobs as $name => $job) {
        expect($job->connection)->not->toBeNull("Job [{$name}] must specify a connection rather than defaulting to null")
            ->and($job->connection)->toBe($expectedPdfConnection, "Job [{$name}] must target the configured PDF connection")
            ->and($job->queue)->toBe('pdf-generation', "Job [{$name}] must target the pdf-generation queue");
    }

    $supervisorPdf = config('horizon.defaults.supervisor-pdf');
    expect($supervisorPdf['connection'])->toBe('redis-pdf')
        ->and($supervisorPdf['queue'])->toContain('pdf-generation');
});
