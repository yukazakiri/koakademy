<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Authority code imports
    |--------------------------------------------------------------------------
    |
    | Global toggles only. Authority definitions themselves are managed at
    | runtime per school (DB-managed) so no country-specific dataset ships
    | with this repository. Schools import their regulator's spreadsheet
    | only when their curriculum capabilities make that authority relevant.
    |
    */
    'import' => [
        'max_rows' => (int) env('AUTHORITY_CODE_IMPORT_MAX_ROWS', 10000),
        'max_mb' => (int) env('AUTHORITY_CODE_IMPORT_MAX_MB', 10),
        'allowed_extensions' => ['xlsx', 'xls', 'csv'],
    ],
];
