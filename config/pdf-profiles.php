<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | PDF Render Profiles
    |--------------------------------------------------------------------------
    |
    | Named option sets used by PdfGenerationService. Profiles are
    | driver-aware: when the active driver is not browsershot, Chrome
    | CLI flags (no-sandbox, disable-gpu, etc.) are stripped automatically.
    |
    */

    'profiles' => [

        'browsershot_headless' => [
            'headless' => true,
            'no-sandbox' => true,
            'disable-dev-shm-usage' => true,
            'disable-gpu' => true,
            'no-first-run' => true,
            'disable-background-timer-throttling' => true,
            'disable-backgrounding-occluded-windows' => true,
            'disable-renderer-backgrounding' => true,
            'print-to-pdf-no-header' => true,
            'run-all-compositor-stages-before-draw' => true,
            'disable-extensions' => true,
            'virtual-time-budget' => 10000,
        ],

        'assessment_form' => [
            'format' => 'A4',
            'landscape' => true,
            'print-background' => true,
            'margin-top' => '10mm',
            'margin-bottom' => '10mm',
            'margin-left' => '10mm',
            'margin-right' => '10mm',
        ],

        'attendance_report' => [
            'landscape' => true,
            'format' => 'A4',
            'margins' => [
                'top' => '10mm',
                'right' => '10mm',
                'bottom' => '10mm',
                'left' => '10mm',
            ],
        ],

        'timetable_landscape' => [
            'landscape' => true,
        ],

        'timetable_portrait' => [
            'landscape' => false,
        ],

        'enrollment_report' => [
            'headless' => true,
            'no-sandbox' => true,
            'disable-dev-shm-usage' => true,
            'disable-gpu' => true,
            'no-first-run' => true,
            'disable-background-timer-throttling' => true,
            'disable-backgrounding-occluded-windows' => true,
            'disable-renderer-backgrounding' => true,
            'print-to-pdf-no-header' => true,
            'run-all-compositor-stages-before-draw' => true,
            'disable-extensions' => true,
            'virtual-time-budget' => 10000,
        ],

        'student_soa' => [
            'headless' => true,
            'no-sandbox' => true,
            'disable-dev-shm-usage' => true,
            'disable-gpu' => true,
            'no-first-run' => true,
            'disable-background-timer-throttling' => true,
            'disable-backgrounding-occluded-windows' => true,
            'disable-renderer-backgrounding' => true,
            'print-to-pdf-no-header' => true,
            'run-all-compositor-stages-before-draw' => true,
            'disable-extensions' => true,
            'virtual-time-budget' => 10000,
        ],

        'student_list' => [
            'headless' => true,
            'no-sandbox' => true,
            'disable-dev-shm-usage' => true,
            'disable-gpu' => true,
            'no-first-run' => true,
            'disable-background-timer-throttling' => true,
            'disable-backgrounding-occluded-windows' => true,
            'disable-renderer-backgrounding' => true,
            'print-to-pdf-no-header' => true,
            'run-all-compositor-stages-before-draw' => true,
            'disable-extensions' => true,
            'virtual-time-budget' => 10000,
        ],

    ],

];
