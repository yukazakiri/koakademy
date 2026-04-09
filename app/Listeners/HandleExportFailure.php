<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Filament\Handlers\ExportFailureHandler;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Queue\Events\JobFailed;

final readonly class HandleExportFailure
{
    public function __construct(
        private ExportFailureHandler $failureHandler
    ) {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(JobFailed $event): void
    {
        $job = $event->job;
        $exception = $event->exception;

        // Check if this is an export-related job
        $jobClass = $job->resolveName();

        if (! str_contains($jobClass, 'Filament\\Actions\\Exports')) {
            return;
        }

        // Try to extract the export from the job data
        $payload = $job->payload();
        $command = null;

        if (isset($payload['data']['command'])) {
            $command = unserialize($payload['data']['command']);
        }

        // Check if the exception is related to missing Export model
        if (str_contains($exception->getMessage(), 'No query results for model [Filament\\Actions\\Exports\\Models\\Export]')) {
            if ($command instanceof Export) {
                $this->failureHandler->handleMissingExport(
                    $command->exporter,
                    $command->user_id,
                    $command->id
                );
            }
        } elseif ($command instanceof Export) {
            // For other export errors, try to get more info
            $this->failureHandler->handleExportFailure(
                $command,
                $exception->getMessage()
            );
        }
    }
}
