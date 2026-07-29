<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\StudentTransaction;
use App\Services\FinancialDocumentService;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;
use Throwable;

final readonly class StudentTransactionObserver implements ShouldHandleEventsAfterCommit
{
    public function __construct(private FinancialDocumentService $documents) {}

    public function created(StudentTransaction $studentTransaction): void
    {
        try {
            $this->documents->issueReceipt($studentTransaction);
        } catch (Throwable $throwable) {
            report($throwable);
        }
    }
}
