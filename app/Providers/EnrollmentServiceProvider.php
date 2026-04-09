<?php

declare(strict_types=1);

namespace App\Providers;

use App\Enums\GradeEnum;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\StudentTuition;
use App\Models\Subject;
use App\Models\SubjectEnrollment;
use Exception;
// Added missing use statement
use Filament\Schemas\Components\Utilities\Get;
// use Filament\Forms\Get;
// use Filament\Forms\Set;
use Filament\Schemas\Components\Utilities\Set;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\HtmlString;
use Illuminate\Support\ServiceProvider;

// Assuming this exists based on CreateStudentEnrollment

final class EnrollmentServiceProvider extends ServiceProvider
{
    /**
     * Updates the tuition totals based on enrolled subjects and form state.
     * Handles both automatic calculation and respecting manual overrides.
     *
     * @param  Get  $get  Filament Get instance
     * @param  Set  $set  Filament Set instance
     * @param  StudentEnrollment|array|null  $record  The current enrollment record (null if creating)
     */
    public static function updateTotals(Get $get, Set $set, $record = null): void // Changed type hint to allow array and null
    {
        // Skip if record is not a StudentEnrollment
        if ($record !== null && ! ($record instanceof StudentEnrollment)) {
            return;
        }

        // Always recalculate from form state - removed early return logic
        // This ensures manual changes to lecture/lab fees are properly reflected
        $subjectsEnrolled = $get('subjectsEnrolled');
        $totalLecture = 0;
        $totalLaboratory = 0;
        $totalModularSubjects = 0;
        $miscellaneousFee = 3500; // Default miscellaneous fee

        // Eager load course if record exists to get miscellaneous fees
        // Ensure student relationship is loaded before accessing course
        if ($record && $record->relationLoaded('student') && $record->student && $record->student->relationLoaded('course') && $record->student->course) {
            $miscellaneousFee = $record->student->course->miscelaneous ?? 3500;
        } elseif ($record && $record->student_id) {
            // If record exists but course not loaded, fetch it
            $student = Student::with('course')->find($record->student_id);
            if ($student && $student->course) {
                $miscellaneousFee = $student->course->miscelaneous ?? 3500;
            }
        } elseif ($get('student_id')) {
            // If creating and student_id is set, fetch student and course
            $student = Student::with('course')->find($get('student_id'));
            if ($student && $student->course) {
                $miscellaneousFee = $student->course->miscelaneous ?? 3500;
            }
        }

        if ($subjectsEnrolled !== null) {
            foreach ($subjectsEnrolled as $subjectEnrolled) {
                if (! empty($subjectEnrolled['subject_id'])) {
                    // Use the values from the form for lecture and lab fees
                    // Lecture fee does NOT include modular fee - it's calculated separately
                    $totalLecture += (float) ($subjectEnrolled['lecture'] ?? 0);
                    $totalLaboratory += (float) ($subjectEnrolled['laboratory'] ?? 0);

                    // Count modular subjects to calculate modular fee separately
                    if (! empty($subjectEnrolled['is_modular'])) {
                        $totalModularSubjects++;
                    }
                }
            }
        }

        // Calculate total modular fee (2400 per modular subject)
        $totalModularFee = $totalModularSubjects * 2400;

        $discount = (int) ($get('discount') ?? 0);
        $discountedLecture = $totalLecture * (1 - $discount / 100);
        $discountedTuition = $discountedLecture + $totalLaboratory;

        // Calculate total additional fees
        // Handle both form state (create/edit) and database relationship (edit mode)
        $formAdditionalFees = collect($get('additionalFees') ?? [])
            ->sum(fn ($fee): float => (float) ($fee['amount'] ?? 0));

        $dbAdditionalFees = 0;
        if ($record instanceof StudentEnrollment && $record->exists) {
            $dbAdditionalFees = $record->additionalFees()->sum('amount');
        }

        // Use form data if available (during form interaction), otherwise use database data
        $additionalFees = $formAdditionalFees > 0 ? $formAdditionalFees : $dbAdditionalFees;

        // Overall Total = Discounted Tuition + Miscellaneous + Additional Fees + Modular Fees
        $overallTotal = $discountedTuition + $miscellaneousFee + $additionalFees + $totalModularFee;
        $downPayment = (float) ($get('downpayment') ?? 0); // Ensure downpayment is float
        $balance = $overallTotal - $downPayment;

        // Set the calculated values in the form
        $set('total_lectures', number_format($discountedLecture, 2, '.', ''));
        $set('total_laboratory', number_format($totalLaboratory, 2, '.', ''));
        $set('Total_Tuition', number_format($discountedTuition, 2, '.', ''));
        $set('miscellaneous', number_format($miscellaneousFee, 2, '.', ''));

        // Always update overall_total with calculated value (including additional fees)
        // The manual modification flag should not prevent updates when additional fees change
        $set('overall_total', number_format($overallTotal, 2, '.', ''));
        $set('total_balance', number_format($balance, 2, '.', ''));

        // Reset the manual modification flag since we're updating with calculated values
        $set('is_overall_manually_modified', false);

        // Update or create tuition record if in edit context
        // Note: In 'create' context, tuition is typically handled in `afterCreate`.
        // This call might be redundant here if also called from afterStateUpdated/deleteAction.
        // Consider calling updateOrCreateTuition only when necessary (e.g., on form save).
        // For now, keep the logic but be aware it might need adjustment.
        if ($record instanceof StudentEnrollment) {
            self::updateOrCreateTuition( // Changed $this to self::
                $record,
                $get,
                $discountedTuition,
                $overallTotal,
                $discountedLecture,
                $totalLaboratory,
                $miscellaneousFee,
                $discount,
                $downPayment
            );
        }
    }

