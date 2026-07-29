<?php

declare(strict_types=1);

namespace App\Enums;

enum FinancialDocumentType: string
{
    case Receipt = 'receipt';
    case Invoice = 'invoice';

    public function label(): string
    {
        return match ($this) {
            self::Receipt => 'Official eReceipt',
            self::Invoice => 'Official eInvoice',
        };
    }

    public function numberPrefix(): string
    {
        return match ($this) {
            self::Receipt => 'ERCP',
            self::Invoice => 'EINV',
        };
    }
}
