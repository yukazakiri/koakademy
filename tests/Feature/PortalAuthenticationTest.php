<?php

declare(strict_types=1);

use App\Models\Account;
use Filament\Facades\Filament;

test('account model can access portal panel', function () {
    // Create an active account
    $account = Account::factory()->active()->create();

    // Set the current panel to portal
    Filament::setCurrentPanel('portal');

    // Test that the account can access the portal panel
    expect($account->canAccessPanel(Filament::getCurrentPanel()))->toBeTrue();
});

test('inactive account cannot access portal panel', function () {
    // Create an inactive account
    $account = Account::factory()->inactive()->create();

    // Set the current panel to portal
    Filament::setCurrentPanel('portal');

    // Test that the inactive account cannot access the portal panel
    expect($account->canAccessPanel(Filament::getCurrentPanel()))->toBeFalse();
});

test('account cannot access non-portal panels', function () {
    // Create an active account
    $account = Account::factory()->active()->create();

    // Test that the account cannot access admin panel (or any other panel)
    expect($account->canAccessPanel(Filament::getPanel('admin')))->toBeFalse();
});

test('portal panel uses correct auth guard', function () {
    // Get the portal panel configuration
    $panel = Filament::getPanel('portal');

    // The panel should be configured to use the 'portal' guard
    expect($panel->getAuthGuard())->toBe('portal');
});
