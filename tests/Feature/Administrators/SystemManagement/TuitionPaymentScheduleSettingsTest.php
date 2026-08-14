<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\GeneralSetting;
use App\Models\User;
use Spatie\Permission\Models\Permission;

it('stores validated payment schedule profiles for authorized administrators', function (): void {
    $user = User::factory()->create(['role' => UserRole::Admin]);
    Permission::findOrCreate('Update:SystemManagementTuitionPaymentSchedule', 'web');
    $user->givePermissionTo('Update:SystemManagementTuitionPaymentSchedule');

    $profile = [
        'enabled' => true,
        'percentages' => ['prelim' => 30, 'midterm' => 30, 'finals' => 40],
        'rounding_increment' => 100,
        'rounding_mode' => 'nearest',
        'rounded_terms' => ['prelim', 'midterm'],
        'remainder_term' => 'finals',
    ];

    $this->actingAs($user)
        ->put(portalUrlForAdministrators('/administrators/system-management/tuition-payment-schedule'), [
            'profiles' => ['college' => $profile, 'shs' => [...$profile, 'enabled' => false], 'tesda' => [...$profile, 'enabled' => false], 'dhrt' => [...$profile, 'enabled' => false]],
        ])
        ->assertRedirect();

    expect(GeneralSetting::query()->firstOrFail()->more_configs['tuition_payment_schedule']['profiles']['college'])
        ->toMatchArray($profile);
});

it('rejects percentages that do not total one hundred', function (): void {
    $user = User::factory()->create(['role' => UserRole::Admin]);
    Permission::findOrCreate('Update:SystemManagementTuitionPaymentSchedule', 'web');
    $user->givePermissionTo('Update:SystemManagementTuitionPaymentSchedule');

    $this->actingAs($user)
        ->put(portalUrlForAdministrators('/administrators/system-management/tuition-payment-schedule'), [
            'profiles' => ['college' => [
                'enabled' => true,
                'percentages' => ['prelim' => 20, 'midterm' => 20, 'finals' => 20],
                'rounding_increment' => 100,
                'rounding_mode' => 'nearest',
                'rounded_terms' => ['prelim', 'midterm'],
                'remainder_term' => 'finals',
            ]],
        ])
        ->assertSessionHasErrors('profiles.college.percentages');
});
