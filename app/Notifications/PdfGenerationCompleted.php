<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\StudentEnrollment;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

final class PdfGenerationCompleted extends Notification
{
    use Queueable;

    public function __construct(
        private StudentEnrollment $studentEnrollment,
        private bool $failed = false,
        private string $message = '',
        private ?string $errorMessage = null
    ) {
        //
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * Get the database representation of the notification.
     */
    public function toDatabase(User $user): array
    {
        if ($this->failed) {
            return FilamentNotification::make()
                ->title('PDF Generation Failed')
                ->body(
                    sprintf('PDF generation failed for %s (ID: %s): %s', $this->studentEnrollment->student->full_name, $this->studentEnrollment->student_id, $this->errorMessage)
                )
                ->danger()
                ->icon('heroicon-o-exclamation-triangle')
                ->getDatabaseMessage();
        }

        return FilamentNotification::make()
            ->title('PDF Generation Complete')
            ->body(
                sprintf('Assessment PDF has been generated successfully for %s (ID: %s)', $this->studentEnrollment->student->full_name, $this->studentEnrollment->student_id)
            )
            ->success()
            ->icon('heroicon-o-document-check')
            ->actions([
                Action::make('download')
                    ->label('Download PDF')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->url(
                        route('assessment.download', [
                            'record' => $this->studentEnrollment->id,
                        ], false)
                    )
                    ->openUrlInNewTab(true),
                Action::make('view_enrollment')
                    ->label('View Enrollment')
                    ->icon('heroicon-o-eye')
                    ->url(
                        route(
                            'filament.admin.resources.student-enrollments.view',
                            ['record' => $this->studentEnrollment->id]
                        )
                    )
                    ->openUrlInNewTab(true),
            ])
            ->getDatabaseMessage();
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'enrollment_id' => $this->studentEnrollment->id,
            'student_id' => $this->studentEnrollment->student_id,
            'student_name' => $this->studentEnrollment->student->full_name ?? 'Unknown',
            'failed' => $this->failed,
            'message' => $this->message,
            'error_message' => $this->errorMessage,
        ];
    }
}
