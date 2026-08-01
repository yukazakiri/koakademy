<?php

declare(strict_types=1);

it('uses an isolated native redis queue with compatible worker timeouts', function (): void {
    expect(config('queue.connections.assessment-pdf.driver'))->toBe('redis')
        ->and(config('queue.connections.assessment-pdf.connection'))->toBe('queue-assessment')
        ->and(config('queue.connections.assessment-pdf.retry_after'))->toBeGreaterThan(config('assessment-exports.merge.timeout'));

    $supervisor = file_get_contents(base_path('docker/supervisord.station.conf'));
    $healthcheck = file_get_contents(base_path('docker/healthcheck'));

    expect($supervisor)->toContain('queue:work %(ENV_ASSESSMENT_EXPORT_CONNECTION)s --queue=%(ENV_ASSESSMENT_EXPORT_QUEUE)s')
        ->toContain('queue:work %(ENV_ASSESSMENT_EXPORT_CONNECTION)s --queue=%(ENV_ASSESSMENT_EXPORT_EVENT_QUEUE)s')
        ->and($healthcheck)->toContain('assessment-pdf:assessment-pdf_00')
        ->toContain('assessment-events:assessment-events_00');
});
