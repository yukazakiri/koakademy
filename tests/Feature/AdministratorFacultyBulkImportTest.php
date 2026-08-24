<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\Faculty;
use App\Models\FacultyBulkImport;
use App\Models\FacultyCustomFieldDefinition;
use App\Models\FacultyCustomFieldValue;
use App\Models\School;
use App\Models\User;
use App\Services\TenantContext;
use Illuminate\Http\UploadedFile;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Spatie\Permission\Models\Permission;

beforeEach(function (): void {
    foreach (['ViewAny:Faculty', 'Create:Faculty', 'Update:Faculty'] as $permission) {
        Permission::findOrCreate($permission, 'web');
    }
});

/** @param list<string> $permissions */
function facultyImportAdmin(School $school, array $permissions = ['ViewAny:Faculty', 'Create:Faculty', 'Update:Faculty']): User
{
    $user = User::factory()->create(['school_id' => $school->id, 'role' => UserRole::Admin]);
    $user->givePermissionTo($permissions);
    app(TenantContext::class)->setCurrentSchool($school);

    return $user;
}

/** @param list<list<string|null>> $rows */
function facultyImportWorkbook(array $rows): UploadedFile
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

    return UploadedFile::fake()->createWithContent('faculty-import.xlsx', Excel::raw($export, Maatwebsite\Excel\Excel::XLSX));
}

it('stages, masks, and confirms configurable faculty fields from an Excel import', function (): void {
    $school = School::factory()->create();
    $admin = facultyImportAdmin($school);
    FacultyCustomFieldDefinition::query()->create([
        'school_id' => $school->id,
        'key' => 'national_insurance_number',
        'label' => 'National Insurance Number',
        'field_type' => 'text',
        'source_header_aliases' => ['NI Number'],
        'is_sensitive' => true,
        'display_order' => 10,
    ]);
    config()->set('app.url', 'https://school.example.edu');
    $upload = facultyImportWorkbook([
        ['Faculty ID Number', 'First Name', 'Middle Name', 'Last Name', 'Position', 'Date Employed', 'Custom: national_insurance_number'],
        ['FAC-100', 'Ada', null, 'Lovelace', 'Professor', '2024-06-01', 'NI-1234'],
    ]);

    $response = $this->actingAs($admin)->post(route('administrators.faculties.imports.store'), ['file' => $upload], ['Accept' => 'application/json'])
        ->assertCreated()
        ->assertJsonPath('import.summary.ready_rows', 1)
        ->assertJsonPath('import.rows.0.action', 'create')
        ->assertJsonPath('import.rows.0.fields.0.masked', true)
        ->assertJsonPath('import.rows.0.fields.0.value', '••••');

    $importId = $response->json('import.id');
    $rowId = $response->json('import.rows.0.id');
    $this->actingAs($admin)->postJson(route('administrators.faculties.imports.confirm', $importId), ['row_ids' => [$rowId]])
        ->assertSuccessful()
        ->assertJsonPath('import.status', 'completed')
        ->assertJsonPath('import.summary.applied_rows', 1);

    $faculty = Faculty::query()->where('faculty_id_number', 'FAC-100')->firstOrFail();
    expect($faculty->email)->toBe('fac.100@school.example.edu')
        ->and($faculty->position)->toBe('Professor')
        ->and($faculty->date_employed?->toDateString())->toBe('2024-06-01');
    $value = FacultyCustomFieldValue::query()->where('faculty_id', $faculty->id)->firstOrFail();
    expect($value->value)->toBe('NI-1234')
        ->and($value->getRawOriginal('value'))->not->toBe('NI-1234');
});

