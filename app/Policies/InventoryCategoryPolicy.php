<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\InventoryCategory;
use Illuminate\Auth\Access\HandlesAuthorization;

class InventoryCategoryPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:InventoryCategory');
    }

    public function view(AuthUser $authUser, InventoryCategory $inventoryCategory): bool
    {
        return $authUser->can('View:InventoryCategory');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:InventoryCategory');
    }

    public function update(AuthUser $authUser, InventoryCategory $inventoryCategory): bool
    {
        return $authUser->can('Update:InventoryCategory');
    }

    public function delete(AuthUser $authUser, InventoryCategory $inventoryCategory): bool
    {
        return $authUser->can('Delete:InventoryCategory');
    }

    public function restore(AuthUser $authUser, InventoryCategory $inventoryCategory): bool
    {
        return $authUser->can('Restore:InventoryCategory');
    }

    public function forceDelete(AuthUser $authUser, InventoryCategory $inventoryCategory): bool
    {
        return $authUser->can('ForceDelete:InventoryCategory');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:InventoryCategory');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:InventoryCategory');
    }

    public function replicate(AuthUser $authUser, InventoryCategory $inventoryCategory): bool
    {
        return $authUser->can('Replicate:InventoryCategory');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:InventoryCategory');
    }

}