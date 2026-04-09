<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\EventReminder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

final class EventReminderMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     */
    public function __construct(
        public EventReminder $reminder
    ) {
        //
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $event = $this->reminder->event;
        $timeInfo = $this->getTimeInfo();

        return new Envelope(
            subject: "Reminder: {$event->title} {$timeInfo}",
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'mail.event-reminder',
            with: [
                'reminder' => $this->reminder,
                'event' => $this->reminder->event,
                'user' => $this->reminder->user,
                'timeInfo' => $this->getTimeInfo(),
                'timeUntilEvent' => $this->getTimeUntilEvent(),
                'eventUrl' => $this->getEventUrl(),
            ],
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }

    /**
     * Get formatted time information for the event.
     */
    private function getTimeInfo(): string
    {
        $event = $this->reminder->event;

        if ($event->is_all_day) {
            return "on {$event->start_datetime->format('F j, Y')}";
        }

        if ($event->start_datetime->format('Y-m-d') === $event->end_datetime->format('Y-m-d')) {
            return "on {$event->start_datetime->format('F j, Y')} from {$event->start_datetime->format('g:i A')} to {$event->end_datetime->format('g:i A')}";
        }

        return "from {$event->start_datetime->format('F j, Y g:i A')} to {$event->end_datetime->format('F j, Y g:i A')}";
    }

    /**
     * Get human-readable time until event.
     */
    private function getTimeUntilEvent(): string
    {
        $event = $this->reminder->event;

        return $event->start_datetime->diffForHumans();
    }

    /**
     * Get URL to view the event.
     */
    private function getEventUrl(): string
    {
        // This would be the frontend URL for the event
        // For now, return a placeholder
        return url("/events/{$this->reminder->event->id}");
    }
}
