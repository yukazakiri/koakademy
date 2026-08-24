<?php

declare(strict_types=1);

namespace App\Exports;

use App\Support\RegistrarStudentProfileWorkbook;
use Maatwebsite\Excel\Concerns\Export;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

final readonly class RegistrarAnalyticsExport implements Export, WithMultipleSheets
{
    /**
     * @param  array<string, mixed>  $analytics
     * @param  array<string, mixed>  $report
     */
    public function __construct(
        private array $analytics,
        private array $report,
        private ?int $schoolId = null,
        private ?string $generatedAt = null,
    ) {}

    /**
     * @return array<int, object>
     */
    public function sheets(): array
    {
        $workbook = app(RegistrarStudentProfileWorkbook::class);
        $details = collect($this->analytics['detailed_enrollments'] ?? [])
            ->map(fn (mixed $row): array => is_array($row) ? $row : $row->toArray())
            ->values()
            ->all();
        $schoolId = $this->schoolId ?? (int) ($details[0]['school_id'] ?? 0);
        $preparedDetails = $workbook->prepareRows($details, $schoolId);
        $metadata = [
            'schema_version' => RegistrarStudentProfileWorkbook::SCHEMA_VERSION,
            'school_id' => $schoolId,
            'report_label' => (string) ($this->report['label'] ?? ''),
            'generated_at' => $this->generatedAt ?? now()->toIso8601String(),
        ];

        return [
            new Sheets\RegistrarReportContextSheet($this->analytics, $this->report),
            new Sheets\RegistrarFormBcSheet($this->analytics, $this->report),
            new Sheets\RegistrarSummarySheet($this->analytics, $this->report),
            new Sheets\RegistrarProgramYearLevelSheet($this->analytics, $this->report),
            new Sheets\RegistrarEnrollmentDetailSheet($preparedDetails, $this->report, $workbook),
            new Sheets\RegistrarStatusSheet($this->analytics),
            new Sheets\RegistrarDepartmentSheet($this->analytics),
            new Sheets\RegistrarYearLevelSheet($this->analytics),
            new Sheets\RegistrarGenderSheet($this->analytics),
            new Sheets\RegistrarGenderCourseSheet($this->analytics),
            new Sheets\RegistrarGenderYearLevelSheet($this->analytics),
            new Sheets\RegistrarStudentTypeSheet($this->analytics),
            new Sheets\RegistrarCourseSheet($this->analytics),
            new Sheets\RegistrarQualitySheet($this->analytics),
            new Sheets\RegistrarTrendSheet($this->analytics),
            new Sheets\RegistrarMonthlyTrendSheet($this->analytics),
            new Sheets\RegistrarImportMetadataSheet($metadata, $workbook),
            new Sheets\RegistrarImportBaselineSheet($preparedDetails, $schoolId, $workbook),
        ];
    }
}
