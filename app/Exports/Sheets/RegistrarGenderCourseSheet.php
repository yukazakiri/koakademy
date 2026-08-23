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

final readonly class RegistrarGenderCourseSheet implements FromArray, ShouldAutoSize, WithEvents, WithHeadings, WithStyles, WithTitle
{
    use RegistrarExportStyles;

    /** @param array<string, mixed> $analytics */
    public function __construct(private array $analytics) {}

    public function title(): string
    {
        return 'Gender by Program';
    }

    public function array(): array
    {
        $items = $this->normalize($this->analytics['gender_by_program'] ?? []);

        // Build a pivot: course -> [gender => count]
        $pivot = [];
        foreach ($items as $item) {
            $course = mb_trim((string) ($item['program_code'] ?? '')) ?: 'Unassigned';
            $gender = mb_strtolower(mb_trim((string) ($item['gender'] ?? '')));
            $label = $gender === 'male' ? 'Male' : ($gender === 'female' ? 'Female' : 'Unspecified');
            $pivot[$course]['title'] = $item['program_title'] ?? '';
            $pivot[$course][$label] = ($pivot[$course][$label] ?? 0) + (int) ($item['count'] ?? 0);
        }
        ksort($pivot);

        $rows = [];
        $grand = ['Male' => 0, 'Female' => 0, 'Unspecified' => 0, 'Total' => 0];
        foreach ($pivot as $course => $data) {
            $male = (int) ($data['Male'] ?? 0);
            $female = (int) ($data['Female'] ?? 0);
            $unspec = (int) ($data['Unspecified'] ?? 0);
            $total = $male + $female + $unspec;
            $rows[] = [
                $course,
                $data['title'] ?? '',
                $male, $female, $unspec, $total,
                $total > 0 ? round(($male / $total) * 100, 1).'%' : '0%',
                $total > 0 ? round(($female / $total) * 100, 1).'%' : '0%',
            ];
            $grand['Male'] += $male;
            $grand['Female'] += $female;
            $grand['Unspecified'] += $unspec;
            $grand['Total'] += $total;
        }
        $rows[] = [
            'TOTAL', '',
            $grand['Male'], $grand['Female'], $grand['Unspecified'], $grand['Total'],
            $grand['Total'] > 0 ? round(($grand['Male'] / $grand['Total']) * 100, 1).'%' : '0%',
            $grand['Total'] > 0 ? round(($grand['Female'] / $grand['Total']) * 100, 1).'%' : '0%',
        ];

        return $rows;
    }

    public function headings(): array
    {
        return ['Program Code', 'Program Title', 'Male', 'Female', 'Unspecified', 'Total', 'Male Percentage', 'Female Percentage'];
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
            $sheet->mergeCells('A1:H1');
            $sheet->setCellValue('A1', 'SEX BREAKDOWN BY PROGRAM');
            $sheet->mergeCells('A2:H2');
            $sheet->setCellValue('A2', 'Male, female, and unspecified sex enrollment totals for each program in the selected reporting population.');
            $sheet->getStyle('A1')->getFont()->setSize(14)->setBold(true);
            $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle('A2')->getFont()->setSize(10)->setItalic(true);
            $sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $lastRow = $sheet->getHighestRow();
            $sheet->getStyle("A{$lastRow}:H{$lastRow}")->getFont()->setBold(true);
            $this->applySheetStyle($event, 2, 3);
        }];
    }
}
