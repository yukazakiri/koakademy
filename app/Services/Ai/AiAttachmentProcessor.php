<?php

declare(strict_types=1);

namespace App\Services\Ai;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Laravel\Ai\Files\Document;
use Laravel\Ai\Files\Image;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Throwable;

final class AiAttachmentProcessor
{
    /**
     * Maximum rows to parse from an uploaded spreadsheet into prompt context.
     */
    private const int MAX_SPREADSHEET_ROWS = 250;

    /**
     * Process uploaded files into Laravel AI SDK attachments and enrich prompt context.
     *
     * @param  array<int, UploadedFile>  $uploadedFiles
     * @return array{attachments: array<int, mixed>, enrichedPrompt: string}
     */
    public function process(array $uploadedFiles, string $basePrompt): array
    {
        $attachments = [];
        $dataContexts = [];

        foreach ($uploadedFiles as $file) {
            if (! $file instanceof UploadedFile || ! $file->isValid()) {
                continue;
            }

            $originalName = $file->getClientOriginalName();
            $extension = mb_strtolower($file->getClientOriginalExtension());
            $mime = $file->getMimeType() ?: 'application/octet-stream';
            $realPath = $file->getRealPath();

            // 1. Process Images
            if ($this->isImage($extension, $mime)) {
                try {
                    $attachments[] = Image::fromPath($realPath)->withMimeType($mime);
                    $dataContexts[] = "[Attached Image: {$originalName} ({$mime})]";
                } catch (Throwable) {
                    // Fallback to Document if image driver unsupported
                    $attachments[] = Document::fromPath($realPath)->withMimeType($mime);
                }

                continue;
            }

            // 2. Process Spreadsheets (Excel .xlsx, .xls, .csv)
            if ($this->isSpreadsheet($extension, $mime)) {
                $spreadsheetTable = $this->extractSpreadsheetData($file, $extension);
                if (filled($spreadsheetTable)) {
                    $dataContexts[] = $spreadsheetTable;
                }

                try {
                    $attachments[] = Document::fromPath($realPath)->withMimeType($mime);
                } catch (Throwable) {
                    // Best effort attachment
                }

                continue;
            }

            // 3. Process Text & Markdown files directly
            if (in_array($extension, ['txt', 'md', 'json', 'sql', 'csv', 'log'], true)) {
                $textContent = @file_get_contents($realPath);
                if (is_string($textContent) && filled($textContent)) {
                    $truncated = Str::limit($textContent, 10000, "\n...[truncated]");
                    $dataContexts[] = "```\n[Attached Document: {$originalName}]\n{$truncated}\n```";
                }

                try {
                    $attachments[] = Document::fromPath($realPath)->withMimeType($mime);
                } catch (Throwable) {
                    // Best effort attachment
                }

                continue;
            }

            // 4. Default Documents (PDF, DOCX, etc.)
            try {
                $attachments[] = Document::fromPath($realPath)->withMimeType($mime);
                $dataContexts[] = "[Attached Document: {$originalName} ({$mime})]";
            } catch (Throwable) {
                // Best effort attachment
            }
        }

        $enrichedPrompt = $basePrompt;

        if (! empty($dataContexts)) {
            $contextText = implode("\n\n", $dataContexts);
            $enrichedPrompt = "{$basePrompt}\n\n--- User Uploaded Files & Extracted Content ---\n{$contextText}\n--- End of Uploaded Content ---";
        }

        return [
            'attachments' => $attachments,
            'enrichedPrompt' => $enrichedPrompt,
        ];
    }

    private function isImage(string $extension, string $mime): bool
    {
        return str_starts_with($mime, 'image/') || in_array($extension, ['png', 'jpg', 'jpeg', 'webp', 'gif'], true);
    }

    private function isSpreadsheet(string $extension, string $mime): bool
    {
        return in_array($extension, ['xlsx', 'xls', 'csv', 'tsv', 'ods'], true)
            || str_contains($mime, 'spreadsheet')
            || str_contains($mime, 'excel')
            || $mime === 'text/csv';
    }

