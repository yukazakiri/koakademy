<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\GeneralSetting;
use App\Models\School;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia;

it('aggregates finance dashboard KPIs and 7-day trend without 7-day query loops or redundant tuition scans', function (): void {
    $school = School::factory()->create();
    GeneralSetting::factory()->create([
        'semester' => 1,
        'school_starting_date' => now()->startOfYear()->toDateString(),
        'school_ending_date' => now()->endOfYear()->toDateString(),
    ]);

    $admin = User::factory()->create([
        'role' => UserRole::SuperAdmin,
        'school_id' => $school->id,
    ]);

    $tx = new Transaction;
    $tx->forceFill([
        'user_id' => $admin->id,
        'description' => 'Tuition payment',
        'transaction_number' => 'TX-PERF-580-1',
        'invoicenumber' => 'OR-PERF-580-1',
        'payment_method' => 'Cash',
        'status' => 'Paid',
        'settlements' => ['tuition' => 2500.0, 'misc' => 500.0],
        'transaction_date' => now(),
    ])->save();

    DB::flushQueryLog();
    DB::enableQueryLog();

    $this->actingAs($admin)
        ->get(route('administrators.finance.index'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('stats.today_collection', 3000)
            ->where('stats.today_transactions', 1)
            ->has('daily_collection', 7));

    $queries = collect(DB::getQueryLog())->pluck('query');
    DB::disableQueryLog();

    $tuitionQueries = $queries->filter(static fn (string $sql): bool => preg_match('/from [`"]?student_tuition[`"]?(?:\s|$)/i', $sql) === 1)->count();
    $transactionDateQueries = $queries->filter(static fn (string $sql): bool => str_contains($sql, 'transaction_date'))->count();

    // Collapsed from 4 tuition queries to <= 2 (1 aggregate + 1 collection queue) and 9 transaction_date queries to 1
    expect($tuitionQueries)->toBeLessThanOrEqual(2)
        ->and($transactionDateQueries)->toBe(1);
});
