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
    foreach ([
        'ViewAny:IndustryCourseCode', 'Create:IndustryCourseCode', 'Update:IndustryCourseCode', 'Delete:IndustryCourseCode',
        'ViewAny:CodeAuthority', 'Create:CodeAuthority', 'Update:CodeAuthority', 'Delete:CodeAuthority',
    ] as $permission) {
        Permission::findOrCreate($permission, 'web');
    }
});

/** @param list<string> $permissions */
function authorityCodeAdmin(School $school, array $permissions = [
    'ViewAny:IndustryCourseCode', 'Create:IndustryCourseCode', 'Update:IndustryCourseCode', 'Delete:IndustryCourseCode',
    'ViewAny:CodeAuthority', 'Create:CodeAuthority', 'Update:CodeAuthority', 'Delete:CodeAuthority',
]): User
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
        ['6-Digit PSCED Code', 'PSCED Name', '2-digit PSCED Discipline', 'Discipline Group', 'Special Regional Notes'],
        ['140101.0', 'Elementary Education', '14.0', 'Education Science and Teacher Training', 'Priority discipline region 4A'],
        ['464108.0', 'Information Technology', '47.0', 'IT-Related Disciplines', 'Priority discipline nation-wide'],
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
        ->and($code->category_code)->toBe('47')
        ->and($code->category_name)->toBe('IT-Related Disciplines')
        ->and($code->source)->toBe('import');

    $elem = IndustryCourseCode::query()->where('code', '140101')->firstOrFail();
    expect($elem->title)->toBe('Elementary Education')
        ->and($elem->category_code)->toBe('14')
        ->and($elem->category_name)->toBe('Education Science and Teacher Training');

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

it('enforces specific create and update permissions during import confirmation', function (): void {
    $school = chedAccreditedSchool();
    $authority = CodeAuthority::query()->create([
        'school_id' => $school->id,
        'key' => 'ched',
        'name' => 'CHED',
        'schema' => CodeAuthority::defaultSchema(),
        'is_active' => true,
    ]);

    IndustryCourseCode::query()->create([
        'school_id' => $school->id,
        'code_authority_id' => $authority->id,
        'code' => '140101',
        'title' => 'Old Title',
        'source' => 'manual',
    ]);

    // Admin has both to stage
    $stager = authorityCodeAdmin($school);
    $upload = authorityCodeWorkbook([
        ['Code', 'Title'],
        ['140101', 'Updated Title'],
        ['464108', 'Brand New Title'],
    ]);

    $response = $this->actingAs($stager)->post(route('administrators.curriculum.code-authority-imports.store'), [
        'file' => $upload,
        'code_authority_id' => $authority->id,
    ], ['Accept' => 'application/json'])->assertCreated();

    $importId = $response->json('import.id');
    $updateRowId = $response->json('import.rows.0.id');
    $createRowId = $response->json('import.rows.1.id');

    // User with only Create permission cannot confirm an update row
    $creatorOnly = User::factory()->create(['school_id' => $school->id, 'role' => UserRole::Registrar]);
    $creatorOnly->givePermissionTo(['ViewAny:IndustryCourseCode', 'Create:IndustryCourseCode']);
    app(TenantContext::class)->setCurrentSchool($school);

    // Staged by stager, so let's test authorizeRows via service
    $import = App\Models\CodeAuthorityImport::query()->where('public_id', $importId)->firstOrFail();

    expect(fn () => app(App\Services\CodeAuthorityImportService::class)->confirm($import, $creatorOnly, [$updateRowId]))
        ->toThrow(Symfony\Component\HttpKernel\Exception\HttpException::class);

    // User with only Update permission cannot confirm a create row
    $updaterOnly = User::factory()->create(['school_id' => $school->id, 'role' => UserRole::Registrar]);
    $updaterOnly->givePermissionTo(['ViewAny:IndustryCourseCode', 'Update:IndustryCourseCode']);

    expect(fn () => app(App\Services\CodeAuthorityImportService::class)->confirm($import, $updaterOnly, [$createRowId]))
        ->toThrow(Symfony\Component\HttpKernel\Exception\HttpException::class);
});

