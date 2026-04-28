<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

final class SubjectPolicy
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
        return $user->can('ViewAny:Subject');
    }

    public function view(User $user): bool
    {
        return $user->can('View:Subject');
    }

    public function create(User $user): bool
    {
        return $user->can('Create:Subject');
    }

    public function update(User $user): bool
    {
        return $user->can('Update:Subject');
    }

    public function delete(User $user): bool
    {
        return $user->can('Delete:Subject');
    }

    public function restore(User $user): bool
    {
        return $user->can('Restore:Subject');
    }

    public function forceDelete(User $user): bool
    {
        return $user->can('ForceDelete:Subject');
    }

    public function forceDeleteAny(User $user): bool
    {
        return $user->can('ForceDeleteAny:Subject');
    }

    public function restoreAny(User $user): bool
    {
        return $user->can('RestoreAny:Subject');
    }

    public function replicate(User $user): bool
    {
        return $user->can('Replicate:Subject');
    }

    public function reorder(User $user): bool
    {
        return $user->can('Reorder:Subject');
    }
}
