<?php

declare(strict_types=1);

namespace App\Exports\Sheets;

use App\Exports\RegistrarExportStyles;
use App\Support\RegistrarStudentProfileWorkbook;
use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\Cell\DefaultValueBinder;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Protection;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

final readonly class RegistrarEnrollmentDetailSheet implements FromArray, ShouldAutoSize, WithCustomValueBinder, WithEvents, WithHeadings, WithStyles, WithTitle
{
    use RegistrarExportStyles;

    /**
     * @param  list<array<string, mixed>>  $rows
     * @param  array<string, mixed>  $report
     */
    public function __construct(
        private array $rows,
        private array $report,
        private RegistrarStudentProfileWorkbook $workbook,
    ) {}

    public function title(): string
    {
        return RegistrarStudentProfileWorkbook::DETAILS_SHEET;
    }

    public function array(): array
    {
        return collect($this->rows)->map(function (array $item): array {
            $name = mb_trim(
                mb_trim((string) ($item['last_name'] ?? '')).', '.
                mb_trim((string) ($item['first_name'] ?? '')).' '.
                mb_trim((string) ($item['middle_name'] ?? '')).' '.
                mb_trim((string) ($item['suffix'] ?? ''))
            );
            $yearLevel = (int) ($item['year_level'] ?? 0);
            $profileValues = is_array($item['profile_values'] ?? null) ? $item['profile_values'] : [];

            return [
                $item['record_key'] ?? '',
                $item['student_reference'] ?? '',
                $name ?: 'Unnamed Student',
                $item['department'] ?? 'Unassigned',
                $item['course_code'] ?? '',
                $item['course_title'] ?? '',
                $yearLevel > 0 ? 'Year '.$yearLevel : '',
                $yearLevel === 1
                    ? $this->workbook->displayIntakeCategory($item['intake_category'] ?? null)
                    : 'Not applicable',
                $item['status'] ?? '',
                filled($item['created_at'] ?? null)
                    ? Carbon::parse($item['created_at'])->format('Y-m-d H:i')
                    : '',
                ...collect($this->workbook->fields())
                    ->map(fn (array $field): string => $this->workbook->displayValue(
                        $field['key'],
                        $profileValues[$field['key']] ?? null,
                    ))
                    ->all(),
            ];
        })->all();
    }

    public function headings(): array
    {
        return $this->workbook->headings();
    }

    public function styles(Worksheet $sheet): array
    {
        return [];
    }

    public function bindValue(Cell $cell, mixed $value): bool
    {
        if ($cell->getWorksheet()->getTitle() !== RegistrarStudentProfileWorkbook::DETAILS_SHEET) {
            return (new DefaultValueBinder)->bindValue($cell, $value);
        }

        $cell->setValueExplicit($value === null ? '' : (string) $value, DataType::TYPE_STRING);

        return true;
    }

    public function registerEvents(): array
    {
        return [AfterSheet::class => function (AfterSheet $event): void {
            $sheet = $event->sheet;
            $delegate = $sheet->getDelegate();
            $lastColumn = Coordinate::stringFromColumnIndex(count($this->workbook->headings()));

            $sheet->insertNewRowBefore(1, 2);
            $lastRow = max(3, $delegate->getHighestRow());
            $sheet->mergeCells("A1:{$lastColumn}1");
            $sheet->setCellValue('A1', 'ENROLLMENT DETAILS · STUDENT PROFILE UPDATE WORKBOOK');
            $sheet->mergeCells("A2:{$lastColumn}2");
            $sheet->setCellValue(
                'A2',
                'Blue profile cells and yellow Year 1 classification cells are editable. Reference columns are view-only. Blank cells make no change; scroll horizontally to reach every field.'
            );

            $sheet->getStyle('A1')->getFont()->setSize(14)->setBold(true);
            $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle('A2')->getFont()->setSize(10)->setItalic(true);
            $sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $this->applySheetStyle($event, 2, 3);

            $delegate->freezePane('A4');
            $delegate->setAutoFilter("B3:{$lastColumn}{$lastRow}");
            $delegate->getColumnDimension('A')->setVisible(false);
            $delegate->getStyle("A3:J{$lastRow}")->getProtection()->setLocked(Protection::PROTECTION_PROTECTED);
            $delegate->getStyle("K4:{$lastColumn}{$lastRow}")->getProtection()->setLocked(Protection::PROTECTION_UNPROTECTED);
            $delegate->getStyle("K3:{$lastColumn}{$lastRow}")->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->getStartColor()->setARGB('FFEAF4FF');
            $delegate->getStyle("H3:H{$lastRow}")->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->getStartColor()->setARGB('FFFFF4CC');

            $booleanColumns = [];
            $genderColumn = null;
            foreach ($this->workbook->fields() as $offset => $field) {
                $column = Coordinate::stringFromColumnIndex(11 + $offset);
                if ($field['type'] === 'boolean') {
                    $booleanColumns[] = $column;
                }
                if ($field['key'] === 'gender') {
                    $genderColumn = $column;
                }
            }

            for ($row = 4; $row <= $lastRow; $row++) {
                $year = (int) str_replace('Year ', '', (string) $delegate->getCell("G{$row}")->getValue());
                if ($year === 1) {
                    $delegate->getStyle("H{$row}")->getProtection()->setLocked(Protection::PROTECTION_UNPROTECTED);
                    $this->listValidation($delegate, "H{$row}", 'New freshman,Continuing first-year');
                }

                foreach ($booleanColumns as $column) {
                    $this->listValidation($delegate, "{$column}{$row}", 'Yes,No');
                }
                if ($genderColumn !== null) {
                    $this->listValidation($delegate, "{$genderColumn}{$row}", 'Male,Female,Other');
                }
            }

            $delegate->getProtection()
                ->setPassword(mb_substr(hash('sha256', (string) config('app.key')), 0, 16))
                ->setSort(true)
                ->setAutoFilter(true)
                ->setSelectLockedCells(false)
                ->setSelectUnlockedCells(false)
                ->setSheet(true);
        }];
    }

    private function listValidation(Worksheet $sheet, string $coordinate, string $values): void
    {
        $validation = $sheet->getCell($coordinate)->getDataValidation();
        $validation->setType(DataValidation::TYPE_LIST);
        $validation->setErrorStyle(DataValidation::STYLE_STOP);
        $validation->setAllowBlank(true);
        $validation->setShowErrorMessage(true);
        $validation->setShowDropDown(true);
        $validation->setError('Choose a value from the list.');
        $validation->setFormula1('"'.$values.'"');
    }
}
