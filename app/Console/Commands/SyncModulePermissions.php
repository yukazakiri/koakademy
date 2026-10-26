<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Modules\ModulePermissionService;
use Illuminate\Console\Command;
use Override;

final class SyncModulePermissions extends Command
{
    #[Override]
    protected $signature = 'modules:sync-permissions';

    #[Override]
    protected $description = 'Provision declared extension permissions without granting them to roles or users.';

    public function handle(ModulePermissionService $permissions): int
    {
        $count = count($permissions->sync());
        $this->info("Provisioned {$count} declared extension permission(s). Role assignments were preserved.");

        return self::SUCCESS;
    }
}
