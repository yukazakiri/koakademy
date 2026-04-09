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

    $payload = [
        'lrn' => '32423412312',
        'last_name' => 'Louis',
        'first_name' => 'Lukkanit',
        'middle_name' => 'A',
        'contact' => '09600179497',
        'strand_id' => $strand->id,
        'grade_level' => '11',
        'enroll_in_class' => true,
    ];

    $this->actingAs($user)
        ->post(route('classes.students.store-shs', ['class' => $class->id]), $payload)
        ->assertRedirect();

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

    expect($student->status)->toBe(StudentStatus::Enrolled);
});
