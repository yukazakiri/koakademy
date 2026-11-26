<?php

declare(strict_types=1);

namespace App\Http\Requests\Administrators;

use App\Enrollment\EnrollmentTransitionEngine;
use App\Models\StudentEnrollment;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

final class TransitionEnrollmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $enrollment = $this->route('enrollment');
        $user = $this->user();

        if (! $enrollment instanceof StudentEnrollment || ! $user instanceof User) {
            return false;
        }

        if ($user->can('update', $enrollment) || $user->hasRole('super_admin')) {
            return true;
        }

        $available = app(EnrollmentTransitionEngine::class)->availableTransitions($enrollment, $user);
        if ($available === []) {
            return false;
        }

        $requestedKey = $this->input('transition_key');
        if (is_string($requestedKey) && $requestedKey !== '') {
            return collect($available)->contains(
                static fn (array $transition): bool => ($transition['key'] ?? null) === $requestedKey,
            );
        }

        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'transition_key' => ['nullable', 'string', 'max:80'],
            'idempotency_key' => ['required', 'string', 'max:96'],
            'payload' => ['nullable', 'array'],
        ];
    }
}
