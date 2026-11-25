<?php

declare(strict_types=1);

namespace App\Http\Requests\Administrators;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

final class UpsertEnrollmentPeriodRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof User && $user->canAccessAdminPortal();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'regular_starts_at' => ['nullable', 'date'],
            'regular_ends_at' => ['nullable', 'date', 'after_or_equal:regular_starts_at'],
            'late_starts_at' => ['nullable', 'date', 'after_or_equal:regular_starts_at'],
            'late_ends_at' => ['nullable', 'date', 'after_or_equal:late_starts_at'],
            'add_drop_deadline_at' => ['nullable', 'date'],
            'late_fee_amount' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
            'late_fee_label' => ['nullable', 'string', 'max:120'],
            'auto_apply_late_fee' => ['boolean'],
            'enforce_windows' => ['boolean'],
            'is_manually_closed' => ['boolean'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
