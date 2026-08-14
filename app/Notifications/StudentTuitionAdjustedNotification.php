<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class StudentTuitionAdjustedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array<string, mixed>  $beforeSnapshot
     * @param  array<string, mixed>  $afterSnapshot
     */
    public function __construct(
        private readonly array $beforeSnapshot,
        private readonly array $afterSnapshot,
        private readonly string $reason,
        private readonly ?string $actorName,
    ) {
        $this->afterCommit();
    }

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return $notifiable instanceof User ? ['database'] : ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Your student tuition was adjusted')
            ->greeting('Hello,')
            ->line(sprintf('Your tuition assessment for %s, Semester %s was updated.', $this->afterSnapshot['school_year'], $this->afterSnapshot['semester']))
            ->line('Reason: '.$this->reason)
            ->line($this->changeLine('Total fees', 'total_fees'))
            ->line($this->changeLine('Paid / opening amount', 'paid'))
            ->line('Account position: '.$this->accountPosition($this->beforeSnapshot).' → '.$this->accountPosition($this->afterSnapshot))
            ->line($this->installmentLine())
            ->when($this->actorName !== null, fn (MailMessage $mail): MailMessage => $mail->line('Updated by: '.$this->actorName))
            ->action('View Tuition', url('/student/tuition'))
            ->line('If anything looks incorrect, please contact the Finance Office.');
    }

    /** @return array<string, mixed> */
    public function toDatabase(object $notifiable): array
    {
        return [
            'title' => 'Student tuition adjusted',
            'message' => sprintf(
                'Your %s Semester %s tuition is now %s with %s.',
                $this->afterSnapshot['school_year'],
                $this->afterSnapshot['semester'],
                $this->money((float) $this->afterSnapshot['total_fees']),
                (float) ($this->afterSnapshot['credit'] ?? 0) > 0
                    ? $this->money((float) $this->afterSnapshot['credit']).' credit'
                    : $this->money((float) $this->afterSnapshot['balance_due']).' remaining',
            ),
            'type' => 'tuition_adjusted',
            'priority' => 'high',
            'icon' => 'heroicon-o-banknotes',
            'reason' => $this->reason,
            'before' => $this->beforeSnapshot,
            'tuition' => $this->afterSnapshot,
            'action_url' => url('/student/tuition'),
            'action_text' => 'View tuition',
        ];
    }

    private function installmentLine(): string
    {
        $installments = collect($this->afterSnapshot['installments'] ?? [])->keyBy('term');

        return sprintf(
            'Installments — Prelim: %s, Midterm: %s, Finals: %s',
            $this->money((float) data_get($installments, 'prelim.amount', 0)),
            $this->money((float) data_get($installments, 'midterm.amount', 0)),
            $this->money((float) data_get($installments, 'finals.amount', 0)),
        );
    }

    private function money(float $amount): string
    {
        return '₱'.number_format($amount, 2);
    }

    private function changeLine(string $label, string $key): string
    {
        return sprintf(
            '%s: %s → %s',
            $label,
            $this->money((float) ($this->beforeSnapshot[$key] ?? 0)),
            $this->money((float) ($this->afterSnapshot[$key] ?? 0)),
        );
    }

    /** @param array<string, mixed> $snapshot */
    private function accountPosition(array $snapshot): string
    {
        return (float) ($snapshot['credit'] ?? 0) > 0
            ? $this->money((float) $snapshot['credit']).' credit'
            : $this->money((float) ($snapshot['balance_due'] ?? 0)).' balance';
    }
}