it('adds confirmed unmapped faculty columns as protected configurable fields', function (): void {
    $school = School::factory()->create();
    $admin = facultyImportAdmin($school);
    Permission::findOrCreate('Update:SystemManagementFacultyFields', 'web');
    $admin->givePermissionTo('Update:SystemManagementFacultyFields');
    $response = $this->actingAs($admin)->post(route('administrators.faculties.imports.store'), ['file' => facultyImportWorkbook([
        ['Faculty ID Number', 'First Name', 'Last Name', 'Tax Identifier'],
        ['FAC-150', 'Katherine', 'Johnson', 'TAX-1234'],
    ])], ['Accept' => 'application/json'])
        ->assertCreated()
        ->assertJsonPath('import.field_proposals.0.key', 'tax_identifier')
        ->assertJsonPath('import.field_proposals.0.populated_rows', 1)
        ->assertJsonPath('import.can_add_field_proposals', true);

    $this->actingAs($admin)->postJson(route('administrators.faculties.imports.confirm', $response->json('import.id')), [
        'row_ids' => [$response->json('import.rows.0.id')],
        'create_custom_field_keys' => ['tax_identifier'],
    ])->assertSuccessful();

    $definition = FacultyCustomFieldDefinition::query()
        ->where('school_id', $school->id)
        ->where('key', 'tax_identifier')
        ->firstOrFail();
    $faculty = Faculty::query()->where('school_id', $school->id)->where('faculty_id_number', 'FAC-150')->firstOrFail();

    expect($definition->field_type)->toBe('text')
        ->and($definition->is_sensitive)->toBeTrue()
        ->and($definition->is_required)->toBeFalse()
        ->and($definition->source_header_aliases)->toContain('tax_identifier')
        ->and(FacultyCustomFieldValue::query()
            ->where('faculty_id', $faculty->id)
            ->where('faculty_custom_field_definition_id', $definition->id)
            ->value('value'))->toBe('TAX-1234');
});

it('does not let a faculty-only administrator create proposed custom fields', function (): void {
    $school = School::factory()->create();
    $admin = facultyImportAdmin($school);
    $response = $this->actingAs($admin)->post(route('administrators.faculties.imports.store'), ['file' => facultyImportWorkbook([
        ['Faculty ID Number', 'First Name', 'Last Name', 'National Insurance'],
        ['FAC-151', 'Dorothy', 'Vaughan', 'NI-1234'],
    ])], ['Accept' => 'application/json'])
        ->assertCreated()
        ->assertJsonPath('import.can_add_field_proposals', false)
        ->assertJsonPath('import.field_proposals.0.key', 'national_insurance');

    $this->actingAs($admin)->postJson(route('administrators.faculties.imports.confirm', $response->json('import.id')), [
        'row_ids' => [$response->json('import.rows.0.id')],
        'create_custom_field_keys' => ['national_insurance'],
    ])->assertForbidden();

    expect(FacultyCustomFieldDefinition::query()
        ->where('school_id', $school->id)
        ->where('key', 'national_insurance')
        ->exists())->toBeFalse();
});

it('updates an existing faculty by ID without replacing a real email with a generated one', function (): void {
    $school = School::factory()->create();
    $admin = facultyImportAdmin($school);
    $faculty = Faculty::factory()->create([
        'school_id' => $school->id,
        'faculty_id_number' => 'FAC-200',
        'email' => 'ada@example.edu',
        'position' => null,
    ]);
    $upload = facultyImportWorkbook([
        ['Faculty ID Number', 'First Name', 'Last Name', 'Position'],
        ['FAC-200', 'Ada', 'Updated', 'Dean'],
    ]);

    $response = $this->actingAs($admin)->post(route('administrators.faculties.imports.store'), ['file' => $upload], ['Accept' => 'application/json'])
        ->assertCreated()
        ->assertJsonPath('import.rows.0.action', 'update');
    $this->actingAs($admin)->postJson(route('administrators.faculties.imports.confirm', $response->json('import.id')), ['row_ids' => [$response->json('import.rows.0.id')]])
        ->assertSuccessful();

    expect($faculty->refresh()->email)->toBe('ada@example.edu')
        ->and($faculty->last_name)->toBe('Updated')
        ->and($faculty->position)->toBe('Dean');
});

