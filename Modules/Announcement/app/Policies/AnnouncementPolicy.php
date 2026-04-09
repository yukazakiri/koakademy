<?php

declare(strict_types=1);

namespace Modules\Announcement\Policies;

use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;
use Modules\Announcement\Models\Announcement;

final class AnnouncementPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $this->canAccessAnnouncements($authUser, [
            'ViewAny:Announcement',
            'view_announcements',
            'manage_announcements',
        ]);
    }

    public function view(AuthUser $authUser, Announcement $announcement): bool
    {
        return $this->canAccessAnnouncements($authUser, [
            'View:Announcement',
            'view_announcements',
            'manage_announcements',
        ]);
    }

    public function create(AuthUser $authUser): bool
    {
        return $this->canAccessAnnouncements($authUser, [
            'Create:Announcement',
            'manage_announcements',
        ]);
    }

    public function update(AuthUser $authUser, Announcement $announcement): bool
    {
        return $this->canAccessAnnouncements($authUser, [
            'Update:Announcement',
            'manage_announcements',
        ]);
    }

    public function delete(AuthUser $authUser, Announcement $announcement): bool
    {
        return $this->canAccessAnnouncements($authUser, [
            'Delete:Announcement',
            'manage_announcements',
        ]);
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

    /**
     * @param  list<string>  $abilities
     */
    private function canAccessAnnouncements(AuthUser $authUser, array $abilities): bool
    {
        foreach ($abilities as $ability) {
            if ($authUser->can($ability)) {
                return true;
            }
        }

        return $authUser instanceof User
            && (
                $authUser->role?->isAdministrative()
                || $authUser->role?->isSuperAdmin()
                || $authUser->role?->isRegistrar()
                || $authUser->role?->isDeptHead()
                || $authUser->role?->isStudentServices()
                || $authUser->role?->isFinance()
                || $authUser->role === \App\Enums\UserRole::ITSupport
                || $authUser->role === \App\Enums\UserRole::HRManager
                || $authUser->role === \App\Enums\UserRole::AdministrativeAssistant
                || $authUser->role === \App\Enums\UserRole::Developer
            );
    }
}
