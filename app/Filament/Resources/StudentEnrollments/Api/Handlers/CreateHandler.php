<?php

declare(strict_types=1);

namespace App\Filament\Resources\StudentEnrollments\Api\Handlers;

use App\Filament\Resources\StudentEnrollments\Api\Requests\CreateStudentEnrollmentRequest;
use App\Filament\Resources\StudentEnrollments\StudentEnrollmentResource;
use App\Models\Course;
use App\Models\Subject;
use App\Services\GeneralSettingsService;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Rupadana\ApiService\Http\Handlers;

final class CreateHandler extends Handlers
{
    public static ?string $uri = '/';

    public static ?string $resource = StudentEnrollmentResource::class;

    protected static string $permission = 'Create:StudentEnrollment';

    public static function getMethod()
    {
        return Handlers::POST;
    }

    public static function getModel()
    {
        return self::$resource::getModel();
    }

    /**
     * Create StudentEnrollment
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function handler(CreateStudentEnrollmentRequest $request)
    {
        DB::beginTransaction();

        try {
            $generalSettingsService = app(GeneralSettingsService::class);

            // Create the main enrollment record
            $model = new (self::getModel());
            $model->fill([
                'student_id' => $request->input('student_id'),
                'course_id' => $request->input('course_id'),
                'semester' => $request->input('semester'),
                'academic_year' => $request->input('academic_year'),
                'school_year' => $generalSettingsService->getCurrentSchoolYearString(),
                'downpayment' => $request->input('downpayment', 0),
                'remarks' => $request->input('remarks'),
            ]);
            $model->save();

            // Get course for fee calculation
            $course = Course::find($request->input('course_id'));

            // Calculate totals
            $totalLectureFee = 0;
            $totalLaboratoryFee = 0;

            // Process subject enrollments
            if ($request->filled('subjectsEnrolled')) {
                foreach ($request->input('subjectsEnrolled', []) as $subjectData) {
                    $subject = Subject::find($subjectData['subject_id']);

                    // Calculate fees based on subject type
                    if (isset($subjectData['is_modular']) && $subjectData['is_modular']) {
                        // Modular subjects have fixed 2400 fee
                        $lectureFee = 2400;
                        $laboratoryFee = 0;
                    } else {
                        // Calculate lecture fee
                        $totalUnits = $subject->lecture + $subject->laboratory;
                        $lectureFee = $totalUnits * $course->lec_per_unit;

                        // Apply NSTP 50% discount
                        if (str_contains(mb_strtoupper((string) $subject->code), 'NSTP')) {
                            $lectureFee *= 0.5;
                        }

                        // Calculate laboratory fee
                        $laboratoryFee = 1 * $course->lab_per_unit;
                    }

                    // Create subject enrollment
                    $model->subjectsEnrolled()->create([
                        'subject_id' => $subjectData['subject_id'],
                        'class_id' => $subjectData['class_id'],
                        'is_modular' => $subjectData['is_modular'] ?? false,
                        'enrolled_lecture_units' => $subject->lecture,
                        'enrolled_laboratory_units' => $subject->laboratory,
                        'lecture_fee' => $lectureFee,
                        'laboratory_fee' => $laboratoryFee,
                        'school_year' => $generalSettingsService->getCurrentSchoolYearString(),
                        'semester' => $request->input('semester'),
                        'academic_year' => $request->input('academic_year'),
                    ]);

                    // Add to totals
                    $totalLectureFee += $lectureFee;
                    $totalLaboratoryFee += $laboratoryFee;
                }
            }

            // Calculate tuition with discount
            $discount = $request->input('discount', 0);
            $discountMultiplier = 1 - ($discount / 100);
            $discountedLectureFee = $totalLectureFee * $discountMultiplier;

            // Get miscellaneous fees
            $miscellaneousFees = $course->getMiscellaneousFee();

            // Calculate totals
            $totalTuition = $discountedLectureFee + $totalLaboratoryFee;
            $overallTuition = $totalTuition + $miscellaneousFees;

            // Add additional fees to overall tuition
            $additionalFeesTotal = 0;
            if ($request->filled('additionalFees')) {
                foreach ($request->input('additionalFees', []) as $feeData) {
                    $additionalFeesTotal += $feeData['amount'];
                }
                $overallTuition += $additionalFeesTotal;
            }

            // Calculate balance
            $downpayment = $request->input('downpayment', 0);
            $totalBalance = $overallTuition - $downpayment;

            // Create StudentTuition record
            $model->studentTuition()->create([
                'discount' => $discount,
                'total_lectures' => $discountedLectureFee,
                'total_laboratory' => $totalLaboratoryFee,
                'total_tuition' => $totalTuition,
                'total_miscelaneous_fees' => $miscellaneousFees,
                'overall_tuition' => $overallTuition,
                'downpayment' => $downpayment,
                'total_balance' => $totalBalance,
            ]);

            // Create additional fees
            if ($request->filled('additionalFees')) {
                foreach ($request->input('additionalFees', []) as $feeData) {
                    $model->additionalFees()->create([
                        'fee_name' => $feeData['fee_name'],
                        'amount' => $feeData['amount'],
                        'description' => $feeData['description'] ?? null,
                        'is_separate_transaction' => $feeData['is_separate_transaction'] ?? false,
                    ]);
                }
            }

            DB::commit();

            // Load relationships for response
            $model->load([
                'student.course',
                'course',
                'subjectsEnrolled.subject',
                'subjectsEnrolled.class',
                'studentTuition',
                'additionalFees',
            ]);

            return self::sendSuccessResponse($model, 'Successfully Create Resource');

        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Failed to create student enrollment', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to create enrollment',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
