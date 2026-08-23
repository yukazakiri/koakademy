<?php

declare(strict_types=1);

use App\Enums\StudentStatus;
use App\Enums\SubjectEnrolledEnum;
use App\Enums\UserRole;
use App\Models\School;
use App\Models\Student;
use App\Models\StudentClearance;
use App\Models\StudentEnrollment;
use App\Models\StudentStatusRecord;
use App\Models\Subject;
use App\Models\SubjectEnrollment;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\withoutVite;

beforeEach(function (): void {
    School::factory()->create();
});

it('rejects non-consecutive graduation academic years', function (): void {
    $user = User::factory()->create(['role' => UserRole::Admin]);
    $student = Student::factory()->create();

    actingAs($user)
        ->patch(route('administrators.students.update-status', $student), [
            'status' => StudentStatus::Graduated->value,
            'graduation_school_year' => '2026 - 2028',
        ])
        ->assertSessionHasErrors('graduation_school_year');
});

it('updates an existing historical subject enrollment', function (): void {
    $user = User::factory()->create(['role' => UserRole::Admin]);
    $student = Student::factory()->create();
    $subject = Subject::factory()->create();

    $studentEnrollmentPast = StudentEnrollment::factory()->create([
        'student_id' => $student->id,
        'school_year' => '2022 - 2023',
        'semester' => 1,
    ]);

    // Create a past enrollment
    $pastEnrollment = SubjectEnrollment::create([
        'student_id' => $student->id,
        'enrollment_id' => $studentEnrollmentPast->id,
        'subject_id' => $subject->id,
        'grade' => 74.0,
        'academic_year' => 1,
        'school_year' => '2022 - 2023',
        'semester' => 1,
        'classification' => SubjectEnrolledEnum::INTERNAL->value,
    ]);

    $studentEnrollmentCurrent = StudentEnrollment::factory()->create([
        'student_id' => $student->id,
        'school_year' => '2023 - 2024',
        'semester' => 1,
    ]);

    // Create a current enrollment
    $currentEnrollment = SubjectEnrollment::create([
        'student_id' => $student->id,
        'enrollment_id' => $studentEnrollmentCurrent->id,
        'subject_id' => $subject->id,
        'grade' => null,
        'academic_year' => 2,
        'school_year' => '2023 - 2024',
        'semester' => 1,
        'classification' => SubjectEnrolledEnum::INTERNAL->value,
    ]);

    // Ensure we are testing exactly 2 exist
    expect(SubjectEnrollment::where('student_id', $student->id)->count())->toBe(2);

    actingAs($user)
        ->patch(route('administrators.students.subjects.update-grade', ['student' => $student->id, 'subject' => $subject->id]), [
            'enrollment_record_id' => $pastEnrollment->id,
            'is_new_record' => false,
            'grade' => 75.0, // Changing past grade
            'remarks' => 'Passed on appeal',
            'classification' => SubjectEnrolledEnum::INTERNAL->value,
            'academic_year' => 1,
            'school_year' => '2022 - 2023',
            'semester' => 1,
        ])
        ->assertRedirect();

    // Verify
    expect($pastEnrollment->refresh()->grade)->toBe(75.0)
        ->and($pastEnrollment->refresh()->remarks)->toBe('Passed on appeal')
        ->and($currentEnrollment->refresh()->grade)->toBeNull();
});

