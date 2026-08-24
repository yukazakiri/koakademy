<?php

declare(strict_types=1);

use App\Contracts\AssessmentFormPdfRenderer;
use App\Enums\UserRole;
use App\Events\AssessmentExportProgressed;
use App\Jobs\GenerateBulkAssessmentItemJob;
use App\Jobs\GenerateBulkAssessmentsJob;
use App\Jobs\MergeBulkAssessmentExportJob;
use App\Models\AssessmentExport;
use App\Models\AssessmentExportItem;
use App\Models\Course;
use App\Models\School;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\User;
use App\Services\AssessmentExportArtifactService;
use App\Services\AssessmentExportCoordinator;
use App\Services\AssessmentExportNotificationService;
use App\Services\EnrollmentPipelineService;
use App\Services\TenantContext;
use Illuminate\Bus\PendingBatch;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use setasign\Fpdi\Fpdi;

function assessmentExportSchoolContext(School $school): void
{
    app(TenantContext::class)->setCurrentSchoolId($school->id);
}

function createTrackedAssessmentExport(User $user, School $school, array $attributes = []): AssessmentExport
{
    return AssessmentExport::withoutSchoolScope()->create([
        'user_id' => $user->id,
        'school_id' => $school->id,
        'status' => 'processing',
        'stage' => 'rendering',
        'filters' => [
            'course_id' => null,
            'year_level' => null,
            'student_limit' => null,
            'include_deleted' => false,
            'semester' => 1,
            'school_year' => '2024 - 2025',
        ],
        'message' => 'Rendering assessments...',
        ...$attributes,
    ]);
}

function storeAssessmentFixturePdf(string $disk, string $path, string $label): array
{
    $temporary = tempnam(sys_get_temp_dir(), 'assessment_export_test_');
    if ($temporary === false) {
        throw new RuntimeException('Unable to allocate test PDF path.');
    }
    $localPath = $temporary.'.pdf';
    rename($temporary, $localPath);
    $pdf = new FPDF('L', 'mm', 'A4');
    $pdf->AddPage();
    $pdf->SetFont('Arial', '', 16);
    $pdf->Cell(0, 10, $label);
    $pdf->Output($localPath, 'F');

    try {
        return app(AssessmentExportArtifactService::class)->storeValidatedPdf($disk, $path, $localPath);
    } finally {
        @unlink($localPath);
    }
}

it('registers a durable tenant-scoped export before dispatching preparation', function (): void {
    Queue::fake();
    $school = School::factory()->create();
    assessmentExportSchoolContext($school);
    $user = User::factory()->create(['role' => UserRole::Admin, 'school_id' => $school->id]);
    $course = Course::factory()->create(['school_id' => $school->id]);

    $response = $this->actingAs($user)->postJson(
        portalUrlForAdministrators('/administrators/enrollments/reports/bulk-assessments'),
        ['course_id' => $course->id, 'year_level' => 2, 'student_limit' => 25, 'include_deleted' => false],
    );

    $response->assertAccepted()
        ->assertJsonPath('status', 'pending')
        ->assertJsonPath('stage', 'queued')
        ->assertJsonPath('metadata.filters.course_id', $course->id)
        ->assertJsonStructure(['id', 'status', 'stage', 'message', 'counts', 'actions']);

    $export = AssessmentExport::withoutSchoolScope()->findOrFail((string) $response->json('id'));
    expect($export->user_id)->toBe($user->id)
        ->and($export->school_id)->toBe($school->id)
        ->and($export->filters['year_level'])->toBe(2);
    Queue::assertPushed(GenerateBulkAssessmentsJob::class, fn (GenerateBulkAssessmentsJob $job): bool => $job->exportId === $export->id
        && $job->connection === 'assessment-pdf'
        && $job->queue === 'assessment-pdf');
});