    /**
     * Recalculates totals specifically when manual overrides or discounts change.
     *
     * @param  Get  $get  Filament Get instance
     * @param  Set  $set  Filament Set instance
     * @param  StudentEnrollment|array|null  $record  The current enrollment record
     */
    public static function recalculateTotals(Get $get, Set $set, $record = null): void // Changed type hint
    {
        // Skip if record is not a StudentEnrollment
        if ($record !== null && ! ($record instanceof StudentEnrollment)) {
            return;
        }

        // Use the manually entered lecture/lab totals if available, otherwise calculate from original
        $originalLecture = (float) ($get('original_lecture_amount') ?: $get('total_lectures')); // Fallback to current total if original not set
        $totalLaboratory = (float) $get('total_laboratory'); // Use the current lab total from the form
        $discount = (int) ($get('discount') ?? 0);
        $miscellaneousFee = 3500; // Default

        // Count modular subjects from form state
        $subjectsEnrolled = $get('subjectsEnrolled');
        $totalModularSubjects = 0;
        if ($subjectsEnrolled !== null) {
            foreach ($subjectsEnrolled as $subjectEnrolled) {
                if (! empty($subjectEnrolled['subject_id']) && ! empty($subjectEnrolled['is_modular'])) {
                    $totalModularSubjects++;
                }
            }
        }
        $totalModularFee = $totalModularSubjects * 2400;

        // Fetch miscellaneous fee based on record or student_id
        // Ensure student relationship is loaded before accessing course
        if ($record && $record->relationLoaded('student') && $record->student && $record->student->relationLoaded('course') && $record->student->course) {
            $miscellaneousFee = $record->student->course->miscelaneous ?? 3500;
        } elseif ($record && $record->student_id) {
            $student = Student::with('course')->find($record->student_id);
            if ($student && $student->course) {
                $miscellaneousFee = $student->course->miscelaneous ?? 3500;
            }
        } elseif ($get('student_id')) {
            $student = Student::with('course')->find($get('student_id'));
            if ($student && $student->course) {
                $miscellaneousFee = $student->course->miscelaneous ?? 3500;
            }
        }

        // Apply discount only to the lecture portion, using the original/current lecture amount
        $discountedLectures = $originalLecture * (1 - $discount / 100);
        $discountedTuition = $discountedLectures + $totalLaboratory; // Add the current lab total

        // Calculate total additional fees
        // Handle both form state (create/edit) and database relationship (edit mode)
        $formAdditionalFees = collect($get('additionalFees') ?? [])
            ->sum(fn ($fee): float => (float) ($fee['amount'] ?? 0));

        $dbAdditionalFees = 0;
        if ($record instanceof StudentEnrollment && $record->exists) {
            $dbAdditionalFees = $record->additionalFees()->sum('amount');
        }

        // Use form data if available (during form interaction), otherwise use database data
        $additionalFees = $formAdditionalFees > 0 ? $formAdditionalFees : $dbAdditionalFees;

        // Overall Total = Discounted Tuition + Miscellaneous + Additional Fees + Modular Fees
        $overallTotal = $discountedTuition + $miscellaneousFee + $additionalFees + $totalModularFee;
        $downPayment = (float) ($get('downpayment') ?? 0); // Ensure float
        $balance = $overallTotal - $downPayment;

        // Update the form fields
        $set('total_lectures', number_format($discountedLectures, 2, '.', ''));
        $set('Total_Tuition', number_format($discountedTuition, 2, '.', ''));
        $set('miscellaneous', number_format($miscellaneousFee, 2, '.', '')); // Set misc fee

        // Always update overall_total with calculated value (including additional fees)
        // The manual modification flag should not prevent updates when additional fees change
        $set('overall_total', number_format($overallTotal, 2, '.', ''));
        $set('total_balance', number_format($balance, 2, '.', ''));

        // Reset the manual modification flag since we're updating with calculated values
        $set('is_overall_manually_modified', false);

        // Persist changes if in edit context
        if ($record instanceof StudentEnrollment) {
            self::updateOrCreateTuition( // Changed $this to self::
                $record,
                $get,
                $discountedTuition,
                $overallTotal,
                $discountedLectures, // Pass the newly calculated discounted lecture
                $totalLaboratory,    // Pass the current total laboratory
                $miscellaneousFee,
                $discount,
                $downPayment
            );
        }
    }

