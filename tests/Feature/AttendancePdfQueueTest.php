<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Http\Middleware\FacultyIdValidationMiddleware;
use App\Jobs\GenerateAttendancePdfJob;
use App\Models\ClassEnrollment;
use App\Models\Classes;
use App\Models\Faculty;
use App\Models\Student;
use App\Models\User;
use Illuminate\Support\Facades\Bus;

use function Pest\Laravel\withoutMiddleware;

it('queues attendance PDF generation instead of rendering in-request', function (): void {
    withoutMiddleware(FacultyIdValidationMiddleware::class);
    cache()->flush();

    Bus::fake();

    $facultyEmail = 'faculty-attendance-'.uniqid().'@example.com';

    $user = User::factory()->create([
        'role' => UserRole::Instructor->value,
        'email' => $facultyEmail,
        'email_verified_at' => now(),
    ]);

    $faculty = Faculty::factory()->createOne([
        'email' => $user->email,
    ]);

    $class = Classes::factory()->createOne([
        'faculty_id' => $faculty->id,
        'semester' => 1,
        'school_year' => '2025-2026',
        'classification' => 'college',
    ]);

    $student = Student::factory()->create();

    ClassEnrollment::factory()->create([
        'class_id' => $class->id,
        'student_id' => $student->id,
        'status' => true,
    ]);

    $response = $this->actingAs($user)
        ->getJson(portalUrlForAdministrators('/faculty/classes/'.$class->id.'/attendance/export?format=pdf'));

    $response->assertAccepted()
        ->assertJsonPath('message', 'Attendance PDF export started. You will receive a notification once the file is ready.');

    Bus::assertDispatched(GenerateAttendancePdfJob::class);
});
