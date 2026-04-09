<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\StudentType;
use App\Models\Student;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

final class FixCollegeStudentIds extends Command
{
    protected $signature = 'students:fix-college-ids {--dry-run : Simulate the fix process without making changes}';

    protected $description = 'Fix college students with 7 or 8 digit IDs and convert them to proper 6-digit IDs starting with 206***';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');

        $this->info('🔍 Analyzing college student IDs...');

        // Find students with 7 or 8 digit IDs
        $affectedStudents = Student::where('student_type', StudentType::College->value)
            ->whereRaw('LENGTH(CAST(student_id AS TEXT)) >= 7')
            ->orderBy('student_id')
            ->get();

        if ($affectedStudents->isEmpty()) {
            $this->info('✅ No college students with 7 or 8 digit IDs found.');

            return self::SUCCESS;
        }

        $this->info("📊 Found {$affectedStudents->count()} college students with 7 or 8 digit IDs.");

        // Show some examples
        $this->info('📝 Examples of affected IDs:');
        $affectedStudents->take(5)->each(function ($student): void {
            $this->line("  - {$student->student_id} ({$student->first_name} {$student->last_name})");
        });

        if ($affectedStudents->count() > 5) {
            $this->line('  ... and '.($affectedStudents->count() - 5).' more');
        }

        // Analyze the conversion pattern
        $this->newLine();
        $this->info('🔄 Conversion Pattern Analysis:');

        $minId = $affectedStudents->min('student_id');
        $maxId = $affectedStudents->max('student_id');

        $this->line("  Current range: {$minId} - {$maxId}");

        // Show info about existing 206*** IDs
        $highestExisting206 = $this->getHighestExisting206Id();
        if ($highestExisting206 > 205999) {
            $this->line("  Highest existing 206*** ID: {$highestExisting206}");
            $this->line('  Will start assigning from: '.($highestExisting206 + 1));
        } else {
            $this->line('  No existing 206*** IDs found, will start from: 206000');
        }

        // Show conversion examples using sequential approach
        $this->line('  Conversion examples (sequential):');
        $nextId = $this->getNextAvailableId($highestExisting206 + 1, 210999);
        $affectedStudents->take(3)->each(function ($student) use (&$nextId): void {
            $this->line("    {$student->student_id} → {$nextId}");
            $nextId++;
        });

        if ($dryRun) {
            $this->newLine();
            $this->warn('🧪 DRY RUN MODE - No changes will be made');
            $this->info('💡 Run without --dry-run to apply the fixes');

            return self::SUCCESS;
        }

        // Confirm the operation (skip in testing environment)
        $this->newLine();
        if (app()->environment('testing')) {
            $this->info('🧪 Testing environment - skipping confirmation');
        } elseif (! $this->confirm('⚠️  This will modify student IDs. Are you sure you want to proceed?')) {
            $this->info('❌ Operation cancelled.');

            return self::SUCCESS;
        }

        // Start the conversion process
        $this->newLine();
        $this->info('🚀 Starting ID conversion process...');

        $progressBar = $this->output->createProgressBar($affectedStudents->count());
        $progressBar->start();

        $convertedCount = 0;
        $errorCount = 0;

        // Get the next available starting ID for 206*** (start from highest existing + 1)
        $highestExisting206 = $this->getHighestExisting206Id();
        $nextAvailableId = $this->getNextAvailableId($highestExisting206 + 1, 210999);

        DB::transaction(function () use ($affectedStudents, $progressBar, &$convertedCount, &$errorCount, &$nextAvailableId): void {
            foreach ($affectedStudents as $student) {
                try {
                    $oldId = $student->student_id;

                    // Use sequential numbering starting from next available ID
                    $newId = $nextAvailableId;
                    $nextAvailableId++;

                    // Check if the new ID already exists (extra safety check)
                    if (Student::where('student_id', $newId)->exists()) {
                        // Find next available ID
                        $newId = $this->getNextAvailableId($nextAvailableId, 206999);
                        $nextAvailableId = $newId + 1;
                    }

                    // Update the student ID
                    $student->update(['student_id' => $newId]);
                    $convertedCount++;

                } catch (Exception $e) {
                    $this->newLine();
                    $this->error("❌ Error updating student {$student->student_id}: {$e->getMessage()}");
                    $errorCount++;
                }

                $progressBar->advance();
            }
        });

        $progressBar->finish();
        $this->newLine(2);

        // Show results
        $this->info('📈 Conversion Results:');
        $this->line("  ✅ Successfully converted: {$convertedCount} students");
        if ($errorCount > 0) {
            $this->line("  ❌ Errors encountered: {$errorCount} students");
        }

        // Verify the results
        $remainingBadIds = Student::where('student_type', StudentType::College->value)
            ->whereRaw('LENGTH(CAST(student_id AS TEXT)) >= 7')
            ->count();

        if ($remainingBadIds === 0) {
            $this->info('🎉 All college student IDs are now properly formatted!');
        } else {
            $this->warn("⚠️  {$remainingBadIds} college students still have 7+ digit IDs.");
        }

        return self::SUCCESS;
    }

    /**
     * Convert a 7 or 8 digit ID to a 6-digit ID starting with 206***
     */
    private function convertToSixDigit(int $studentId): int
    {
        $idString = (string) $studentId;

        // If it's 7 digits and starts with 206, remove the last digit
        if (mb_strlen($idString) === 7 && str_starts_with($idString, '206')) {
            return (int) mb_substr($idString, 0, 6);
        }

        // If it's 8 digits and starts with 206, remove the last two digits
        if (mb_strlen($idString) === 8 && str_starts_with($idString, '206')) {
            return (int) mb_substr($idString, 0, 6);
        }

        // For other patterns, try to preserve the 206 prefix and make it 6 digits
        if (str_starts_with($idString, '206')) {
            // Keep first 6 characters
            return (int) mb_substr($idString, 0, 6);
        }

        // Fallback: if it doesn't start with 206, we might need manual intervention
        throw new Exception("ID {$studentId} doesn't follow expected pattern (206****)");
    }

    /**
     * Get the next available ID in the given range
     */
    private function getNextAvailableId(int $startId, int $endId): int
    {
        for ($id = $startId; $id <= $endId; $id++) {
            if (! Student::where('student_id', $id)->exists()) {
                return $id;
            }
        }

        throw new Exception("No available IDs in range {$startId}-{$endId}");
    }

    /**
     * Get the highest existing student ID in the 206*** range
     */
    private function getHighestExisting206Id(): int
    {
        $maxId = Student::where('student_id', '>=', 206000)
            ->where('student_id', '<=', 206999)
            ->max('student_id');

        // If no existing 206*** IDs, start from 206000
        return $maxId ? (int) $maxId : 205999;
    }
}
