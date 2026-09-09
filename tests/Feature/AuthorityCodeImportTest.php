<?php

declare(strict_types=1);

use App\Enums\CurriculumFramework;
use App\Enums\SchoolLevel;
use App\Enums\UserRole;
use App\Models\CodeAuthority;
use App\Models\Course;
use App\Models\Department;
use App\Models\IndustryCourseCode;
use App\Models\School;
use App\Models\SchoolCurriculumCapability;
use App\Models\User;
use App\Services\TenantContext;
use Illuminate\Http\UploadedFile;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Facades\Excel;
use Spatie\Permission\Models\Permission;

beforeEach(function (): void {
    foreach (['ViewAny:IndustryCourseCode', 'Create:IndustryCourseCode', 'Update:IndustryCourseCode', 'ViewAny:CodeAuthority', 'Create:CodeAuthority', 'Update:CodeAuthority'] as $permission) {
        Permission::findOrCreate($permission, 'web');
    }
});

/** @param list<string> $permissions */
function authorityCodeAdmin(School $school, array $permissions = ['ViewAny:IndustryCourseCode', 'Create:IndustryCourseCode', 'Update:IndustryCourseCode', 'ViewAny:CodeAuthority', 'Create:CodeAuthority', 'Update:CodeAuthority']): User
{
    // Admin role passes the curriculum program request gates; explicit
    // permissions pass the authority/code Gate checks.
    $user = User::factory()->create(['school_id' => $school->id, 'role' => UserRole::Admin]);
    $user->givePermissionTo($permissions);
    app(TenantContext::class)->setCurrentSchool($school);

    return $user;
}

function chedAccreditedSchool(): School
{
    $school = School::factory()->create(['country_code' => 'PH', 'school_level' => SchoolLevel::HigherEducation]);
    SchoolCurriculumCapability::factory()->for($school)->create([
        'school_level' => SchoolLevel::HigherEducation,
        'curriculum_framework' => CurriculumFramework::ChedPsg,
        'is_enabled' => true,
    ]);

    return $school;
}

/** @param list<list<string|null>> $rows */
function authorityCodeWorkbook(array $rows): UploadedFile
{
    $export = new class($rows) implements FromArray
    {
        /** @param list<list<string|null>> $rows */
        public function __construct(private readonly array $rows) {}

        public function array(): array
        {
            return $this->rows;
        }
    };

    return UploadedFile::fake()->createWithContent('authority-codes.xlsx', Excel::raw($export, Maatwebsite\Excel\Excel::XLSX));
}

it('lets a registrar create a CHED authority and import its spreadsheet with new columns adopted', function (): void {
    $school = chedAccreditedSchool();
    $admin = authorityCodeAdmin($school);

    $authorityId = $this->actingAs($admin)->postJson(route('administrators.curriculum.code-authorities.store'), [
        'key' => 'ched',
        'name' => 'CHED',
        'country_code' => 'PH',
        'curriculum_framework' => 'ched_psg',
    ])->assertCreated()->json('authority.id');

    $upload = authorityCodeWorkbook([
        ['6-Digit PSCED Code', 'PSCED Name', '2-digit PSCED Discipline', 'Discipline Group'],
        ['140101.0', 'Elementary Education', '14.0', 'Education Science and Teacher Training'],
        ['464108.0', 'Information Technology', '47.0', 'IT-Related Disciplines'],
    ]);

    $response = $this->actingAs($admin)->post(route('administrators.curriculum.code-authority-imports.store'), [
        'file' => $upload,
        'code_authority_id' => $authorityId,
    ], ['Accept' => 'application/json'])
        ->assertCreated()
        ->assertJsonPath('import.summary.ready_rows', 2)
        ->assertJsonPath('import.rows.0.code', '140101');

    // Unknown spreadsheet columns become adoptable proposals, not errors.
    expect($response->json('import.field_proposals'))->not->toBeEmpty();

    $proposalKeys = collect($response->json('import.field_proposals'))->pluck('key')->all();

    $this->actingAs($admin)->postJson(route('administrators.curriculum.code-authority-imports.confirm', $response->json('import.id')), [
        'row_ids' => [$response->json('import.rows.0.id'), $response->json('import.rows.1.id')],
        'adopt_column_keys' => $proposalKeys,
    ])->assertSuccessful()->assertJsonPath('import.status', 'completed')
        ->assertJsonPath('import.summary.applied_rows', 2);

    $code = IndustryCourseCode::query()->where('code', '464108')->firstOrFail();
    expect($code->title)->toBe('Information Technology')
        ->and($code->source)->toBe('import');

    // Adopted columns extend the authority schema for future templates.
    expect(CodeAuthority::query()->findOrFail($authorityId)->columnDefinitions())
        ->not->toBe(CodeAuthority::defaultSchema());
});

