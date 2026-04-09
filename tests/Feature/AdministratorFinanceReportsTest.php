<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\User;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

function grantFinancePermission(User $user): void
{
    Permission::findOrCreate('View:Cashier', 'web');

    $role = Role::findOrCreate($user->role->value, 'web');
    $role->syncPermissions(['View:Cashier']);
}

it('redirects guests away from finance reports page', function (): void {
    $this->get(portalUrlForAdministrators('/administrators/finance/reports'))
        ->assertRedirect('/login');
});

it('forbids non-administrative users from accessing finance reports', function (): void {
    $user = User::factory()->create([
        'role' => UserRole::Instructor,
        'faculty_id_number' => 'FAC-101',
    ]);

    $this->actingAs($user)
        ->get(portalUrlForAdministrators('/administrators/finance/reports'))
        ->assertForbidden();
});

it('allows administrative users to view the finance reports page', function (): void {
    $user = User::factory()->create([
        'role' => UserRole::Admin,
    ]);

    grantFinancePermission($user);

    $this->actingAs($user)
        ->get(portalUrlForAdministrators('/administrators/finance/reports'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->component('administrators/finance/reports', false)
            ->has('filters')
            ->has('filters.school_years')
            ->has('filters.semesters')
            ->has('filters.payment_methods')
            ->has('filters.current_school_year')
            ->has('filters.current_semester')
        );
});

it('allows cashier users to view the finance reports page', function (): void {
    $user = User::factory()->create([
        'role' => UserRole::Cashier,
    ]);

    grantFinancePermission($user);

    $this->actingAs($user)
        ->get(portalUrlForAdministrators('/administrators/finance/reports'))
        ->assertOk();
});

it('forbids registrar users from accessing finance reports without cashier permission', function (): void {
    $user = User::factory()->create([
        'role' => UserRole::Registrar,
    ]);

    $this->actingAs($user)
        ->get(portalUrlForAdministrators('/administrators/finance/reports'))
        ->assertForbidden();
});

it('returns daily collection report data', function (): void {
    $user = User::factory()->create([
        'role' => UserRole::Admin,
    ]);

    grantFinancePermission($user);

    $this->actingAs($user)
        ->getJson(portalUrlForAdministrators('/administrators/finance/reports/daily-collection?date='.now()->format('Y-m-d')))
        ->assertOk()
        ->assertJsonStructure([
            'transactions',
            'summary' => [
                'total_transactions',
                'total_amount',
                'by_payment_method',
                'date',
            ],
        ]);
});

it('returns collection report data for date range', function (): void {
    $user = User::factory()->create([
        'role' => UserRole::Admin,
    ]);

    grantFinancePermission($user);

    $this->actingAs($user)
        ->getJson(portalUrlForAdministrators('/administrators/finance/reports/collection?start_date='.now()->subDays(30)->format('Y-m-d').'&end_date='.now()->format('Y-m-d')))
        ->assertOk()
        ->assertJsonStructure([
            'transactions',
            'summary' => [
                'total_transactions',
                'total_amount',
                'by_payment_method',
                'daily_breakdown',
                'start_date',
                'end_date',
            ],
        ]);
});

it('returns outstanding balances report data', function (): void {
    $user = User::factory()->create([
        'role' => UserRole::Admin,
    ]);

    grantFinancePermission($user);

    $this->actingAs($user)
        ->getJson(portalUrlForAdministrators('/administrators/finance/reports/outstanding-balances'))
        ->assertOk()
        ->assertJsonStructure([
            'students',
            'summary' => [
                'total_students',
                'total_outstanding',
                'total_collectible',
                'total_collected',
                'collection_rate',
                'school_year',
                'semester',
            ],
        ]);
});

it('returns scholarship report data', function (): void {
    $user = User::factory()->create([
        'role' => UserRole::Admin,
    ]);

    grantFinancePermission($user);

    $this->actingAs($user)
        ->getJson(portalUrlForAdministrators('/administrators/finance/reports/scholarship'))
        ->assertOk()
        ->assertJsonStructure([
            'scholars',
            'summary' => [
                'total_scholars',
                'total_discount_granted',
                'original_revenue',
                'discounted_revenue',
                'by_discount_level',
                'school_year',
                'semester',
            ],
        ]);
});

it('returns revenue breakdown report data', function (): void {
    $user = User::factory()->create([
        'role' => UserRole::Admin,
    ]);

    grantFinancePermission($user);

    $this->actingAs($user)
        ->getJson(portalUrlForAdministrators('/administrators/finance/reports/revenue-breakdown'))
        ->assertOk()
        ->assertJsonStructure([
            'summary' => [
                'total_revenue',
                'total_transactions',
                'breakdown',
                'monthly_trend',
                'school_year',
                'semester',
            ],
        ]);
});

it('returns fully paid students report data', function (): void {
    $user = User::factory()->create([
        'role' => UserRole::Admin,
    ]);

    grantFinancePermission($user);

    $this->actingAs($user)
        ->getJson(portalUrlForAdministrators('/administrators/finance/reports/fully-paid'))
        ->assertOk()
        ->assertJsonStructure([
            'students',
            'summary' => [
                'total_students',
                'total_collected',
                'school_year',
                'semester',
            ],
        ]);
});

it('returns cashier performance report data', function (): void {
    $user = User::factory()->create([
        'role' => UserRole::Admin,
    ]);

    grantFinancePermission($user);

    $this->actingAs($user)
        ->getJson(portalUrlForAdministrators('/administrators/finance/reports/cashier-performance?start_date='.now()->subDays(30)->format('Y-m-d').'&end_date='.now()->format('Y-m-d')))
        ->assertOk()
        ->assertJsonStructure([
            'cashiers',
            'summary' => [
                'total_cashiers',
                'total_transactions',
                'total_collected',
                'start_date',
                'end_date',
            ],
        ]);
});

it('validates required date fields for collection report', function (): void {
    $user = User::factory()->create([
        'role' => UserRole::Admin,
    ]);

    grantFinancePermission($user);

    $this->actingAs($user)
        ->getJson(portalUrlForAdministrators('/administrators/finance/reports/collection'))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['start_date', 'end_date']);
});

it('validates required date fields for cashier performance report', function (): void {
    $user = User::factory()->create([
        'role' => UserRole::Admin,
    ]);

    grantFinancePermission($user);

    $this->actingAs($user)
        ->getJson(portalUrlForAdministrators('/administrators/finance/reports/cashier-performance'))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['start_date', 'end_date']);
});
