<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Classes;
use App\Models\Subject;
use Illuminate\Console\Command;

final class UpdateClassesSubjectCodes extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'classes:update-subject-codes';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update subject_code field for classes that have subject_ids but missing subject_code';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Updating classes with missing subject_code...');

        $classes = Classes::query()
            ->whereNotNull('subject_ids')
            ->where(function ($query): void {
                $query->whereNull('subject_code')
                    ->orWhere('subject_code', '');
            })
            ->get();

        if ($classes->isEmpty()) {
            $this->info('No classes need updating.');

            return self::SUCCESS;
        }

        $updated = 0;
        $bar = $this->output->createProgressBar($classes->count());
        $bar->start();

        foreach ($classes as $class) {
            if (! empty($class->subject_ids) && is_array($class->subject_ids)) {
                $firstSubjectId = $class->subject_ids[0] ?? null;
                if ($firstSubjectId) {
                    $subject = Subject::query()->find($firstSubjectId);
                    if ($subject) {
                        $class->subject_code = $subject->code;
                        $class->subject_id = $firstSubjectId;
                        $class->save();
                        $updated++;
                    }
                }
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);
        $this->info("Updated {$updated} classes successfully.");

        return self::SUCCESS;
    }
}
