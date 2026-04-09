<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Classes;
use App\Models\Course;
use App\Models\StudentEnrollment;
use App\Models\Subject;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class StudentGlobalSearchController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $query = $request->input('q') ?? $request->input('query');
        $type = $request->input('type', 'all');

        $emptyResults = [
            'subjects' => [],
            'classes' => [],
            'courses' => [],
            'enrollments' => [],
        ];

        if (! is_string($query)) {
            return response()->json($emptyResults);
        }

        $query = mb_trim($query);

        if ($query === '' || mb_strlen($query) < 2) {
            return response()->json($emptyResults);
        }

        $results = [];
        $limit = $type === 'all' ? 5 : 20;

        if ($type === 'all' || $type === 'subjects') {
            $results['subjects'] = $this->searchSubjects($query, $limit);
        }

        if ($type === 'all' || $type === 'classes') {
            $results['classes'] = $this->searchClasses($query, $limit);
        }

        if ($type === 'all' || $type === 'courses') {
            $results['courses'] = $this->searchCourses($query, $limit);
        }

        if ($type === 'all' || $type === 'enrollments') {
            $results['enrollments'] = $this->searchEnrollments($query, $limit, $request);
        }

        return response()->json($results);
    }

    private function searchSubjects(string $query, int $limit): array
    {
        return Subject::search($query)
            ->take($limit)
            ->get()
            ->map(fn (Subject $subject): array => [
                'id' => $subject->id,
                'code' => $subject->code,
                'title' => $subject->title,
                'units' => $subject->units,
            ])
            ->values()
            ->toArray();
    }

    private function searchClasses(string $query, int $limit): array
    {
        return Classes::search($query)
            ->take($limit)
            ->get()
            ->map(fn (Classes $class): array => [
                'id' => $class->id,
                'record_title' => $class->record_title,
                'subject_code' => $class->subject_code ?? 'N/A',
                'section' => $class->section ?? 'N/A',
            ])
            ->values()
            ->toArray();
    }

    private function searchCourses(string $query, int $limit): array
    {
        return Course::search($query)
            ->take($limit)
            ->get()
            ->map(fn (Course $course): array => [
                'id' => $course->id,
                'code' => $course->code,
                'title' => $course->title,
                'department' => $course->department,
            ])
            ->values()
            ->toArray();
    }

    private function searchEnrollments(string $query, int $limit, Request $request): array
    {
        $user = $request->user();
        if (! $user) {
            return [];
        }

        $studentId = $user->student?->id;
        if (! $studentId) {
            return [];
        }

        return StudentEnrollment::search($query)
            ->where('student_id', $studentId)
            ->query(fn ($builder) => $builder->with(['student', 'course']))
            ->take($limit)
            ->get()
            ->map(fn (StudentEnrollment $enrollment): array => [
                'id' => $enrollment->id,
                'student_name' => $enrollment->student?->full_name ?? 'Unknown',
                'course_code' => $enrollment->course?->code ?? 'N/A',
                'year_level' => (string) ($enrollment->academic_year ?? 'N/A'),
                'status' => $enrollment->status,
                'school_year' => $enrollment->school_year,
                'semester' => (string) $enrollment->semester,
            ])
            ->values()
            ->toArray();
    }
}