    /**
     * Generates an HTML table representing the student's subject checklist.
     *
     * @param  int|null  $academicYear  (Currently unused in the logic, but kept for signature consistency)
     */
    public static function generateChecklistTable(?int $courseId, ?int $studentId, ?int $academicYear): HtmlString // Made static
    {
        if ($courseId === null || $courseId === 0 || ($studentId === null || $studentId === 0)) {
            return new HtmlString('<p class="text-warning-600">Please select a student and course first.</p>');
        }

        try {
            $subjects = Subject::query()->select('id', 'code', 'title', 'semester', 'academic_year', 'units')
                ->where('course_id', $courseId)
                ->orderBy('academic_year')
                ->orderBy('semester')
                ->get()
                ->groupBy(['academic_year', 'semester']);

            $enrolledSubjects = SubjectEnrollment::query()->where('student_id', $studentId)
                ->get()
                ->keyBy('subject_id'); // Key by subject_id for easy lookup

            $table = '<div class="space-y-4">';

            foreach ($subjects as $year => $semesters) {
                $table .=
                    '<div class="rounded-xl border border-gray-200 dark:border-gray-700 p-4">';
                $table .=
                    '<h2 class="text-xl font-bold mb-4">Academic Year '.
                    $year.
                    '</h2>';

                foreach ($semesters as $semester => $semesterSubjects) {
                    $table .= '<div class="mb-6">';
                    $table .=
                        '<h3 class="text-lg font-medium mb-2">Semester '.
                        $semester.
                        '</h3>';
                    $table .= '<div class="overflow-x-auto">';
                    $table .=
                        '<table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">';
                    $table .= '<thead class="bg-gray-50 dark:bg-gray-800">';
                    $table .= '<tr>';
                    $table .=
                        '<th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Code</th>';
                    $table .=
                        '<th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Title</th>';
                    $table .=
                        '<th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Units</th>';
                    $table .=
                        '<th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Status</th>';
                    $table .=
                        '<th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Grade</th>';
                    $table .= '</tr>';
                    $table .= '</thead>';
                    $table .=
                        '<tbody class="bg-white dark:bg-gray-900 divide-y divide-gray-200 dark:divide-gray-700">';

                    foreach ($semesterSubjects as $index => $subject) {
                        $enrolledSubject = $enrolledSubjects->get($subject->id); // Use get() for safety
                        $grade = $enrolledSubject->grade ?? '-';
                        $status = 'Not Completed';
                        $statusColor = 'bg-danger-100 text-danger-800 dark:bg-danger-900 dark:text-danger-300';

                        if ($enrolledSubject) {
                            // Determine status based on grade presence and value
                            if ($grade !== null && $grade !== '-' && is_numeric($grade) && $grade >= 75) {
                                $status = 'Completed';
                                $statusColor = 'bg-success-100 text-success-800 dark:bg-success-900 dark:text-success-300';
                            } elseif ($grade !== null && $grade !== '-' && is_numeric($grade) && $grade < 75) {
                                $status = 'Failed'; // Or another appropriate status
                                $statusColor = 'bg-danger-100 text-danger-800 dark:bg-danger-900 dark:text-danger-300';
                            } else {
                                // Grade might be null, '-', or non-numeric - consider it In Progress or Pending
                                $status = 'In Progress'; // Or Pending?
                                $statusColor = 'bg-warning-100 text-warning-800 dark:bg-warning-900 dark:text-warning-300';
                            }
                        }

                        $gradeClass = 'text-gray-800 dark:text-gray-200'; // Default class
                        if (is_numeric($grade)) {
                            $gradeValue = (float) $grade;
                            // Assuming GradeEnum exists and has fromGrade and getColor methods
                            if (class_exists(GradeEnum::class)) {
                                $gradeEnum = GradeEnum::fromGrade($gradeValue);
                                $gradeColor = $gradeEnum->getColor();
                                $gradeClass = match ($gradeColor) {
                                    'primary' => 'text-primary-600 dark:text-primary-400 font-bold',
                                    'info' => 'text-info-600 dark:text-info-400 font-bold',
                                    'success' => 'text-success-600 dark:text-success-400 font-bold',
                                    'warning' => 'text-warning-600 dark:text-warning-400 font-bold',
                                    'danger' => 'text-danger-600 dark:text-danger-400 font-bold',
                                    default => $gradeClass, // Keep default if color not matched
                                };
                            }
                        }

                        $rowClass =
                            $index % 2 === 0
                                ? 'bg-gray-50 dark:bg-gray-800'
                                : 'bg-white dark:bg-gray-900';

                        $table .= '<tr class="'.$rowClass.'">';
                        $table .=
                            '<td class="px-6 py-4 whitespace-nowrap text-sm">'.
                            htmlspecialchars((string) $subject->code).
                            '</td>';
                        $table .=
                            '<td class="px-6 py-4 whitespace-nowrap text-sm">'.
                            htmlspecialchars((string) $subject->title).
                            '</td>';
                        $table .=
                            '<td class="px-6 py-4 whitespace-nowrap text-sm text-right">'.
                            htmlspecialchars($subject->units ?? '-'). // Handle potential null units
                            '</td>';
                        $table .=
                            '<td class="px-6 py-4 whitespace-nowrap text-sm"><span class="'.
                            $statusColor.
                            ' px-2 py-1 rounded-md text-xs font-medium">'. // Added text-xs font-medium
                            htmlspecialchars($status).
                            '</span></td>';
                        $table .=
                            '<td class="px-6 py-4 whitespace-nowrap text-sm '.
                            $gradeClass.
                            '">'.
                            htmlspecialchars((string) $grade).
                            '</td>';
                        $table .= '</tr>';
                    }

                    $table .= '</tbody>';
                    $table .= '</table>';
                    $table .= '</div>'; // overflow-x-auto
                    $table .= '</div>'; // mb-6
                }

                $table .= '</div>'; // rounded-xl border...
            }

            $table .= '</div>'; // space-y-4

            return new HtmlString($table);

        } catch (Exception $exception) {
            // Log the error for debugging
            Log::error('Error generating checklist table: '.$exception->getMessage(), [
                'courseId' => $courseId,
                'studentId' => $studentId,
                'exception' => $exception,
            ]);

            return new HtmlString('<p class="text-danger-600">Error generating checklist. Please check logs.</p>');
        }
    }

