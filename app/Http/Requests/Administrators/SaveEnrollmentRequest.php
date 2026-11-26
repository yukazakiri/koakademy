<?php

declare(strict_types=1);

namespace App\Http\Requests\Administrators;

use App\Enums\UserRole;
use App\Models\StudentEnrollment;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

final class SaveEnrollmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        if (! $user instanceof User || ! $user->canAccessAdminPortal()) {
            return false;
        }

        $enrollment = $this->route('enrollment');
        $isUpdate = $enrollment instanceof StudentEnrollment;

        if ($isUpdate) {
            if ($user->can('update', $enrollment) || $user->can('Update:StudentEnrollment')) {
                return true;
            }
        } elseif ($user->can('create', StudentEnrollment::class) || $user->can('Create:StudentEnrollment') || $user->can('Update:StudentEnrollment')) {
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

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'student_id' => ['required', 'exists:students,id'],
            'semester' => ['required', 'integer', 'in:1,2,3'],
            'academic_year' => ['required', 'integer', 'in:1,2,3,4,5'],
            'subjects' => ['required', 'array', 'min:1'],
            'subjects.*.subject_id' => ['required', 'exists:subject,id', 'distinct'],
            'subjects.*.class_id' => ['nullable', 'exists:classes,id'],
            'subjects.*.is_modular' => ['boolean'],
            'subjects.*.exclude_from_tuition' => ['boolean'],
            'subjects.*.lecture_fee' => ['nullable', 'numeric', 'min:0'],
            'subjects.*.laboratory_fee' => ['nullable', 'numeric', 'min:0'],
            'subjects.*.enrolled_lecture_units' => ['required', 'integer', 'min:0'],
            'subjects.*.enrolled_laboratory_units' => ['required', 'integer', 'min:0'],
            'discount' => ['nullable', 'integer', 'min:0', 'max:100'],
            'discount_id' => ['nullable', 'integer', 'exists:enrollment_discounts,id'],
            'miscellaneous_fee' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'downpayment' => ['nullable', 'numeric', 'min:0'],
            'additional_fees' => ['nullable', 'array'],
            'additional_fees.*.id' => ['nullable', 'integer'],
            'additional_fees.*.fee_name' => ['required_with:additional_fees', 'string'],
            'additional_fees.*.amount' => ['required_with:additional_fees', 'numeric', 'min:0'],
            'remarks' => ['nullable', 'string', 'max:2000'],
            'notify_student' => ['nullable', 'boolean'],
            'change_reason' => ['nullable', 'string', 'max:1000'],
            'force_overload' => ['nullable', 'boolean'],
            'override_window' => ['nullable', 'boolean'],
            'override_holds' => ['nullable', 'boolean'],
            'override_holds_reason' => ['nullable', 'string', 'max:1000'],
            'confirm_grade_loss' => ['nullable', 'boolean'],
            'override_unit_overload' => ['nullable', 'boolean'],
            'override_prerequisites' => ['nullable', 'boolean'],
            'record_col_changes' => ['nullable', 'boolean'],
        ];
    }
}
