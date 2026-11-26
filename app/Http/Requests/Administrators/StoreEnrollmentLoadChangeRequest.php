<?php

declare(strict_types=1);

namespace App\Http\Requests\Administrators;

use App\Enums\UserRole;
use App\Models\EnrollmentLoadChange;
use App\Models\StudentEnrollment;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreEnrollmentLoadChangeRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        if (! $user instanceof User) {
            return false;
        }

        $enrollment = $this->route('enrollment');
        if ($enrollment instanceof StudentEnrollment && ($user->can('update', $enrollment) || $user->can('Update:StudentEnrollment'))) {
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
        ], true);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'change_type' => ['required', 'string', Rule::in([
                EnrollmentLoadChange::TypeAdd,
                EnrollmentLoadChange::TypeDrop,
                EnrollmentLoadChange::TypeSwap,
            ])],
            'added_subject_id' => ['nullable', 'integer', 'exists:subject,id'],
            'added_class_id' => ['nullable', 'integer', 'exists:classes,id'],
            'dropped_subject_enrollment_id' => ['nullable', 'integer', 'exists:subject_enrollments,id'],
            'reason' => ['nullable', 'string', 'max:1000'],
            'apply_immediately' => ['boolean'],
            'force_overload' => ['boolean'],
            'confirm_grade_loss' => ['boolean'],
            'override_window' => ['boolean'],
            'override_holds' => ['boolean'],
            'override_holds_reason' => ['nullable', 'string', 'max:500'],
        ];
    }
}
