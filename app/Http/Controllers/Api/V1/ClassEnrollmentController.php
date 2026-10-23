<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\ClassEnrollmentFormRequest;
use App\Http\Requests\VerifyClassGradesRequest;
use App\Http\Resources\ClassEnrollmentResource;
use App\Models\ClassEnrollment;
use App\Models\Classes;
use App\Services\BulkActivityLogger;
use App\Services\GradeComputationService;
use App\Services\GradeEvaluationService;
use App\Services\GradingSystemService;
use App\Support\BulkChunk;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Class Enrollment API Controller
 *
 * Provides REST API endpoints for managing student enrollments in classes
 * and their grades. All endpoints are protected by Sanctum authentication middleware.
 */
final class ClassEnrollmentController extends Controller
{
    /**
     * Display a listing of class enrollments.
     */
    public function index(Request $request): AnonymousResourceCollection|JsonResponse
    {
        $query = ClassEnrollment::with(['class', 'student'])->orderBy('created_at', 'desc');

        // Filter by class_id if provided
        if ($request->has('class_id')) {
            $query->where('class_id', $request->input('class_id'));
        }

        // Filter by student_id if provided
        if ($request->has('student_id')) {
            $query->where('student_id', $request->input('student_id'));
        }

        // Filter by status if provided
        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        // Filter by has_grades flag
        if ($request->has('has_grades')) {
            $hasGrades = $request->boolean('has_grades');
            if ($hasGrades) {
                $query->whereNotNull('total_average');
            } else {
                $query->whereNull('total_average');
            }
        }

        // Filter by is_finalized flag
        if ($request->has('is_finalized')) {
            $query->where('is_grades_finalized', $request->boolean('is_finalized'));
        }

        // Filter by is_verified flag
        if ($request->has('is_verified')) {
            $query->where('is_grades_verified', $request->boolean('is_verified'));
        }

        // Search by student name or ID
        if ($request->has('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search): void {
                $q->whereLike('student_id', "%{$search}%")
                    ->orWhereHas('student', function ($studentQuery) use ($search): void {
                        $studentQuery->whereLike('first_name', "%{$search}%")
                            ->orWhereLike('last_name', "%{$search}%")
                            ->orWhereLike('student_id', "%{$search}%");
                    });
            });
        }

        // Paginate results
        $perPage = (int) $request->input('per_page', 15);
        $perPage = min(max($perPage, 1), 100);

        $enrollments = $query->paginate($perPage);

