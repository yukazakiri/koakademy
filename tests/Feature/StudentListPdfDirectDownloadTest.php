<?php

declare(strict_types=1);

use App\Jobs\GenerateStudentListPdfJob;
use App\Models\ClassEnrollment;
use App\Models\Classes;
use App\Models\Faculty;
use App\Models\Student;
use App\Services\PdfGenerationService;
use Illuminate\Support\Facades\Bus;

use function Pest\Laravel\actingAs;

it('downloads student list PDF directly without queue', function () {
    Bus::fake();

    /** @var Faculty $faculty */
    $faculty = Faculty::factory()->createOne([
        'email' => 'faculty@example.com',
    ]);

    $class = Classes::factory()->createOne([
        'faculty_id' => $faculty->id,
        'subject_code' => 'IT101',
        'section' => 'A',
        'semester' => '1st',
        'school_year' => '2023-2024',
        'classification' => 'college',
    ]);

    $student = Student::factory()->create([
        'student_id' => '20230001',
    ]);

    ClassEnrollment::factory()->create([
        'class_id' => $class->id,
        'student_id' => $student->id,
        'status' => true,
    ]);

    app()->instance(PdfGenerationService::class, new class
    {
        public function generatePdfFromView(string $viewName, array $data, string $outputPath, array $options = []): void
        {
            file_put_contents($outputPath, '%PDF-1.4 fake');
        }
    });

    /** @var Illuminate\Contracts\Auth\Authenticatable $facultyUser */
    $facultyUser = $faculty;

    $response = actingAs($facultyUser, 'faculty')
        ->get(route('classes.students.export.pdf', ['class' => $class->id]));

    $response->assertOk();

    Bus::assertNotDispatched(GenerateStudentListPdfJob::class);

    $contentDisposition = $response->headers->get('content-disposition');

    expect($contentDisposition)->toBeString();
    expect($contentDisposition)->toContain('attachment');
    expect($contentDisposition)->toContain('.pdf');
});
