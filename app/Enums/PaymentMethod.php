<?php

declare(strict_types=1);

namespace App\Enums;

enum PaymentMethod: string
{
    case Cash = 'Cash';
    case Check = 'Check';
    case GCash = 'GCash';
    case Maya = 'Maya';
    case BankTransfer = 'Bank Transfer';
    case CreditCard = 'Credit Card';
    case DebitCard = 'Debit Card';
    case OnlinePayment = 'Online Payment';

    /** @return list<array{value: string, label: string}> */
    public static function options(): array
    {
        return array_map(
            fn (self $method): array => ['value' => $method->value, 'label' => $method->label()],
            self::cases(),
        );
    }

    public function label(): string
    {
        return match ($this) {
            self::GCash => 'GCash',
            default => $this->value,
        };
    }
}
