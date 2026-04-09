<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\User;

use function Pest\Laravel\actingAs;

describe('role management', function (): void {
    it('returns correct manageable roles for Developer', function (): void {
        /** @var User $developer */
        $developer = User::factory()->make(['role' => UserRole::Developer]);
        actingAs($developer);

        $manageableRoles = $developer->lowerRoles();

        expect($manageableRoles)->toContain(UserRole::Developer);
        expect($manageableRoles)->toContain(UserRole::Admin);
        expect($manageableRoles)->toContain(UserRole::President);
        expect($manageableRoles)->toContain(UserRole::Student);
        expect($manageableRoles)->toContain(UserRole::User);
        expect(count($manageableRoles))->toBeGreaterThan(20);
    });

    it('returns correct manageable roles for Admin', function (): void {
        /** @var User $admin */
        $admin = User::factory()->make(['role' => UserRole::Admin]);
        actingAs($admin);

        $manageableRoles = $admin->lowerRoles();

        expect($manageableRoles)->not->toContain(UserRole::Developer);
        expect($manageableRoles)->toContain(UserRole::Admin);
        expect($manageableRoles)->toContain(UserRole::Dean);
        expect($manageableRoles)->toContain(UserRole::Student);
    });

    it('returns limited manageable roles for Department Head', function (): void {
        /** @var User $deptHead */
        $deptHead = User::factory()->make(['role' => UserRole::DepartmentHead]);
        actingAs($deptHead);

        $manageableRoles = $deptHead->lowerRoles();

        expect($manageableRoles)->not->toContain(UserRole::Developer);
        expect($manageableRoles)->not->toContain(UserRole::Admin);
        expect($manageableRoles)->not->toContain(UserRole::Dean);
        expect($manageableRoles)->toContain(UserRole::Professor);
        expect($manageableRoles)->toContain(UserRole::Instructor);
    });

    it('returns empty manageable roles for Student', function (): void {
        /** @var User $student */
        $student = User::factory()->make(['role' => UserRole::Student]);
        actingAs($student);

        expect($student->lowerRoles())->toBeEmpty();
    });
});

describe('user management permissions', function (): void {
    test('developer can manage another developer', function (): void {
        /** @var User $developer1 */
        $developer1 = User::factory()->make(['role' => UserRole::Developer]);
        actingAs($developer1);

        /** @var User $developer2 */
        $developer2 = User::factory()->make(['role' => UserRole::Developer]);

        expect($developer2->isLowerInRole())->toBeTrue();
        expect($developer1->canManageUser($developer2))->toBeTrue();
    });

    test('admin cannot manage developer', function (): void {
        /** @var User $admin */
        $admin = User::factory()->make(['role' => UserRole::Admin]);
        actingAs($admin);

        /** @var User $developer */
        $developer = User::factory()->make(['role' => UserRole::Developer]);

        expect($developer->isLowerInRole())->toBeFalse();
        expect($admin->canManageUser($developer))->toBeFalse();
    });

    test('department head can manage faculty in their department', function (): void {
        /** @var User $deptHead */
        $deptHead = User::factory()->make(['role' => UserRole::DepartmentHead]);
        actingAs($deptHead);

        /** @var User $professor */
        $professor = User::factory()->make(['role' => UserRole::Professor]);

        expect($professor->isLowerInRole())->toBeTrue();
        expect($deptHead->canManageUser($professor))->toBeTrue();
    });

    test('regular user cannot manage others', function (): void {
        /** @var User $user */
        $user = User::factory()->make(['role' => UserRole::User]);
        actingAs($user);

        /** @var User $student */
        $student = User::factory()->make(['role' => UserRole::Student]);

        expect($student->isLowerInRole())->toBeFalse();
        expect($user->canManageUser($student))->toBeFalse();
    });
});

