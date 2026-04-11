<?php

declare(strict_types=1);

namespace Modules\LibrarySystem\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use Modules\LibrarySystem\Models\BorrowRecord;
use Illuminate\Auth\Access\HandlesAuthorization;

class BorrowRecordPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:BorrowRecord');
    }

    public function view(AuthUser $authUser, BorrowRecord $borrowRecord): bool
    {
        return $authUser->can('View:BorrowRecord');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:BorrowRecord');
    }

    public function update(AuthUser $authUser, BorrowRecord $borrowRecord): bool
    {
        return $authUser->can('Update:BorrowRecord');
    }

    public function delete(AuthUser $authUser, BorrowRecord $borrowRecord): bool
    {
        return $authUser->can('Delete:BorrowRecord');
    }

    public function restore(AuthUser $authUser, BorrowRecord $borrowRecord): bool
    {
        return $authUser->can('Restore:BorrowRecord');
    }

    public function forceDelete(AuthUser $authUser, BorrowRecord $borrowRecord): bool
    {
        return $authUser->can('ForceDelete:BorrowRecord');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:BorrowRecord');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:BorrowRecord');
    }

    public function replicate(AuthUser $authUser, BorrowRecord $borrowRecord): bool
    {
        return $authUser->can('Replicate:BorrowRecord');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:BorrowRecord');
    }

}