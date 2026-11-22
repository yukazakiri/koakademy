<?php

declare(strict_types=1);

use App\Enums\SchoolLevel;
use App\Enums\UserRole;
use App\Models\GeneralSetting;
use App\Models\School;
use App\Models\User;

it('performs unattended setup with defaults', function (): void {
    $this->artisan('app:setup --unattended')
        ->assertSuccessful();

    $school = School::query()->first();
    expect($school)->not->toBeNull()
        ->and($school?->name)->toBe('KoAkademy Institution')
        ->and($school?->code)->toBe('KOA')
        ->and($school?->country_code)->toBe('US')
        ->and($school?->school_level)->toBe(SchoolLevel::HigherEducation);

    $user = User::query()->where('role', UserRole::SuperAdmin)->first();
    expect($user)->not->toBeNull()
        ->and($user?->email)->toBe('admin@example.com');

    $setting = GeneralSetting::query()->first();
    expect($setting)->not->toBeNull()
        ->and($setting?->is_setup)->toBeTrue()
        ->and($setting?->currency)->toBe('USD');
});

it('performs unattended setup with custom options', function (): void {
    $this->artisan('app:setup', [
        '--admin-name' => 'Dr. Alexander Vance',
        '--admin-email' => 'vance@blackmesa.edu',
        '--admin-password' => 'ResonanceCascade99!',
        '--school-name' => 'Black Mesa Research Facility',
        '--school-code' => 'BMRF',
        '--country-code' => 'US',
        '--school-level' => 'higher_education',
        '--currency' => 'USD',
        '--unattended' => true,
    ])->assertSuccessful();

    $school = School::query()->where('code', 'BMRF')->first();
    expect($school)->not->toBeNull()
        ->and($school?->name)->toBe('Black Mesa Research Facility');

    $user = User::query()->where('email', 'vance@blackmesa.edu')->first();
    expect($user)->not->toBeNull()
        ->and($user?->name)->toBe('Dr. Alexander Vance')
        ->and($user?->role)->toBe(UserRole::SuperAdmin);
});

it('does not re-run setup without force when already setup', function (): void {
    GeneralSetting::factory()->create(['is_setup' => true]);

    $this->artisan('app:setup --unattended')
        ->expectsOutputToContain('KoAkademy is already set up')
        ->assertSuccessful();
});

it('fails closed for unattended setup in production when no admin password is supplied', function (): void {
    $previousEnv = app()->environment();
    app()->detectEnvironment(static fn (): string => 'production');

    try {
        $this->artisan('app:setup --unattended')
            ->expectsOutputToContain('Admin password (--admin-password or KOAKADEMY_ADMIN_PASSWORD) is required for unattended setup in production.')
            ->assertFailed();

        expect(User::query()->where('role', UserRole::SuperAdmin)->exists())->toBeFalse();
    } finally {
        app()->detectEnvironment(static fn (): string => (string) $previousEnv);
    }
});

it('accepts KOAKADEMY_ADMIN_PASSWORD from the environment for unattended production setup', function (): void {
    $previousEnv = app()->environment();
    app()->detectEnvironment(static fn (): string => 'production');
    $_ENV['KOAKADEMY_ADMIN_PASSWORD'] = 'EnvSuppliedSecret99!';
    $_SERVER['KOAKADEMY_ADMIN_PASSWORD'] = 'EnvSuppliedSecret99!';
    putenv('KOAKADEMY_ADMIN_PASSWORD=EnvSuppliedSecret99!');

    try {
        $this->artisan('app:setup', [
            '--admin-email' => 'ops@school.example',
            '--unattended' => true,
        ])->assertSuccessful();

        $user = User::query()->where('email', 'ops@school.example')->first();
        expect($user)->not->toBeNull()
            ->and($user?->role)->toBe(UserRole::SuperAdmin);
    } finally {
        unset($_ENV['KOAKADEMY_ADMIN_PASSWORD'], $_SERVER['KOAKADEMY_ADMIN_PASSWORD']);
        putenv('KOAKADEMY_ADMIN_PASSWORD');
        app()->detectEnvironment(static fn (): string => (string) $previousEnv);
    }
});
