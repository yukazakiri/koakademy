<?php

declare(strict_types=1);

namespace App\Filament\Resources\Faculties\Api\Handlers;

use App\Filament\Resources\Faculties\FacultyResource;
use App\Http\Controllers\Api\V1\ApiPagination;
use App\Models\Classes;
use App\Models\Faculty;
use App\Models\Student;
use Illuminate\Http\Request;
use Override;
use Rupadana\ApiService\Http\Handlers;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

final class FacultyStudentsHandler extends Handlers
{
    #[Override]
    public static ?string $uri = '/{faculty_id}/students';

    #[Override]
    public static ?string $resource = FacultyResource::class;

    protected static string $permission = 'View:Faculty';

    public static function getRouteMiddleware(): array
    {
        return [
            'auth:sanctum',
        ];
    }

    /**
     * Get All Students Handled by a Faculty Member
     *
     * This endpoint returns all students enrolled in classes taught by the specified faculty.
     * Supports filtering by course, section, academic year, semester, and student search.
     */
    public function handler(Request $request): \Illuminate\Http\JsonResponse|array
    {
        $facultyIdentifier = $request->route('faculty_id');

        // Find faculty by UUID, faculty_id_number, or email
        $faculty = $this->findFaculty($facultyIdentifier);

        if (! $faculty instanceof Faculty) {
            return self::sendNotFoundResponse('Faculty not found');
        }

        $user = $request->user();
        if (! $user) {
            return response()->json([
                'error' => true,
                'message' => 'Unauthenticated.',
                'code' => 'UNAUTHENTICATED',
            ], 401);
        }

        // Context-aware authorization check:
        // A faculty member can only fetch their own students.
        // Administrative users with standard View:Faculty permission can fetch anyone's students.
        $isAuthorized = false;
        if ($user->role && $user->role->isFaculty()) {
            if ($user->email === $faculty->email || $user->faculty_id_number === $faculty->faculty_id_number) {
                $isAuthorized = true;
            }
        } elseif ($user->can('View:Faculty')) {
            $isAuthorized = true;
        }

        if (! $isAuthorized) {
            return response()->json([
                'error' => true,
                'message' => 'User does not have the right permissions.',
                'code' => 'FORBIDDEN',
            ], 403);
        }

        // Get all class IDs for this faculty
        $classIds = Classes::query()
            ->where('faculty_id', $faculty->id)
            ->pluck('id');

        if ($classIds->isEmpty()) {
            return [
                'faculty' => $this->formatFacultyInfo($faculty),
                'students' => [],
                'meta' => [
                    'total_students' => 0,
                    'total_classes' => 0,
                    'filters_applied' => $this->getAppliedFilters($request),
                ],
            ];
        }

        // Build query for students through class enrollments
        $studentsQuery = QueryBuilder::for(Student::class)
            ->whereHas('classEnrollments', function ($query) use ($classIds, $request): void {
                $query->whereIn('class_id', $classIds);

                // Filter by specific class if provided
                if ($request->filled('filter.class_id')) {
                    $query->where('class_id', $request->input('filter.class_id'));
                }
            })
            ->with([
                'course:id,code,title',
                'classEnrollments' => function ($query) use ($classIds): void {
                    $query->whereIn('class_id', $classIds)
                        ->with([
                            'class:id,subject_code,section,academic_year,semester,school_year',
                            'class.Subject:id,code,title',
                        ]);
                },
            ])
            ->allowedFilters([
                AllowedFilter::exact('course_id'),
                AllowedFilter::exact('year_level'),
                AllowedFilter::exact('gender'),
                AllowedFilter::partial('student_id'),
                AllowedFilter::callback('search', function ($query, $value): void {
                    $query->where(function ($q) use ($value): void {
                        $q->whereLike('first_name', "%{$value}%")
                            ->orWhereLike('last_name', "%{$value}%")
                            ->orWhereLike('student_id', "%{$value}%")
                            ->orWhereLike('email', "%{$value}%");
                    });
                }),
                AllowedFilter::callback('class_id', function ($query, $value) use ($classIds): void {
                    if (in_array($value, $classIds->toArray())) {
                        $query->whereHas('classEnrollments', function ($q) use ($value): void {
                            $q->where('class_id', $value);
                        });
                    }
                }),
            ])
            ->allowedSorts([
                'student_id',
                'first_name',
                'last_name',
                'year_level',
                'created_at',
            ])
            ->defaultSort('last_name');

        // Paginate results
        $perPage = ApiPagination::perPage($request->input('per_page'));
        $students = $studentsQuery->paginate($perPage);

        // Get class summary for this faculty
        $classSummary = $this->getClassSummary($classIds);

        return [
            'faculty' => $this->formatFacultyInfo($faculty),
            'students' => $this->formatStudents($students->items(), $classIds),
            'classes_summary' => $classSummary,
            'meta' => [
                'current_page' => $students->currentPage(),
                'per_page' => $students->perPage(),
                'total_students' => $students->total(),
                'total_pages' => $students->lastPage(),
                'total_classes' => $classIds->count(),
                'filters_applied' => $this->getAppliedFilters($request),
            ],
            'links' => [
                'first' => $students->url(1),
                'last' => $students->url($students->lastPage()),
                'prev' => $students->previousPageUrl(),
                'next' => $students->nextPageUrl(),
            ],
        ];
    }

