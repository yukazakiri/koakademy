<?php

declare(strict_types=1);

namespace App\Exports;

use App\Enums\StudentType;
use App\Models\Classes;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

final readonly class StudentListExport implements FromCollection, ShouldAutoSize, WithEvents, WithHeadings, WithMapping, WithStyles
{
    public function __construct(private Classes $class) {}

    public function collection()
    {
        return $this->class->class_enrollments()
            ->with(['student.course'])
            ->where('status', true)
            ->get()
            ->sortBy(fn ($enrollment): string => $enrollment->student->last_name.' '.$enrollment->student->first_name);
    }

    public function map($enrollment): array
    {
        $student = $enrollment->student;

        $yearLevel = $student->formatted_academic_year;
        if ($student->student_type === StudentType::SeniorHighSchool) {
            $yearLevel = 'Grade '.$student->academic_year;
        }

        return [
            $student->full_name,
            $student->student_type === StudentType::SeniorHighSchool
                ? ($student->lrn ?? 'N/A')
                : ($student->student_id ?? 'N/A'),
            $student->email,
            $student->course?->code ?? 'N/A',
            ucfirst($yearLevel ?? 'N/A'),
            $enrollment->status ? 'Active' : 'Dropped',
        ];
    }

    public function headings(): array
    {
        return [
            ['CLASS STUDENT LIST'], // A1
            [$this->class->record_title], // A2
            [($this->class->subject_code ?? '').' - '.($this->class->subject_title ?? '')], // A3
            [($this->class->school_year ?? '').' | '.($this->class->semester ?? '')], // A4
            [''], // A5 (Spacer)
            [ // A6 (Headers)
                'Student Name',
                'Student ID / LRN',
                'Email',
                'Course',
                'Year Level',
                'Status',
            ],
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            6 => ['font' => ['bold' => true]], // Header row
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event): void {
                $sheet = $event->sheet;

                // Merge Title Rows
                $sheet->mergeCells('A1:F1');
                $sheet->mergeCells('A2:F2');
                $sheet->mergeCells('A3:F3');
                $sheet->mergeCells('A4:F4');

                // Style Titles
                $sheet->getStyle('A1:A4')->applyFromArray([
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical' => Alignment::VERTICAL_CENTER,
                    ],
                ]);

                $sheet->getStyle('A1')->getFont()->setSize(14)->setBold(true);
                $sheet->getStyle('A2')->getFont()->setSize(12)->setBold(true);

                // Borders for the table (starting row 6)
                $highestRow = $sheet->getHighestRow();
                if ($highestRow >= 6) {
                    $sheet->getStyle('A6:F'.$highestRow)->applyFromArray([
                        'borders' => [
                            'allBorders' => [
                                'borderStyle' => Border::BORDER_THIN,
                            ],
                        ],
                    ]);
                }

                // Header Background
                $sheet->getStyle('A6:F6')->applyFromArray([
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['argb' => 'FFE0E0E0'],
                    ],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                    ],
                ]);
            },
        ];
    }
}
