<?php

declare(strict_types=1);

use App\Models\Transaction;

it('builds first semester academic period bounds correctly', function () {
    $query = Transaction::query()->forAcademicPeriod('2024-2025', 1);

    expect($query->toSql())->toContain('between ? and ?')
        ->and($query->getBindings())->toBe([
            '2024-01-01 00:00:00',
            '2025-02-28 23:59:59',
        ]);
});

it('returns an empty-safe query for invalid school year format', function () {
    $query = Transaction::query()->forAcademicPeriod('1', 1);

    expect($query->toSql())->toContain('"transactions"."id" is null')
        ->and($query->getBindings())->toBeEmpty();
});

it('returns an empty-safe query for invalid semester value', function () {
    $query = Transaction::query()->forAcademicPeriod('2024-2025', 3);

    expect($query->toSql())->toContain('"transactions"."id" is null')
        ->and($query->getBindings())->toBeEmpty();
});
