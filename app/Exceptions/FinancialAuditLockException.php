<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

final class FinancialAuditLockException extends RuntimeException
{
    public static function studentHasFinancialRecords(int $studentId): self
    {
        return new self("Cannot permanently delete student ID {$studentId}: immutable financial ledgers, transactions, or signed compliance documents exist.");
    }

    public static function enrollmentHasFinancialRecords(int $enrollmentId): self
    {
        return new self("Cannot permanently delete enrollment ID {$enrollmentId}: tuition ledgers, additional fees, or payment allocations exist.");
    }
}
