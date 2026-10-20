<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\User;
use App\Services\UserSystemRoleService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

final class ReconcileUserSystemRoles extends Command
{
    protected $signature = 'users:reconcile-system-roles {--apply : Apply only unambiguous assignments; report conflicts without changing them}';

    protected $description = 'Report legacy/Spatie role mismatches; optionally backfill missing Spatie roles and legacy projections.';

    public function handle(UserSystemRoleService $roles): int
    {
        $conflicts = 0;
        User::query()->with('roles')->orderBy('id')->chunkById(200, function ($users) use ($roles, &$conflicts): void {
            foreach ($users as $user) {
                $legacy = UserRole::tryFrom((string) $user->getRawOriginal('role'));
                $assigned = $roles->assigned($user);
                if (count($assigned) > 1) {
                    $conflicts++;
                    $this->warn("User #{$user->id}: multiple system roles ".implode(', ', array_map(fn (UserRole $role): string => $role->value, $assigned)).'; resolve explicitly in user management.');

                    continue;
                }
                $effective = $assigned[0] ?? $legacy;
                if ($effective === null) {
                    $conflicts++;
                    $this->warn("User #{$user->id}: no valid system role; manual review required.");

                    continue;
                }
                if ($assigned === [] || $legacy !== $effective) {
                    $this->line("User #{$user->id}: legacy=".($legacy?->value ?? 'invalid').'; Spatie='.($assigned[0]->value ?? 'missing').'; effective='.$effective->value);
                    if ($this->option('apply')) {
                        if ($assigned === []) {
                            $roles->assign($user, $effective);
                        } else {
                            DB::table('users')->where('id', $user->id)->update(['role' => $effective->value]);
                        }
                    }
                }
            }
        });
        $this->info($this->option('apply') ? 'Unambiguous assignments reconciled.' : 'Report only. No assignments changed.');

        return $conflicts > 0 ? self::FAILURE : self::SUCCESS;
    }
}
