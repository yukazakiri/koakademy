<?php

declare(strict_types=1);

namespace App\Finance;

use App\Models\Transaction;

final readonly class RecordedFinancePayment
{
    public function __construct(
        public Transaction $transaction,
        public bool $duplicate,
    ) {}
}
