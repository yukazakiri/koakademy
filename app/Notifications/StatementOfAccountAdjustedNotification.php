<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\User;
use App\Settings\SiteSettings;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class StatementOfAccountAdjustedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array<string, float>  $before
     * @param  array<string, float>  $after
     */
    public function __construct(
        private readonly int $studentId,
        private readonly string $studentName,
        private readonly string $schoolYear,
        private readonly int $semester,
        private readonly array $before,
        private readonly array $after,
        private readonly ?string $adjustmentNote = null,
        private readonly ?int $changedByUserId = null,
        private readonly ?string $changedByName = null,
    ) {
        $this->afterCommit();
    }

    /**
     * Portal users receive both an in-app notification and an email.
     * Fallback (email-only) notifiables only receive the email.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        if ($notifiable instanceof User) {
            return ['mail', 'database'];
        }

        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject('Your Statement of Account has been adjusted')
            ->greeting('Hello '.$this->studentName.',')
            ->line(sprintf(
                'Your Statement of Account for **%s • %s Semester** has been adjusted. Please review the updated assessment below.',
                $this->schoolYear,
                $this->semester === 1 ? '1st' : '2nd'
            ))
            ->line('**Updated charges:**')
            ->lines($this->breakdownLines());

        if ($this->adjustmentNote !== null && mb_trim($this->adjustmentNote) !== '') {
            $mail->line('**Note from the registrar:** '.$this->adjustmentNote);
        }

        if ($this->changedByName !== null && $this->changedByName !== '') {
            $mail->line(sprintf('This adjustment was made by %s.', $this->changedByName));
        }

        return $mail
            ->action('View Statement of Account', route('student.tuition.index'))
            ->line('If you believe this adjustment was made in error, please contact the registrar or accounting office as soon as possible.');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Statement of Account adjusted',
            'message' => sprintf(
                'Your Statement of Account for %s • %s Semester was updated. New balance due: %s.',
                $this->schoolYear,
                $this->semester === 1 ? '1st' : '2nd',
                $this->formatAmount((float) ($this->after['total_balance'] ?? 0))
            ),
            'type' => 'statement_of_account_adjusted',
            'priority' => 'high',
            'icon' => 'heroicon-o-banknotes',
            'student_id' => $this->studentId,
            'school_year' => $this->schoolYear,
            'semester' => $this->semester,
            'before' => $this->before,
            'after' => $this->after,
            'adjustment_note' => $this->adjustmentNote,
            'changed_by_user_id' => $this->changedByUserId,
            'changed_by_name' => $this->changedByName,
            'action_url' => route('student.tuition.index'),
        ];
    }

    /**
     * @return array<int, string>
     */
    private function breakdownLines(): array
    {
        $labels = [
            'total_lectures' => 'Lecture Fees',
            'total_laboratory' => 'Laboratory Fees',
            'total_miscelaneous_fees' => 'Miscellaneous Fees',
            'discount' => 'Discount',
            'downpayment' => 'Downpayment',
            'total_tuition' => 'Total Tuition (Lecture + Lab)',
            'overall_tuition' => 'Overall Tuition',
            'total_balance' => 'Balance Due',
        ];

        $lines = [];

        foreach ($labels as $key => $label) {
            if (! array_key_exists($key, $this->before) || ! array_key_exists($key, $this->after)) {
                continue;
            }

            $lines[] = sprintf(
                '- %s: %s → %s',
                $label,
                $this->formatValue($key, (float) $this->before[$key]),
                $this->formatValue($key, (float) $this->after[$key])
            );
        }

        return $lines === [] ? ['No numeric changes were recorded.'] : $lines;
    }

    private function formatValue(string $key, float $value): string
    {
        if ($key === 'discount') {
            return number_format($value, 0).'%';
        }

        return $this->formatAmount($value);
    }

    private function formatAmount(float $value): string
    {
        $currency = app(SiteSettings::class)->getCurrency();
        $symbol = $currency === 'USD' ? '$' : '₱';

        return $symbol.' '.number_format($value, 2);
    }
}