it('creates a new historical subject enrollment if is_new_record is true', function (): void {
    $user = User::factory()->create(['role' => UserRole::Admin]);
    $student = Student::factory()->create();
    $subject = Subject::factory()->create();

    // Initially 0 enrollments
    expect(SubjectEnrollment::where('student_id', $student->id)->count())->toBe(0);

    actingAs($user)
        ->patch(route('administrators.students.subjects.update-grade', ['student' => $student->id, 'subject' => $subject->id]), [
            'is_new_record' => true,
            'grade' => 74.0,
            'remarks' => 'Failed take 1',
            'classification' => SubjectEnrolledEnum::INTERNAL->value,
            'academic_year' => 1,
            'school_year' => '2022 - 2023',
            'semester' => 1,
        ])
        ->assertRedirect();

    expect(SubjectEnrollment::where('student_id', $student->id)->where('subject_id', $subject->id)->count())->toBe(1);

    $enrollment = SubjectEnrollment::where('student_id', $student->id)->first();
    expect($enrollment->grade)->toBe(74.0)
        ->and($enrollment->remarks)->toBe('Failed take 1');
});

it('creates non credited historical records without attaching them to the clicked curriculum subject', function (): void {
    $user = User::factory()->create(['role' => UserRole::Admin]);
    $student = Student::factory()->create();
    $subject = Subject::factory()->create([
        'academic_year' => 1,
        'semester' => 1,
        'course_id' => $student->course_id,
    ]);

    actingAs($user)
        ->patch(route('administrators.students.subjects.update-grade', ['student' => $student->id, 'subject' => $subject->id]), [
            'is_new_record' => true,
            'grade' => 88,
            'remarks' => 'Taken externally with no equivalent',
            'classification' => SubjectEnrolledEnum::NON_CREDITED->value,
            'school_name' => 'External Academy',
            'external_subject_code' => 'EXT-101',
            'external_subject_title' => 'External Logic',
            'external_subject_units' => 3,
            'academic_year' => 1,
            'school_year' => '2022 - 2023',
            'semester' => 1,
        ])
        ->assertRedirect();

    $enrollment = SubjectEnrollment::query()->where('student_id', $student->id)->sole();

    expect($enrollment->classification)->toBe(SubjectEnrolledEnum::NON_CREDITED->value)
        ->and($enrollment->subject_id)->toBeNull()
        ->and($enrollment->credited_subject_id)->toBeNull()
        ->and($enrollment->external_subject_code)->toBe('EXT-101')
        ->and($enrollment->external_subject_title)->toBe('External Logic');
});

it('shows standalone non credited records separately from the checklist payload', function (): void {
    $user = User::factory()->create(['role' => UserRole::Admin]);
    $student = Student::factory()->create();
    $studentEnrollment = StudentEnrollment::factory()->create([
        'student_id' => $student->id,
        'school_year' => '2023 - 2024',
        'semester' => 1,
    ]);
    $historicalEnrollment = StudentEnrollment::factory()->create([
        'student_id' => $student->id,
        'school_year' => '2022 - 2023',
        'semester' => 1,
    ]);
    $curriculumSubject = Subject::factory()->create([
        'academic_year' => 1,
        'semester' => 1,
        'course_id' => $student->course_id,
        'code' => 'MATH101',
        'title' => 'College Algebra',
    ]);

    SubjectEnrollment::create([
        'student_id' => $student->id,
        'enrollment_id' => $studentEnrollment->id,
        'subject_id' => $curriculumSubject->id,
        'grade' => 85,
        'academic_year' => 1,
        'school_year' => '2023 - 2024',
        'semester' => 1,
        'classification' => SubjectEnrolledEnum::INTERNAL->value,
    ]);

    SubjectEnrollment::create([
        'student_id' => $student->id,
        'enrollment_id' => $historicalEnrollment->id,
        'subject_id' => null,
        'grade' => 90,
        'remarks' => 'No equivalent subject',
        'academic_year' => 1,
        'school_year' => '2022 - 2023',
        'semester' => 1,
        'classification' => SubjectEnrolledEnum::NON_CREDITED->value,
        'school_name' => 'External Academy',
        'external_subject_code' => 'EXT-101',
        'external_subject_title' => 'External Logic',
        'external_subject_units' => 3,
    ]);

    actingAs($user)
        ->get(route('administrators.students.show', $student->id))
        ->assertSuccessful()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->component('administrators/students/show', false)
            ->has('student.non_credited_subjects', 1)
            ->where('student.non_credited_subjects.0.external_subject_code', 'EXT-101')
            ->where('student.non_credited_subjects.0.linked_subject', null)
            ->where('student.checklist.0.semesters.0.subjects.0.code', 'MATH101'));
});

