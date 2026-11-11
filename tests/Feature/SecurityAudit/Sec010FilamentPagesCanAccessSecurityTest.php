<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Filament\Pages\GeneralSettings;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Auth;
use Modules\Cashier\Filament\Pages\Cashier;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * SEC-010 · Cashier and GeneralSettings Filament pages have canAccess() commented out.
 *
 * Verifies that:
 * 1. Cashier::canAccess() rejects unauthenticated and unauthorized callers (e.g. it_support, maintenance_staff).
 * 2. Cashier::canAccess() permits authorized callers (super_admin, admin, cashier, page_Cashier, View:Cashier).
 * 3. GeneralSettings::canAccess() rejects unauthenticated and unauthorized callers (e.g. it_support, cashier, security_guard).
 * 4. GeneralSettings::canAccess() permits authorized callers (super_admin, admin, page_GeneralSetting, View:GeneralSettings).
 * 5. Direct navigation to /admin/cashier and /admin/general-settings returns 403 Forbidden for unauthorized accounts.
 * 6. The General Settings user menu action in AdminPanelProvider is visible only to authorized callers.
 */
beforeEach(function (): void {
    Permission::findOrCreate('page_Cashier', 'web');
    Permission::findOrCreate('View:Cashier', 'web');
    Permission::findOrCreate('page_GeneralSetting', 'web');
    Permission::findOrCreate('View:GeneralSettings', 'web');

    App\Models\GeneralSetting::factory()->create([
        'school_starting_date' => '2026-06-01',
        'school_ending_date' => '2027-03-31',
        'semester' => 1,
    ]);
});

describe('SEC-010 Cashier::canAccess() authorization', function (): void {
    it('denies access to unauthenticated callers', function (): void {
        Auth::logout();

        expect(Cashier::canAccess())->toBeFalse();
    });

    it('denies access to unauthorized panel roles', function (UserRole $role): void {
        $user = User::factory()->create([
            'role' => $role,
        ]);
        Auth::login($user);

        expect(Cashier::canAccess())->toBeFalse();
    })->with([
        'it_support' => UserRole::ITSupport,
        'security_guard' => UserRole::SecurityGuard,
        'maintenance_staff' => UserRole::MaintenanceStaff,
        'librarian' => UserRole::Librarian,
        'administrative_assistant' => UserRole::AdministrativeAssistant,
        'instructor' => UserRole::Instructor,
        'student' => UserRole::Student,
    ]);

    it('allows access to super administrators and developers', function (UserRole $role): void {
        $user = User::factory()->create([
            'role' => $role,
        ]);
        Auth::login($user);

        expect(Cashier::canAccess())->toBeTrue();
    })->with([
        'super_admin' => UserRole::SuperAdmin,
        'developer' => UserRole::Developer,
    ]);

    it('allows access to administrators holding cashier page permission', function (): void {
        $user = User::factory()->create([
            'role' => UserRole::Admin,
        ]);
        $user->givePermissionTo('page_Cashier');
        Auth::login($user);

        expect(Cashier::canAccess())->toBeTrue();
    });

    it('allows access to cashier role holding View:Cashier', function (): void {
        $user = User::factory()->create([
            'role' => UserRole::Cashier,
        ]);
        $user->givePermissionTo('View:Cashier');
        Auth::login($user);

        expect(Cashier::canAccess())->toBeTrue();
    });

    it('allows access to users holding page_Cashier permission', function (): void {
        $user = User::factory()->create([
            'role' => UserRole::AdministrativeAssistant,
        ]);
        $user->givePermissionTo('page_Cashier');
        Auth::login($user);

        expect(Cashier::canAccess())->toBeTrue();
    });
});

