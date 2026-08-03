<?php

declare(strict_types=1);

namespace App\Enums;

enum NewsletterRemoteStatus: string
{
    case Subscribed = 'subscribed';
    case OptedOut = 'opted_out';
    case Missing = 'missing';
    case Unavailable = 'unavailable';
}
