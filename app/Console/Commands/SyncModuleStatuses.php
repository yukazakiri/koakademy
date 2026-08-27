<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Modules\ModuleManifestRepository;
use App\Modules\ModuleStateRepository;
use Illuminate\Console\Command;
use Override;

final class SyncModuleStatuses extends Command
{
    #[Override]
    protected $signature = 'modules:sync-statuses
                            {--mode=preserve : initialize new module rows from the legacy file or disable them}
                            {--acknowledge-restart : clear restart-required markers after every application replica has been updated}';

    #[Override]
    protected $description = 'Synchronize installed module metadata and activation state into the database.';

    public function handle(ModuleManifestRepository $manifests, ModuleStateRepository $states): int
    {
        $mode = (string) $this->option('mode');

        if (! in_array($mode, ['disabled', 'preserve'], true)) {
            $this->error('The --mode option must be either disabled or preserve.');

            return self::INVALID;
        }

        if (! $states->databaseIsAvailable()) {
            $this->warn('The module state table is unavailable; keeping the legacy status file as the fallback.');

            return self::SUCCESS;
        }

        $count = $states->sync(
            manifests: $manifests->all(),
            mode: $mode,
            clearRestartRequired: (bool) $this->option('acknowledge-restart'),
        );

        $this->info("Synchronized {$count} installed module state record(s).");

        return self::SUCCESS;
    }
}
