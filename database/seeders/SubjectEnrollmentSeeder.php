<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\EnrollStat;
use App\Enums\SubjectEnrolledEnum;
use App\Models\Course;
use App\Models\GradeRelease;
use App\Models\School;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\Subject;
use App\Models\SubjectEnrollment;
use App\Models\User;
use App\Services\GeneralSettingsService;
use App\Services\GradeReleaseService;
use App\Services\GradingSystemService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

final class SubjectEnrollmentSeeder extends Seeder
{
    /**
     * Realistic passing percentage grades (0–100 scale, passing >= 75).
     *
     * @var list<float>
     */
    private const array PASSING_GRADES = [
        82.00,
        84.00,
        85.00,
        86.00,
        87.00,
        88.00,
        89.00,
        90.00,
        91.00,
        92.00,
        93.00,
        94.00,
        95.00,
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::transaction(function (): void {
            $this->seedSubjectEnrollments();
        });

        $this->command?->info('Subject enrollments seeded successfully!');
    }

    private function seedSubjectEnrollments(): void
    {
        $school = School::query()->first();
        $schoolId = $school?->id ?? 1;

        $gradingConfig = app(GradingSystemService::class)->ensureConfig($school);
        $policyVersionId = isset($gradingConfig['policy_version_id']) ? (int) $gradingConfig['policy_version_id'] : null;

        $settings = app(GeneralSettingsService::class);
        $currentSchoolYear = $settings->getCurrentSchoolYearString();
        $currentSemester = $settings->getCurrentSemester();

        $students = Student::withoutSchoolScope()->get()->keyBy('id');
        $courses = Course::withoutSchoolScope()->get()->keyBy('id');
        $subjects = Subject::withoutSchoolScope()
            ->orderBy('academic_year')
            ->orderBy('semester')
            ->orderBy('code')
            ->get();

        $subjectsByPeriod = $subjects->groupBy(
            fn (Subject $s): string => sprintf('%d:%d:%d', (int) $s->course_id, (int) $s->academic_year, (int) $s->semester),
        );

        // Identify the 3 Y1S1 BSCS subjects that will be marked as transferee-credited for student 2024003
        $bscsCourseId = $courses->firstWhere('code', 'BSCS')?->id;
        $transfereeCreditedSubjectIds = $bscsCourseId
            ? ($subjectsByPeriod->get("{$bscsCourseId}:1:1") ?? collect())->take(3)->pluck('id')->map(fn ($id): int => (int) $id)->all()
            : [];

        // 1. Seed current-semester subject enrollments (in-progress, grade = null)
        $activeStatuses = [
            'Enrolled',
            EnrollStat::Enrolled->value,
            EnrollStat::VerifiedByCashier->value,
            EnrollStat::VerifiedByDeptHead->value,
            EnrollStat::Pending->value,
        ];

        $currentEnrollments = StudentEnrollment::withoutSchoolScope()
            ->whereIn('status', $activeStatuses)
            ->get();

        foreach ($currentEnrollments as $enrollment) {
            // Skip pending future-semester pre-enrollments so next semester's subjects stay 'Not Completed'
            if ($enrollment->status === EnrollStat::Pending->value
                && $enrollment->school_year === $currentSchoolYear
                && (int) $enrollment->semester !== $currentSemester) {
                continue;
            }

            $student = $students->get((int) $enrollment->student_id);
            if (! $student instanceof Student) {
                continue;
            }

            $course = $courses->get((int) $enrollment->course_id) ?? $courses->get((int) $student->course_id);
            $periodKey = sprintf('%d:%d:%d', (int) $enrollment->course_id, (int) $enrollment->academic_year, (int) $enrollment->semester);
            $courseSubjects = $subjectsByPeriod->get($periodKey, collect());

            foreach ($courseSubjects as $subject) {
                $lectureFee = $subject->lecture * (float) ($course?->lec_per_unit ?? 150);
                $laboratoryFee = $subject->laboratory * (float) ($course?->lab_per_unit ?? 200);

                SubjectEnrollment::withoutSchoolScope()->firstOrCreate(
                    [
                        'enrollment_id' => $enrollment->id,
                        'subject_id' => $subject->id,
                    ],
                    [
                        'class_id' => null,
                        'grade' => null,
                        'grading_policy_version_id' => $policyVersionId,
                        'grade_symbol' => null,
                        'grade_outcome' => null,
                        'grade_quality_points' => null,
                        'instructor' => null,
                        'student_id' => $student->id,
                        'school_id' => $schoolId,
                        'academic_year' => (int) $enrollment->academic_year,
                        'school_year' => $enrollment->school_year,
                        'semester' => (int) $enrollment->semester,
                        'remarks' => null,
                        'classification' => SubjectEnrolledEnum::INTERNAL->value,
                        'school_name' => 'KoAkademy',
                        'is_credited' => false,
                        'credited_subject_id' => null,
                        'section' => null,
                        'is_modular' => false,
                        'lecture_fee' => $lectureFee,
                        'laboratory_fee' => $laboratoryFee,
                        'enrolled_lecture_units' => $subject->lecture,
                        'enrolled_laboratory_units' => $subject->laboratory,
                    ],
                );
            }
        }

        // 2. Seed completed historical subject enrollments with 0–100 percentage grades
        $completedEnrollments = StudentEnrollment::withoutSchoolScope()
            ->where('status', 'Completed')
            ->get();

        $publisher = User::query()
            ->whereIn('email', ['registrar@koakademy.edu', 'admin@koakademy.edu'])
            ->first() ?? User::query()->first();
        $gradeReleaseService = app(GradeReleaseService::class);

        foreach ($completedEnrollments as $completedEnrollment) {
            $student = $students->get((int) $completedEnrollment->student_id);
            if (! $student instanceof Student) {
                continue;
            }

            $course = $courses->get((int) $completedEnrollment->course_id) ?? $courses->get((int) $student->course_id);
            $periodKey = sprintf(
                '%d:%d:%d',
                (int) $completedEnrollment->course_id,
                (int) $completedEnrollment->academic_year,
                (int) $completedEnrollment->semester,
            );
            $courseSubjects = $subjectsByPeriod->get($periodKey, collect());

            foreach ($courseSubjects as $courseSubject) {
                $isTransfereeCredit = ((int) $student->id === 2024003 || $student->student_type === 'Transferee')
                    && in_array((int) $courseSubject->id, $transfereeCreditedSubjectIds, true);

                $lectureFee = $isTransfereeCredit ? 0.0 : $courseSubject->lecture * (float) ($course?->lec_per_unit ?? 150);
                $laboratoryFee = $isTransfereeCredit ? 0.0 : $courseSubject->laboratory * (float) ($course?->lab_per_unit ?? 200);

                $gradeIndex = abs(crc32($student->id.':'.$courseSubject->id)) % count(self::PASSING_GRADES);
                $grade = $isTransfereeCredit ? 88.00 : self::PASSING_GRADES[$gradeIndex];

                SubjectEnrollment::withoutSchoolScope()->firstOrCreate(
                    [
                        'enrollment_id' => $completedEnrollment->id,
                        'subject_id' => $courseSubject->id,
                    ],
                    [
                        'class_id' => null,
                        'grade' => $grade,
                        'grading_policy_version_id' => $policyVersionId,
                        'grade_symbol' => null,
                        'grade_outcome' => 'pass',
                        'grade_quality_points' => null,
                        'instructor' => $isTransfereeCredit ? 'Previous School Instructor' : 'Faculty Instructor',
                        'student_id' => $student->id,
                        'school_id' => $schoolId,
                        'academic_year' => (int) $completedEnrollment->academic_year,
                        'school_year' => $completedEnrollment->school_year,
                        'semester' => (int) $completedEnrollment->semester,
                        'remarks' => $isTransfereeCredit ? 'Credited from previous institution' : 'Passed',
                        'classification' => $isTransfereeCredit
                            ? SubjectEnrolledEnum::CREDITED->value
                            : SubjectEnrolledEnum::INTERNAL->value,
                        'school_name' => $isTransfereeCredit ? 'Previous University' : 'KoAkademy',
                        'is_credited' => $isTransfereeCredit,
                        'credited_subject_id' => $isTransfereeCredit ? $courseSubject->id : null,
                        'section' => $isTransfereeCredit ? 'N/A' : 'A',
                        'is_modular' => false,
                        'lecture_fee' => $lectureFee,
                        'laboratory_fee' => $laboratoryFee,
                        'enrolled_lecture_units' => $courseSubject->lecture,
                        'enrolled_laboratory_units' => $courseSubject->laboratory,
                    ],
                );
            }

            // Publish historical grade release snapshot so student-facing views also display completed grades
            if ($courseSubjects->isNotEmpty()) {
                $normalizedYear = $gradeReleaseService->normalizeYear((string) $completedEnrollment->school_year);
                $snapshot = $gradeReleaseService->snapshot($student, $normalizedYear, (int) $completedEnrollment->semester);

                GradeRelease::query()->updateOrCreate(
                    [
                        'student_id' => $student->id,
                        'school_year' => $normalizedYear,
                        'semester' => (int) $completedEnrollment->semester,
                    ],
                    [
                        'snapshot' => $snapshot,
                        'revision' => 1,
                        'published_by' => $publisher?->id,
                        'published_at' => now(),
                        'notification_status' => ['mail' => 'sent', 'database' => 'sent', 'warnings' => []],
                    ],
                );
            }
        }
    }
}
