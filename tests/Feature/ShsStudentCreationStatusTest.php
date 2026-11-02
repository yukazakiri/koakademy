<?php

declare(strict_types=1);

use App\Enums\StudentStatus;
use App\Enums\StudentType;
use App\Enums\UserRole;
use App\Models\Classes;
use App\Models\Faculty;
use App\Models\ShsStrand;
use App\Models\Student;
use App\Models\User;
use Illuminate\Support\Facades\Schema;

beforeEach(function (): void {
    config()->set('activitylog.enabled', false);

    if (! Schema::hasTable('shs_students') || ! Schema::hasTable('shs_strands')) {
        $this->markTestSkipped('SHS tables are not available on this test database connection.');
    }
});

it('creates an SHS student with enrolled status', function (): void {
    $user = User::factory()->create([
        'role' => UserRole::Instructor,
        'faculty_id_number' => 'FAC-TEST-001',
    ]);

    $faculty = Faculty::factory()->create([
        'email' => $user->email,
    ]);

    $strand = ShsStrand::factory()->create();

    $class = Classes::factory()->create([
        'faculty_id' => $faculty->id,
        'classification' => 'shs',
        'shs_track_id' => $strand->track_id,
        'shs_strand_id' => $strand->id,
        'grade_level' => 'Grade 11',
    ]);

    app(App\Services\StudentConfigurationService::class)->update(App\Services\StudentConfigurationService::defaults(legacy: true), $class->school_id);
    app(App\Services\TenantContext::class)->setCurrentSchool($class->school);

    $payload = [
        'lrn' => '32423412312',
        'last_name' => 'Louis',
        'first_name' => 'Lukkanit',
        'middle_name' => 'A',
        'birth_date' => '2008-01-15',
        'gender' => 'male',
        'contact' => '09600179497',
        'strand_id' => $strand->id,
        'grade_level' => '11',
        'enroll_in_class' => true,
    ];

    $this->actingAs($user)
        ->post(route('faculty.classes.students.store-shs', ['class' => $class->id]), $payload)
        ->assertRedirect()->assertSessionHasNoErrors();

    $this->assertDatabaseHas('students', [
        'lrn' => $payload['lrn'],
        'student_type' => StudentType::SeniorHighSchool->value,
        'status' => StudentStatus::Enrolled->value,
    ]);

    $this->assertDatabaseHas('shs_students', [
        'student_lrn' => $payload['lrn'],
    ]);

    $student = Student::query()->where('lrn', $payload['lrn'])->firstOrFail();

    $this->assertDatabaseHas('class_enrollments', [
        'class_id' => $class->id,
        'student_id' => $student->id,
        'status' => true,
    ]);

    expect($student->status)->toBe(StudentStatus::Enrolled)->and($student->student_id)->toBe($payload['lrn']);
});

it('does not create a faculty SHS student when the school requires an unsupported institution field', function (): void {
    $user = User::factory()->create(['role' => UserRole::Instructor, 'faculty_id_number' => 'FAC-TEST-002']);
    $faculty = Faculty::factory()->create(['email' => $user->email]);
    $strand = ShsStrand::factory()->create();
    $class = Classes::factory()->create(['faculty_id' => $faculty->id, 'classification' => 'shs', 'shs_track_id' => $strand->track_id, 'shs_strand_id' => $strand->id]);
    $settings = App\Services\StudentConfigurationService::defaults(legacy: true);
    $settings['custom_fields'] = [['key' => 'custom_1', 'label' => 'Cohort', 'type' => 'text', 'active' => true, 'required' => true, 'options' => []]];
    app(App\Services\StudentConfigurationService::class)->update($settings, $class->school_id);
    app(App\Services\TenantContext::class)->setCurrentSchool($class->school);
    $this->actingAs($user)->post(route('faculty.classes.students.store-shs', ['class' => $class->id]), [
        'lrn' => '32423412313', 'last_name' => 'Example', 'first_name' => 'Alex', 'birth_date' => '2008-01-15',
        'gender' => 'male', 'strand_id' => $strand->id, 'grade_level' => '11',
    ])->assertSessionHasErrors('custom_fields.custom_1');
    $this->assertDatabaseMissing('students', ['lrn' => '32423412313']);
    $this->assertDatabaseMissing('shs_students', ['student_lrn' => '32423412313']);
});
