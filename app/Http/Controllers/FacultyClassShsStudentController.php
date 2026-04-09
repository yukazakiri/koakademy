<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\StudentStatus;
use App\Enums\StudentType;
use App\Models\ClassEnrollment;
use App\Models\Classes;
use App\Models\Faculty;
use App\Models\ShsStrand;
use App\Models\ShsStudent;
use App\Models\Student;
use App\Models\User;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

final class FacultyClassShsStudentController extends Controller
{
    public function __invoke(Request $request, Classes $class): RedirectResponse
    {
        $this->assertFacultyOwnsClass($class);

        if ($class->classification !== 'shs') {
            return redirect()->back()->with('flash', [
                'error' => 'This class is not an SHS class.',
            ]);
        }

        $validated = $request->validate([
            'lrn' => ['required', 'string', 'max:20', 'unique:shs_students,student_lrn'],
            'last_name' => ['required', 'string', 'max:100'],
            'first_name' => ['required', 'string', 'max:100'],
            'middle_name' => ['nullable', 'string', 'max:100'],
            'birth_date' => ['required', 'date', 'before_or_equal:today'],
            'gender' => ['required', 'in:male,female'],
            'contact' => ['nullable', 'string', 'max:20'],
            'strand_id' => ['required', 'exists:shs_strands,id'],
            'grade_level' => ['required', 'in:11,12'],
            'enroll_in_class' => ['boolean'],
        ]);

        DB::beginTransaction();

        try {
            $strand = ShsStrand::query()->findOrFail($validated['strand_id']);

            $birthDate = Carbon::parse($validated['birth_date']);
            $age = $birthDate->age;

            ShsStudent::query()->create([
                'student_lrn' => $validated['lrn'],
                'fullname' => mb_trim("{$validated['last_name']}, {$validated['first_name']} ".($validated['middle_name'] ?? '')),
                'student_contact' => $validated['contact'] ?? null,
                'strand_id' => (int) $validated['strand_id'],
                'track_id' => (int) $strand->track_id,
                'grade_level' => (string) $validated['grade_level'],
                'gender' => (string) $validated['gender'],
                'birthdate' => $birthDate->toDateString(),
                'civil_status' => 'Single',
                'nationality' => 'Filipino',
            ]);

            if (Student::query()->where('lrn', $validated['lrn'])->exists()) {
                throw new Exception('Student with this LRN already exists in the students table.');
            }

            $studentId = Student::generateNextId(StudentType::SeniorHighSchool);

            $student = Student::query()->create([
                'student_id' => $studentId,
                'lrn' => $validated['lrn'],
                'student_type' => StudentType::SeniorHighSchool->value,
                'first_name' => $validated['first_name'],
                'last_name' => $validated['last_name'],
                'middle_name' => $validated['middle_name'] ?? null,
                'birth_date' => $birthDate->toDateString(),
                'age' => $age,
                'gender' => $validated['gender'],
                'contacts' => $validated['contact'] ? ['personal_contact' => $validated['contact']] : null,
                'shs_strand_id' => (int) $validated['strand_id'],
                'shs_track_id' => (int) $strand->track_id,
                'academic_year' => (int) $validated['grade_level'],
                'status' => StudentStatus::Enrolled->value,
            ]);

            if (($validated['enroll_in_class'] ?? true) === true) {
                $enrollment = ClassEnrollment::withTrashed()->updateOrCreate([
                    'class_id' => $class->id,
                    'student_id' => $student->id,
                ], [
                    'status' => true,
                ]);

                if ($enrollment->trashed()) {
                    $enrollment->restore();

                    $enrollment->forceFill([
                        'status' => true,
                    ])->save();
                }
            }

            DB::commit();

            return redirect()->back()->with('flash', [
                'success' => "SHS student {$student->full_name} created and enrolled successfully.",
            ]);
        } catch (Exception $e) {
            DB::rollBack();

            return redirect()->back()->with('flash', [
                'error' => 'Failed to create student: '.$e->getMessage(),
            ]);
        }
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
