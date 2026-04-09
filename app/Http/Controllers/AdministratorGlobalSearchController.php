<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\StudentStatus;
use App\Enums\StudentType;
use App\Models\Classes;
use App\Models\Faculty;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\User;
use App\Services\GeneralSettingsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class AdministratorGlobalSearchController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $query = $request->input('q') ?? $request->input('query');
        $type = $request->input('type', 'all');

        $emptyResults = [
            'students' => [],
            'classes' => [],
            'users' => [],
            'faculty' => [],
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

        if ($type === 'all' || $type === 'students') {
            $results['students'] = $this->searchStudents($query, $limit);
        }

        if ($type === 'all' || $type === 'classes') {
            $results['classes'] = $this->searchClasses($query, $limit);
        }

        if ($type === 'all' || $type === 'users') {
            $results['users'] = $this->searchUsers($query, $limit);
        }

        if ($type === 'all' || $type === 'faculty') {
            $results['faculty'] = $this->searchFaculty($query, $limit);
        }

        if ($type === 'all' || $type === 'enrollments') {
            $results['enrollments'] = $this->searchEnrollments($query, $limit);
        }

        return response()->json($results);
    }

    /**
     * Search students
     *
     * @return array<int, array<string, mixed>>
     */
    private function searchStudents(string $query, int $limit): array
    {
        return Student::search($query)
            ->query(fn ($builder) => $builder->with(['Course']))
            ->take($limit)
            ->get()
            ->map(function (Student $student): array {
                $studentType = $student->student_type;
                $status = $student->status;

                return [
                    'id' => $student->id,
                    'student_id' => $student->student_id,
                    'name' => $student->full_name,
                    'email' => $student->email,
                    'course' => $student->Course?->code,
                    'course_title' => $student->Course?->title,
                    'academic_year' => $student->formatted_academic_year,
                    'type' => $studentType instanceof StudentType ? $studentType->value : (is_string($studentType) ? $studentType : null),
                    'status' => $status instanceof StudentStatus ? $status->value : (is_string($status) ? $status : null),
                ];
            })
            ->values()
            ->toArray();
    }

    /**
     * Search classes
     *
     * @return array<int, array<string, mixed>>
     */
    private function searchClasses(string $query, int $limit): array
    {
        $generalSettingsService = app(GeneralSettingsService::class);
        $schoolYearWithSpaces = $generalSettingsService->getCurrentSchoolYearString();
        $schoolYearNoSpaces = str_replace(' ', '', $schoolYearWithSpaces);
        $semester = (string) $generalSettingsService->getCurrentSemester();

        return Classes::search($query)
            ->where('semester', $semester)
            ->whereIn('school_year', [$schoolYearWithSpaces, $schoolYearNoSpaces])
            ->query(fn ($builder) => $builder
                ->with([
                    'Subject',
                    'SubjectByCodeFallback',
                    'ShsSubject',
                    'faculty',
                    'Room',
                ])
                ->withCount('class_enrollments')
            )
            ->take($limit)
            ->get()
            ->map(function (Classes $class): array {
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
                    'faculty' => $class->faculty?->full_name ?? 'TBA',
                    'students_count' => (int) ($class->class_enrollments_count ?? 0),
                    'maximum_slots' => (int) ($class->maximum_slots ?? 0),
                    'room' => $class->Room?->name ?? 'TBA',
                    'courses' => $class->formatted_course_codes ?? 'N/A',
                ];
            })
            ->values()
            ->toArray();
    }

    /**
     * Search users
     *
     * @return array<int, array<string, mixed>>
     */
    private function searchUsers(string $query, int $limit): array
    {
        return User::search($query)
            ->take($limit)
            ->get()
            ->map(fn (User $user): array => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role?->getLabel() ?? 'User',
                'avatar' => $user->avatar_url,
            ])
            ->values()
            ->toArray();
    }

    /**
     * Search faculty
     *
     * @return array<int, array<string, mixed>>
     */
    private function searchFaculty(string $query, int $limit): array
    {
        return Faculty::search($query)
            ->take($limit)
            ->get()
            ->map(fn (Faculty $faculty): array => [
                'id' => $faculty->id,
                'name' => $faculty->full_name,
                'email' => $faculty->email,
                'department' => $faculty->department,
                'avatar' => $faculty->photo_url,
            ])
            ->values()
            ->toArray();
    }

    /**
     * Search enrollments
     *
     * @return array<int, array<string, mixed>>
     */
    private function searchEnrollments(string $query, int $limit): array
    {
        $generalSettingsService = app(GeneralSettingsService::class);
        $schoolYearWithSpaces = $generalSettingsService->getCurrentSchoolYearString();
        $schoolYearNoSpaces = str_replace(' ', '', $schoolYearWithSpaces);
        $semester = (string) $generalSettingsService->getCurrentSemester();

        return StudentEnrollment::search($query)
            ->where('semester', $semester)
            ->whereIn('school_year', [$schoolYearWithSpaces, $schoolYearNoSpaces])
            ->query(fn ($builder) => $builder->with(['student', 'course']))
            ->take($limit)
            ->get()
            ->map(fn (StudentEnrollment $enrollment): array => [
                'id' => $enrollment->id,
                'student_name' => $enrollment->student?->full_name ?? 'Unknown',
                'course_code' => $enrollment->course?->code ?? 'N/A',
                'year_level' => $enrollment->academic_year ?? 'N/A',
                'status' => $enrollment->status,
                'school_year' => $enrollment->school_year,
                'semester' => $enrollment->semester,
            ])
            ->values()
            ->toArray();
    }
}