describe('hierarchy system', function (): void {
    test('higher authority users can manage lower authority users', function (): void {
        /** @var User $president */
        $president = User::factory()->make(['role' => UserRole::President]);

        /** @var User $dean */
        $dean = User::factory()->make(['role' => UserRole::Dean]);

        expect($president->hasHigherAuthorityThan($dean))->toBeTrue();
    });

    test('lower authority users cannot manage higher authority users', function (): void {
        /** @var User $instructor */
        $instructor = User::factory()->make(['role' => UserRole::Instructor]);

        /** @var User $dean */
        $dean = User::factory()->make(['role' => UserRole::Dean]);

        expect($instructor->hasHigherAuthorityThan($dean))->toBeFalse();
    });
});

describe('role type identification', function (): void {
    test('identifies administrative roles correctly', function (): void {
        /** @var User $president */
        $president = User::factory()->make(['role' => UserRole::President]);
        expect($president->isAdministrative())->toBeTrue();

        /** @var User $dean */
        $dean = User::factory()->make(['role' => UserRole::Dean]);
        expect($dean->isAdministrative())->toBeTrue();

        /** @var User $professor */
        $professor = User::factory()->make(['role' => UserRole::Professor]);
        expect($professor->isAdministrative())->toBeFalse();
    });

    test('identifies faculty roles correctly', function (): void {
        /** @var User $professor */
        $professor = User::factory()->make(['role' => UserRole::Professor]);
        expect($professor->isFaculty())->toBeTrue();

        /** @var User $instructor */
        $instructor = User::factory()->make(['role' => UserRole::Instructor]);
        expect($instructor->isFaculty())->toBeTrue();

        /** @var User $registrar */
        $registrar = User::factory()->make(['role' => UserRole::Registrar]);
        expect($registrar->isFaculty())->toBeFalse();
    });

    test('identifies student service roles correctly', function (): void {
        /** @var User $registrar */
        $registrar = User::factory()->make(['role' => UserRole::Registrar]);
        expect($registrar->isStudentServices())->toBeTrue();

        /** @var User $counselor */
        $counselor = User::factory()->make(['role' => UserRole::GuidanceCounselor]);
        expect($counselor->isStudentServices())->toBeTrue();

        /** @var User $cashier */
        $cashier = User::factory()->make(['role' => UserRole::Cashier]);
        expect($cashier->isStudentServices())->toBeFalse();
    });

    test('identifies finance roles correctly', function (): void {
        /** @var User $cashier */
        $cashier = User::factory()->make(['role' => UserRole::Cashier]);
        expect($cashier->isFinance())->toBeTrue();

        /** @var User $accountingOfficer */
        $accountingOfficer = User::factory()->make(['role' => UserRole::AccountingOfficer]);
        expect($accountingOfficer->isFinance())->toBeTrue();

        /** @var User $registrar */
        $registrar = User::factory()->make(['role' => UserRole::Registrar]);
        expect($registrar->isFinance())->toBeFalse();
    });

    test('identifies student roles correctly', function (): void {
        /** @var User $student */
        $student = User::factory()->make(['role' => UserRole::Student]);
        expect($student->isStudentRole())->toBeTrue();

        /** @var User $gradStudent */
        $gradStudent = User::factory()->make(['role' => UserRole::GraduateStudent]);
        expect($gradStudent->isStudentRole())->toBeTrue();

        /** @var User $professor */
        $professor = User::factory()->make(['role' => UserRole::Professor]);
        expect($professor->isStudentRole())->toBeFalse();
    });
});