it('requires the matching faculty permission for each selected import action', function (): void {
    $school = School::factory()->create();
    $creator = facultyImportAdmin($school, ['Create:Faculty']);
    $createUpload = facultyImportWorkbook([
        ['Faculty ID Number', 'First Name', 'Last Name'],
        ['FAC-210', 'Grace', 'Hopper'],
    ]);

    $createResponse = $this->actingAs($creator)->post(route('administrators.faculties.imports.store'), ['file' => $createUpload], ['Accept' => 'application/json'])
        ->assertCreated();
    $this->actingAs($creator)->postJson(route('administrators.faculties.imports.confirm', $createResponse->json('import.id')), [
        'row_ids' => [$createResponse->json('import.rows.0.id')],
    ])->assertSuccessful();

    $existing = Faculty::factory()->create([
        'school_id' => $school->id,
        'faculty_id_number' => 'FAC-211',
        'first_name' => 'Original',
        'last_name' => 'Faculty',
    ]);
    $updateUpload = facultyImportWorkbook([
        ['Faculty ID Number', 'First Name', 'Last Name'],
        ['FAC-211', 'Updated', 'Faculty'],
    ]);
    $blockedResponse = $this->actingAs($creator)->post(route('administrators.faculties.imports.store'), ['file' => $updateUpload], ['Accept' => 'application/json'])
        ->assertCreated()
        ->assertJsonPath('import.rows.0.action', 'update');
    $this->actingAs($creator)->postJson(route('administrators.faculties.imports.confirm', $blockedResponse->json('import.id')), [
        'row_ids' => [$blockedResponse->json('import.rows.0.id')],
    ])->assertForbidden();
    expect($existing->refresh()->first_name)->toBe('Original');

    $updater = facultyImportAdmin($school, ['Update:Faculty']);
    $allowedResponse = $this->actingAs($updater)->post(route('administrators.faculties.imports.store'), ['file' => facultyImportWorkbook([
        ['Faculty ID Number', 'First Name', 'Last Name'],
        ['FAC-211', 'Updated', 'Faculty'],
    ])], ['Accept' => 'application/json'])->assertCreated();
    $this->actingAs($updater)->postJson(route('administrators.faculties.imports.confirm', $allowedResponse->json('import.id')), [
        'row_ids' => [$allowedResponse->json('import.rows.0.id')],
    ])->assertSuccessful();
    expect($existing->refresh()->first_name)->toBe('Updated');
});

it('revalidates staged custom fields and still writes values for deactivated definitions', function (): void {
    $school = School::factory()->create();
    $admin = facultyImportAdmin($school);
    $definition = FacultyCustomFieldDefinition::query()->create([
        'school_id' => $school->id,
        'key' => 'employment_type',
        'label' => 'Employment Type',
        'field_type' => 'select',
        'options' => ['Permanent'],
        'is_required' => false,
        'is_sensitive' => true,
        'display_order' => 10,
    ]);
    $requiredDefinition = FacultyCustomFieldDefinition::query()->create([
        'school_id' => $school->id,
        'key' => 'payroll_reference',
        'label' => 'Payroll Reference',
        'field_type' => 'text',
        'is_required' => false,
        'is_sensitive' => true,
        'display_order' => 11,
    ]);
    $response = $this->actingAs($admin)->post(route('administrators.faculties.imports.store'), ['file' => facultyImportWorkbook([
        ['Faculty ID Number', 'First Name', 'Last Name', 'Custom: employment_type'],
        ['FAC-225', 'Dorothy', 'Vaughan', 'Permanent'],
    ])], ['Accept' => 'application/json'])->assertCreated();

    $definition->update(['options' => ['Contract']]);
    $route = route('administrators.faculties.imports.confirm', $response->json('import.id'));
    $rowIds = ['row_ids' => [$response->json('import.rows.0.id')]];
    $this->actingAs($admin)->postJson($route, $rowIds)
        ->assertUnprocessable()
        ->assertJsonValidationErrors('import');

    $definition->update(['options' => ['Permanent'], 'field_type' => 'number']);
    $this->actingAs($admin)->postJson($route, $rowIds)
        ->assertUnprocessable()
        ->assertJsonValidationErrors('import');

    $definition->update(['field_type' => 'select', 'is_active' => false]);
    $requiredDefinition->update(['is_required' => true]);
    $this->actingAs($admin)->postJson($route, $rowIds)
        ->assertUnprocessable()
        ->assertJsonValidationErrors('import');

    $requiredDefinition->update(['is_required' => false]);
    $this->actingAs($admin)->postJson($route, $rowIds)->assertSuccessful();

    $faculty = Faculty::query()->where('school_id', $school->id)->where('faculty_id_number', 'FAC-225')->firstOrFail();
    expect(FacultyCustomFieldValue::query()
        ->where('faculty_id', $faculty->id)
        ->where('faculty_custom_field_definition_id', $definition->id)
        ->value('value'))->toBe('Permanent');
});

