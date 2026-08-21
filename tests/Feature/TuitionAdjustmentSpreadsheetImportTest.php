<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Exports\Sheets\TuitionAdjustmentSpreadsheetTemplateSheet;
use App\Exports\TuitionAdjustmentSpreadsheetTemplateExport;
use App\Models\Course;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\StudentTuition;
use App\Models\TuitionAdjustment;
use App\Models\TuitionAdjustmentSpreadsheetImport;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Spatie\Permission\Models\Permission;

function spreadsheetImportUser(): User
{
    $user = User::factory()->create(['role' => UserRole::AccountingOfficer]);
    foreach (['view_tuition_fees', 'manage_tuition_fees'] as $permission) {
        Permission::findOrCreate($permission, 'web');
    }
    $user->givePermissionTo(['view_tuition_fees', 'manage_tuition_fees']);

    return $user;
}

/** @return array{student: Student, enrollment: StudentEnrollment, tuition: StudentTuition} */
function spreadsheetImportEnrollment(float $total = 12000): array
{
    $course = Course::factory()->create(['code' => 'BSA-'.Str::upper(Str::random(4))]);
    $student = Student::factory()->create(['course_id' => $course->id, 'student_type' => 'college']);
    $enrollment = StudentEnrollment::factory()->create([
        'student_id' => $student->id,
        'course_id' => $course->id,
        'school_year' => '2026 - 2027',
        'semester' => 1,
        'academic_year' => 1,
    ]);
    $tuition = StudentTuition::query()->create([
        'student_id' => $student->id,
        'enrollment_id' => $enrollment->id,
        'school_year' => $enrollment->school_year,
        'semester' => 1,
        'academic_year' => 1,
        'total_tuition' => $total,
        'total_lectures' => $total,
        'total_laboratory' => 0,
        'total_miscelaneous_fees' => 0,
        'overall_tuition' => $total,
        'total_balance' => $total,
        'paid' => 0,
        'discount' => 0,
        'downpayment' => 0,
        'status' => 'pending',
    ]);

    return compact('student', 'enrollment', 'tuition');
}

/** @param list<array<int, mixed>> $rows */
function tuitionSpreadsheetUpload(array $rows, ?array $headings = null): UploadedFile
{
    $spreadsheet = new Spreadsheet;
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->fromArray($headings ?? TuitionAdjustmentSpreadsheetTemplateSheet::HEADINGS, null, 'A1');
    foreach ($rows as $index => $row) {
        $sheet->fromArray($row, null, 'A'.($index + 2));
    }
    $path = tempnam(sys_get_temp_dir(), 'tuition-import-');
    (new Xlsx($spreadsheet))->save($path);

    return new UploadedFile($path, 'tuition-adjustments.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
}

it('downloads a formatted spreadsheet template for tuition viewers', function (): void {
    $user = spreadsheetImportUser();
    $this->actingAs($user)
        ->get(portalUrlForAdministrators('/administrators/finance/tuition-adjustments/template'))
        ->assertOk()
        ->assertHeader('content-disposition', 'attachment; filename=tuition-adjustment-template.xlsx');

    $templatePath = tempnam(sys_get_temp_dir(), 'tuition-template-');
    file_put_contents($templatePath, Excel::raw(new TuitionAdjustmentSpreadsheetTemplateExport, Maatwebsite\Excel\Excel::XLSX));
    $workbook = IOFactory::load($templatePath);
    expect($workbook->getSheetNames())->toBe(['Instructions', 'Tuition Adjustments'])
        ->and($workbook->getSheetByName('Tuition Adjustments')?->getCell('A1')->getValue())->toBe('Student Number')
        ->and($workbook->getSheetByName('Tuition Adjustments')?->getFreezePane())->toBe('A2');
});

it('stages valid and invalid spreadsheet rows without changing tuition', function (): void {
    Storage::fake('private');
    $user = spreadsheetImportUser();
    ['student' => $student, 'tuition' => $tuition] = spreadsheetImportEnrollment();
    $file = tuitionSpreadsheetUpload([
        [(string) $student->student_id, 'Scholarship correction', 10000, '', '', '', '', 0, '', '', '', ''],
        ['missing-student', 'Adjustment', 9000, '', '', '', '', '', '', '', '', ''],
    ]);

    $this->actingAs($user)
        ->post(portalUrlForAdministrators('/administrators/finance/tuition-adjustments/imports'), [
            'file' => $file,
            'school_year' => '2026 - 2027',
            'semester' => 1,
        ])
        ->assertRedirect();

    $import = TuitionAdjustmentSpreadsheetImport::query()->sole();
    expect($import->ready_count)->toBe(1)->and($import->invalid_count)->toBe(1)
        ->and((float) $tuition->refresh()->overall_tuition)->toBe(12000.0);
    Storage::disk('private')->assertExists($import->stored_path);
});

it('confirms only valid spreadsheet rows through the existing tuition adjustment audit', function (): void {
    Storage::fake('private');
    $user = spreadsheetImportUser();
    ['student' => $student, 'tuition' => $tuition] = spreadsheetImportEnrollment();
    $file = tuitionSpreadsheetUpload([
        [(string) $student->student_id, 'Approved scholarship adjustment', 10000, '', '', '', '', 0, '', '', '', ''],
        ['missing-student', 'Unmatched adjustment', 9000, '', '', '', '', '', '', '', '', ''],
    ]);
    $this->actingAs($user)->post(portalUrlForAdministrators('/administrators/finance/tuition-adjustments/imports'), [
        'file' => $file, 'school_year' => '2026 - 2027', 'semester' => 1,
    ]);
    $import = TuitionAdjustmentSpreadsheetImport::query()->sole();

    $this->actingAs($user)
        ->post(portalUrlForAdministrators('/administrators/finance/tuition-adjustments/imports/'.$import->public_id.'/confirm'))
        ->assertRedirect();

    expect((float) $tuition->refresh()->overall_tuition)->toBe(10000.0)
        ->and(TuitionAdjustment::query()->sole()->reason)->toBe('Approved scholarship adjustment')
        ->and(TuitionAdjustment::query()->sole()->source)->toBe('spreadsheet')
        ->and($import->refresh()->applied_count)->toBe(1)
        ->and($import->rejected_count)->toBe(0);
});

it('requires a valid xlsx workbook and finance permissions for spreadsheet uploads', function (): void {
    $user = User::factory()->create(['role' => UserRole::Admin]);
    $this->actingAs($user)
        ->post(portalUrlForAdministrators('/administrators/finance/tuition-adjustments/imports'), [
            'school_year' => '2026 - 2027', 'semester' => 1,
        ])
        ->assertForbidden();

    $authorized = spreadsheetImportUser();
    $this->actingAs($authorized)
        ->post(portalUrlForAdministrators('/administrators/finance/tuition-adjustments/imports'), [
            'file' => UploadedFile::fake()->create('not-a-template.csv', 4, 'text/csv'),
            'school_year' => '2026 - 2027', 'semester' => 1,
        ])
        ->assertSessionHasErrors('file');

    Storage::fake('private');
    $wrongHeaders = tuitionSpreadsheetUpload([['123', 'Reason', 1000]], ['Student Number', 'Reason', 'Wrong total']);
    $this->actingAs($authorized)
        ->post(portalUrlForAdministrators('/administrators/finance/tuition-adjustments/imports'), [
            'file' => $wrongHeaders, 'school_year' => '2026 - 2027', 'semester' => 1,
        ])
        ->assertSessionHasErrors('file');

    expect(TuitionAdjustmentSpreadsheetImport::query()->count())->toBe(0);
});
