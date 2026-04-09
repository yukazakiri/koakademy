<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\ClassEnrollment;
use App\Models\Classes;
use App\Models\Faculty;
use App\Models\Student;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

final class FacultyGlobalSearchController extends Controller
{
    public function classes(Request $request): JsonResponse
    {
        $faculty = $this->resolveFacultyOrAbort();

        $query = $this->normalizeQuery($request);

        if ($query === null) {
            return response()->json(['classes' => []]);
        }

        $classes = Classes::query()
            ->where('faculty_id', $faculty->id)
            ->currentAcademicPeriod()
            ->with([
                'Subject',
                'SubjectByCodeFallback',
                'ShsSubject',
            ])
            ->where(function ($builder) use ($query): void {
                $builder->where('subject_code', 'like', "%{$query}%")
                    ->orWhere('section', 'like', "%{$query}%")
                    ->orWhere('school_year', 'like', "%{$query}%")
                    ->orWhereRaw("CONCAT(subject_code, ' - ', section) LIKE ?", ["%{$query}%"])
                    ->orWhereHas('Subject', function ($subjectQuery) use ($query): void {
                        $subjectQuery->where('code', 'like', "%{$query}%")
                            ->orWhere('title', 'like', "%{$query}%");
                    })
                    ->orWhereHas('SubjectByCodeFallback', function ($subjectQuery) use ($query): void {
                        $subjectQuery->where('code', 'like', "%{$query}%")
                            ->orWhere('title', 'like', "%{$query}%");
                    })
                    ->orWhereHas('ShsSubject', function ($subjectQuery) use ($query): void {
                        $subjectQuery->where('code', 'like', "%{$query}%")
                            ->orWhere('title', 'like', "%{$query}%");
                    });
            })
            ->orderByDesc('school_year')
            ->orderBy('semester')
            ->orderBy('section')
            ->limit(12)
            ->get();

        $results = $classes->map(function (Classes $class): array {
            $subject = $class->subjects->first();

            if (! $subject) {
                $subject = $class->isShs()
                    ? $class->ShsSubject
                    : ($class->Subject ?: $class->SubjectByCodeFallback);
            }

            return [
                'id' => $class->id,
                'record_title' => $class->record_title,
                'subject_code' => $subject?->code ?? $class->subject_code ?? 'N/A',
                'subject_title' => $subject?->title ?? 'N/A',
                'section' => $class->section ?? 'N/A',
                'school_year' => $class->school_year ?? 'N/A',
                'semester' => $class->semester ?? 'N/A',
                'classification' => $class->classification ?? 'college',
            ];
        })->values();

        return response()->json(['classes' => $results]);
    }

    public function students(Request $request): JsonResponse
    {
        $faculty = $this->resolveFacultyOrAbort();

        $query = $this->normalizeQuery($request);

        if ($query === null) {
            return response()->json(['students' => []]);
        }

        $students = Student::query()
            ->whereHas('classEnrollments.class', function ($builder) use ($faculty): void {
                $builder->where('faculty_id', $faculty->id)
                    ->currentAcademicPeriod();
            })
            ->where(function ($builder) use ($query): void {
                $builder->where('first_name', 'like', "%{$query}%")
                    ->orWhere('last_name', 'like', "%{$query}%")
                    ->orWhere('student_id', 'like', "%{$query}%")
                    ->orWhereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", ["%{$query}%"])
                    ->orWhereRaw("CONCAT(last_name, ', ', first_name) LIKE ?", ["%{$query}%"]);
            })
            ->with(['Course'])
            ->withCount([
                'classEnrollments as class_count' => function ($enrollmentQuery) use ($faculty): void {
                    $enrollmentQuery->whereHas('class', function ($classQuery) use ($faculty): void {
                        $classQuery->where('faculty_id', $faculty->id)
                            ->currentAcademicPeriod();
                    });
                },
            ])
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->limit(12)
            ->get();

        $studentIds = $students->pluck('id');

        $enrollments = ClassEnrollment::query()
            ->whereIn('student_id', $studentIds)
            ->whereHas('class', function ($classQuery) use ($faculty): void {
                $classQuery->where('faculty_id', $faculty->id)
                    ->currentAcademicPeriod();
            })
            ->with([
                'class:id,section,subject_code',
            ])
            ->get()
            ->groupBy('student_id');

        $results = $students->map(function (Student $student) use ($enrollments): array {
            $labels = $enrollments
                ->get($student->id, collect())
                ->map(function (ClassEnrollment $enrollment): string {
                    $subjectCode = $enrollment->class?->subject_code ?? 'N/A';
                    $section = $enrollment->class?->section ?? 'N/A';

                    return $subjectCode !== 'N/A' ? ($subjectCode.' - '.$section) : $section;
                })
                ->filter()
                ->unique()
                ->values();

            return [
                'id' => $student->id,
                'student_id' => $student->student_id,
                'name' => $student->full_name,
                'course' => $student->Course?->code,
                'course_title' => $student->Course?->title,
                'academic_year' => $student->formatted_academic_year,
                'class_count' => $student->class_count,
                'sections' => $labels,
            ];
        })->values();

        return response()->json(['students' => $results]);
    }

    private function resolveFacultyOrAbort(): Faculty
    {
        /** @var User|null $user */
        $user = Auth::user();

        if (! $user || ! method_exists($user, 'isFaculty') || ! $user->isFaculty()) {
            abort(403);
        }

        $faculty = Faculty::where('email', $user->email)->first();

        if (! $faculty) {
            abort(403);
        }

        return $faculty;
    }

    private function normalizeQuery(Request $request): ?string
    {
        $query = $request->input('q') ?? $request->input('query');

        if (! is_string($query)) {
            return null;
        }

        $query = mb_trim($query);

        if ($query === '' || mb_strlen($query) < 2) {
            return null;
        }

        return $query;
    }
}
