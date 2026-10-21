<?php

declare(strict_types=1);

use App\Enums\StudentStatus;
use App\Enums\UserRole;
use App\Models\Course;
use App\Models\Department;
use App\Models\EnrollmentRequirement;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->withoutVite();
});

/**
 * Build an applicant with a given set of requirement states.
 *
 * @param  array<int, array{key: string, status: string, evidence: ?string}>  $requirements
 */
function makeApplicant(array $requirements = [], array $overrides = []): Student
{
    $applicant = Student::factory()->create(array_merge([
        'status' => StudentStatus::Applicant->value,
        'student_type' => 'college',
        'scholarship_type' => null,
    ], $overrides));

    if ($requirements === []) {
        return $applicant;
    }

    $enrollment = StudentEnrollment::factory()->create([
        'student_id' => $applicant->id,
        'course_id' => $applicant->course_id,
    ]);

    foreach ($requirements as $requirement) {
        EnrollmentRequirement::create([
            'student_enrollment_id' => $enrollment->id,
            'requirement_key' => $requirement['key'],
            'label' => ucwords(str_replace('_', ' ', $requirement['key'])),
            'is_required' => true,
            'status' => $requirement['status'],
            'evidence_path' => $requirement['evidence'],
            'evidence' => $requirement['evidence'] !== null
                ? ['path' => $requirement['evidence'], 'uploaded_at' => '2026-01-01 09:00:00']
                : null,
        ]);
    }

    return $applicant;
}

