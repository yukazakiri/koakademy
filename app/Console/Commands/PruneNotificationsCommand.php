<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class PruneNotificationsCommand extends Command
{
    protected $signature = 'notifications:prune {--days=30 : Prune read notifications older than this many days}';

    protected $description = 'Prune read notifications older than the given retention period';

    public function handle(): int
    {
        if (! Schema::hasTable('notifications')) {
            $this->components->info('Notifications table does not exist.');

            return self::SUCCESS;
        }

        $days = (int) ($this->option('days') ?: 30);
        $cutoff = now()->subDays($days);

        $pruned = DB::table('notifications')
            ->whereNotNull('read_at')
            ->where('created_at', '<', $cutoff)
            ->delete();

        $this->components->info("Pruned {$pruned} read notification record(s).");

        return self::SUCCESS;
    }
}
