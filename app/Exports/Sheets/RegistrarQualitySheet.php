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
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

final readonly class RegistrarQualitySheet implements FromArray, ShouldAutoSize, WithEvents, WithHeadings, WithStyles, WithTitle
{
    use RegistrarExportStyles;

    /** @param array<string, mixed> $analytics */
    public function __construct(private array $analytics) {}

    public function title(): string
    {
        return 'Data Quality';
    }

    public function array(): array
    {
        $q = $this->analytics['quality'] ?? [];

        return [
            ['Metric', 'Count', 'Status'],
            ['Missing Department', $q['missing_department_count'] ?? 0, ($q['missing_department_count'] ?? 0) > 0 ? '⚠ Needs Attention' : '✓ OK'],
            ['Missing Course', $q['missing_course_count'] ?? 0, ($q['missing_course_count'] ?? 0) > 0 ? '⚠ Needs Attention' : '✓ OK'],
            ['Missing Student Record', $q['missing_student_record_count'] ?? 0, ($q['missing_student_record_count'] ?? 0) > 0 ? '⚠ Needs Attention' : '✓ OK'],
            ['Without Gender Data', $q['without_gender_count'] ?? 0, ($q['without_gender_count'] ?? 0) > 0 ? '⚠ Needs Attention' : '✓ OK'],
        ];
    }

    public function headings(): array
    {
        return [];
    }

    public function styles(Worksheet $sheet): array
    {
        return [];
    }

    public function registerEvents(): array
    {
        return [AfterSheet::class => function (AfterSheet $event): void {
            $sheet = $event->sheet;
            $sheet->insertNewRowBefore(1, 1);
            $sheet->mergeCells('A1:C1');
            $sheet->setCellValue('A1', 'DATA QUALITY METRICS');
            $sheet->getStyle('A1')->getFont()->setSize(14)->setBold(true);
            $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $this->applySheetStyle($event, 1, 2);
        }];
    }
}
