<?php

declare(strict_types=1);

namespace Modules\Inventory\Policies;

use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

final class InventoryProductPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:InventoryProduct');
    }

    public function view(AuthUser $authUser): bool
    {
        return $authUser->can('View:InventoryProduct');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:InventoryProduct');
    }

    public function update(AuthUser $authUser): bool
    {
        return $authUser->can('Update:InventoryProduct');
    }

    public function delete(AuthUser $authUser): bool
    {
        return $authUser->can('Delete:InventoryProduct');
    }

    public function restore(AuthUser $authUser): bool
    {
        return $authUser->can('Restore:InventoryProduct');
    }

    public function forceDelete(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDelete:InventoryProduct');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:InventoryProduct');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:InventoryProduct');
    }

    public function replicate(AuthUser $authUser): bool
    {
        return $authUser->can('Replicate:InventoryProduct');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:InventoryProduct');
    }
}
