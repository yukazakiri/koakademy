<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\ClassEnrollment;
use App\Models\Classes;
use App\Models\Faculty;
use App\Models\School;
use App\Models\Student;
use App\Models\SubjectEnrollment;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

final class ClassEnrollmentSeeder extends Seeder
{
    /**
     * The sections reserved for the demo cohort.
     *
     * @var list<string>
     */
    private const array DEMO_SECTIONS = ['DEMO-A', 'DEMO-B', 'DEMO-C'];

    /**
     * Realistic partial term grades (0–100 percentage scale).
     *
     * @var list<float>
     */
    private const array PARTIAL_GRADES = [
        80.00,
        82.00,
        84.00,
        85.00,
        87.00,
        88.00,
        90.00,
        92.00,
        94.00,
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::transaction(function (): void {
            $this->seedClassEnrollments();
        });

        $this->command?->info('Class enrollments seeded successfully!');
    }

    /**
     * Both accepted spellings of a school year.
     *
     * @return list<string>
     */
    private static function schoolYearVariants(?string $schoolYear): array
    {
        if ($schoolYear === null || $schoolYear === '') {
            return [];
        }

        $compact = str_replace(' ', '', $schoolYear);

        return array_values(array_unique([$schoolYear, $compact]));
    }

    private function seedClassEnrollments(): void
    {
        $school = School::query()->first();
        $schoolId = $school?->id ?? 1;
        $students = Student::withoutSchoolScope()->get()->keyBy('id');
        $classes = Classes::withoutSchoolScope()->get();
        $subjectEnrollments = SubjectEnrollment::withoutSchoolScope()
            ->with('subject')
            ->whereNull('grade')
            ->whereNull('class_id')
            ->get();

        // The demo classes carry a curated cohort that the faculty walkthrough
        // depends on, so the automatic path leaves them alone.
        $autoEnrolmentClasses = $classes->whereNotIn('section', self::DEMO_SECTIONS);

        // Track slot counts in memory to avoid per-candidate COUNT(*) queries
        $enrollmentCounts = ClassEnrollment::withoutSchoolScope()
            ->selectRaw('class_id, COUNT(*) as aggregate')
            ->groupBy('class_id')
            ->pluck('aggregate', 'class_id')
            ->map(fn ($c): int => (int) $c)
            ->all();

        // Auto-enroll students in current-semester classes based on their in-progress subject enrollments
        foreach ($subjectEnrollments as $subjectEnrollment) {
            $student = $students->get((int) $subjectEnrollment->student_id);
            $subject = $subjectEnrollment->subject;

            if (! $student instanceof Student || $subject === null) {
                continue;
            }

            $schoolYearVariants = self::schoolYearVariants($subjectEnrollment->school_year);
            $targetSemester = (int) $subjectEnrollment->semester;
            $targetSubjectId = (int) $subject->id;
            $targetCourseId = (int) $student->course_id;

            $availableClasses = $autoEnrolmentClasses->filter(function (Classes $class) use (
                $schoolYearVariants,
                $targetSemester,
                $targetSubjectId,
                $targetCourseId,
                $subject,
            ): bool {
                if (! in_array($class->school_year, $schoolYearVariants, true) || (int) $class->semester !== $targetSemester) {
                    return false;
                }

                $subjectIds = array_map('intval', (array) $class->subject_ids);
                if ((int) $class->subject_id === $targetSubjectId || in_array($targetSubjectId, $subjectIds, true)) {
                    return true;
                }

                if ($class->subject_code !== $subject->code) {
                    return false;
                }

                $courseCodes = is_string($class->course_codes)
                    ? (array) json_decode($class->course_codes, true)
                    : (array) $class->course_codes;

                return in_array($targetCourseId, array_map('intval', $courseCodes), true);
            });

            if ($availableClasses->isEmpty()) {
                continue;
            }

            $bestClass = null;
            $minEnrollment = PHP_INT_MAX;

            foreach ($availableClasses as $availableClass) {
                $currentCount = $enrollmentCounts[$availableClass->id] ?? 0;

                if ($availableClass->maximum_slots && $currentCount >= $availableClass->maximum_slots) {
                    continue;
                }

                if ($currentCount < $minEnrollment) {
                    $minEnrollment = $currentCount;
                    $bestClass = $availableClass;
                }
            }

            if ($bestClass instanceof Classes) {
                $classEnrollment = ClassEnrollment::withoutSchoolScope()->firstOrCreate(
                    [
                        'class_id' => $bestClass->id,
                        'student_id' => $student->id,
                    ],
                    [
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
                    ],
                );

                if ($classEnrollment->wasRecentlyCreated) {
                    $enrollmentCounts[$bestClass->id] = ($enrollmentCounts[$bestClass->id] ?? 0) + 1;
                }

                $subjectEnrollment->update([
                    'class_id' => $bestClass->id,
                    'section' => $bestClass->section,
                ]);
            }
        }

        // Seed partial term grades (prelim & midterm on 0–100 scale) for a sample of current class enrollments
        $currentSubjectEnrollments = SubjectEnrollment::withoutSchoolScope()
            ->whereNull('grade')
            ->whereNotNull('class_id')
            ->take(15)
            ->get();

        foreach ($currentSubjectEnrollments as $currentSubjectEnrollment) {
            $classEnrollment = ClassEnrollment::withoutSchoolScope()
                ->where('class_id', $currentSubjectEnrollment->class_id)
                ->where('student_id', $currentSubjectEnrollment->student_id)
                ->first();

            if ($classEnrollment instanceof ClassEnrollment) {
                $prelim = self::PARTIAL_GRADES[array_rand(self::PARTIAL_GRADES)];
                $midterm = self::PARTIAL_GRADES[array_rand(self::PARTIAL_GRADES)];

                $classEnrollment->update([
                    'prelim_grade' => $prelim,
                    'midterm_grade' => $midterm,
                    'finals_grade' => null,
                    'total_average' => null,
                    'is_grades_finalized' => false,
                    'is_grades_verified' => false,
                    'remarks' => 'In progress - partial grades recorded',
                ]);
            }
        }

        $this->seedDemoFacultyClassEnrollments($schoolId);
    }

