<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\AssessmentExport;
use App\Models\AssessmentExportItem;
use App\Services\AssessmentExportCoordinator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

final class MaintainAssessmentExports extends Command
{
    protected $signature = 'assessment-exports:maintain';

    protected $description = 'Reconcile stale assessment exports and prune expired artifacts';

    public function handle(AssessmentExportCoordinator $coordinator): int
    {
        $staleBefore = now()->subSeconds((int) config('assessment-exports.render.timeout', 600) * 2);
        $staleExportIds = AssessmentExportItem::withoutSchoolScope()
            ->where('status', 'processing')
            ->where('updated_at', '<', $staleBefore)
            ->pluck('assessment_export_id')
            ->unique();

        AssessmentExportItem::withoutSchoolScope()
            ->where('status', 'processing')
            ->where('updated_at', '<', $staleBefore)
            ->update([
                'status' => 'failed',
                'error_code' => 'worker_interrupted',
                'error_message' => 'The PDF worker stopped before this assessment completed. Retry the failed item.',
                'failed_at' => now(),
                'updated_at' => now(),
            ]);

        foreach ($staleExportIds as $exportId) {
            $coordinator->synchronize((string) $exportId);
        }

        $stageTimeouts = [
            'queued' => (int) config('assessment-exports.prepare.timeout', 300) * 2,
            'preparing' => (int) config('assessment-exports.prepare.timeout', 300) * 2,
            'rendering' => (int) config('assessment-exports.render.timeout', 600) * 2,
            'merging' => (int) config('assessment-exports.merge.timeout', 1800) * 2,
        ];
        foreach ($stageTimeouts as $stage => $timeoutSeconds) {
            $stalledIds = AssessmentExport::withoutSchoolScope()
                ->whereIn('status', AssessmentExport::ACTIVE_STATUSES)
                ->where('stage', $stage)
                ->where('updated_at', '<', now()->subSeconds($timeoutSeconds))
                ->pluck('id');

            foreach ($stalledIds as $exportId) {
                $coordinator->fail(
                    (string) $exportId,
                    $stage,
                    new RuntimeException(sprintf('No assessment export progress was recorded during the %s timeout window.', $stage)),
                    ['detected_by' => 'assessment-exports:maintain'],
                );
            }
        }

        $disk = (string) config('assessment-exports.disk');
        $intermediateBefore = now()->subHours((int) config('assessment-exports.retention.intermediate_hours', 24));
        AssessmentExport::withoutSchoolScope()
            ->whereIn('status', AssessmentExport::TERMINAL_STATUSES)
            ->where('updated_at', '<', $intermediateBefore)
            ->chunk(100, function ($exports) use ($disk): void {
                foreach ($exports as $export) {
                    $base = sprintf('assessment-exports/%d/%d/%s', $export->school_id, $export->user_id, $export->id);
                    Storage::disk($disk)->deleteDirectory($base.'/items');
                    Storage::disk($disk)->deleteDirectory($base.'/parts');
                }
            });

        $finalBefore = now()->subDays((int) config('assessment-exports.retention.final_days', 30));
        AssessmentExport::withoutSchoolScope()
            ->whereIn('status', AssessmentExport::TERMINAL_STATUSES)
            ->where('updated_at', '<', $finalBefore)
            ->whereNotNull('output_path')
            ->get()
            ->each(function (AssessmentExport $export) use ($disk): void {
                Storage::disk($disk)->deleteDirectory(sprintf('assessment-exports/%d/%d/%s/final', $export->school_id, $export->user_id, $export->id));
                $export->forceFill([
                    'output_path' => null,
                    'report_path' => null,
                    'message' => 'This assessment export has expired. Start a new export to regenerate it.',
                ])->save();
            });

        AssessmentExport::withoutSchoolScope()
            ->whereIn('status', AssessmentExport::TERMINAL_STATUSES)
            ->where('updated_at', '<', now()->subDays((int) config('assessment-exports.retention.metadata_days', 90)))
            ->delete();

        return self::SUCCESS;
    }
}
