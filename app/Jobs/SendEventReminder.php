<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Mail\EventReminderMail;
use App\Models\EventReminder;
use App\Notifications\EventReminderNotification;
use Exception;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

final class SendEventReminder implements ShouldQueue
{
    use Queueable;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The maximum number of seconds the job can run.
     */
    public int $timeout = 120;

    public function __construct(
        public EventReminder $reminder
    ) {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            // Check if the reminder is still pending
            if ($this->reminder->status !== 'pending') {
                Log::info("Reminder {$this->reminder->id} is no longer pending, skipping");

                return;
            }

            // Check if the event still exists and is active
            if (! $this->reminder->event || $this->reminder->event->status !== 'active') {
                $this->reminder->update([
                    'status' => 'failed',
                    'failure_reason' => 'Event is no longer active or does not exist',
                ]);

                return;
            }

            // Check if the user still exists
            if (! $this->reminder->user) {
                $this->reminder->update([
                    'status' => 'failed',
                    'failure_reason' => 'User no longer exists',
                ]);

                return;
            }

            // Send the reminder based on type
            $this->sendReminder();

            // Mark as sent
            $this->reminder->update([
                'status' => 'sent',
                'sent_at' => now(),
                'delivery_data' => [
                    'sent_via' => $this->reminder->reminder_type,
                    'sent_at' => format_timestamp_now(),
                ],
            ]);

            Log::info("Reminder {$this->reminder->id} sent successfully to user {$this->reminder->user->id}");

        } catch (Exception $e) {
            $this->handleFailure($e);
            throw $e; // Re-throw to trigger retry mechanism
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(?Exception $exception = null): void
    {
        if ($exception instanceof Exception) {
            $this->handleFailure($exception);
        }
    }

    /**
     * Send the reminder based on its type.
     */
    private function sendReminder(): void
    {
        $user = $this->reminder->user;

        switch ($this->reminder->reminder_type) {
            case 'email':
                Mail::to($user->email)->send(new EventReminderMail($this->reminder));
                break;

            case 'push':
            case 'in_app':
                $user->notify(new EventReminderNotification($this->reminder));
                break;

            case 'sms':
                // SMS implementation would go here
                // For now, we'll fall back to email if SMS is not configured
                if (empty($user->phone)) {
                    Mail::to($user->email)->send(new EventReminderMail($this->reminder));
                } else {
                    // TODO: Implement SMS sending logic
                    throw new Exception('SMS reminders not yet implemented');
                }
                break;

            default:
                throw new Exception("Unknown reminder type: {$this->reminder->reminder_type}");
        }
    }

    /**
     * Handle job failure.
     */
    private function handleFailure(Throwable $exception): void
    {
        $this->reminder->increment('retry_count');

        $this->reminder->update([
            'status' => 'failed',
            'failure_reason' => $exception->getMessage(),
            'delivery_data' => [
                'error' => $exception->getMessage(),
                'failed_at' => format_timestamp_now(),
                'retry_count' => $this->reminder->retry_count,
            ],
        ]);

        Log::error("Failed to send reminder {$this->reminder->id}: {$exception->getMessage()}", [
            'reminder_id' => $this->reminder->id,
            'event_id' => $this->reminder->event_id,
            'user_id' => $this->reminder->user_id,
            'exception' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);
    }
}
