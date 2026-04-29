<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\InventorySupplier;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

final class InventorySupplierPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:InventorySupplier');
    }

    public function view(AuthUser $authUser): bool
    {
        return $authUser->can('View:InventorySupplier');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:InventorySupplier');
    }

    public function update(AuthUser $authUser): bool
    {
        return $authUser->can('Update:InventorySupplier');
    }

    public function delete(AuthUser $authUser): bool
    {
        return $authUser->can('Delete:InventorySupplier');
    }

    public function restore(AuthUser $authUser): bool
    {
        return $authUser->can('Restore:InventorySupplier');
    }

    public function forceDelete(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDelete:InventorySupplier');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:InventorySupplier');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:InventorySupplier');
    }

    public function replicate(AuthUser $authUser): bool
    {
        return $authUser->can('Replicate:InventorySupplier');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:InventorySupplier');
    }
}
