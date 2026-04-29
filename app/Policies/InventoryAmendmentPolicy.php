<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

final class InventoryAmendmentPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:InventoryAmendment');
    }

    public function view(AuthUser $authUser): bool
    {
        return $authUser->can('View:InventoryAmendment');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:InventoryAmendment');
    }

    public function update(AuthUser $authUser): bool
    {
        return $authUser->can('Update:InventoryAmendment');
    }

    public function delete(AuthUser $authUser): bool
    {
        return $authUser->can('Delete:InventoryAmendment');
    }

    public function restore(AuthUser $authUser): bool
    {
        return $authUser->can('Restore:InventoryAmendment');
    }

    public function forceDelete(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDelete:InventoryAmendment');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:InventoryAmendment');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:InventoryAmendment');
    }

    public function replicate(AuthUser $authUser): bool
    {
        return $authUser->can('Replicate:InventoryAmendment');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:InventoryAmendment');
    }
}
