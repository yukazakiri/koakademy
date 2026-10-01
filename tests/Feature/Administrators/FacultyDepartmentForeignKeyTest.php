<?php

declare(strict_types=1);

use App\Models\Department;
use App\Models\Faculty;
use App\Models\School;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Faculty.department was free text matched by code OR name. The department_id foreign key
 * replaces that; these cover the backfill and the readers that now depend on it.
 */
it('adds a department_id foreign key to faculty', function (): void {
    expect(Schema::hasColumn('faculty', 'department_id'))->toBeTrue();
});

it('keeps the legacy department string column', function (): void {
    // Filament, the MCP faculty tools, DigitalIdCardService and FacultySearchable all still
    // read and write it. Dropping it would break them, unlike the courses table where the
    // string column was removed.
    expect(Schema::hasColumn('faculty', 'department'))->toBeTrue();
});

it('constrains department_id to departments and indexes the pair', function (): void {
    $foreignKeys = collect(DB::select("PRAGMA foreign_key_list('faculty')"))
        ->filter(fn (object $row): bool => $row->from === 'department_id');

    expect($foreignKeys)->not->toBeEmpty();
    expect($foreignKeys->first()->table)->toBe('departments');
    expect($foreignKeys->first()->on_delete)->toBe('SET NULL');

    $indexes = collect(DB::select("PRAGMA index_list('faculty')"))->pluck('name');

    expect($indexes)->toContain('faculty_department_school_index');
    expect($indexes)->toContain('faculty_school_department_index');
});

it('backfills faculty by department code', function (): void {
    $school = School::factory()->create();
    $department = Department::factory()->create(['school_id' => $school->id, 'code' => 'IT', 'name' => 'Information Technology']);

    $faculty = Faculty::factory()->create(['school_id' => $school->id, 'department' => 'IT']);
    $faculty->forceFill(['department_id' => null])->save();

    expect(Faculty::query()->find($faculty->id)->department_id)->toBeNull();

    // Re-run the resolution the migration performs.
    $resolved = Department::query()
        ->whereRaw('UPPER(TRIM(code)) = ?', [mb_strtoupper('IT')])
        ->first();

    Faculty::query()->whereKey($faculty->id)->update(['department_id' => $resolved?->id]);

    expect(Faculty::query()->find($faculty->id)->department_id)->toBe($department->id);
});

it('resolves faculty to their department through the foreign key', function (): void {
    $school = School::factory()->create();
    $department = Department::factory()->create(['school_id' => $school->id, 'code' => 'IT']);

    $faculty = Faculty::factory()->create([
        'school_id' => $school->id,
        'department' => 'IT',
        'department_id' => $department->id,
    ]);

    expect($faculty->fresh()->departmentRecord?->id)->toBe($department->id);
});

it('scopes department faculty to its own department only', function (): void {
    $school = School::factory()->create();
    $it = Department::factory()->create(['school_id' => $school->id, 'code' => 'IT']);
    $ba = Department::factory()->create(['school_id' => $school->id, 'code' => 'BA']);

    Faculty::factory()->count(3)->create(['school_id' => $school->id, 'department_id' => $it->id, 'department' => 'IT']);
    Faculty::factory()->count(2)->create(['school_id' => $school->id, 'department_id' => $ba->id, 'department' => 'BA']);

    expect($it->faculty()->count())->toBe(3);
    expect($ba->faculty()->count())->toBe(2);
    expect($it->getFacultyCount())->toBe(3);
    expect($it->hasFaculty())->toBeTrue();
});

it('excludes faculty whose department was never resolved', function (): void {
    $school = School::factory()->create();
    $department = Department::factory()->create(['school_id' => $school->id, 'code' => 'IT']);

    // A row entered with a department that does not exist keeps its string but has no FK.
    $orphan = Faculty::factory()->create([
        'school_id' => $school->id,
        'department' => 'REMOVED DEPARTMENT',
        'department_id' => null,
    ]);

    Faculty::factory()->create(['school_id' => $school->id, 'department_id' => $department->id]);

    expect($department->faculty()->count())->toBe(1);
    expect(Faculty::query()->find($orphan->id)->department)->toBe('REMOVED DEPARTMENT');
});

it('unassigns faculty when a department is deleted', function (): void {
    $school = School::factory()->create();
    $department = Department::factory()->create(['school_id' => $school->id, 'code' => 'IT']);

    $faculty = Faculty::factory()->create([
        'school_id' => $school->id,
        'department' => 'IT',
        'department_id' => $department->id,
    ]);

    $department->delete();

    $faculty = $faculty->fresh();

    expect($faculty->department_id)->toBeNull();
    // The legacy string is cleared too, so it cannot keep matching a department that is gone.
    expect($faculty->department)->toBeNull();
});

it('counts faculty per department in the executive comparison', function (): void {
    $school = School::factory()->create();
    $it = Department::factory()->create(['school_id' => $school->id, 'code' => 'IT', 'name' => 'Information Technology', 'is_active' => true]);
    $ba = Department::factory()->create(['school_id' => $school->id, 'code' => 'BA', 'name' => 'Business Administration', 'is_active' => true]);

    Faculty::factory()->count(3)->create(['school_id' => $school->id, 'department_id' => $it->id, 'department' => 'IT']);
    Faculty::factory()->count(2)->create(['school_id' => $school->id, 'department_id' => $ba->id, 'department' => 'Business Administration']);

    $payload = (new App\Dashboards\ExecutiveDesk())->data(
        new App\Models\User(['role' => App\Enums\UserRole::Dean]),
        App\Dashboards\DashboardContext::for(),
    );

    $rows = collect($payload['tables'][0]['rows'])->keyBy('code');

    expect($rows['IT']['faculty'])->toBe(3);
    expect($rows['BA']['faculty'])->toBe(2);
});

it('reports no unstaffed department when every one has faculty', function (): void {
    $school = School::factory()->create();
    $it = Department::factory()->create(['school_id' => $school->id, 'code' => 'IT', 'is_active' => true]);
    $ba = Department::factory()->create(['school_id' => $school->id, 'code' => 'BA', 'is_active' => true]);

    Faculty::factory()->create(['school_id' => $school->id, 'department_id' => $it->id]);
    Faculty::factory()->create(['school_id' => $school->id, 'department_id' => $ba->id]);

    $payload = (new App\Dashboards\HrDesk())->data(
        new App\Models\User(['role' => App\Enums\UserRole::HRManager]),
        App\Dashboards\DashboardContext::for(),
    );

    $unstaffed = collect($payload['kpis'])->firstWhere('label', 'Unstaffed Departments');

    expect($unstaffed['value'])->toBe(0);
});

it('scopes the academic desk faculty count to the department', function (): void {
    $school = School::factory()->create();
    $it = Department::factory()->create(['school_id' => $school->id, 'code' => 'IT']);
    $ba = Department::factory()->create(['school_id' => $school->id, 'code' => 'BA']);

    Faculty::factory()->count(3)->create(['school_id' => $school->id, 'department_id' => $it->id]);
    Faculty::factory()->count(2)->create(['school_id' => $school->id, 'department_id' => $ba->id]);

    $payload = (new App\Dashboards\AcademicDesk())->data(
        new App\Models\User(['role' => App\Enums\UserRole::DepartmentHead]),
        App\Dashboards\DashboardContext::for(null, $it),
    );

    $faculty = collect($payload['kpis'])->firstWhere('label', 'Faculty');

    expect($payload['scope']['id'])->toBe($it->id);
    expect($faculty['value'])->toBe(3);
});