it('rejects courses outside the active school and concurrent exports', function (): void {
    Queue::fake();
    $school = School::factory()->create();
    $otherSchool = School::factory()->create();
    assessmentExportSchoolContext($school);
    $user = User::factory()->create(['role' => UserRole::Admin, 'school_id' => $school->id]);
    $foreignCourse = Course::withoutSchoolScope()->create(Course::factory()->raw(['school_id' => $otherSchool->id]));

    $this->actingAs($user)->postJson(
        portalUrlForAdministrators('/administrators/enrollments/reports/bulk-assessments'),
        ['course_id' => $foreignCourse->id, 'year_level' => null, 'student_limit' => null, 'include_deleted' => false],
    )->assertUnprocessable()->assertJsonValidationErrors('course_id');

    createTrackedAssessmentExport($user, $school, ['status' => 'pending', 'stage' => 'queued']);
    $this->postJson(
        portalUrlForAdministrators('/administrators/enrollments/reports/bulk-assessments'),
        ['course_id' => null, 'year_level' => null, 'student_limit' => null, 'include_deleted' => false],
    )->assertConflict();
});

it('snapshots matching enrollments in deterministic order and dispatches one item per assessment', function (): void {
    Bus::fake();
    Event::fake([AssessmentExportProgressed::class]);
    $school = School::factory()->create();
    assessmentExportSchoolContext($school);
    $user = User::factory()->create(['role' => UserRole::Admin, 'school_id' => $school->id]);
    $courseB = Course::factory()->create(['school_id' => $school->id, 'code' => 'ZZZ']);
    $courseA = Course::factory()->create(['school_id' => $school->id, 'code' => 'AAA']);
    $studentZulu = Student::factory()->create(['id' => 1001, 'school_id' => $school->id, 'course_id' => $courseA->id, 'last_name' => 'Zulu']);
    $studentAlpha = Student::factory()->create(['id' => 1002, 'school_id' => $school->id, 'course_id' => $courseA->id, 'last_name' => 'Alpha']);
    $studentCourseB = Student::factory()->create(['id' => 1003, 'school_id' => $school->id, 'course_id' => $courseB->id, 'last_name' => 'Able']);
    $status = app(EnrollmentPipelineService::class)->getCashierVerifiedStatus();
    $orderedEnrollments = [
        StudentEnrollment::factory()->create(['school_id' => $school->id, 'student_id' => $studentAlpha->id, 'course_id' => $courseA->id, 'academic_year' => 2, 'status' => $status, 'semester' => 1, 'school_year' => '2024 - 2025']),
        StudentEnrollment::factory()->create(['school_id' => $school->id, 'student_id' => $studentZulu->id, 'course_id' => $courseA->id, 'academic_year' => 2, 'status' => $status, 'semester' => 1, 'school_year' => '2024 - 2025']),
        StudentEnrollment::factory()->create(['school_id' => $school->id, 'student_id' => $studentCourseB->id, 'course_id' => $courseB->id, 'academic_year' => 2, 'status' => $status, 'semester' => 1, 'school_year' => '2024 - 2025']),
    ];
    $export = createTrackedAssessmentExport($user, $school, ['status' => 'pending', 'stage' => 'queued']);

    (new GenerateBulkAssessmentsJob($export->id))->handle(
        app(EnrollmentPipelineService::class),
        app(AssessmentExportCoordinator::class),
        app(AssessmentExportNotificationService::class),
    );

    expect($export->items()->orderBy('sequence')->pluck('enrollment_id')->all())
        ->toBe(array_map(fn (StudentEnrollment $enrollment): int => $enrollment->id, $orderedEnrollments));
    Bus::assertBatched(fn (PendingBatch $batch): bool => $batch->connection() === 'assessment-pdf'
        && $batch->queue() === 'assessment-pdf'
        && count($batch->jobs) === 3
        && collect($batch->jobs)->every(fn ($job): bool => $job instanceof GenerateBulkAssessmentItemJob));
});

