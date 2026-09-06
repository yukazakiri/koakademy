<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\AssessmentExport;
use App\Services\AssessmentExportCoordinator;
use App\Services\AssessmentExportNotificationService;
use App\Services\RegulatoryReportRegistry;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use RuntimeException;
use Throwable;

final class GenerateRegulatoryReportExportJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout;

    public int $tries;

    public bool $failOnTimeout = true;

    public function __construct(public string $exportId)
    {
        $this->timeout = (int) config('assessment-exports.merge.timeout', 1800);
        $this->tries = (int) config('assessment-exports.merge.tries', 2);
        $this->onConnection((string) config('assessment-exports.connection'));
        $this->onQueue((string) config('assessment-exports.render_queue'));
    }

    /** @return array<int, int> */
    public function backoff(): array
    {
        return (array) config('assessment-exports.merge.backoff', [60, 300]);
    }

    public function uniqueId(): string
    {
        return 'regulatory-'.$this->exportId;
    }

    public function handle(
        RegulatoryReportRegistry $reports,
        AssessmentExportCoordinator $coordinator,
        AssessmentExportNotificationService $notifications,
    ): void {
        $export = AssessmentExport::withoutSchoolScope()->findOrFail($this->exportId);
        if ($export->isTerminal() || $export->cancel_requested_at !== null) {
            return;
        }

        $export->forceFill([
            'status' => 'processing',
            'stage' => 'generating',
            'percentage' => 5,
            'message' => 'Generating the CHED Excel workbook...',
            'started_at' => $export->started_at ?? now(),
        ])->save();
        $coordinator->broadcast($export->refresh());

        $temporaryPath = null;

        try {
            $filters = $export->filters;
            $reportKey = (string) ($filters['report_key'] ?? '');
            if ($reportKey === '') {
                throw new RuntimeException('The queued regulatory report is missing its report key.');
            }

            $spreadsheet = $reports->adapter($reportKey)->generate($filters);
            $temporaryPath = tempnam(sys_get_temp_dir(), 'regulatory-report-');
            if ($temporaryPath === false) {
                throw new RuntimeException('Unable to allocate a temporary workbook path.');
            }

            (new Xlsx($spreadsheet))->save($temporaryPath);

            $disk = (string) config('assessment-exports.disk');
            $storagePath = sprintf(
                'regulatory-reports/%d/%d/%s/%s.xlsx',
                $export->school_id,
                $export->user_id,
                $export->id,
                (string) ($filters['file_name_prefix'] ?? $reportKey),
            );
            $stream = fopen($temporaryPath, 'rb');
            if ($stream === false) {
                throw new RuntimeException('Unable to read the generated workbook.');
            }

            try {
                $stored = Storage::disk($disk)->put($storagePath, $stream, ['visibility' => 'private']);
            } finally {
                fclose($stream);
            }

            if (! $stored || ! Storage::disk($disk)->exists($storagePath)) {
                throw new RuntimeException('Unable to store the generated workbook.');
            }

            $fileName = sprintf(
                '%s_%s.xlsx',
                (string) ($filters['file_name_prefix'] ?? $reportKey),
                now()->format('Y-m-d_His'),
            );

            DB::transaction(function () use ($export, $disk, $storagePath, $fileName): void {
                $locked = AssessmentExport::withoutSchoolScope()
                    ->forSchool((int) $export->school_id)
                    ->lockForUpdate()
                    ->findOrFail($export->id);

                if ($locked->cancel_requested_at !== null || $locked->status === 'cancelled') {
                    return;
                }

                $locked->forceFill([
                    'status' => 'completed',
                    'stage' => 'ready',
                    'percentage' => 100,
                    'message' => 'CHED Excel workbook is ready to download.',
                    'output_disk' => $disk,
                    'output_path' => $storagePath,
                    'output_name' => $fileName,
                    'error_code' => null,
                    'error_message' => null,
                    'error_context' => null,
                    'completed_at' => now(),
                    'failed_at' => null,
                ])->save();
            });

            $export->refresh();
            if ($export->status === 'completed') {
                $coordinator->broadcast($export);
                $notifications->sendTerminal($export);
            }
        } finally {
            if ($temporaryPath !== null) {
                @unlink($temporaryPath);
            }
        }
    }

    public function failed(?Throwable $throwable): void
    {
        if ($throwable !== null) {
            app(AssessmentExportCoordinator::class)->fail($this->exportId, 'generating', $throwable);
        }
    }
}
