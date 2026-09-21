<?php

declare(strict_types=1);

namespace App\Ai\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Cache;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

final class GenerateAdministrativeDocumentTool implements Tool
{
    public function description(): Stringable|string
    {
        return 'Generate a formal institutional document (such as an Enrollment Summary, Faculty Clearance Memo, Financial Audit Brief, or Policy Circular) that administrators can download as a PDF, CSV, or Markdown file directly from the conversation.';
    }

    public function handle(Request $request): Stringable|string
    {
        $raw = $request->all();

        // Normalize format (case-insensitive and alias-tolerant)
        $rawFormat = mb_strtolower(mb_trim((string) ($raw['format'] ?? 'pdf')));
        $format = match (true) {
            str_contains($rawFormat, 'csv') => 'csv',
            str_contains($rawFormat, 'mark') || str_contains($rawFormat, 'md') => 'markdown',
            default => 'pdf',
        };

        // Normalize title
        $title = mb_trim((string) ($raw['title'] ?? $raw['document_title'] ?? $raw['name'] ?? 'Administrative Report'));
        if ($title === '') {
            $title = 'Administrative Report';
        }

        // Normalize category
        $category = mb_trim((string) ($raw['document_category'] ?? $raw['category'] ?? 'executive_brief'));
        if ($category === '') {
            $category = 'executive_brief';
        }

        // Normalize content markdown
        $contentMarkdown = mb_trim((string) ($raw['content_markdown'] ?? $raw['content'] ?? $raw['body'] ?? $raw['text'] ?? ''));
        if ($contentMarkdown === '') {
            $contentMarkdown = "# {$title}\n\nGenerated on ".now()->toFormattedDateString();
        }

        $summary = mb_trim((string) ($raw['summary'] ?? $raw['description'] ?? 'Official document prepared and ready for download.'));
        if ($summary === '') {
            $summary = 'Official document prepared and ready for download.';
        }

        $docId = 'doc_'.bin2hex(random_bytes(8));
        $extension = match ($format) {
            'pdf' => 'pdf',
            'csv' => 'csv',
            default => 'md',
        };

        $cleanTitle = mb_ereg_replace("([^\w\s\d\-_~,;\[\]\(\).])", '', $title);
        $cleanTitle = mb_ereg_replace("([\.]{2,})", '', (string) $cleanTitle);
        $fileName = str_replace(' ', '_', (string) $cleanTitle).".{$extension}";

        $documentPayload = [
            'id' => $docId,
            'title' => $title,
            'filename' => $fileName,
            'format' => $format,
            'category' => $category,
            'content' => $contentMarkdown,
            'summary' => $summary,
            'created_at' => now()->toIso8601String(),
        ];

        // Store in cache for 6 hours so it can be streamed or downloaded via controller
        Cache::put("ai:doc:{$docId}", $documentPayload, now()->addHours(6));

        $approxSizeKb = max(1, (int) round(mb_strlen($contentMarkdown) / 1024));

        return json_encode([
            '_type' => 'document_artifact',
            'document_id' => $docId,
            'title' => $title,
            'filename' => $fileName,
            'format' => $format,
            'category' => $category,
            'download_url' => "/ai/download-document/{$docId}",
            'summary' => $summary,
            'file_size' => "{$approxSizeKb} KB",
            'content' => $contentMarkdown,
        ], JSON_PRETTY_PRINT);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'title' => $schema->string()->required()->description('Document title (e.g. "Formal Policy Memo", "Enrollment Summary")'),
            'format' => $schema->string()->description('Document format: pdf, csv, or markdown (defaults to pdf)'),
            'document_category' => $schema->string()->description('Category of document: policy_memo, enrollment_report, clearance_memo, executive_brief, report'),
            'content_markdown' => $schema->string()->required()->description('Full document body formatted in Markdown'),
            'summary' => $schema->string()->description('1-2 sentence executive summary of the document'),
        ];
    }
}
