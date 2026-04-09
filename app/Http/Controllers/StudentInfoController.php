<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\ClassEnrollment;
use App\Models\Faculty;
use App\Models\Student;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

final class StudentInfoController extends Controller
{
    public function show(string $id): JsonResponse
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

        $student = Student::find($id) ?? Student::where('student_id', $id)->first();

        if (! $student) {
            return response()->json([
                'error' => 'Student not found',
            ], 404);
        }

        $canViewStudent = ClassEnrollment::query()
            ->where('student_id', $student->id)
            ->whereHas('class', function ($classQuery) use ($faculty): void {
                $classQuery->where('faculty_id', $faculty->id)
                    ->currentAcademicPeriod();
            })
            ->exists();

        if (! $canViewStudent) {
            abort(403);
        }

        $student->loadMissing([
            'Course',
            'studentContactsInfo',
            'studentParentInfo',
            'studentEducationInfo',
            'personalInfo',
        ]);

        $picture = $student->picture1x1 ?: null;

        $academicYears = [
            1 => '1st year',
            2 => '2nd year',
            3 => '3rd year',
            4 => '4th year',
            5 => '5th year',
        ];

        $formattedAcademicYear = $academicYears[$student->academic_year] ?? 'Unknown year';

        $nameSegments = collect([
            $student->first_name,
            $student->middle_name,
            $student->last_name,
        ])
            ->filter()
            ->implode(' ');

        $fullName = $student->full_name ?: ($nameSegments ?: 'N/A');

        $publicInfo = [
            'id' => $student->id,
            'student_id' => $student->student_id,
            'lrn' => $student->lrn,
            'name' => $fullName,
            'first_name' => $student->first_name,
            'middle_name' => $student->middle_name,
            'last_name' => $student->last_name,
            'email' => $student->email,
            'phone' => $student->phone,
            'gender' => $student->gender,
            'civil_status' => $student->civil_status,
            'nationality' => $student->nationality,
            'religion' => $student->religion,
            'address' => $student->address,
            'student_type' => $student->student_type,
            'status' => $student->status,
            'course' => [
                'name' => $student->Course?->title ?? 'N/A',
                'code' => $student->Course?->code ?? 'N/A',
            ],
            'academic_year' => $formattedAcademicYear,
            'picture' => $picture,
            'age' => $student->age,
            'birth_date' => $student->birth_date?->format('F j, Y'),
            'emergency_contact' => $student->emergency_contact,
        ];

        return response()->json([
            'student' => $publicInfo,
        ]);
    }
}
