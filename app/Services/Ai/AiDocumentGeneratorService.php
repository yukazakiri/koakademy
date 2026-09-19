<?php

declare(strict_types=1);

namespace App\Services\Ai;

use App\Models\GeneralSetting;
use FPDF;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\Cache;
use Throwable;

final class AiDocumentGeneratorService
{
    /**
     * Serve a document from cache by ID.
     */
    public function downloadDocument(string $documentId): HttpResponse
    {
        $doc = Cache::get("ai:doc:{$documentId}");

        if (! is_array($doc)) {
            abort(404, 'The requested document has expired or was not found.');
        }

        $title = (string) ($doc['title'] ?? 'Institutional_Report');
        $format = (string) ($doc['format'] ?? 'pdf');
        $content = (string) ($doc['content'] ?? '');
        $filename = (string) ($doc['filename'] ?? "{$title}.{$format}");

        return match ($format) {
            'pdf' => $this->generatePdfDownload($title, $content, $filename),
            'csv' => response($content, 200, [
                'Content-Type' => 'text/csv; charset=UTF-8',
                'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            ]),
            default => response($content, 200, [
                'Content-Type' => 'text/markdown; charset=UTF-8',
                'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            ]),
        };
    }

    /**
     * Generate on-the-fly document export.
     */
    public function exportDocument(string $title, string $format, string $content): HttpResponse
    {
        $extension = $format === 'markdown' ? 'md' : $format;
        $cleanTitle = str_replace(' ', '_', preg_replace('/[^\w\-]/', '_', $title) ?? 'document');
        $filename = "{$cleanTitle}.{$extension}";

        return match ($format) {
            'pdf' => $this->generatePdfDownload($title, $content, $filename),
            'csv' => response($content, 200, [
                'Content-Type' => 'text/csv; charset=UTF-8',
                'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            ]),
            default => response($content, 200, [
                'Content-Type' => 'text/markdown; charset=UTF-8',
                'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            ]),
        };
    }

    /**
     * Render structured Markdown into an A4 PDF using FPDF with safe Windows-1252 encoding.
     */
    public function generatePdfDownload(string $title, string $content, string $filename): HttpResponse
    {
        try {
            $pdf = new FPDF('P', 'mm', 'A4');
            $pdf->AddPage();
            $pdf->SetMargins(18, 18, 18);

            // Institution Branding Header
            $setting = GeneralSetting::query()->first();
            $appName = $setting?->site_name ?? 'KoAkademy Education';

            $pdf->SetFont('Helvetica', 'B', 16);
            $pdf->SetTextColor(30, 41, 59);
            $pdf->Cell(0, 8, $this->sanitize($appName), 0, 1, 'L');

            $pdf->SetFont('Helvetica', '', 9);
            $pdf->SetTextColor(100, 116, 139);
            $pdf->Cell(0, 5, $this->sanitize('Official Administrative AI Report | Generated on '.now()->toFormattedDateString()), 0, 1, 'L');

            $pdf->Ln(3);
            $pdf->SetDrawColor(226, 232, 240);
            $pdf->Line(18, $pdf->GetY(), 192, $pdf->GetY());
            $pdf->Ln(5);

            // Document Title
            $pdf->SetFont('Helvetica', 'B', 13);
            $pdf->SetTextColor(15, 23, 42);
            $pdf->Cell(0, 7, $this->sanitize($title), 0, 1, 'L');
            $pdf->Ln(2);

            // Document Body Content
            $pdf->SetFont('Helvetica', '', 10);
            $pdf->SetTextColor(51, 65, 85);

            $cleanText = str_replace(["\r\n", "\r"], "\n", $content);
            $lines = explode("\n", $cleanText);

            $tableBuffer = [];

            foreach ($lines as $line) {
                $trimmed = mb_trim($line);

                // Handle Markdown Table
                if (str_starts_with($trimmed, '|') && str_ends_with($trimmed, '|')) {
                    // Skip markdown table delimiter row (e.g. |---|---|)
                    if (preg_match('/^\|[\s\-:|]+\|$/', $trimmed)) {
                        continue;
                    }
                    $tableBuffer[] = array_values(array_filter(
                        array_map('trim', explode('|', $trimmed)),
                        fn ($col, $k) => true,
                        ARRAY_FILTER_USE_BOTH
                    ));

                    continue;
                }

                // If table buffer accumulated, flush table before proceeding
                if (! empty($tableBuffer)) {
                    $this->renderPdfTable($pdf, $tableBuffer);
                    $tableBuffer = [];
                }

                if (str_starts_with($trimmed, '# ')) {
                    $pdf->Ln(3);
                    $pdf->SetFont('Helvetica', 'B', 12);
                    $pdf->MultiCell(0, 6, $this->sanitize(mb_substr($trimmed, 2)));
                    $pdf->SetFont('Helvetica', '', 10);
                } elseif (str_starts_with($trimmed, '## ')) {
                    $pdf->Ln(2);
                    $pdf->SetFont('Helvetica', 'B', 11);
                    $pdf->MultiCell(0, 5, $this->sanitize(mb_substr($trimmed, 3)));
                    $pdf->SetFont('Helvetica', '', 10);
                } elseif (str_starts_with($trimmed, '### ')) {
                    $pdf->Ln(1);
                    $pdf->SetFont('Helvetica', 'B', 10);
                    $pdf->MultiCell(0, 5, $this->sanitize(mb_substr($trimmed, 4)));
                    $pdf->SetFont('Helvetica', '', 10);
                } elseif (str_starts_with($trimmed, '- ') || str_starts_with($trimmed, '* ')) {
                    $pdf->Cell(5, 5, $this->sanitize('•'), 0, 0, 'L');
                    $pdf->MultiCell(0, 5, $this->sanitize(mb_substr($trimmed, 2)));
                } else {
                    $pdf->MultiCell(0, 5, $this->sanitize($line));
                }
            }

            if (! empty($tableBuffer)) {
                $this->renderPdfTable($pdf, $tableBuffer);
            }

            // Footer note
            $pdf->SetY(-18);
            $pdf->SetFont('Helvetica', 'I', 8);
            $pdf->SetTextColor(148, 163, 184);
            $pdf->Cell(0, 6, $this->sanitize('Generated electronically by KoAkademy AI Intelligence. Official institutional copy.'), 0, 0, 'C');

            $pdfOutput = $pdf->Output('S');

            return response($pdfOutput, 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            ]);
        } catch (Throwable) {
            return response($content, 200, [
                'Content-Type' => 'text/markdown; charset=UTF-8',
                'Content-Disposition' => "attachment; filename=\"{$filename}.md\"",
            ]);
        }
    }

    /**
     * Render an aligned Markdown table in the PDF.
     *
     * @param  array<int, array<int, string>>  $rows
     */
    private function renderPdfTable(FPDF $pdf, array $rows): void
    {
        if (empty($rows)) {
            return;
        }

        // Clean empty leading/trailing array cells from markdown split
        $cleanedRows = [];
        foreach ($rows as $r) {
            $c = array_values(array_filter($r, fn ($cell, $idx) => ! (($idx === 0 || $idx === count($r) - 1) && $cell === ''), ARRAY_FILTER_USE_BOTH));
            if (! empty($c)) {
                $cleanedRows[] = $c;
            }
        }

        if (empty($cleanedRows)) {
            return;
        }

        $colCount = max(array_map('count', $cleanedRows));
        if ($colCount === 0) {
            return;
        }

        $pageWidth = 174.0; // 210mm A4 - 36mm margins
        $colWidth = round($pageWidth / $colCount, 2);

        $pdf->Ln(2);

        foreach ($cleanedRows as $idx => $row) {
            $isHeader = $idx === 0;

            if ($isHeader) {
                $pdf->SetFont('Helvetica', 'B', 9);
                $pdf->SetFillColor(241, 245, 249);
                $pdf->SetTextColor(30, 41, 59);
            } else {
                $pdf->SetFont('Helvetica', '', 9);
                $pdf->SetFillColor($idx % 2 === 0 ? 255 : 248);
                $pdf->SetTextColor(51, 65, 85);
            }

            for ($i = 0; $i < $colCount; $i++) {
                $text = $row[$i] ?? '';
                $pdf->Cell($colWidth, 7, $this->sanitize($text), 1, 0, 'L', true);
            }

            $pdf->Ln();
        }

        $pdf->Ln(2);
    }

    /**
     * Replace Unicode currency symbols and typographical marks into Windows-1252 safe chars.
     */
    private function sanitize(string $text): string
    {
        $replacements = [
            '₱' => 'PHP ',
            '•' => '* ',
            '—' => '--',
            '–' => '-',
            '“' => '"',
            '”' => '"',
            '‘' => "'",
            '’' => "'",
            '…' => '...',
            "\xc2\xa0" => ' ',
        ];

        $cleaned = str_replace(array_keys($replacements), array_values($replacements), $text);

        return mb_convert_encoding($cleaned, 'windows-1252', 'UTF-8');
    }
}
