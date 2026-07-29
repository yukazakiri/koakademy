<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Jobs\GenerateBulkAssessmentChunkJob;
use App\Jobs\GenerateBulkAssessmentsJob;
use App\Jobs\MergeBulkAssessmentChunksJob;
use App\Models\Course;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\User;
use App\Services\EnrollmentPipelineService;
use App\Services\JobTrackerService;
use App\Services\PdfGenerationService;
use Illuminate\Bus\PendingBatch;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use setasign\Fpdi\Fpdi;

it('dispatches chunk jobs in a batch for bulk assessment generation', function (): void {
    Bus::fake();

    $storageDisk = (string) config('filesystems.default');
    Storage::fake($storageDisk);

    $user = User::factory()->create();
    $course = Course::factory()->create();
    $student = Student::factory()->create([
        'course_id' => $course->id,
    ]);

    $cashierVerifiedStatus = app(EnrollmentPipelineService::class)->getCashierVerifiedStatus();

    StudentEnrollment::factory()->create([
        'student_id' => $student->id,
        'course_id' => $course->id,
        'status' => $cashierVerifiedStatus,
        'semester' => 1,
        'school_year' => '2024 - 2025',
    ]);

    $filters = [
        'course_id' => null,
        'year_level' => null,
        'student_limit' => null,
        'include_deleted' => false,
        'semester' => 1,
        'school_year' => '2024 - 2025',
    ];

    $job = new GenerateBulkAssessmentsJob($filters, $user->id, 'bulk-orchestration-test');
    $job->handle(app(JobTrackerService::class));

    Bus::assertBatched(function (PendingBatch $batch): bool {
        return $batch->name === 'bulk-assessment-bulk-orchestration-test'
            && $batch->connection() === config('queue.assessment_notification_connection')
            && $batch->queue() === config('queue.assessment_notification_queue')
            && $batch->hasJobs([
                fn (GenerateBulkAssessmentChunkJob $chunkJob): bool => $chunkJob->jobId === 'bulk-orchestration-test'
                    && $chunkJob->chunkIndex === 0
                    && $chunkJob->connection === config('queue.assessment_notification_connection')
                    && $chunkJob->queue === config('queue.assessment_notification_queue'),
            ]);
    });

    Storage::disk($storageDisk)->assertExists('bulk_assessments/bulk-orchestration-test/manifest.json');
});

it('registers and queues bulk assessment exports before the worker starts', function (): void {
    Queue::fake();

    $user = User::factory()->create(['role' => UserRole::Admin]);
    $course = Course::factory()->create();

    $response = $this->actingAs($user)->postJson(
        portalUrlForAdministrators('/administrators/enrollments/reports/bulk-assessments'),
        [
            'course_id' => $course->id,
            'year_level' => 2,
            'student_limit' => 25,
            'include_deleted' => false,
        ]
    );

    $response
        ->assertAccepted()
        ->assertJsonPath('status', 'pending')
        ->assertJsonStructure(['job_id', 'status', 'message']);

    $jobId = (string) $response->json('job_id');
    $trackedJob = app(JobTrackerService::class)->getJobStatus($jobId);

    expect($trackedJob)
        ->not->toBeNull()
        ->and($trackedJob['status'])->toBe('pending')
        ->and($trackedJob['metadata']['stage'])->toBe('queued')
        ->and($trackedJob['metadata']['filters']['course_id'])->toBe($course->id)
        ->and($trackedJob['metadata']['filters']['year_level'])->toBe(2);

    Queue::assertPushed(GenerateBulkAssessmentsJob::class, fn (GenerateBulkAssessmentsJob $job): bool => $job->connection === config('queue.assessment_notification_connection')
        && $job->queue === config('queue.assessment_notification_queue'));

    $this->actingAs($user)
        ->getJson('/api/jobs')
        ->assertSuccessful()
        ->assertJsonPath('has_active', true)
        ->assertJsonPath('jobs.0.id', $jobId);
});

it('validates bulk assessment filters before registering a queued job', function (): void {
    Queue::fake();

    $user = User::factory()->create(['role' => UserRole::Admin]);

    $this->actingAs($user)
        ->postJson(
            portalUrlForAdministrators('/administrators/enrollments/reports/bulk-assessments'),
            [
                'course_id' => PHP_INT_MAX,
                'year_level' => 8,
                'student_limit' => 7,
            ]
        )
        ->assertUnprocessable()
        ->assertJsonValidationErrors([
            'course_id',
            'year_level',
            'student_limit',
            'include_deleted',
        ]);

    Queue::assertNotPushed(GenerateBulkAssessmentsJob::class);
});

it('finishes explicitly without a download when no enrolled students match', function (): void {
    Bus::fake();
    Storage::fake((string) config('filesystems.default'));

    $user = User::factory()->create(['role' => UserRole::Admin]);
    $jobId = 'bulk-no-matches-test';
    $tracker = app(JobTrackerService::class);

    $tracker->registerJob($jobId, $user->id, 'bulk_assessment', 'Bulk Assessment Export');

    $job = new GenerateBulkAssessmentsJob([
        'course_id' => null,
        'year_level' => null,
        'student_limit' => null,
        'include_deleted' => false,
        'semester' => 1,
        'school_year' => '2099 - 2100',
    ], $user->id, $jobId);
    $job->handle($tracker);

    $trackedJob = $tracker->getJobStatus($jobId);

    expect($trackedJob['status'])->toBe('completed')
        ->and($trackedJob['message'])->toContain('No enrolled students matched')
        ->and($trackedJob['download_url'])->toBeNull()
        ->and($trackedJob['metadata']['total_count'])->toBe(0);
});

