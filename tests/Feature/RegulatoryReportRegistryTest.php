<?php

declare(strict_types=1);

use App\Contracts\RegulatoryReportAdapter;
use App\Models\School;
use App\Services\ChedFormBcExportService;
use App\Services\RegulatoryReportRegistry;

uses(Tests\TestCase::class);

test('regulatory report providers can be configured without a country or framework lock', function (): void {
    $school = School::factory()->create(['country_code' => 'US']);
    $originalDefinitions = config('regulatory-reports.definitions');

    config()->set('regulatory-reports.definitions', [
        'institutional_matrix' => [
            'key' => 'institutional_matrix',
            'title' => 'Institutional matrix',
            'agency' => 'Institutional',
            'country_code' => null,
            'framework' => null,
            'adapter' => ChedFormBcExportService::class,
            'enabled' => true,
        ],
    ]);

    try {
        $registry = app(RegulatoryReportRegistry::class);

        expect($registry->isAvailable('institutional_matrix', $school))->toBeTrue()
            ->and($registry->context($school)['available_report_keys'])->toContain('institutional_matrix')
            ->and($registry->adapter('institutional_matrix'))->toBeInstanceOf(RegulatoryReportAdapter::class);
    } finally {
        config()->set('regulatory-reports.definitions', $originalDefinitions);
    }
});
