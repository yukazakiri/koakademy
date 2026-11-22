<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

it('denies viewPulse to the previously hardcoded Gmail address when user is not super_admin or developer', function (): void {
    $user = User::factory()->create([
        'email' => 'superadminh@gmail.com',
        'role' => UserRole::Student,
    ]);

    expect(Gate::forUser($user)->allows('viewPulse'))->toBeFalse();
});

it('grants viewPulse only to super administrators and developers', function (UserRole $role, bool $expected): void {
    $user = User::factory()->create(['role' => $role]);

    expect(Gate::forUser($user)->allows('viewPulse'))->toBe($expected);
})->with([
    'super_admin' => [UserRole::SuperAdmin, true],
    'developer' => [UserRole::Developer, true],
    'admin' => [UserRole::Admin, false],
    'registrar' => [UserRole::Registrar, false],
    'student' => [UserRole::Student, false],
]);
