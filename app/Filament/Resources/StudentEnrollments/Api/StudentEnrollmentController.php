<?php

declare(strict_types=1);

namespace App\Filament\Resources\StudentEnrollments\Api;

use App\Data\Enrollment\EnrollmentSubmissionData;
use App\Enrollment\EnrollmentWorkflowCoordinator;
use App\Filament\Resources\StudentEnrollments\Api\Transformers\StudentEnrollmentTransformer;
use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\School;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\User;
use App\Services\GeneralSettingsService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final class StudentEnrollmentController extends Controller
{
    public function __construct(
        private readonly GeneralSettingsService $settingsService,
        private readonly EnrollmentWorkflowCoordinator $workflowCoordinator,
    ) {}

    public function transition(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'transition_key' => ['nullable', 'string', 'max:80'],
            'idempotency_key' => ['required', 'string', 'max:96'],
            'payload' => ['nullable', 'array'],
        ]);
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);
        $enrollment = StudentEnrollment::query()->findOrFail($id);
        $this->authorize('update', $enrollment);

        return response()->json($this->workflowCoordinator->transition(
            $enrollment,
            $actor,
            $validated['transition_key'] ?? null,
            $validated['payload'] ?? [],
            $validated['idempotency_key'],
        ));
    }

    public function reopen(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'target_step_key' => ['nullable', 'string', 'max:80'],
            'reason' => ['required', 'string', 'max:2000'],
            'idempotency_key' => ['required', 'string', 'max:96'],
        ]);
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);

        return response()->json($this->workflowCoordinator->reopen(
            StudentEnrollment::query()->findOrFail($id),
            $actor,
            $validated['target_step_key'] ?? null,
            $validated['reason'],
            $validated['idempotency_key'],
        ));
    }

    /**
     * Display a listing of student enrollments
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = StudentEnrollment::query()
            ->with([
                'student.course',
                'course',
                'subjectsEnrolled.subject',
                'subjectsEnrolled.class.faculty',
                'subjectsEnrolled.class.schedule.room',
                'studentTuition',
                'additionalFees',
                'resources',
                'policySnapshot',
                'workflowEvents' => fn ($query) => $query->latest(),
            ]);

        // Filter by current academic period if requested
        if ($request->boolean('current_period', false)) {
            $query->currentAcademicPeriod();
        }

        // Filter by school year
        if ($request->filled('school_year')) {
            $query->where('school_year', $request->input('school_year'));
        }

        // Filter by semester
        if ($request->filled('semester')) {
            $query->where('semester', (int) $request->input('semester'));
        }

        // Filter by student
        if ($request->filled('student_id')) {
            $query->where('student_id', $request->input('student_id'));
        }

        // Filter by course
        if ($request->filled('course_id')) {
            $query->where('course_id', (int) $request->input('course_id'));
        }

        // Filter by status
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        // Search functionality
        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search): void {
                $q->where('id', 'like', "%{$search}%")
                    ->orWhere('student_id', 'like', "%{$search}%")
                    ->orWhereHas('student', function ($studentQuery) use ($search): void {
                        $studentQuery->where('first_name', 'ilike', "%{$search}%")
                            ->orWhere('last_name', 'ilike', "%{$search}%");
                    });
            });
        }

        // Include trashed records if requested
        if ($request->boolean('with_trashed', false)) {
            $query->withTrashed();
        }

        // Sorting
        $sortBy = $request->input('sort_by', 'created_at');
        $sortDirection = $request->input('sort_direction', 'desc');
        $query->orderBy($sortBy, $sortDirection);

        // Pagination
        $perPage = min((int) $request->input('per_page', 15), 100);

        return StudentEnrollmentTransformer::collection(
            $query->paginate($perPage)
        );
    }

    /**
     * Store a newly created enrollment
     *
     *
     * @throws ValidationException
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'student_id' => 'required|exists:students,id',
            'course_id' => 'required|exists:courses,id',
            'semester' => 'required|integer|in:1,2',
            'academic_year' => 'required|integer|min:1|max:4',
            'remarks' => 'nullable|string',
            'subjects' => 'nullable|array',
            'subjects.*.subject_id' => 'required|exists:subject,id',
            'subjects.*.class_id' => 'required|exists:classes,id',
            'subjects.*.is_modular' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $validated = $validator->validated();
            $actor = $request->user();
            $subjects = $validated['subjects'] ?? [];
            $course = Course::query()->findOrFail((int) $validated['course_id']);
            $schoolId = $course->school_id
                ?? Student::query()->whereKey((int) $validated['student_id'])->value('school_id')
                ?? School::query()->where('is_active', true)->value('id')
                ?? School::query()->value('id');
            $enrollment = $this->workflowCoordinator->submit(
                new EnrollmentSubmissionData(
                    enrollmentAttributes: [
                        'school_id' => $schoolId,
                        'student_id' => $validated['student_id'],
                        'course_id' => $validated['course_id'],
                        'semester' => $validated['semester'],
                        'academic_year' => $validated['academic_year'],
                        'school_year' => $this->settingsService->getCurrentSchoolYearString(),
                        'remarks' => $validated['remarks'] ?? null,
                    ],
                    subjects: $subjects,
                    classAssignments: collect($subjects)->map(fn (array $subject): array => [
                        'subject_id' => (int) $subject['subject_id'],
                        'class_id' => (int) $subject['class_id'],
                    ])->all(),
                    channel: 'api',
                    idempotencyKey: (string) ($request->header('Idempotency-Key') ?: \Illuminate\Support\Str::uuid()),
                    actor: $actor instanceof User ? $actor : null,
                ),
                function (StudentEnrollment $legacyEnrollment) use ($subjects): void {
                    foreach ($subjects as $subject) {
                        $legacyEnrollment->subjectsEnrolled()->create($subject);
                    }
                },
            );

            return response()->json([
                'message' => 'Enrollment created successfully',
                'data' => new StudentEnrollmentTransformer(
                    $enrollment->load([
                        'student.course',
                        'course',
                        'subjectsEnrolled.subject',
                        'subjectsEnrolled.class',
                        'studentTuition',
                    ])
                ),
            ], 201);

        } catch (ValidationException $exception) {
            return response()->json([
                'message' => 'Enrollment policy validation failed',
                'errors' => $exception->errors(),
            ], 422);
        } catch (Exception $e) {
            return response()->json([
                'message' => 'Failed to create enrollment',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Display the specified enrollment
     */
    public function show(Request $request, int $id): StudentEnrollmentTransformer
    {
        $query = StudentEnrollment::query()
            ->with([
                'student.course',
                'course',
                'subjectsEnrolled.subject',
                'subjectsEnrolled.class.faculty',
                'subjectsEnrolled.class.schedule.room',
                'studentTuition',
                'additionalFees',
                'resources',
                'policySnapshot',
                'workflowEvents' => fn ($query) => $query->latest(),
            ]);

        // Include trashed if requested
        if ($request->boolean('with_trashed', false)) {
            $query->withTrashed();
        }

        $enrollment = $query->findOrFail($id);

        // Load transactions if requested
        if ($request->boolean('with_transactions', false)) {
            $enrollment->load('enrollmentTransactions');
        }

        return new StudentEnrollmentTransformer($enrollment);
    }

    /**
     * Update the specified enrollment
     *
     *
     * @throws ValidationException
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $enrollment = StudentEnrollment::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'student_id' => 'sometimes|exists:students,id',
            'course_id' => 'sometimes|exists:courses,id',
            'semester' => 'sometimes|integer|in:1,2',
            'academic_year' => 'sometimes|integer|min:1|max:4',
            'status' => 'sometimes|string',
            'remarks' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        if ($enrollment->workflow_runtime === StudentEnrollment::WorkflowRuntimePolicyV1 && $request->has('status')) {
            return response()->json([
                'message' => 'Policy enrollment statuses can only change through a workflow transition.',
            ], 422);
        }

        DB::beginTransaction();

        try {
            $validated = $validator->validated();
            $status = $validated['status'] ?? null;
            unset($validated['status']);
            $enrollment->update($validated);
            if (is_string($status)) {
                $this->workflowCoordinator->updateLegacyReportingStatus($enrollment, $status);
            }

            DB::commit();

            return response()->json([
                'message' => 'Enrollment updated successfully',
                'data' => new StudentEnrollmentTransformer(
                    $enrollment->fresh([
                        'student.course',
                        'course',
                        'subjectsEnrolled.subject',
                        'studentTuition',
                    ])
                ),
            ], 200);

        } catch (Exception $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Failed to update enrollment',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove the specified enrollment (soft delete)
     */
    public function destroy(int $id): JsonResponse
    {
        try {
            $enrollment = StudentEnrollment::findOrFail($id);
            $enrollment->delete();

            return response()->json([
                'message' => 'Enrollment deleted successfully',
            ], 200);

        } catch (Exception $e) {
            return response()->json([
                'message' => 'Failed to delete enrollment',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Restore a soft-deleted enrollment
     */
    public function restore(int $id): JsonResponse
    {
        try {
            $enrollment = StudentEnrollment::onlyTrashed()->findOrFail($id);
            $enrollment->restore();

            return response()->json([
                'message' => 'Enrollment restored successfully',
                'data' => new StudentEnrollmentTransformer(
                    $enrollment->fresh([
                        'student.course',
                        'course',
                        'subjectsEnrolled.subject',
                        'studentTuition',
                    ])
                ),
            ], 200);

        } catch (Exception $e) {
            return response()->json([
                'message' => 'Failed to restore enrollment',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Force delete an enrollment permanently
     */
    public function forceDestroy(int $id): JsonResponse
    {
        try {
            $enrollment = StudentEnrollment::withTrashed()->findOrFail($id);
            $enrollment->forceDelete();

            return response()->json([
                'message' => 'Enrollment permanently deleted',
            ], 200);

        } catch (Exception $e) {
            return response()->json([
                'message' => 'Failed to permanently delete enrollment',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get enrollment statistics
     */
    public function statistics(Request $request): JsonResponse
    {
        $query = StudentEnrollment::query();

        // Filter by current academic period if requested
        if ($request->boolean('current_period', true)) {
            $query->currentAcademicPeriod();
        }

        $stats = [
            'total_enrollments' => $query->count(),
            'by_status' => $query->select('status', DB::raw('count(*) as count'))
                ->groupBy('status')
                ->pluck('count', 'status'),
            'by_semester' => $query->select('semester', DB::raw('count(*) as count'))
                ->groupBy('semester')
                ->pluck('count', 'semester'),
            'by_academic_year' => $query->select('academic_year', DB::raw('count(*) as count'))
                ->groupBy('academic_year')
                ->pluck('count', 'academic_year'),
            'current_school_year' => $this->settingsService->getCurrentSchoolYearString(),
            'current_semester' => $this->settingsService->getCurrentSemester(),
        ];

        return response()->json($stats);
    }

    /**
     * Get class schedule for an enrollment
     */
    public function schedule(int $id): JsonResponse
    {
        $enrollment = StudentEnrollment::with([
            'subjectsEnrolled.subject',
            'subjectsEnrolled.class.schedule.room',
            'subjectsEnrolled.class.faculty',
        ])->findOrFail($id);

        $schedule = [];

        foreach ($enrollment->subjectsEnrolled as $subjectEnrollment) {
            if ($subjectEnrollment->class && $subjectEnrollment->class->schedule) {
                foreach ($subjectEnrollment->class->schedule as $sched) {
                    $schedule[] = [
                        'subject' => [
                            'code' => $subjectEnrollment->subject->code,
                            'title' => $subjectEnrollment->subject->title,
                        ],
                        'class' => [
                            'section' => $subjectEnrollment->class->section,
                            'faculty' => $subjectEnrollment->class->faculty?->full_name,
                        ],
                        'day_of_week' => $sched->day_of_week,
                        'start_time' => $sched->start_time,
                        'end_time' => $sched->end_time,
                        'room' => $sched->room?->name,
                    ];
                }
            }
        }

        return response()->json([
            'enrollment_id' => $enrollment->id,
            'student' => $enrollment->student->full_name,
            'schedule' => $schedule,
        ]);
    }

    /**
     * Get assessment/tuition details for an enrollment
     */
    public function assessment(int $id): JsonResponse
    {
        $enrollment = StudentEnrollment::with([
            'studentTuition',
            'additionalFees',
            'subjectsEnrolled.subject',
        ])->findOrFail($id);

        $assessment = [
            'enrollment_id' => $enrollment->id,
            'student' => $enrollment->student->full_name,
            'tuition' => $enrollment->studentTuition ? [
                'discount' => $enrollment->studentTuition->discount,
                'total_lectures' => $enrollment->studentTuition->total_lectures,
                'total_laboratory' => $enrollment->studentTuition->total_laboratory,
                'total_tuition' => $enrollment->studentTuition->total_tuition,
                'total_miscelaneous_fees' => $enrollment->studentTuition->total_miscelaneous_fees,
                'overall_tuition' => $enrollment->studentTuition->overall_tuition,
                'downpayment' => $enrollment->studentTuition->downpayment,
                'total_balance' => $enrollment->studentTuition->total_balance,
            ] : null,
            'additional_fees' => $enrollment->additionalFees->map(fn ($fee): array => [
                'fee_name' => $fee->fee_name,
                'amount' => $fee->amount,
                'description' => $fee->description,
            ]),
            'additional_fees_total' => $enrollment->additionalFees->sum('amount'),
            'subjects_enrolled' => $enrollment->subjectsEnrolled->map(fn ($subject): array => [
                'code' => $subject->subject->code,
                'title' => $subject->subject->title,
                'lecture_units' => $subject->enrolled_lecture_units,
                'laboratory_units' => $subject->enrolled_laboratory_units,
                'lecture_fee' => $subject->lecture_fee,
                'laboratory_fee' => $subject->laboratory_fee,
            ]),
            'assessment_url' => $enrollment->assessment_url,
        ];

        return response()->json($assessment);
    }
}
