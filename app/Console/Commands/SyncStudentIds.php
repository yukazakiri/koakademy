<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Student;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final class SyncStudentIds extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'student:sync-ids 
                            {--dry-run : Run the command without making changes}
                            {--force : Skip confirmation prompts}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync the id column with the student_id column in the students table';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Student ID Synchronization Tool');
        $this->info('=====================================');

        $isDryRun = $this->option('dry-run');
        $isForced = $this->option('force');

        if ($isDryRun) {
            $this->warn('DRY RUN MODE: No changes will be made to the database');
        }

        try {
            // Get statistics
            $totalStudents = Student::withTrashed()->count();
            $studentsWithMismatch = Student::withTrashed()
                ->whereColumn('id', '!=', 'student_id')
                ->count();
            $studentsWithNullStudentId = Student::withTrashed()
                ->whereNull('student_id')
                ->count();

            $this->info("Total students in database: {$totalStudents}");
            $this->info("Students with mismatched IDs: {$studentsWithMismatch}");
            $this->info("Students with null student_id: {$studentsWithNullStudentId}");

            if ($studentsWithMismatch === 0 && $studentsWithNullStudentId === 0) {
                $this->info('✅ All student IDs are already synchronized!');

                return Command::SUCCESS;
            }

            if (! $isDryRun && ! $isForced && ! $this->confirm('Do you want to proceed with synchronizing the student IDs?')) {
                $this->info('Operation cancelled.');

                return Command::SUCCESS;
            }

            // Preview changes if dry run
            if ($isDryRun) {
                $this->previewChanges();

                return Command::SUCCESS;
            }

            // Perform the actual synchronization
            $this->performSync();

            $this->info('✅ Student ID synchronization completed successfully!');

            return Command::SUCCESS;

        } catch (Exception $e) {
            $this->error('❌ An error occurred during synchronization: '.$e->getMessage());
            Log::error('Student ID sync error: '.$e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);

            return Command::FAILURE;
        }
    }

    /**
     * Preview what changes would be made without actually making them
     */
    private function previewChanges(): void
    {
        $this->info('📋 Preview of changes that would be made:');
        $this->line('');

        // Students with mismatched IDs
        $mismatchedStudents = Student::withTrashed()
            ->whereColumn('id', '!=', 'student_id')
            ->select('id', 'student_id', 'first_name', 'last_name')
            ->limit(10)
            ->get();

        if ($mismatchedStudents->isNotEmpty()) {
            $this->info('Students with mismatched IDs (showing first 10):');
            $headers = ['Current ID', 'Current Student ID', 'Name', 'Action'];
            $rows = [];

            foreach ($mismatchedStudents as $student) {
                $rows[] = [
                    $student->id,
                    $student->student_id ?? 'NULL',
                    "{$student->first_name} {$student->last_name}",
                    "Set student_id = {$student->id}",
                ];
            }

            $this->table($headers, $rows);
        }

        // Students with null student_id
        $nullStudentIds = Student::withTrashed()
            ->whereNull('student_id')
            ->select('id', 'first_name', 'last_name')
            ->limit(10)
            ->get();

        if ($nullStudentIds->isNotEmpty()) {
            $this->info('Students with null student_id (showing first 10):');
            $headers = ['ID', 'Name', 'Action'];
            $rows = [];

            foreach ($nullStudentIds as $student) {
                $rows[] = [
                    $student->id,
                    "{$student->first_name} {$student->last_name}",
                    "Set student_id = {$student->id}",
                ];
            }

            $this->table($headers, $rows);
        }
    }

    /**
     * Perform the actual synchronization
     */
    private function performSync(): void
    {
        DB::beginTransaction();

        try {
            $this->info('🔄 Starting synchronization process...');

            // Update all records where id != student_id or student_id is null
            $affectedRows = Student::withTrashed()
                ->where(function ($query): void {
                    $query->whereColumn('id', '!=', 'student_id')
                        ->orWhereNull('student_id');
                })
                ->update(['student_id' => DB::raw('id')]);

            $this->info("📊 Updated {$affectedRows} student records");

            // Log the operation
            Log::info('Student ID synchronization completed', [
                'affected_rows' => $affectedRows,
                'timestamp' => now(),
                'user' => 'console_command',
            ]);

            // Verify the synchronization
            $remainingMismatches = Student::withTrashed()
                ->whereColumn('id', '!=', 'student_id')
                ->count();

            $remainingNulls = Student::withTrashed()
                ->whereNull('student_id')
                ->count();

            if ($remainingMismatches > 0 || $remainingNulls > 0) {
                throw new Exception("Synchronization incomplete. Remaining mismatches: {$remainingMismatches}, Remaining nulls: {$remainingNulls}");
            }

            DB::commit();

            $this->info('✅ All student IDs have been successfully synchronized!');
            $this->info('📋 Verification: All id and student_id columns now match');

        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }
}
