<?php

declare(strict_types=1);

namespace App\Modules;

use Illuminate\Support\Facades\DB;
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
                $names[] = $permission;
            }
        }

        $names = array_values(array_unique($names));

        if ($names !== []) {
            $permissionsTableConfig = config('permission.table_names.permissions');
            $permissionsTable = is_string($permissionsTableConfig) && $permissionsTableConfig !== ''
                ? $permissionsTableConfig
                : 'permissions';

            /** @var array<int, string> $existing */
            $existing = DB::table($permissionsTable)
                ->where('guard_name', 'web')
                ->pluck('name')
                ->map(static fn (mixed $name): string => is_string($name) ? $name : '')
                ->all();

            $missing = array_values(array_diff($names, $existing));

            if ($missing !== []) {
                $now = now();
                $rows = array_map(
                    static fn (string $permission): array => [
                        'name' => $permission,
                        'guard_name' => 'web',
                        'created_at' => $now,
                        'updated_at' => $now,
                    ],
                    $missing,
                );

                foreach (array_chunk($rows, 200) as $chunk) {
                    DB::table($permissionsTable)->insert($chunk);
                }
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $names;
    }
}