it('requires restaging when a staged create now matches faculty by ID', function (): void {
    $school = School::factory()->create();
    $admin = facultyImportAdmin($school);
    $response = $this->actingAs($admin)->post(route('administrators.faculties.imports.store'), ['file' => facultyImportWorkbook([
        ['Faculty ID Number', 'First Name', 'Last Name'],
        ['FAC-230', 'Katherine', 'Johnson'],
    ])], ['Accept' => 'application/json'])->assertCreated();
    $existing = Faculty::factory()->create([
        'school_id' => $school->id,
        'faculty_id_number' => 'FAC-230',
        'first_name' => 'Existing',
        'last_name' => 'Faculty',
    ]);

    $this->actingAs($admin)->postJson(route('administrators.faculties.imports.confirm', $response->json('import.id')), [
        'row_ids' => [$response->json('import.rows.0.id')],
    ])->assertUnprocessable()->assertJsonValidationErrors('import');

    expect($existing->refresh()->first_name)->toBe('Existing')
        ->and(Faculty::query()->where('school_id', $school->id)->where('faculty_id_number', 'FAC-230')->count())->toBe(1);
});

it('maps legacy Access-style headers through the editable default aliases', function (): void {
    $school = School::factory()->create();
    $admin = facultyImportAdmin($school);
    $upload = facultyImportWorkbook([
        ['FULL_NAME', 'ID_NO', 'ADDRESS', 'POSITION', 'DATE_EMPLOYED', 'SSS_NO', 'TIN_NO', 'PHILHEALTH_NO', 'PAG-IBIG_NO'],
        ['Curie, Marie Sklodowska', 'FAC-250', 'Paris', 'Research Professor', '2020-01-02', 'SSS-1', 'TIN-1', 'PH-1', 'PI-1'],
    ]);

    $response = $this->actingAs($admin)->post(route('administrators.faculties.imports.store'), ['file' => $upload], ['Accept' => 'application/json'])
        ->assertCreated()
        ->assertJsonPath('import.summary.ready_rows', 1);

    $this->actingAs($admin)->postJson(route('administrators.faculties.imports.confirm', $response->json('import.id')), [
        'row_ids' => [$response->json('import.rows.0.id')],
    ])->assertSuccessful();

    $faculty = Faculty::query()->where('school_id', $school->id)->where('faculty_id_number', 'FAC-250')->firstOrFail();
    expect($faculty->first_name)->toBe('Marie')
        ->and($faculty->middle_name)->toBe('Sklodowska')
        ->and($faculty->last_name)->toBe('Curie')
        ->and($faculty->address_line1)->toBe('Paris')
        ->and($faculty->position)->toBe('Research Professor')
        ->and(FacultyCustomFieldValue::query()->where('faculty_id', $faculty->id)->count())->toBe(4);
});

