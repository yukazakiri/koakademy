<?php

declare(strict_types=1);

namespace App\Filament\Resources\StudentEnrollments\Api\Requests;

use App\Rules\PreviousSemesterCleared;
use App\Services\GeneralSettingsService;
use Illuminate\Foundation\Http\FormRequest;

final class CreateStudentEnrollmentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $generalSettingsService = app(GeneralSettingsService::class);

        return [
            // Required enrollment fields
            'student_id' => [
                'required',
                'exists:students,id',
                new PreviousSemesterCleared(
                    $generalSettingsService->getCurrentSchoolYearString(),
                    $generalSettingsService->getCurrentSemester()
                ),
            ],
            'course_id' => 'required|exists:courses,id',
            'semester' => 'required|integer|in:1,2',
            'academic_year' => 'required|integer|min:1|max:4',
            'remarks' => 'nullable|string',

            // Subject enrollments
            'subjectsEnrolled' => 'nullable|array',
            'subjectsEnrolled.*.subject_id' => 'required_with:subjectsEnrolled|exists:subjects,id',
            'subjectsEnrolled.*.class_id' => 'required_with:subjectsEnrolled|exists:classes,id',
            'subjectsEnrolled.*.is_modular' => 'nullable|boolean',
            'subjectsEnrolled.*.enrolled_lecture_units' => 'nullable|integer|min:0',
            'subjectsEnrolled.*.enrolled_laboratory_units' => 'nullable|integer|min:0',
            'subjectsEnrolled.*.lecture_fee' => 'nullable|numeric|min:0',
            'subjectsEnrolled.*.laboratory_fee' => 'nullable|numeric|min:0',

            // Assessment fields
            'discount' => 'nullable|integer|min:0|max:100',
            'total_lectures' => 'nullable|numeric|min:0',
            'total_laboratory' => 'nullable|numeric|min:0',
            'Total_Tuition' => 'nullable|numeric|min:0',
            'miscellaneous' => 'nullable|numeric|min:0',
            'overall_total' => 'nullable|numeric|min:0',
            'downpayment' => 'nullable|numeric|min:0',
            'total_balance' => 'nullable|numeric|min:0',

            // Additional fees
            'additionalFees' => 'nullable|array',
            'additionalFees.*.fee_name' => 'required_with:additionalFees|string|max:255',
            'additionalFees.*.amount' => 'required_with:additionalFees|numeric|min:0',
            'additionalFees.*.description' => 'nullable|string',
            'additionalFees.*.is_separate_transaction' => 'nullable|boolean',
        ];
    }

    /**
     * Get custom attribute names for validator errors.
     */
    public function attributes(): array
    {
        return [
            'student_id' => 'student',
            'course_id' => 'course',
            'subjectsEnrolled.*.subject_id' => 'subject',
            'subjectsEnrolled.*.class_id' => 'section',
            'additionalFees.*.fee_name' => 'fee name',
            'additionalFees.*.amount' => 'fee amount',
        ];
    }
}
