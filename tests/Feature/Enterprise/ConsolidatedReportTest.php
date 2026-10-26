<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\School;
use App\Models\StudentEnrollment;
use App\Models\User;
use App\Services\TenantContext;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\Models\Permission;
use Tests\Support\EnterpriseLicense;

it('aggregates only the selected licensed schools and academic period', function (): void {
    $first = School::factory()->create(['name' => 'Bayani College']);
    $second = School::factory()->create(['name' => 'Tala Academy']);
    $excluded = School::factory()->create();
    $user = User::factory()->create(['role' => UserRole::SuperAdmin, 'school_id' => $first->id]);
    $user->assignRole(UserRole::SuperAdmin);
    EnterpriseLicense::activate($first);
    EnterpriseLicense::activate($second);
    EnterpriseLicense::activate($excluded);
    StudentEnrollment::factory()->count(2)->create(['school_id' => $first->id, 'school_year' => '2026 - 2027', 'semester' => 1]);
    StudentEnrollment::factory()->create(['school_id' => $second->id, 'school_year' => '2026-2027', 'semester' => 1]);
    StudentEnrollment::factory()->create(['school_id' => $second->id, 'school_year' => '2025 - 2026', 'semester' => 1]);
    StudentEnrollment::factory()->create(['school_id' => $second->id, 'school_year' => '2026 - 2027', 'semester' => 2]);
    $deleted = StudentEnrollment::factory()->create(['school_id' => $second->id, 'school_year' => '2026 - 2027', 'semester' => 1]);
    $deleted->delete();
    StudentEnrollment::factory()->create(['school_id' => $excluded->id, 'school_year' => '2026 - 2027', 'semester' => 1]);
    app(TenantContext::class)->setCurrentSchool($first);

    $this->actingAs($user)->get(route('administrators.consolidated-reports.index', [
        'school_ids' => [$first->id, $second->id], 'school_year' => '2026-2027', 'semester' => 1,
    ]))->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
        ->component('administrators/consolidated-reports/index', false)
        ->where('report.totals.schools', 2)->where('report.totals.enrollments', 3)
        ->where('report.totals.students', 3)
        ->where('report.rows.0.school_id', $first->id)->where('report.rows.0.enrollments', 2)
        ->where('report.rows.1.school_id', $second->id)->where('report.rows.1.enrollments', 1));
});

it('rejects every report containing an unauthorized or unlicensed school', function (string $boundary): void {
    $first = School::factory()->create();
    $second = School::factory()->create();
    $user = User::factory()->create(['role' => UserRole::Registrar, 'school_id' => $first->id]);
    $user->givePermissionTo(Permission::findOrCreate('ViewAny:StudentEnrollment', 'web'));
    EnterpriseLicense::activate($first);
    if ($boundary === 'membership') {
        EnterpriseLicense::activate($second);
    } else {
        $user->addToOrganization($second);
        if ($boundary === 'enabled') {
            EnterpriseLicense::activate($second);
            DB::table('school_capabilities')->where('school_id', $second->id)->update(['enabled' => false]);
        }
    }

    foreach (['index', 'export'] as $action) {
        $this->actingAs($user)->get(route('administrators.consolidated-reports.'.$action, [
            'school_ids' => [$first->id, $second->id], 'school_year' => '2026 - 2027', 'semester' => 1,
        ]))->assertForbidden();
    }
})->with(['membership', 'license', 'enabled']);

it('requires report permission independently of a school license', function (): void {
    $school = School::factory()->create();
    $user = User::factory()->create(['role' => UserRole::Registrar, 'school_id' => $school->id]);
    EnterpriseLicense::activate($school);

    $this->actingAs($user)->get(route('administrators.consolidated-reports.index'))->assertForbidden();
    $this->actingAs($user)->get(route('administrators.consolidated-reports.export'))->assertForbidden();
});

it('includes zero count schools and exports spreadsheet safe school names', function (): void {
    $school = School::factory()->create(['name' => '=HYPERLINK("https://invalid.test")', 'code' => '+FORMULA']);
    $user = User::factory()->create(['role' => UserRole::SuperAdmin]);
    $user->assignRole(UserRole::SuperAdmin);
    EnterpriseLicense::activate($school);

    $response = $this->actingAs($user)->get(route('administrators.consolidated-reports.export', [
        'school_ids' => [$school->id], 'school_year' => '2026 - 2027', 'semester' => 1,
    ]))->assertDownload('consolidated-enrollments.csv');

    $lines = explode("\n", mb_trim($response->streamedContent()));
    $row = str_getcsv($lines[1], escape: '');
    expect($row[0])->toBe("'+FORMULA");
    expect($row[1])->toBe("'=HYPERLINK(\"https://invalid.test\")");
    expect(array_slice($row, 4))->toBe(['0', '0', '0']);
});

it('validates report filters before querying schools', function (array $filters, string $field): void {
    $user = User::factory()->create(['role' => UserRole::SuperAdmin]);
    $user->assignRole(UserRole::SuperAdmin);

    $this->actingAs($user)->getJson(route('administrators.consolidated-reports.index', $filters))->assertUnprocessable()->assertJsonValidationErrors($field);
})->with([
    'bad semester' => [['semester' => 9], 'semester'],
    'bad year' => [['school_year' => 'all years'], 'school_year'],
    'duplicate school' => [['school_ids' => [1, 1]], 'school_ids.0'],
    'bad school' => [['school_ids' => ['all']], 'school_ids.0'],
]);
