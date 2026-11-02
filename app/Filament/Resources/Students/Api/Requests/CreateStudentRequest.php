<?php

declare(strict_types=1);

namespace App\Filament\Resources\Students\Api\Requests;

use App\Models\User;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

final class CreateStudentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof User && ! $user->role?->isStudent() && ! $user->role?->isFaculty()
            && $user->role !== \App\Enums\UserRole::Dean && $user->can('create', \App\Models\Student::class);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $type = (string) $this->input('student_type');
        $configuration = app(\App\Services\StudentConfigurationService::class);
        $configuration->assertRequiredFieldsSupported(['email', 'phone', 'civil_status', 'nationality', 'religion']);
        $rules = [
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'middle_name' => ['nullable', 'string', 'max:100'],
            'suffix' => ['nullable', 'string', 'max:20'],
            'gender' => ['required', 'in:male,female,other,prefer_not_to_say'],
            'birth_date' => ['required', 'date', 'before:today'],
            'student_type' => ['required', 'string'],
            'status' => ['required', \Illuminate\Validation\Rule::enum(\App\Enums\StudentStatus::class)],
            'academic_year' => ['required', 'integer'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'civil_status' => ['nullable', 'string', 'max:50'],
            'nationality' => ['nullable', 'string', 'max:50'],
            'religion' => ['nullable', 'string', 'max:50'],
            'remarks' => ['nullable', 'string'],
            'address' => ['nullable', 'string', 'max:500'],
            // Legacy clients sent JSON as text; preserve that public contract.
            'contacts' => ['nullable', function (string $attribute, mixed $value, Closure $fail): void {
                if (! is_array($value) && ! is_string($value)) {
                    $fail('The contacts must be text or an object.');
                }
            }],
            'identifier_assignment' => ['nullable', 'in:automatic,manual'],
            'course_id' => [\Illuminate\Validation\Rule::requiredIf($type !== 'shs'), 'nullable', 'exists:courses,id'],
            'shs_strand_id' => [\Illuminate\Validation\Rule::requiredIf($type === 'shs'), 'nullable', 'exists:shs_strands,id'],
            'student_id' => $configuration->usesExternal($type) || $this->input('identifier_assignment', $this->filled('student_id') ? 'manual' : 'automatic') === 'automatic'
                ? ['exclude'] : app(\App\Services\IdentifierGenerator::class)->getStudentIdValidationRules(\App\Enums\StudentType::tryFrom($type)),
        ];

        return $configuration->rules($rules, $type);
    }
}
