<?php

declare(strict_types=1);

namespace App\Filament\Resources\StudentEnrollments\Api\Handlers;

use App\Data\Enrollment\EnrollmentContext;
use App\Data\Enrollment\EnrollmentSubmissionData;
use App\Enrollment\EnrollmentAssignmentService;
use App\Enrollment\EnrollmentWorkflowCoordinator;
use App\Filament\Resources\StudentEnrollments\Api\Requests\CreateStudentEnrollmentRequest;
use App\Filament\Resources\StudentEnrollments\StudentEnrollmentResource;
use App\Models\Course;
use App\Models\StudentEnrollment;
use App\Models\User;
use App\Services\EnrollmentService;
use App\Services\GeneralSettingsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Override;
use Rupadana\ApiService\Http\Handlers;

final class CreateHandler extends Handlers
{
    #[Override]
    public static ?string $uri = '/';

    #[Override]
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

    public function handler(CreateStudentEnrollmentRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $course = Course::query()->findOrFail($validated['course_id']);
        $subjects = $validated['subjectsEnrolled'] ?? [];
        $fees = $validated['additionalFees'] ?? [];
        $classes = collect($subjects)->filter(fn (array $subject): bool => ! empty($subject['class_id']))
            ->map(fn (array $subject): array => ['subject_id' => (int) $subject['subject_id'], 'class_id' => (int) $subject['class_id']])->values()->all();
        $billing = ['discount_percentage' => $validated['discount'] ?? 0, 'downpayment' => $validated['downpayment'] ?? 0];
        if (isset($validated['miscellaneous'])) {
            $billing['miscellaneous_fee'] = $validated['miscellaneous'];
        }
        $actor = $request->user();
        $model = app(EnrollmentWorkflowCoordinator::class)->submit(new EnrollmentSubmissionData(
            enrollmentAttributes: [
                'student_id' => $validated['student_id'], 'course_id' => $course->id, 'school_id' => $course->school_id,
                'semester' => $validated['semester'], 'academic_year' => $validated['academic_year'],
                'school_year' => app(GeneralSettingsService::class)->getCurrentSchoolYearString(),
                'downpayment' => $validated['downpayment'] ?? 0, 'remarks' => $validated['remarks'] ?? null,
            ],
            subjects: $subjects,
            classAssignments: $classes,
            additionalFees: $fees,
            billingOverrides: $billing,
            channel: 'api',
            idempotencyKey: (string) ($request->header('Idempotency-Key') ?: Str::uuid()),
            actor: $actor instanceof User ? $actor : null,
        ), function (StudentEnrollment $enrollment) use ($subjects, $fees, $classes, $billing): void {
            $context = EnrollmentContext::fromEnrollment($enrollment);
            $assignment = app(EnrollmentAssignmentService::class);
            $assigned = $assignment->assignSubjects($context, ['source' => 'runtime_payload', 'runtime_payload' => ['subjects' => $subjects]]);
            if (! $assigned->successful) {
                throw ValidationException::withMessages(['subjectsEnrolled' => $assigned->message]);
            }
            $assignedClasses = $assignment->assignClasses($context, ['mode' => 'runtime_payload', 'optional' => true, 'runtime_payload' => ['assignments' => $classes]]);
            if (! $assignedClasses->successful) {
                throw ValidationException::withMessages(['subjectsEnrolled' => $assignedClasses->message]);
            }
            foreach ($fees as $fee) {
                // `additional_fees` has no `description` column, so the payload
                // is narrowed to the columns the table actually stores.
                $enrollment->additionalFees()->create([
                    'fee_name' => $fee['fee_name'],
                    'amount' => $fee['amount'],
                    'is_separate_transaction' => (bool) ($fee['is_separate_transaction'] ?? false),
                ]);
            }
            $form = [
                'subjectsEnrolled' => $enrollment->subjectsEnrolled()->get()->toArray(),
                'discount' => $billing['discount_percentage'], 'downpayment' => $billing['downpayment'], 'additionalFees' => $fees,
            ];
            if (array_key_exists('miscellaneous_fee', $billing)) {
                $form['miscellaneous_fee'] = $billing['miscellaneous_fee'];
            }
            if (! app(EnrollmentService::class)->createStudentTuition($enrollment, $form)) {
                throw ValidationException::withMessages(['subjectsEnrolled' => 'The tuition assessment could not be created.']);
            }
        });

        return self::sendSuccessResponse($model->load([
            'student.course', 'course', 'subjectsEnrolled.subject', 'subjectsEnrolled.class', 'studentTuition', 'additionalFees',
        ]), 'Successfully Create Resource');
    }
}
