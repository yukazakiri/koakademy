<?php

declare(strict_types=1);

namespace App\Enums;

enum NewsletterSubscribeResult: string
{
    case Created = 'created';
    case AlreadySubscribed = 'already_subscribed';
    case NotConfigured = 'not_configured';
    case Failed = 'failed';

    public function succeeded(): bool
    {
        return $this === self::Created || $this === self::AlreadySubscribed;
    }
}
