<?php

declare(strict_types=1);

use App\Exports\StudentListExport;
use App\Http\Controllers\AdministratorClassManagementController;
use App\Models\ClassEnrollment;
use App\Models\Classes;
use App\Models\Student;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Spatie\LaravelPdf\Facades\Pdf;
use Spatie\LaravelPdf\PdfBuilder;

it('returns styles compatible with the Excel styles concern', function (): void {
    $styles = (new StudentListExport(new Classes))->styles(new Worksheet);

    expect($styles)->toBe([
        6 => ['font' => ['bold' => true]],
    ]);
});

it('excludes active enrollments whose students are no longer available from Excel exports', function (): void {
    $class = Classes::factory()->create();
    $visibleStudent = Student::factory()->create();
    $deletedStudent = Student::factory()->create();

    ClassEnrollment::factory()->create([
        'class_id' => $class->id,
        'student_id' => $visibleStudent->id,
        'status' => true,
    ]);
    ClassEnrollment::factory()->create([
        'class_id' => $class->id,
        'student_id' => $deletedStudent->id,
        'status' => true,
    ]);

    $deletedStudent->delete();

    expect((new StudentListExport($class))->collection()->pluck('student_id')->all())
        ->toBe([$visibleStudent->id]);
});

it('excludes active enrollments whose students are no longer available from PDF exports', function (): void {
    $class = Classes::factory()->create();
    $visibleStudent = Student::factory()->create();
    $deletedStudent = Student::factory()->create();

    ClassEnrollment::factory()->create([
        'class_id' => $class->id,
        'student_id' => $visibleStudent->id,
        'status' => true,
    ]);
    ClassEnrollment::factory()->create([
        'class_id' => $class->id,
        'student_id' => $deletedStudent->id,
        'status' => true,
    ]);

    $deletedStudent->delete();
    Pdf::fake();

    app(AdministratorClassManagementController::class)->downloadStudentListPdf($class);

    Pdf::assertSaved(function (PdfBuilder $pdf, string $path) use ($visibleStudent): bool {
        return $pdf->viewName === 'exports.student-list-pdf'
            && $pdf->viewData['students']->pluck('student_id')->all() === [$visibleStudent->id]
            && $path !== '';
    });
});