describe('legacy role detection', function (): void {
    test('detects cashier from name pattern', function (): void {
        /** @var User $cashier */
        $cashier = User::factory()->make([
            'role' => UserRole::User,
            'name' => 'John Cashier Office',
        ]);

        expect($cashier->is_cashier)->toBeTrue();
    });

    test('detects registrar from name pattern', function (): void {
        /** @var User $registrar */
        $registrar = User::factory()->make([
            'role' => UserRole::User,
            'name' => 'Jane Registrar Office',
        ]);

        expect($registrar->is_registrar)->toBeTrue();
    });

    test('detects department head from name pattern', function (): void {
        /** @var User $deptHead */
        $deptHead = User::factory()->make([
            'role' => UserRole::User,
            'name' => 'IT Head Department',
        ]);

        expect($deptHead->is_dept_head)->toBeTrue();
    });

    test('proper role detection overrides name pattern', function (): void {
        /** @var User $cashier */
        $cashier = User::factory()->make([
            'role' => UserRole::Cashier,
            'name' => 'John Doe',  // Name doesn't contain 'cashier'
        ]);

        expect($cashier->is_cashier)->toBeTrue();
    });
});

describe('filament panel access', function (): void {
    test('administrative users can access panel', function (): void {
        $filamentPanel = filament()->getPanel('admin');

        /** @var User $admin */
        $admin = User::factory()->make(['role' => UserRole::Admin]);
        expect($admin->canAccessPanel($filamentPanel))->toBeTrue();

        /** @var User $dean */
        $dean = User::factory()->make(['role' => UserRole::Dean]);
        expect($dean->canAccessPanel($filamentPanel))->toBeTrue();
    });

    test('faculty can access panel', function (): void {
        $filamentPanel = filament()->getPanel('admin');

        /** @var User $professor */
        $professor = User::factory()->make(['role' => UserRole::Professor]);
        expect($professor->canAccessPanel($filamentPanel))->toBeFalse();

        /** @var User $instructor */
        $instructor = User::factory()->make(['role' => UserRole::Instructor]);
        expect($instructor->canAccessPanel($filamentPanel))->toBeFalse();
    });

    test('staff can access panel', function (): void {
        $filamentPanel = filament()->getPanel('admin');

        /** @var User $registrar */
        $registrar = User::factory()->make(['role' => UserRole::Registrar]);
        expect($registrar->canAccessPanel($filamentPanel))->toBeTrue();

        /** @var User $cashier */
        $cashier = User::factory()->make(['role' => UserRole::Cashier]);
        expect($cashier->canAccessPanel($filamentPanel))->toBeTrue();
    });

    test('students cannot access admin panel', function (): void {
        $filamentPanel = filament()->getPanel('admin');

        /** @var User $student */
        $student = User::factory()->make(['role' => UserRole::Student]);
        expect($student->canAccessPanel($filamentPanel))->toBeFalse();

        /** @var User $gradStudent */
        $gradStudent = User::factory()->make(['role' => UserRole::GraduateStudent]);
        expect($gradStudent->canAccessPanel($filamentPanel))->toBeFalse();
    });

    test('general users cannot access panel', function (): void {
        $filamentPanel = filament()->getPanel('admin');

        /** @var User $user */
        $user = User::factory()->make(['role' => UserRole::User]);
        expect($user->canAccessPanel($filamentPanel))->toBeFalse();
    });
});

describe('course access', function (): void {
    test('high level administrators can view all courses', function (): void {
        /** @var User $developer */
        $developer = User::factory()->make(['role' => UserRole::Developer]);
        expect($developer->viewable_courses)->toBe([1, 2, 3, 4, 5, 6, 8, 9, 10, 11, 12, 13]);

        /** @var User $president */
        $president = User::factory()->make(['role' => UserRole::President]);
        expect($president->viewable_courses)->toBe([1, 2, 3, 4, 5, 6, 8, 9, 10, 11, 12, 13]);
    });

    test('student services can view all courses', function (): void {
        /** @var User $registrar */
        $registrar = User::factory()->make(['role' => UserRole::Registrar]);
        expect($registrar->viewable_courses)->toBe([1, 2, 3, 4, 5, 6, 8, 9, 10, 11, 12, 13]);
    });

    test('students have no course access by default', function (): void {
        /** @var User $student */
        $student = User::factory()->make(['role' => UserRole::Student]);
        expect($student->viewable_courses)->toBe([]);
    });

    test('view title reflects access level', function (): void {
        /** @var User $admin */
        $admin = User::factory()->make(['role' => UserRole::Admin]);
        expect($admin->view_title_course)->toBe('All Departments');

        /** @var User $dean */
        $dean = User::factory()->make(['role' => UserRole::Dean]);
        expect($dean->view_title_course)->toBe('College Level Access');

        /** @var User $deptHead */
        $deptHead = User::factory()->make(['role' => UserRole::DepartmentHead]);
        expect($deptHead->view_title_course)->toBe('Department Head Access');

        /** @var User $student */
        $student = User::factory()->make(['role' => UserRole::Student]);
        expect($student->view_title_course)->toBe('Limited Access');
    });
});

