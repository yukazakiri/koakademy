<?php

declare(strict_types=1);

namespace Modules\NotificationCenter\Jobs;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Modules\NotificationCenter\Notifications\SendAdminNotification;
use Modules\NotificationCenter\Services\NotificationTemplateService;

final class SendBulkNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public array $targetAudience,
        public array $channels,
        public string $title,
        public string $content,
        public string $type = 'info',
        public ?string $icon = null,
        public array $actions = [],
        public ?string $templateSlug = null,
        public array $templateData = []
    ) {}

    public function handle(): void
    {
        $templateService = app(NotificationTemplateService::class);

        if ($this->templateSlug && $templateService->templateExists($this->templateSlug)) {
            $this->sendWithTemplate($templateService);
        } else {
            $this->sendWithBasicNotification();
        }
    }

    private function sendWithTemplate(NotificationTemplateService $templateService): void
    {
        $data = array_merge($this->templateData, [
            'title' => $this->title,
            'content' => $this->content,
            'icon' => $this->icon,
            'action_url' => $this->actions[0]['url'] ?? null,
            'action_text' => $this->actions[0]['label'] ?? null,
        ]);

        $users = $this->getTargetUsers();

        foreach ($users as $user) {
            $notification = $templateService->createNotification(
                $this->templateSlug,
                array_merge($data, ['recipient_name' => $user->name]),
                $this->channels
            );

            if ($notification) {
                if (in_array('mail', $this->channels)) {
                    $user->notify($notification);
                }

                if (in_array('database', $this->channels)) {
                    $notification->sendFilamentNotification($user);
                }
            }
        }
    }

    private function sendWithBasicNotification(): void
    {
        $notification = new SendAdminNotification(
            channels: $this->channels,
            title: $this->title,
            message: $this->content,
            type: $this->type,
            icon: $this->icon,
            actions: $this->actions
        );

        $users = $this->getTargetUsers();

        foreach ($users as $user) {
            if (in_array('mail', $this->channels)) {
                $user->notify($notification);
            }

            if (in_array('database', $this->channels)) {
                $notification->sendFilamentNotification($user);
            }
        }
    }

    private function getTargetUsers(): iterable
    {
        $query = User::query()
            ->whereNotIn('account_status', ['suspended', 'locked', 'inactive']);

        if (! in_array('all', $this->targetAudience)) {
            $query->where(function (Builder $q) {
                if (in_array('all_students', $this->targetAudience)) {
                    $q->orWhere('role', 'student');
                }

                if (in_array('all_faculty', $this->targetAudience)) {
                    $q->orWhere('role', 'faculty');
                }

                $specificRoles = array_filter(
                    $this->targetAudience,
                    fn ($item) => ! is_numeric($item) && ! in_array($item, ['all', 'all_students', 'all_faculty'])
                );
                $specificUserIds = array_filter($this->targetAudience, fn ($item) => is_numeric($item));

                if (! empty($specificRoles)) {
                    $q->orWhereIn('role', $specificRoles);
                }

                if (! empty($specificUserIds)) {
                    $q->orWhereIn('id', $specificUserIds);
                }
            });
        }

        return $query->cursor();
    }
}
