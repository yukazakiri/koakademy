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

final readonly class RegistrarCourseSheet implements FromArray, ShouldAutoSize, WithEvents, WithHeadings, WithStyles, WithTitle
{
    use RegistrarExportStyles;

    /** @param array<string, mixed> $analytics */
    public function __construct(private array $analytics) {}

    public function title(): string
    {
        return 'Enrollment by Program';
    }

    public function array(): array
    {
        $items = $this->normalize($this->analytics['program_year_matrix'] ?? []);
        $total = array_sum(array_map(fn (array $item): int => (int) ($item['total'] ?? 0), $items));
        usort($items, fn (array $left, array $right): int => ((int) ($right['total'] ?? $right['count'] ?? 0)) <=> ((int) ($left['total'] ?? $left['count'] ?? 0)));
        $rows = [];
        $rank = 1;
        foreach ($items as $item) {
            $count = (int) ($item['total'] ?? $item['count'] ?? 0);
            $pct = $total > 0 ? round(($count / $total) * 100, 1) : 0;
            $rows[] = [$rank++, $item['program_code'] ?? 'Unassigned', $item['program_title'] ?? 'Unassigned program', $count, $pct.'%'];
        }

        return $rows;
    }

    public function headings(): array
    {
        return ['Rank', 'Program Code', 'Program Title', 'Enrollments', 'Percentage'];
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
            $sheet->mergeCells('A1:E1');
            $sheet->setCellValue('A1', 'ENROLLMENT BY PROGRAM');
            $sheet->getStyle('A1')->getFont()->setSize(14)->setBold(true);
            $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $this->applySheetStyle($event, 1, 2);
        }];
    }
}
