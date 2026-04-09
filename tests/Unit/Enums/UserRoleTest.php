<?php

declare(strict_types=1);

use App\Enums\UserRole;
use Filament\Support\Colors\Color;

describe('label', function (): void {
    it('returns correct label for each role', function (UserRole $userRole, string $expectedLabel): void {
        expect($userRole->getLabel())->toBe($expectedLabel);
    })->with([
        // System Roles
        [UserRole::Developer, 'System Developer'],
        [UserRole::Admin, 'System Administrator'],

        // Academic Administration
        [UserRole::President, 'University President'],
        [UserRole::VicePresident, 'Vice President'],
        [UserRole::Dean, 'Dean'],
        [UserRole::AssociateDean, 'Associate Dean'],
        [UserRole::DepartmentHead, 'Department Head'],
        [UserRole::ProgramChair, 'Program Chair'],

        // Academic Staff
        [UserRole::Professor, 'Professor'],
        [UserRole::AssociateProfessor, 'Associate Professor'],
        [UserRole::AssistantProfessor, 'Assistant Professor'],
        [UserRole::Instructor, 'Instructor'],
        [UserRole::PartTimeFaculty, 'Part-time Faculty'],

        // Student Services
        [UserRole::Registrar, 'Registrar'],
        [UserRole::AssistantRegistrar, 'Assistant Registrar'],
        [UserRole::StudentAffairsOfficer, 'Student Affairs Officer'],
        [UserRole::GuidanceCounselor, 'Guidance Counselor'],
        [UserRole::Librarian, 'Librarian'],

        // Finance & Administration
        [UserRole::Cashier, 'Cashier'],
        [UserRole::AccountingOfficer, 'Accounting Officer'],
        [UserRole::BursarOfficer, 'Bursar Officer'],
        [UserRole::HRManager, 'HR Manager'],

        // Support Staff
        [UserRole::ITSupport, 'IT Support'],
        [UserRole::SecurityGuard, 'Security Guard'],
        [UserRole::MaintenanceStaff, 'Maintenance Staff'],
        [UserRole::AdministrativeAssistant, 'Administrative Assistant'],

        // Students
        [UserRole::Student, 'Student'],
        [UserRole::GraduateStudent, 'Graduate Student'],

        // General User (Legacy)
        [UserRole::User, 'User'],
    ]);
});

describe('color', function (): void {
    it('returns correct color for each role', function (UserRole $userRole, Color|array $expectedColor): void {
        expect($userRole->getColor())->toBe($expectedColor);
    })->with([
        // System Roles
        [UserRole::Developer, Color::Red],
        [UserRole::Admin, Color::Red],

        // Academic Administration
        [UserRole::President, Color::Indigo],
        [UserRole::VicePresident, Color::Blue],
        [UserRole::Dean, Color::Sky],
        [UserRole::AssociateDean, Color::Cyan],
        [UserRole::DepartmentHead, Color::Teal],
        [UserRole::ProgramChair, Color::Emerald],

        // Academic Staff
        [UserRole::Professor, Color::Green],
        [UserRole::AssociateProfessor, Color::Lime],
        [UserRole::AssistantProfessor, Color::Emerald],
        [UserRole::Instructor, Color::Teal],
        [UserRole::PartTimeFaculty, Color::Cyan],

        // Student Services
        [UserRole::Registrar, Color::Orange],
        [UserRole::AssistantRegistrar, Color::Amber],
        [UserRole::StudentAffairsOfficer, Color::Yellow],
        [UserRole::GuidanceCounselor, Color::Lime],
        [UserRole::Librarian, Color::Green],

        // Finance & Administration
        [UserRole::Cashier, Color::Red],
        [UserRole::AccountingOfficer, Color::Pink],
        [UserRole::BursarOfficer, Color::Rose],
        [UserRole::HRManager, Color::Fuchsia],

        // Support Staff
        [UserRole::ITSupport, Color::Slate],
        [UserRole::SecurityGuard, Color::Gray],
        [UserRole::MaintenanceStaff, Color::Stone],
        [UserRole::AdministrativeAssistant, Color::Neutral],

        // Students
        [UserRole::Student, Color::Sky],
        [UserRole::GraduateStudent, Color::Indigo],

        // General User (Legacy)
        [UserRole::User, Color::Zinc],
    ]);
});

describe('value', function (): void {
    it('has expected string values', function (UserRole $userRole, string $expectedValue): void {
        expect($userRole->value)->toBe($expectedValue);
    })->with([
        // System Roles
        [UserRole::Developer, 'developer'],
        [UserRole::Admin, 'admin'],

        // Academic Administration
        [UserRole::President, 'president'],
        [UserRole::VicePresident, 'vice_president'],
        [UserRole::Dean, 'dean'],
        [UserRole::AssociateDean, 'associate_dean'],
        [UserRole::DepartmentHead, 'department_head'],
        [UserRole::ProgramChair, 'program_chair'],

        // Academic Staff
        [UserRole::Professor, 'professor'],
        [UserRole::AssociateProfessor, 'associate_professor'],
        [UserRole::AssistantProfessor, 'assistant_professor'],
        [UserRole::Instructor, 'instructor'],
        [UserRole::PartTimeFaculty, 'part_time_faculty'],

        // Student Services
        [UserRole::Registrar, 'registrar'],
        [UserRole::AssistantRegistrar, 'assistant_registrar'],
        [UserRole::StudentAffairsOfficer, 'student_affairs_officer'],
        [UserRole::GuidanceCounselor, 'guidance_counselor'],
        [UserRole::Librarian, 'librarian'],

        // Finance & Administration
        [UserRole::Cashier, 'cashier'],
        [UserRole::AccountingOfficer, 'accounting_officer'],
        [UserRole::BursarOfficer, 'bursar_officer'],
        [UserRole::HRManager, 'hr_manager'],

        // Support Staff
        [UserRole::ITSupport, 'it_support'],
        [UserRole::SecurityGuard, 'security_guard'],
        [UserRole::MaintenanceStaff, 'maintenance_staff'],
        [UserRole::AdministrativeAssistant, 'administrative_assistant'],

        // Students
        [UserRole::Student, 'student'],
        [UserRole::GraduateStudent, 'graduate_student'],

        // General User (Legacy)
        [UserRole::User, 'user'],
    ]);
});

