<?php

declare(strict_types=1);

namespace App\Filament\Handlers;

use App\Models\User;
use Filament\Actions\Exports\Models\Export;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Log;

final class ExportFailureHandler
{
    public function handleExportFailure(Export $export, string $errorMessage): void
    {
        Log::error('Export failed', [
            'export_id' => $export->id,
            'exporter' => $export->exporter,
            'user_id' => $export->user_id,
            'error' => $errorMessage,
        ]);

        // Send notification to user
        if ($export->user_id) {
            $user = User::find($export->user_id);
            if ($user) {
                Notification::make()
                    ->title('Export Failed')
                    ->body("Your export has failed: {$errorMessage}")
                    ->danger()
                    ->sendToUser($user);
            }
        }
    }

    public function handleMissingExport(string $exporterClass, int $userId, ?int $exportId = null): void
    {
        Log::warning('Export job attempted to restore missing Export model', [
            'export_id' => $exportId,
            'exporter' => $exporterClass,
            'user_id' => $userId,
        ]);

        // Send notification to user about the failed export
        $user = User::find($userId);
        if ($user) {
            $body = $exportId
                ? "Export #{$exportId} could not be completed as the export record was not found. Please try again."
                : 'Your export job could not be completed as the export record was not found. Please try again.';

            Notification::make()
                ->title('Export Failed')
                ->body($body)
                ->warning()
                ->sendToUser($user);
        }
    }
}