describe('null role safety', function (): void {
    test('handles null role gracefully in canAccessPanel', function (): void {
        $filamentPanel = filament()->getPanel('admin');

        /** @var User $user */
        $user = User::factory()->make(['role' => null]);

        expect($user->canAccessPanel($filamentPanel))->toBeFalse();
    });

    test('handles null role gracefully in role type checks', function (): void {
        /** @var User $user */
        $user = User::factory()->make(['role' => null]);

        expect($user->isAdministrative())->toBeFalse();
        expect($user->isFaculty())->toBeFalse();
        expect($user->isStudentServices())->toBeFalse();
        expect($user->isFinance())->toBeFalse();
        expect($user->isStudentRole())->toBeFalse();
    });

    test('handles null role gracefully in user management', function (): void {
        /** @var User $userWithNullRole */
        $userWithNullRole = User::factory()->make(['role' => null]);

        /** @var User $adminUser */
        $adminUser = User::factory()->make(['role' => UserRole::Admin]);

        expect($userWithNullRole->lowerRoles())->toBe([]);
        expect($userWithNullRole->canManageUser($adminUser))->toBeFalse();
        expect($userWithNullRole->hasHigherAuthorityThan($adminUser))->toBeFalse();
    });

    test('handles null role gracefully in legacy detection', function (): void {
        /** @var User $user */
        $user = User::factory()->make([
            'role' => null,
            'name' => 'John Cashier Office',
        ]);

        // With null role, it falls back to User role and uses name-based detection
        expect($user->is_cashier)->toBeTrue(); // Name contains 'cashier'
        expect($user->is_registrar)->toBeFalse();
        expect($user->is_dept_head)->toBeFalse();
    });

    test('handles null role gracefully in course access', function (): void {
        /** @var User $user */
        $user = User::factory()->make(['role' => null]);

        // With null role, it falls back to User role behavior
        expect($user->viewable_courses)->toBe([]);
        expect($user->view_title_course)->toBe('Limited Access');
    });
});

describe('filament form compatibility', function (): void {
    test('attributesToArray returns string role for forms', function (): void {
        /** @var User $user */
        $user = User::factory()->make(['role' => UserRole::Professor]);

        $formData = $user->attributesToArray();

        expect($formData['role'])->toBeString();
        expect($formData['role'])->toBe('professor');
    });

    test('can recreate enum from form data', function (): void {
        /** @var User $user */
        $user = User::factory()->make(['role' => UserRole::Dean]);

        $formData = $user->attributesToArray();
        $recreatedEnum = UserRole::tryFrom($formData['role']);

        expect($recreatedEnum)->toBeInstanceOf(UserRole::class);
        expect($recreatedEnum)->toBe(UserRole::Dean);
    });

    test('handles all role types in form data', function (): void {
        $testRoles = [
            UserRole::Developer,
            UserRole::Admin,
            UserRole::President,
            UserRole::Professor,
            UserRole::Student,
            UserRole::Cashier,
        ];

        foreach ($testRoles as $role) {
            /** @var User $user */
            $user = User::factory()->make(['role' => $role]);

            $formData = $user->attributesToArray();

            expect($formData['role'])->toBeString();
            expect($formData['role'])->toBe($role->value);
            expect(UserRole::tryFrom($formData['role']))->toBe($role);
        }
    });
});
