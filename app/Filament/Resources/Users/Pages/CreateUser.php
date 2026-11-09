<?php

declare(strict_types=1);

namespace App\Filament\Resources\Users\Pages;

use App\Enums\UserRole;
use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use App\Services\UserSystemRoleService;
use App\Support\AdministratorPermissionChecker;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;
use Override;
use Spatie\Permission\Models\Role;

final class CreateUser extends CreateRecord
{
    #[Override]
    protected static string $resource = UserResource::class;

    /**
     * Validate role assignments and authority before creation.
     */
    protected function beforeCreate(): void
    {
        $actor = Auth::user();
        if (! $actor instanceof User) {
            abort(403);
        }

        $state = $this->form->getRawState();
        $newRole = UserRole::from($state['role']);
        $newAdditionalAccess = collect($state['additional_access'] ?? [])
            ->map(fn ($id): int => (int) $id)
            ->sort()
            ->values()
            ->all();

        abort_unless(AdministratorPermissionChecker::userHasAnyPermission($actor, 'Assign:Role'), 403, 'You do not have permission to assign roles.');
        abort_unless($actor->role !== null && in_array($newRole, $actor->role->getManageableRoles(), true), 403, "You cannot assign the role '{$newRole->getLabel()}'.");

        foreach (Role::with('permissions')->whereIn('id', $newAdditionalAccess)->get() as $assignedRole) {
            $systemRole = UserSystemRoleService::fromName($assignedRole->name);
            abort_if($systemRole !== null, 403, 'Choose a system role using the System role selector, not Additional access.');
            foreach ($assignedRole->permissions as $permission) {
                abort_unless(AdministratorPermissionChecker::userHasAnyPermission($actor, $permission->name), 403);
            }
        }
    }

    /**
     * Synchronize roles after creation.
     */
    protected function afterCreate(): void
    {
        $state = $this->form->getRawState();
        app(UserSystemRoleService::class)->assign($this->record, UserRole::from($state['role']), $state['additional_access'] ?? []);
    }
}