describe('hierarchy methods', function (): void {
    it('returns correct hierarchy level', function (): void {
        expect(UserRole::Developer->getHierarchyLevel())->toBe(1000);
        expect(UserRole::Admin->getHierarchyLevel())->toBe(900);
        expect(UserRole::President->getHierarchyLevel())->toBe(800);
        expect(UserRole::VicePresident->getHierarchyLevel())->toBe(700);
        expect(UserRole::Dean->getHierarchyLevel())->toBe(600);
        expect(UserRole::DepartmentHead->getHierarchyLevel())->toBe(500);
        expect(UserRole::Student->getHierarchyLevel())->toBe(50);
        expect(UserRole::User->getHierarchyLevel())->toBe(10);
    });

    it('identifies administrative roles correctly', function (): void {
        expect(UserRole::Developer->isAdministrative())->toBeTrue();
        expect(UserRole::Admin->isAdministrative())->toBeTrue();
        expect(UserRole::President->isAdministrative())->toBeTrue();
        expect(UserRole::Dean->isAdministrative())->toBeTrue();
        expect(UserRole::DepartmentHead->isAdministrative())->toBeTrue();

        expect(UserRole::Professor->isAdministrative())->toBeFalse();
        expect(UserRole::Student->isAdministrative())->toBeFalse();
        expect(UserRole::Cashier->isAdministrative())->toBeFalse();
    });

    it('identifies faculty roles correctly', function (): void {
        expect(UserRole::Professor->isFaculty())->toBeTrue();
        expect(UserRole::AssociateProfessor->isFaculty())->toBeTrue();
        expect(UserRole::AssistantProfessor->isFaculty())->toBeTrue();
        expect(UserRole::Instructor->isFaculty())->toBeTrue();
        expect(UserRole::PartTimeFaculty->isFaculty())->toBeTrue();

        expect(UserRole::Dean->isFaculty())->toBeFalse();
        expect(UserRole::Student->isFaculty())->toBeFalse();
        expect(UserRole::Cashier->isFaculty())->toBeFalse();
    });

    it('identifies student service roles correctly', function (): void {
        expect(UserRole::Registrar->isStudentServices())->toBeTrue();
        expect(UserRole::AssistantRegistrar->isStudentServices())->toBeTrue();
        expect(UserRole::StudentAffairsOfficer->isStudentServices())->toBeTrue();
        expect(UserRole::GuidanceCounselor->isStudentServices())->toBeTrue();
        expect(UserRole::Librarian->isStudentServices())->toBeTrue();

        expect(UserRole::Professor->isStudentServices())->toBeFalse();
        expect(UserRole::Cashier->isStudentServices())->toBeFalse();
    });

    it('identifies finance roles correctly', function (): void {
        expect(UserRole::Cashier->isFinance())->toBeTrue();
        expect(UserRole::AccountingOfficer->isFinance())->toBeTrue();
        expect(UserRole::BursarOfficer->isFinance())->toBeTrue();

        expect(UserRole::Professor->isFinance())->toBeFalse();
        expect(UserRole::Registrar->isFinance())->toBeFalse();
    });

    it('identifies student roles correctly', function (): void {
        expect(UserRole::Student->isStudent())->toBeTrue();
        expect(UserRole::GraduateStudent->isStudent())->toBeTrue();

        expect(UserRole::Professor->isStudent())->toBeFalse();
        expect(UserRole::Cashier->isStudent())->toBeFalse();
    });
});

describe('manageable roles', function (): void {
    it('developer can manage all roles', function (): void {
        $manageableRoles = UserRole::Developer->getManageableRoles();

        expect($manageableRoles)->toContain(UserRole::Developer);
        expect($manageableRoles)->toContain(UserRole::Admin);
        expect($manageableRoles)->toContain(UserRole::President);
        expect($manageableRoles)->toContain(UserRole::Student);
        expect($manageableRoles)->toContain(UserRole::User);
        expect(count($manageableRoles))->toBeGreaterThan(20);
    });

    it('admin cannot manage developer', function (): void {
        $manageableRoles = UserRole::Admin->getManageableRoles();

        expect($manageableRoles)->not->toContain(UserRole::Developer);
        expect($manageableRoles)->toContain(UserRole::Admin);
        expect($manageableRoles)->toContain(UserRole::Dean);
        expect($manageableRoles)->toContain(UserRole::Student);
    });

    it('department head has limited management scope', function (): void {
        $manageableRoles = UserRole::DepartmentHead->getManageableRoles();

        expect($manageableRoles)->not->toContain(UserRole::Developer);
        expect($manageableRoles)->not->toContain(UserRole::Admin);
        expect($manageableRoles)->not->toContain(UserRole::Dean);
        expect($manageableRoles)->toContain(UserRole::Professor);
        expect($manageableRoles)->toContain(UserRole::Instructor);
    });

    it('student cannot manage any roles', function (): void {
        $manageableRoles = UserRole::Student->getManageableRoles();

        expect($manageableRoles)->toBeEmpty();
    });
});
