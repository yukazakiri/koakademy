<?php

declare(strict_types=1);

namespace App\Exports\Sheets;

use App\Exports\RegistrarExportStyles;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

final readonly class RegistrarProgramYearLevelSheet implements FromArray, ShouldAutoSize, WithEvents, WithHeadings, WithStyles, WithTitle
{
    use RegistrarExportStyles;

    /** @param array<string, mixed> $analytics
     *  @param array<string, mixed> $report */
    public function __construct(private array $analytics, private array $report) {}

    public function title(): string
    {
        return 'Program by Year Level';
    }

    public function array(): array
    {
        $maximumYearLevel = $this->maximumYearLevel();
        $rows = collect($this->normalize($this->analytics['program_year_matrix'] ?? []))->map(function (array $row) use ($maximumYearLevel): array {
            return [
                $row['department'] ?? 'Unassigned',
                $row['program_code'] ?? 'Unassigned',
                $row['program_title'] ?? 'Unassigned program',
                ...array_map(fn (int $year): int => (int) ($row["year_{$year}"] ?? 0), range(1, $maximumYearLevel)),
                (int) ($row['unclassified_year_level'] ?? 0),
                (int) ($row['total'] ?? 0),
            ];
        })->all();

        $totals = array_fill(0, $maximumYearLevel + 5, 0);
        foreach ($rows as $row) {
            foreach (range(3, $maximumYearLevel + 4) as $index) {
                $totals[$index] += (int) $row[$index];
            }
        }
        $totals[0] = 'Selected reporting population total';
        $totals[1] = '';
        $totals[2] = '';

        return [...$rows, $totals];
    }

    public function headings(): array
    {
        return [
            'Department',
            'Program Code',
            'Program Title',
            ...array_map(fn (int $year): string => 'Year '.$year, range(1, $this->maximumYearLevel())),
            'Unclassified or Other Year Level',
            'Total',
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [];
    }

    public function registerEvents(): array
    {
        return [AfterSheet::class => function (AfterSheet $event): void {
            $sheet = $event->sheet;
            $lastColumn = Coordinate::stringFromColumnIndex($this->maximumYearLevel() + 5);
            $sheet->insertNewRowBefore(1, 2);
            $sheet->mergeCells("A1:{$lastColumn}1");
            $sheet->setCellValue('A1', 'ENROLLMENT BY PROGRAM AND YEAR LEVEL');
            $sheet->mergeCells("A2:{$lastColumn}2");
            $sheet->setCellValue('A2', 'Report period: '.($this->report['label'] ?? 'Configured current term').'. Every row uses the same filtered reporting population as the dashboard.');
            $sheet->getStyle('A1')->getFont()->setSize(14)->setBold(true);
            $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle('A2')->getFont()->setSize(10)->setItalic(true);
            $sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle('A'.$sheet->getHighestRow().":{$lastColumn}".$sheet->getHighestRow())->getFont()->setBold(true);
            $this->applySheetStyle($event, 2, 3);
        }];
    }

    private function maximumYearLevel(): int
    {
        return max(2, min(7, (int) ($this->report['max_year_level'] ?? 4)));
    }
}