it('restricts search results to eligible active authorities matching the school', function (): void {
    $school = chedAccreditedSchool();
    $admin = authorityCodeAdmin($school);

    $activeChed = CodeAuthority::query()->create([
        'school_id' => $school->id,
        'key' => 'ched',
        'name' => 'CHED',
        'country_code' => 'PH',
        'curriculum_framework' => 'ched_psg',
        'is_active' => true,
    ]);
    $inactiveAuth = CodeAuthority::query()->create([
        'school_id' => $school->id,
        'key' => 'inactive_auth',
        'name' => 'Inactive Authority',
        'is_active' => false,
    ]);
    $foreignAuth = CodeAuthority::query()->create([
        'school_id' => $school->id,
        'key' => 'foreign_auth',
        'name' => 'Foreign Authority',
        'country_code' => 'US', // School is PH
        'is_active' => true,
    ]);

    IndustryCourseCode::query()->create([
        'school_id' => $school->id,
        'code_authority_id' => $activeChed->id,
        'code' => '464108',
        'title' => 'Information Technology',
        'source' => 'manual',
    ]);
    IndustryCourseCode::query()->create([
        'school_id' => $school->id,
        'code_authority_id' => $inactiveAuth->id,
        'code' => '999001',
        'title' => 'Hidden Code Inactive',
        'source' => 'manual',
    ]);
    IndustryCourseCode::query()->create([
        'school_id' => $school->id,
        'code_authority_id' => $foreignAuth->id,
        'code' => '999002',
        'title' => 'Hidden Code Foreign',
        'source' => 'manual',
    ]);

    $res = $this->actingAs($admin)->getJson(route('administrators.curriculum.authority-codes.search'))
        ->assertOk();

    $codes = collect($res->json('codes'))->pluck('code')->all();
    expect($codes)->toContain('464108')
        ->and($codes)->not->toContain('999001')
        ->and($codes)->not->toContain('999002');
});

it('does not leak non-CHED authority codes into CHED Form B/C exports', function (): void {
    $school = chedAccreditedSchool();
    $admin = authorityCodeAdmin($school);
    $department = Department::factory()->create(['school_id' => $school->id]);
    $courseType = App\Models\CourseType::factory()->create();

    $tesdaAuth = CodeAuthority::query()->create([
        'school_id' => $school->id,
        'key' => 'tesda',
        'name' => 'Technical Education and Skills Development Authority',
        'curriculum_framework' => 'tesda_tr',
        'is_active' => true,
    ]);
    $tesdaCode = IndustryCourseCode::query()->create([
        'school_id' => $school->id,
        'code_authority_id' => $tesdaAuth->id,
        'code' => 'TESDA-NC2',
        'title' => 'Cookery NC II',
        'source' => 'manual',
    ]);

    $course = Course::factory()->create([
        'school_id' => $school->id,
        'department_id' => $department->id,
        'course_type_id' => $courseType->id,
        'code' => 'BSIT',
        'title' => 'Bachelor of Science in Information Technology',
        'ched_program_code' => 'CHED-IT-001',
        'industry_course_code_id' => $tesdaCode->id,
    ]);

    // officialChedProgramCode refuses the non-CHED code and falls back to ched_program_code
    expect($course->officialChedProgramCode())->toBe('CHED-IT-001');

    $preview = app(App\Services\ChedFormBcExportService::class)->buildPreviewData([
        'school_id' => $school->id,
        'school_year' => '2024 - 2025',
        'semester' => 1,
        'report_key' => App\Services\RegulatoryReportRegistry::CHED_BACCALAUREATE,
    ]);

    $row = collect($preview['sheets']['Baccalaureate'] ?? [])->firstWhere('course_id', $course->id);
    expect($row['program_code'] ?? null)->toBe('CHED-IT-001');
});

it('checks policy permissions in Filament resources', function (): void {
    $school = chedAccreditedSchool();
    $dean = User::factory()->create(['school_id' => $school->id, 'role' => UserRole::Dean]);
    // Dean does not have Delete:CodeAuthority
    $this->actingAs($dean);

    expect(App\Filament\Resources\CodeAuthorities\CodeAuthorityResource::canDelete(new CodeAuthority))->toBeFalse()
        ->and(App\Filament\Resources\IndustryCourseCodes\IndustryCourseCodeResource::canDelete(new IndustryCourseCode))->toBeFalse();
});

it('counts distinct courses missing authority codes in quality analytics', function (): void {
    $school = chedAccreditedSchool();
    app(TenantContext::class)->setCurrentSchool($school);

    $department = Department::factory()->create(['school_id' => $school->id]);
    $courseWithoutCode = Course::factory()->create([
        'school_id' => $school->id,
        'department_id' => $department->id,
        'is_active' => true,
        'ched_program_code' => null,
        'industry_course_code_id' => null,
    ]);
    $courseWithCode = Course::factory()->create([
        'school_id' => $school->id,
        'department_id' => $department->id,
        'is_active' => true,
        'ched_program_code' => 'CHED-01',
    ]);

    $student1 = App\Models\Student::factory()->create(['school_id' => $school->id, 'course_id' => $courseWithoutCode->id]);
    $student2 = App\Models\Student::factory()->create(['school_id' => $school->id, 'course_id' => $courseWithoutCode->id]);

    App\Models\StudentEnrollment::factory()->create([
        'school_id' => $school->id,
        'student_id' => $student1->id,
        'course_id' => $courseWithoutCode->id,
        'school_year' => '2024 - 2025',
        'semester' => 1,
    ]);
    App\Models\StudentEnrollment::factory()->create([
        'school_id' => $school->id,
        'student_id' => $student2->id,
        'course_id' => $courseWithoutCode->id,
        'school_year' => '2024 - 2025',
        'semester' => 1,
    ]);

    $data = app(App\Services\RegistrarAnalyticsService::class)->build([
        'school_year' => '2024 - 2025',
        'semester' => 1,
    ]);

    // Count is 1 distinct course, not 2 enrollment rows
    expect($data['quality']['missing_authority_code_count'])->toBe(1);
});

