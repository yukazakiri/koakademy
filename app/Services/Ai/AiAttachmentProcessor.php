<?php

declare(strict_types=1);

namespace App\Services\Ai;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Laravel\Ai\Files\Document;
use Laravel\Ai\Files\Image;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Throwable;
use ZipArchive;

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
    public function process(array $uploadedFiles, string $basePrompt, bool $supportsDocumentAttachments = true): array
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
            $documentText = null;

            // 1. Process Images
            if ($this->isImage($extension, $mime)) {
                try {
                    $attachments[] = Image::fromPath($realPath, $mime);
                    $dataContexts[] = "[Attached Image: {$originalName} ({$mime})]";
                } catch (Throwable) {
                    $dataContexts[] = "[Image could not be attached: {$originalName} ({$mime})]";
                }

                continue;
            }

            // Extract PDF text locally so compatible chat providers that only
            // accept images can still reason over text-heavy PDFs without the
            // unsupported Document attachment.
            if ($extension === 'pdf') {
                $pdfText = $this->extractPdfText($file);
                if (filled($pdfText)) {
                    $dataContexts[] = "### [PDF Text: {$originalName}]\n{$pdfText}";
                }

                if (filled($pdfText) || ! $supportsDocumentAttachments) {
                    $dataContexts[] = filled($pdfText)
                        ? "[Original PDF attachment omitted; extracted PDF text is included above: {$originalName}]"
                        : "[PDF attachment omitted because this provider accepts image inputs only: {$originalName}. Text extraction was unavailable; upload page images or choose a document-capable model.]";
                } else {
                    try {
                        $attachments[] = Document::fromPath($realPath)->withMimeType($mime);
                    } catch (Throwable) {
                        $dataContexts[] = "[PDF could not be attached: {$originalName}]";
                    }
                }

                continue;
            }

            // 2. Process Spreadsheets (Excel .xlsx, .xls, .csv)
            if ($this->isSpreadsheet($extension, $mime)) {
                $spreadsheetTable = $this->extractSpreadsheetData($file, $extension);
                if (filled($spreadsheetTable)) {
                    $dataContexts[] = $spreadsheetTable;
                } else {
                    $dataContexts[] = "[Could not extract spreadsheet rows from {$originalName} ({$mime}). Ask the administrator for a CSV or another export if it remains unreadable.]";
                }

                // Spreadsheets are already extracted into prompt context. Sending
                // them as Document attachments breaks OpenAI-compatible/Omni providers.

                continue;
            }

            // 3. Process Text & Markdown files directly
            if (in_array($extension, ['txt', 'md', 'json', 'sql', 'csv', 'log'], true)) {
                $textContent = @file_get_contents($realPath);
                if (is_string($textContent) && filled($textContent)) {
                    $truncated = Str::limit($textContent, 10000, "\n...[truncated]");
                    $dataContexts[] = "```\n[Attached Document: {$originalName}]\n{$truncated}\n```";
                } elseif (in_array($extension, ['txt', 'md', 'json', 'sql', 'log'], true)) {
                    $dataContexts[] = "[Text file could not be read: {$originalName}]";
                }

                continue;
            }

            if (in_array($extension, ['docx', 'odt', 'xlsx', 'ods'], true)) {
                $documentText = $this->extractZipDocumentText($realPath, $extension);
                if (filled($documentText)) {
                    $dataContexts[] = "### [Document Text: {$originalName}]\n".Str::limit($documentText, 30000, "\n...[truncated]");
                }
            }

            // 4. Default documents (PDF, DOCX, etc.). Never send a Document
            // to providers that only accept images as attachments.
            if ($supportsDocumentAttachments && ! isset($documentText)) {
                try {
                    $attachments[] = Document::fromPath($realPath)->withMimeType($mime);
                    $dataContexts[] = "[Attached Document: {$originalName} ({$mime})]";
                } catch (Throwable) {
                    $dataContexts[] = "[Document could not be attached: {$originalName} ({$mime})]";
                }
            } elseif (! $supportsDocumentAttachments) {
                $dataContexts[] = "[Document attachment omitted because the selected model provider accepts images but not document files: {$originalName} ({$mime}). Ask for a text-based export or attach page images.]";
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
        return in_array($mime, ['image/jpeg', 'image/png', 'image/webp', 'image/gif'], true)
            || in_array($extension, ['png', 'jpg', 'jpeg', 'webp', 'gif'], true);
    }

    private function isSpreadsheet(string $extension, string $mime): bool
    {
        return in_array($extension, ['xlsx', 'xls', 'csv', 'tsv', 'ods'], true)
            || str_contains($mime, 'spreadsheet')
            || str_contains($mime, 'excel');
    }

    private function extractPdfText(UploadedFile $file): ?string
    {
        try {
            $parser = new \Smalot\PdfParser\Parser;
            $pdf = $parser->parseFile($file->getRealPath());
            $text = $pdf->getText();

            if (filled($text)) {
                return Str::limit($text, 30000, "\n...[truncated]");
            }
        } catch (Throwable) {
            // Try the lightweight content-stream extractor below for simple
            // PDFs when the standards parser cannot recover text.
        }

        return $this->extractSimplePdfText($file->getRealPath());
    }

    private function extractSimplePdfText(string $path): ?string
    {
        $contents = @file_get_contents($path);
        if (! is_string($contents) || ! str_starts_with($contents, '%PDF-')) {
            return null;
        }

        preg_match_all('/\\(((?:\\\\.|[^\\\\)])*)\\)\\s*Tj/s', $contents, $matches);
        $strings = array_map(static function (string $value): string {
            return preg_replace_callback('/\\\\([\\\\()])|\\\\([0-7]{1,3})/', static function (array $match): string {
                if (($match[1] ?? '') !== '') {
                    return $match[1];
                }

                return chr(octdec($match[2]));
            }, $value) ?? $value;
        }, $matches[1] ?? []);

        $text = mb_trim(implode(' ', $strings));

        return filled($text) ? Str::limit($text, 30000, "\n...[truncated]") : null;
    }

    private function extractZipDocumentText(string $path, string $extension): ?string
    {
        $archive = new ZipArchive;
        if ($archive->open($path) !== true) {
            return null;
        }

        try {
            $contentPath = match ($extension) {
                'docx' => 'word/document.xml',
                'xlsx' => 'xl/sharedStrings.xml',
                default => 'content.xml',
            };
            $xml = $archive->getFromName($contentPath);
            if (! is_string($xml)) {
                return null;
            }

            if ($extension === 'xlsx') {
                preg_match_all('/<t[^>]*>(.*?)<\\/t>/s', $xml, $matches);
                $text = implode("\n", $matches[1] ?? []);
            } else {
                $text = preg_replace('/<\\/w:p>|<\\/text:p>/', "\n", $xml) ?? $xml;
                $text = strip_tags($text);
                $text = html_entity_decode($text, ENT_QUOTES | ENT_XML1, 'UTF-8');
            }

            return filled($text) ? $text : null;
        } catch (Throwable) {
            return null;
        } finally {
            $archive->close();
        }
    }

    /**
     * Detect data-bearing image-only rows even when their image cells are not
     * merged. Such workbooks have no real header row; present every row so the
     * model can infer the table structure rather than dropping its first row.
     *
     * @param  array<int, array<int, mixed>>  $rows
     */
    private function isHeaderlessDocument(array $rows): bool
    {
        $firstRows = array_slice($rows, 0, 12);
        $singleValueRows = 0;
        foreach ($firstRows as $row) {
            $values = array_values(array_filter($row, fn (mixed $value): bool => filled($value)));
            if (count($values) === 1) {
                $singleValueRows++;
            }
        }

        return count($firstRows) >= 3 && $singleValueRows >= 2;
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

                if ($this->isHeaderlessDocument($nonEmptyRows)) {
                    foreach ($nonEmptyRows as $row) {
                        if ($totalRowsProcessed >= self::MAX_SPREADSHEET_ROWS) {
                            break 2;
                        }
                        $cells = array_map(
                            static fn (mixed $value): string => str_replace(['|', "\n", "\r"], [' ', ' ', ' '], mb_trim((string) $value)),
                            $row,
                        );
                        $sheetOutput[] = '- '.implode(' | ', array_filter($cells, fn (string $value): bool => $value !== ''));
                        $totalRowsProcessed++;
                    }
                    $output[] = implode("\n", $sheetOutput);

                    continue;
                }

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
