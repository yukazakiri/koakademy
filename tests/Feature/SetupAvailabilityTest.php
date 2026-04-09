<?php

declare(strict_types=1);

use App\Models\GeneralSetting;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

it('shows the setup screen when the database is empty', function (): void {
    truncateAllSetupTables();

    $this->get('/setup')->assertOk();

    expect(GeneralSetting::query()->exists())->toBeFalse();
});

it('marks the application as setup when data exists and blocks access', function (): void {
    User::factory()->create();

    $this->get('/setup')->assertForbidden();

    $settings = GeneralSetting::query()->first();
    expect($settings)->not->toBeNull();
    expect($settings?->is_setup)->toBeTrue();
});

it('blocks access to setup after setup is complete', function (): void {
    GeneralSetting::factory()->create(['is_setup' => true]);
    $user = User::factory()->create();

    $this->actingAs($user)->get('/setup')->assertForbidden();
});

function truncateAllSetupTables(): void
{
    $skipTables = [
        'migrations',
        'failed_jobs',
        'password_reset_tokens',
        'personal_access_tokens',
        'cache',
        'cache_locks',
        'sessions',
        'job_batches',
        'jobs',
        'telescope_entries',
        'telescope_entries_tags',
        'telescope_monitoring',
        'pulse_entries',
        'pulse_aggregates',
        'pulse_values',
    ];

    $tables = array_diff(Schema::getConnection()->getSchemaBuilder()->getTableListing(), $skipTables);
    $driver = DB::getDriverName();

    if ($driver === 'pgsql') {
        foreach ($tables as $table) {
            $wrapped = DB::getQueryGrammar()->wrapTable($table);
            DB::statement(sprintf('TRUNCATE TABLE %s RESTART IDENTITY CASCADE', $wrapped));
        }

        return;
    }

    if ($driver === 'mysql') {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
    }

    foreach ($tables as $table) {
        DB::table($table)->truncate();
    }

    if ($driver === 'mysql') {
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }
}