it('renders the authority codes management CRUD page with stats and categories', function (): void {
    $school = chedAccreditedSchool();
    $admin = authorityCodeAdmin($school);

    $authority = CodeAuthority::query()->create([
        'school_id' => $school->id,
        'key' => 'ched',
        'name' => 'CHED',
        'country_code' => 'PH',
        'curriculum_framework' => 'ched_psg',
        'is_active' => true,
    ]);

    IndustryCourseCode::query()->create([
        'school_id' => $school->id,
        'code_authority_id' => $authority->id,
        'code' => '464108',
        'title' => 'Information Technology',
        'category_code' => '47',
        'category_name' => 'IT-Related Disciplines',
        'source' => 'import',
        'is_active' => true,
    ]);

    $response = $this->actingAs($admin)
        ->get(route('administrators.curriculum.authority-codes.index'))
        ->assertOk();

    $response->assertInertia(fn (Inertia\Testing\AssertableInertia $page) => $page
        ->component('administrators/curriculum/authority-codes/index')
        ->has('stats')
        ->where('stats.total_codes', 1)
        ->where('stats.total_categories', 1)
        ->has('categories', 1)
        ->has('codes.data', 1)
        ->where('codes.data.0.category_code', '47')
        ->where('codes.data.0.category_name', 'IT-Related Disciplines')
    );
});

it('supports full CRUD for authority course codes including category metadata', function (): void {
    $school = chedAccreditedSchool();
    $admin = authorityCodeAdmin($school);

    $authority = CodeAuthority::query()->create([
        'school_id' => $school->id,
        'key' => 'ched',
        'name' => 'CHED',
        'country_code' => 'PH',
        'curriculum_framework' => 'ched_psg',
        'is_active' => true,
    ]);

    // Create
    $this->actingAs($admin)
        ->post(route('administrators.curriculum.authority-codes.store'), [
            'code_authority_id' => $authority->id,
            'code' => '541601',
            'title' => 'Civil Engineering',
            'category_code' => '54',
            'category_name' => 'Engineering and Tech',
            'is_active' => true,
        ])
        ->assertRedirect();

    $code = IndustryCourseCode::query()->where('code', '541601')->firstOrFail();
    expect($code->title)->toBe('Civil Engineering')
        ->and($code->category_code)->toBe('54')
        ->and($code->category_name)->toBe('Engineering and Tech');

    // Update
    $this->actingAs($admin)
        ->put(route('administrators.curriculum.authority-codes.update', $code), [
            'title' => 'Civil Engineering (Revised)',
            'category_code' => '54',
            'category_name' => 'Engineering and Technology',
            'is_active' => true,
        ])
        ->assertRedirect();

    $code->refresh();
    expect($code->title)->toBe('Civil Engineering (Revised)')
        ->and($code->category_name)->toBe('Engineering and Technology');

    // Delete
    $this->actingAs($admin)
        ->delete(route('administrators.curriculum.authority-codes.destroy', $code))
        ->assertRedirect();

    expect(IndustryCourseCode::query()->where('id', $code->id)->exists())->toBeFalse();
});

it('supports deleting an authority and cascades its codes', function (): void {
    $school = chedAccreditedSchool();
    $admin = authorityCodeAdmin($school, [
        'ViewAny:CodeAuthority', 'Create:CodeAuthority', 'Update:CodeAuthority', 'Delete:CodeAuthority',
        'ViewAny:IndustryCourseCode', 'Create:IndustryCourseCode', 'Update:IndustryCourseCode', 'Delete:IndustryCourseCode',
    ]);

    $authority = CodeAuthority::query()->create([
        'school_id' => $school->id,
        'key' => 'temp_auth',
        'name' => 'Temporary Authority',
        'is_active' => true,
    ]);

    $code = IndustryCourseCode::query()->create([
        'school_id' => $school->id,
        'code_authority_id' => $authority->id,
        'code' => 'TEMP-01',
        'title' => 'Temporary Course',
        'category_code' => '99',
        'category_name' => 'Temporary Group',
    ]);

    $this->actingAs($admin)
        ->delete(route('administrators.curriculum.code-authorities.destroy', $authority))
        ->assertRedirect();

    expect(CodeAuthority::query()->where('id', $authority->id)->exists())->toBeFalse()
        ->and(IndustryCourseCode::query()->where('id', $code->id)->exists())->toBeFalse();
});
