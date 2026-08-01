<?php

declare(strict_types=1);

namespace App\Services;

use App\Events\AssessmentExportProgressed;
use App\Jobs\GenerateBulkAssessmentItemJob;
use App\Jobs\MergeBulkAssessmentExportJob;
use App\Models\AssessmentExport;
use Illuminate\Bus\Batch;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

final class AssessmentExportCoordinator
{
    public function __construct(
        private readonly AssessmentExportPayloadService $payloads,
        private readonly AssessmentExportNotificationService $notifications,
    ) {}

    public function dispatchPendingItems(string $exportId): ?Batch
    {
        $export = AssessmentExport::withoutSchoolScope()->findOrFail($exportId);
        if ($export->cancel_requested_at !== null || $export->isTerminal()) {
            return null;
        }

        $jobs = $export->items()
            ->withoutSchoolScope()
            ->forSchool((int) $export->school_id)
            ->whereIn('status', ['pending', 'failed', 'cancelled'])
            ->orderBy('sequence')
            ->pluck('id')
            ->map(fn (int $itemId): GenerateBulkAssessmentItemJob => new GenerateBulkAssessmentItemJob($itemId))
            ->all();

        if ($jobs === []) {
            $this->synchronize($exportId);

            return null;
        }

        $batch = Bus::batch($jobs)
            ->name('assessment-export-'.$exportId)
            ->allowFailures()
            ->onConnection((string) config('assessment-exports.connection'))
            ->onQueue((string) config('assessment-exports.render_queue'))
            ->dispatch();

        $export->forceFill([
            'batch_id' => $batch->id,
            'status' => 'processing',
            'stage' => 'rendering',
            'message' => sprintf('Rendering 0 of %d assessments...', $export->total_count),
            'percentage' => max(5, $export->percentage),
            'started_at' => $export->started_at ?? now(),
        ])->save();
        $this->broadcast($export->refresh());

        return $batch;
    }

    public function synchronize(string $exportId): void
    {
        $dispatchMerge = false;
        $becameTerminal = false;

        $export = DB::transaction(function () use ($exportId, &$dispatchMerge, &$becameTerminal): AssessmentExport {
            $export = AssessmentExport::withoutSchoolScope()->lockForUpdate()->findOrFail($exportId);
            $counts = $export->items()
                ->withoutSchoolScope()
                ->forSchool((int) $export->school_id)
                ->selectRaw('status, COUNT(*) as aggregate')
                ->groupBy('status')
                ->pluck('aggregate', 'status');

            $completed = (int) ($counts['completed'] ?? 0);
            $skipped = (int) ($counts['skipped'] ?? 0);
            $failed = (int) ($counts['failed'] ?? 0);
            $cancelled = (int) ($counts['cancelled'] ?? 0);
            $processed = $completed + $skipped + $failed + $cancelled;
            $total = (int) $export->total_count;

            $export->forceFill([
                'processed_count' => $processed,
                'completed_count' => $completed,
                'skipped_count' => $skipped,
                'failed_count' => $failed,
                'percentage' => $total > 0 ? min(84, 5 + (int) floor(($processed / $total) * 79)) : $export->percentage,
                'message' => sprintf('Rendered %d of %d assessments%s.', $completed, $total, $skipped > 0 ? sprintf(' (%d skipped)', $skipped) : ''),
            ]);

            if ($export->cancel_requested_at !== null && $processed >= $total) {
                $export->forceFill([
                    'status' => 'cancelled',
                    'stage' => 'cancelled',
                    'message' => 'Assessment export cancelled. Completed artifacts were retained for retry.',
                    'completed_at' => now(),
                ]);
                $becameTerminal = true;
            } elseif ($total > 0 && $processed >= $total) {
                if ($cancelled > 0) {
                    $export->forceFill([
                        'status' => 'cancelled',
                        'stage' => 'cancelled',
                        'message' => 'Assessment export cancelled. Completed artifacts were retained for retry.',
                        'completed_at' => now(),
                    ]);
                    $becameTerminal = true;
                } elseif ($failed > 0) {
                    $firstFailure = $export->items()
                        ->withoutSchoolScope()
                        ->forSchool((int) $export->school_id)
                        ->where('status', 'failed')
                        ->orderBy('sequence')
                        ->first();
                    $export->forceFill([
                        'status' => 'failed',
                        'stage' => 'failed',
                        'error_code' => $export->error_code ?? $firstFailure?->error_code ?? 'assessment_render_failed',
                        'error_message' => $export->error_message ?? $firstFailure?->error_message ?? sprintf('%d assessment(s) failed to render.', $failed),
                        'error_context' => $export->error_context ?? $firstFailure?->error_context,
                        'message' => sprintf('%d of %d assessments failed. Retry will reuse completed files.', $failed, $total),
                        'failed_at' => now(),
                    ]);
                    $becameTerminal = true;
                } elseif ($export->merge_dispatched_at === null) {
                    $export->forceFill([
                        'status' => 'processing',
                        'stage' => 'merging',
                        'percentage' => 85,
                        'message' => $completed === 0
                            ? 'Preparing the skipped-student report...'
                            : 'All assessments rendered. Preparing the combined PDF...',
                        'merge_dispatched_at' => now(),
                    ]);
                    $dispatchMerge = true;
                }
            }

            $export->save();

            return $export;
        });

        $this->broadcast($export->refresh());

        if ($dispatchMerge) {
            MergeBulkAssessmentExportJob::dispatch($exportId)
                ->onConnection((string) config('assessment-exports.connection'))
                ->onQueue((string) config('assessment-exports.render_queue'));
        }

        if ($becameTerminal) {
            $this->notifications->sendTerminal($export);
        }
    }

