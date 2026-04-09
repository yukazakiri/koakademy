<?php

declare(strict_types=1);

namespace App\Enums;

enum PaymentMethod: string
{
    case Cash = 'Cash';
    case Check = 'Check';
    case BankTransfer = 'Bank Transfer';
    case CreditCard = 'Credit Card';
    case DebitCard = 'Debit Card';
    case OnlinePayment = 'Online Payment';
}
