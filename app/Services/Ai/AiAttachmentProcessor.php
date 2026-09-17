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
            $spreadsheet = IOFactory::load($file->getRealPath());
            $sheet = $spreadsheet->getActiveSheet();
            $title = $sheet->getTitle();
            $rows = $sheet->toArray();

            if (empty($rows)) {
                return null;
            }

            $output = [];
            $output[] = "### [Spreadsheet Data: {$file->getClientOriginalName()} (Sheet: '{$title}')]";

            $limitedRows = array_slice($rows, 0, self::MAX_SPREADSHEET_ROWS);
            $header = array_shift($limitedRows);

            if (! is_array($header) || empty($header)) {
                return null;
            }

            // Clean header column names
            $cleanHeaders = array_map(fn ($h, $idx) => filled($h) ? mb_trim((string) $h) : 'Col_'.($idx + 1), $header, array_keys($header));
            $output[] = '| '.implode(' | ', $cleanHeaders).' |';
            $output[] = '| '.implode(' | ', array_fill(0, count($cleanHeaders), '---')).' |';

            foreach ($limitedRows as $row) {
                if (! is_array($row) || empty(array_filter($row, fn ($val) => filled($val)))) {
                    continue; // skip blank rows
                }

                $cells = [];
                foreach (array_keys($cleanHeaders) as $colIdx) {
                    $cellVal = isset($row[$colIdx]) ? mb_trim((string) $row[$colIdx]) : '';
                    $cleanCell = str_replace(['|', "\n", "\r"], [' ', ' ', ' '], $cellVal);
                    $cells[] = $cleanCell;
                }

                $output[] = '| '.implode(' | ', $cells).' |';
            }

            if (count($rows) > self::MAX_SPREADSHEET_ROWS) {
                $output[] = "\n*(Note: Displaying first ".self::MAX_SPREADSHEET_ROWS.' of '.count($rows).' rows from this spreadsheet)*';
            }

            return implode("\n", $output);
        } catch (Throwable) {
            return null;
        }
    }
}
