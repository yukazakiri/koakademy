<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AssessmentExport;

final class AssessmentExportPayloadService
{
    /** @return array<string, mixed> */
    public function make(AssessmentExport $export, bool $withFailures = false): array
    {
        $payload = [
            'id' => $export->id,
            'user_id' => $export->user_id,
            'type' => 'bulk_assessment',
            'title' => 'Bulk Assessment Export',
            'status' => $export->status,
            'stage' => $export->stage,
            'percentage' => $export->percentage,
            'message' => $export->message ?? 'Preparing assessment export...',
            'counts' => [
                'total' => $export->total_count,
                'processed' => $export->processed_count,
                'completed' => $export->completed_count,
                'skipped' => $export->skipped_count,
                'failed' => $export->failed_count,
                'merged_parts' => $export->merged_parts,
                'total_parts' => $export->total_parts,
            ],
            'metadata' => [
                'stage' => $export->stage,
                'processed_count' => $export->processed_count,
                'total_count' => $export->total_count,
                'completed_count' => $export->completed_count,
                'skipped_count' => $export->skipped_count,
                'failed_count' => $export->failed_count,
                'merged_parts' => $export->merged_parts,
                'total_parts' => $export->total_parts,
                'filters' => $export->filters,
                'report_url' => $export->report_path ? route('download.bulk-assessment-report', $export, false) : null,
            ],
            'download_url' => $export->output_path ? route('download.bulk-assessment', $export, false) : null,
            'error' => $export->error_message ? [
                'code' => $export->error_code,
                'summary' => $export->error_message,
                'context' => $export->error_context,
                'correlation_id' => $export->id,
            ] : null,
            'actions' => [
                'can_cancel' => in_array($export->status, ['pending', 'processing'], true),
                'can_retry' => in_array($export->status, ['failed', 'cancelled'], true),
                'can_dismiss' => $export->isTerminal(),
                'can_download' => $export->status === 'completed' && $export->output_path !== null,
            ],
            'created_at' => $export->created_at?->toIso8601String(),
            'updated_at' => $export->updated_at?->toIso8601String(),
            'completed_at' => $export->completed_at?->toIso8601String(),
            'failed_at' => $export->failed_at?->toIso8601String(),
        ];

        if ($withFailures) {
            $payload['failed_items'] = $export->items()
                ->where('status', 'failed')
                ->orderBy('sequence')
                ->limit(50)
                ->get(['id', 'enrollment_id', 'sequence', 'attempts', 'error_code', 'error_message', 'error_context', 'failed_at'])
                ->map(fn ($item): array => [
                    'id' => $item->id,
                    'enrollment_id' => $item->enrollment_id,
                    'sequence' => $item->sequence,
                    'attempts' => $item->attempts,
                    'code' => $item->error_code,
                    'message' => $item->error_message,
                    'context' => $item->error_context,
                    'failed_at' => $item->failed_at?->toIso8601String(),
                ])->all();
        }

        return $payload;
    }
}