it('does not mutate faculty records until an administrator confirms ready rows', function (): void {
    $school = School::factory()->create();
    $admin = facultyImportAdmin($school);
    $upload = facultyImportWorkbook([
        ['Faculty ID Number', 'First Name', 'Last Name'],
        ['FAC-300', 'Grace', 'Hopper'],
        ['FAC-300', 'Duplicate', 'Person'],
    ]);

    $response = $this->actingAs($admin)->post(route('administrators.faculties.imports.store'), ['file' => $upload], ['Accept' => 'application/json'])
        ->assertCreated()
        ->assertJsonPath('import.summary.ready_rows', 1)
        ->assertJsonPath('import.summary.invalid_rows', 1);

    expect(Faculty::query()->where('school_id', $school->id)->count())->toBe(0)
        ->and(FacultyBulkImport::query()->where('public_id', $response->json('import.id'))->exists())->toBeTrue();
});

it('prevents a confirmed import from being replayed', function (): void {
    $school = School::factory()->create();
    $admin = facultyImportAdmin($school);
    $upload = facultyImportWorkbook([
        ['Faculty ID Number', 'First Name', 'Last Name'],
        ['FAC-350', 'Katherine', 'Johnson'],
    ]);

    $response = $this->actingAs($admin)->post(route('administrators.faculties.imports.store'), ['file' => $upload], ['Accept' => 'application/json'])
        ->assertCreated();
    $route = route('administrators.faculties.imports.confirm', $response->json('import.id'));
    $rowIds = ['row_ids' => [$response->json('import.rows.0.id')]];

    $this->actingAs($admin)->postJson($route, $rowIds)->assertSuccessful();
    $this->actingAs($admin)->postJson($route, $rowIds)
        ->assertUnprocessable()
        ->assertJsonValidationErrors('import');

    expect(Faculty::query()->where('school_id', $school->id)->where('faculty_id_number', 'FAC-350')->count())->toBe(1);
});

it('downloads a template with the school configured custom fields', function (): void {
    $school = School::factory()->create();
    $admin = facultyImportAdmin($school);
    FacultyCustomFieldDefinition::query()->create([
        'school_id' => $school->id,
        'key' => 'tax_identifier',
        'label' => 'Tax Identifier',
        'field_type' => 'text',
        'is_sensitive' => true,
        'display_order' => 5,
    ]);

    $response = $this->actingAs($admin)->get(route('administrators.faculties.imports.template'))
        ->assertSuccessful()
        ->assertDownload('faculty-import-template.xlsx');
    $templatePath = tempnam(sys_get_temp_dir(), 'faculty-import-template-');
    file_put_contents($templatePath, $response->streamedContent());
    $headers = IOFactory::load($templatePath)->getActiveSheet()->toArray()[0];

    expect($headers)->toContain('Custom: tax_identifier');
});

it('allows authorized system administrators to configure tenant faculty fields', function (): void {
    $school = School::factory()->create();
    $admin = facultyImportAdmin($school);
    foreach (['View:SystemManagementFacultyFields', 'Update:SystemManagementFacultyFields'] as $permission) {
        Permission::findOrCreate($permission, 'web');
    }
    $admin->givePermissionTo(['View:SystemManagementFacultyFields', 'Update:SystemManagementFacultyFields']);

    $this->actingAs($admin)->get(route('administrators.system-management.faculty-fields.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('administrators/system-management/faculty-fields', false)->has('field_definitions'));
    $this->actingAs($admin)->post(route('administrators.system-management.faculty-fields.store'), [
        'label' => 'National ID',
        'key' => 'national_id',
        'field_type' => 'text',
        'help_text' => 'Use the identifier required by this school.',
        'options' => [],
        'source_header_aliases' => ['National ID Number'],
        'is_required' => false,
        'is_sensitive' => true,
        'display_order' => 10,
    ])->assertRedirect();

    expect(FacultyCustomFieldDefinition::query()->where('school_id', $school->id)->where('key', 'national_id')->exists())->toBeTrue();
});
