<?php

declare(strict_types=1);

namespace App\Exports\Sheets;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

final class TuitionAdjustmentSpreadsheetInstructionsSheet implements FromArray, WithColumnWidths, WithStyles, WithTitle
{
    public function array(): array
    {
        return [
            ['Tuition Adjustment Spreadsheet'],
            ['Use this workbook to propose tuition changes. Uploading only stages rows for review; nothing changes until a finance admin confirms the valid rows.'],
            [],
            ['Required columns'],
            ['Student Number', 'Must exactly match one student record. Names and email addresses are not accepted.'],
            ['Reason', 'Explain why this student needs the adjustment. This is saved in the audit history.'],
            ['New Total Fees', 'Required final tuition amount after the adjustment.'],
            [],
            ['Optional columns'],
            ['Opening Paid', 'Leave blank to keep the current opening paid amount. It cannot be lower than verified cashier payments.'],
            ['Lecture / Laboratory / Miscellaneous', 'Leave blank to retain the current component amount.'],
            ['Discount %', 'Leave blank to retain the current discount. Use a number between 0 and 100.'],
            ['Required Downpayment', 'Leave blank to retain the current required downpayment.'],
            ['Prelim / Midterm / Finals', 'Leave all three blank to generate the configured schedule. If one is entered, enter all three; they must equal the remaining balance.'],
            [],
            ['Do not rename, add, remove, or reorder the headers on the Tuition Adjustments sheet.'],
            ['Rows with errors remain available in the review screen. Correct them in a new workbook and upload it again.'],
        ];
    }

    public function title(): string
    {
        return 'Instructions';
    }

    public function columnWidths(): array
    {
        return ['A' => 30, 'B' => 110];
    }

    public function styles(Worksheet $sheet): array
    {
        $sheet->getStyle('A1:B1')->getFont()->setBold(true)->setSize(16);
        $sheet->getStyle('A4:B4')->getFont()->setBold(true);
        $sheet->getStyle('A9:B9')->getFont()->setBold(true);
        $sheet->getStyle('A1:B20')->getAlignment()->setWrapText(true)->setVertical('top');

        return [];
    }
}