describe('SEC-010 GeneralSettings::canAccess() authorization', function (): void {
    it('denies access to unauthenticated callers', function (): void {
        Auth::logout();

        expect(GeneralSettings::canAccess())->toBeFalse();
    });

    it('denies access to unauthorized panel roles including it_support', function (UserRole $role): void {
        $user = User::factory()->create([
            'role' => $role,
        ]);
        Auth::login($user);

        expect(GeneralSettings::canAccess())->toBeFalse();
    })->with([
        'it_support' => UserRole::ITSupport,
        'security_guard' => UserRole::SecurityGuard,
        'maintenance_staff' => UserRole::MaintenanceStaff,
        'librarian' => UserRole::Librarian,
        'administrative_assistant' => UserRole::AdministrativeAssistant,
        'cashier' => UserRole::Cashier,
        'instructor' => UserRole::Instructor,
        'student' => UserRole::Student,
    ]);

    it('allows access to super administrators and developers', function (UserRole $role): void {
        $user = User::factory()->create([
            'role' => $role,
        ]);
        Auth::login($user);

        expect(GeneralSettings::canAccess())->toBeTrue();
    })->with([
        'super_admin' => UserRole::SuperAdmin,
        'developer' => UserRole::Developer,
    ]);

    it('allows access to users holding page_GeneralSetting permission', function (): void {
        $user = User::factory()->create([
            'role' => UserRole::AdministrativeAssistant,
        ]);
        $user->givePermissionTo('page_GeneralSetting');
        Auth::login($user);

        expect(GeneralSettings::canAccess())->toBeTrue();
    });

    it('allows access to users holding View:GeneralSettings permission', function (): void {
        $user = User::factory()->create([
            'role' => UserRole::AdministrativeAssistant,
        ]);
        $user->givePermissionTo('View:GeneralSettings');
        Auth::login($user);

        expect(GeneralSettings::canAccess())->toBeTrue();
    });
});

describe('SEC-010 Filament panel HTTP route protection', function (): void {
    it('blocks unauthorized callers from reaching /admin/cashier', function (): void {
        $user = User::factory()->create([
            'role' => UserRole::ITSupport,
        ]);

        $this->actingAs($user, 'web')
            ->get('/admin/cashier')
            ->assertForbidden();
    });

    it('allows authorized cashiers to reach /admin/cashier', function (): void {
        $user = User::factory()->create([
            'role' => UserRole::Cashier,
        ]);
        $user->givePermissionTo('View:Cashier');

        $this->actingAs($user, 'web')
            ->get('/admin/cashier')
            ->assertSuccessful();
    });

    it('blocks unauthorized callers including it_support from reaching /admin/general-settings', function (): void {
        $user = User::factory()->create([
            'role' => UserRole::ITSupport,
        ]);

        $this->actingAs($user, 'web')
            ->get('/admin/general-settings')
            ->assertForbidden();
    });

    it('allows super administrators to reach /admin/general-settings', function (): void {
        $user = User::factory()->create([
            'role' => UserRole::SuperAdmin,
        ]);

        $this->actingAs($user, 'web')
            ->get('/admin/general-settings')
            ->assertSuccessful();
    });
});

describe('SEC-010 User menu item visibility', function (): void {
    it('hides General Settings user menu item from unauthorized users', function (): void {
        $user = User::factory()->create([
            'role' => UserRole::ITSupport,
        ]);
        Auth::login($user);

        $panel = Filament::getPanel('admin');
        $userMenuItems = $panel->getUserMenuItems();

        expect(array_key_exists('general-settings', $userMenuItems))->toBeFalse();
    });

    it('shows General Settings user menu item to super administrators', function (): void {
        $user = User::factory()->create([
            'role' => UserRole::SuperAdmin,
        ]);
        Auth::login($user);

        $panel = Filament::getPanel('admin');
        $userMenuItems = $panel->getUserMenuItems();

        expect(array_key_exists('general-settings', $userMenuItems))->toBeTrue();
    });
});

describe('SEC-010 Migration grants page_Cashier to target roles', function (): void {
    it('grants page_Cashier to target staff and administrator roles via migration', function (): void {
        $targetRoles = [
            UserRole::Admin->value,
            UserRole::Cashier->value,
            UserRole::AccountingOfficer->value,
            UserRole::BursarOfficer->value,
        ];

        foreach ($targetRoles as $roleName) {
            $role = Role::findOrCreate($roleName, 'web');
            if ($role->hasPermissionTo('page_Cashier', 'web')) {
                $role->revokePermissionTo('page_Cashier');
            }
            expect($role->hasPermissionTo('page_Cashier', 'web'))->toBeFalse();
        }

        $migration = require database_path('migrations/2026_11_11_000001_grant_cashier_and_general_settings_page_permissions.php');
        $migration->up();

        foreach ($targetRoles as $roleName) {
            $role = Role::findByName($roleName, 'web');
            expect($role->hasPermissionTo('page_Cashier', 'web'))->toBeTrue();
        }
    });
});