it('merges completed assessment chunks into one tracked user export', function (): void {
    $disk = (string) config('filesystems.default');
    Storage::fake($disk);

    $user = User::factory()->create(['role' => UserRole::Admin]);
    $jobId = 'bulk-merge-test';
    $manifestPath = "bulk_assessments/{$jobId}/manifest.json";
    $chunkDirectory = "bulk_assessments/{$jobId}/chunks";
    $tracker = app(JobTrackerService::class);

    $tracker->registerJob($jobId, $user->id, 'bulk_assessment', 'Bulk Assessment Export');

    foreach ([0 => 'FIRST ASSESSMENT', 1 => 'SECOND ASSESSMENT'] as $index => $label) {
        $temporaryPath = tempnam(sys_get_temp_dir(), 'bulk-assessment-test-');

        if ($temporaryPath === false) {
            throw new RuntimeException('Unable to allocate a temporary PDF path.');
        }

        $pdfPath = $temporaryPath.'.pdf';
        rename($temporaryPath, $pdfPath);

        $pdf = new FPDF('L', 'mm', 'A4');
        $pdf->AddPage();
        $pdf->SetFont('Arial', '', 16);
        $pdf->Cell(0, 10, $label);
        $pdf->Output($pdfPath, 'F');

        $storagePath = sprintf('%s/chunk-%03d.pdf', $chunkDirectory, $index);
        Storage::disk($disk)->put($storagePath, file_get_contents($pdfPath));
        Storage::disk($disk)->put(
            sprintf('%s/chunk-%03d.json', $chunkDirectory, $index),
            json_encode([
                'job_id' => $jobId,
                'chunk_index' => $index,
                'status' => 'completed',
                'storage_path' => $storagePath,
                'enrollment_ids' => [$index + 1],
                'generated_count' => 1,
                'skipped' => [],
            ], JSON_THROW_ON_ERROR)
        );

        unlink($pdfPath);
    }

    Storage::disk($disk)->put($manifestPath, json_encode([
        'job_id' => $jobId,
        'skipped_enrollments' => [],
    ], JSON_THROW_ON_ERROR));

    $job = new MergeBulkAssessmentChunksJob($jobId, $user->id, 'batch-test', $manifestPath);
    $job->handle($tracker, app(PdfGenerationService::class));

    $exportFiles = Storage::disk($disk)->files("exports/bulk-assessments/{$user->id}/{$jobId}");
    $pdfExport = collect($exportFiles)->first(fn (string $path): bool => str_ends_with($path, '.pdf'));

    expect($pdfExport)->not->toBeNull();

    $reader = new Fpdi();
    $trackedJob = $tracker->getJobStatus($jobId);

    expect($reader->setSourceFile(Storage::disk($disk)->path($pdfExport)))->toBe(2)
        ->and($trackedJob['status'])->toBe('completed')
        ->and($trackedJob['metadata']['total_count'])->toBe(2)
        ->and($trackedJob['download_url'])->toContain("/download/bulk-assessment/{$jobId}/");
});

it('never marks an export ready when a completed chunk PDF is missing', function (): void {
    $disk = (string) config('filesystems.default');
    Storage::fake($disk);

    $user = User::factory()->create(['role' => UserRole::Admin]);
    $jobId = 'bulk-missing-chunk-test';
    $manifestPath = "bulk_assessments/{$jobId}/manifest.json";
    $tracker = app(JobTrackerService::class);

    $tracker->registerJob($jobId, $user->id, 'bulk_assessment', 'Bulk Assessment Export');
    Storage::disk($disk)->put(
        "bulk_assessments/{$jobId}/chunks/chunk-000.json",
        json_encode([
            'job_id' => $jobId,
            'chunk_index' => 0,
            'status' => 'completed',
            'storage_path' => "bulk_assessments/{$jobId}/chunks/chunk-000.pdf",
            'generated_count' => 1,
            'skipped' => [],
        ], JSON_THROW_ON_ERROR)
    );
    Storage::disk($disk)->put($manifestPath, json_encode([
        'job_id' => $jobId,
        'skipped_enrollments' => [],
    ], JSON_THROW_ON_ERROR));

    $job = new MergeBulkAssessmentChunksJob($jobId, $user->id, 'batch-missing', $manifestPath);
    $failure = null;

    try {
        $job->handle($tracker, app(PdfGenerationService::class));
    } catch (Throwable $throwable) {
        $failure = $throwable;
        $job->failed($throwable);
    }

    $trackedJob = $tracker->getJobStatus($jobId);

    expect($failure)->not->toBeNull()
        ->and($trackedJob['status'])->toBe('failed')
        ->and($trackedJob['download_url'] ?? null)->toBeNull()
        ->and(Storage::disk($disk)->files("exports/bulk-assessments/{$user->id}/{$jobId}"))->toBe([]);
});