it('rejects rows missing a code or title and flags duplicates', function (): void {
    $school = chedAccreditedSchool();
    $admin = authorityCodeAdmin($school);
    $authority = CodeAuthority::query()->create([
        'school_id' => $school->id,
        'key' => 'ched',
        'name' => 'CHED',
        'country_code' => 'PH',
        'curriculum_framework' => 'ched_psg',
        'schema' => CodeAuthority::defaultSchema(),
        'is_active' => true,
    ]);

    $upload = authorityCodeWorkbook([
        ['Code', 'Title'],
        ['464108', 'Information Technology'],
        ['464108', 'Information Technology Duplicate'],
        [null, 'Missing Code Row'],
    ]);

    $this->actingAs($admin)->post(route('administrators.curriculum.code-authority-imports.store'), [
        'file' => $upload,
        'code_authority_id' => $authority->id,
    ], ['Accept' => 'application/json'])
        ->assertCreated()
        ->assertJsonPath('import.summary.ready_rows', 1)
        ->assertJsonPath('import.summary.invalid_rows', 2);
});

it('keeps authority code lists isolated per school', function (): void {
    $schoolA = chedAccreditedSchool();
    $schoolB = chedAccreditedSchool();
    $adminA = authorityCodeAdmin($schoolA);

    $authority = CodeAuthority::query()->create([
        'school_id' => $schoolA->id,
        'key' => 'ched',
        'name' => 'CHED',
        'schema' => CodeAuthority::defaultSchema(),
        'is_active' => true,
    ]);
    IndustryCourseCode::query()->create([
        'school_id' => $schoolA->id,
        'code_authority_id' => $authority->id,
        'code' => '464108',
        'title' => 'Information Technology',
        'source' => 'manual',
    ]);

    // School A sees its code.
    $this->actingAs($adminA)->getJson(route('administrators.curriculum.authority-codes.search', ['q' => '4641']))
        ->assertOk()->assertJsonCount(1, 'codes');

    // School B sees nothing from school A.
    $adminB = authorityCodeAdmin($schoolB);
    $this->actingAs($adminB)->getJson(route('administrators.curriculum.authority-codes.search', ['q' => '4641']))
        ->assertOk()->assertJsonCount(0, 'codes');
});

it('links a program to an authority code and prefers it in CHED exports', function (): void {
    $school = chedAccreditedSchool();
    $admin = authorityCodeAdmin($school);
    $department = Department::factory()->create(['school_id' => $school->id]);

    $authority = CodeAuthority::query()->create([
        'school_id' => $school->id,
        'key' => 'ched',
        'name' => 'CHED',
        'schema' => CodeAuthority::defaultSchema(),
        'is_active' => true,
    ]);
    $code = IndustryCourseCode::query()->create([
        'school_id' => $school->id,
        'code_authority_id' => $authority->id,
        'code' => '464108',
        'title' => 'Information Technology',
        'source' => 'manual',
    ]);

    $courseType = App\Models\CourseType::factory()->create();

    $course = Course::factory()->create([
        'school_id' => $school->id,
        'department_id' => $department->id,
        'course_type_id' => $courseType->id,
        'code' => 'BSIT',
        'title' => 'Bachelor of Science in Information Technology',
        'ched_program_code' => 'LEGACY-CODE',
    ]);

    $this->actingAs($admin)->put(route('administrators.curriculum.programs.update', $course), [
        'code' => 'BSIT',
        'title' => 'Bachelor of Science in Information Technology',
        'department_id' => $department->id,
        'course_type_id' => $courseType->id,
        'curriculum_kind' => 'program',
        'industry_course_code_id' => $code->id,
    ])->assertRedirect();

    $course = $course->refresh();
    expect($course->industry_course_code_id)->toBe($code->id)
        ->and($course->officialAuthorityCode())->toBe('464108');

    $preview = app(App\Services\ChedFormBcExportService::class)->buildPreviewData([
        'school_id' => $school->id,
        'school_year' => '2024 - 2025',
        'semester' => 1,
        'report_key' => App\Services\RegulatoryReportRegistry::CHED_BACCALAUREATE,
    ]);

    $row = collect($preview['sheets']['Baccalaureate'] ?? [])->firstWhere('course_id', $course->id);
    expect($row['program_code'] ?? null)->toBe('464108');
});

it('prevents linking a program to another school’s authority code', function (): void {
    $schoolA = chedAccreditedSchool();
    $schoolB = chedAccreditedSchool();
    $adminA = authorityCodeAdmin($schoolA);
    $department = Department::factory()->create(['school_id' => $schoolA->id]);

    $foreignAuthority = CodeAuthority::query()->create([
        'school_id' => $schoolB->id,
        'key' => 'ched',
        'name' => 'CHED',
        'schema' => CodeAuthority::defaultSchema(),
        'is_active' => true,
    ]);
    $foreignCode = IndustryCourseCode::query()->create([
        'school_id' => $schoolB->id,
        'code_authority_id' => $foreignAuthority->id,
        'code' => '464108',
        'title' => 'Information Technology',
        'source' => 'manual',
    ]);

    $courseType = App\Models\CourseType::factory()->create();

    $course = Course::factory()->create([
        'school_id' => $schoolA->id,
        'department_id' => $department->id,
        'course_type_id' => $courseType->id,
        'code' => 'BSIT',
        'title' => 'Bachelor of Science in Information Technology',
    ]);

    $this->actingAs($adminA)->put(route('administrators.curriculum.programs.update', $course), [
        'code' => 'BSIT',
        'title' => 'Bachelor of Science in Information Technology',
        'department_id' => $department->id,
        'course_type_id' => $courseType->id,
        'curriculum_kind' => 'program',
        'industry_course_code_id' => $foreignCode->id,
    ])->assertSessionHasErrors('industry_course_code_id');
});
