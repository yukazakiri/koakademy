<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\User;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

function grantPaymentWorkspacePermission(User $user): void
{
    Permission::findOrCreate('View:Cashier', 'web');
    $role = Role::findOrCreate($user->role->value, 'web');
    $role->syncPermissions(['View:Cashier']);
    $user->syncRoles([$role]);
}

it('shares default finance workspace preferences only with authorized cashiers', function (): void {
    $cashier = User::factory()->create(['role' => UserRole::Cashier]);
    grantPaymentWorkspacePermission($cashier);

    $this->actingAs($cashier)
        ->get(portalUrlForAdministrators('/administrators/settings'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('can_configure_payment_workspace', true)
            ->where('payment_workspace.layout', 'guided')
            ->where('payment_workspace.density', 'comfortable')
            ->where('payment_workspace.history_visibility', 'auto')
            ->where('payment_workspace.default_payment_method', 'Cash')
            ->has('payment_workspace_url')
            ->has('payment_methods')
        );

    $administrator = User::factory()->create(['role' => UserRole::Admin]);

    $this->actingAs($administrator)
        ->get(portalUrlForAdministrators('/administrators/settings'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('can_configure_payment_workspace', false)
            ->where('payment_workspace', null)
            ->where('payment_workspace_url', null)
        );
});

it('merges finance workspace preferences without overwriting other user preferences', function (): void {
    $cashier = User::factory()->create(['role' => UserRole::Cashier]);
    $cashier->forceFill([
        'preferences' => [
            'locale' => 'fil',
            'finance' => ['show_collection_tip' => false],
        ],
    ])->save();
    grantPaymentWorkspacePermission($cashier);

    $this->actingAs($cashier)
        ->put(portalUrlForAdministrators('/administrators/settings/payment-workspace'), [
            'layout' => 'spreadsheet',
            'density' => 'compact',
            'history_visibility' => 'hidden',
            'default_payment_method' => 'GCash',
        ])
        ->assertRedirect();

    expect($cashier->refresh()->preferences)
        ->toMatchArray([
            'locale' => 'fil',
            'finance' => [
                'show_collection_tip' => false,
                'payment_workspace' => [
                    'layout' => 'spreadsheet',
                    'density' => 'compact',
                    'history_visibility' => 'hidden',
                    'default_payment_method' => 'GCash',
                ],
            ],
        ]);
});

it('rejects invalid finance workspace preferences and unauthorized updates', function (): void {
    $cashier = User::factory()->create(['role' => UserRole::Cashier]);
    grantPaymentWorkspacePermission($cashier);

    $this->actingAs($cashier)
        ->put(portalUrlForAdministrators('/administrators/settings/payment-workspace'), [
            'layout' => 'whatever',
            'density' => 'dense',
            'history_visibility' => 'sometimes',
            'default_payment_method' => 'Crypto',
        ])
        ->assertSessionHasErrors(['layout', 'density', 'history_visibility', 'default_payment_method']);

    $administrator = User::factory()->create(['role' => UserRole::Admin]);

    $this->actingAs($administrator)
        ->put(portalUrlForAdministrators('/administrators/settings/payment-workspace'), [
            'layout' => 'guided',
            'density' => 'comfortable',
            'history_visibility' => 'auto',
            'default_payment_method' => 'Cash',
        ])
        ->assertForbidden();
});
