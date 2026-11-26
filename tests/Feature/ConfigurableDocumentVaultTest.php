<?php

declare(strict_types=1);

use App\Enums\StudentStatus;
use App\Enums\UserRole;
use App\Models\Course;
use App\Models\GeneralSetting;
use App\Models\School;
use App\Models\Student;
use App\Models\User;
use App\Services\StudentConfigurationService;
use App\Services\TenantContext;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    config(['activitylog.enabled' => false]);
    $this->withoutVite();

    GeneralSetting::factory()->create([
        'semester' => 1,
        'school_starting_date' => '2025-08-01',
        'school_ending_date' => '2026-05-31',
    ]);

    $this->school = School::factory()->create();
    $this->admin = User::factory()->create([
        'role' => UserRole::Developer,
        'school_id' => $this->school->id,
    ]);
    $this->course = Course::factory()->create([
        'school_id' => $this->school->id,
    ]);

    app(TenantContext::class)->setCurrentSchool($this->school);
});

function createVaultStudent(School $school, Course $course, string $email = 'vault.configurable@example.test'): array
{
    $user = User::factory()->create([
        'role' => UserRole::Student,
        'school_id' => $school->id,
        'email' => $email,
    ]);

    $student = Student::factory()->create([
        'school_id' => $school->id,
        'institution_id' => $school->id,
        'user_id' => $user->id,
        'email' => $email,
        'student_id' => '20264001',
        'course_id' => $course->id,
        'document_location_id' => null,
        'status' => StudentStatus::Enrolled,
    ]);

    return [$user, $student];
}