it('renders the admissions inbox with stage facets and filter options', function (): void {
    $admin = User::factory()->create(['role' => UserRole::Admin]);

    $this->actingAs($admin)
        ->get(portalUrlForAdministrators('/administrators/enrollments/applicants'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->component('administrators/enrollments/applicants', false)
            ->has('applicants.data')
            ->has('stats.total')
            ->has('stats.awaiting_documents')
            ->has('stage_counts')
            ->has('stage_meta')
            ->has('filter_options.departments')
            ->has('filter_options.courses')
            ->has('filter_options.student_types')
            ->has('filter_options.scholarships')
            ->has('enrollment_pipeline.status_classes')
            ->has('filters.search')
            ->has('filters.stage')
        );
});

it('derives the applicant stage from the submitted requirement evidence', function (): void {
    $admin = User::factory()->create(['role' => UserRole::Admin]);

    makeApplicant([], ['first_name' => 'Manual', 'last_name' => 'Applicant']);
    makeApplicant([
        ['key' => 'birth_certificate', 'status' => 'pending', 'evidence' => null],
        ['key' => 'report_card', 'status' => 'pending', 'evidence' => 'enrollment-documents/1/report.pdf'],
    ], ['first_name' => 'Awaiting', 'last_name' => 'Applicant']);
    makeApplicant([
        ['key' => 'birth_certificate', 'status' => 'pending', 'evidence' => 'enrollment-documents/1/birth.pdf'],
        ['key' => 'report_card', 'status' => 'pending', 'evidence' => 'enrollment-documents/1/report.pdf'],
    ], ['first_name' => 'Ready', 'last_name' => 'Applicant']);
    makeApplicant([
        ['key' => 'birth_certificate', 'status' => 'verified', 'evidence' => 'enrollment-documents/1/birth.pdf'],
        ['key' => 'report_card', 'status' => 'waived', 'evidence' => 'enrollment-documents/1/report.pdf'],
    ], ['first_name' => 'Reviewed', 'last_name' => 'Applicant']);

    $this->actingAs($admin)
        ->get(portalUrlForAdministrators('/administrators/enrollments/applicants'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('stats.manual', 1)
            ->where('stats.awaiting_documents', 1)
            ->where('stats.ready_for_review', 1)
            ->where('stats.reviewed', 1)
            ->where('stage_counts.manual', 1)
            ->where('stage_counts.awaiting_documents', 1)
            ->where('stage_counts.ready_for_review', 1)
            ->where('stage_counts.reviewed', 1)
        );
});

it('filters applicants by stage, program, student type, scholarship and search term', function (): void {
    $admin = User::factory()->create(['role' => UserRole::Admin]);

    $department = Department::factory()->create(['code' => 'IT', 'name' => 'Information Technology', 'is_active' => true]);
    $course = Course::factory()->create(['code' => 'BSCS', 'title' => 'Bachelor of Science in Computer Science', 'department_id' => $department->id, 'is_active' => true]);
    $otherCourse = Course::factory()->create(['code' => 'BSA', 'title' => 'Bachelor of Science in Accountancy', 'department_id' => $department->id, 'is_active' => true]);

    $target = makeApplicant([
        ['key' => 'birth_certificate', 'status' => 'pending', 'evidence' => 'enrollment-documents/1/birth.pdf'],
    ], [
        'first_name' => 'Juan',
        'last_name' => 'Delacruz',
        'email' => 'juan.delacruz@koakademy.test',
        'course_id' => $course->id,
        'student_type' => 'shs',
        'scholarship_type' => 'tes',
    ]);

    makeApplicant([], [
        'first_name' => 'Maria',
        'last_name' => 'Santos',
        'email' => 'maria.santos@koakademy.test',
        'course_id' => $otherCourse->id,
    ]);

    $this->actingAs($admin)
        ->get(portalUrlForAdministrators('/administrators/enrollments/applicants?search=Delacruz'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('applicants.total', 1)
            ->where('applicants.data.0.id', $target->id)
            ->where('applicants.data.0.stage', 'ready_for_review')
            ->where('applicants.data.0.scholarship_label', 'TES (Tertiary Education Subsidy)')
            ->where('applicants.data.0.student_type_label', 'Senior High School Student')
            ->where('applicants.data.0.department', 'IT')
            ->where('applicants.data.0.email', $target->email)
            ->where('applicants.data.0.phone', $target->phone)
        );

    $this->actingAs($admin)
        ->get(portalUrlForAdministrators('/administrators/enrollments/applicants?stage=ready_for_review'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('applicants.total', 1)
            ->where('applicants.data.0.id', $target->id)
        );

    $this->actingAs($admin)
        ->get(portalUrlForAdministrators('/administrators/enrollments/applicants?student_type=shs&scholarship=tes'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('applicants.total', 1)
            ->where('applicants.data.0.id', $target->id)
        );

    $this->actingAs($admin)
        ->get(portalUrlForAdministrators('/administrators/enrollments/applicants?department=IT&course=BSCS'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('applicants.total', 1)
            ->where('applicants.data.0.id', $target->id)
        );
});

it('hides soft deleted applicants unless they are explicitly requested', function (): void {
    $admin = User::factory()->create(['role' => UserRole::Admin]);

    $active = makeApplicant([], ['first_name' => 'Active', 'last_name' => 'Applicant']);
    $deleted = makeApplicant([], ['first_name' => 'Removed', 'last_name' => 'Applicant']);
    $deleted->delete();

    $this->actingAs($admin)
        ->get(portalUrlForAdministrators('/administrators/enrollments/applicants'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('applicants.total', 1)
            ->where('applicants.data.0.id', $active->id)
            ->where('stats.deleted', 1)
        );

    $this->actingAs($admin)
        ->get(portalUrlForAdministrators('/administrators/enrollments/applicants?include_deleted=1'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('applicants.total', 2)
        );
});

it('separates applicants without a scholarship from those holding one', function (): void {
    $admin = User::factory()->create(['role' => UserRole::Admin]);

    makeApplicant([], ['first_name' => 'Scholar', 'last_name' => 'One', 'scholarship_type' => 'tes']);
    makeApplicant([], ['first_name' => 'Plain', 'last_name' => 'Two', 'scholarship_type' => null]);

    $this->actingAs($admin)
        ->get(portalUrlForAdministrators('/administrators/enrollments/applicants?scholarship=tes'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('applicants.total', 1)
            ->where('applicants.data.0.scholarship_type', 'tes')
            ->where('applicants.data.0.scholarship_label', 'TES (Tertiary Education Subsidy)')
            ->where('stats.with_scholarship', 1)
        );

    $this->actingAs($admin)
        ->get(portalUrlForAdministrators('/administrators/enrollments/applicants?scholarship=none'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('applicants.total', 1)
            ->where('applicants.data.0.scholarship_type', null)
            ->where('applicants.data.0.scholarship_label', null)
        );
});

it('keeps the stage facets in sync with the other active filters', function (): void {
    $admin = User::factory()->create(['role' => UserRole::Admin]);

    $department = Department::factory()->create(['code' => 'IT', 'name' => 'Information Technology', 'is_active' => true]);
    $course = Course::factory()->create(['code' => 'BSIT', 'title' => 'BSIT', 'department_id' => $department->id, 'is_active' => true]);
    $otherCourse = Course::factory()->create(['code' => 'BSA', 'title' => 'BSA', 'department_id' => $department->id, 'is_active' => true]);

    makeApplicant([
        ['key' => 'birth_certificate', 'status' => 'pending', 'evidence' => 'enrollment-documents/1/birth.pdf'],
    ], ['first_name' => 'Target', 'last_name' => 'One', 'course_id' => $course->id]);

    makeApplicant([
        ['key' => 'birth_certificate', 'status' => 'pending', 'evidence' => 'enrollment-documents/2/birth.pdf'],
    ], ['first_name' => 'Other', 'last_name' => 'Two', 'course_id' => $otherCourse->id]);

    $this->actingAs($admin)
        ->get(portalUrlForAdministrators('/administrators/enrollments/applicants?course=BSIT'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('applicants.total', 1)
            ->where('stage_counts.ready_for_review', 1)
            ->where('stage_counts.awaiting_documents', 0)
            ->where('stage_counts.reviewed', 0)
            ->where('stage_counts.manual', 0)
        );
});

it('still exposes the requirement readiness of a deleted applicant when requested', function (): void {
    $admin = User::factory()->create(['role' => UserRole::Admin]);

    $applicant = makeApplicant([
        ['key' => 'birth_certificate', 'status' => 'pending', 'evidence' => 'enrollment-documents/1/birth.pdf'],
    ]);
    $applicant->delete();

    $this->actingAs($admin)
        ->get(portalUrlForAdministrators('/administrators/enrollments/applicants?include_deleted=1'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('applicants.total', 1)
            ->where('applicants.data.0.is_trashed', true)
            ->where('applicants.data.0.requirements_total', 1)
            ->where('applicants.data.0.requirements_submitted', 1)
            ->where('applicants.data.0.stage', 'ready_for_review')
        );
});

it('exposes the deleted queue through its own stage', function (): void {
    $admin = User::factory()->create(['role' => UserRole::Admin]);

    makeApplicant([], ['first_name' => 'Live', 'last_name' => 'Applicant']);
    $removed = makeApplicant([], ['first_name' => 'Removed', 'last_name' => 'Applicant']);
    $removed->delete();

    $this->actingAs($admin)
        ->get(portalUrlForAdministrators('/administrators/enrollments/applicants?stage=deleted'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('applicants.total', 1)
            ->where('applicants.data.0.id', $removed->id)
            ->where('applicants.data.0.is_trashed', true)
            ->where('stage_counts.deleted', 1)
            ->where('stage_counts.all', 1)
        );
});

it('returns the applicant dossier for the review drawer', function (): void {
    $admin = User::factory()->create(['role' => UserRole::Admin]);

    $applicant = makeApplicant([
        ['key' => 'birth_certificate', 'status' => 'pending', 'evidence' => 'enrollment-documents/9/birth.pdf'],
    ]);

    $this->actingAs($admin)
        ->get(portalUrlForAdministrators("/administrators/enrollments/applicants/{$applicant->id}"))
        ->assertOk()
        ->assertJsonPath('applicant.id', $applicant->id)
        ->assertJsonPath('applicant.email', $applicant->email)
        ->assertJsonPath('requirements.0.key', 'birth_certificate')
        ->assertJsonPath('requirements.0.status', 'pending')
        ->assertJsonPath('documents.0.path', 'enrollment-documents/9/birth.pdf');
});

it('serves an uploaded requirement document to administrators', function (): void {
    $admin = User::factory()->create(['role' => UserRole::Admin]);

    Storage::fake('private');
    Storage::disk('private')->put('enrollment-documents/9/birth.pdf', 'pdf-bytes');

    $applicant = makeApplicant([
        ['key' => 'birth_certificate', 'status' => 'pending', 'evidence' => 'enrollment-documents/9/birth.pdf'],
    ]);

    $requirement = EnrollmentRequirement::query()->where('requirement_key', 'birth_certificate')->firstOrFail();

    $this->actingAs($admin)
        ->get(portalUrlForAdministrators("/administrators/enrollments/applicants/{$applicant->id}/documents/{$requirement->id}"))
        ->assertOk()
        ->assertHeader('content-disposition', 'attachment; filename=birth.pdf');
});
