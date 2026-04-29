<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Room;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

final class RoomPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Room');
    }

    public function view(AuthUser $authUser): bool
    {
        return $authUser->can('View:Room');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Room');
    }

    public function update(AuthUser $authUser): bool
    {
        return $authUser->can('Update:Room');
    }

    public function delete(AuthUser $authUser): bool
    {
        return $authUser->can('Delete:Room');
    }

    public function restore(AuthUser $authUser): bool
    {
        return $authUser->can('Restore:Room');
    }

    public function forceDelete(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDelete:Room');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:Room');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:Room');
    }

    public function replicate(AuthUser $authUser): bool
    {
        return $authUser->can('Replicate:Room');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:Room');
    }
}
