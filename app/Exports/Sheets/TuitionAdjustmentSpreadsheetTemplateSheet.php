<?php

declare(strict_types=1);

namespace App\Exports\Sheets;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

final class TuitionAdjustmentSpreadsheetTemplateSheet implements FromArray, WithColumnWidths, WithEvents, WithTitle
{
    /** @var list<string> */
    public const HEADINGS = [
        'Student Number', 'Reason', 'New Total Fees', 'Opening Paid', 'Lecture', 'Laboratory', 'Miscellaneous',
        'Discount %', 'Required Downpayment', 'Prelim', 'Midterm', 'Finals',
    ];

    public function array(): array
    {
        return [self::HEADINGS, ...array_fill(0, 250, array_fill(0, count(self::HEADINGS), null))];
    }

    public function title(): string
    {
        return 'Tuition Adjustments';
    }

    public function columnWidths(): array
    {
        return ['A' => 18, 'B' => 42, 'C' => 18, 'D' => 16, 'E' => 14, 'F' => 14, 'G' => 16, 'H' => 13, 'I' => 21, 'J' => 14, 'K' => 14, 'L' => 14];
    }

    public function registerEvents(): array
    {
        return [AfterSheet::class => function (AfterSheet $event): void {
            $sheet = $event->sheet->getDelegate();
            $sheet->freezePane('A2');
            $sheet->setAutoFilter('A1:L251');
            $sheet->getStyle('A1:L1')->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
            $sheet->getStyle('A1:L1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('1D4ED8');
            $sheet->getStyle('A1:L251')->getAlignment()->setVertical('center');
            $sheet->getStyle('B2:B251')->getAlignment()->setWrapText(true);
            $sheet->getStyle('C2:G251')->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_NUMBER_00);
            $sheet->getStyle('I2:L251')->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_NUMBER_00);
            $sheet->getStyle('H2:H251')->getNumberFormat()->setFormatCode('0.00');

            foreach (['C', 'D', 'E', 'F', 'G', 'I', 'J', 'K', 'L'] as $column) {
                $validation = new DataValidation;
                $validation->setType(DataValidation::TYPE_DECIMAL);
                $validation->setOperator(DataValidation::OPERATOR_GREATERTHANOREQUAL);
                $validation->setFormula1('0');
                $validation->setAllowBlank(true);
                $validation->setShowErrorMessage(true);
                $validation->setErrorTitle('Invalid amount');
                $validation->setError('Enter a non-negative number.');
                $sheet->setDataValidation("{$column}2:{$column}251", $validation);
            }

            $discount = new DataValidation;
            $discount->setType(DataValidation::TYPE_DECIMAL);
            $discount->setOperator(DataValidation::OPERATOR_BETWEEN);
            $discount->setFormula1('0');
            $discount->setFormula2('100');
            $discount->setAllowBlank(true);
            $discount->setShowErrorMessage(true);
            $discount->setErrorTitle('Invalid discount');
            $discount->setError('Enter a percentage from 0 to 100.');
            $sheet->setDataValidation('H2:H251', $discount);
        }];
    }
}
