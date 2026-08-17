<?php

declare(strict_types=1);

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

trait RegistrarExportStyles
{
    /**
     * Normalize query results (Eloquent Collection / models) into a plain
     * array of associative arrays so array_column / usort work reliably.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function normalize(mixed $rows): array
    {
        if ($rows instanceof Collection) {
            return $rows->map(fn ($row): array => $this->rowToArray($row))->values()->all();
        }

        if (! is_array($rows)) {
            return [];
        }

        return array_map(fn ($row): array => $this->rowToArray($row), $rows);
    }

    protected function applySheetStyle(AfterSheet $event, int $titleRows, int $headerRow): void
    {
        $sheet = $event->sheet;

        for ($i = 1; $i <= $titleRows; $i++) {
            $sheet->mergeCells("A{$i}:H{$i}");
        }

        $sheet->getStyle("A1:A{$titleRows}")->applyFromArray([
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
        ]);
        $sheet->getStyle('A1')->getFont()->setSize(14)->setBold(true);
        if ($titleRows >= 2) {
            $sheet->getStyle('A2')->getFont()->setSize(11);
        }

        $highestCol = $sheet->getHighestColumn();
        $highestRow = $sheet->getHighestRow();

        if ($highestRow >= $headerRow) {
            $sheet->getStyle("A{$headerRow}:{$highestCol}{$highestRow}")->applyFromArray([
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
            ]);
        }

        $sheet->getStyle("A{$headerRow}:{$highestCol}{$headerRow}")->applyFromArray([
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFE0E0E0']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            'font' => ['bold' => true],
        ]);
    }

    /** @return array<string, mixed> */
    private function rowToArray(mixed $row): array
    {
        if (is_object($row) && method_exists($row, 'toArray')) {
            return $row->toArray();
        }

        if (is_array($row)) {
            return $row;
        }

        if (is_object($row)) {
            return (array) $row;
        }

        return [];
    }
}
