<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\OnboardingFeature;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

final class OnboardingFeaturePolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:OnboardingFeature');
    }

    public function view(AuthUser $authUser, OnboardingFeature $onboardingFeature): bool
    {
        return $authUser->can('View:OnboardingFeature');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:OnboardingFeature');
    }

    public function update(AuthUser $authUser, OnboardingFeature $onboardingFeature): bool
    {
        return $authUser->can('Update:OnboardingFeature');
    }

    public function delete(AuthUser $authUser, OnboardingFeature $onboardingFeature): bool
    {
        return $authUser->can('Delete:OnboardingFeature');
    }

    public function restore(AuthUser $authUser, OnboardingFeature $onboardingFeature): bool
    {
        return $authUser->can('Restore:OnboardingFeature');
    }

    public function forceDelete(AuthUser $authUser, OnboardingFeature $onboardingFeature): bool
    {
        return $authUser->can('ForceDelete:OnboardingFeature');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:OnboardingFeature');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:OnboardingFeature');
    }

    public function replicate(AuthUser $authUser, OnboardingFeature $onboardingFeature): bool
    {
        return $authUser->can('Replicate:OnboardingFeature');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:OnboardingFeature');
    }
}
