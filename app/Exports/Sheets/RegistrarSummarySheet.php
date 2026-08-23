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
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

final readonly class RegistrarSummarySheet implements FromArray, ShouldAutoSize, WithEvents, WithHeadings, WithStyles, WithTitle
{
    use RegistrarExportStyles;

    /** @param array<string, mixed> $analytics */
    public function __construct(
        private array $analytics,
        private array $filters,
    ) {}

    public function title(): string
    {
        return 'Executive Summary';
    }

    public function array(): array
    {
        $a = $this->analytics;
        $f = $this->filters;
        $current = $a['current_semester_count'] ?? 0;
        $previous = $a['previous_semester_count'] ?? 0;
        $delta = $current - $previous;
        $growth = $previous > 0 ? round(($delta / $previous) * 100, 1) : ($current > 0 ? 100 : 0);

        return [
            ['REGISTRAR ANALYTICS REPORT'],
            ['Generated: '.now()->format('F j, Y h:i A').' | '.($f['label'] ?? 'Configured current term')],
            [''],
            ['METRIC', 'VALUE'],
            ['Current Semester Enrollments', $current],
            ['Previous Semester Enrollments', $previous],
            ['Semester-over-Semester Change', ($delta >= 0 ? '+' : '').$delta.' ('.$growth.'%)'],
            ['Current School Year Total', $a['current_school_year_count'] ?? 0],
            ['Active (non-trashed)', $a['active_count'] ?? 0],
            ['Trashed / Deleted', $a['trashed_count'] ?? 0],
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
        return [AfterSheet::class => fn (AfterSheet $e) => $this->applySheetStyle($e, 2, 4)];
    }
}
