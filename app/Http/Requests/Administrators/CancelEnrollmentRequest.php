<?php

declare(strict_types=1);

namespace App\Http\Requests\Administrators;

use App\Models\StudentEnrollment;
use Illuminate\Foundation\Http\FormRequest;

final class CancelEnrollmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $enrollment = $this->route('enrollment');
        $user = $this->user();

        return $enrollment instanceof StudentEnrollment
            && $user !== null
            && ($user->can('Cancel:StudentEnrollment') || $user->hasRole('super_admin'));
    }

    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'max:2000'],
            'idempotency_key' => ['required', 'string', 'max:96'],
            'acknowledge_outstanding_payments' => ['nullable', 'boolean'],
        ];
    }
}
