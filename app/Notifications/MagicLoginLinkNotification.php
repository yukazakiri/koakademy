<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Settings\SiteSettings;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class MagicLoginLinkNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $url,
        public readonly int $expiresInMinutes = 15,
        public readonly ?string $requestIp = null,
    ) {}

    /**
     * @return array<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $siteSettings = app(SiteSettings::class);
        $appName = $siteSettings->getAppName();

        return (new MailMessage)
            ->subject($appName.' — Passwordless sign-in link')
            ->view('emails.magic-login-link', [
                'appName' => $appName,
                'user' => $notifiable,
                'url' => $this->url,
                'expiresInMinutes' => $this->expiresInMinutes,
                'requestIp' => $this->requestIp,
                'requestTime' => now()->format('M j, Y \a\t g:i A'),
            ]);
    }
}
