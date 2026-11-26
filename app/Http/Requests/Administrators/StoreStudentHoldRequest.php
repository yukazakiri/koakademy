<?php

declare(strict_types=1);

namespace App\Http\Requests\Administrators;

use App\Enums\UserRole;
use App\Models\StudentHold;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreStudentHoldRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        if (! $user instanceof User) {
            return false;
        }

        if ($user->can('Update:StudentEnrollment') || $user->hasRole('super_admin')) {
            return true;
        }

        return in_array($user->role, [
            UserRole::Developer,
            UserRole::SuperAdmin,
            UserRole::Admin,
            UserRole::Registrar,
            UserRole::AssistantRegistrar,
            UserRole::Dean,
            UserRole::AssociateDean,
            UserRole::DepartmentHead,
            UserRole::ProgramChair,
            UserRole::Cashier,
            UserRole::AccountingOfficer,
            UserRole::BursarOfficer,
            UserRole::Librarian,
            UserRole::GuidanceCounselor,
            UserRole::StudentAffairsOfficer,
        ], true);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'student_id' => ['required', 'integer', 'exists:students,id'],
            'office' => ['required', 'string', Rule::in(array_keys(StudentHold::OFFICES))],
            'hold_type' => ['nullable', 'string', 'max:60'],
            'severity' => ['required', 'string', Rule::in([StudentHold::SeverityBlocking, StudentHold::SeverityWarning])],
            'title' => ['required', 'string', 'max:160'],
            'reason' => ['required', 'string', 'max:2000'],
            'blocks_enrollment' => ['boolean'],
            'blocks_add_drop' => ['boolean'],
            'blocks_grade_release' => ['boolean'],
            'expires_at' => ['nullable', 'date'],
        ];
    }
}
