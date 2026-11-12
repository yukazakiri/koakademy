<?php

declare(strict_types=1);

use App\Enums\SubjectEnrolledEnum;
use App\Models\Course;
use App\Models\School;
use App\Models\Student;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Coverage for the merge that repairs the duplicate curriculum rows the
 * school scoped program key refused to accept.
 *
 * Production held two programs with the same code in the same school:
 *
 *   ERROR: could not create unique index "courses_school_id_code_unique"
 *   DETAIL: Key (school_id, code)=(4, BSHRM (2009 - 2010)) is duplicated.
 *
 * The merge folds the duplicate into the oldest program that carries the
 * code and repoints everything that referenced it.
 */
beforeEach(function (): void {
    // The school scoped key on the program code cannot hold the duplicate
    // rows. Production reached that state because the global key on the
    // program code was never applied there, so the test removes the key the
    // same way.
    DB::statement('DROP INDEX IF EXISTS courses_school_id_code_unique');
});

/**
 * The migration under test.
 */
function mergeDuplicateCoursesMigration(): object
{
    return require base_path('database/migrations/2026_11_08_000001_merge_duplicate_courses.php');
}

/**
 * The scope key an enrollment policy carries for a program.
 */
function enrollmentPolicyScopeKey(int $schoolId, int $courseId, string $schoolYear, int $semester): string
{
    return hash('sha256', implode('|', [
        'school:'.$schoolId,
        'student_type:*',
        'course:'.$courseId,
        'school_year:'.$schoolYear,
        'semester:'.$semester,
    ]));
}

