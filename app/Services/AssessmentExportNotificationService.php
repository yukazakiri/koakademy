<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AssessmentExport;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\DB;

final class AssessmentExportNotificationService
{
    public function sendTerminal(AssessmentExport $export): void
    {
        $export = AssessmentExport::withoutSchoolScope()
            ->forSchool((int) $export->school_id)
            ->find($export->id);
        if ($export === null || $export->terminal_notified_at !== null || ! $export->isTerminal()) {
            return;
        }

        $user = $export->user;
        if ($user === null) {
            return;
        }

        $notification = Notification::make()->body($this->body($export));

        if ($export->status === 'completed' && $export->output_path !== null) {
            $actions = [
                Action::make('download')->label('Download PDF')->url(route('download.bulk-assessment', $export))->openUrlInNewTab(),
            ];
            if ($export->report_path !== null) {
                $actions[] = Action::make('report')->label('Skipped report')->url(route('download.bulk-assessment-report', $export))->openUrlInNewTab();
            }
            $notification->title('Bulk Assessment PDF Ready')->success()->actions($actions);
        } elseif ($export->status === 'completed') {
            $notification->title('Bulk Assessment Export Finished')->info();
            if ($export->report_path !== null) {
                $notification->actions([
                    Action::make('report')->label('Skipped report')->url(route('download.bulk-assessment-report', $export))->openUrlInNewTab(),
                ]);
            }
        } elseif ($export->status === 'cancelled') {
            $notification->title('Bulk Assessment Export Cancelled')->warning();
        } else {
            $notification->title('Bulk Assessment Generation Failed')->danger()->actions([
                Action::make('details')
                    ->label('View details')
                    ->url(route('administrators.enrollments.index', ['assessment_export' => $export->id])),
            ]);
        }

        $sent = DB::transaction(function () use ($export, $notification, $user): bool {
            $locked = AssessmentExport::withoutSchoolScope()
                ->forSchool((int) $export->school_id)
                ->lockForUpdate()
                ->find($export->id);
            if ($locked === null || $locked->terminal_notified_at !== null || ! $locked->isTerminal()) {
                return false;
            }

            $notification->sendToDatabase($user);
            $locked->forceFill(['terminal_notified_at' => now()])->save();

            return true;
        });
        if (! $sent) {
            return;
        }

        $notification->broadcast($user);
    }

    private function body(AssessmentExport $export): string
    {
        if ($export->status === 'failed') {
            return sprintf('%s Reference: %s', $export->error_message ?? 'The export failed.', $export->id);
        }

        return $export->message ?? 'The assessment export finished.';
    }
}
