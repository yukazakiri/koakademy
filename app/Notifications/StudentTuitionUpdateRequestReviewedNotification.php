<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\StudentTuitionUpdateRequest;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class StudentTuitionUpdateRequestReviewedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly StudentTuitionUpdateRequest $request)
    {
        $this->afterCommit();
    }

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return $notifiable instanceof User ? ['database', 'mail'] : ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $this->request->loadMissing(['resolvedTransaction', 'tuitionAdjustment']);
        $resolved = $this->request->status === StudentTuitionUpdateRequest::StatusResolved;
        $studentName = (string) ($notifiable->name ?? $notifiable->email ?? 'Student');
        $period = "{$this->request->school_year} · ".($this->request->semester === 1 ? '1st' : '2nd').' Semester';

        $subject = $resolved
            ? "Tuition Update Request #{$this->request->id} Resolved ({$period})"
            : "Tuition Update Request #{$this->request->id} Review Update ({$period})";

        $mail = (new MailMessage)
            ->subject($subject)
            ->greeting("Hello {$studentName},");

        if ($resolved) {
            $mail->line("Your tuition update request (#{$this->request->id}) for {$period} has been reviewed and resolved by Finance.");
            if ($this->request->resolvedTransaction) {
                $tx = $this->request->resolvedTransaction;
                $txLine = "Payment Transaction: #{$tx->transaction_number}";
                if ($tx->invoicenumber) {
                    $txLine .= " (OR: {$tx->invoicenumber})";
                }
                $mail->line($txLine);
            }
            if ($this->request->tuitionAdjustment) {
                $adj = $this->request->tuitionAdjustment;
                $mail->line("Linked Tuition Adjustment: #{$adj->id} ({$adj->reason})");
            }
        } else {
            $mail->line("Your tuition update request (#{$this->request->id}) for {$period} was reviewed by Finance but could not be approved.");
        }

        if (filled($this->request->resolution_note)) {
            $mail->line("Finance note: {$this->request->resolution_note}");
        }

        return $mail
            ->action('View Tuition Requests', route('student.tuition.update-requests.index'))
            ->line('Thank you for keeping your academic financial records up to date.');
    }

    /** @return array<string, mixed> */
    public function toDatabase(object $notifiable): array
    {
        $resolved = $this->request->status === StudentTuitionUpdateRequest::StatusResolved;

        return [
            'title' => $resolved ? 'Tuition update completed' : 'Tuition update request reviewed',
            'message' => $resolved
                ? 'Finance completed your tuition update request. '.$this->request->resolution_note
                : 'Finance could not complete your tuition update request. '.$this->request->resolution_note,
            'type' => 'tuition_update_request_reviewed',
            'priority' => $resolved ? 'normal' : 'high',
            'icon' => $resolved ? 'heroicon-o-check-circle' : 'heroicon-o-exclamation-circle',
            'action_url' => route('student.tuition.update-requests.index'),
            'action_text' => 'View tuition requests',
            'tuition_update_request_id' => $this->request->id,
        ];
    }
}
