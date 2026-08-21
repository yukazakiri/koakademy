<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\StudentTuitionUpdateRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
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
        return ['database'];
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
