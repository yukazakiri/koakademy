<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\User;
use App\Services\PdfGenerationService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

final class GenerateEnrollmentReportPreviewPdfJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 300;

    public int $tries = 3;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public array $payload,
        public string $downloadName,
        public int $userId,
    ) {
        $this->onQueue('pdf-generation');
    }

    public function handle(PdfGenerationService $pdfService): void
    {
        $startedAt = microtime(true);
        $disk = config('filesystems.default');
        $directory = 'exports/enrollment-reports/'.$this->userId;
        $filename = str_ends_with($this->downloadName, '.pdf') ? $this->downloadName : $this->downloadName.'.pdf';

        Storage::disk($disk)->makeDirectory($directory);

        $temporaryFilePath = tempnam(sys_get_temp_dir(), 'enrollment_report_').'.pdf';

        try {
            $pdfService->generatePdfFromView('pdf.enrollment-report', ['data' => $this->payload], $temporaryFilePath, [
                'headless' => true,
                'no-sandbox' => true,
                'disable-dev-shm-usage' => true,
                'disable-gpu' => true,
                'no-first-run' => true,
                'disable-background-timer-throttling' => true,
                'disable-backgrounding-occluded-windows' => true,
                'disable-renderer-backgrounding' => true,
                'print-to-pdf-no-header' => true,
                'run-all-compositor-stages-before-draw' => true,
                'disable-extensions' => true,
                'virtual-time-budget' => 10000,
            ]);

            $storagePath = $directory.'/'.$filename;
            Storage::disk($disk)->put($storagePath, file_get_contents($temporaryFilePath));

            $durationMs = (int) round((microtime(true) - $startedAt) * 1000);
            $outputSize = Storage::disk($disk)->size($storagePath);

            Log::info('Queued enrollment report preview PDF generated', [
                'requester_id' => $this->userId,
                'duration_ms' => $durationMs,
                'output_size' => $outputSize,
                'disk' => $disk,
                'path' => $storagePath,
            ]);

            $user = User::query()->find($this->userId);
            if (! $user) {
                return;
            }

            Notification::make()
                ->title('Enrollment Report PDF Ready')
                ->body('Your enrollment report preview is ready to download.')
                ->success()
                ->actions([
                    Action::make('download')
                        ->label('Download PDF')
                        ->icon('heroicon-o-arrow-down-tray')
                        ->url(route('download.enrollment-report', ['filename' => $filename], false))
                        ->openUrlInNewTab(),
                ])
                ->sendToDatabase($user)
                ->send();
        } finally {
            if (file_exists($temporaryFilePath)) {
                unlink($temporaryFilePath);
            }
        }
    }

    public function failed(Throwable $throwable): void
    {
        Log::error('Queued enrollment report preview PDF generation failed', [
            'requester_id' => $this->userId,
            'error' => $throwable->getMessage(),
        ]);

        $user = User::query()->find($this->userId);
        if (! $user) {
            return;
        }

        Notification::make()
            ->title('Enrollment Report PDF Generation Failed')
            ->body('We could not generate the enrollment report preview. Please try again.')
            ->danger()
            ->sendToDatabase($user)
            ->send();
    }
}
