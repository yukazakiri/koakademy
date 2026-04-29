<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

final class InventoryBorrowingPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:InventoryBorrowing');
    }

    public function view(AuthUser $authUser): bool
    {
        return $authUser->can('View:InventoryBorrowing');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:InventoryBorrowing');
    }

    public function update(AuthUser $authUser): bool
    {
        return $authUser->can('Update:InventoryBorrowing');
    }

    public function delete(AuthUser $authUser): bool
    {
        return $authUser->can('Delete:InventoryBorrowing');
    }

    public function restore(AuthUser $authUser): bool
    {
        return $authUser->can('Restore:InventoryBorrowing');
    }

    public function forceDelete(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDelete:InventoryBorrowing');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:InventoryBorrowing');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:InventoryBorrowing');
    }

    public function replicate(AuthUser $authUser): bool
    {
        return $authUser->can('Replicate:InventoryBorrowing');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:InventoryBorrowing');
    }
}
