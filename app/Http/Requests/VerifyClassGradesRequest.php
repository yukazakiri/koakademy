<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

final class VerifyClassGradesRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof User && (
            $user->can('Update:ClassEnrollment')
            || $user->isAdministrative()
            || $user->role === UserRole::Registrar
            || $user->role === UserRole::AssistantRegistrar
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
