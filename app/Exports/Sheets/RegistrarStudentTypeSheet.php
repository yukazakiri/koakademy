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

final readonly class RegistrarStudentTypeSheet implements FromArray, ShouldAutoSize, WithEvents, WithHeadings, WithStyles, WithTitle
{
    use RegistrarExportStyles;

    /** @param array<string, mixed> $analytics */
    public function __construct(private array $analytics) {}

    public function title(): string
    {
        return 'By Student Type';
    }

    public function array(): array
    {
        $items = $this->normalize($this->analytics['by_student_type'] ?? []);
        $total = array_sum(array_column($items, 'count'));
        $rows = [];
        foreach ($items as $item) {
            $pct = $total > 0 ? round((($item['count'] ?? 0) / $total) * 100, 1) : 0;
            $label = match ($item['student_type'] ?? '') {
                'college' => 'College', 'shs' => 'Senior High School',
                'tesda' => 'TESDA', 'dhrt' => 'DHRT',
                default => ucfirst($item['student_type'] ?? 'Unknown'),
            };
            $rows[] = [$label, $item['count'] ?? 0, $pct.'%'];
        }

        return $rows;
    }

    public function headings(): array
    {
        return ['Student Type', 'Count', 'Percentage'];
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
            $sheet->setCellValue('A1', 'ENROLLMENT BY STUDENT TYPE');
            $sheet->getStyle('A1')->getFont()->setSize(14)->setBold(true);
            $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $this->applySheetStyle($event, 1, 2);
        }];
    }
}
