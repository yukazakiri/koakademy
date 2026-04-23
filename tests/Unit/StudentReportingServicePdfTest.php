<?php

declare(strict_types=1);

use App\Models\Course;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Services\PdfGenerationService;
use App\Services\StudentReportingService;

uses()->group('pdf');

beforeEach(function (): void {
    // Bind a test double for PdfGenerationService to avoid Chrome/external APIs
    app()->bind(PdfGenerationService::class, function (): object {
        return new class
        {
            public function generatePdfFromHtml(string $html, string $outputPath, array $options = [], ?string $profile = null): void
            {
                file_put_contents($outputPath, "%PDF-1.4 fake pdf content\n");
            }

            public function generatePdfFromView(string $viewName, array $data, string $outputPath, array $options = [], ?string $profile = null): void
            {
                file_put_contents($outputPath, "%PDF-1.4 fake pdf content\n");
            }
        };
    });
});

test('generatePdfExport returns a pdf file path', function (): void {
    $course = Course::factory()->create();
    $student = Student::factory()->create(['course_id' => $course->id]);
    StudentEnrollment::factory()->create([
        'student_id' => $student->id,
        'school_year' => '2024-2025',
        'semester' => 1,
    ]);

    $service = app(StudentReportingService::class);
    $filePath = $service->generateFilteredExport([
        'course_filter' => 'all',
        'year_level_filter' => 'all',
    ], 'pdf');

    expect($filePath)->toBeString()
        ->and(str_ends_with($filePath, '.pdf'))->toBeTrue()
        ->and(Storage::exists($filePath))->toBeTrue();

    $content = Storage::get($filePath);
    expect(str_starts_with($content, '%PDF'))->toBeTrue();

    Storage::delete($filePath);
});

test('generatePdfExportContent returns pdf binary content', function (): void {
    $course = Course::factory()->create();
    $student = Student::factory()->create(['course_id' => $course->id]);
    StudentEnrollment::factory()->create([
        'student_id' => $student->id,
        'school_year' => '2024-2025',
        'semester' => 1,
    ]);

    $service = app(StudentReportingService::class);
    $content = $service->generateFilteredExportContent([
        'course_filter' => 'all',
        'year_level_filter' => 'all',
    ], 'pdf');

    expect($content)->toBeString()
        ->and(str_starts_with($content, '%PDF'))->toBeTrue();
});

test('generateCsvExport returns a csv file path', function (): void {
    $course = Course::factory()->create();
    $student = Student::factory()->create(['course_id' => $course->id]);
    StudentEnrollment::factory()->create([
        'student_id' => $student->id,
        'school_year' => '2024-2025',
        'semester' => 1,
    ]);

    $service = app(StudentReportingService::class);
    $filePath = $service->generateFilteredExport([
        'course_filter' => 'all',
        'year_level_filter' => 'all',
    ], 'csv');

    expect($filePath)->toBeString()
        ->and(str_ends_with($filePath, '.csv'))->toBeTrue()
        ->and(Storage::exists($filePath))->toBeTrue();

    Storage::delete($filePath);
});

test('export student data job uses pdf extension for pdf format', function (): void {
    $job = new ReflectionClass(\App\Jobs\ExportStudentDataJob::class);
    $method = $job->getMethod('generateFileName');
    $method->setAccessible(true);

    $exportJob = new \App\Models\ExportJob([
        'filters' => ['course_filter' => 'all', 'year_level_filter' => 'all'],
        'format' => 'pdf',
    ]);

    $fileName = $method->invoke(new \App\Jobs\ExportStudentDataJob(1), $exportJob);

    expect(str_ends_with($fileName, '.pdf'))->toBeTrue();
});
