<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use Backstage\Mails\Laravel\Models\Mail;
use Illuminate\Auth\Access\HandlesAuthorization;

class MailPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Mail');
    }

    public function view(AuthUser $authUser, Mail $mail): bool
    {
        return $authUser->can('View:Mail');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Mail');
    }

    public function update(AuthUser $authUser, Mail $mail): bool
    {
        return $authUser->can('Update:Mail');
    }

    public function delete(AuthUser $authUser, Mail $mail): bool
    {
        return $authUser->can('Delete:Mail');
    }

    public function restore(AuthUser $authUser, Mail $mail): bool
    {
        return $authUser->can('Restore:Mail');
    }

    public function forceDelete(AuthUser $authUser, Mail $mail): bool
    {
        return $authUser->can('ForceDelete:Mail');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:Mail');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:Mail');
    }

    public function replicate(AuthUser $authUser, Mail $mail): bool
    {
        return $authUser->can('Replicate:Mail');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:Mail');
    }

}