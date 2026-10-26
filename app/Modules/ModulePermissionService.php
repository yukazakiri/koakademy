<?php

declare(strict_types=1);

namespace App\Modules;

use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

final readonly class ModulePermissionService
{
    public function __construct(private ModuleManifestRepository $modules) {}

    /** @return list<string> */
    public function sync(): array
    {
        $names = [];
        foreach ($this->modules->installed() as $module) {
            foreach ($module->manifest->permissions as $permission) {
                Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
                $names[] = $permission;
            }
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return array_values(array_unique($names));
    }
}
