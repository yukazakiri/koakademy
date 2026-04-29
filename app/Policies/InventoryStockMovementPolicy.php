<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

final class InventoryStockMovementPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:InventoryStockMovement');
    }

    public function view(AuthUser $authUser): bool
    {
        return $authUser->can('View:InventoryStockMovement');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:InventoryStockMovement');
    }

    public function update(AuthUser $authUser): bool
    {
        return $authUser->can('Update:InventoryStockMovement');
    }

    public function delete(AuthUser $authUser): bool
    {
        return $authUser->can('Delete:InventoryStockMovement');
    }

    public function restore(AuthUser $authUser): bool
    {
        return $authUser->can('Restore:InventoryStockMovement');
    }

    public function forceDelete(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDelete:InventoryStockMovement');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:InventoryStockMovement');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:InventoryStockMovement');
    }

    public function replicate(AuthUser $authUser): bool
    {
        return $authUser->can('Replicate:InventoryStockMovement');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:InventoryStockMovement');
    }
}
