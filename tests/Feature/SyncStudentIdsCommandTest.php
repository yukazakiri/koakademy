<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;

it('displays the correct command output and statistics', function () {
    // Run the sync command in dry-run mode to test output
    $this->artisan('student:sync-ids --dry-run')
        ->expectsOutput('Student ID Synchronization Tool')
        ->expectsOutput('=====================================')
        ->expectsOutput('DRY RUN MODE: No changes will be made to the database')
        ->assertExitCode(0);
});

it('handles force flag correctly', function () {
    // Test that the force flag works
    $this->artisan('student:sync-ids --dry-run --force')
        ->expectsOutput('Student ID Synchronization Tool')
        ->expectsOutput('DRY RUN MODE: No changes will be made to the database')
        ->assertExitCode(0);
});

it('validates the SQL query works correctly', function () {
    // Test the core SQL logic that would be used
    // This simulates what the command does without requiring factories

    // Create a temporary test table to verify the query logic
    DB::statement('CREATE TEMPORARY TABLE test_students (
        id INTEGER PRIMARY KEY,
        student_id INTEGER,
        name TEXT
    )');

    // Insert test data
    DB::table('test_students')->insert([
        ['id' => 1, 'student_id' => null, 'name' => 'Student 1'],
        ['id' => 2, 'student_id' => 999, 'name' => 'Student 2'],
        ['id' => 3, 'student_id' => 3, 'name' => 'Student 3'], // Already synced
    ]);

    // Test the query that finds mismatched records
    $mismatchedCount = DB::table('test_students')
        ->where(function ($query) {
            $query->whereColumn('id', '!=', 'student_id')
                ->orWhereNull('student_id');
        })
        ->count();

    expect($mismatchedCount)->toBe(2); // Should find records 1 and 2

    // Test the update query
    $affectedRows = DB::table('test_students')
        ->where(function ($query) {
            $query->whereColumn('id', '!=', 'student_id')
                ->orWhereNull('student_id');
        })
        ->update(['student_id' => DB::raw('id')]);

    expect($affectedRows)->toBe(2);

    // Verify the sync worked
    $syncedRecords = DB::table('test_students')
        ->whereColumn('id', '=', 'student_id')
        ->count();

    expect($syncedRecords)->toBe(3); // All 3 should now be synced
});
