<?php

declare(strict_types=1);

use App\Services\ChedFormBcExportService;

return [
    /*
    |--------------------------------------------------------------------------
    | Regulatory report providers
    |--------------------------------------------------------------------------
    |
    | Regulatory exports are application-level adapters. The registry is
    | intentionally configuration-driven so an installation can disable the
    | bundled CHED adapter or add a provider for another jurisdiction without
    | changing the shared report controller or database contract. CHED is the
    | only provider shipped by this repository; other providers must be added
    | deliberately by an application or compatible module.
    |
    */
    'definitions' => [
        'ched_eform_bc' => [
            'key' => 'ched_eform_bc',
            'title' => 'CHED E-Form B/C',
            'description' => 'Philippine curriculum, enrolment, graduates, and equity workbook.',
            'agency' => 'CHED',
            'country_code' => 'PH',
            'framework' => 'ched_psg',
            'adapter' => ChedFormBcExportService::class,
            'file_name_prefix' => 'CHED_Form_B-C',
            'enabled' => (bool) env('REGULATORY_REPORT_CHED_ENABLED', true),
        ],
    ],
];