    /**
     * Extract tabular sheet rows into formatted Markdown table text.
     */
    private function extractSpreadsheetData(UploadedFile $file, string $extension): ?string
    {
        try {
            $reader = IOFactory::createReaderForFile($file->getRealPath());
            if (method_exists($reader, 'setReadDataOnly')) {
                $reader->setReadDataOnly(true);
            }
            $spreadsheet = $reader->load($file->getRealPath());
            $sheets = $spreadsheet->getAllSheets();

            if (empty($sheets)) {
                return null;
            }

            $output = [];
            $totalRowsProcessed = 0;
            $sheetsToProcess = array_slice($sheets, 0, 5);

            foreach ($sheetsToProcess as $sheet) {
                $title = $sheet->getTitle();
                $rows = $sheet->toArray();
                if (empty($rows)) {
                    continue;
                }

                $nonEmptyRows = array_values(array_filter($rows, fn ($r) => is_array($r) && ! empty(array_filter($r, fn ($v) => filled($v)))));
                if (empty($nonEmptyRows)) {
                    continue;
                }

                $sheetOutput = [];
                $sheetOutput[] = "### [Spreadsheet: {$file->getClientOriginalName()} · Sheet: '{$title}']";

                // Find the best header row (first row with >= 2 populated columns)
                $headerIdx = null;
                $metadataLines = [];
                foreach ($nonEmptyRows as $idx => $r) {
                    $populated = array_values(array_filter($r, fn ($v) => filled($v)));
                    if (count($populated) >= 2) {
                        $headerIdx = $idx;
                        break;
                    }
                    $metadataLines[] = mb_trim((string) ($populated[0] ?? ''));

                }

                if (! empty($metadataLines)) {
                    $sheetOutput[] = '> **Document Header / Metadata**: '.implode(' · ', array_filter($metadataLines));
                }

                if ($headerIdx === null) {
                    // Fallback: list non-empty rows directly
                    foreach (array_slice($nonEmptyRows, 0, 50) as $rIdx => $r) {
                        $sheetOutput[] = '- Row '.($rIdx + 1).': '.implode(' | ', array_filter($r, fn ($v) => filled($v)));
                    }
                    $output[] = implode("\n", $sheetOutput);

                    continue;
                }

                $rawHeader = $nonEmptyRows[$headerIdx];
                $cleanHeaders = array_map(fn ($h, $i) => filled($h) ? mb_trim((string) $h) : 'Col_'.($i + 1), $rawHeader, array_keys($rawHeader));
                $sheetOutput[] = '| '.implode(' | ', $cleanHeaders).' |';
                $sheetOutput[] = '| '.implode(' | ', array_fill(0, count($cleanHeaders), '---')).' |';

                $dataRows = array_slice($nonEmptyRows, $headerIdx + 1);
                foreach ($dataRows as $row) {
                    if ($totalRowsProcessed >= self::MAX_SPREADSHEET_ROWS) {
                        break 2;
                    }

                    $populated = array_values(array_filter($row, fn ($v) => filled($v)));
                    if (empty($populated)) {
                        continue;
                    }

                    // If row is a section marker (single cell spanning across), format as subtitle row
                    if (count($populated) === 1 && filled($row[0] ?? null)) {
                        $sheetOutput[] = "\n**[Section: {$row[0]}]**\n";

                        continue;
                    }

                    $cells = [];
                    foreach (array_keys($cleanHeaders) as $colIdx) {
                        $cellVal = isset($row[$colIdx]) ? mb_trim((string) $row[$colIdx]) : '';
                        $cleanCell = str_replace(['|', "\n", "\r"], [' ', ' ', ' '], $cellVal);
                        $cells[] = $cleanCell;
                    }
                    $sheetOutput[] = '| '.implode(' | ', $cells).' |';
                    $totalRowsProcessed++;
                }

                $output[] = implode("\n", $sheetOutput);
            }

            if ($totalRowsProcessed >= self::MAX_SPREADSHEET_ROWS) {
                $output[] = "\n*(Note: Displaying first ".self::MAX_SPREADSHEET_ROWS.' rows from this spreadsheet)*';
            }

            return implode("\n\n", $output);
        } catch (Throwable) {
            return null;
        }
    }
}
