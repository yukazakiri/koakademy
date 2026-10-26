<?php

declare(strict_types=1);

namespace Koakademy\SchoolNotes\Policies;

use App\Models\User;

final class SchoolNotePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('school-notes.view');
    }

    public function create(User $user): bool
    {
        return $user->can('school-notes.create');
    }
}
