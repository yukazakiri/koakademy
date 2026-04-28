<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\SanityContent;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

final class SanityContentPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:SanityContent');
    }

    public function view(AuthUser $authUser, SanityContent $sanityContent): bool
    {
        return $authUser->can('View:SanityContent');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:SanityContent');
    }

    public function update(AuthUser $authUser, SanityContent $sanityContent): bool
    {
        return $authUser->can('Update:SanityContent');
    }

    public function delete(AuthUser $authUser, SanityContent $sanityContent): bool
    {
        return $authUser->can('Delete:SanityContent');
    }

    public function restore(AuthUser $authUser, SanityContent $sanityContent): bool
    {
        return $authUser->can('Restore:SanityContent');
    }

    public function forceDelete(AuthUser $authUser, SanityContent $sanityContent): bool
    {
        return $authUser->can('ForceDelete:SanityContent');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:SanityContent');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:SanityContent');
    }

    public function replicate(AuthUser $authUser, SanityContent $sanityContent): bool
    {
        return $authUser->can('Replicate:SanityContent');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:SanityContent');
    }
}
