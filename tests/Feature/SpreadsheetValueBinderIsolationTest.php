<?php

declare(strict_types=1);

use App\Exports\Sheets\RegistrarImportMetadataSheet;
use App\Models\Course;
use App\Models\CourseType;
use App\Models\Department;
use App\Models\School;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Services\ChedFormBcExportService;
use App\Services\RegulatoryReportRegistry;
use App\Services\TenantContext;
use App\Support\RegistrarStudentProfileWorkbook;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\DefaultValueBinder;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

/**
 * A workbook must keep its formulas after an export that writes values as text.
 *
 * Maatwebsite installs the sheet being written as PhpSpreadsheet's process-wide
 * static value binder and never restores the default. An export that binds
 * every value as text therefore leaves that binder installed, and any
 * spreadsheet built later in the same process has `setCellValue()` coerced to
 * a string. Formulas become literal text, so `getCalculatedValue()` returns
 * the formula itself and every total casts to zero.
 *
 * This was not theoretical: a CHED Form B/C report generated after a registrar
 * analytics export filed with zeroed totals, and it surfaced as an intermittent
 * CI failure in a completely unrelated feature.
 */
it('keeps formulas intact after a text-binding export has run', function (): void {
    $school = School::factory()->create();
    app(TenantContext::class)->setCurrentSchool($school);

    $type = CourseType::firstOrCreate(['name' => 'College Undergraduate']);
    $department = Department::factory()->forSchool($school)->create(['code' => 'CCS', 'name' => 'Computing']);
    $course = Course::factory()->create([
        'school_id' => $school->id, 'department_id' => $department->id, 'course_type_id' => $type->id,
        'title' => 'Information Technology', 'code' => 'BSIT', 'is_active' => true,
    ]);

    $student = Student::factory()->minimal()->create([
        'school_id' => $school->id, 'course_id' => $course->id, 'gender' => 'Male',
        'is_pwd' => true, 'pwd_type' => 'Apparent physical disability',
    ]);
    StudentEnrollment::factory()->create([
        'school_id' => $school->id, 'student_id' => $student->id, 'course_id' => $course->id,
        'academic_year' => 2, 'school_year' => '2026-2027', 'semester' => 1,
    ]);

    // Reproduce the leak exactly as the framework does it: install a binder
    // that coerces every value to text, the way the registrar export sheets do.
    $textBinder = new class extends DefaultValueBinder
    {
        public function bindValue(Cell $cell, mixed $value): bool
        {
            $cell->setValueExplicit($value === null ? '' : (string) $value, DataType::TYPE_STRING);

            return true;
        }
    };

    Cell::setValueBinder($textBinder);
    expect(Cell::getValueBinder())->toBe($textBinder);

    // A workbook built while that binder is installed loses its formulas.
    $spreadsheet = new Spreadsheet;
    $spreadsheet->getActiveSheet()->setCellValue('D25', '=1+1');
    expect($spreadsheet->getActiveSheet()->getCell('D25')->getDataType())->toBe(DataType::TYPE_STRING);

    // The real symptom: totals in a report generated afterwards read zero.
    $report = app(ChedFormBcExportService::class)->generate([
        'school_year' => '2026-2027', 'semester' => 1, 'school_id' => $school->id,
        'course_id' => $course->id, 'report_key' => RegulatoryReportRegistry::CHED_EQUITY_ENROLLMENT,
    ]);
    $poisoned = (int) $report->getSheet(0)->getCell('D25')->getCalculatedValue();

    // The boundary reset is what the application relies on.
    Cell::setValueBinder(new DefaultValueBinder);

    $after = app(ChedFormBcExportService::class)->generate([
        'school_year' => '2026-2027', 'semester' => 1, 'school_id' => $school->id,
        'course_id' => $course->id, 'report_key' => RegulatoryReportRegistry::CHED_EQUITY_ENROLLMENT,
    ]);

    expect($poisoned)->toBe(0)
        ->and((int) $after->getSheet(0)->getCell('D25')->getCalculatedValue())->toBe(1);
});

/**
 * The registrar export sheets are the ones that force text, so they are the
 * reason a reset is needed. Pin that they still bind as text, so the reset is
 * not mistaken for permission to make them numeric.
 */
it('still binds the registrar import metadata sheet as text', function (): void {
    $sheet = new RegistrarImportMetadataSheet(
        ['report_label' => 'Registrar Analytics', 'generated_at' => '2026-09-29T00:00:00+00:00', 'signature' => 'abc'],
        new RegistrarStudentProfileWorkbook,
    );

    $cell = (new Spreadsheet)->getActiveSheet()->getCell('A1');

    $sheet->bindValue($cell, '0012345');

    expect($cell->getDataType())->toBe(DataType::TYPE_STRING)
        ->and((string) $cell->getValue())->toBe('0012345');
});
