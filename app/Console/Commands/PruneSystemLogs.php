<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\LogRetentionService;
use Illuminate\Console\Command;

final class PruneSystemLogs extends Command
{
    protected $signature = 'system-logs:prune-due';

    protected $description = 'Queue due log-retention cleanups from System Settings';

    public function handle(LogRetentionService $retention): int
    {
        $this->components->info($retention->queueDueSchedules().' log cleanup(s) queued.');

        return self::SUCCESS;
    }
}
