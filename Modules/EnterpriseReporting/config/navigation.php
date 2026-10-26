<?php

declare(strict_types=1);

return [
    'admin' => [[
        'id' => 'admin-consolidated-reports',
        'title' => 'Consolidated reports',
        'link' => '/administrators/consolidated-reports',
        'inertiaPage' => 'administrators/consolidated-reports/index',
        'section' => 'reports',
        'icon' => 'report_analytics',
        'requiredPermission' => ['ViewAny:StudentEnrollment'],
    ]],
];
