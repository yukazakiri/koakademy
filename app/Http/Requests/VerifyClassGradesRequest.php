<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

final class VerifyClassGradesRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool True if authorized, false otherwise.
     */
    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof User && (
            $user->can('Update:ClassEnrollment')
            || in_array($user->role, [
                UserRole::Developer,
                UserRole::SuperAdmin,
                UserRole::Admin,
                UserRole::Registrar,
                UserRole::AssistantRegistrar,
            ], true)
            || $user->role?->isFaculty()
        );
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'verification_notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * The note to store alongside the verification, when one was supplied.
     */
    public function verificationNotes(): ?string
    {
        $notes = $this->input('verification_notes');

        return is_string($notes) ? $notes : null;
    }
}
