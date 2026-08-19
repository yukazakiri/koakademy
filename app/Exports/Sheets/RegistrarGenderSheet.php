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

final readonly class RegistrarGenderSheet implements FromArray, ShouldAutoSize, WithEvents, WithHeadings, WithStyles, WithTitle
{
    use RegistrarExportStyles;

    /** @param array<string, mixed> $analytics */
    public function __construct(private array $analytics) {}

    public function title(): string
    {
        return 'Gender Breakdown';
    }

    public function array(): array
    {
        $items = $this->normalize($this->analytics['by_gender'] ?? []);

        // Merge case variants (Male / male), then sort by count desc.
        $merged = [];
        foreach ($items as $item) {
            $key = mb_strtolower(mb_trim((string) ($item['gender'] ?? '')));
            $label = match ($key) {
                'male' => 'Male', 'female' => 'Female',
                '' => 'Unspecified', default => ucfirst($key ?: 'Unknown'),
            };
            $merged[$label] = ($merged[$label] ?? 0) + (int) ($item['count'] ?? 0);
        }
        arsort($merged);

        $total = array_sum($merged);
        $rows = [];
        foreach ($merged as $label => $count) {
            $pct = $total > 0 ? round(($count / $total) * 100, 1) : 0;
            $rows[] = [$label, $count, $pct.'%'];
        }
        $rows[] = ['TOTAL', $total, $total > 0 ? '100%' : '0%'];

        return $rows;
    }

    public function headings(): array
    {
        return ['Gender', 'Count', 'Percentage'];
    }

    public function styles(Worksheet $sheet): array
    {
        return [];
    }

    public function registerEvents(): array
    {
        return [AfterSheet::class => function (AfterSheet $event): void {
            $sheet = $event->sheet;
            $sheet->insertNewRowBefore(1, 2);
            $sheet->mergeCells('A1:C1');
            $sheet->setCellValue('A1', 'ENROLLMENT GENDER DISTRIBUTION');
            $sheet->mergeCells('A2:C2');
            $sheet->setCellValue('A2', 'Aggregate gender totals for the current semester. See the "Enrollment Details" sheet for the individual records behind these counts.');
            $sheet->getStyle('A1')->getFont()->setSize(14)->setBold(true);
            $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle('A2')->getFont()->setSize(10)->setItalic(true);
            $sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            // Bold the TOTAL row (last data row).
            $lastRow = $sheet->getHighestRow();
            $sheet->getStyle("A{$lastRow}:C{$lastRow}")->getFont()->setBold(true);
            $this->applySheetStyle($event, 2, 3);
        }];
    }
}
