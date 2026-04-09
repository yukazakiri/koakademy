<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\ClassEnrollment;
use App\Models\Classes;
use App\Models\School;
use App\Models\Student;
use App\Models\SubjectEnrollment;
use Illuminate\Database\Seeder;

final class ClassEnrollmentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $school = School::query()->first();
        $schoolId = $school?->id ?? 1;
        $students = Student::all();
        $classes = Classes::all();
        $subjectEnrollments = SubjectEnrollment::query()->whereNull('class_id')->get();

        // Auto-enroll students in classes based on their subject enrollments
        foreach ($subjectEnrollments as $subjectEnrollment) {
            $student = $students->where('id', $subjectEnrollment->student_id)->first();
            if (! $student) {
                continue;
            }

            // Find appropriate class for this subject enrollment
            $availableClasses = $classes->where('subject_code', $subjectEnrollment->subject->code)
                ->where('school_year', $subjectEnrollment->school_year)
                ->where('semester', $subjectEnrollment->semester);

            // Filter by academic year if subject has specific academic year
            if ($subjectEnrollment->subject->academic_year !== null) {
                $availableClasses = $availableClasses->where('academic_year', $subjectEnrollment->academic_year);
            }

            // Filter by course codes
            $availableClasses = $availableClasses->filter(function ($class) use ($student): bool {
                if (! $class->course_codes) {
                    return false;
                }

                $courseCodes = is_string($class->course_codes)
                    ? json_decode($class->course_codes, true)
                    : $class->course_codes;

                return in_array((string) $student->course_id, $courseCodes);
            });

            if ($availableClasses->isNotEmpty()) {
                // Find the class with the least enrollment
                $bestClass = null;
                $minEnrollment = PHP_INT_MAX;

                foreach ($availableClasses as $availableClass) {
                    $enrollmentCount = ClassEnrollment::query()->where('class_id', $availableClass->id)->count();

                    // Skip if class is full
                    if ($availableClass->maximum_slots && $enrollmentCount >= $availableClass->maximum_slots) {
                        continue;
                    }

                    if ($enrollmentCount < $minEnrollment) {
                        $minEnrollment = $enrollmentCount;
                        $bestClass = $availableClass;
                    }
                }

                if ($bestClass) {
                    // Check if student is already enrolled in this class
                    $existingEnrollment = ClassEnrollment::query()->where('class_id', $bestClass->id)
                        ->where('student_id', $student->id)
                        ->first();

                    if (! $existingEnrollment) {
                        // Create class enrollment
                        ClassEnrollment::query()->create([
                            'class_id' => $bestClass->id,
                            'student_id' => $student->id,
                            'school_id' => $schoolId,
                            'completion_date' => null,
                            'status' => true,
                            'remarks' => 'Auto-enrolled based on subject enrollment',
                            'prelim_grade' => null,
                            'midterm_grade' => null,
                            'finals_grade' => null,
                            'total_average' => null,
                            'is_grades_finalized' => false,
                            'is_grades_verified' => false,
                            'verified_by' => null,
                            'verified_at' => null,
                            'verification_notes' => null,
                        ]);

                        // Update subject enrollment with class and section info
                        $subjectEnrollment->update([
                            'class_id' => $bestClass->id,
                            'section' => $bestClass->section,
                        ]);
                    }
                }
            }
        }

        // Create some class enrollments with grades for completed subjects
        $completedSubjectEnrollments = SubjectEnrollment::query()->whereNotNull('grade')->get();

        foreach ($completedSubjectEnrollments as $completedSubjectEnrollment) {
            if ($completedSubjectEnrollment->class_id) {
                continue;
            } // Skip if already has class

            $student = $students->where('id', $completedSubjectEnrollment->student_id)->first();
            if (! $student) {
                continue;
            }

            // Find a class for this completed subject (from previous semester)
            $pastClass = $classes->where('subject_code', $completedSubjectEnrollment->subject->code)->first();

            if ($pastClass) {
                // Generate realistic term grades that average to the final grade
                $finalGrade = $completedSubjectEnrollment->grade;
                $variance = 0.25; // Allow some variance between terms

                $prelimGrade = max(1.0, min(5.0, $finalGrade + (random_int(-25, 25) / 100)));
                $midtermGrade = max(1.0, min(5.0, $finalGrade + (random_int(-25, 25) / 100)));
                $finalsGrade = max(1.0, min(5.0, $finalGrade + (random_int(-25, 25) / 100)));

                // Adjust to ensure average matches final grade
                $average = ($prelimGrade + $midtermGrade + $finalsGrade) / 3;
                $adjustment = $finalGrade - $average;
                $finalsGrade = max(1.0, min(5.0, $finalsGrade + $adjustment));

                ClassEnrollment::query()->create([
                    'class_id' => $pastClass->id,
                    'student_id' => $student->id,
                    'school_id' => $schoolId,
                    'completion_date' => now()->subMonths(6), // 6 months ago
                    'status' => true,
                    'remarks' => 'Completed with grades',
                    'prelim_grade' => round($prelimGrade, 2),
                    'midterm_grade' => round($midtermGrade, 2),
                    'finals_grade' => round($finalsGrade, 2),
                    'total_average' => round($finalGrade, 2),
                    'is_grades_finalized' => true,
                    'is_grades_verified' => true,
                    'verified_by' => 1, // Admin user
                    'verified_at' => now()->subMonths(5),
                    'verification_notes' => 'Grades verified and finalized',
                ]);

                // Update subject enrollment with class info
                $completedSubjectEnrollment->update([
                    'class_id' => $pastClass->id,
                    'section' => $pastClass->section,
                ]);
            }
        }

        // Create some in-progress class enrollments with partial grades
        $currentSubjectEnrollments = SubjectEnrollment::query()->whereNull('grade')
            ->whereNotNull('class_id')
            ->take(15) // Limit to 15 for variety
            ->get();

        foreach ($currentSubjectEnrollments as $currentSubjectEnrollment) {
            $classEnrollment = ClassEnrollment::query()->where('class_id', $currentSubjectEnrollment->class_id)
                ->where('student_id', $currentSubjectEnrollment->student_id)
                ->first();

            if ($classEnrollment) {
                // Add some partial grades (prelim and midterm only)
                $grades = [1.0, 1.25, 1.5, 1.75, 2.0, 2.25, 2.5, 2.75, 3.0];

                $classEnrollment->update([
                    'prelim_grade' => $grades[array_rand($grades)],
                    'midterm_grade' => $grades[array_rand($grades)],
                    'finals_grade' => null, // Not yet taken
                    'total_average' => null,
                    'is_grades_finalized' => false,
                    'is_grades_verified' => false,
                    'remarks' => 'In progress - partial grades recorded',
                ]);
            }
        }

        $this->command->info('Class enrollments seeded successfully!');
    }
}
