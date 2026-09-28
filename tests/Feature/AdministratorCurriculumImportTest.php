<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\Course;
use App\Models\CourseType;
use App\Models\Department;
use App\Models\School;
use App\Models\Subject;
use App\Models\User;
use App\Services\GeneralSettingsService;
use App\Services\TenantContext;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Spatie\Permission\Models\Permission;

function curriculumUpload(): UploadedFile
{
    $book = new Spreadsheet;
    $sheet = $book->getActiveSheet();
    foreach ([
        1 => ['Diploma in Culinary Arts (Supervision and Administration) Technology'],
        6 => ['FIRST YEAR'], 7 => ['1ST SEMESTER'],
        8 => ['course code', 'Descriptive Title', 'lab', 'lec', 'Total', 'Hrs/week', 'Pre-requisite/s'],
        9 => ['GE 1', 'Mathematics in the Modern World', 3, '', 3, 3],
        13 => ['HPC 1', 'Fundamentals in Food Service Operation', 3, 2, 5, 13],
        17 => [6, '', 26, '', '', 40],
        18 => ['2ND SEMESTER'],
        19 => ['Course Code', 'Descriptive Title', 'Lab', 'Lec', 'Total', 'Hrs/week', 'Pre-requisite/s'],
        20 => ['HPC 2', 'Applied Business Tools', 3, '', 3, 3, 'HPC 1'],
        21 => ['HPC 3', 'Kitchen Fundamentals', 3, 2, 5, 13, 'HPC 3'],
        22 => ['THIRD YEAR'], 23 => ['2ND SEMESTER'],
        24 => ['Prac', 'Internship (600 Hours)', 6],
        25 => ['HPC 17', 'Capstone', 6],
    ] as $row => $cells) {
        foreach ($cells as $column => $value) {
            $sheet->setCellValue([$column + 1, $row], $value);
        }
    }
    $path = tempnam(sys_get_temp_dir(), 'curriculum-').'.xlsx';
    (new Xlsx($book))->save($path);
    $book->disconnectWorksheets();

    return new UploadedFile($path, 'ANNEX A_DCCP BAGUIO.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
}

function importSelection(array $rows, string $mode = 'new', array $options = []): array
{
    if ($mode === 'existing') {
        return ['mode' => 'existing', 'course_id' => $options['course_id'], 'rows' => array_map(static fn (array $row): array => [
            'skip' => false, 'code' => $row['code'], 'title' => $row['title'], 'units' => $row['units'],
            'lecture' => 0, 'laboratory' => 0, 'prerequisites' => $row['prerequisites_raw'], 'hours_confirmed' => true,
        ], $rows)];
    }

    return array_merge([
        'mode' => $mode,
        'code' => 'DCA-TEST', 'title' => 'Diploma in Culinary Arts',
        'department_id' => $options['department_id'] ?? null, 'course_type_id' => $options['course_type_id'] ?? null,
        'curriculum_kind' => 'program',
        'rows' => array_map(static fn (array $row): array => [
            'skip' => false, 'code' => $row['code'], 'title' => $row['title'], 'units' => $row['units'],
            'lecture' => 0, 'laboratory' => 0, 'prerequisites' => $row['prerequisites_raw'],
            'hours_confirmed' => true,
        ], $rows),
    ], $options);
}

beforeEach(function (): void {
    GeneralSettingsService::flushGlobalSetting();
    $school = School::factory()->create();
    $admin = User::factory()->create(['role' => UserRole::Admin, 'school_id' => $school->id]);
    foreach (['View:Course', 'Create:Course', 'Update:Course', 'Create:Subject', 'Update:Subject'] as $permission) {
        Permission::findOrCreate($permission, 'web');
    }
    $admin->givePermissionTo(['View:Course', 'Create:Course', 'Update:Course', 'Create:Subject', 'Update:Subject']);
    $this->actingAs($admin);
    app(TenantContext::class)->setCurrentSchool($school);
    $this->school = $school;
});

afterEach(function (): void {
    app(TenantContext::class)->clear();
    GeneralSettingsService::flushGlobalSetting();
});

it('stages the irregular workbook without creating subjects and applies only reviewed rows', function (): void {
    $dept = Department::factory()->create(['school_id' => $this->school->id]);
    $type = CourseType::factory()->create();
    $url = portalUrlForAdministrators('/administrators/ai/curriculum-imports');
    $draft = $this->postJson($url, ['file' => curriculumUpload()])->assertOk()->json();
    expect($draft['rows'])->toHaveCount(6)
        ->and($draft['rows'][1]['row'])->toBe(13)
        ->and($draft['rows'][3]['issues'])->toContain('Self-referencing prerequisite.')
        ->and(Course::query()->count())->toBe(0)
        ->and(Subject::query()->count())->toBe(0);

    $selection = importSelection($draft['rows'], options: ['department_id' => $dept->id, 'course_type_id' => $type->id]);
    $selection['rows'][3]['skip'] = true;
    $selection['rows'][4]['skip'] = true;
    $selection['rows'][5]['skip'] = true;
    $this->postJson("{$url}/{$draft['id']}/approve", $selection)->assertOk();
    $result = $this->postJson("{$url}/{$draft['id']}/apply")->assertOk()->json();
    expect($result['created'])->toBe(3)
        ->and(Subject::query()->count())->toBe(3)
        ->and(Subject::query()->where('code', 'HPC 2')->first()->pre_riquisite)->toBe([Subject::query()->where('code', 'HPC 1')->value('id')]);
    $this->postJson("{$url}/{$draft['id']}/apply")->assertOk()->assertJsonPath('already_applied', true);
});

it('blocks self prerequisites and cross-program codes while preserving the existing curriculum', function (): void {
    $course = Course::factory()->create(['school_id' => $this->school->id, 'code' => 'DCA-TEST']);
    Subject::factory()->for($course)->create(['code' => 'HPC 1', 'title' => 'Old title']);
    $url = portalUrlForAdministrators('/administrators/ai/curriculum-imports');
    $draft = $this->postJson($url, ['file' => curriculumUpload()])->assertOk()->json();
    $selection = importSelection($draft['rows'], 'existing', ['course_id' => $course->id]);
    $this->postJson("{$url}/{$draft['id']}/approve", $selection)->assertUnprocessable();
    expect(Subject::query()->where('code', 'HPC 1')->first()->title)->toBe('Old title');

    $selection['rows'][3]['skip'] = true;
    $selection['rows'][4]['skip'] = true;
    $selection['rows'][5]['skip'] = true;
    $this->postJson("{$url}/{$draft['id']}/approve", $selection)->assertOk();
    $this->postJson("{$url}/{$draft['id']}/apply")->assertOk()->assertJsonPath('updated', 1);
    expect(Subject::query()->where('code', 'HPC 1')->first()->title)->toBe('Fundamentals in Food Service Operation');
});

it('does not allow applying without approval or when MCP writes are disabled', function (): void {
    $dept = Department::factory()->create(['school_id' => $this->school->id]);
    $type = CourseType::factory()->create();
    $url = portalUrlForAdministrators('/administrators/ai/curriculum-imports');
    $draft = $this->postJson($url, ['file' => curriculumUpload()])->assertOk()->json();
    $this->postJson("{$url}/{$draft['id']}/apply")->assertUnprocessable();
    $selection = importSelection($draft['rows'], options: ['department_id' => $dept->id, 'course_type_id' => $type->id]);
    $selection['rows'][3]['skip'] = true;
    $selection['rows'][4]['skip'] = true;
    $selection['rows'][5]['skip'] = true;
    $this->postJson("{$url}/{$draft['id']}/approve", $selection)->assertOk();
    app(GeneralSettingsService::class)->updateApiManagementConfig(['mcp_write_enabled' => false]);
    $this->postJson("{$url}/{$draft['id']}/apply")->assertForbidden();
    app(GeneralSettingsService::class)->updateApiManagementConfig(['mcp_write_enabled' => true]);
    GeneralSettingsService::flushGlobalSetting();
    expect(Course::query()->count())->toBe(0)
        ->and(Subject::query()->count())->toBe(0);
});

it('does not let another administrator apply an approved draft', function (): void {
    $dept = Department::factory()->create(['school_id' => $this->school->id]);
    $type = CourseType::factory()->create();
    $url = portalUrlForAdministrators('/administrators/ai/curriculum-imports');
    $draft = $this->postJson($url, ['file' => curriculumUpload()])->assertOk()->json();
    $selection = importSelection($draft['rows'], options: ['department_id' => $dept->id, 'course_type_id' => $type->id]);
    $selection['rows'][3]['skip'] = true;
    $selection['rows'][4]['skip'] = true;
    $selection['rows'][5]['skip'] = true;
    $this->postJson("{$url}/{$draft['id']}/approve", $selection)->assertOk();

    $other = User::factory()->create(['role' => UserRole::Admin, 'school_id' => $this->school->id]);
    $other->givePermissionTo(['View:Course', 'Create:Course', 'Create:Subject']);
    $this->actingAs($other);
    $this->postJson("{$url}/{$draft['id']}/apply")->assertNotFound();
    expect(Course::query()->count())->toBe(0);
});

it('rejects prerequisite cycles rather than importing them', function (): void {
    $dept = Department::factory()->create(['school_id' => $this->school->id]);
    $type = CourseType::factory()->create();
    $url = portalUrlForAdministrators('/administrators/ai/curriculum-imports');
    $draft = $this->postJson($url, ['file' => curriculumUpload()])->assertOk()->json();
    $selection = importSelection($draft['rows'], options: ['department_id' => $dept->id, 'course_type_id' => $type->id]);
    $selection['rows'][3]['skip'] = true;
    $selection['rows'][4]['skip'] = true;
    $selection['rows'][5]['skip'] = true;
    $selection['rows'][1]['prerequisites'] = 'HPC 2';
    $this->postJson("{$url}/{$draft['id']}/approve", $selection)->assertUnprocessable()->assertJsonValidationErrors('rows');
    expect(Course::query()->count())->toBe(0);
});

it('rejects a globally used subject code from a different program', function (): void {
    $dept = Department::factory()->create(['school_id' => $this->school->id]);
    $type = CourseType::factory()->create();
    $other = Course::factory()->create(['school_id' => $this->school->id, 'code' => 'OTHER-COURSE']);
    Subject::factory()->for($other)->create(['code' => 'GE 1']);
    $url = portalUrlForAdministrators('/administrators/ai/curriculum-imports');
    $draft = $this->postJson($url, ['file' => curriculumUpload()])->assertOk()->json();
    $selection = importSelection($draft['rows'], options: ['department_id' => $dept->id, 'course_type_id' => $type->id]);
    $selection['rows'][3]['skip'] = $selection['rows'][4]['skip'] = $selection['rows'][5]['skip'] = true;
    $this->postJson("{$url}/{$draft['id']}/approve", $selection)->assertUnprocessable()->assertJsonValidationErrors('rows');
    expect(Course::query()->count())->toBe(1);
});

it('requires confirmed lecture and lab hours for every included row', function (): void {
    $dept = Department::factory()->create(['school_id' => $this->school->id]);
    $type = CourseType::factory()->create();
    $url = portalUrlForAdministrators('/administrators/ai/curriculum-imports');
    $draft = $this->postJson($url, ['file' => curriculumUpload()])->assertOk()->json();
    $selection = importSelection($draft['rows'], options: ['department_id' => $dept->id, 'course_type_id' => $type->id]);
    $selection['rows'][3]['skip'] = $selection['rows'][4]['skip'] = $selection['rows'][5]['skip'] = true;
    $selection['rows'][0]['hours_confirmed'] = false;
    $this->postJson("{$url}/{$draft['id']}/approve", $selection)->assertUnprocessable()->assertJsonValidationErrors('rows');
    expect(Subject::query()->count())->toBe(0);
});
