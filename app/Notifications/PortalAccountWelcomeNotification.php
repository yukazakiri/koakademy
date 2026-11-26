<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Settings\SiteSettings;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class PortalAccountWelcomeNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $token,
        public readonly ?string $identifier = null,
        public readonly string $identifierLabel = 'ID Number',
        public readonly string $portalLabel = 'Portal',
    ) {
        $this->afterCommit();
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
        $settings = app(SiteSettings::class);
        $appName = $settings->getAppName();
        $organizationName = $settings->getOrganizationName();
        $email = (string) ($notifiable->email ?? '');
        $name = (string) ($notifiable->name ?? 'there');

        $activationUrl = route('password.reset', [
            'token' => $this->token,
            'email' => $email,
        ]);

        $message = (new MailMessage)
            ->subject("Welcome & Activate Your {$appName} {$this->portalLabel} Account")
            ->greeting("Welcome, {$name}!")
            ->line("Your {$this->portalLabel} account for {$organizationName} has been provisioned and is ready to activate.")
            ->line("**Institutional Email:** {$email}");

        if (filled($this->identifier)) {
            $message->line("**{$this->identifierLabel}:** {$this->identifier}");
        }

        return $message
            ->action('Set Password & Activate Account', $activationUrl)
            ->line('Use the button above to choose your password and sign in to your portal.')
            ->line('For your security, this activation link will expire automatically. If it expires, you can request a new link from the Forgot Password page.');
    }
}
