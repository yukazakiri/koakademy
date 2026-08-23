<?php

declare(strict_types=1);

namespace App\Exports;

use Maatwebsite\Excel\Concerns\Export;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

final readonly class RegistrarAnalyticsExport implements Export, WithMultipleSheets
{
    /**
     * @param  array<string, mixed>  $analytics
     * @param  array<string, mixed>  $filters
     */
    public function __construct(
        private array $analytics,
        private array $filters,
    ) {}

    /**
     * @return array<int, object>
     */
    public function sheets(): array
    {
        return [
            new Sheets\RegistrarFormBcSheet($this->analytics, $this->filters),
            new Sheets\RegistrarSummarySheet($this->analytics, $this->filters),
            new Sheets\RegistrarEnrollmentDetailSheet($this->analytics),
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
        ];
    }
}