        return ClassEnrollmentResource::collection($enrollments);
    }

    /**
     * Store a newly created class enrollment.
     *
     *
     * @throws ValidationException
     */
    public function store(ClassEnrollmentFormRequest $request): JsonResponse
    {
        try {
            $validated = $request->validated();

            // Check if student is already enrolled in this class
            $existingEnrollment = ClassEnrollment::where('class_id', $validated['class_id'])
                ->where('student_id', $validated['student_id'])
                ->first();

            if ($existingEnrollment) {
                return response()->json([
                    'message' => 'Student is already enrolled in this class',
                    'data' => null,
                ], 422);
            }

            $enrollment = app(\App\Services\ClassEnrollmentService::class)->enrollOnce(
                (int) $validated['student_id'],
                (int) $validated['class_id'],
                $validated,
            );
            $enrollment->load(['class', 'student']);

            return response()->json([
                'message' => 'Class enrollment created successfully',
                'data' => new ClassEnrollmentResource($enrollment),
            ], 201);

        } catch (Exception $e) {
            return response()->json([
                'message' => 'Failed to create class enrollment',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Display the specified class enrollment.
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $query = ClassEnrollment::with(['class', 'student', 'verifier']);

        if ($request->boolean('with_trashed', false)) {
            $query->withTrashed();
        }

        $enrollment = $query->find($id);

        if (! $enrollment) {
            return response()->json([
                'message' => 'Class enrollment not found',
                'data' => null,
            ], 404);
        }

        return response()->json([
            'message' => 'Class enrollment retrieved successfully',
            'data' => new ClassEnrollmentResource($enrollment),
        ]);
    }

    /**
     * Update the specified class enrollment.
     *
     *
     * @throws ValidationException
     */
    public function update(ClassEnrollmentFormRequest $request, int $id, GradingSystemService $gradingSystem, GradeEvaluationService $gradeEvaluation): JsonResponse
    {
        try {
            $enrollment = ClassEnrollment::findOrFail($id);
            $validated = $request->validated();

            $policy = $gradingSystem->ensureConfig();
            if ($policy['input_type'] !== 'numeric') {
                return response()->json([
                    'message' => 'Symbolic grading policies require a final symbol workflow.',
                ], 422);
            }

            // Calculate the final grade from the active school's configured components.
            if (isset($validated['prelim_grade']) || isset($validated['midterm_grade']) || isset($validated['finals_grade'])) {
                $prelim = $validated['prelim_grade'] ?? $enrollment->prelim_grade;
                $midterm = $validated['midterm_grade'] ?? $enrollment->midterm_grade;
                $finals = $validated['finals_grade'] ?? $enrollment->finals_grade;

                $evaluation = $gradeEvaluation->calculate([
                    'prelim' => $prelim,
                    'midterm' => $midterm,
                    'final' => $finals,
                ], $policy);
                $validated['total_average'] = $evaluation['numeric_grade'];
                $validated['grading_components'] = $evaluation['components'];
                $validated['grade_symbol'] = $evaluation['symbol'];
                $validated['grade_outcome'] = $evaluation['outcome'];
                $validated['grade_quality_points'] = $evaluation['quality_points'];
                $validated['grading_policy_version_id'] = $policy['policy_version_id'] ?? null;
            }

            $enrollment->update($validated);
            $enrollment->load(['class', 'student']);

            return response()->json([
                'message' => 'Class enrollment updated successfully',
                'data' => new ClassEnrollmentResource($enrollment->fresh()),
            ], 200);

        } catch (Exception $e) {
            return response()->json([
                'message' => 'Failed to update class enrollment',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove the specified class enrollment (soft delete).
     */
    public function destroy(int $id): JsonResponse
    {
        try {
            $enrollment = ClassEnrollment::findOrFail($id);
            $enrollment->delete();

            return response()->json([
                'message' => 'Class enrollment deleted successfully',
            ], 200);

        } catch (Exception $e) {
            return response()->json([
                'message' => 'Failed to delete class enrollment',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Restore a soft-deleted class enrollment.
     */
    public function restore(int $id): JsonResponse
    {
        try {
            $enrollment = ClassEnrollment::onlyTrashed()->findOrFail($id);
            $enrollment->restore();
            $enrollment->load(['class', 'student']);

            return response()->json([
                'message' => 'Class enrollment restored successfully',
                'data' => new ClassEnrollmentResource($enrollment->fresh()),
            ], 200);

        } catch (Exception $e) {
            return response()->json([
                'message' => 'Failed to restore class enrollment',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Force delete a class enrollment permanently.
     */
    public function forceDestroy(int $id): JsonResponse
    {
        try {
            $enrollment = ClassEnrollment::withTrashed()->findOrFail($id);
            $enrollment->forceDelete();

            return response()->json([
                'message' => 'Class enrollment permanently deleted',
            ], 200);

        } catch (Exception $e) {
            return response()->json([
                'message' => 'Failed to permanently delete class enrollment',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update grades for a class enrollment.
     */
    public function updateGrades(ClassEnrollmentFormRequest $request, int $id, GradingSystemService $gradingSystem, GradeEvaluationService $gradeEvaluation): JsonResponse
    {
        try {
            $enrollment = ClassEnrollment::findOrFail($id);
            $validated = $request->only([
                'prelim_grade',
                'midterm_grade',
                'finals_grade',
                'total_average',
            ]);
            $policy = $gradingSystem->getConfig();

            if ($policy['input_type'] !== 'numeric') {
                return response()->json(['message' => 'Symbolic grading policies require a final symbol workflow.'], 422);
            }

            // Calculate total average if all individual grades are provided
            if (isset($validated['prelim_grade']) || isset($validated['midterm_grade']) || isset($validated['finals_grade'])) {
                $prelim = $validated['prelim_grade'] ?? $enrollment->prelim_grade;
                $midterm = $validated['midterm_grade'] ?? $enrollment->midterm_grade;
                $finals = $validated['finals_grade'] ?? $enrollment->finals_grade;

                $evaluation = $gradeEvaluation->calculate(['prelim' => $prelim, 'midterm' => $midterm, 'final' => $finals], $policy);
                $validated['total_average'] = $evaluation['numeric_grade'];
                $validated['grading_components'] = $evaluation['components'];
                $validated['grade_symbol'] = $evaluation['symbol'];
                $validated['grade_outcome'] = $evaluation['outcome'];
                $validated['grade_quality_points'] = $evaluation['quality_points'];
                $validated['grading_policy_version_id'] = $policy['policy_version_id'] ?? null;
            }

            $enrollment->update($validated);
            $enrollment->load(['class', 'student']);

            return response()->json([
                'message' => 'Grades updated successfully',
                'data' => new ClassEnrollmentResource($enrollment->fresh()),
            ], 200);

        } catch (Exception $e) {
            return response()->json([
                'message' => 'Failed to update grades',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Finalize grades for a class enrollment.
     */
    public function finalizeGrades(int $id, GradeComputationService $gradeComputation): JsonResponse
    {
        try {
            $enrollment = ClassEnrollment::findOrFail($id);

            // Check if all required grades are present
            if ($enrollment->prelim_grade === null || $enrollment->midterm_grade === null || $enrollment->finals_grade === null) {
                return response()->json([
                    'message' => 'Cannot finalize grades. All individual grades (prelim, midterm, finals) must be entered first.',
                    'data' => null,
                ], 422);
            }

            // Calculate total average if not already calculated
            if ($enrollment->total_average === null) {
                $enrollment->total_average = $gradeComputation->totalAverage(
                    (float) $enrollment->prelim_grade,
                    (float) $enrollment->midterm_grade,
                    (float) $enrollment->finals_grade,
                );
            }

            $enrollment->is_grades_finalized = true;
            $enrollment->save();
            $enrollment->load(['class', 'student']);

            return response()->json([
                'message' => 'Grades finalized successfully',
                'data' => new ClassEnrollmentResource($enrollment->fresh()),
            ], 200);

        } catch (Exception $e) {
            return response()->json([
                'message' => 'Failed to finalize grades',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Verify grades for a class enrollment.
     */
    public function verifyGrades(VerifyClassGradesRequest $request, int $id): JsonResponse
    {
        try {
            $enrollment = ClassEnrollment::findOrFail($id);

            // Check if grades are finalized
            if (! $enrollment->is_grades_finalized) {
                return response()->json([
                    'message' => 'Cannot verify grades. Grades must be finalized first.',
                    'data' => null,
                ], 422);
            }

            $verifierId = Auth::id();
            $verificationNotes = $request->verificationNotes();

            // Keep the write atomic: any failure below rolls the row back instead of
            // leaving a partially verified enrollment behind a 500 response.
            DB::transaction(function () use ($enrollment, $verifierId, $verificationNotes): void {
                $enrollment->is_grades_verified = true;
                $enrollment->verified_by = $verifierId === null ? null : (int) $verifierId;
                $enrollment->verified_at = now();
                $enrollment->verification_notes = $verificationNotes;

                $enrollment->save();
                $enrollment->load(['class', 'student', 'verifier']);
            });

            return response()->json([
                'message' => 'Grades verified successfully',
                'data' => new ClassEnrollmentResource($enrollment->fresh(['class', 'student', 'verifier'])),
            ], 200);

        } catch (Exception $e) {
            return response()->json([
                'message' => 'Failed to verify grades',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get enrollments by class.
     */
    public function byClass(int $classId, Request $request): AnonymousResourceCollection
    {
        $query = ClassEnrollment::where('class_id', $classId)
            ->with(['class', 'student'])
            ->orderBy('created_at', 'desc');

        // Filter by status if provided
        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        // Paginate results
        $perPage = (int) $request->input('per_page', 15);
        $perPage = min(max($perPage, 1), 100);

        $enrollments = $query->paginate($perPage);

        return ClassEnrollmentResource::collection($enrollments);
    }

    /**
     * Get grade statistics for a class.
     */
    public function gradeStatistics(int $classId): JsonResponse
    {
        try {
            $class = Classes::findOrFail($classId);

            $enrollments = ClassEnrollment::where('class_id', $classId)
                ->whereNotNull('total_average')
                ->get();

            if ($enrollments->isEmpty()) {
                return response()->json([
                    'message' => 'No graded enrollments found for this class',
                    'data' => null,
                ], 404);
            }

            $grades = $enrollments->pluck('total_average');

            $stats = [
                'class_id' => $classId,
                'class_section' => $class->section,
                'total_students' => $enrollments->count(),
                'highest_grade' => $grades->max(),
                'lowest_grade' => $grades->min(),
                'average_grade' => round($grades->avg(), 2),
                'median_grade' => round($grades->sort()->median(), 2),
                'grade_distribution' => [
                    'A (93-100)' => $grades->whereBetween('total_average', [93, 100])->count(),
                    'B (85-92)' => $grades->whereBetween('total_average', [85, 92])->count(),
                    'C (77-84)' => $grades->whereBetween('total_average', [77, 84])->count(),
                    'D (70-76)' => $grades->whereBetween('total_average', [70, 76])->count(),
                    'F (Below 70)' => $grades->where('total_average', '<', 70)->count(),
                ],
                'passing_rate' => round(($grades->where('>=', 75)->count() / $grades->count()) * 100, 2),
            ];

            return response()->json([
                'message' => 'Grade statistics retrieved successfully',
                'data' => $stats,
            ], 200);

        } catch (Exception $e) {
            return response()->json([
                'message' => 'Failed to retrieve grade statistics',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Bulk update grades for multiple enrollments.
     *
     * Every enrollment is read with a single query, written back with an
     * upsert inside a transaction and processed in chunks, so the number of
     * statements stays constant instead of growing with the class size.
     */
    public function bulkUpdateGrades(
        Request $request,
        int $classId,
        GradeComputationService $gradeComputation,
        BulkActivityLogger $activityLogger,
    ): JsonResponse {
        $request->validate([
            'grades' => 'required|array',
            'grades.*.enrollment_id' => 'required|exists:class_enrollments,id',
            'grades.*.prelim_grade' => 'nullable|numeric|min:0|max:100',
            'grades.*.midterm_grade' => 'nullable|numeric|min:0|max:100',
            'grades.*.finals_grade' => 'nullable|numeric|min:0|max:100',
        ]);

        try {
            $gradeRows = $request->input('grades', []);

            $enrollmentIds = array_map(
                static fn (array $gradeData): int => (int) $gradeData['enrollment_id'],
                $gradeRows
            );

            // Read every target enrollment with one query per chunk instead
            // of one lookup (plus one relation load) per student.
            $enrollments = collect();

            foreach (BulkChunk::of($enrollmentIds) as $chunk) {
                $enrollments = $enrollments->merge(
                    ClassEnrollment::query()
                        ->where('class_id', $classId)
                        ->whereIn('id', $chunk)
                        ->get()
                );
            }

            $enrollments = $enrollments->keyBy('id');

            $errors = [];
            $rows = [];
            $changes = [];

            foreach ($gradeRows as $gradeData) {
                $enrollmentId = (int) $gradeData['enrollment_id'];
                $enrollment = $enrollments->get($enrollmentId);

                if (! $enrollment instanceof ClassEnrollment) {
                    $errors[] = [
                        'enrollment_id' => $enrollmentId,
                        'error' => 'Class enrollment not found for the given class.',
                    ];

                    continue;
                }

                $payload = [
                    'prelim_grade' => array_key_exists('prelim_grade', $gradeData)
                        ? $gradeData['prelim_grade']
                        : $enrollment->prelim_grade,
                    'midterm_grade' => array_key_exists('midterm_grade', $gradeData)
                        ? $gradeData['midterm_grade']
                        : $enrollment->midterm_grade,
                    'finals_grade' => array_key_exists('finals_grade', $gradeData)
                        ? $gradeData['finals_grade']
                        : $enrollment->finals_grade,
                ];

                // Calculate total average if all grades are provided
                if (isset($payload['prelim_grade'], $payload['midterm_grade'], $payload['finals_grade'])) {
                    $payload['total_average'] = $gradeComputation->totalAverage(
                        (float) $payload['prelim_grade'],
                        (float) $payload['midterm_grade'],
                        (float) $payload['finals_grade'],
                    );
                } else {
                    $payload['total_average'] = $enrollment->total_average;
                }

                $rows[] = ['id' => $enrollmentId, ...$this->upsertRow($enrollment), ...$payload];
                $changes[$enrollmentId] = [
                    'model' => $enrollment,
                    'old' => $enrollment->getRawOriginal(),
                    'new' => [...$enrollment->getRawOriginal(), ...$payload],
                ];
            }

            if ($rows !== []) {
                DB::transaction(function () use ($rows, $changes, $activityLogger): void {
                    foreach (array_chunk($rows, BulkChunk::size()) as $chunk) {
                        ClassEnrollment::upsert(
                            $chunk,
                            ['id'],
                            ['prelim_grade', 'midterm_grade', 'finals_grade', 'total_average', 'updated_at'],
                        );
                    }

                    // A bulk write never fires Eloquent events, so the audit
                    // trail is written back explicitly.
                    foreach ($changes as $change) {
                        $activityLogger->updated($change['model'], $change['old'], $change['new']);
                    }
                });
            }

            $updated = [];

            if ($enrollments->isNotEmpty()) {
                $refreshed = collect();

                foreach (BulkChunk::of($enrollments->keys()->all()) as $chunk) {
                    $refreshed = $refreshed->merge(
                        ClassEnrollment::query()
                            ->with('student')
                            ->whereIn('id', $chunk)
                            ->get()
                    );
                }

                $refreshed = $refreshed->keyBy('id');

                foreach ($enrollments->keys() as $enrollmentId) {
                    $enrollment = $refreshed->get($enrollmentId);

                    if ($enrollment instanceof ClassEnrollment) {
                        $updated[] = new ClassEnrollmentResource($enrollment);
                    }
                }
            }

            return response()->json([
                'message' => 'Bulk grade update completed',
                'data' => [
                    'updated' => $updated,
                    'errors' => $errors,
                    'total_updated' => count($updated),
                    'total_errors' => count($errors),
                ],
            ], 200);

        } catch (Exception $e) {
            return response()->json([
                'message' => 'Failed to update grades',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * The stored columns of an enrollment, ready to be written back with an
     * upsert.
     *
     * The upsert statement carries every column of the row, so the columns
     * that are not part of the grade payload keep their current value and the
     * not-null columns keep the database satisfied.
     *
     * @return array<string, mixed>
     */
    private function upsertRow(ClassEnrollment $enrollment): array
    {
        $attributes = $enrollment->getRawOriginal();

        unset($attributes['id'], $attributes['created_at'], $attributes['updated_at']);

        return $attributes;
    }
}
