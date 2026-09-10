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
        'ched_baccalaureate' => [
            'key' => 'ched_baccalaureate',
            'title' => 'CHED Baccalaureate',
            'description' => 'Baccalaureate program profile, enrollment by sex and year, and graduates.',
            'agency' => 'CHED',
            'country_code' => 'PH',
            'framework' => 'ched_psg',
            'adapter' => ChedFormBcExportService::class,
            'file_name_prefix' => 'CHED_Baccalaureate',
            'enabled' => (bool) env('REGULATORY_REPORT_CHED_ENABLED', true),
        ],
        'ched_special_equity' => [
            'key' => 'ched_special_equity',
            'title' => 'CHED Special Equity Groups',
            'description' => 'Special equity enrollment and graduate counts by curricular program and major.',
            'agency' => 'CHED',
            'country_code' => 'PH',
            'framework' => 'ched_psg',
            'adapter' => ChedFormBcExportService::class,
            'file_name_prefix' => 'CHED_Special_Equity_Groups',
            'enabled' => (bool) env('REGULATORY_REPORT_CHED_ENABLED', true),
        ],
        'ched_equity_enrollment' => [
            'key' => 'ched_equity_enrollment',
            'title' => 'Actual Distribution by Special Equity Group (Enrollment)',
            'description' => 'Enrollment counts for each equity group, by sex and year level.',
            'agency' => 'CHED',
            'country_code' => 'PH',
            'framework' => 'ched_psg',
            'adapter' => ChedFormBcExportService::class,
            'file_name_prefix' => 'CHED_Equity_Enrollment_Distribution',
            'enabled' => (bool) env('REGULATORY_REPORT_CHED_ENABLED', true),
        ],
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
