<?php

declare(strict_types=1);

use App\Jobs\GenerateBulkAssessmentChunkJob;
use App\Jobs\GenerateBulkAssessmentsJob;
use App\Models\Course;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\User;
use App\Services\EnrollmentPipelineService;
use App\Services\JobTrackerService;
use Illuminate\Bus\PendingBatch;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;

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
        'course_filter' => 'all',
        'year_level_filter' => 'all',
        'student_limit' => 'all',
        'include_deleted' => false,
        'semester' => 1,
        'school_year' => '2024 - 2025',
    ];

    $job = new GenerateBulkAssessmentsJob($filters, $user->id, 'bulk-orchestration-test');
    $job->handle(app(JobTrackerService::class));

    Bus::assertBatched(function (PendingBatch $batch): bool {
        return $batch->name === 'bulk-assessment-bulk-orchestration-test'
            && $batch->hasJobs([
                fn (GenerateBulkAssessmentChunkJob $chunkJob): bool => $chunkJob->jobId === 'bulk-orchestration-test'
                    && $chunkJob->chunkIndex === 0,
            ]);
    });

    Storage::disk($storageDisk)->assertExists('bulk_assessments/bulk-orchestration-test/manifest.json');
});
