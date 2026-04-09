/**
 * UserRole enum - mirrors the PHP App\Enums\UserRole enum
 * Used for role-based access control in the frontend
 */
export enum UserRole {
    // System Roles
    Developer = "developer",
    Admin = "admin",
    SuperAdmin = "super_admin",

    // Academic Administration
    President = "president",
    VicePresident = "vice_president",
    Dean = "dean",
    AssociateDean = "associate_dean",
    DepartmentHead = "department_head",
    ProgramChair = "program_chair",

    // Academic Staff
    Professor = "professor",
    AssociateProfessor = "associate_professor",
    AssistantProfessor = "assistant_professor",
    Instructor = "instructor",
    PartTimeFaculty = "part_time_faculty",

    // Student Services
    Registrar = "registrar",
    AssistantRegistrar = "assistant_registrar",
    StudentAffairsOfficer = "student_affairs_officer",
    GuidanceCounselor = "guidance_counselor",
    Librarian = "librarian",

    // Finance & Administration
    Cashier = "cashier",
    AccountingOfficer = "accounting_officer",
    BursarOfficer = "bursar_officer",
    HRManager = "hr_manager",

    // Support Staff
    ITSupport = "it_support",
    SecurityGuard = "security_guard",
    MaintenanceStaff = "maintenance_staff",
    AdministrativeAssistant = "administrative_assistant",

    // Students
    Student = "student",
    GraduateStudent = "graduate_student",
    ShsStudent = "shs_student",

    // Legacy
    User = "user",
}

/**
 * Role category groups for permission checking
 */
export const SYSTEM_ADMIN_ROLES: UserRole[] = [UserRole.Developer, UserRole.SuperAdmin, UserRole.Admin];

export const ACADEMIC_ADMIN_ROLES: UserRole[] = [
    UserRole.President,
    UserRole.VicePresident,
    UserRole.Dean,
    UserRole.AssociateDean,
    UserRole.DepartmentHead,
    UserRole.ProgramChair,
];

export const FACULTY_ROLES: UserRole[] = [
    UserRole.Professor,
    UserRole.AssociateProfessor,
    UserRole.AssistantProfessor,
    UserRole.Instructor,
    UserRole.PartTimeFaculty,
];

export const STUDENT_SERVICES_ROLES: UserRole[] = [
    UserRole.Registrar,
    UserRole.AssistantRegistrar,
    UserRole.StudentAffairsOfficer,
    UserRole.GuidanceCounselor,
    UserRole.Librarian,
];

export const FINANCE_ROLES: UserRole[] = [UserRole.Cashier, UserRole.AccountingOfficer, UserRole.BursarOfficer];

export const HR_ROLES: UserRole[] = [UserRole.HRManager];

export const IT_SUPPORT_ROLES: UserRole[] = [UserRole.ITSupport];

export const SUPPORT_STAFF_ROLES: UserRole[] = [UserRole.SecurityGuard, UserRole.MaintenanceStaff, UserRole.AdministrativeAssistant];

export const STUDENT_ROLES: UserRole[] = [UserRole.Student, UserRole.GraduateStudent, UserRole.ShsStudent];

/**
 * Combined role groups for broader permission checks
 */
export const ALL_ADMIN_ROLES: UserRole[] = [...SYSTEM_ADMIN_ROLES, ...ACADEMIC_ADMIN_ROLES];

export const ALL_STAFF_ROLES: UserRole[] = [
    ...ALL_ADMIN_ROLES,
    ...FACULTY_ROLES,
    ...STUDENT_SERVICES_ROLES,
    ...FINANCE_ROLES,
    ...HR_ROLES,
    ...IT_SUPPORT_ROLES,
    ...SUPPORT_STAFF_ROLES,
];

/**
 * Role labels for display purposes
 */
export const USER_ROLE_LABELS: Record<UserRole, string> = {
    [UserRole.Developer]: "System Developer",
    [UserRole.Admin]: "System Administrator",
    [UserRole.SuperAdmin]: "Super Administrator",
    [UserRole.President]: "University President",
    [UserRole.VicePresident]: "Vice President",
    [UserRole.Dean]: "Dean",
    [UserRole.AssociateDean]: "Associate Dean",
    [UserRole.DepartmentHead]: "Department Head",
    [UserRole.ProgramChair]: "Program Chair",
    [UserRole.Professor]: "Professor",
    [UserRole.AssociateProfessor]: "Associate Professor",
    [UserRole.AssistantProfessor]: "Assistant Professor",
    [UserRole.Instructor]: "Instructor",
    [UserRole.PartTimeFaculty]: "Part-time Faculty",
    [UserRole.Registrar]: "Registrar",
    [UserRole.AssistantRegistrar]: "Assistant Registrar",
    [UserRole.StudentAffairsOfficer]: "Student Affairs Officer",
    [UserRole.GuidanceCounselor]: "Guidance Counselor",
    [UserRole.Librarian]: "Librarian",
    [UserRole.Cashier]: "Cashier",
    [UserRole.AccountingOfficer]: "Accounting Officer",
    [UserRole.BursarOfficer]: "Bursar Officer",
    [UserRole.HRManager]: "HR Manager",
    [UserRole.ITSupport]: "IT Support",
    [UserRole.SecurityGuard]: "Security Guard",
    [UserRole.MaintenanceStaff]: "Maintenance Staff",
    [UserRole.AdministrativeAssistant]: "Administrative Assistant",
    [UserRole.Student]: "Student",
    [UserRole.GraduateStudent]: "Graduate Student",
    [UserRole.ShsStudent]: "SHS Student",
    [UserRole.User]: "User",
};

/**
 * Helper functions for role checking
 */
export function isSystemAdmin(role: string): boolean {
    return SYSTEM_ADMIN_ROLES.includes(role as UserRole);
}

export function isAcademicAdmin(role: string): boolean {
    return ACADEMIC_ADMIN_ROLES.includes(role as UserRole);
}

export function isAdministrative(role: string): boolean {
    return ALL_ADMIN_ROLES.includes(role as UserRole);
}

export function isFaculty(role: string): boolean {
    return FACULTY_ROLES.includes(role as UserRole);
}

export function isStudentServices(role: string): boolean {
    return STUDENT_SERVICES_ROLES.includes(role as UserRole);
}

export function isFinance(role: string): boolean {
    return FINANCE_ROLES.includes(role as UserRole);
}

export function isHR(role: string): boolean {
    return HR_ROLES.includes(role as UserRole);
}

export function isITSupport(role: string): boolean {
    return IT_SUPPORT_ROLES.includes(role as UserRole);
}

export function isStudent(role: string): boolean {
    return STUDENT_ROLES.includes(role as UserRole);
}

export function hasRole(userRole: string, allowedRoles: UserRole[]): boolean {
    return allowedRoles.includes(userRole as UserRole);
}

export function getRoleLabel(role: string): string {
    return USER_ROLE_LABELS[role as UserRole] || role;
}
