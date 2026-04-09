<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum NotificationChannel: string implements HasLabel
{
    case Mail = 'mail';
    case Database = 'database';
    case Broadcast = 'broadcast';
    case Sms = 'sms';
    case Pusher = 'pusher';

    /**
     * Get the default enabled channels for new installations.
     *
     * @return array<int, self>
     */
    public static function defaultChannels(): array
    {
        return [self::Mail, self::Database];
    }

    /**
     * Get all channel values as a flat string array.
     *
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Mail => 'Email',
            self::Database => 'In-App (Database)',
            self::Broadcast => 'Realtime (Broadcast)',
            self::Sms => 'SMS',
            self::Pusher => 'Pusher',
        };
    }

    public function getDescription(): string
    {
        return match ($this) {
            self::Mail => 'Send notifications via email using SMTP or a mail driver.',
            self::Database => 'Store notifications in the database for in-app display.',
            self::Broadcast => 'Push realtime notifications via WebSocket broadcasting.',
            self::Sms => 'Deliver notifications as SMS text messages.',
            self::Pusher => 'Pusher Channels for realtime event broadcasting.',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::Mail => 'heroicon-o-envelope',
            self::Database => 'heroicon-o-bell',
            self::Broadcast => 'heroicon-o-signal',
            self::Sms => 'heroicon-o-device-phone-mobile',
            self::Pusher => 'heroicon-o-bolt',
        };
    }

    /**
     * Whether this channel delivers in realtime.
     */
    public function isRealtime(): bool
    {
        return in_array($this, [self::Broadcast, self::Pusher]);
    }

    /**
     * Map to Laravel notification channel class strings.
     */
    public function toNotificationChannelString(): string
    {
        return match ($this) {
            self::Mail => 'mail',
            self::Database => 'database',
            self::Broadcast => 'broadcast',
            self::Sms => 'vonage',
            self::Pusher => 'broadcast',
        };
    }
}
