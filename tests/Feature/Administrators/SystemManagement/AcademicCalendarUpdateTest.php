<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\GeneralSetting;
use App\Models\School;
use App\Models\User;
use Spatie\Permission\Models\Permission;

use function Pest\Laravel\actingAs;

function grantAcademicCalendarPermission(User $user): void
{
    foreach (['View:SystemManagementSchool', 'Update:SystemManagementSchool'] as $permission) {
        Permission::firstOrCreate([
            'name' => $permission,
            'guard_name' => 'web',
        ]);
    }

    $user->givePermissionTo(['View:SystemManagementSchool', 'Update:SystemManagementSchool']);
}

it('updates global academic calendar defaults', function (): void {
    $admin = User::factory()->create([
        'role' => UserRole::Admin,
        'school_id' => null,
    ]);
    grantAcademicCalendarPermission($admin);
    School::factory()->create();

    actingAs($admin)
        ->put(route('administrators.system-management.academic-calendar.update'), [
            'semester' => 2,
            'school_starting_date' => '2025-06-01',
            'school_ending_date' => '2026-03-31',
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    $settings = GeneralSetting::query()->first();

    expect($settings)->not->toBeNull()
        ->and($settings->semester)->toBe(2)
        ->and($settings->school_starting_date->format('Y-m-d'))->toBe('2025-06-01')
        ->and($settings->school_ending_date->format('Y-m-d'))->toBe('2026-03-31');
});

it('validates academic calendar fields', function (): void {
    $admin = User::factory()->create([
        'role' => UserRole::Admin,
        'school_id' => null,
    ]);
    grantAcademicCalendarPermission($admin);
    School::factory()->create();

    actingAs($admin)
        ->put(route('administrators.system-management.academic-calendar.update'), [
            'semester' => 3,
            'school_starting_date' => 'invalid-date',
            'school_ending_date' => '2024-01-01',
        ])
        ->assertSessionHasErrors(['semester', 'school_starting_date']);
});

it('validates that ending date is after or equal to starting date', function (): void {
    $admin = User::factory()->create([
        'role' => UserRole::Admin,
        'school_id' => null,
    ]);
    grantAcademicCalendarPermission($admin);
    School::factory()->create();

    actingAs($admin)
        ->put(route('administrators.system-management.academic-calendar.update'), [
            'semester' => 1,
            'school_starting_date' => '2025-06-01',
            'school_ending_date' => '2025-05-01',
        ])
        ->assertSessionHasErrors(['school_ending_date']);
});
