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
        $maximumYearLevel = $this->maximumYearLevel();
        $rows = collect($this->normalize($this->analytics['form_bc_matrix'] ?? []))->map(function (array $row) use ($maximumYearLevel): array {
            $values = [$row['department'] ?? 'Unassigned', $row['program_code'] ?? 'Unassigned', $row['program_title'] ?? ''];
            foreach (['new_freshman_male', 'new_freshman_female', 'continuing_first_year_male', 'continuing_first_year_female'] as $column) {
                $values[] = (int) ($row[$column] ?? 0);
            }
            foreach (range(2, $maximumYearLevel) as $year) {
                foreach (['male', 'female'] as $gender) {
                    $values[] = (int) ($row["year_{$year}_{$gender}"] ?? 0);
                }
            }
            $values[] = (int) ($row['total'] ?? 0);

            return $values;
        })->all();

        $reportedTotal = array_sum(array_map(fn (array $row): int => (int) $row[array_key_last($row)], $rows));
        $selectedTotal = (int) ($this->analytics['current_semester_count'] ?? 0);

        return [
            ...$rows,
            $this->summaryRow('Eligible Form B/C total', $reportedTotal, $maximumYearLevel),
            $this->summaryRow('Selected reporting population total', $selectedTotal, $maximumYearLevel),
            $this->summaryRow('Records outside the Form B/C sex and intake categories', $selectedTotal - $reportedTotal, $maximumYearLevel),
        ];
    }

    public function headings(): array
    {
        $headings = ['Department', 'Program Code', 'Program Title', 'New First-Year Students, Male', 'New First-Year Students, Female', 'Continuing First-Year Students, Male', 'Continuing First-Year Students, Female'];

        foreach (range(2, $this->maximumYearLevel()) as $year) {
            $headings[] = "Year {$year} Students, Male";
            $headings[] = "Year {$year} Students, Female";
        }
        $headings[] = 'Eligible Form B/C Total';

        return $headings;
    }

    public function styles(Worksheet $sheet): array
    {
        return [];
    }

    public function registerEvents(): array
    {
        return [AfterSheet::class => function (AfterSheet $event): void {
            $lastColumn = Coordinate::stringFromColumnIndex(count($this->headings()));
            $event->sheet->insertNewRowBefore(1, 2);
            $event->sheet->mergeCells("A1:{$lastColumn}1");
            $event->sheet->setCellValue('A1', 'COMMISSION ON HIGHER EDUCATION FORM B/C — ENROLLMENT CONTROL TOTAL');
            $event->sheet->mergeCells("A2:{$lastColumn}2");
            $event->sheet->setCellValue('A2', 'Report period: '.($this->report['label'] ?? 'Configured current term').'. The eligible Form B/C total excludes records without a male or female sex value and unclassified first-year intake records.');
            $this->applySheetStyle($event, 2, 3);
        }];
    }

    /** @return array<int, string|int> */
    private function summaryRow(string $label, int $total, int $maximumYearLevel): array
    {
        return [$label, ...array_fill(0, ($maximumYearLevel * 2) + 4, ''), $total];
    }

    private function maximumYearLevel(): int
    {
        return max(2, min(7, (int) ($this->report['max_year_level'] ?? 4)));
    }
}
