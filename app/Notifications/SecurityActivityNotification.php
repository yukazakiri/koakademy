<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Settings\SiteSettings;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Jenssegers\Agent\Agent;

final class SecurityActivityNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public string $occurredAt;

    public string $deviceSummary;

    public function __construct(
        public readonly string $actionTitle,
        public readonly string $actionSummary,
        public readonly ?string $ipAddress = null,
        ?string $userAgent = null,
    ) {
        $this->occurredAt = now()->timezone((string) config('app.timezone'))->format('M j, Y g:i A T');

        $agent = new Agent;
        $agent->setUserAgent((string) $userAgent);
        $browser = $agent->browser() ?: 'Unknown browser';
        $platform = $agent->platform() ?: 'Unknown OS';
        $this->deviceSummary = "{$browser} on {$platform}";
    }

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $appName = app(SiteSettings::class)->getAppName();

        $mail = (new MailMessage)
            ->subject("{$appName} Security Alert: {$this->actionTitle}")
            ->greeting('Security Notice')
            ->line($this->actionSummary)
            ->line("Time: {$this->occurredAt}")
            ->line("Device: {$this->deviceSummary}");

        if (filled($this->ipAddress)) {
            $mail->line("IP Address: {$this->ipAddress}");
        }

        return $mail
            ->line('If you made this change, you can safely ignore this email.')
            ->line('If you did not recognize this activity, reset your password immediately and contact your institution administrator.')
            ->action('Reset Password', route('password.request'));
    }
}
