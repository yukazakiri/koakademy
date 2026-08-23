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
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

final readonly class RegistrarFormBcSheet implements FromArray, ShouldAutoSize, WithEvents, WithHeadings, WithStyles, WithTitle
{
    use RegistrarExportStyles;

    /** @param array<string, mixed> $analytics */
    public function __construct(private array $analytics, private array $report) {}

    public function title(): string
    {
        return 'Form B-C Control Total';
    }

    public function array(): array
    {
        return collect($this->analytics['form_bc_matrix'] ?? [])->map(function ($row): array {
            $row = (array) $row;
            $values = [$row['department'] ?? 'Unassigned', $row['program_code'] ?? 'Unassigned', $row['program_title'] ?? ''];
            foreach (['new_freshman_male', 'new_freshman_female', 'continuing_first_year_male', 'continuing_first_year_female'] as $column) {
                $values[] = (int) ($row[$column] ?? 0);
            }
            foreach (range(2, 7) as $year) {
                foreach (['male', 'female'] as $gender) {
                    $values[] = (int) ($row["year_{$year}_{$gender}"] ?? 0);
                }
            }
            $values[] = (int) ($row['total'] ?? 0);

            return $values;
        })->all();
    }

    public function headings(): array
    {
        return ['Department', 'Program Code', 'Program Title', 'New F M', 'New F F', 'Continuing 1st M', 'Continuing 1st F', 'Y2 M', 'Y2 F', 'Y3 M', 'Y3 F', 'Y4 M', 'Y4 F', 'Y5 M', 'Y5 F', 'Y6 M', 'Y6 F', 'Y7 M', 'Y7 F', 'Total'];
    }

    public function styles(Worksheet $sheet): array
    {
        return [];
    }

    public function registerEvents(): array
    {
        return [AfterSheet::class => function (AfterSheet $event): void {
            $event->sheet->insertNewRowBefore(1, 2);
            $event->sheet->mergeCells('A1:T1');
            $event->sheet->setCellValue('A1', 'CHED E-FORM B/C — ENROLMENT CONTROL TOTAL');
            $event->sheet->mergeCells('A2:T2');
            $event->sheet->setCellValue('A2', 'Report population: '.($this->report['label'] ?? 'Configured current term').'. Unclassified first-year intake is intentionally excluded.');
            $this->applySheetStyle($event, 2, 3);
        }];
    }
}
