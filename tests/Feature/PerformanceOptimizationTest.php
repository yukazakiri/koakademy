<?php

declare(strict_types=1);

use App\Dashboards\Contracts\Dashboard;
use App\Dashboards\DashboardRegistry;
use App\Enums\UserRole;
use App\Models\ClassEnrollment;
use App\Models\Classes;
use App\Models\Course;
use App\Models\Faculty;
use App\Models\GeneralSetting;
use App\Models\Room;
use App\Models\Schedule;
use App\Models\Student;
use App\Models\StudentTransaction;
use App\Models\StudentTuition;
use App\Models\Subject;
use App\Models\Transaction;
use App\Models\User;
use App\Policies\GeneralSettingPolicy;
use App\Services\Ai\AiSettingsService;
use App\Services\EnrollmentBillingService;
use App\Services\GeneralSettingsService;
use App\Services\Newsletter\NewsletterSettingsService;
use App\Services\SchoolBrandingService;
use App\Services\SettingsShareService;
use App\Support\SystemManagementPermissions;
use Database\Seeders\RolesSeeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

it('hydrates zero Permission models in DashboardRegistry::visibleFor for admins and respects non-admin permissions', function (): void {
    $this->seed(RolesSeeder::class);

    $admin = User::factory()->create(['role' => UserRole::SuperAdmin]);
    $cashier = User::factory()->create(['role' => UserRole::Cashier]);
    $cashierRole = Role::query()->where('name', UserRole::Cashier->value)->firstOrFail();
    $cashier->syncRoles([$cashierRole]);
    $cashier = $cashier->fresh();

    $hydratedPermissions = 0;
    Permission::retrieved(function () use (&$hydratedPermissions): void {
        $hydratedPermissions++;
    });

    $registry = app(DashboardRegistry::class);

    $adminDesks = $registry->visibleFor($admin);
    expect($adminDesks)->toHaveCount(count($registry->all()))
        ->and($hydratedPermissions)->toBe(0);

    $cashierDeskIds = array_map(
        fn (Dashboard $desk): string => $desk->id(),
        $registry->visibleFor($cashier),
    );

    expect($cashierDeskIds)->toContain('accounting')
        ->and($cashierDeskIds)->not->toContain('executive')
        ->and($hydratedPermissions)->toBe(0);
});

it('evaluates GeneralSettingPolicy section checks without hydrating Permission models', function (): void {
    $updateSchoolPermission = SystemManagementPermissions::updatePermission('school');
    expect($updateSchoolPermission)->not->toBeNull();

    $permission = Permission::findOrCreate((string) $updateSchoolPermission, 'web');
    $user = User::factory()->create(['role' => UserRole::Registrar]);
    $user->givePermissionTo($permission);
    $user = $user->fresh();

    $hydratedPermissions = 0;
    Permission::retrieved(function () use (&$hydratedPermissions): void {
        $hydratedPermissions++;
    });

    $policy = new GeneralSettingPolicy;

    expect($policy->updateSchool($user))->toBeTrue()
        ->and($policy->viewSchool($user))->toBeTrue()
        ->and($policy->updateAi($user))->toBeFalse()
        ->and($hydratedPermissions)->toBe(0);
});

it('skips fallback role permission queries in SettingsShareService for students with an assigned Spatie role', function (): void {
    $studentRole = Role::findOrCreate(UserRole::Student->value, 'web');
    $studentUser = User::factory()->create(['role' => UserRole::Student]);
    $studentUser->syncRoles([$studentRole]);
    $studentUser = $studentUser->fresh();
    $studentUser->load('roles');

    $queries = [];
    DB::listen(function ($query) use (&$queries): void {
        $queries[] = $query->sql;
    });

    $authData = app(SettingsShareService::class)->getAuthData($studentUser);

    expect($authData['user']['permissions'])->toBe([]);

    $roleFallbackQueries = array_filter(
        $queries,
        static fn (string $sql): bool => str_contains(mb_strtolower($sql), 'from "roles"')
            || str_contains(mb_strtolower($sql), 'from `roles`'),
    );

    expect($roleFallbackQueries)->toBeEmpty();
});

it('memoizes StudentTuition billing queries across serialized accessors and invalidates on payment sync', function (): void {
    $student = Student::factory()->create([
        'id' => fake()->numberBetween(100000, 999999),
    ]);

    $tuition = StudentTuition::query()->create([
        'student_id' => $student->id,
        'school_year' => '2026 - 2027',
        'semester' => 1,
        'academic_year' => 1,
        'total_tuition' => 6500,
        'total_lectures' => 6500,
        'total_laboratory' => 0,
        'total_miscelaneous_fees' => 3500,
        'overall_tuition' => 10000,
        'total_balance' => 10000,
        'downpayment' => 2500,
        'discount' => 0,
    ]);

    EnrollmentBillingService::flushCache();

    $queriesOnFirstCall = 0;
    DB::listen(function () use (&$queriesOnFirstCall): void {
        $queriesOnFirstCall++;
    });

    $firstBalance = $tuition->formatted_total_balance;
    expect($queriesOnFirstCall)->toBeGreaterThan(0);

    $queriesAfterWarm = 0;
    DB::listen(function () use (&$queriesAfterWarm): void {
        $queriesAfterWarm++;
    });

    $formattedPaid = $tuition->formatted_total_paid;
    $progress = $tuition->payment_progress;
    $paymentStatus = $tuition->payment_status;
    $statusClass = $tuition->status_class;

    expect($firstBalance)->toContain('10,000.00')
        ->and($formattedPaid)->toContain('0.00')
        ->and($progress)->toEqual(0)
        ->and($paymentStatus)->toBe('Not Fully Paid')
        ->and($statusClass)->toContain('bg-red-100')
        ->and($queriesAfterWarm)->toBe(0);

    // Record a payment and verify syncTuitionBalance invalidates the memoized value
    $transaction = Transaction::query()->create([
        'description' => 'Tuition payment',
        'payment_method' => 'Cash',
        'settlements' => ['tuition_fee' => 10000],
        'status' => 'Paid',
        'invoicenumber' => 'INV-MEMO-001',
        'transaction_date' => '2026-04-15 12:00:00',
    ]);

    $transaction->forceFill([
        'created_at' => '2026-04-15 12:00:00',
        'updated_at' => '2026-04-15 12:00:00',
    ])->save();

    StudentTransaction::query()->create([
        'student_id' => $student->id,
        'transaction_id' => $transaction->id,
        'amount' => 10000,
        'status' => 'Paid',
    ]);

    $synced = app(EnrollmentBillingService::class)->syncTuitionBalance($tuition->refresh());

    expect($synced->total_paid)->toBe(10000.0)
        ->and($synced->payment_status)->toBe('Fully Paid')
        ->and($synced->payment_progress)->toEqual(100)
        ->and($synced->status_class)->toContain('bg-green-100');
});

