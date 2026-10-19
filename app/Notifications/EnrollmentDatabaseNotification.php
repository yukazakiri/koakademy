<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/** Snapshot only: later workflow changes must not rewrite queued notification content. */
final class EnrollmentDatabaseNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /** @param array<string, mixed> $snapshot */
    public function __construct(public readonly array $snapshot)
    {
        $this->afterCommit();
    }

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string, mixed> */
    public function toDatabase(object $notifiable): array
    {
        return $this->snapshot;
    }
}