it('stores student signature on the default filesystem and returns a show-page signature url', function (): void {
    $defaultDisk = config('filesystems.default');

    if (! is_string($defaultDisk)) {
        throw new RuntimeException('The default filesystem disk must be a string.');
    }

    Storage::fake($defaultDisk);

    $user = User::factory()->create(['role' => UserRole::Admin]);
    $student = Student::factory()->create();
    $signatureFile = UploadedFile::fake()->image('signature.png', 700, 200);

    actingAs($user)
        ->post(route('administrators.students.signature.update', $student->id), [
            'signature' => $signatureFile,
        ])
        ->assertRedirect();

    $student->refresh();

    expect($student->signature_path)->not->toBeNull();
    Storage::disk($defaultDisk)->assertExists((string) $student->signature_path);

    actingAs($user)
        ->get(route('administrators.students.show', $student->id))
        ->assertSuccessful()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->component('administrators/students/show', false)
            ->where('student.signature_url', Storage::disk($defaultDisk)->url((string) $student->signature_path)));
});

it('soft deletes a student via the destroy endpoint', function (): void {
    config(['activitylog.enabled' => false]);

    $user = User::factory()->create(['role' => UserRole::Admin]);
    $student = Student::factory()->create();

    actingAs($user)
        ->delete(route('administrators.students.destroy', $student))
        ->assertRedirect();

    expect(Student::query()->whereKey($student->id)->exists())->toBeFalse();
    expect(Student::withTrashed()->whereKey($student->id)->first()?->trashed())->toBeTrue();
});

it('restores a soft-deleted student via the restore endpoint', function (): void {
    config(['activitylog.enabled' => false]);

    $user = User::factory()->create(['role' => UserRole::Admin]);
    $student = Student::factory()->create();
    $student->delete();

    expect(Student::query()->whereKey($student->id)->exists())->toBeFalse();

    actingAs($user)
        ->post(route('administrators.students.restore', $student->id))
        ->assertRedirect();

    expect(Student::query()->whereKey($student->id)->exists())->toBeTrue()
        ->and(Student::query()->whereKey($student->id)->first()?->trashed())->toBeFalse();
});

it('force deletes a student and all of their related records', function (): void {
    config(['activitylog.enabled' => false]);

    $user = User::factory()->create(['role' => UserRole::Admin]);
    $student = Student::factory()->create();
    $studentId = $student->id;

    $enrollment = StudentEnrollment::factory()->create(['student_id' => $studentId]);
    $subjectEnrollment = SubjectEnrollment::query()->create([
        'student_id' => $studentId,
        'subject_id' => Subject::factory()->create()->id,
        'enrollment_id' => $enrollment->id,
        'school_year' => '2026 - 2027',
        'semester' => 1,
        'classification' => 'credited',
    ]);
    StudentClearance::query()->create([
        'student_id' => $studentId,
        'academic_year' => '2026 - 2027',
        'semester' => 1,
        'is_cleared' => true,
    ]);
    StudentStatusRecord::query()->create([
        'student_id' => $studentId,
        'academic_year' => '2026 - 2027',
        'semester' => 1,
        'status' => StudentStatus::Enrolled->value,
    ]);

    expect(StudentEnrollment::query()->where('student_id', $studentId)->exists())->toBeTrue();
    expect(SubjectEnrollment::query()->where('student_id', $studentId)->exists())->toBeTrue();

    actingAs($user)
        ->delete(route('administrators.students.force-destroy', $studentId))
        ->assertRedirect();

    expect(Student::withTrashed()->whereKey($studentId)->exists())->toBeFalse()
        ->and(StudentEnrollment::withTrashed()->whereKey($enrollment->id)->exists())->toBeFalse()
        ->and(SubjectEnrollment::query()->whereKey($subjectEnrollment->id)->exists())->toBeFalse()
        ->and(StudentClearance::query()->where('student_id', $studentId)->exists())->toBeFalse()
        ->and(StudentStatusRecord::query()->where('student_id', $studentId)->exists())->toBeFalse();
});