it('eager loads schedules and room in Student::getCurrentClasses without N+1 queries', function (): void {
    GeneralSetting::factory()->create([
        'school_starting_date' => '2025-08-01',
        'school_ending_date' => '2026-05-30',
        'semester' => 1,
    ]);

    $course = Course::factory()->create();
    $student = Student::factory()->create(['course_id' => $course->id]);
    $room = Room::factory()->create(['name' => 'Room 301']);
    $faculty = Faculty::factory()->create();
    $subject = Subject::factory()->create(['course_id' => $course->id]);

    $class = Classes::create([
        'subject_code' => $subject->code,
        'section' => 'A',
        'room_id' => $room->id,
        'faculty_id' => $faculty->id,
        'maximum_slots' => 40,
        'semester' => '1',
        'school_year' => '2025 - 2026',
        'subject_id' => $subject->id,
    ]);

    Schedule::query()->create([
        'class_id' => $class->id,
        'room_id' => $room->id,
        'day_of_week' => 'Monday',
        'start_time' => '09:00:00',
        'end_time' => '10:30:00',
    ]);

    ClassEnrollment::create([
        'class_id' => $class->id,
        'student_id' => $student->id,
    ]);

    $currentClasses = $student->getCurrentClasses();

    expect($currentClasses)->toHaveCount(1);

    $loadedClass = $currentClasses->first()->class;

    expect($loadedClass->relationLoaded('schedules'))->toBeTrue()
        ->and($loadedClass->relationLoaded('room'))->toBeTrue();

    $extraQueries = 0;
    DB::listen(function () use (&$extraQueries): void {
        $extraQueries++;
    });

    expect($loadedClass->room?->name)->toBe('Room 301')
        ->and($loadedClass->schedules)->toHaveCount(1)
        ->and($extraQueries)->toBe(0);
});

it('reuses a single request-scoped GeneralSetting query across settings services', function (): void {
    GeneralSetting::factory()->create([
        'site_name' => 'Memoized Campus',
        'school_portal_title' => 'Memoized Portal',
    ]);

    app()->forgetScopedInstances();

    $generalSettingsQueries = 0;
    DB::listen(function ($query) use (&$generalSettingsQueries): void {
        if (str_contains(mb_strtolower($query->sql), 'general_settings')) {
            $generalSettingsQueries++;
        }
    });

    app(GeneralSettingsService::class)->getGlobalSettingsModel();
    app(AiSettingsService::class)->get();
    app(NewsletterSettingsService::class)->get();
    app(SchoolBrandingService::class)->resolve();

    expect($generalSettingsQueries)->toBe(1);
});

it('hydrates zero Subject models in AdministratorStudentManagementController::edit and skips heavy props on partial reloads', function (): void {
    GeneralSetting::factory()->create([
        'school_starting_date' => '2025-08-01',
        'school_ending_date' => '2026-05-30',
        'semester' => 1,
    ]);

    $admin = User::factory()->create(['role' => UserRole::SuperAdmin]);
    $course = Course::factory()->create();
    $student = Student::factory()->create(['course_id' => $course->id]);

    Subject::factory()->count(5)->create(['course_id' => $course->id]);

    $hydratedSubjects = 0;
    Subject::retrieved(function () use (&$hydratedSubjects): void {
        $hydratedSubjects++;
    });

    $initialResponse = $this->actingAs($admin)
        ->get(portalUrlForAdministrators(route('administrators.students.edit', $student, false)))
        ->assertOk();

    expect($hydratedSubjects)->toBe(0);

    $inertiaVersion = (string) ($initialResponse->viewData('page')['version'] ?? '');

    // Partial reload requesting only user skips evaluating options/subjects entirely
    $subjectTableQueries = 0;
    DB::listen(function ($query) use (&$subjectTableQueries): void {
        if (str_contains(mb_strtolower($query->sql), 'from "subjects"') || str_contains(mb_strtolower($query->sql), 'from `subjects`')) {
            $subjectTableQueries++;
        }
    });

    $this->actingAs($admin)
        ->withHeaders([
            'X-Inertia' => 'true',
            'X-Inertia-Version' => $inertiaVersion,
            'X-Inertia-Partial-Component' => 'administrators/students/edit',
            'X-Inertia-Partial-Data' => 'user',
        ])
        ->get(portalUrlForAdministrators(route('administrators.students.edit', $student, false)))
        ->assertOk();

    expect($subjectTableQueries)->toBe(0);
});
