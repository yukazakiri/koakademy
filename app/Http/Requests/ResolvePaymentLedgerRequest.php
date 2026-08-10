<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

final class ResolvePaymentLedgerRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof User && $user->canAccessAdminPortal();
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'student_identifiers' => ['required', 'array', 'min:1', 'max:100'],
            'student_identifiers.*' => ['required', 'string', 'max:64', 'distinct'],
        ];
    }
}
