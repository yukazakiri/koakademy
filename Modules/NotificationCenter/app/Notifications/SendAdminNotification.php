<?php

declare(strict_types=1);

namespace Modules\NotificationCenter\Notifications;

use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class SendAdminNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array<string>  $channels
     * @param  array<int, array<string, string>>  $actions
     */
    public function __construct(
        public array $channels,
        public string $title,
        public string $message,
        public string $type = 'info',
        public ?string $icon = null,
        public array $actions = []
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        $resolvedChannels = [];

        if (in_array('mail', $this->channels)) {
            $resolvedChannels[] = 'mail';
        }

        return $resolvedChannels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mailMessage = (new MailMessage)
            ->subject($this->title)
            ->line($this->message);

        foreach ($this->actions as $action) {
            $url = $action['url'] ?? null;
            $label = $action['label'] ?? null;

            if ($url && $label) {
                $mailMessage->action($label, $url);
            }
        }

        return $mailMessage;
    }

    public function sendFilamentNotification(object $notifiable): void
    {
        $filamentNotification = FilamentNotification::make()
            ->title($this->title)
            ->body($this->message)
            ->icon(str_starts_with($this->icon ?? 'heroicon-o-bell', 'heroicon') ? $this->icon : 'heroicon-o-bell')
            ->{$this->getFilamentTypeMethod()}();

        if (! empty($this->actions)) {
            $filamentActions = [];
            foreach ($this->actions as $action) {
                $url = $action['url'] ?? null;
                $label = $action['label'] ?? null;

                if ($url && $label) {
                    $filamentActions[] = \Filament\Actions\Action::make('action_'.md5($url))
                        ->label($label)
                        ->url($url)
                        ->openUrlInNewTab();
                }
            }
            if (! empty($filamentActions)) {
                $filamentNotification->actions($filamentActions);
            }
        }

        $filamentNotification
            ->broadcast($notifiable)
            ->sendToDatabase($notifiable);
    }

    public function toDatabase(object $notifiable): array
    {
        $filamentNotification = FilamentNotification::make()
            ->title($this->title)
            ->body($this->message)
            ->icon(str_starts_with($this->icon ?? 'heroicon-o-bell', 'heroicon') ? $this->icon : 'heroicon-o-bell')
            ->{$this->getFilamentTypeMethod()}();

        if (! empty($this->actions)) {
            $filamentActions = [];
            foreach ($this->actions as $action) {
                $url = $action['url'] ?? null;
                $label = $action['label'] ?? null;

                if ($url && $label) {
                    $filamentActions[] = \Filament\Actions\Action::make('action_'.md5($url))
                        ->label($label)
                        ->url($url)
                        ->openUrlInNewTab();
                }
            }
            if (! empty($filamentActions)) {
                $filamentNotification->actions($filamentActions);
            }
        }

        return $filamentNotification->toArray();
    }

    private function getFilamentTypeMethod(): string
    {
        return match ($this->type) {
            'success' => 'success',
            'error', 'danger' => 'danger',
            'warning' => 'warning',
            default => 'info',
        };
    }
}