it('shows the trashed students list when the trashed filter is set', function (): void {
    config(['activitylog.enabled' => false, 'inertia.testing.ensure_pages_exist' => false]);
    withoutVite();

    $user = User::factory()->create(['role' => UserRole::Admin]);
    $active = Student::factory()->create(['first_name' => 'Active', 'last_name' => 'One']);
    $trashed = Student::factory()->create(['first_name' => 'Trashed', 'last_name' => 'Two']);
    $trashed->delete();

    actingAs($user)
        ->get(portalUrlForAdministrators('/administrators/students?trashed=trashed'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('administrators/students/index', false)
            ->where('students.data.0.id', $trashed->id)
            ->where('students.data.0.deleted_at', fn ($value) => $value !== null)
            ->where('filters.trashed', 'trashed')
        );

    actingAs($user)
        ->get(portalUrlForAdministrators('/administrators/students'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('administrators/students/index', false)
            ->where('students.data.0.id', $active->id)
            ->where('students.data.0.deleted_at', null)
        );
});

it('renders the show page for a soft-deleted student', function (): void {
    config(['activitylog.enabled' => false, 'inertia.testing.ensure_pages_exist' => false]);
    withoutVite();

    $user = User::factory()->create(['role' => UserRole::Admin]);
    $student = Student::factory()->create();
    $student->delete();

    actingAs($user)
        ->get(route('administrators.students.show', $student->id))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('administrators/students/show', false)
            ->where('student.is_trashed', true)
            ->where('student.deleted_at', fn ($value) => $value !== null)
        );
});

it('permanently deletes multiple students via the bulk force-destroy endpoint when the confirmation text matches', function (): void {
    config(['activitylog.enabled' => false]);

    $user = User::factory()->create(['role' => UserRole::Admin]);
    $studentOne = Student::factory()->create(['first_name' => 'Alpha', 'last_name' => 'One']);
    $studentTwo = Student::factory()->create(['first_name' => 'Bravo', 'last_name' => 'Two']);
    $studentOneEnrollment = StudentEnrollment::factory()->create(['student_id' => $studentOne->id]);
    $studentTwoEnrollment = StudentEnrollment::factory()->create(['student_id' => $studentTwo->id]);

    actingAs($user)
        ->delete(route('administrators.students.bulk-force-destroy'), [
            'student_ids' => [$studentOne->id, $studentTwo->id],
            'confirm_text' => 'PERMANENTLY DELETE 2 STUDENTS',
        ])
        ->assertRedirect(route('administrators.students.index'));

    expect(Student::withTrashed()->whereIn('id', [$studentOne->id, $studentTwo->id])->exists())->toBeFalse()
        ->and(StudentEnrollment::withTrashed()->whereIn('id', [$studentOneEnrollment->id, $studentTwoEnrollment->id])->exists())->toBeFalse();
});

it('rejects the bulk force-destroy endpoint when the confirmation text does not match', function (): void {
    config(['activitylog.enabled' => false]);

    $user = User::factory()->create(['role' => UserRole::Admin]);
    $student = Student::factory()->create();

    actingAs($user)
        ->delete(route('administrators.students.bulk-force-destroy'), [
            'student_ids' => [$student->id],
            'confirm_text' => 'not the right phrase',
        ])
        ->assertSessionHasErrors('confirm_text')
        ->assertRedirect();

    expect(Student::query()->whereKey($student->id)->exists())->toBeTrue();
});
