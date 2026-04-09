export const ADMINISTRATOR_PORTAL_ROLES = [
    "admin",
    "super_admin",
    "developer",
    "president",
    "vice_president",
    "dean",
    "associate_dean",
    "department_head",
    "program_chair",
    "registrar",
    "assistant_registrar",
    "cashier",
    "hr_manager",
    "student_affairs_officer",
    "guidance_counselor",
    "librarian",
] as const;

export const FACULTY_PORTAL_ROLES = ["professor", "associate_professor", "assistant_professor", "instructor", "part_time_faculty"] as const;

export const STUDENT_PORTAL_ROLES = ["student", "graduate_student", "shs_student"] as const;

export function normalizePortalRole(role?: string | null): string {
    return role?.toLowerCase() ?? "";
}

export function isAdministratorPortalRole(role?: string | null): boolean {
    return ADMINISTRATOR_PORTAL_ROLES.includes(normalizePortalRole(role) as (typeof ADMINISTRATOR_PORTAL_ROLES)[number]);
}

export function isFacultyPortalRole(role?: string | null): boolean {
    return FACULTY_PORTAL_ROLES.includes(normalizePortalRole(role) as (typeof FACULTY_PORTAL_ROLES)[number]);
}

export function isStudentPortalRole(role?: string | null): boolean {
    return STUDENT_PORTAL_ROLES.includes(normalizePortalRole(role) as (typeof STUDENT_PORTAL_ROLES)[number]);
}
