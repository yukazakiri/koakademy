<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Get the migration connection name.
     */
    public function getConnection(): ?string
    {
        return config('telescope.storage.database.connection');
    }

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $connection = $this->getConnection();
        Schema::connection($connection);

        // Check if the primary key already exists
        if (! $this->hasPrimaryKey($connection)) {
            // Add primary key to the sequence column
            DB::connection($connection)->statement(
                'ALTER TABLE telescope_entries ADD PRIMARY KEY (sequence)'
            );
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $connection = $this->getConnection();
        Schema::connection($connection);

        // Drop the primary key if it exists
        if ($this->hasPrimaryKey($connection)) {
            DB::connection($connection)->statement(
                'ALTER TABLE telescope_entries DROP CONSTRAINT telescope_entries_pkey'
            );
        }
    }

    /**
     * Check if the table has a primary key.
     */
    private function hasPrimaryKey(?string $connection): bool
    {
        $databaseType = DB::connection($connection)->getPdo()->getAttribute(PDO::ATTR_DRIVER_NAME);

        if ($databaseType === 'sqlite') {
            // SQLite: use PRAGMA table_info
            $result = DB::connection($connection)->select(
                "PRAGMA table_info('telescope_entries')"
            );

            // Check if any column has pk > 0 (part of primary key)
            foreach ($result as $column) {
                if ($column->pk > 0) {
                    return true;
                }
            }

            return false;
        }
        // PostgreSQL/MySQL: use information_schema
        $result = DB::connection($connection)->select(
            "SELECT constraint_name
                 FROM information_schema.table_constraints
                 WHERE table_name = 'telescope_entries'
                 AND constraint_type = 'PRIMARY KEY'"
        );

        return count($result) > 0;

    }
};
