<?php

declare(strict_types=1);

use App\Exports\StudentListExport;
use App\Models\Classes;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

it('returns styles compatible with the Excel styles concern', function (): void {
    $styles = (new StudentListExport(new Classes))->styles(new Worksheet);

    expect($styles)->toBe([
        6 => ['font' => ['bold' => true]],
    ]);
});