it('merges a duplicated program code into the oldest program and moves its subjects', function (): void {
    $school = School::factory()->create();

    $survivor = Course::factory()->create([
        'school_id' => $school->id,
        'code' => 'BSHRM (2009 - 2010)',
        'title' => 'Bachelor of Science in Hotel and Restaurant Management',
        'curriculum_year' => '2009 - 2010',
    ]);

    $duplicate = Course::factory()->create([
        'school_id' => $school->id,
        'code' => 'BSHRM (2009 - 2010)',
        'title' => 'Bachelor of Science in Hotel and Restaurant Management',
        'curriculum_year' => '2009 - 2010',
    ]);

    $sharedOnSurvivor = DB::table('subject')->insertGetId([
        'classification' => SubjectEnrolledEnum::INTERNAL->value,
        'code' => 'NSTP 1',
        'title' => 'National Service Training Program I',
        'units' => 3,
        'lecture' => 2,
        'laboratory' => 1,
        'semester' => 1,
        'course_id' => $survivor->id,
        'school_id' => $school->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $sharedOnDuplicate = DB::table('subject')->insertGetId([
        'classification' => SubjectEnrolledEnum::INTERNAL->value,
        'code' => 'NSTP 1',
        'title' => 'National Service Training Program I',
        'units' => 3,
        'lecture' => 2,
        'laboratory' => 1,
        'semester' => 1,
        'course_id' => $duplicate->id,
        'school_id' => $school->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $onlyOnDuplicate = DB::table('subject')->insertGetId([
        'classification' => SubjectEnrolledEnum::INTERNAL->value,
        'code' => 'HOS101',
        'title' => 'Front Office Procedures',
        'units' => 3,
        'lecture' => 2,
        'laboratory' => 1,
        'semester' => 1,
        'course_id' => $duplicate->id,
        'school_id' => $school->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    mergeDuplicateCoursesMigration()->up();

    expect(DB::table('courses')->where('id', $duplicate->id)->exists())->toBeFalse()
        ->and(DB::table('courses')->where('id', $survivor->id)->exists())->toBeTrue();

    // The subject that shares its code with the survivor is folded into it,
    // and the subject that only exists on the duplicate moves over.
    expect(DB::table('subject')->where('id', $sharedOnDuplicate)->exists())->toBeFalse()
        ->and(DB::table('subject')->where('id', $sharedOnSurvivor)->exists())->toBeTrue()
        ->and(DB::table('subject')->where('id', $onlyOnDuplicate)->value('course_id'))->toBe($survivor->id)
        ->and(DB::table('subject')->where('course_id', $survivor->id)->count())->toBe(2);
});

it('repoints every reference of a merged program at the surviving program', function (): void {
    $school = School::factory()->create();
    $user = User::factory()->create(['school_id' => $school->id]);

    $survivor = Course::factory()->create([
        'school_id' => $school->id,
        'code' => 'BSHRM (2009 - 2010)',
    ]);

    $duplicate = Course::factory()->create([
        'school_id' => $school->id,
        'code' => 'BSHRM (2009 - 2010)',
    ]);

    $student = Student::factory()->create([
        'school_id' => $school->id,
        'course_id' => $duplicate->id,
    ]);

    $enrollmentId = DB::table('student_enrollment')->insertGetId([
        'student_id' => $student->id,
        'course_id' => $duplicate->id,
        'semester' => 1,
        'academic_year' => 1,
        'school_year' => '2026-2027',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $importId = DB::table('curriculum_imports')->insertGetId([
        'public_id' => '11111111-1111-4111-8111-111111111111',
        'school_id' => $school->id,
        'uploaded_by_user_id' => $user->id,
        'course_id' => $duplicate->id,
        'filename' => 'bshrm-2009-2010.xlsx',
        'checksum' => str_repeat('a', 64),
        'status' => 'completed',
        'draft' => json_encode(['rows' => []]),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $paperId = DB::table('library_research_papers')->insertGetId([
        'title' => 'Hospitality service research',
        'type' => 'research',
        'course_id' => $duplicate->id,
        'school_id' => $school->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    mergeDuplicateCoursesMigration()->up();

    expect(DB::table('students')->where('id', $student->id)->value('course_id'))->toBe($survivor->id)
        ->and(DB::table('student_enrollment')->where('id', $enrollmentId)->value('course_id'))->toBe($survivor->id)
        ->and(DB::table('curriculum_imports')->where('id', $importId)->value('course_id'))->toBe($survivor->id)
        ->and(DB::table('library_research_papers')->where('id', $paperId)->value('course_id'))->toBe($survivor->id);
});

it('removes the enrollment policy and fee schedule version that duplicate the surviving program scope', function (): void {
    $school = School::factory()->create();

    $survivor = Course::factory()->create([
        'school_id' => $school->id,
        'code' => 'BSHRM (2009 - 2010)',
    ]);

    $duplicate = Course::factory()->create([
        'school_id' => $school->id,
        'code' => 'BSHRM (2009 - 2010)',
    ]);

    $survivorPolicyId = DB::table('enrollment_policies')->insertGetId([
        'name' => 'BSHRM 2009 policy',
        'scope_key' => enrollmentPolicyScopeKey($school->id, $survivor->id, '2026-2027', 1),
        'school_id' => $school->id,
        'course_id' => $survivor->id,
        'school_year' => '2026-2027',
        'semester' => 1,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $duplicatePolicyId = DB::table('enrollment_policies')->insertGetId([
        'name' => 'BSHRM 2009 policy (duplicate)',
        'scope_key' => enrollmentPolicyScopeKey($school->id, $duplicate->id, '2026-2027', 1),
        'school_id' => $school->id,
        'course_id' => $duplicate->id,
        'school_year' => '2026-2027',
        'semester' => 1,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    // A policy for another period of the duplicate program has no conflict
    // and is moved with a recomputed scope key.
    $movedPolicyId = DB::table('enrollment_policies')->insertGetId([
        'name' => 'BSHRM 2025 policy',
        'scope_key' => enrollmentPolicyScopeKey($school->id, $duplicate->id, '2025-2026', 2),
        'school_id' => $school->id,
        'course_id' => $duplicate->id,
        'school_year' => '2025-2026',
        'semester' => 2,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $survivorFeeId = DB::table('fee_schedule_versions')->insertGetId([
        'public_id' => '22222222-2222-4222-8222-222222222222',
        'course_id' => $survivor->id,
        'school_year' => '2026-2027',
        'semester' => 1,
        'academic_year' => 1,
        'version' => 1,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $duplicateFeeId = DB::table('fee_schedule_versions')->insertGetId([
        'public_id' => '33333333-3333-4333-8333-333333333333',
        'course_id' => $duplicate->id,
        'school_year' => '2026-2027',
        'semester' => 1,
        'academic_year' => 1,
        'version' => 1,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $movedFeeId = DB::table('fee_schedule_versions')->insertGetId([
        'public_id' => '44444444-4444-4444-8444-444444444444',
        'course_id' => $duplicate->id,
        'school_year' => '2025-2026',
        'semester' => 2,
        'academic_year' => 1,
        'version' => 1,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    mergeDuplicateCoursesMigration()->up();

    expect(DB::table('enrollment_policies')->where('id', $survivorPolicyId)->exists())->toBeTrue()
        ->and(DB::table('enrollment_policies')->where('id', $duplicatePolicyId)->exists())->toBeFalse()
        ->and(DB::table('enrollment_policies')->where('id', $movedPolicyId)->value('course_id'))->toBe($survivor->id)
        ->and(DB::table('enrollment_policies')->where('id', $movedPolicyId)->value('scope_key'))
        ->toBe(enrollmentPolicyScopeKey($school->id, $survivor->id, '2025-2026', 2));

    expect(DB::table('fee_schedule_versions')->where('id', $survivorFeeId)->exists())->toBeTrue()
        ->and(DB::table('fee_schedule_versions')->where('id', $duplicateFeeId)->exists())->toBeFalse()
        ->and(DB::table('fee_schedule_versions')->where('id', $movedFeeId)->value('course_id'))->toBe($survivor->id);
});

it('repoints class, enrollment and prerequisite references of a merged subject', function (): void {
    $school = School::factory()->create();

    $survivor = Course::factory()->create([
        'school_id' => $school->id,
        'code' => 'BSHRM (2009 - 2010)',
    ]);

    $duplicate = Course::factory()->create([
        'school_id' => $school->id,
        'code' => 'BSHRM (2009 - 2010)',
    ]);

    $keeperSubjectId = DB::table('subject')->insertGetId([
        'classification' => SubjectEnrolledEnum::INTERNAL->value,
        'code' => 'HOS101',
        'title' => 'Front Office Procedures',
        'units' => 3,
        'lecture' => 2,
        'laboratory' => 1,
        'semester' => 1,
        'course_id' => $survivor->id,
        'school_id' => $school->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $duplicateSubjectId = DB::table('subject')->insertGetId([
        'classification' => SubjectEnrolledEnum::INTERNAL->value,
        'code' => 'HOS101',
        'title' => 'Front Office Procedures',
        'units' => 3,
        'lecture' => 2,
        'laboratory' => 1,
        'semester' => 1,
        'course_id' => $duplicate->id,
        'school_id' => $school->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $prerequisiteId = DB::table('subject')->insertGetId([
        'classification' => SubjectEnrolledEnum::INTERNAL->value,
        'code' => 'HOS102',
        'title' => 'Front Office Procedures II',
        'units' => 3,
        'lecture' => 2,
        'laboratory' => 1,
        'semester' => 2,
        'course_id' => $duplicate->id,
        'school_id' => $school->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    // The prerequisite of the duplicate subject points at the duplicate
    // subject itself, which is folded into the surviving subject.
    DB::table('subject')->where('id', $duplicateSubjectId)->update([
        'pre_riquisite' => json_encode([$prerequisiteId]),
    ]);

    $student = Student::factory()->create([
        'school_id' => $school->id,
        'course_id' => $survivor->id,
    ]);

    $enrollmentId = DB::table('student_enrollment')->insertGetId([
        'student_id' => $student->id,
        'course_id' => $survivor->id,
        'semester' => 1,
        'academic_year' => 1,
        'school_year' => '2026-2027',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $classId = DB::table('classes')->insertGetId([
        'subject_code' => 'HOS101',
        'subject_id' => $duplicateSubjectId,
        'subject_ids' => json_encode([$duplicateSubjectId, $keeperSubjectId]),
        'course_codes' => json_encode([$duplicate->id, $survivor->id]),
        'section' => 'BSHRM-1A',
        'semester' => 1,
        'school_year' => '2026-2027',
        'school_id' => $school->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $subjectEnrollmentId = DB::table('subject_enrollments')->insertGetId([
        'student_id' => $student->id,
        'subject_id' => $duplicateSubjectId,
        'credited_subject_id' => $duplicateSubjectId,
        'enrollment_id' => $enrollmentId,
        'school_year' => '2026-2027',
        'semester' => 1,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $scheduleId = DB::table('schedule')->insertGetId([
        'day_of_week' => 'Monday',
        'start_time' => '08:00',
        'end_time' => '09:00',
        'class_id' => $classId,
        'subject_id' => $duplicateSubjectId,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    mergeDuplicateCoursesMigration()->up();

    expect(DB::table('classes')->where('id', $classId)->value('subject_id'))->toBe($keeperSubjectId)
        ->and(json_decode((string) DB::table('classes')->where('id', $classId)->value('subject_ids'), true))
        ->toBe([$keeperSubjectId])
        ->and(json_decode((string) DB::table('classes')->where('id', $classId)->value('course_codes'), true))
        ->toBe([$survivor->id])
        ->and(DB::table('subject_enrollments')->where('id', $subjectEnrollmentId)->value('subject_id'))->toBe($keeperSubjectId)
        ->and(DB::table('subject_enrollments')->where('id', $subjectEnrollmentId)->value('credited_subject_id'))->toBe($keeperSubjectId)
        ->and(DB::table('schedule')->where('id', $scheduleId)->value('subject_id'))->toBe($keeperSubjectId);

    // The prerequisite of the merged subject is carried over to the subject
    // that survives.
    expect(json_decode((string) DB::table('subject')->where('id', $keeperSubjectId)->value('pre_riquisite'), true))
        ->toBe([$prerequisiteId]);
});

it('changes nothing when it runs again', function (): void {
    $school = School::factory()->create();

    $survivor = Course::factory()->create([
        'school_id' => $school->id,
        'code' => 'BSHRM (2009 - 2010)',
    ]);

    $duplicate = Course::factory()->create([
        'school_id' => $school->id,
        'code' => 'BSHRM (2009 - 2010)',
    ]);

    DB::table('subject')->insert([
        'classification' => SubjectEnrolledEnum::INTERNAL->value,
        'code' => 'NSTP 1',
        'title' => 'National Service Training Program I',
        'units' => 3,
        'lecture' => 2,
        'laboratory' => 1,
        'semester' => 1,
        'course_id' => $duplicate->id,
        'school_id' => $school->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    mergeDuplicateCoursesMigration()->up();

    $courses = DB::table('courses')->orderBy('id')->pluck('id')->all();
    $subjects = DB::table('subject')->orderBy('id')->pluck('id')->all();

    mergeDuplicateCoursesMigration()->up();

    expect(DB::table('courses')->orderBy('id')->pluck('id')->all())->toBe($courses)
        ->and(DB::table('subject')->orderBy('id')->pluck('id')->all())->toBe($subjects)
        ->and($courses)->toBe([$survivor->id]);
});
