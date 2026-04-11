<?php

declare(strict_types=1);

namespace Modules\LibrarySystem\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use Modules\LibrarySystem\Models\Book;
use Illuminate\Auth\Access\HandlesAuthorization;

class BookPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Book');
    }

    public function view(AuthUser $authUser, Book $book): bool
    {
        return $authUser->can('View:Book');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Book');
    }

    public function update(AuthUser $authUser, Book $book): bool
    {
        return $authUser->can('Update:Book');
    }

    public function delete(AuthUser $authUser, Book $book): bool
    {
        return $authUser->can('Delete:Book');
    }

    public function restore(AuthUser $authUser, Book $book): bool
    {
        return $authUser->can('Restore:Book');
    }

    public function forceDelete(AuthUser $authUser, Book $book): bool
    {
        return $authUser->can('ForceDelete:Book');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:Book');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:Book');
    }

    public function replicate(AuthUser $authUser, Book $book): bool
    {
        return $authUser->can('Replicate:Book');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:Book');
    }

}