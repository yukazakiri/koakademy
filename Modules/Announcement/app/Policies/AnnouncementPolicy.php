<?php

declare(strict_types=1);

namespace Modules\Announcement\Policies;

use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;
use Modules\Announcement\Models\Announcement;

final class AnnouncementPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Announcement');
    }

    public function view(AuthUser $authUser, Announcement $announcement): bool
    {
        return $authUser->can('View:Announcement');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Announcement');
    }

    public function update(AuthUser $authUser, Announcement $announcement): bool
    {
        return $authUser->can('Update:Announcement');
    }

    public function delete(AuthUser $authUser, Announcement $announcement): bool
    {
        return $authUser->can('Delete:Announcement');
    }

    public function restore(AuthUser $authUser, Announcement $announcement): bool
    {
        return $authUser->can('Restore:Announcement');
    }

    public function forceDelete(AuthUser $authUser, Announcement $announcement): bool
    {
        return $authUser->can('ForceDelete:Announcement');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:Announcement');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:Announcement');
    }

    public function replicate(AuthUser $authUser, Announcement $announcement): bool
    {
        return $authUser->can('Replicate:Announcement');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:Announcement');
    }
}