it('limits preparation to the selected course', function (): void {
    Bus::fake();
    Event::fake([AssessmentExportProgressed::class]);
    $school = School::factory()->create();
    assessmentExportSchoolContext($school);
    $user = User::factory()->create(['role' => UserRole::Admin, 'school_id' => $school->id]);
    $courseA = Course::factory()->create(['school_id' => $school->id, 'code' => 'AAA']);
    $courseB = Course::factory()->create(['school_id' => $school->id, 'code' => 'ZZZ']);
    $status = app(EnrollmentPipelineService::class)->getCashierVerifiedStatus();
    $studentA = Student::factory()->create(['school_id' => $school->id, 'course_id' => $courseA->id]);
    $studentB = Student::factory()->create(['school_id' => $school->id, 'course_id' => $courseB->id]);
    $enrollmentA = StudentEnrollment::factory()->create([
        'school_id' => $school->id,
        'student_id' => $studentA->id,
        'course_id' => $courseA->id,
        'status' => $status,
        'semester' => 1,
        'school_year' => '2024 - 2025',
    ]);
    StudentEnrollment::factory()->create([
        'school_id' => $school->id,
        'student_id' => $studentB->id,
        'course_id' => $courseB->id,
        'status' => $status,
        'semester' => 1,
        'school_year' => '2024 - 2025',
    ]);
    $export = createTrackedAssessmentExport($user, $school, [
        'status' => 'pending',
        'stage' => 'queued',
        'filters' => [
            'course_id' => $courseA->id,
            'year_level' => null,
            'student_limit' => null,
            'include_deleted' => false,
            'semester' => 1,
            'school_year' => '2024 - 2025',
        ],
    ]);

    (new GenerateBulkAssessmentsJob($export->id))->handle(
        app(EnrollmentPipelineService::class),
        app(AssessmentExportCoordinator::class),
        app(AssessmentExportNotificationService::class),
    );

    expect($export->items()->orderBy('sequence')->pluck('enrollment_id')->all())
        ->toBe([$enrollmentA->id]);
    Bus::assertBatched(fn (PendingBatch $batch): bool => count($batch->jobs) === 1);
});

it('merges every validated item into one page-verified PDF', function (): void {
    Event::fake([AssessmentExportProgressed::class]);
    config()->set('assessment-exports.disk', 'local');
    Storage::fake('local');
    $school = School::factory()->create();
    assessmentExportSchoolContext($school);
    $user = User::factory()->create(['role' => UserRole::Admin, 'school_id' => $school->id]);
    $export = createTrackedAssessmentExport($user, $school, [
        'stage' => 'merging', 'total_count' => 2, 'processed_count' => 2, 'completed_count' => 2, 'percentage' => 85,
    ]);

    foreach ([1 => 'FIRST ASSESSMENT', 2 => 'SECOND ASSESSMENT'] as $sequence => $label) {
        $path = sprintf('assessment-exports/%d/%d/%s/items/%06d.pdf', $school->id, $user->id, $export->id, $sequence);
        $metadata = storeAssessmentFixturePdf('local', $path, $label);
        AssessmentExportItem::query()->create([
            'assessment_export_id' => $export->id,
            'school_id' => $school->id,
            'sequence' => $sequence,
            'status' => 'completed',
            'attempts' => 1,
            'artifact_disk' => 'local',
            'artifact_path' => $path,
            'page_count' => $metadata['page_count'],
            'byte_size' => $metadata['byte_size'],
            'checksum' => $metadata['checksum'],
            'completed_at' => now(),
        ]);
    }

    (new MergeBulkAssessmentExportJob($export->id))->handle(
        app(App\Services\PdfGenerationService::class),
        app(AssessmentExportArtifactService::class),
        app(AssessmentExportCoordinator::class),
        app(AssessmentExportNotificationService::class),
    );

    $export->refresh();
    expect($export->status)->toBe('completed')
        ->and($export->stage)->toBe('ready')
        ->and($export->output_path)->not->toBeNull()
        ->and((new Fpdi)->setSourceFile(Storage::disk('local')->path($export->output_path)))->toBe(2);
});