    /**
     * Find faculty by various identifiers
     */
    private function findFaculty(string $identifier): ?Faculty
    {
        // UUID pattern
        if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $identifier)) {
            return Faculty::query()->find($identifier);
        }

        // Email pattern
        if (str_contains($identifier, '@')) {
            return Faculty::query()->where('email', $identifier)->first();
        }

        // Faculty ID number
        return Faculty::query()->where('faculty_id_number', $identifier)->first();
    }

    /**
     * Format faculty information for response
     *
     * @return array<string, mixed>
     */
    private function formatFacultyInfo(Faculty $faculty): array
    {
        return [
            'id' => $faculty->id,
            'faculty_id_number' => $faculty->faculty_id_number,
            'full_name' => $faculty->full_name,
            'first_name' => $faculty->first_name,
            'last_name' => $faculty->last_name,
            'email' => $faculty->email,
            'department' => $faculty->department,
        ];
    }

    /**
     * Format students for response
     *
     * @param  \Illuminate\Support\Collection  $classIds
     * @return array<int, array<string, mixed>>
     */
    private function formatStudents(array $students, $classIds): array
    {
        return collect($students)->map(function ($student) use ($classIds): array {
            $enrollments = $student->classEnrollments
                ->whereIn('class_id', $classIds)
                ->map(fn ($enrollment): array => [
                    'class_id' => $enrollment->class_id,
                    'subject_code' => $enrollment->class?->subject_code,
                    'subject_title' => $enrollment->class?->Subject?->title,
                    'section' => $enrollment->class?->section,
                    'grades' => [
                        'prelim' => $enrollment->prelim_grade,
                        'midterm' => $enrollment->midterm_grade,
                        'finals' => $enrollment->finals_grade,
                        'total_average' => $enrollment->total_average,
                        'is_finalized' => $enrollment->is_grades_finalized,
                    ],
                ]);

            return [
                'id' => $student->id,
                'student_id' => $student->student_id,
                'full_name' => $student->full_name,
                'first_name' => $student->first_name,
                'middle_name' => $student->middle_name,
                'last_name' => $student->last_name,
                'email' => $student->email,
                'year_level' => $student->year_level,
                'course' => $student->course ? [
                    'id' => $student->course->id,
                    'code' => $student->course->code,
                    'title' => $student->course->title,
                ] : null,
                'enrolled_classes' => $enrollments->values()->toArray(),
                'total_enrolled_classes' => $enrollments->count(),
            ];
        })->toArray();
    }

    /**
     * Get summary of classes handled by faculty
     *
     * @param  \Illuminate\Support\Collection  $classIds
     * @return array<int, array<string, mixed>>
     */
    private function getClassSummary($classIds): array
    {
        $classes = Classes::query()
            ->whereIn('id', $classIds)
            ->withCount('class_enrollments')
            ->with(['Subject:id,code,title'])
            ->get();

        return $classes->map(fn ($class): array => [
            'id' => $class->id,
            'subject_code' => $class->subject_code,
            'subject_title' => $class->Subject?->title,
            'section' => $class->section,
            'academic_year' => $class->academic_year,
            'semester' => $class->semester,
            'school_year' => $class->school_year,
            'enrolled_count' => $class->class_enrollments_count,
            'maximum_slots' => $class->maximum_slots,
        ])->toArray();
    }

    /**
     * Get applied filters from request
     *
     * @return array<string, mixed>
     */
    private function getAppliedFilters(Request $request): array
    {
        $filters = [];

        foreach (['course_id', 'year_level', 'gender', 'student_id', 'search', 'class_id'] as $filter) {
            if ($request->filled("filter.{$filter}")) {
                $filters[$filter] = $request->input("filter.{$filter}");
            }
        }

        return $filters;
    }
}
