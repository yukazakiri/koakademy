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
        $formBcEligibleTotal = (int) collect($a['form_bc_matrix'] ?? [])->sum('total');

        return [
            ['REGISTRAR ANALYTICS REPORT'],
            ['Report period: '.($f['label'] ?? 'Configured current term').' | Generated: '.now()->format('F j, Y h:i A')],
            [''],
            ['METRIC', 'VALUE'],
            ['Selected reporting population', $current],
            ['Eligible Commission on Higher Education Form B/C total', $formBcEligibleTotal],
            ['Records outside Form B/C sex and intake categories', $current - $formBcEligibleTotal],
            ['Previous-term reporting population', $previous],
            ['Change from previous term', ($delta >= 0 ? '+' : '').$delta.' ('.$growth.'%)'],
            ['Academic-year reporting population', $a['current_school_year_count'] ?? 0],
            ['Active records', $a['active_count'] ?? 0],
            ['Deleted records retained for audit', $a['trashed_count'] ?? 0],
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
