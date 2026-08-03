<?php

declare(strict_types=1);

namespace App\Services\Newsletter;

use App\Data\NewsletterContact;
use App\Models\Faculty;
use App\Models\Student;
use App\Models\User;

final class NewsletterContactFactory
{
    public function forUser(User $user): NewsletterContact
    {
        $role = $user->isStudentRole() ? 'student' : 'faculty';
        [$firstName, $lastName, $attributes] = $this->resolveIdentity($user, $role);

        return new NewsletterContact(
            email: (string) $user->email,
            externalId: 'user_'.$user->id,
            role: $role,
            firstName: $firstName,
            lastName: $lastName,
            tags: ['portal', $role],
            attributes: ['role' => $role, 'source' => 'portal_prompt', ...$attributes],
        );
    }

    /** @return array{0: string|null, 1: string|null, 2: array<string, string>} */
    private function resolveIdentity(User $user, string $role): array
    {
        if ($role === 'student') {
            $student = Student::query()
                ->where('email', $user->email)
                ->orWhere('user_id', $user->id)
                ->first();

            if ($student instanceof Student) {
                $attributes = [];
                if (filled($student->student_id)) {
                    $attributes['student_id'] = (string) $student->student_id;
                }
                if (filled($student->Course?->code)) {
                    $attributes['course'] = (string) $student->Course?->code;
                }

                return [$student->first_name, $student->last_name, $attributes];
            }
        }

        if ($role === 'faculty') {
            $faculty = Faculty::query()->where('email', $user->email)->first();
            if ($faculty instanceof Faculty) {
                $attributes = [];
                if (filled($faculty->faculty_id_number)) {
                    $attributes['faculty_id_number'] = (string) $faculty->faculty_id_number;
                }
                if (filled($faculty->department)) {
                    $attributes['department'] = (string) $faculty->department;
                }

                return [$faculty->first_name, $faculty->last_name, $attributes];
            }
        }

        $parts = preg_split('/\s+/', mb_trim((string) $user->name), 2) ?: [];

        return [$parts[0] ?? null, $parts[1] ?? null, []];
    }
}
