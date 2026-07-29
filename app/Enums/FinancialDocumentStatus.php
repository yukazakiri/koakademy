<?php

declare(strict_types=1);

namespace App\Enums;

enum FinancialDocumentStatus: string
{
    case AwaitingReference = 'awaiting_reference';
    case Ready = 'ready';
    case Queued = 'queued';
    case Sent = 'sent';
    case Failed = 'failed';
    case Skipped = 'skipped';
    case Revoked = 'revoked';
}
