<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Faculty;
use App\Models\Student;
use App\Models\User;

final class ApiIdentityService
{
    public function studentFor(User $user): ?Student
    {
        return Student::query()
            ->where('user_id', $user->id)
            ->orWhere(function ($query) use ($user): void {
                $query->whereNull('user_id')->where('email', $user->email);
            })
            ->first();
    }

    public function facultyFor(User $user): ?Faculty
    {
        return Faculty::query()->where('email', $user->email)->first();
    }
}
