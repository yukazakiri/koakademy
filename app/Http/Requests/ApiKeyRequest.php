<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class ApiKeyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'abilities' => ['nullable', 'array', 'min:1'],
            'abilities.*' => ['string', 'in:read,write,mcp:read,mcp:write'],
            'expires_at' => ['nullable', 'date', 'after:now'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'A name for the API key is required.',
            'name.max' => 'The API key name cannot exceed 255 characters.',
            'abilities.*.in' => 'Abilities must be one of: read, write, mcp:read, or mcp:write.',
            'abilities.min' => 'At least one ability is required.',
            'expires_at.after' => 'The expiration date must be in the future.',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $abilities = $this->input('abilities');

            if (! is_array($abilities)) {
                return;
            }

            if (in_array('mcp:write', $abilities, true) && ! in_array('mcp:read', $abilities, true)) {
                $validator->errors()->add('abilities', 'The "mcp:write" ability requires "mcp:read".');
            }

            $user = $this->user();
            $isStaffOrAdmin = $user && ($user->isFaculty() || $user->role?->canAccessAdminPortal());
            $hasMcpWriteAbility = in_array('mcp:write', $abilities, true);

            if ($hasMcpWriteAbility && ! $isStaffOrAdmin) {
                $validator->errors()->add('abilities', 'The "mcp:write" ability is available for staff and administrative accounts only.');
            }
        });
    }
}