    /**
     * Updates or creates tuition record when overall total is manually modified.
     * This method handles the case where the user manually overrides the overall tuition.
     *
     * @param  StudentEnrollment  $studentEnrollment  The enrollment record
     * @param  Get  $get  Filament Get instance
     * @param  float  $manualOverallTotal  The manually set overall total
     */
    public static function updateOrCreateTuitionWithManualOverride(
        StudentEnrollment $studentEnrollment,
        Get $get,
        float $manualOverallTotal
    ): void {
        // Ensure the record is valid
        if (! $studentEnrollment->id) {
            return;
        }

        // Get current values from form
        $totalLectures = (float) ($get('total_lectures') ?? 0);
        $totalLaboratory = (float) ($get('total_laboratory') ?? 0);
        $totalTuition = (float) ($get('Total_Tuition') ?? 0);
        $miscellaneousFee = (float) ($get('miscellaneous') ?? 0);
        $discount = (int) ($get('discount') ?? 0);
        $downPayment = (float) ($get('downpayment') ?? 0);

        // Calculate balance based on manual overall total
        $balance = $manualOverallTotal - $downPayment;

        $tuitionData = [
            'student_id' => $studentEnrollment->student_id ?? $get('student_id'),
            'total_tuition' => $totalTuition,
            'total_balance' => $balance,
            'total_lectures' => $totalLectures,
            'total_laboratory' => $totalLaboratory,
            'total_miscelaneous_fees' => $miscellaneousFee,
            'discount' => $discount,
            'downpayment' => $downPayment,
            'overall_tuition' => $manualOverallTotal, // Use the manually set value
        ];

        // Ensure student_id is present
        if (empty($tuitionData['student_id'])) {
            Log::error('Cannot update/create tuition: student_id is missing.', ['enrollment_id' => $studentEnrollment->id]);

            return;
        }

        // Update or create the tuition record
        StudentTuition::query()->updateOrCreate(['enrollment_id' => $studentEnrollment->id], $tuitionData);
    }

    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }

    /**
     * Updates or creates the StudentTuition record associated with an enrollment.
     * NOTE: This remains static as it's called by other static form utility methods.
     *
     * @param  StudentEnrollment  $studentEnrollment  The enrollment record
     * @param  Get  $get  Filament Get instance
     * @param  float  $discountedTuition  Calculated total tuition after discount
     * @param  float  $overallTotal  Calculated overall total including miscellaneous fees
     * @param  float  $discountedLecture  Calculated lecture fee after discount
     * @param  float  $totalLaboratory  Calculated total laboratory fee
     * @param  float  $miscellaneousFee  Miscellaneous fee amount
     * @param  int  $discount  Discount percentage applied
     * @param  float  $downPayment  Down payment amount
     */
    private static function updateOrCreateTuition(// Made static
        StudentEnrollment $studentEnrollment,
        Get $get,
        float $discountedTuition,
        float $overallTotal,
        float $discountedLecture,
        float $totalLaboratory,
        float $miscellaneousFee,
        int $discount,
        float $downPayment
    ): void {
        // Ensure the record is a valid StudentEnrollment
        if (! $studentEnrollment->id) {
            return;
        }

        $tuitionData = [
            'student_id' => $studentEnrollment->student_id ?? $get('student_id'), // Ensure student_id is set
            'total_tuition' => $discountedTuition,
            'total_balance' => $overallTotal - $downPayment,
            'total_lectures' => $discountedLecture,
            'total_laboratory' => $totalLaboratory,
            'total_miscelaneous_fees' => $miscellaneousFee,
            'discount' => $discount,
            'downpayment' => $downPayment,
            'overall_tuition' => $overallTotal,
            // Consider adding semester, school_year, academic_year if needed here
            // 'semester' => $record->semester ?? $get('semester'),
            // 'school_year' => GeneralSetting::first()?->getSchoolYearString(), // Fetch current school year
            // 'academic_year' => $record->academic_year ?? $get('academic_year'),
        ];

        // Ensure student_id is present before attempting to update/create
        if (empty($tuitionData['student_id'])) {
            // Log error or handle appropriately - cannot save tuition without student_id
            Log::error('Cannot update/create tuition: student_id is missing.', ['enrollment_id' => $studentEnrollment->id]);

            return;
        }

        // Use updateOrCreate for efficiency and atomicity
        StudentTuition::query()->updateOrCreate(
            ['enrollment_id' => $studentEnrollment->id],
            // Find by enrollment_id
            $tuitionData
        );
    }

    // Methods checkFullClasses, createStudentTuition, verifyByHeadDept, verifyByCashier, and resendAssessmentNotification
    // have been moved to EnrollmentService or are static utility methods.
}
