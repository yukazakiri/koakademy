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

final readonly class RegistrarReportContextSheet implements FromArray, ShouldAutoSize, WithEvents, WithHeadings, WithStyles, WithTitle
{
    use RegistrarExportStyles;

    /** @param array<string, mixed> $analytics
     *  @param array<string, mixed> $report */
    public function __construct(private array $analytics, private array $report) {}

    public function title(): string
    {
        return 'Report Context';
    }

    public function array(): array
    {
        $context = $this->report['context'] ?? [];
        $rows = [
            ['REGISTRAR ANALYTICS REPORT CONTEXT', ''],
            ['Report period', $this->report['label'] ?? 'Configured current term'],
            ['Generated at', now()->format('F j, Y h:i A')],
            ['Selected reporting population', (int) ($this->analytics['current_semester_count'] ?? 0)],
            ['Enrollment status rule', $context['status_rule'] ?? 'Pending enrollment records are excluded unless a specific enrollment status is selected.'],
            ['Form B/C treatment', $context['form_bc_rule'] ?? 'Unclassified first-year intake records are excluded from the Form B/C first-year categories.'],
            ['', ''],
            ['APPLIED FILTERS', ''],
        ];

        foreach ($context['filters'] ?? [] as $filter) {
            $rows[] = [$filter['label'] ?? 'Filter', $filter['value'] ?? ''];
        }

        return $rows;
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
        return [AfterSheet::class => fn (AfterSheet $event) => $this->applySheetStyle($event, 1, 8)];
    }
}
