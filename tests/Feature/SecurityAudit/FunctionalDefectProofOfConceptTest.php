<?php

declare(strict_types=1);

use App\Jobs\BulkMoveStudentsToSectionJob;
use App\Jobs\GenerateAssessmentPdfJob;
use App\Jobs\GenerateAttendancePdfJob;
use App\Jobs\GenerateEnrollmentReportPreviewPdfJob;
use App\Jobs\GenerateStudentSoaPdfJob;
use App\Jobs\MoveStudentToSectionJob;

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