    private function seedDemoFacultyClassEnrollments(int $schoolId): void
    {
        $demoFaculty = Faculty::query()
            ->where('email', 'j.adams@koakademy.edu')
            ->first();

        if ($demoFaculty === null) {
            return;
        }

        $demoClasses = Classes::query()
            ->where('faculty_id', $demoFaculty->id)
            ->whereIn('section', self::DEMO_SECTIONS)
            ->orderBy('section')
            ->get();

        if ($demoClasses->count() !== 3) {
            return;
        }

        $students = Student::query()
            ->where('email', '!=', 'john.student@student.koakademy.edu')
            ->orderBy('id')
            ->take(30)
            ->get();

        if ($students->count() < 30) {
            return;
        }

        $studentsByClass = $students->chunk(10)->values();

        foreach ($demoClasses as $index => $demoClass) {
            $classStudents = $studentsByClass->get($index);
            if ($classStudents === null || $classStudents->count() < 10) {
                continue;
            }

            foreach ($classStudents as $student) {
                $alreadyEnrolled = ClassEnrollment::query()
                    ->where('class_id', $demoClass->id)
                    ->where('student_id', $student->id)
                    ->exists();

                if ($alreadyEnrolled) {
                    continue;
                }

                ClassEnrollment::query()->create([
                    'class_id' => $demoClass->id,
                    'student_id' => $student->id,
                    'school_id' => $schoolId,
                    'completion_date' => null,
                    'status' => true,
                    'remarks' => 'Demo enrollment for faculty walkthrough',
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
            }
        }
    }
}
