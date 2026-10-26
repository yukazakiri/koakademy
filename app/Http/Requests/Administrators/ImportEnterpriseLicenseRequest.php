<?php

declare(strict_types=1);

namespace App\Http\Requests\Administrators;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

final class ImportEnterpriseLicenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof User && $user->is_super_admin;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return ['license' => ['required', 'file', 'max:64']];
    }
}
