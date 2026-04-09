<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Classes;
use Illuminate\Console\Command;

final class BackfillClassSubjectCodes extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'classes:backfill-subject-codes';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Backfill null subject_code values from relationships';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Starting to backfill subject codes...');

        // Get all classes with null subject_code
        $classesWithNullCode = Classes::query()
            ->whereNull('subject_code')
            ->with(['Subject', 'ShsSubject'])
            ->get();

        $this->info('Found '.$classesWithNullCode->count().' classes with null subject_code');

        $updated = 0;
        $skipped = 0;

        $progressBar = $this->output->createProgressBar($classesWithNullCode->count());
        $progressBar->start();

        foreach ($classesWithNullCode as $class) {
            $subjectCode = null;

            // For SHS classes
            if ($class->isShs() && $class->ShsSubject) {
                $subjectCode = $class->ShsSubject->code;
            }
            // Try to get from subject_ids (multiple subjects) - use first one
            elseif (! empty($class->subject_ids) && is_array($class->subject_ids)) {
                $subjects = $class->subjects;
                if (! $subjects->isEmpty()) {
                    $subjectCode = $subjects->first()->code ?? null;
                }
            }
            // Try to get from single subject relationship
            elseif ($class->subject_id && $class->Subject) {
                $subjectCode = $class->Subject->code;
            }

            if ($subjectCode) {
                $class->subject_code = $subjectCode;
                $class->saveQuietly(); // Save without triggering events
                $updated++;
            } else {
                $skipped++;
            }

            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine(2);

        $this->info('Backfill completed!');
        $this->info("Updated: {$updated} classes");
        $this->info("Skipped: {$skipped} classes (no subject code found)");

        return self::SUCCESS;
    }
}
