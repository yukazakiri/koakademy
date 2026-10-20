<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

final class UserSystemRoleService
{
    public static function fromName(string $name): ?UserRole
    {
        if ($name === (string) config('filament-shield.super_admin.name', 'super_admin')) {
            return UserRole::SuperAdmin;
        }

        return UserRole::tryFrom($name) ?? collect(UserRole::cases())
            ->first(fn (UserRole $role): bool => $role->getLabel() === $name);
    }

    /** @return list<UserRole> */
    public function assigned(User $user): array
    {
        if (! $user->exists) {
            return [];
        }

        return $user->roles->where('guard_name', 'web')
            ->map(fn (Role $role): ?UserRole => self::fromName($role->name))
            ->filter()->unique(fn (UserRole $role): string => $role->value)
            ->sortByDesc(fn (UserRole $role): int => $role->getHierarchyLevel())
            ->values()->all();
    }

    /** @param list<int>|null $additionalRoleIds null preserves existing custom access */
    public function assign(User $user, UserRole $role, ?array $additionalRoleIds = null): void
    {
        DB::transaction(function () use ($user, $role, $additionalRoleIds): void {
            $extras = $additionalRoleIds === null
                ? $user->roles()->get()
                : Role::query()->whereIn('id', $additionalRoleIds)->get();
            $extras = $extras->filter(fn (Role $assigned): bool => $assigned->guard_name === 'web' && self::fromName($assigned->name) === null);
            $name = $role === UserRole::SuperAdmin
                ? (string) config('filament-shield.super_admin.name', $role->value)
                : $role->value;
            $systemRole = Role::findOrCreate($name, 'web');
            $user->syncRoles($extras->push($systemRole));
            // Compatibility projection for legacy SQL reports and filters; not a second selector.
            DB::table('users')->where('id', $user->id)->update(['role' => $role->value]);
            $user->setRawAttributes(array_replace($user->getAttributes(), ['role' => $role->value]), true);
            $user->unsetRelation('roles');
        });
    }
}
