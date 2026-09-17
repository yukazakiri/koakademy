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
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'format' => 'required|string|in:pdf,csv,markdown',
            'document_category' => 'required|string|in:enrollment_report,clearance_memo,financial_audit,curriculum_review,executive_brief',
            'content_markdown' => 'required|string',
            'summary' => 'nullable|string|max:500',
        ]);

        $docId = 'doc_'.bin2hex(random_bytes(8));
        $extension = match ($validated['format']) {
            'pdf' => 'pdf',
            'csv' => 'csv',
            default => 'md',
        };

        $cleanTitle = mb_ereg_replace("([^\w\s\d\-_~,;\[\]\(\).])", '', $validated['title']);
        $cleanTitle = mb_ereg_replace("([\.]{2,})", '', (string) $cleanTitle);
        $fileName = str_replace(' ', '_', (string) $cleanTitle).".{$extension}";

        $documentPayload = [
            'id' => $docId,
            'title' => $validated['title'],
            'filename' => $fileName,
            'format' => $validated['format'],
            'category' => $validated['document_category'],
            'content' => $validated['content_markdown'],
            'summary' => $validated['summary'] ?? "Generated {$validated['document_category']} document.",
            'created_at' => now()->toIso8601String(),
        ];

        // Store in cache for 6 hours so it can be streamed or downloaded via controller
        Cache::put("ai:doc:{$docId}", $documentPayload, now()->addHours(6));

        $approxSizeKb = max(1, (int) round(mb_strlen($validated['content_markdown']) / 1024));

        return json_encode([
            '_type' => 'document_artifact',
            'document_id' => $docId,
            'title' => $validated['title'],
            'filename' => $fileName,
            'format' => $validated['format'],
            'category' => $validated['document_category'],
            'download_url' => "/administrators/ai/download-document/{$docId}",
            'summary' => $validated['summary'] ?? 'Official document prepared and ready for download.',
            'file_size' => "{$approxSizeKb} KB",
            'content' => $validated['content_markdown'],
        ], JSON_PRETTY_PRINT);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'title' => $schema->string()->required(),
            'format' => $schema->string()->enum(['pdf', 'csv', 'markdown'])->required(),
            'document_category' => $schema->string()
                ->enum(['enrollment_report', 'clearance_memo', 'financial_audit', 'curriculum_review', 'executive_brief'])
                ->required(),
            'content_markdown' => $schema->string()->required(),
            'summary' => $schema->string(),
        ];
    }
}
