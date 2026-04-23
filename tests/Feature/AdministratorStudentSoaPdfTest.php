<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Jobs\GenerateStudentSoaPdfJob;
use App\Models\Course;
use App\Models\School;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\User;
use Illuminate\Support\Facades\Bus;

use function Pest\Laravel\actingAs;

it('queues administrator student SOA PDF generation', function (): void {
    Bus::fake();

    School::factory()->create();

    $admin = User::factory()->create(['role' => UserRole::Admin->value]);
    $course = Course::factory()->create();
    $student = Student::factory()->create([
        'course_id' => $course->id,
        'student_id' => '20260001',
    ]);

    $enrollment = StudentEnrollment::factory()->create([
        'student_id' => $student->id,
        'course_id' => $course->id,
        'semester' => 1,
        'school_year' => '2025 - 2026',
        'academic_year' => 1,
    ]);

    App\Models\StudentTuition::query()->create([
        'student_id' => $student->id,
        'enrollment_id' => $enrollment->id,
        'semester' => 1,
        'school_year' => '2025 - 2026',
        'academic_year' => 1,
        'total_lectures' => 15000,
        'total_laboratory' => 5000,
        'total_miscelaneous_fees' => 3000,
        'total_tuition' => 20000,
        'overall_tuition' => 23000,
        'downpayment' => 5000,
        'discount' => 0,
        'total_balance' => 18000,
        'status' => 'pending',
    ]);

    $response = actingAs($admin)->getJson(route('administrators.students.tuition.soa', [
        'student' => $student->id,
        'school_year' => '2025 - 2026',
        'semester' => 1,
    ]));

    $response->assertAccepted()
        ->assertJsonPath('message', 'SOA PDF generation queued. You will be notified when the file is ready.');

    Bus::assertDispatched(GenerateStudentSoaPdfJob::class);
});

it('queues SOA PDF generation even when tuition data is missing', function (): void {
    Bus::fake();

    School::factory()->create();

    $admin = User::factory()->create(['role' => UserRole::Admin->value]);
    $course = Course::factory()->create();
    $student = Student::factory()->create([
        'course_id' => $course->id,
        'student_id' => '20260002',
    ]);

    $response = actingAs($admin)->getJson(route('administrators.students.tuition.soa', [
        'student' => $student->id,
        'school_year' => '2025 - 2026',
        'semester' => 2,
    ]));

    $response->assertAccepted()
        ->assertJsonPath('message', 'SOA PDF generation queued. You will be notified when the file is ready.');

    Bus::assertDispatched(GenerateStudentSoaPdfJob::class);
});