it('renders an item idempotently and advances exact student progress once', function (): void {
    Queue::fake();
    Event::fake([AssessmentExportProgressed::class]);
    config()->set('assessment-exports.disk', 'local');
    Storage::fake('local');
    $school = School::factory()->create();
    assessmentExportSchoolContext($school);
    $user = User::factory()->create(['role' => UserRole::Admin, 'school_id' => $school->id]);
    $course = Course::factory()->create(['school_id' => $school->id]);
    $student = Student::factory()->create(['school_id' => $school->id, 'course_id' => $course->id]);
    $enrollment = StudentEnrollment::factory()->create([
        'school_id' => $school->id,
        'student_id' => $student->id,
        'course_id' => $course->id,
        'status' => app(EnrollmentPipelineService::class)->getCashierVerifiedStatus(),
    ]);
    $export = createTrackedAssessmentExport($user, $school, ['total_count' => 1]);
    $item = AssessmentExportItem::query()->create([
        'assessment_export_id' => $export->id,
        'school_id' => $school->id,
        'enrollment_id' => $enrollment->id,
        'sequence' => 1,
        'status' => 'pending',
    ]);
    $renderer = new class implements AssessmentFormPdfRenderer
    {
        public function render(StudentEnrollment $enrollment, string $outputPath): void
        {
            $pdf = new FPDF('L', 'mm', 'A4');
            $pdf->AddPage();
            $pdf->SetFont('Arial', '', 12);
            $pdf->Cell(0, 10, 'ASSESSMENT '.$enrollment->id);
            $pdf->Output($outputPath, 'F');
        }
    };
    $job = new GenerateBulkAssessmentItemJob($item->id);

    $job->handle($renderer, app(AssessmentExportArtifactService::class), app(AssessmentExportCoordinator::class));
    $job->handle($renderer, app(AssessmentExportArtifactService::class), app(AssessmentExportCoordinator::class));

    expect($item->refresh()->status)->toBe('completed')
        ->and($item->attempts)->toBe(1)
        ->and($export->refresh()->completed_count)->toBe(1)
        ->and($export->processed_count)->toBe(1)
        ->and($export->stage)->toBe('merging');
    Queue::assertPushed(MergeBulkAssessmentExportJob::class, 1);
});

it('never publishes an export when an item artifact is missing', function (): void {
    Event::fake([AssessmentExportProgressed::class]);
    config()->set('assessment-exports.disk', 'local');
    Storage::fake('local');
    $school = School::factory()->create();
    assessmentExportSchoolContext($school);
    $user = User::factory()->create(['role' => UserRole::Admin, 'school_id' => $school->id]);
    $export = createTrackedAssessmentExport($user, $school, [
        'stage' => 'merging', 'total_count' => 1, 'processed_count' => 1, 'completed_count' => 1, 'percentage' => 85,
    ]);
    AssessmentExportItem::query()->create([
        'assessment_export_id' => $export->id,
        'school_id' => $school->id,
        'sequence' => 1,
        'status' => 'completed',
        'artifact_disk' => 'local',
        'artifact_path' => 'missing.pdf',
        'page_count' => 1,
        'checksum' => str_repeat('a', 64),
    ]);
    $job = new MergeBulkAssessmentExportJob($export->id);

    try {
        $job->handle(
            app(App\Services\PdfGenerationService::class),
            app(AssessmentExportArtifactService::class),
            app(AssessmentExportCoordinator::class),
            app(AssessmentExportNotificationService::class),
        );
    } catch (Throwable $throwable) {
        $job->failed($throwable);
    }

    $export->refresh();
    expect($export->status)->toBe('failed')
        ->and($export->output_path)->toBeNull()
        ->and($export->error_context['stage'])->toBe('merging');
});

