<?php

declare(strict_types=1);

namespace App\Policies;

use Backstage\Mails\Models\MailEvent;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

final class MailEventPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:MailEvent');
    }

    public function view(AuthUser $authUser, MailEvent $mailEvent): bool
    {
        return $authUser->can('View:MailEvent');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:MailEvent');
    }

    public function update(AuthUser $authUser, MailEvent $mailEvent): bool
    {
        return $authUser->can('Update:MailEvent');
    }

    public function delete(AuthUser $authUser, MailEvent $mailEvent): bool
    {
        return $authUser->can('Delete:MailEvent');
    }

    public function restore(AuthUser $authUser, MailEvent $mailEvent): bool
    {
        return $authUser->can('Restore:MailEvent');
    }

    public function forceDelete(AuthUser $authUser, MailEvent $mailEvent): bool
    {
        return $authUser->can('ForceDelete:MailEvent');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:MailEvent');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:MailEvent');
    }

    public function replicate(AuthUser $authUser, MailEvent $mailEvent): bool
    {
        return $authUser->can('Replicate:MailEvent');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:MailEvent');
    }
}
