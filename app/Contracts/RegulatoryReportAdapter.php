<?php

declare(strict_types=1);

namespace App\Contracts;

use PhpOffice\PhpSpreadsheet\Spreadsheet;

interface RegulatoryReportAdapter
{
    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function buildPreviewData(array $filters = []): array;

    /**
     * @param  array<string, mixed>  $filters
     */
    public function generate(array $filters = []): Spreadsheet;
}
