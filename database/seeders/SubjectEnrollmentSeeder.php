<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\School;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\Subject;
use App\Models\SubjectEnrollment;
use Illuminate\Database\Seeder;

final class SubjectEnrollmentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $school = School::query()->first();
        $schoolId = $school?->id ?? 1;
        $students = Student::all();
        $subjects = Subject::all();
        $enrollments = StudentEnrollment::query()->where('status', 'Enrolled')->get();

        // Create subject enrollments for each student enrollment
        foreach ($enrollments as $enrollment) {
            $student = $students->where('id', $enrollment->student_id)->first();
            if (! $student) {
                continue;
            }

            // Get subjects for this student's course and academic year
            $courseSubjects = $subjects->where('course_id', $enrollment->course_id)
                ->where('academic_year', $enrollment->academic_year)
                ->where('semester', $enrollment->semester);

            // Also get PATHFIT and other general subjects (academic_year = null)
            $generalSubjects = $subjects->where('course_id', $enrollment->course_id)
                ->whereNull('academic_year')
                ->where('semester', $enrollment->semester);

            $allSubjects = $courseSubjects->merge($generalSubjects);

            foreach ($allSubjects as $allSubject) {
                // Calculate fees based on subject units and course rates
                $course = $student->Course;
                $lectureFee = $allSubject->lecture * (float) ($course->lec_per_unit ?? 150);
                $laboratoryFee = $allSubject->laboratory * (float) ($course->lab_per_unit ?? 200);

                SubjectEnrollment::query()->create([
                    'subject_id' => $allSubject->id,
                    'class_id' => null, // Will be assigned when auto-enrolling in classes
                    'grade' => null, // Grades not assigned yet
                    'instructor' => null,
                    'student_id' => $student->id,
                    'school_id' => $schoolId,
                    'academic_year' => $enrollment->academic_year,
                    'school_year' => $enrollment->school_year,
                    'semester' => $enrollment->semester,
                    'enrollment_id' => $enrollment->id,
                    'remarks' => null,
                    'classification' => $allSubject->classification->value,
                    'school_name' => 'KoAkademy',
                    'is_credited' => $allSubject->is_credited,
                    'credited_subject_id' => null,
                    'section' => null, // Will be assigned when enrolled in class
                    'is_modular' => false,
                    'lecture_fee' => $lectureFee,
                    'laboratory_fee' => $laboratoryFee,
                    'enrolled_lecture_units' => $allSubject->lecture,
                    'enrolled_laboratory_units' => $allSubject->laboratory,
                ]);
            }
        }

        // Create some completed subject enrollments with grades (for previous semesters)
        $completedEnrollments = StudentEnrollment::query()->where('status', 'Completed')->get();

        foreach ($completedEnrollments as $completedEnrollment) {
            $student = $students->where('id', $completedEnrollment->student_id)->first();
            if (! $student) {
                continue;
            }

            $courseSubjects = $subjects->where('course_id', $completedEnrollment->course_id)
                ->where('academic_year', $completedEnrollment->academic_year)
                ->where('semester', $completedEnrollment->semester)
                ->take(5); // Limit to 5 subjects for previous semesters

            foreach ($courseSubjects as $courseSubject) {
                $course = $student->Course;
                $lectureFee = $courseSubject->lecture * (float) ($course->lec_per_unit ?? 150);
                $laboratoryFee = $courseSubject->laboratory * (float) ($course->lab_per_unit ?? 200);

                // Generate realistic grades
                $grades = [1.0, 1.25, 1.5, 1.75, 2.0, 2.25, 2.5, 2.75, 3.0];
                $randomGrade = $grades[array_rand($grades)];

                SubjectEnrollment::query()->create([
                    'subject_id' => $courseSubject->id,
                    'class_id' => null,
                    'grade' => $randomGrade,
                    'instructor' => 'Previous Instructor',
                    'student_id' => $student->id,
                    'school_id' => $schoolId,
                    'academic_year' => $completedEnrollment->academic_year,
                    'school_year' => $completedEnrollment->school_year,
                    'semester' => $completedEnrollment->semester,
                    'enrollment_id' => $completedEnrollment->id,
                    'remarks' => 'Completed with grade',
                    'classification' => $courseSubject->classification->value,
                    'school_name' => 'KoAkademy',
                    'is_credited' => $courseSubject->is_credited,
                    'credited_subject_id' => null,
                    'section' => 'A',
                    'is_modular' => false,
                    'lecture_fee' => $lectureFee,
                    'laboratory_fee' => $laboratoryFee,
                    'enrolled_lecture_units' => $courseSubject->lecture,
                    'enrolled_laboratory_units' => $courseSubject->laboratory,
                ]);
            }
        }

        // Create some credited subject enrollments (for transferee)
        $transfereeStudent = $students->where('student_type', 'Transferee')->first();
        if ($transfereeStudent) {
            $transfereeEnrollment = $enrollments->where('student_id', $transfereeStudent->id)->first();
            if ($transfereeEnrollment) {
                // Create a few credited subjects from previous school
                $creditedSubjects = $subjects->where('course_id', $transfereeEnrollment->course_id)
                    ->where('academic_year', 1) // First year subjects credited
                    ->take(3);

                foreach ($creditedSubjects as $creditedSubject) {
                    SubjectEnrollment::query()->create([
                        'subject_id' => $creditedSubject->id,
                        'class_id' => null,
                        'grade' => 2.0, // Good grade from previous school
                        'instructor' => 'Previous School Instructor',
                        'student_id' => $transfereeStudent->id,
                        'school_id' => $schoolId,
                        'academic_year' => 1,
                        'school_year' => '2023-2024',
                        'semester' => 1,
                        'enrollment_id' => $transfereeEnrollment->id,
                        'remarks' => 'Credited from previous institution',
                        'classification' => 'credited',
                        'school_name' => 'Previous University',
                        'is_credited' => true,
                        'credited_subject_id' => $creditedSubject->id,
                        'section' => 'N/A',
                        'is_modular' => false,
                        'lecture_fee' => 0, // No fee for credited subjects
                        'laboratory_fee' => 0,
                        'enrolled_lecture_units' => $creditedSubject->lecture,
                        'enrolled_laboratory_units' => $creditedSubject->laboratory,
                    ]);
                }
            }
        }

        $this->command->info('Subject enrollments seeded successfully!');
    }
}
