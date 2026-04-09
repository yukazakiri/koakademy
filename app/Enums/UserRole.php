<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Colors\Color;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum UserRole: string implements HasColor, HasLabel
{
    // System Roles
    case Developer = 'developer';
    case Admin = 'admin';
    case SuperAdmin = 'super_admin';

    // Academic Administration
    case President = 'president';
    case VicePresident = 'vice_president';
    case Dean = 'dean';
    case AssociateDean = 'associate_dean';
    case DepartmentHead = 'department_head';
    case ProgramChair = 'program_chair';

    // Academic Staff
    case Professor = 'professor';
    case AssociateProfessor = 'associate_professor';
    case AssistantProfessor = 'assistant_professor';
    case Instructor = 'instructor';
    case PartTimeFaculty = 'part_time_faculty';

    // Student Services
    case Registrar = 'registrar';
    case AssistantRegistrar = 'assistant_registrar';
    case StudentAffairsOfficer = 'student_affairs_officer';
    case GuidanceCounselor = 'guidance_counselor';
    case Librarian = 'librarian';

    // Finance & Administration
    case Cashier = 'cashier';
    case AccountingOfficer = 'accounting_officer';
    case BursarOfficer = 'bursar_officer';
    case HRManager = 'hr_manager';

    // Support Staff
    case ITSupport = 'it_support';
    case SecurityGuard = 'security_guard';
    case MaintenanceStaff = 'maintenance_staff';
    case AdministrativeAssistant = 'administrative_assistant';

    // Students
    case Student = 'student';
    case GraduateStudent = 'graduate_student';

    // General User (Legacy)
    case User = 'user';

    case ShsStudent = 'shs_student';

    public function getLabel(): ?string
    {
        return match ($this) {
            // System Roles
            self::Developer => 'System Developer',
            self::Admin => 'System Administrator',
            self::SuperAdmin => 'Super Administrator',

            // Academic Administration
            self::President => 'University President',
            self::VicePresident => 'Vice President',
            self::Dean => 'Dean',
            self::AssociateDean => 'Associate Dean',
            self::DepartmentHead => 'Department Head',
            self::ProgramChair => 'Program Chair',

            // Academic Staff
            self::Professor => 'Professor',
            self::AssociateProfessor => 'Associate Professor',
            self::AssistantProfessor => 'Assistant Professor',
            self::Instructor => 'Instructor',
            self::PartTimeFaculty => 'Part-time Faculty',

            // Student Services
            self::Registrar => 'Registrar',
            self::AssistantRegistrar => 'Assistant Registrar',
            self::StudentAffairsOfficer => 'Student Affairs Officer',
            self::GuidanceCounselor => 'Guidance Counselor',
            self::Librarian => 'Librarian',

            // Finance & Administration
            self::Cashier => 'Cashier',
            self::AccountingOfficer => 'Accounting Officer',
            self::BursarOfficer => 'Bursar Officer',
            self::HRManager => 'HR Manager',

            // Support Staff
            self::ITSupport => 'IT Support',
            self::SecurityGuard => 'Security Guard',
            self::MaintenanceStaff => 'Maintenance Staff',
            self::AdministrativeAssistant => 'Administrative Assistant',

            // Students
            self::Student => 'Student',
            self::GraduateStudent => 'Graduate Student',
            self::ShsStudent => 'SHS Student',

            // General User (Legacy)
            self::User => 'User',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            // System Roles - Purple/Violet shades
            self::Developer => Color::Red,
            self::Admin => Color::Red,
            self::SuperAdmin => Color::Red,

            // Academic Administration - Blue shades
            self::President => Color::Indigo,
            self::VicePresident => Color::Blue,
            self::Dean => Color::Sky,
            self::AssociateDean => Color::Cyan,
            self::DepartmentHead => Color::Teal,
            self::ProgramChair => Color::Emerald,

            // Academic Staff - Green shades
            self::Professor => Color::Green,
            self::AssociateProfessor => Color::Lime,
            self::AssistantProfessor => Color::Emerald,
            self::Instructor => Color::Teal,
            self::PartTimeFaculty => Color::Cyan,

            // Student Services - Orange/Yellow shades
            self::Registrar => Color::Orange,
            self::AssistantRegistrar => Color::Amber,
            self::StudentAffairsOfficer => Color::Yellow,
            self::GuidanceCounselor => Color::Lime,
            self::Librarian => Color::Green,

            // Finance & Administration - Red/Pink shades
            self::Cashier => Color::Red,
            self::AccountingOfficer => Color::Pink,
            self::BursarOfficer => Color::Rose,
            self::HRManager => Color::Fuchsia,

            // Support Staff - Gray/Neutral shades
            self::ITSupport => Color::Slate,
            self::SecurityGuard => Color::Gray,
            self::MaintenanceStaff => Color::Stone,
            self::AdministrativeAssistant => Color::Neutral,

            // Students - Light/Pastel shades
            self::Student => Color::Sky,
            self::GraduateStudent => Color::Indigo,
            self::ShsStudent => Color::Amber,

            // General User (Legacy)
            self::User => Color::Zinc,
        };
    }

    /**
     * Get roles that can be managed by the current role
     */
    public function getManageableRoles(): array
    {
        return match ($this) {
            self::Developer => [
                self::Developer, self::SuperAdmin, self::Admin, self::President, self::VicePresident,
                self::Dean, self::AssociateDean, self::DepartmentHead, self::ProgramChair,
                self::Professor, self::AssociateProfessor, self::AssistantProfessor,
                self::Instructor, self::PartTimeFaculty, self::Registrar, self::AssistantRegistrar,
                self::StudentAffairsOfficer, self::GuidanceCounselor, self::Librarian,
                self::Cashier, self::AccountingOfficer, self::BursarOfficer, self::HRManager,
                self::ITSupport, self::SecurityGuard, self::MaintenanceStaff,
                self::AdministrativeAssistant, self::Student, self::GraduateStudent, self::User,
            ],
            self::SuperAdmin => [
                self::Admin, self::President, self::VicePresident,
                self::Dean, self::AssociateDean, self::DepartmentHead, self::ProgramChair,
                self::Professor, self::AssociateProfessor, self::AssistantProfessor,
                self::Instructor, self::PartTimeFaculty, self::Registrar, self::AssistantRegistrar,
                self::StudentAffairsOfficer, self::GuidanceCounselor, self::Librarian,
                self::Cashier, self::AccountingOfficer, self::BursarOfficer, self::HRManager,
                self::ITSupport, self::SecurityGuard, self::MaintenanceStaff,
                self::AdministrativeAssistant, self::Student, self::GraduateStudent, self::User,
            ],
            self::Admin => [
                self::Admin, self::Dean, self::AssociateDean, self::DepartmentHead,
                self::ProgramChair, self::Professor, self::AssociateProfessor,
                self::AssistantProfessor, self::Instructor, self::PartTimeFaculty,
                self::Registrar, self::AssistantRegistrar, self::StudentAffairsOfficer,
                self::GuidanceCounselor, self::Librarian, self::Cashier, self::AccountingOfficer,
                self::BursarOfficer, self::HRManager, self::ITSupport, self::SecurityGuard,
                self::MaintenanceStaff, self::AdministrativeAssistant, self::Student,
                self::GraduateStudent, self::User,
            ],
            self::President => [
                self::VicePresident, self::Dean, self::AssociateDean, self::DepartmentHead,
                self::ProgramChair, self::Professor, self::AssociateProfessor,
                self::AssistantProfessor, self::Instructor, self::PartTimeFaculty,
                self::Registrar, self::AssistantRegistrar, self::StudentAffairsOfficer,
                self::GuidanceCounselor, self::Librarian, self::Cashier, self::AccountingOfficer,
                self::BursarOfficer, self::HRManager, self::ITSupport, self::SecurityGuard,
                self::MaintenanceStaff, self::AdministrativeAssistant,
            ],
            self::VicePresident => [
                self::Dean, self::AssociateDean, self::DepartmentHead, self::ProgramChair,
                self::Professor, self::AssociateProfessor, self::AssistantProfessor,
                self::Instructor, self::PartTimeFaculty, self::Registrar, self::AssistantRegistrar,
                self::StudentAffairsOfficer, self::GuidanceCounselor, self::Librarian,
                self::Cashier, self::AccountingOfficer, self::BursarOfficer, self::HRManager,
                self::ITSupport, self::SecurityGuard, self::MaintenanceStaff,
                self::AdministrativeAssistant,
            ],
            self::Dean => [
                self::AssociateDean, self::DepartmentHead, self::ProgramChair,
                self::Professor, self::AssociateProfessor, self::AssistantProfessor,
                self::Instructor, self::PartTimeFaculty, self::AssistantRegistrar,
                self::StudentAffairsOfficer, self::GuidanceCounselor, self::Librarian,
                self::AdministrativeAssistant,
            ],
            self::DepartmentHead => [
                self::ProgramChair, self::Professor, self::AssociateProfessor,
                self::AssistantProfessor, self::Instructor, self::PartTimeFaculty,
                self::AdministrativeAssistant,
            ],
            self::Registrar => [
                self::AssistantRegistrar, self::AdministrativeAssistant,
            ],
            self::AssociateDean => [
                self::DepartmentHead, self::ProgramChair,
                self::Professor, self::AssociateProfessor, self::AssistantProfessor,
                self::Instructor, self::PartTimeFaculty, self::AssistantRegistrar,
                self::StudentAffairsOfficer, self::GuidanceCounselor, self::Librarian,
                self::AdministrativeAssistant,
            ],
            self::HRManager => [
                self::AdministrativeAssistant, self::SecurityGuard, self::MaintenanceStaff,
            ],
            default => [],
        };
    }

    /**
     * Check if this role has administrative privileges
     */
    public function isAdministrative(): bool
    {
        return in_array($this, [
            self::Developer,
            self::SuperAdmin,
            self::Admin,
            self::President,
            self::VicePresident,
            self::Dean,
            self::AssociateDean,
            self::DepartmentHead,
            self::ProgramChair,
        ]);
    }

    /**
     * Check if this role is faculty
     */
    public function isFaculty(): bool
    {
        return in_array($this, [
            self::Professor,
            self::AssociateProfessor,
            self::AssistantProfessor,
            self::Instructor,
            self::PartTimeFaculty,
        ]);
    }

    /**
     * Check if this role handles student services
     */
    public function isStudentServices(): bool
    {
        return in_array($this, [
            self::Registrar,
            self::AssistantRegistrar,
            self::StudentAffairsOfficer,
            self::GuidanceCounselor,
            self::Librarian,
        ]);
    }

    /**
     * Check if this role handles finance
     */
    public function isFinance(): bool
    {
        return in_array($this, [
            self::Cashier,
            self::AccountingOfficer,
            self::BursarOfficer,
            self::HRManager,
        ]);
    }

    /**
     * Check if this role is a student
     */
    public function isStudent(): bool
    {
        return in_array($this, [
            self::Student,
            self::GraduateStudent,
            self::ShsStudent,
        ]);
    }

    public function isCashier(): bool
    {
        return $this === self::Cashier;
    }

    public function isSupportStaff(): bool
    {
        return in_array($this, [
            self::ITSupport,
            self::SecurityGuard,
            self::MaintenanceStaff,
            self::AdministrativeAssistant,
        ]);
    }

    public function canAccessAdminPortal(): bool
    {
        if ($this->isAdministrative()) {
            return true;
        }
        if ($this->isStudentServices()) {
            return true;
        }
        if ($this->isFinance()) {
            return true;
        }

        return $this->isSupportStaff();
    }

    public function isDeptHead(): bool
    {
        return $this === self::DepartmentHead;
    }

    public function isRegistrar(): bool
    {
        return $this === self::Registrar;
    }

    public function isSuperAdmin(): bool
    {
        return $this === self::SuperAdmin;
    }

    /**
     * Get the hierarchy level (higher number = higher authority)
     */
    public function getHierarchyLevel(): int
    {
        return match ($this) {
            self::Developer => 1000,
            self::SuperAdmin => 950,
            self::Admin => 900,
            self::President => 800,
            self::VicePresident => 700,
            self::Dean => 600,
            self::AssociateDean => 550,
            self::DepartmentHead => 500,
            self::ProgramChair => 450,
            self::Professor => 400,
            self::AssociateProfessor => 350,
            self::AssistantProfessor => 300,
            self::Instructor => 250,
            self::PartTimeFaculty => 200,
            self::Registrar => 400,
            self::AssistantRegistrar => 300,
            self::StudentAffairsOfficer => 300,
            self::GuidanceCounselor => 250,
            self::Librarian => 250,
            self::Cashier => 300,
            self::AccountingOfficer => 250,
            self::BursarOfficer => 350,
            self::HRManager => 400,
            self::ITSupport => 200,
            self::SecurityGuard => 100,
            self::MaintenanceStaff => 100,
            self::AdministrativeAssistant => 150,
            self::Student => 50,
            self::GraduateStudent => 60,
            self::ShsStudent => 70,
            self::User => 10,
        };
    }
}
