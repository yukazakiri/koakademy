<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class PruneSessionsCommand extends Command
{
    protected $signature = 'sessions:prune {--hours= : Stale age in hours (default: session.lifetime converted to hours)}';

    protected $description = 'Prune stale sessions from the database';

    public function handle(): int
    {
        if (! Schema::hasTable('sessions')) {
            $this->components->info('Sessions table does not exist.');

            return self::SUCCESS;
        }

        $hours = $this->option('hours');
        $lifetimeMinutes = $hours !== null ? ((int) $hours) * 60 : (int) config('session.lifetime', 120);
        $cutoff = now()->subMinutes($lifetimeMinutes)->getTimestamp();

        $pruned = DB::table('sessions')
            ->where('last_activity', '<', $cutoff)
            ->delete();

        $this->components->info("Pruned {$pruned} stale session record(s).");

        return self::SUCCESS;
    }
}
