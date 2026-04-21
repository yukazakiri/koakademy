<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\Course;
use App\Models\School;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\User;
use App\Services\PdfGenerationService;

use function Pest\Laravel\actingAs;

it('serves administrator student SOA as an inline PDF', function (): void {
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

    app()->instance(PdfGenerationService::class, new class
    {
        /**
         * @param  array<string, mixed>  $data
         * @param  array<string, mixed>  $options
         */
        public function generatePdfFromView(string $viewName, array $data, string $outputPath, array $options = []): void
        {
            file_put_contents($outputPath, '%PDF-1.4 fake');
        }
    });

    $response = actingAs($admin)->get(route('administrators.students.tuition.soa', [
        'student' => $student->id,
        'school_year' => '2025 - 2026',
        'semester' => 1,
    ]));

    $response->assertSuccessful();

    $contentType = (string) $response->headers->get('content-type');
    $contentDisposition = (string) $response->headers->get('content-disposition');

    expect($contentType)->toContain('application/pdf')
        ->and($contentDisposition)->toContain('inline;')
        ->and($contentDisposition)->toContain('.pdf');
});

it('falls back to native SOA PDF generation when chrome-based rendering is unavailable', function (): void {
    School::factory()->create();

    $admin = User::factory()->create(['role' => UserRole::Admin->value]);
    $course = Course::factory()->create();
    $student = Student::factory()->create([
        'course_id' => $course->id,
        'student_id' => '20260002',
    ]);

    $enrollment = StudentEnrollment::factory()->create([
        'student_id' => $student->id,
        'course_id' => $course->id,
        'semester' => 2,
        'school_year' => '2025 - 2026',
        'academic_year' => 1,
    ]);

    App\Models\StudentTuition::query()->create([
        'student_id' => $student->id,
        'enrollment_id' => $enrollment->id,
        'semester' => 2,
        'school_year' => '2025 - 2026',
        'academic_year' => 1,
        'total_lectures' => 12000,
        'total_laboratory' => 4000,
        'total_miscelaneous_fees' => 2500,
        'total_tuition' => 16000,
        'overall_tuition' => 18500,
        'downpayment' => 1000,
        'discount' => 0,
        'total_balance' => 17500,
        'status' => 'pending',
    ]);

    app()->instance(PdfGenerationService::class, new class
    {
        /**
         * @param  array<string, mixed>  $data
         * @param  array<string, mixed>  $options
         */
        public function generatePdfFromView(string $viewName, array $data, string $outputPath, array $options = []): void
        {
            throw new RuntimeException('Google Chrome executable not found.');
        }
    });

    $response = actingAs($admin)->get(route('administrators.students.tuition.soa', [
        'student' => $student->id,
        'school_year' => '2025 - 2026',
        'semester' => 2,
    ]));

    $response->assertSuccessful();

    $contentType = (string) $response->headers->get('content-type');
    $contentDisposition = (string) $response->headers->get('content-disposition');

    expect($contentType)->toContain('application/pdf')
        ->and($contentDisposition)->toContain('inline;')
        ->and($contentDisposition)->toContain('.pdf');
});
