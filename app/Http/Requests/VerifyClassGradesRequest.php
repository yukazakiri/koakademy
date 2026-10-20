<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class VerifyClassGradesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
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
