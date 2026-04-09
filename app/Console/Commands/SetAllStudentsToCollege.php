<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\StudentType;
use App\Models\Student;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

final class SetAllStudentsToCollege extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'students:set-college-type {--dry-run : Run without making changes}';

    /**
     * The console command description.
     */
    protected $description = 'Set all existing students to College type';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $dryRun = $this->option('dry-run');

        if ($dryRun) {
            $this->info('Running in dry-run mode. No changes will be made.');
        }

        // Get all students that don't have student_type set to college
        $students = Student::query()
            ->where(function ($query): void {
                $query->where('student_type', '!=', StudentType::College->value)
                    ->orWhereNull('student_type');
            })
            ->get();

        if ($students->isEmpty()) {
            $this->info('All students are already set to College type.');

            return self::SUCCESS;
        }

        $this->info("Found {$students->count()} students to update.");

        if (! $dryRun && ! $this->confirm('Do you want to proceed with updating these students?')) {
            $this->info('Operation cancelled.');

            return self::SUCCESS;
        }

        $progressBar = $this->output->createProgressBar($students->count());
        $progressBar->start();

        $updated = 0;
        $errors = 0;

        foreach ($students as $student) {
            try {
                if (! $dryRun) {
                    $student->update([
                        'student_type' => StudentType::College->value,
                    ]);
                    Log::info("Updated student {$student->id} to College type");
                }
                $updated++;
            } catch (Exception $e) {
                $errors++;
                Log::error("Failed to update student {$student->id}: ".$e->getMessage());
                $this->error("Failed to update student {$student->id}: ".$e->getMessage());
            }

            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine();

        if ($dryRun) {
            $this->info("Dry run complete. Would have updated {$updated} students.");
        } else {
            $this->info("Successfully updated {$updated} students to College type.");
        }

        if ($errors > 0) {
            $this->error("Encountered {$errors} errors during the update process.");

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
