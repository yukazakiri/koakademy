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
        return 'Top Courses';
    }

    public function array(): array
    {
        $items = $this->normalize($this->analytics['by_course'] ?? []);
        $total = array_sum(array_column($items, 'count'));
        $rows = [];
        $rank = 1;
        foreach ($items as $item) {
            $pct = $total > 0 ? round((($item['count'] ?? 0) / $total) * 100, 1) : 0;
            $rows[] = [$rank++, $item['course_code'] ?? 'N/A', $item['course_title'] ?? '', $item['count'] ?? 0, $pct.'%'];
        }

        return $rows;
    }

    public function headings(): array
    {
        return ['Rank', 'Course Code', 'Course Title', 'Enrollments', 'Percentage'];
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
            $sheet->setCellValue('A1', 'TOP COURSES BY ENROLLMENT');
            $sheet->getStyle('A1')->getFont()->setSize(14)->setBold(true);
            $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $this->applySheetStyle($event, 1, 2);
        }];
    }
}
