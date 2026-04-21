<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\User;
use App\Support\SystemManagementPermissions;

final class GeneralSettingPolicy
{
    public function viewAny(User $user): bool
    {
        foreach (SystemManagementPermissions::sectionKeys() as $section) {
            if ($this->canViewSection($user, $section)) {
                return true;
            }
        }

        return false;
    }

    public function viewSchool(User $user): bool
    {
        return $this->canViewSection($user, 'school');
    }

    public function updateSchool(User $user): bool
    {
        return $this->canUpdateSection($user, 'school');
    }

    public function viewEnrollmentPipeline(User $user): bool
    {
        return $this->canViewSection($user, 'pipeline');
    }

    public function updateEnrollmentPipeline(User $user): bool
    {
        return $this->canUpdateSection($user, 'pipeline');
    }

    public function viewSeo(User $user): bool
    {
        return $this->canViewSection($user, 'seo');
    }

    public function updateSeo(User $user): bool
    {
        return $this->canUpdateSection($user, 'seo');
    }

    public function viewAnalytics(User $user): bool
    {
        return $this->canViewSection($user, 'analytics');
    }

    public function updateAnalytics(User $user): bool
    {
        return $this->canUpdateSection($user, 'analytics');
    }

    public function viewBrand(User $user): bool
    {
        return $this->canViewSection($user, 'brand');
    }

    public function updateBrand(User $user): bool
    {
        return $this->canUpdateSection($user, 'brand');
    }

    public function viewSanity(User $user): bool
    {
        return $this->canViewSection($user, 'sanity');
    }

    public function updateSanity(User $user): bool
    {
        return $this->canUpdateSection($user, 'sanity');
    }

    public function viewSocialite(User $user): bool
    {
        return $this->canViewSection($user, 'socialite');
    }

    public function updateSocialite(User $user): bool
    {
        return $this->canUpdateSection($user, 'socialite');
    }

    public function viewMail(User $user): bool
    {
        return $this->canViewSection($user, 'mail');
    }

    public function updateMail(User $user): bool
    {
        return $this->canUpdateSection($user, 'mail');
    }

    public function viewApi(User $user): bool
    {
        return $this->canViewSection($user, 'api');
    }

    public function updateApi(User $user): bool
    {
        return $this->canUpdateSection($user, 'api');
    }

    public function viewNotifications(User $user): bool
    {
        return $this->canViewSection($user, 'notifications');
    }

    public function updateNotifications(User $user): bool
    {
        return $this->canUpdateSection($user, 'notifications');
    }

    public function viewPulse(User $user): bool
    {
        return $this->canViewSection($user, 'pulse');
    }

    public function viewGrading(User $user): bool
    {
        return $this->canViewSection($user, 'grading');
    }

    public function updateGrading(User $user): bool
    {
        return $this->canUpdateSection($user, 'grading');
    }

    private function canViewSection(User $user, string $section): bool
    {
        if ($this->hasFullSystemManagementAccess($user)) {
            return true;
        }

        $permissions = $user->getAllPermissions()->pluck('name');
        $updatePermission = SystemManagementPermissions::updatePermission($section);

        return $permissions->contains(SystemManagementPermissions::viewPermission($section))
            || ($updatePermission !== null && $permissions->contains($updatePermission));
    }

    private function canUpdateSection(User $user, string $section): bool
    {
        $updatePermission = SystemManagementPermissions::updatePermission($section);

        if ($updatePermission === null) {
            return false;
        }

        if ($this->hasFullSystemManagementAccess($user)) {
            return true;
        }

        return $user->getAllPermissions()->pluck('name')->contains($updatePermission);
    }

    private function hasFullSystemManagementAccess(User $user): bool
    {
        if ($user->role === UserRole::SuperAdmin || $user->role === UserRole::Developer) {
            return true;
        }

        $superAdminRoleName = (string) config('filament-shield.super_admin.name', 'super_admin');

        return $user->hasRole($superAdminRoleName) || $user->hasRole(UserRole::Developer->value);
    }
}
