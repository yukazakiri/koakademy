<?php

declare(strict_types=1);

namespace App\Exports;

use App\Exports\Sheets\TuitionAdjustmentSpreadsheetInstructionsSheet;
use App\Exports\Sheets\TuitionAdjustmentSpreadsheetTemplateSheet;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

final class TuitionAdjustmentSpreadsheetTemplateExport implements WithMultipleSheets
{
    public function sheets(): array
    {
        return [
            new TuitionAdjustmentSpreadsheetInstructionsSheet,
            new TuitionAdjustmentSpreadsheetTemplateSheet,
        ];
    }
}
