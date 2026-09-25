<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Throwable;
use ZipArchive;

final class CurriculumWorkbookParser
{
    /** @return array{title: string, rows: list<array<string, mixed>>, warnings: list<string>} */
    public function parse(UploadedFile $file): array
    {
        $this->validateArchive($file);

        try {
            $reader = IOFactory::createReaderForFile($file->getRealPath());
            $reader->setReadDataOnly(true);
            $book = $reader->load($file->getRealPath());
        } catch (Throwable) {
            throw ValidationException::withMessages(['file' => 'Unable to read this spreadsheet. Upload a valid XLSX workbook.']);
        }

        try {
            if ($book->getSheetCount() > 10) {
                throw ValidationException::withMessages(['file' => 'A curriculum workbook may contain at most 10 sheets.']);
            }

            $title = '';
            $rows = [];
            $warnings = [];
            $skipped = [];
            $sheetLayout = [];
            foreach ($book->getAllSheets() as $sheet) {
                if ($sheet->getHighestRow() > 1000 || \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($sheet->getHighestColumn()) > 30) {
                    throw ValidationException::withMessages(['file' => 'A curriculum sheet exceeds the 1,000-row or 30-column limit.']);
                }

                $year = null;
                $semester = null;
                $hasHeader = false;
                foreach ($sheet->getRowIterator() as $sheetRow) {
                    $number = $sheetRow->getRowIndex();
                    $cells = [];
                    foreach (range('A', 'I') as $column) {
                        $coordinate = $column.$number;
                        $value = $sheet->cellExists($coordinate) ? $sheet->getCell($coordinate)->getValue() : null;
                        // Uploaded formulae are untrusted data, not instructions to evaluate.
                        $cells[] = is_scalar($value) && ! str_starts_with((string) $value, '=') ? mb_trim((string) $value) : '';
                    }
                    $first = $cells[0];
                    if ($first === '') {
                        continue;
                    }
                    if ($title === '' && preg_match('/(?:diploma|bachelor|associate|certificate|\bBS[A-Z]+)/i', $first)) {
                        $title = $first;
                    }
                    if ($hasHeader && preg_match('/^\d+(?:\.\d+)?$/', $first) && $cells[1] === '') {
                        $hasHeader = false;

                        continue;
                    }
                    if (preg_match('/^\s*(?:prepared|reviewed|endorsed)\s+by/i', $first)) {
                        $hasHeader = false;
                    }
                    if (preg_match('/\b(FIRST|SECOND|THIRD|FOURTH|FIFTH)\s+YEAR\b/i', $first, $match)) {
                        $year = array_search(mb_strtoupper($match[1]), ['FIRST', 'SECOND', 'THIRD', 'FOURTH', 'FIFTH'], true) + 1;
                        $semester = null;

                        continue;
                    }
                    if (preg_match('/\b([123])(?:ST|ND|RD)\s+SEMESTER\b/i', $first, $match)) {
                        $semester = (int) $match[1];
                        $hasHeader = false;

                        continue;
                    }
                    if (preg_match('/^course\s*code$/i', $first) && preg_match('/(?:descriptive\s*)?title/i', $cells[1])) {
                        $hasHeader = true;
                        $sheetLayout[$sheet->getTitle()] = true;

                        continue;
                    }
                    $headerlessSubject = $year !== null && $semester !== null
                        && isset($sheetLayout[$sheet->getTitle()])
                        && (preg_match('/^[A-Za-z][A-Za-z0-9.\s-]*\d+$/', $first) || mb_strtolower($first) === 'prac');
                    if ((! $hasHeader && ! $headerlessSubject) || $cells[1] === '' || ! preg_match('/[A-Za-z]/', $cells[1]) || ! preg_match('/[A-Za-z]/', $first) || preg_match('/^(prepared|reviewed|endorsed)/i', $first)) {
                        continue;
                    }
                    if ($year === null || $semester === null) {
                        $skipped[] = "{$sheet->getTitle()}!{$number}: subject-like row has no year and semester.";

                        continue;
                    }

                    $units = filter_var($cells[4], FILTER_VALIDATE_INT);
                    $issues = [];
                    if ($units === false || $units < 0 || $units > 12) {
                        $issues[] = 'Total units need review.';
                    }
                    if ($cells[6] !== '' && mb_strtoupper(preg_replace('/\s+/', '', $first)) === mb_strtoupper(preg_replace('/\s+/', '', $cells[6]))) {
                        $issues[] = 'Self-referencing prerequisite.';
                    }
                    if (preg_match('/(?:internship|capstone)/i', $cells[1])) {
                        $issues[] = 'Confirm credits, term placement and internship handling before including this row.';
                    }
                    if ($cells[4] !== '' && is_numeric($cells[2]) && is_numeric($cells[3]) && (int) $cells[2] + (int) $cells[3] !== (int) $cells[4]) {
                        $issues[] = 'Lab + Lec does not match Total; verify the column meanings.';
                    }
                    $rows[] = [
                        'sheet' => $sheet->getTitle(), 'row' => $number, 'code' => $first,
                        'title' => $cells[1], 'year' => $year, 'semester' => $semester,
                        'lab_raw' => $cells[2], 'lec_raw' => $cells[3], 'units' => $units === false ? null : $units,
                        'hours_per_week' => $cells[5], 'prerequisites_raw' => $cells[6],
                        'resultant' => $cells[7], 'job_roles' => $cells[8], 'issues' => $issues,
                    ];
                }
            }

            if ($rows === []) {
                throw ValidationException::withMessages(['file' => 'No year/semester subject tables were found in this workbook.']);
            }
            $warnings = array_merge($warnings, $skipped);
            $warnings[] = 'The workbook does not reliably distinguish contact hours from credit units. Confirm lecture and laboratory hours for every included row.';
            if (count($rows) > 500) {
                throw ValidationException::withMessages(['file' => 'A curriculum import may contain at most 500 subject rows.']);
            }
            if ($title === '') {
                $warnings[] = 'Program title could not be determined from the workbook.';
            }

            return ['title' => $title, 'rows' => $rows, 'warnings' => $warnings];
        } finally {
            $book->disconnectWorksheets();
        }
    }

    private function validateArchive(UploadedFile $file): void
    {
        $zip = new ZipArchive;
        if ($zip->open($file->getRealPath()) !== true) {
            throw ValidationException::withMessages(['file' => 'Upload a valid XLSX workbook.']);
        }

        try {
            if ($zip->numFiles > 250) {
                throw ValidationException::withMessages(['file' => 'The workbook contains too many embedded files.']);
            }

            $uncompressedBytes = 0;
            for ($index = 0; $index < $zip->numFiles; $index++) {
                $entry = $zip->statIndex($index);
                $size = (int) ($entry['size'] ?? 0);
                $uncompressedBytes += $size;
                if ($size > 8 * 1024 * 1024 || $uncompressedBytes > 25 * 1024 * 1024) {
                    throw ValidationException::withMessages(['file' => 'The workbook is too large to parse safely.']);
                }
            }
        } finally {
            $zip->close();
        }
    }
}
