<?php

declare(strict_types=1);

namespace Modules\LibrarySystem\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use Modules\LibrarySystem\Models\Author;
use Illuminate\Auth\Access\HandlesAuthorization;

class AuthorPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Author');
    }

    public function view(AuthUser $authUser, Author $author): bool
    {
        return $authUser->can('View:Author');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Author');
    }

    public function update(AuthUser $authUser, Author $author): bool
    {
        return $authUser->can('Update:Author');
    }

    public function delete(AuthUser $authUser, Author $author): bool
    {
        return $authUser->can('Delete:Author');
    }

    public function restore(AuthUser $authUser, Author $author): bool
    {
        return $authUser->can('Restore:Author');
    }

    public function forceDelete(AuthUser $authUser, Author $author): bool
    {
        return $authUser->can('ForceDelete:Author');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:Author');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:Author');
    }

    public function replicate(AuthUser $authUser, Author $author): bool
    {
        return $authUser->can('Replicate:Author');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:Author');
    }

}