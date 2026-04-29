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
        return $this->hasAdministrativeAccess($authUser)
            || $authUser->can('ViewAny:Announcement')
            || $authUser->can('view_announcements')
            || $authUser->can('manage_announcements');
    }

    public function view(AuthUser $authUser, Announcement $announcement): bool
    {
        return $this->hasAdministrativeAccess($authUser)
            || $authUser->can('View:Announcement')
            || $authUser->can('view_announcements')
            || $authUser->can('manage_announcements');
    }

    public function create(AuthUser $authUser): bool
    {
        return $this->hasAdministrativeAccess($authUser)
            || $authUser->can('Create:Announcement')
            || $authUser->can('manage_announcements');
    }

    public function update(AuthUser $authUser, Announcement $announcement): bool
    {
        return $this->hasAdministrativeAccess($authUser)
            || $authUser->can('Update:Announcement')
            || $authUser->can('manage_announcements');
    }

    public function delete(AuthUser $authUser, Announcement $announcement): bool
    {
        return $this->hasAdministrativeAccess($authUser)
            || $authUser->can('Delete:Announcement')
            || $authUser->can('manage_announcements');
    }

    public function restore(AuthUser $authUser, Announcement $announcement): bool
    {
        return $this->hasAdministrativeAccess($authUser)
            || $authUser->can('Restore:Announcement')
            || $authUser->can('manage_announcements');
    }

    public function forceDelete(AuthUser $authUser, Announcement $announcement): bool
    {
        return $this->hasAdministrativeAccess($authUser)
            || $authUser->can('ForceDelete:Announcement')
            || $authUser->can('manage_announcements');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $this->hasAdministrativeAccess($authUser)
            || $authUser->can('ForceDeleteAny:Announcement')
            || $authUser->can('manage_announcements');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $this->hasAdministrativeAccess($authUser)
            || $authUser->can('RestoreAny:Announcement')
            || $authUser->can('manage_announcements');
    }

    public function replicate(AuthUser $authUser, Announcement $announcement): bool
    {
        return $this->hasAdministrativeAccess($authUser)
            || $authUser->can('Replicate:Announcement')
            || $authUser->can('manage_announcements');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $this->hasAdministrativeAccess($authUser)
            || $authUser->can('Reorder:Announcement')
            || $authUser->can('manage_announcements');
    }

    private function hasAdministrativeAccess(AuthUser $authUser): bool
    {
        return method_exists($authUser, 'isAdministrative') && $authUser->isAdministrative();
    }
}
