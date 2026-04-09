<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\StudentType;
use App\Models\ClassEnrollment;
use App\Models\Classes;
use App\Models\Faculty;
use App\Models\Student;
use App\Models\SubjectEnrollment;
use App\Models\User;
use App\Services\GeneralSettingsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

final class FacultyClassStudentSearchController extends Controller
{
    public function __invoke(Request $request, Classes $class): JsonResponse
    {
        $this->assertFacultyOwnsClass($class);

        $query = (string) $request->input('query', '');

        if (mb_strlen($query) < 2) {
            return response()->json(['students' => []]);
        }

        $targetType = match ($class->classification) {
            'college' => StudentType::College,
            'shs' => StudentType::SeniorHighSchool,
            default => null,
        };

        $students = Student::query()
            ->where(function ($q) use ($query): void {
                $q->where('first_name', 'like', "%{$query}%")
                    ->orWhere('last_name', 'like', "%{$query}%")
                    ->orWhere('lrn', 'like', "%{$query}%")
                    ->orWhere('student_id', 'like', "%{$query}%")
                    ->orWhereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", ["%{$query}%"]);
            })
            ->when($targetType, function ($q) use ($targetType): void {
                $q->where('student_type', $targetType->value);
            })
            ->limit(10)
            ->get();

        $settings = app(GeneralSettingsService::class);
        $schoolYear = $settings->getCurrentSchoolYearString();
        $semester = $settings->getCurrentSemester();

        $results = $students->map(function (Student $student) use ($class, $schoolYear, $semester): array {
            $inThisClass = $class->class_enrollments()
                ->where('student_id', $student->id)
                ->exists();

            $otherSection = ClassEnrollment::query()
                ->where('student_id', $student->id)
                ->where('class_id', '!=', $class->id)
                ->whereHas('class', function ($q) use ($class, $schoolYear, $semester): void {
                    $q->where('subject_code', $class->subject_code)
                        ->where('school_year', $schoolYear)
                        ->where('semester', $semester);
                })
                ->with('class')
                ->first();

            $hasSubjectEnrollment = SubjectEnrollment::query()
                ->where('student_id', $student->id)
                ->where('school_year', $schoolYear)
                ->where('semester', $semester)
                ->where(function ($q) use ($class): void {
                    $q->whereHas('subject', function ($sq) use ($class): void {
                        $sq->where('code', $class->subject_code);
                    })
                        ->orWhere('external_subject_code', $class->subject_code);
                })
                ->exists();

            $currentSubjects = SubjectEnrollment::query()
                ->where('student_id', $student->id)
                ->where('school_year', $schoolYear)
                ->where('semester', $semester)
                ->with('subject')
                ->get()
                ->map(fn ($se): array => [
                    'code' => $se->subject ? $se->subject->code : $se->external_subject_code,
                    'title' => $se->subject ? $se->subject->title : $se->external_subject_title,
                ]);

            return [
                'id' => $student->id,
                'name' => $student->full_name,
                'student_id' => $student->student_type === StudentType::SeniorHighSchool
                    ? ($student->lrn ?? 'N/A')
                    : ($student->student_id ? (string) $student->student_id : 'N/A'),
                'email' => $student->email,
                'avatar' => $student->profile_url,
                'status' => [
                    'in_this_class' => $inThisClass,
                    'in_other_section' => $otherSection ? $otherSection->class->section : null,
                    'has_subject_enrollment' => $hasSubjectEnrollment,
                ],
                'current_subjects' => $currentSubjects,
            ];
        });

        return response()->json(['students' => $results]);
    }

    private function resolveFacultyOrAbort(): Faculty
    {
        $user = Auth::user();

        if (! $user instanceof User) {
            abort(403);
        }

        $faculty = Faculty::query()->where('email', $user->email)->first();

        if (! $faculty instanceof Faculty) {
            abort(403);
        }

        return $faculty;
    }

    private function assertFacultyOwnsClass(Classes $class): void
    {
        $faculty = $this->resolveFacultyOrAbort();

        if ($class->faculty_id !== $faculty->id) {
            abort(403);
        }
    }
}
