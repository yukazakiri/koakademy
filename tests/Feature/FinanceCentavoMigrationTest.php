<?php

declare(strict_types=1);

use Illuminate\Database\PostgresConnection;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Grammars\PostgresGrammar;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

function financeColumn(string $table, string $column): array
{
    return collect(Schema::getColumns($table))
        ->firstWhere('name', $column);
}

function prepareFinanceCentavoSchema(bool $nullable): void
{
    Schema::create('student_transactions', function (Blueprint $table) use ($nullable): void {
        $table->id();
        $table->integer('amount')->nullable($nullable);
    });

    Schema::create('student_tuition', function (Blueprint $table) use ($nullable): void {
        $table->id();
        $table->bigInteger('paid')->nullable($nullable);
    });

    Schema::create('inventory_stock_movements', function (Blueprint $table): void {
        $table->id();
    });
}

function runFinanceCentavoMigration(): void
{
    $migration = require database_path('migrations/2026_08_10_000001_preserve_centavos_for_finance_payments.php');
    $migration->up();
}

it('preserves nullable finance values while changing them to decimal amounts', function (): void {
    $defaultConnection = config('database.default');
    config([
        'database.connections.finance_centavos_nullable' => [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ],
    ]);
    DB::setDefaultConnection('finance_centavos_nullable');

    try {
        prepareFinanceCentavoSchema(nullable: true);
        DB::table('student_transactions')->insert([['amount' => null], ['amount' => 4000]]);
        DB::table('student_tuition')->insert([['paid' => null], ['paid' => 1750]]);

        runFinanceCentavoMigration();
        runFinanceCentavoMigration();

        expect(financeColumn('student_transactions', 'amount')['nullable'])->toBeTrue()
            ->and(financeColumn('student_tuition', 'paid')['nullable'])->toBeTrue()
            ->and(DB::table('student_transactions')->orderBy('id')->pluck('amount')->all())
            ->toEqual([null, 4000])
            ->and(DB::table('student_tuition')->orderBy('id')->pluck('paid')->all())
            ->toEqual([null, 1750]);

        DB::table('student_transactions')->insert(['amount' => 25.75]);
        DB::table('student_tuition')->insert(['paid' => 19.95]);

        expect((float) DB::table('student_transactions')->latest('id')->value('amount'))->toBe(25.75)
            ->and((float) DB::table('student_tuition')->latest('id')->value('paid'))->toBe(19.95);
    } finally {
        DB::purge('finance_centavos_nullable');
        DB::setDefaultConnection((string) $defaultConnection);
    }
});

it('preserves non-null finance constraints while changing them to decimal amounts', function (): void {
    $defaultConnection = config('database.default');
    config([
        'database.connections.finance_centavos_required' => [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ],
    ]);
    DB::setDefaultConnection('finance_centavos_required');

    try {
        prepareFinanceCentavoSchema(nullable: false);
        DB::table('student_transactions')->insert(['amount' => 4000]);
        DB::table('student_tuition')->insert(['paid' => 1750]);

        runFinanceCentavoMigration();

        expect(financeColumn('student_transactions', 'amount')['nullable'])->toBeFalse()
            ->and(financeColumn('student_tuition', 'paid')['nullable'])->toBeFalse()
            ->and((float) DB::table('student_transactions')->value('amount'))->toBe(4000.0)
            ->and((float) DB::table('student_tuition')->value('paid'))->toBe(1750.0);

        expect(fn () => DB::table('student_transactions')->insert(['amount' => null]))
            ->toThrow(QueryException::class);
    } finally {
        DB::purge('finance_centavos_required');
        DB::setDefaultConnection((string) $defaultConnection);
    }
});

it('generates PostgreSQL changes with the requested nullability', function (): void {
    $connection = new PostgresConnection(new PDO('sqlite::memory:'));
    $connection->setSchemaGrammar(new PostgresGrammar($connection));

    $nullableBlueprint = new Blueprint($connection, 'student_transactions', function (Blueprint $table): void {
        $table->decimal('amount', 12, 2)->nullable()->change();
    });
    $requiredBlueprint = new Blueprint($connection, 'student_transactions', function (Blueprint $table): void {
        $table->decimal('amount', 12, 2)->nullable(false)->change();
    });

    expect(implode(' ', $nullableBlueprint->toSql()))
        ->toContain('alter column "amount" drop not null')
        ->not->toContain('alter column "amount" set not null')
        ->and(implode(' ', $requiredBlueprint->toSql()))
        ->toContain('alter column "amount" set not null');
});
