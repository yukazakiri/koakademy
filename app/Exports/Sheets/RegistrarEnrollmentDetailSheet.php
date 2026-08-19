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

final readonly class RegistrarEnrollmentDetailSheet implements FromArray, ShouldAutoSize, WithEvents, WithHeadings, WithStyles, WithTitle
{
    use RegistrarExportStyles;

    /** @param array<string, mixed> $analytics */
    public function __construct(private array $analytics) {}

    public function title(): string
    {
        return 'Enrollment Details';
    }

    public function array(): array
    {
        $items = $this->normalize($this->analytics['detailed_enrollments'] ?? []);

        $rows = [];
        foreach ($items as $item) {
            $name = mb_trim(mb_trim((string) ($item['last_name'] ?? '')).', '.mb_trim((string) ($item['first_name'] ?? '')).' '.mb_trim((string) ($item['middle_name'] ?? '')).' '.mb_trim((string) ($item['suffix'] ?? '')));
            $rows[] = [
                $item['student_reference'] ?? '',
                $name ?: 'Unnamed Student',
                $item['gender'] ?? '',
                $item['student_type'] ?? '',
                $item['department'] ?? 'Unassigned',
                $item['course_code'] ?? '',
                $item['course_title'] ?? '',
                'Year '.($item['year_level'] ?? '?'),
                $item['status'] ?? '',
                $item['created_at'] ? \Carbon\Carbon::parse($item['created_at'])->format('Y-m-d H:i') : '',
            ];
        }

        return $rows;
    }

    public function headings(): array
    {
        return [
            'Student ID', 'Student Name', 'Gender', 'Student Type',
            'Department', 'Course Code', 'Course Title', 'Year Level',
            'Status', 'Enrolled At',
        ];
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
            $sheet->mergeCells('A1:J1');
            $sheet->setCellValue('A1', 'DETAILED ENROLLMENT ROSTER');
            $sheet->mergeCells('A2:J2');
            $sheet->setCellValue('A2', 'Individual student enrollment records for the current semester — use as the source reference for all breakdown sheets.');
            $sheet->getStyle('A1')->getFont()->setSize(14)->setBold(true);
            $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle('A2')->getFont()->setSize(10)->setItalic(true);
            $sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $this->applySheetStyle($event, 2, 3);
        }];
    }
}
