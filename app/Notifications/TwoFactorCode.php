<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class TwoFactorCode extends Notification
{
    use Queueable;

    public function __construct(public string $code) {}

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Two Factor Authentication Code')
            ->line('Your two factor authentication code is: '.$this->code)
            ->line('This code will expire in 5 minutes.')
            ->line('If you did not request this code, no further action is required.');
    }
}
