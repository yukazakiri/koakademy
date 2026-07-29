<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\GeneralSetting;
use App\Models\User;
use Spatie\Permission\Models\Permission;

it('allows authorized administrators to configure official finance documents', function (): void {
    $user = User::factory()->create(['role' => UserRole::Admin]);
    foreach (['View:SystemManagementFinanceDocuments', 'Update:SystemManagementFinanceDocuments'] as $permission) {
        Permission::findOrCreate($permission, 'web');
    }
    $user->givePermissionTo([
        'View:SystemManagementFinanceDocuments',
        'Update:SystemManagementFinanceDocuments',
    ]);

    $this->actingAs($user)
        ->put(portalUrlForAdministrators('/administrators/system-management/finance-documents'), [
            'automatic_receipts_enabled' => false,
            'require_paper_or_reference' => true,
            'manual_invoices_enabled' => true,
        ])
        ->assertRedirect();

    $configuration = GeneralSetting::query()->firstOrFail()->more_configs['finance_documents'];
    expect($configuration)->toBe([
        'automatic_receipts_enabled' => false,
        'require_paper_or_reference' => true,
        'manual_invoices_enabled' => true,
    ]);
});

it('forbids administrators without finance document settings permission', function (): void {
    $user = User::factory()->create(['role' => UserRole::Admin]);

    $this->actingAs($user)
        ->put(portalUrlForAdministrators('/administrators/system-management/finance-documents'), [
            'automatic_receipts_enabled' => true,
            'require_paper_or_reference' => true,
            'manual_invoices_enabled' => true,
        ])
        ->assertForbidden();
});
