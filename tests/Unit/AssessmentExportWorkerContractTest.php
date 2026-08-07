<?php

declare(strict_types=1);

it('uses an isolated native redis queue with compatible worker timeouts', function (): void {
    expect(config('queue.connections.assessment-pdf.driver'))->toBe('redis')
        ->and(config('queue.connections.assessment-pdf.connection'))->toBe('queue-assessment')
        ->and(config('queue.connections.assessment-pdf.retry_after'))->toBeGreaterThan(config('assessment-exports.merge.timeout'));

    $supervisor = file_get_contents(base_path('docker/supervisord.horizon.conf'));
    $healthcheck = file_get_contents(base_path('docker/healthcheck'));

    expect($supervisor)->toContain('queue:work %(ENV_ASSESSMENT_EXPORT_CONNECTION)s --queue=%(ENV_ASSESSMENT_EXPORT_QUEUE)s')
        ->toContain('queue:work %(ENV_ASSESSMENT_EXPORT_CONNECTION)s --queue=%(ENV_ASSESSMENT_EXPORT_EVENT_QUEUE)s')
        ->and($healthcheck)->toContain('assessment-pdf:assessment-pdf_00')
        ->toContain('assessment-events:assessment-events_00');
});

it('serializes export creation without a postgres aggregate row lock', function (): void {
    $service = file_get_contents(base_path('app/Services/QueueAssessmentExportService.php'));

    expect($service)
        ->toContain('DB::table((new User)->getTable())')
        ->not->toMatch('/whereIn\([^;]+lockForUpdate\(\)[^;]+count\(\)/s');
});

it('compares legacy enrollment identifiers without postgres type mismatches', function (): void {
    $job = file_get_contents(base_path('app/Jobs/GenerateBulkAssessmentsJob.php'));

    expect($job)
        ->toContain('CAST(export_courses.id AS {$textCast})')
        ->toContain('CAST(student_enrollment.course_id AS {$textCast})')
        ->toContain('CAST(export_students.id AS {$textCast})')
        ->toContain('CAST(student_enrollment.student_id AS {$textCast})')
        ->toContain('CAST(student_enrollment.course_id AS {$textCast}) = ?');
});
