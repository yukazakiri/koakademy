<?php

declare(strict_types=1);

namespace App\Exports;

use App\Models\CodeAuthority;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;

/**
 * Empty import template generated from the authority's own column schema,
 * so every country's format is self-describing. Ships headers only —
 * never regulator data.
 */
final class CodeAuthorityImportTemplateExport implements FromArray, WithColumnWidths, WithEvents
{
    public function __construct(private readonly CodeAuthority $authority) {}

    /** @return list<string> */
    public function headings(): array
    {
        $headings = [];

        foreach ($this->authority->columnDefinitions() as $column) {
            $headings[] = $column['label'];
        }

        return $headings;
    }

    public function array(): array
    {
        $headings = $this->headings();

        return [$headings, ...array_fill(0, 100, array_fill(0, count($headings), null))];
    }

    public function columnWidths(): array
    {
        return array_fill_keys(range('A', 'Z'), 22);
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
