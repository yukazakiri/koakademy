<?php

declare(strict_types=1);

namespace Modules\NotificationCenter\Notifications;

use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\View;

abstract class BaseTemplateNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected string $templateSlug;

    protected array $data = [];

    protected array $channels = ['mail', 'database'];

    public function __construct(array $data = [], array $channels = ['mail', 'database'])
    {
        $this->data = $data;
        $this->channels = $channels;
    }

    abstract protected function getSubject(): string;

    abstract protected function getTemplatePath(): string;

    abstract protected function getFilamentIcon(): string;

    abstract protected function getFilamentTypeMethod(): string;

    final public function via(object $notifiable): array
    {
        return $this->channels;
    }

    final public function toMail(object $notifiable): MailMessage
    {
        $viewData = array_merge($this->data, [
            'recipient_name' => $notifiable->name ?? $notifiable->email,
            'subject' => $this->getSubject(),
        ]);

        $templatePath = $this->getTemplatePath();

        if (View::exists($templatePath)) {
            return (new MailMessage)
                ->subject($this->getSubject())
                ->view($templatePath, $viewData);
        }

        return $this->getDefaultMailMessage($notifiable);
    }

    final public function sendFilamentNotification(object $notifiable): void
    {
        $filamentNotification = FilamentNotification::make()
            ->title($this->getSubject())
            ->body($this->data['content'] ?? '')
            ->icon($this->getFilamentIcon())
            ->{$this->getFilamentTypeMethod()}();

        if (! empty($this->data['action_url'])) {
            $filamentNotification->actions([
                \Filament\Actions\Action::make('view')
                    ->label($this->data['action_text'] ?? 'View')
                    ->url($this->data['action_url'])
                    ->openUrlInNewTab(),
            ]);
        }

        $filamentNotification
            ->broadcast($notifiable)
            ->sendToDatabase($notifiable);
    }

    final public function toDatabase(object $notifiable): array
    {
        $filamentNotification = FilamentNotification::make()
            ->title($this->getSubject())
            ->body($this->data['content'] ?? '')
            ->icon($this->getFilamentIcon())
            ->{$this->getFilamentTypeMethod()}();

        if (! empty($this->data['action_url'])) {
            $filamentNotification->actions([
                \Filament\Actions\Action::make('view')
                    ->label($this->data['action_text'] ?? 'View')
                    ->url($this->data['action_url'])
                    ->openUrlInNewTab(),
            ]);
        }

        return $filamentNotification->toArray();
    }

    protected function getDefaultMailMessage(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject($this->getSubject())
            ->line($this->data['content'] ?? '')
            ->action($this->data['action_text'] ?? 'View', $this->data['action_url'] ?? '#');
    }
}
