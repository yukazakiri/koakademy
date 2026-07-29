<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

it('repairs a legacy admin transaction table without losing existing rows', function (): void {
    $defaultConnection = config('database.default');
    config([
        'database.connections.admin_transaction_schema_repair' => [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ],
    ]);
    DB::setDefaultConnection('admin_transaction_schema_repair');

    try {
        Schema::create('admin_transactions', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('admin_id');
            $table->unsignedBigInteger('transaction_id');
            $table->string('status')->default('pending');
            $table->timestamps();
        });
        DB::table('admin_transactions')->insert([
            'admin_id' => 1,
            'transaction_id' => 10,
            'status' => 'Paid',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $migration = require database_path('migrations/2026_07_29_184501_ensure_admin_transaction_ledger_columns.php');
        $migration->up();
        $migration->up();

        expect(Schema::hasColumns('admin_transactions', ['amount', 'type', 'description']))->toBeTrue();

        $existing = DB::table('admin_transactions')->where('transaction_id', 10)->sole();
        expect($existing->amount)->toBeNull()
            ->and($existing->type)->toBeNull()
            ->and($existing->description)->toBeNull();

        DB::table('admin_transactions')->insert([
            'admin_id' => 2,
            'transaction_id' => 20,
            'amount' => 4000,
            'type' => 'credit',
            'description' => 'Enrollment tuition payment',
            'status' => 'Paid',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $ledger = DB::table('admin_transactions')->where('transaction_id', 20)->sole();

        expect((float) $ledger->amount)->toBe(4000.0)
            ->and($ledger->type)->toBe('credit')
            ->and($ledger->description)->toBe('Enrollment tuition payment');
    } finally {
        DB::purge('admin_transaction_schema_repair');
        DB::setDefaultConnection((string) $defaultConnection);
    }
});
