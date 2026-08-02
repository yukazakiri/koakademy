<?php

declare(strict_types=1);

namespace App\Enums;

enum NewsletterSubscriptionStatus: string
{
    case Subscribed = 'subscribed';
    case Declined = 'declined';

    public function getLabel(): string
    {
        return match ($this) {
            self::Subscribed => 'Subscribed',
            self::Declined => 'Declined',
        };
    }
}
