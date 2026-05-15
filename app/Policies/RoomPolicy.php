<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\Room;
use Illuminate\Auth\Access\HandlesAuthorization;

class RoomPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Room');
    }

    public function view(AuthUser $authUser, Room $room): bool
    {
        return $authUser->can('View:Room');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Room');
    }

    public function update(AuthUser $authUser, Room $room): bool
    {
        return $authUser->can('Update:Room');
    }

    public function delete(AuthUser $authUser, Room $room): bool
    {
        return $authUser->can('Delete:Room');
    }

    public function restore(AuthUser $authUser, Room $room): bool
    {
        return $authUser->can('Restore:Room');
    }

    public function forceDelete(AuthUser $authUser, Room $room): bool
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

    public function replicate(AuthUser $authUser, Room $room): bool
    {
        return $authUser->can('Replicate:Room');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:Room');
    }

}