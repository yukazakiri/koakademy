<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\ClassEnrollmentFormRequest;
use App\Http\Resources\ClassEnrollmentResource;
use App\Models\ClassEnrollment;
use App\Models\Classes;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Auth;
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
                $q->where('student_id', 'ilike', "%{$search}%")
                    ->orWhereHas('student', function ($studentQuery) use ($search): void {
                        $studentQuery->where('first_name', 'ilike', "%{$search}%")
                            ->orWhere('last_name', 'ilike', "%{$search}%")
                            ->orWhere('student_id', 'ilike', "%{$search}%");
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

            $enrollment = ClassEnrollment::create($validated);
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
    public function update(ClassEnrollmentFormRequest $request, int $id): JsonResponse
    {
        try {
            $enrollment = ClassEnrollment::findOrFail($id);
            $validated = $request->validated();

            // Calculate total average if grades are provided
            if (isset($validated['prelim_grade']) || isset($validated['midterm_grade']) || isset($validated['finals_grade'])) {
                $prelim = $validated['prelim_grade'] ?? $enrollment->prelim_grade;
                $midterm = $validated['midterm_grade'] ?? $enrollment->midterm_grade;
                $finals = $validated['finals_grade'] ?? $enrollment->finals_grade;

                // Calculate weighted average: Prelim (30%), Midterm (30%), Finals (40%)
                if ($prelim !== null && $midterm !== null && $finals !== null) {
                    $validated['total_average'] = ($prelim * 0.3) + ($midterm * 0.3) + ($finals * 0.4);
                }
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
    public function updateGrades(ClassEnrollmentFormRequest $request, int $id): JsonResponse
    {
        try {
            $enrollment = ClassEnrollment::findOrFail($id);
            $validated = $request->only([
                'prelim_grade',
                'midterm_grade',
                'finals_grade',
                'total_average',
            ]);

            // Calculate total average if all individual grades are provided
            if (isset($validated['prelim_grade']) || isset($validated['midterm_grade']) || isset($validated['finals_grade'])) {
                $prelim = $validated['prelim_grade'] ?? $enrollment->prelim_grade;
                $midterm = $validated['midterm_grade'] ?? $enrollment->midterm_grade;
                $finals = $validated['finals_grade'] ?? $enrollment->finals_grade;

                // Only calculate if all three grades are available
                if ($prelim !== null && $midterm !== null && $finals !== null) {
                    // Calculate weighted average: Prelim (30%), Midterm (30%), Finals (40%)
                    $validated['total_average'] = round(($prelim * 0.3) + ($midterm * 0.3) + ($finals * 0.4), 2);
                }
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
    public function finalizeGrades(int $id): JsonResponse
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
                $totalAverage = ($enrollment->prelim_grade * 0.3) + ($enrollment->midterm_grade * 0.3) + ($enrollment->finals_grade * 0.4);
                $enrollment->total_average = round($totalAverage, 2);
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
    public function verifyGrades(Request $request, int $id): JsonResponse
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

            $enrollment->is_grades_verified = true;
            $enrollment->verified_by = Auth::id();
            $enrollment->verified_at = now();
            $enrollment->verification_notes = $request->input('verification_notes');

            $enrollment->save();
            $enrollment->load(['class', 'student', 'verifier']);

            return response()->json([
                'message' => 'Grades verified successfully',
                'data' => new ClassEnrollmentResource($enrollment->fresh()),
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
     */
    public function bulkUpdateGrades(Request $request, int $classId): JsonResponse
    {
        $request->validate([
            'grades' => 'required|array',
            'grades.*.enrollment_id' => 'required|exists:class_enrollments,id',
            'grades.*.prelim_grade' => 'nullable|numeric|min:0|max:100',
            'grades.*.midterm_grade' => 'nullable|numeric|min:0|max:100',
            'grades.*.finals_grade' => 'nullable|numeric|min:0|max:100',
        ]);

        try {
            $updated = [];
            $errors = [];

            foreach ($request->input('grades') as $gradeData) {
                try {
                    $enrollment = ClassEnrollment::where('id', $gradeData['enrollment_id'])
                        ->where('class_id', $classId)
                        ->firstOrFail();

                    // Calculate total average if all grades are provided
                    if (isset($gradeData['prelim_grade']) && isset($gradeData['midterm_grade']) && isset($gradeData['finals_grade'])) {
                        $totalAverage = ($gradeData['prelim_grade'] * 0.3) + ($gradeData['midterm_grade'] * 0.3) + ($gradeData['finals_grade'] * 0.4);
                        $gradeData['total_average'] = round($totalAverage, 2);
                    }

                    $enrollment->update(collect($gradeData)->only([
                        'prelim_grade',
                        'midterm_grade',
                        'finals_grade',
                        'total_average',
                    ])->toArray());

                    $enrollment->load(['student']);
                    $updated[] = new ClassEnrollmentResource($enrollment);

                } catch (Exception $e) {
                    $errors[] = [
                        'enrollment_id' => $gradeData['enrollment_id'],
                        'error' => $e->getMessage(),
                    ];
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
}
