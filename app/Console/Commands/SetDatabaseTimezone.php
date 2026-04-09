<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

final class SetDatabaseTimezone extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'db:set-timezone 
                            {timezone? : The timezone to set (defaults to app.timezone config)}
                            {--check : Only check the current timezone without making changes}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Set the PostgreSQL database timezone to match the application timezone';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $connection = config('database.default');

        if (! is_string($connection)) {
            $this->error('Database connection configuration is invalid.');

            return self::FAILURE;
        }

        if ($connection !== 'pgsql') {
            $this->warn("This command is designed for PostgreSQL. Current connection: {$connection}");

            if (! $this->confirm('Do you want to continue anyway?')) {
                return self::FAILURE;
            }
        }

        $targetTimezone = $this->argument('timezone') ?? config('app.timezone', 'Asia/Manila');

        if (! is_string($targetTimezone)) {
            $targetTimezone = 'Asia/Manila';
        }

        $databaseName = config('database.connections.pgsql.database');

        if (! is_string($databaseName)) {
            $this->error('Database name configuration is invalid.');

            return self::FAILURE;
        }

        $this->info('PostgreSQL Timezone Configuration');
        $this->info('==================================');
        $this->newLine();

        // Check current timezone
        try {
            $result = DB::scalar('SHOW timezone');
            $currentTimezone = is_string($result) ? $result : 'Unknown';

            $this->table(
                ['Setting', 'Value'],
                [
                    ['App Timezone', config('app.timezone')],
                    ['Target Timezone', $targetTimezone],
                    ['Current DB Timezone', $currentTimezone],
                    ['Database Name', $databaseName],
                ]
            );
        } catch (Exception $e) {
            $this->error('Failed to connect to database: '.$e->getMessage());

            return self::FAILURE;
        }

        if ($this->option('check')) {
            if ($currentTimezone === $targetTimezone) {
                $this->info("✓ Database timezone is correctly set to {$targetTimezone}");

                return self::SUCCESS;
            }

            $this->warn("✗ Database timezone ({$currentTimezone}) does not match target ({$targetTimezone})");

            return self::FAILURE;
        }

        if ($currentTimezone === $targetTimezone) {
            $this->info("✓ Database timezone is already set to {$targetTimezone}");

            return self::SUCCESS;
        }

        $this->newLine();

        if (! $this->confirm("Set database timezone to {$targetTimezone}?", true)) {
            $this->info('Operation cancelled.');

            return self::SUCCESS;
        }

        try {
            // Set timezone at database level (persistent)
            DB::statement("ALTER DATABASE \"{$databaseName}\" SET timezone TO ?", [$targetTimezone]);
            $this->info("✓ Set database-level timezone to {$targetTimezone}");

            // Set timezone for current session
            DB::statement('SET timezone TO ?', [$targetTimezone]);
            $this->info("✓ Set session timezone to {$targetTimezone}");

            // Verify the change
            $result = DB::scalar('SHOW timezone');
            $newTimezone = is_string($result) ? $result : 'Unknown';

            if ($newTimezone === $targetTimezone) {
                $this->newLine();
                $this->info("✓ SUCCESS: PostgreSQL timezone set to {$targetTimezone}");
                $this->newLine();
                $this->warn('IMPORTANT: New connections will use the updated timezone.');
                $this->warn('Please restart your application and queue workers:');
                $this->newLine();
                $this->line('  php artisan queue:restart');
                $this->line('  php artisan config:clear');
                $this->newLine();

                return self::SUCCESS;
            }

            $this->error("Verification failed. Expected {$targetTimezone}, got {$newTimezone}");

            return self::FAILURE;

        } catch (Exception $e) {
            $this->error('Failed to set timezone: '.$e->getMessage());
            $this->newLine();
            $this->warn('You may need superuser privileges to change database settings.');
            $this->warn('Try running this SQL manually as a superuser:');
            $this->newLine();
            $this->line("  ALTER DATABASE \"{$databaseName}\" SET timezone TO '{$targetTimezone}';");
            $this->newLine();

            return self::FAILURE;
        }
    }
}
