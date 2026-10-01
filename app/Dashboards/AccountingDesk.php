<?php

declare(strict_types=1);

namespace App\Dashboards;

use App\Dashboards\Contracts\Dashboard;
use App\Models\StudentTransaction;
use App\Models\StudentTuition;
use App\Models\Transaction;
use App\Models\User;

/**
 * Collections desk for the Accounting office (cashier, accounting officer, bursar).
 *
 * Mirrors the finance overview's query shapes so the numbers agree with
 * AdministratorFinanceController::index(), but adds a payment queue because collections staff
 * act on individual balances.
 */
final class AccountingDesk implements Dashboard
{
    public function id(): string
    {
        return 'accounting';
    }

    public function title(): string
    {
        return 'Accounting';
    }

    public function description(): string
    {
        return 'Collections performance, outstanding balances and today\'s payments.';
    }

    /**
     * @param  list<string>  $permissions
     */
    public function canView(User $user, array $permissions = []): bool
    {
        if ($permissions === []) {
            return $user->role?->isCashier() ?? false;
        }

        return array_intersect(['View:Cashier', 'view_tuition_fees', 'view_payments'], $permissions) !== [];
    }

    /**
     * @return array<string, mixed>
     */
    public function data(User $user, DashboardContext $context): array
    {
        $collected = $this->collected($context);
        [$assessed, $outstandingCount] = $this->tuitionTotals($context);

        // raw_total_amount is an accessor, so the day's total is summed in PHP after fetching,
        // mirroring AdministratorFinanceController::index() rather than a column-level sum().
        $todayRows = Transaction::query()
            ->whereBetween('transaction_date', [today()->startOfDay(), today()->endOfDay()])
            ->get();

        $todayCollection = (float) $todayRows->sum(fn (Transaction $transaction): mixed => $transaction->raw_total_amount);
        $todayTransactions = $todayRows->count();

        $collectionRate = $assessed > 0 ? round(($collected / $assessed) * 100, 2) : 0.0;

        return [
            'kpis' => [
                [
                    'label' => 'Collected',
                    'value' => $collected,
                    'description' => $context->periodLabel(),
                    'tone' => 'success',
                    'format' => 'currency',
                ],
                [
                    'label' => 'Outstanding',
                    'value' => $outstandingCount,
                    'description' => 'Students carrying a remaining balance.',
                    'tone' => $outstandingCount > 0 ? 'warning' : 'success',
                    'format' => 'number',
                ],
                [
                    'label' => 'Collection Rate',
                    'value' => sprintf('%.1f%%', $collectionRate),
                    'description' => 'Collected against assessed tuition.',
                    'tone' => $collectionRate >= 80 ? 'success' : 'warning',
                    'format' => 'percent',
                ],
                [
                    'label' => 'Collected Today',
                    'value' => $todayCollection,
                    'description' => sprintf('%d transaction(s) today.', $todayTransactions),
                    'tone' => 'info',
                    'format' => 'currency',
                ],
            ],
            'queues' => [
                [
                    'id' => 'outstanding-balances',
                    'title' => 'Outstanding balances',
                    'description' => 'Students with a remaining balance this term.',
                    'count' => $outstandingCount,
                    'severity' => $outstandingCount > 0 ? 'warning' : 'success',
                    'href' => '/administrators/finance/invoices',
                    'icon' => 'banknote',
                ],
            ],
            'trends' => [
                [
                    'id' => 'daily-collection',
                    'title' => 'Daily collections',
                    'description' => 'Payments received per day over the selected range.',
                    'data' => $this->dailyCollection($context),
                ],
            ],
            'tables' => [
                [
                    'id' => 'top-payers',
                    'title' => 'Top payers this term',
                    'description' => 'Students with the highest settled amounts.',
                    'columns' => [
                        ['key' => 'name', 'label' => 'Student'],
                        ['key' => 'student_id', 'label' => 'ID'],
                        ['key' => 'transactions', 'label' => 'Payments', 'align' => 'end'],
                        ['key' => 'total', 'label' => 'Paid', 'align' => 'end'],
                    ],
                    'rows' => $this->topPayers($context),
                ],
            ],
        ];
    }

    private function collected(DashboardContext $context): float
    {
        return (float) StudentTransaction::query()
            ->whereHas('transaction', fn ($query) => $query
                ->forAcademicPeriod($context->schoolYear, $context->semester))
            ->sum('amount');
    }

    /**
     * Assessed tuition and the number of students still owing.
     *
     * student_tuition carries its own school_year and semester, so the period filter needs no
     * join. Aggregated in one grouped pass instead of whereHas() + selectRaw(), which cannot be
     * combined because the subquery's select corrupts the aggregate column list.
     *
     * @return array{0: float, 1: int}
     */
    private function tuitionTotals(DashboardContext $context): array
    {
        $row = StudentTuition::query()
            ->where('school_year', $context->schoolYear)
            ->where('semester', $context->semester)
            ->selectRaw('coalesce(sum(overall_tuition), 0) as assessed')
            ->selectRaw('coalesce(sum(case when total_balance > 0 then 1 else 0 end), 0) as outstanding')
            ->first();

        return [
            (float) ($row?->assessed ?? 0),
            (int) ($row?->outstanding ?? 0),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function dailyCollection(DashboardContext $context): array
    {
        // Aggregated off the transactions table directly: joining student_transactions to
        // transactions alongside a whereHas() subquery corrupts the generated select list.
        return Transaction::query()
            ->forAcademicPeriod($context->schoolYear, $context->semester)
            ->whereBetween('transaction_date', [$context->from->toDateTimeString(), $context->to->toDateTimeString()])
            ->selectRaw('date(transaction_date) as day, count(*) as transactions')
            ->groupBy('day')
            ->orderBy('day')
            ->get()
            ->map(fn (Transaction $transaction): array => [
                'date' => (string) $transaction->getAttribute('day'),
                'label' => date('M j', strtotime((string) $transaction->getAttribute('day'))),
                'total' => (float) $transaction->raw_total_amount,
                'transactions' => (int) $transaction->getAttribute('transactions'),
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function topPayers(DashboardContext $context): array
    {
        return StudentTransaction::query()
            ->whereHas('transaction', fn ($query) => $query->forAcademicPeriod($context->schoolYear, $context->semester))
            ->selectRaw('student_id, sum(amount) as total, count(*) as transactions')
            ->groupBy('student_id')
            ->orderByDesc('total')
            ->limit(8)
            ->get()
            ->map(function (StudentTransaction $row): array {
                $student = $row->student()->first();

                return [
                    'name' => $student?->fullname ?? 'Unknown student',
                    'student_id' => $student?->student_id ?? '—',
                    'transactions' => (int) $row->getAttribute('transactions'),
                    'total' => (float) $row->getAttribute('total'),
                ];
            })
            ->values()
            ->all();
    }
}
