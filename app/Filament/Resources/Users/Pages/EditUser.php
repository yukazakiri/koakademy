<?php

declare(strict_types=1);

namespace App\Filament\Resources\Users\Pages;

use App\Enums\UserRole;
use App\Filament\Resources\Users\Schemas\UserForm;
use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use App\Services\UserFeatureFlagService;
use App\Services\UserSystemRoleService;
use App\Support\AdministratorPermissionChecker;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Auth;
use Override;
use Spatie\Permission\Models\Role;

final class EditUser extends EditRecord
{
    #[Override]
    protected static string $resource = UserResource::class;

    /**
     * Get the header actions for the edit record page.
     *
     * @return array<int, \Filament\Actions\Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }

    /**
     * Validate role assignments and authority before saving.
     */
    protected function beforeSave(): void
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

        $currentRole = $this->record->role;
        $currentAdditionalAccess = $this->record->roles
            ->filter(fn ($role): bool => UserSystemRoleService::fromName($role->name) === null)
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->sort()
            ->values()
            ->all();

        $roleChanged = $currentRole !== $newRole;
        $accessChanged = $currentAdditionalAccess !== $newAdditionalAccess;

        if ($roleChanged || $accessChanged) {
            abort_unless(AdministratorPermissionChecker::userHasAnyPermission($actor, 'Assign:Role'), 403, 'You do not have permission to assign roles.');
            abort_unless($actor->hasHigherAuthorityThan($this->record), 403, 'You cannot modify role assignments for users of equal or higher authority.');

            if ($roleChanged) {
                abort_unless($actor->role !== null && in_array($newRole, $actor->role->getManageableRoles(), true), 403, "You cannot assign the role '{$newRole->getLabel()}'.");
            }

            if ($accessChanged) {
                foreach (Role::with('permissions')->whereIn('id', $newAdditionalAccess)->get() as $assignedRole) {
                    $systemRole = UserSystemRoleService::fromName($assignedRole->name);
                    abort_if($systemRole !== null, 403, 'Choose a system role using the System role selector, not Additional access.');
                    foreach ($assignedRole->permissions as $permission) {
                        abort_unless(AdministratorPermissionChecker::userHasAnyPermission($actor, $permission->name), 403);
                    }
                }
            }
        }
    }

    /**
     * Synchronize roles and feature flag overrides after saving.
     */
    protected function afterSave(): void
    {
        $state = $this->form->getRawState();
        app(UserSystemRoleService::class)->assign($this->record, UserRole::from($state['role']), $state['additional_access'] ?? []);

        if (! array_key_exists('feature_flags', $state)) {
            return;
        }

        app(UserFeatureFlagService::class)->syncFeatureOverrides(
            $this->record,
            array_values(array_intersect($state['feature_flags'] ?? [], UserForm::getExperimentalFeatureKeys($state['role'] ?? $this->record->role))),
            $state['role'] ?? $this->record->role,
            true,
        );
    }
}
