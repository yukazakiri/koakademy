<?php

declare(strict_types=1);

namespace App\Exports;

use App\Models\FacultyCustomFieldDefinition;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;

final class FacultyBulkImportTemplateExport implements FromArray, WithColumnWidths, WithEvents
{
    /** @param iterable<FacultyCustomFieldDefinition> $definitions */
    public function __construct(private readonly iterable $definitions) {}

    /** @return list<string> */
    public function headings(): array
    {
        $base = [
            'Faculty ID Number', 'First Name', 'Middle Name', 'Last Name', 'Email', 'Department', 'Position',
            'Status', 'Gender', 'Birth Date', 'Age', 'Phone Number', 'Office Hours', 'Address', 'Biography',
            'Education', 'Courses Taught', 'Date Employed',
        ];

        foreach ($this->definitions as $definition) {
            $base[] = 'Custom: '.$definition->key;
        }

        return $base;
    }

    public function array(): array
    {
        $headings = $this->headings();

        return [$headings, ...array_fill(0, 100, array_fill(0, count($headings), null))];
    }

    public function columnWidths(): array
    {
        return array_fill_keys(range('A', 'Z'), 18);
    }

    public function registerEvents(): array
    {
        return [AfterSheet::class => function (AfterSheet $event): void {
            $sheet = $event->sheet->getDelegate();
            $lastColumn = $sheet->getHighestColumn();
            $sheet->freezePane('A2');
            $sheet->setAutoFilter("A1:{$lastColumn}101");
            $sheet->getStyle("A1:{$lastColumn}1")->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
            $sheet->getStyle("A1:{$lastColumn}1")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('2563EB');
            $sheet->getStyle("A1:{$lastColumn}101")->getAlignment()->setVertical('center');
        }];
    }
}
