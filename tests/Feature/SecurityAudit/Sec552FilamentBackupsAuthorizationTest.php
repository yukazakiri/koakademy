<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Filament\Pages\Backups;
use App\Filament\Pages\Timetable;
use App\Models\GeneralSetting;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Spatie\Permission\Models\Permission;

beforeEach(function (): void {
    Permission::findOrCreate('page_Timetable', 'web');
    Permission::findOrCreate('ViewAny:Classes', 'web');

    GeneralSetting::factory()->create([
        'school_starting_date' => '2026-06-01',
        'school_ending_date' => '2027-03-31',
        'semester' => 1,
    ]);
});

it('denies Backups page access to unauthenticated and non-super-admin roles', function (UserRole $role): void {
    $user = User::factory()->create(['role' => $role]);
    Auth::login($user);

    expect(Backups::canAccess())->toBeFalse()
        ->and(Backups::shouldRegisterNavigation())->toBeFalse();

    $this->actingAs($user)
        ->get('/admin/backups')
        ->assertForbidden();
})->with([
    'cashier' => UserRole::Cashier,
    'registrar' => UserRole::Registrar,
    'it_support' => UserRole::ITSupport,
    'hr_manager' => UserRole::HRManager,
    'admin' => UserRole::Admin,
    'administrative_assistant' => UserRole::AdministrativeAssistant,
]);

it('allows Backups page access only to super administrators and developers', function (UserRole $role): void {
    $user = User::factory()->create(['role' => $role]);
    Auth::login($user);

    expect(Backups::canAccess())->toBeTrue()
        ->and(Backups::shouldRegisterNavigation())->toBeTrue();
})->with([
    'super_admin' => UserRole::SuperAdmin,
    'developer' => UserRole::Developer,
]);

it('denies Timetable page access to unauthorized roles and grants it to permitted users', function (): void {
    $cashier = User::factory()->create(['role' => UserRole::Cashier]);
    Auth::login($cashier);

    expect(Timetable::canAccess())->toBeFalse();

    $registrar = User::factory()->create(['role' => UserRole::Registrar]);
    $registrar->givePermissionTo('ViewAny:Classes');
    Auth::login($registrar);

    expect(Timetable::canAccess())->toBeTrue();
});

it('excludes environment secret files from backup archives', function (): void {
    $excluded = config('backup.backup.source.files.exclude');

    expect($excluded)->toContain(base_path('.env'))
        ->and($excluded)->toContain(base_path('.env.production'));
});
