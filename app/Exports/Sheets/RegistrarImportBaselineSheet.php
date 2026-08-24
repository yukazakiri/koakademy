<?php

declare(strict_types=1);

namespace App\Exports\Sheets;

use App\Support\RegistrarStudentProfileWorkbook;
use JsonException;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

final readonly class RegistrarImportBaselineSheet implements FromArray, WithCustomValueBinder, WithEvents, WithHeadings, WithTitle
{
    /** @param list<array<string, mixed>> $rows */
    public function __construct(
        private array $rows,
        private int $schoolId,
        private RegistrarStudentProfileWorkbook $workbook,
    ) {}

    public function title(): string
    {
        return RegistrarStudentProfileWorkbook::BASELINE_SHEET;
    }

    public function headings(): array
    {
        return [
            'Record Key',
            'Student Record ID',
            'Enrollment Record ID',
            'Student Updated At',
            'Enrollment Updated At',
            'Year Level',
            'Intake Category',
            'Profile Values',
            'Signature',
        ];
    }

    public function array(): array
    {
        return collect($this->rows)->map(function (array $row): array {
            $baseline = $this->workbook->baselinePayload($row, $this->schoolId);

            try {
                $profileValues = json_encode(
                    $baseline['profile_values'],
                    JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
                );
            } catch (JsonException) {
                $profileValues = '{}';
            }

            return [
                $baseline['record_key'],
                $baseline['student_record_id'],
                $baseline['enrollment_record_id'],
                $baseline['student_updated_at'],
                $baseline['enrollment_updated_at'],
                $baseline['year_level'],
                $baseline['intake_category'],
                $profileValues,
                $row['baseline_signature'],
            ];
        })->all();
    }

    public function bindValue(Cell $cell, mixed $value): bool
    {
        $cell->setValueExplicit($value === null ? '' : (string) $value, DataType::TYPE_STRING);

        return true;
    }

    public function registerEvents(): array
    {
        return [AfterSheet::class => function (AfterSheet $event): void {
            $event->sheet->getDelegate()->setSheetState(Worksheet::SHEETSTATE_VERYHIDDEN);
        }];
    }
}