    public function fail(string $exportId, string $stage, Throwable $throwable, array $context = []): void
    {
        $export = DB::transaction(function () use ($exportId, $stage, $throwable, $context): AssessmentExport {
            $export = AssessmentExport::withoutSchoolScope()->lockForUpdate()->findOrFail($exportId);
            if ($export->status === 'completed') {
                return $export;
            }
            if ($export->cancel_requested_at !== null) {
                $export->forceFill([
                    'status' => 'cancelled',
                    'stage' => 'cancelled',
                    'message' => 'Assessment export cancelled. Completed artifacts were retained for retry.',
                    'completed_at' => now(),
                ])->save();

                return $export;
            }

            $safeContext = [
                'stage' => $stage,
                'exception_class' => $throwable::class,
                'file' => basename($throwable->getFile()),
                'line' => $throwable->getLine(),
                'occurred_at' => now()->toIso8601String(),
                ...$context,
            ];

            $export->forceFill([
                'status' => 'failed',
                'stage' => 'failed',
                'error_code' => $export->error_code ?? 'assessment_export_'.$stage.'_failed',
                'error_message' => $export->error_message ?? $this->safeMessage($throwable, $stage),
                'error_context' => $export->error_context ?? $safeContext,
                'message' => $this->safeMessage($throwable, $stage).' Reference: '.$export->id,
                'failed_at' => now(),
            ])->save();

            return $export;
        });

        Log::error('Assessment export failed permanently', [
            'export_id' => $exportId,
            'school_id' => $export->school_id,
            'user_id' => $export->user_id,
            'stage' => $stage,
            'correlation_id' => $exportId,
            'context' => $context,
            'exception' => $throwable,
        ]);
        $this->broadcast($export->refresh());
        $this->notifications->sendTerminal($export);
    }

    public function broadcast(AssessmentExport $export): void
    {
        $throttleMilliseconds = (int) config('assessment-exports.broadcast_throttle_ms', 0);
        if (! $export->isTerminal() && $throttleMilliseconds > 0) {
            $key = 'assessment-export:broadcast:'.$export->id;
            if (! Cache::add($key, true, now()->addMilliseconds($throttleMilliseconds))) {
                return;
            }
        }

        AssessmentExportProgressed::dispatch($export->user_id, $this->payloads->make($export));
    }

    private function safeMessage(Throwable $throwable, string $stage): string
    {
        $message = mb_trim($throwable->getMessage());
        if ($message === '') {
            return sprintf('Assessment export failed during %s.', $stage);
        }

        return mb_substr($message, 0, 500);
    }
}
