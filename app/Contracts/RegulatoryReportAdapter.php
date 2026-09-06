<?php

declare(strict_types=1);

namespace App\Contracts;

use PhpOffice\PhpSpreadsheet\Spreadsheet;

/**
 * Provider boundary for jurisdiction-specific regulatory reports.
 *
 * Implementations receive tenant-scoped, validated report filters and own
 * provider-specific queries and workbook/preview formatting. They must not
 * read the current request or retain tenant state between calls.
 */
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
