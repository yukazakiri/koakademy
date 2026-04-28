<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

final class CoursePolicy
{
    use HandlesAuthorization;

    public function before(User $user): ?bool
    {
        if ($user->hasRole('super_admin')) {
            return true;
        }

        return null;
    }

    public function viewAny(User $user): bool
    {
        return $user->can('ViewAny:Course');
    }

    public function view(User $user): bool
    {
        return $user->can('View:Course');
    }

    public function create(User $user): bool
    {
        return $user->can('Create:Course');
    }

    public function update(User $user): bool
    {
        return $user->can('Update:Course');
    }

    public function delete(User $user): bool
    {
        return $user->can('Delete:Course');
    }

    public function restore(User $user): bool
    {
        return $user->can('Restore:Course');
    }

    public function forceDelete(User $user): bool
    {
        return $user->can('ForceDelete:Course');
    }

    public function forceDeleteAny(User $user): bool
    {
        return $user->can('ForceDeleteAny:Course');
    }

    public function restoreAny(User $user): bool
    {
        return $user->can('RestoreAny:Course');
    }

    public function replicate(User $user): bool
    {
        return $user->can('Replicate:Course');
    }

    public function reorder(User $user): bool
    {
        return $user->can('Reorder:Course');
    }
}
