<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Outcome of a Sequenzy "create subscriber" API call.
 */
enum SequenzySubscribeResult
{
    case Created;
    case AlreadySubscribed;
    case NotConfigured;
    case Failed;

    /**
     * Both a fresh creation and an already-existing subscriber count as a
     * successful subscription from the user's point of view.
     */
    public function succeeded(): bool
    {
        return $this === self::Created || $this === self::AlreadySubscribed;
    }
}
