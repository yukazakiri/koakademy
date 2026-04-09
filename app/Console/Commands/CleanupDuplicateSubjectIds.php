<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Classes;
use Illuminate\Console\Command;

final class CleanupDuplicateSubjectIds extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'classes:cleanup-duplicate-subjects';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Remove duplicate subject IDs from classes subject_ids array';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Cleaning up duplicate subject IDs...');

        $classes = Classes::query()
            ->whereNotNull('subject_ids')
            ->get();

        if ($classes->isEmpty()) {
            $this->info('No classes with subject_ids found.');

            return self::SUCCESS;
        }

        $cleaned = 0;
        $bar = $this->output->createProgressBar($classes->count());
        $bar->start();

        foreach ($classes as $class) {
            if (! empty($class->subject_ids) && is_array($class->subject_ids)) {
                $originalCount = count($class->subject_ids);
                // Remove duplicates while preserving order
                $uniqueIds = array_values(array_unique($class->subject_ids));

                if (count($uniqueIds) < $originalCount) {
                    $class->subject_ids = $uniqueIds;
                    $class->save();
                    $cleaned++;
                    $this->newLine();
                    $this->warn("Class ID {$class->id}: Removed ".(count($class->subject_ids) - count($uniqueIds)).' duplicate subject IDs');
                }
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);
        $this->info("Cleaned up {$cleaned} classes successfully.");

        return self::SUCCESS;
    }
}