it('authorizes job details and supports cancel then retry without discarding completed items', function (): void {
    Bus::fake();
    Event::fake([AssessmentExportProgressed::class]);
    $school = School::factory()->create();
    assessmentExportSchoolContext($school);
    $owner = User::factory()->create(['role' => UserRole::Admin, 'school_id' => $school->id]);
    $other = User::factory()->create(['role' => UserRole::Admin, 'school_id' => $school->id]);
    $export = createTrackedAssessmentExport($owner, $school, ['total_count' => 2, 'completed_count' => 1, 'processed_count' => 1]);
    AssessmentExportItem::query()->create(['assessment_export_id' => $export->id, 'school_id' => $school->id, 'sequence' => 1, 'status' => 'completed']);
    AssessmentExportItem::query()->create(['assessment_export_id' => $export->id, 'school_id' => $school->id, 'sequence' => 2, 'status' => 'pending']);

    $this->actingAs($other)->getJson('/api/jobs/'.$export->id)->assertNotFound();
    $this->actingAs($owner)->postJson('/api/jobs/'.$export->id.'/cancel')->assertAccepted()->assertJsonPath('job.status', 'cancelled');
    $this->postJson('/api/jobs/'.$export->id.'/retry')->assertAccepted()->assertJsonPath('job.status', 'processing');

    expect($export->items()->where('sequence', 1)->value('status'))->toBe('completed')
        ->and($export->items()->where('sequence', 2)->value('status'))->toBe('pending');
    Bus::assertBatched(fn (PendingBatch $batch): bool => count($batch->jobs) === 1);
});

it('can cancel and retry an export before preparation creates any items', function (): void {
    Queue::fake();
    Event::fake([AssessmentExportProgressed::class]);
    $school = School::factory()->create();
    assessmentExportSchoolContext($school);
    $owner = User::factory()->create(['role' => UserRole::Admin, 'school_id' => $school->id]);
    $export = createTrackedAssessmentExport($owner, $school, [
        'status' => 'pending',
        'stage' => 'queued',
        'total_count' => 0,
    ]);

    $this->actingAs($owner)->postJson('/api/jobs/'.$export->id.'/cancel')
        ->assertAccepted()
        ->assertJsonPath('job.status', 'cancelled');
    $this->postJson('/api/jobs/'.$export->id.'/retry')
        ->assertAccepted()
        ->assertJsonPath('job.stage', 'preparing');

    Queue::assertPushed(GenerateBulkAssessmentsJob::class, fn (GenerateBulkAssessmentsJob $job): bool => $job->exportId === $export->id);
});

it('finishes an all-skipped export with a downloadable report and no partial PDF', function (): void {
    Queue::fake();
    Event::fake([AssessmentExportProgressed::class]);
    config()->set('assessment-exports.disk', 'local');
    Storage::fake('local');
    $school = School::factory()->create();
    assessmentExportSchoolContext($school);
    $owner = User::factory()->create(['role' => UserRole::Admin, 'school_id' => $school->id]);
    $export = createTrackedAssessmentExport($owner, $school, ['total_count' => 1]);
    AssessmentExportItem::query()->create([
        'assessment_export_id' => $export->id,
        'school_id' => $school->id,
        'enrollment_id' => null,
        'sequence' => 1,
        'status' => 'skipped',
        'error_code' => 'invalid_enrollment',
        'error_message' => 'Student record is missing.',
        'completed_at' => now(),
    ]);

    app(AssessmentExportCoordinator::class)->synchronize($export->id);
    Queue::assertPushed(MergeBulkAssessmentExportJob::class, 1);

    (new MergeBulkAssessmentExportJob($export->id))->handle(
        app(App\Services\PdfGenerationService::class),
        app(AssessmentExportArtifactService::class),
        app(AssessmentExportCoordinator::class),
        app(AssessmentExportNotificationService::class),
    );

    $export->refresh();
    expect($export->status)->toBe('completed')
        ->and($export->stage)->toBe('no_matches')
        ->and($export->output_path)->toBeNull()
        ->and($export->report_path)->not->toBeNull();
    Storage::disk('local')->assertExists($export->report_path);
});
