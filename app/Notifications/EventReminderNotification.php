<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\EventReminder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class EventReminderNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public EventReminder $reminder
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
        $channels = ['database']; // Always store in database for in-app notifications

        // Add push notifications if the reminder type is 'push'
        if ($this->reminder->reminder_type === 'push') {
            $channels[] = 'broadcast'; // For real-time push notifications
        }

        return $channels;
    }

    /**
     * Get the database representation of the notification.
     */
    public function toDatabase(object $notifiable): array
    {
        $event = $this->reminder->event;
        $timeUntilEvent = $event->start_datetime->diffForHumans();

        return [
            'title' => "Event Reminder: {$event->title}",
            'message' => $this->reminder->message ?: "Don't forget about {$event->title} {$timeUntilEvent}!",
            'event_id' => $event->id,
            'reminder_id' => $this->reminder->id,
            'event_title' => $event->title,
            'event_start' => format_timestamp($event->start_datetime),
            'event_location' => $event->location,
            'time_until_event' => $timeUntilEvent,
            'reminder_type' => $this->reminder->reminder_type,
            'minutes_before' => $this->reminder->minutes_before,
            'action_url' => url("/events/{$event->id}"), // Frontend URL
            'icon' => '📅',
            'color' => 'warning', // Filament notification color
        ];
    }

    /**
     * Get the broadcastable representation of the notification (for push notifications).
     */
    public function toBroadcast(object $notifiable): array
    {
        $event = $this->reminder->event;
        $timeUntilEvent = $event->start_datetime->diffForHumans();

        return [
            'title' => "Event Reminder: {$event->title}",
            'body' => $this->reminder->message ?: "Don't forget about {$event->title} {$timeUntilEvent}!",
            'icon' => '/favicon.ico', // App icon for push notifications
            'badge' => '/favicon.ico',
            'tag' => "event-reminder-{$this->reminder->id}",
            'data' => [
                'event_id' => $event->id,
                'reminder_id' => $this->reminder->id,
                'action_url' => url("/events/{$event->id}"),
            ],
            'actions' => [
                [
                    'action' => 'view',
                    'title' => 'View Event',
                ],
                [
                    'action' => 'dismiss',
                    'title' => 'Dismiss',
                ],
            ],
        ];
    }

    /**
     * Get the mail representation of the notification.
     * This shouldn't be used since we have a dedicated Mailable,
     * but included for completeness.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $event = $this->reminder->event;
        $timeUntilEvent = $event->start_datetime->diffForHumans();

        $mailMessage = (new MailMessage)
            ->subject("Reminder: {$event->title}")
            ->greeting("Hi {$notifiable->name}!")
            ->line("This is a reminder about your upcoming event: **{$event->title}**")
            ->line("The event is {$timeUntilEvent}.");

        if ($event->location) {
            $mailMessage->line("**Location:** {$event->location}");
        }

        if ($event->start_datetime->format('Y-m-d') !== $event->end_datetime->format('Y-m-d')) {
            $mailMessage->line("**Starts:** {$event->start_datetime->format('F j, Y g:i A')}")
                ->line("**Ends:** {$event->end_datetime->format('F j, Y g:i A')}");
        } else {
            $timeRange = $event->is_all_day
                ? 'All day'
                : $event->start_datetime->format('g:i A').' - '.$event->end_datetime->format('g:i A');
            $mailMessage->line("**When:** {$event->start_datetime->format('F j, Y')} at {$timeRange}");
        }

        if ($this->reminder->message) {
            $mailMessage->line("**Custom Message:** {$this->reminder->message}");
        }

        return $mailMessage
            ->action('View Event Details', url("/events/{$event->id}"))
            ->line('We look forward to seeing you there!');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return $this->toDatabase($notifiable);
    }

    /**
     * Determine if the notification should be queued.
     */
    public function shouldQueue(): bool
    {
        return true;
    }

    /**
     * Get the notification's channels that should be used for queuing.
     */
    public function viaQueues(): array
    {
        return [
            'database' => 'notifications',
            'broadcast' => 'notifications',
        ];
    }
}
