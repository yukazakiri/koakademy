<?php

declare(strict_types=1);

namespace App\Ai\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

final class AnalyzeTranscriptDocumentTool implements Tool
{
    public function description(): Stringable|string
    {
        return 'Report transcript-analysis availability. A document name alone does not provide transcript contents; extraction and credit equivalency are unavailable until an authorized document-content source is connected.';
    }

    public function handle(Request $request): Stringable|string
    {
        $validated = $request->validate([
            'document_name' => 'required|string|max:255',
            'applicant_name' => 'sometimes|string|max:255',
        ]);

        return json_encode([
            'document' => $validated['document_name'],
            'status' => 'unavailable',
            'available' => false,
            'reason' => 'document_content_unavailable',
            'message' => 'This tool receives a document name, not authorized document contents. No transcript extraction or credit-equivalency assessment was performed.',
            'extracted_institution' => null,
            'total_credited_units' => null,
            'subjects_detected' => [],
            'compliance_note' => 'Institution, grades, units, grading scale, and curriculum equivalencies have not been verified.',
        ], JSON_PRETTY_PRINT);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'document_name' => $schema->string()->required(),
            'applicant_name' => $schema->string(),
        ];
    }
}