it('uses neutral international default labels on student documents and dashboard', function (): void {
    [$studentUser] = createVaultStudent($this->school, $this->course);

    $this->actingAs($studentUser)
        ->get(route('student.documents.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('student/documents/index', false)
            ->where('summary.uploaded', 0)
            ->where('summary.total', 4)
            ->has('documents', 4)
            ->where('documents.0.key', 'birth_certificate')
            ->where('documents.0.label', 'Birth Certificate / Identity Proof')
            ->where('documents.0.required', false)
            ->where('documents.1.key', 'form_138')
            ->where('documents.1.label', 'Academic Transcript / Report Card')
            ->where('documents.2.key', 'good_moral_cert')
            ->where('documents.2.label', 'Character / Transfer Reference')
            ->where('documents.3.key', 'picture_1x1')
            ->where('documents.3.label', 'ID Photo')
            ->where('documents', function ($documents): bool {
                $serialized = json_encode($documents, JSON_THROW_ON_ERROR);

                return ! str_contains($serialized, 'Form 138')
                    && ! str_contains($serialized, 'PSA / NSO')
                    && ! str_contains($serialized, 'Good Moral');
            }));

    $this->actingAs($studentUser)
        ->get(route('student.dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('student/dashboard', false)
            ->where('student_data.document_summary.uploaded', 0)
            ->where('student_data.document_summary.total', 4)
            ->where('student_data.document_summary.missing', [
                'Birth Certificate / Identity Proof',
                'Academic Transcript / Report Card',
                'Character / Transfer Reference',
                'ID Photo',
            ]));
});

it('persists custom document labels and visibility, hides disabled slots from uploads, and updates dashboard summary', function (): void {
    Storage::fake('private');

    $payload = StudentConfigurationService::defaults();
    $payload['documents']['birth_certificate']['required'] = true;
    $payload['documents']['form_138'] = [
        'label' => 'Official High School Transcript',
        'description' => 'Sealed transcript from your previous secondary school',
        'visible' => true,
        'required' => true,
    ];
    $payload['documents']['good_moral_cert']['visible'] = false;
    $payload['documents']['good_moral_cert']['required'] = false;

    $this->actingAs($this->admin)
        ->put(route('administrators.system-management.student-configuration.update'), $payload)
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    [$studentUser] = createVaultStudent($this->school, $this->course, 'custom.vault@example.test');

    $this->actingAs($studentUser)
        ->get(route('student.documents.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('student/documents/index', false)
            ->where('summary.uploaded', 0)
            ->where('summary.total', 3)
            ->has('documents', 3)
            ->where('documents.0.key', 'birth_certificate')
            ->where('documents.0.required', true)
            ->where('documents.1.key', 'form_138')
            ->where('documents.1.label', 'Official High School Transcript')
            ->where('documents.1.description', 'Sealed transcript from your previous secondary school')
            ->where('documents.1.required', true)
            ->where('documents.2.key', 'picture_1x1'));

    // Hidden slot cannot be uploaded to.
    $this->actingAs($studentUser)
        ->post(route('student.documents.store'), [
            'document_type' => 'good_moral_cert',
            'file' => UploadedFile::fake()->create('reference.pdf', 64, 'application/pdf'),
        ])
        ->assertSessionHasErrors(['document_type']);

    // Dashboard reflects customized checklist before upload.
    $this->actingAs($studentUser)
        ->get(route('student.dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('student/dashboard', false)
            ->where('student_data.document_summary.uploaded', 0)
            ->where('student_data.document_summary.total', 3)
            ->where('student_data.document_summary.missing', [
                'Birth Certificate / Identity Proof',
                'Official High School Transcript',
                'ID Photo',
            ]));

    // Uploading to renamed slot succeeds with dynamic label in flash message.
    $this->actingAs($studentUser)
        ->post(route('student.documents.store'), [
            'document_type' => 'form_138',
            'file' => UploadedFile::fake()->create('transcript.pdf', 80, 'application/pdf'),
        ])
        ->assertRedirect()
        ->assertSessionHas('success', 'Official High School Transcript uploaded successfully.');

    // Dashboard reflects updated completion count.
    $this->actingAs($studentUser)
        ->get(route('student.dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('student/dashboard', false)
            ->where('student_data.document_summary.uploaded', 1)
            ->where('student_data.document_summary.total', 3)
            ->where('student_data.document_summary.missing', [
                'Birth Certificate / Identity Proof',
                'ID Photo',
            ]));
});

it('uses Philippine registrar document labels when the legacy PH preset is enabled', function (): void {
    $legacyConfig = StudentConfigurationService::defaults(legacy: true);

    $this->actingAs($this->admin)
        ->put(route('administrators.system-management.student-configuration.update'), $legacyConfig)
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    [$studentUser] = createVaultStudent($this->school, $this->course, 'legacy.vault@example.test');

    $this->actingAs($studentUser)
        ->get(route('student.documents.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('student/documents/index', false)
            ->where('summary.total', 4)
            ->where('documents.0.label', 'Birth Certificate')
            ->where('documents.0.description', 'PSA / NSO Birth Certificate (clear scan or photo)')
            ->where('documents.1.label', 'Form 138 / Report Card')
            ->where('documents.2.label', 'Good Moral Certificate')
            ->where('documents.3.label', '1x1 ID Photo'));

    $this->actingAs($studentUser)
        ->get(route('student.dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('student/dashboard', false)
            ->where('student_data.document_summary.total', 4)
            ->where('student_data.document_summary.missing', [
                'Birth Certificate',
                'Form 138 / Report Card',
                'Good Moral Certificate',
                '1x1 ID Photo',
            ]));
});

it('rejects marking a hidden document slot as required', function (): void {
    $payload = StudentConfigurationService::defaults();
    $payload['documents']['good_moral_cert'] = [
        'label' => 'Character / Transfer Reference',
        'description' => 'Reference letter',
        'visible' => false,
        'required' => true,
    ];

    $this->actingAs($this->admin)
        ->put(route('administrators.system-management.student-configuration.update'), $payload)
        ->assertSessionHasErrors('documents.good_moral_cert.required');
});

it('falls back to neutral document defaults for legacy DB rows and preserves documents when older clients omit them', function (): void {
    $legacyRow = StudentConfigurationService::defaults();
    unset($legacyRow['documents']);

    DB::table('student_configurations')->upsert([
        [
            'scope' => StudentConfigurationService::scope($this->school->id),
            'school_id' => $this->school->id,
            'configuration' => json_encode($legacyRow, JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ],
    ], ['scope'], ['configuration', 'updated_at']);

    $service = app(StudentConfigurationService::class);
    $service->forget($this->school->id);

    $resolved = $service->get($this->school->id);
    expect($resolved['documents']['form_138']['label'])->toBe('Academic Transcript / Report Card')
        ->and($resolved['documents']['good_moral_cert']['visible'])->toBeTrue();

    // Save custom document label, then send a legacy PUT without the documents key.
    $custom = StudentConfigurationService::defaults();
    $custom['documents']['form_138']['label'] = 'Prior Academic Record';
    $service->update($custom, $this->school->id);

    unset($custom['documents']);

    $this->actingAs($this->admin)
        ->put(route('administrators.system-management.student-configuration.update'), $custom)
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    expect($service->get($this->school->id)['documents']['form_138']['label'])->toBe('Prior Academic Record');
});
