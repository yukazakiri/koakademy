<?php

declare(strict_types=1);

namespace App\Rules;

use App\Models\Student;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

final readonly class PreviousSemesterCleared implements ValidationRule
{
    public function __construct(
        private ?string $schoolYear = null,
        private ?int $semester = null
    ) {}

    /**
     * Run the validation rule.
     *
     * @param  Closure(string, ?string=): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        // $value should be the student_id
        if (! $value) {
            return;
        }

        $student = Student::find($value);

        if (! $student) {
            $fail('The selected student does not exist.');

            return;
        }

        $validation = $student->validateEnrollmentClearance($this->schoolYear, $this->semester);

        if (! $validation['allowed']) {
            $fail($validation['message']);
        }
    }
}
