<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\Department;
use App\Models\School;
use App\Models\User;
use Illuminate\Support\Carbon;
use Spatie\Permission\Models\Permission;

it('returns complete analytics for the administrator users page', function (): void {
    Carbon::setTestNow(Carbon::parse('2026-06-26 12:00:00'));

    Permission::firstOrCreate([
        'name' => 'ViewAny:User',
        'guard_name' => 'web',
    ]);

    $school = School::factory()->withNameAndCode('School of Information Technology', 'SIT')->create();
    $department = Department::factory()->forSchool($school)->withNameAndCode('Information Technology', 'IT')->create();

    $admin = User::factory()->create([
        'role' => UserRole::Admin,
        'security_two_factor_enabled' => false,
        'created_at' => now()->subMonths(3),
        'updated_at' => now()->subMonths(3),
    ]);
    $admin->givePermissionTo('ViewAny:User');

    User::factory()->create([
        'name' => 'Today Student',
        'role' => UserRole::Student,
        'school_id' => $school->id,
        'department_id' => $department->id,
        'security_two_factor_enabled' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    User::factory()->unverified()->create([
        'name' => 'Recent Instructor',
        'role' => UserRole::Instructor,
        'security_two_factor_enabled' => false,
        'created_at' => now()->subDays(5),
        'updated_at' => now()->subDays(5),
    ]);

    User::factory()->create([
        'name' => 'Recent Cashier',
        'role' => UserRole::Cashier,
        'school_id' => $school->id,
        'security_two_factor_enabled' => false,
        'created_at' => now()->subDays(10),
        'updated_at' => now()->subDays(10),
    ]);

    User::factory()->create([
        'name' => 'Previous Window User',
        'role' => UserRole::User,
        'security_two_factor_enabled' => false,
        'created_at' => now()->subDays(40),
        'updated_at' => now()->subDays(40),
    ]);

    $deletedUser = User::factory()->create([
        'role' => UserRole::GraduateStudent,
        'created_at' => now()->subDays(2),
        'updated_at' => now()->subDays(2),
    ]);
    $deletedUser->delete();

    $response = $this->actingAs($admin)
        ->get(portalUrlForAdministrators('/administrators/users'))
        ->assertOk();

    $analytics = $response->inertiaProps('analytics');
    $users = $response->inertiaProps('users.data');
    $todayStudent = collect($users)->firstWhere('name', 'Today Student');

    expect($todayStudent)->not->toBeNull()
        ->and($todayStudent)->toHaveKeys(['id', 'created_at', 'last_login_at', 'security_two_factor_enabled']);

    expect($analytics['total_users'])->toBe(5)
        ->and($analytics['all_time_users'])->toBe(6)
        ->and($analytics['trashed_users'])->toBe(1)
        ->and($analytics['new_users_today'])->toBe(1)
        ->and($analytics['new_users_30_days'])->toBe(3)
        ->and($analytics['previous_30_days_users'])->toBe(1)
        ->and($analytics['growth_rate'])->toBe(200)
        ->and($analytics['verified_users'])->toBe(4)
        ->and($analytics['unverified_users'])->toBe(1)
        ->and($analytics['verification_rate'])->toBe(80)
        ->and($analytics['two_factor_enabled_users'])->toBe(1)
        ->and($analytics['two_factor_rate'])->toBe(20)
        ->and($analytics['assigned_users'])->toBe(2)
        ->and($analytics['assignment_rate'])->toBe(40)
        ->and($analytics['registrations_chart'])->toHaveCount(30)
        ->and($analytics['registrations_chart'][29])->toMatchArray([
            'date' => '2026-06-26',
            'count' => 1,
            'cumulative' => 5,
        ]);

    expect(collect($analytics['role_distribution'])->pluck('label')->all())
        ->toContain('Student', 'Instructor', 'Cashier', 'User');

    $schoolDistribution = collect($analytics['school_distribution'])->firstWhere('name', 'School of Information Technology');
    $departmentDistribution = collect($analytics['department_distribution'])->firstWhere('name', 'Unassigned');

    expect($schoolDistribution)->toMatchArray([
        'name' => 'School of Information Technology',
        'count' => 2,
        'percentage' => 40,
    ]);

    expect($departmentDistribution)->toMatchArray([
        'name' => 'Unassigned',
        'count' => 4,
        'percentage' => 80,
    ]);

    expect($analytics['recent_users'])->toHaveCount(5)
        ->and($analytics['recent_users'][0]['name'])->toBe('Today Student');

    Carbon::setTestNow();
});
