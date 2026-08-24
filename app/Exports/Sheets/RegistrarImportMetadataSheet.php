<?php

declare(strict_types=1);

namespace App\Exports\Sheets;

use App\Support\RegistrarStudentProfileWorkbook;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

final readonly class RegistrarImportMetadataSheet implements FromArray, WithCustomValueBinder, WithEvents, WithTitle
{
    /** @param array<string, scalar|null> $metadata */
    public function __construct(
        private array $metadata,
        private RegistrarStudentProfileWorkbook $workbook,
    ) {}

    public function title(): string
    {
        return RegistrarStudentProfileWorkbook::METADATA_SHEET;
    }

    public function array(): array
    {
        return [
            ['Schema Version', $this->metadata['schema_version']],
            ['School ID', $this->metadata['school_id']],
            ['Report Label', $this->metadata['report_label']],
            ['Generated At', $this->metadata['generated_at']],
            ['Signature', $this->workbook->metadataSignature($this->metadata)],
        ];
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
