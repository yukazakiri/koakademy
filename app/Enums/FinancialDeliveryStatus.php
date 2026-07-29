<?php

declare(strict_types=1);

namespace App\Enums;

enum FinancialDeliveryStatus: string
{
    case Queued = 'queued';
    case Processing = 'processing';
    case Sent = 'sent';
    case Failed = 'failed';
    case Cancelled = 'cancelled';
}
