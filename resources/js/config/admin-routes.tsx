import type { Route } from "@/components/sidebar-03/nav-main";
import {
    ACADEMIC_ADMIN_ROLES,
    ALL_ADMIN_ROLES,
    ALL_STAFF_ROLES,
    FINANCE_ROLES,
    HR_ROLES,
    IT_SUPPORT_ROLES,
    STUDENT_SERVICES_ROLES,
    SYSTEM_ADMIN_ROLES,
    UserRole,
} from "@/types/user-role";
import {
    IconBell,
    IconBook,
    IconBooks,
    IconBriefcase,
    IconBuilding,
    IconCalendarEvent,
    IconCalendarStats,
    IconCash,
    IconCertificate,
    IconChartBar,
    IconChecklist,
    IconClipboardCheck,
    IconDashboard,
    IconFileAnalytics,
    IconFileDescription,
    IconGavel,
    IconHelp,
    IconHistory,
    IconMedicalCross,
    IconNews,
    IconReportAnalytics,
    IconSchool,
    IconServer,
    IconSettings,
    IconShieldLock,
    IconSparkles,
    IconTools,
    IconUser,
    IconUserCheck,
    IconUserCircle,
    IconUserPlus,
    IconUsers,
    IconUsersGroup,
} from "@tabler/icons-react";

/**
 * Extended Route type with role-based access control and permission-based access
 */
export interface AdminRoute extends Route {
    /** Roles allowed to view this route when no explicit permission is configured */
    allowedRoles?: UserRole[];
    /** Section group for organizing the sidebar */
    section?: RouteSection;
    /** Badge to display (e.g., "New", "Beta" or a component) */
    badge?: string | React.ReactNode;
    /**
     * Spatie permission(s) required to view this route.
     * Uses Filament Shield format: "ViewAny:ModelName" or "View:PageName"
     * If specified, user must have at least one of these permissions.
     */
    requiredPermission?: string | string[];
}

/**
 * Route sections for organizing the sidebar
 */
export type RouteSection = "core" | "academic" | "student_services" | "finance" | "hr" | "system" | "support" | "library" | "inventory";

/**
 * Section configuration for display
 */
export interface SectionConfig {
    id: RouteSection;
    title: string;
    allowedRoles: UserRole[];
}

/**
 * Section definitions with their allowed roles
 */
export const ROUTE_SECTIONS: SectionConfig[] = [
    {
        id: "core",
        title: "Overview",
        allowedRoles: ALL_STAFF_ROLES,
    },
    {
        id: "academic",
        title: "Academic Management",
        allowedRoles: [...ALL_ADMIN_ROLES, ...STUDENT_SERVICES_ROLES],
    },
    {
        id: "student_services",
        title: "Student Services",
        allowedRoles: [
            ...SYSTEM_ADMIN_ROLES,
            ...STUDENT_SERVICES_ROLES,
            ...FINANCE_ROLES,
            UserRole.Dean,
            UserRole.AssociateDean,
            UserRole.DepartmentHead,
            UserRole.ProgramChair,
        ],
    },
    {
        id: "finance",
        title: "Finance & Billing",
        allowedRoles: [...SYSTEM_ADMIN_ROLES, ...FINANCE_ROLES, UserRole.President, UserRole.VicePresident],
    },
    {
        id: "hr",
        title: "Human Resources",
        allowedRoles: [...SYSTEM_ADMIN_ROLES, ...HR_ROLES, UserRole.President, UserRole.VicePresident],
    },
    {
        id: "system",
        title: "System Administration",
        allowedRoles: SYSTEM_ADMIN_ROLES,
    },
    {
        id: "library",
        title: "Library Management",
        allowedRoles: [...SYSTEM_ADMIN_ROLES, ...STUDENT_SERVICES_ROLES],
    },
    {
        id: "inventory",
        title: "Inventory Management",
        allowedRoles: [...SYSTEM_ADMIN_ROLES, ...IT_SUPPORT_ROLES],
    },
    {
        id: "support",
        title: "Support",
        allowedRoles: ALL_STAFF_ROLES,
    },
];

/**
 * All administrator routes organized by section
 */
export const ADMIN_ROUTES: AdminRoute[] = [
    // ============================================
    // CORE - Dashboard & Overview
    // ============================================
    {
        id: "admin-dashboard",
        title: "Dashboard",
        icon: <IconDashboard className="size-4" />,
        link: "/administrators/dashboard",
        section: "core",
    },
    {
        id: "admin-notifications",
        title: "Notifications Center",
        icon: <IconBell className="size-4" />,
        link: "/administrators/notifications",
        section: "core",
    },
    {
        id: "admin-announcements",
        title: "Announcements",
        icon: <IconNews className="size-4" />,
        link: "/administrators/announcements",
        section: "core",
    },

    // ============================================
    // ACADEMIC MANAGEMENT
    // ============================================
    {
        id: "admin-classes",
        title: "Class Management",
        icon: <IconSchool className="size-4" />,
        link: "/administrators/classes",
        section: "academic",
        requiredPermission: "ViewAny:Classes",
        allowedRoles: [...SYSTEM_ADMIN_ROLES, ...ACADEMIC_ADMIN_ROLES, ...STUDENT_SERVICES_ROLES],
    },
    {
        id: "admin-faculty",
        title: "Faculty Directory",
        icon: <IconUsers className="size-4" />,
        link: "/administrators/faculties",
        section: "academic",
        requiredPermission: "ViewAny:Faculty",
        allowedRoles: [...SYSTEM_ADMIN_ROLES, ...ACADEMIC_ADMIN_ROLES, ...HR_ROLES],
    },
    {
        id: "admin-scheduling-analytics",
        title: "Scheduling & Analytics",
        icon: <IconCalendarStats className="size-4" />,
        link: "/administrators/scheduling-analytics",
        section: "academic",
        requiredPermission: ["ViewAny:Classes", "View:Timetable"],
        allowedRoles: [...SYSTEM_ADMIN_ROLES, ...ACADEMIC_ADMIN_ROLES, UserRole.Registrar, UserRole.AssistantRegistrar],
    },
    {
        id: "admin-academic-calendar",
        title: "Academic Calendar",
        icon: <IconCalendarEvent className="size-4" />,
        link: "/administrators/academic-calendar",
        section: "academic",
        disabled: true,
        disabledTooltip: "Academic calendar coming soon",
        requiredPermission: "ViewAny:Event",
        allowedRoles: [...SYSTEM_ADMIN_ROLES, ...ACADEMIC_ADMIN_ROLES, ...STUDENT_SERVICES_ROLES],
    },
    {
        id: "admin-curriculum",
        title: "Curriculum Overview",
        icon: <IconFileDescription className="size-4" />,
        link: "/administrators/curriculum",
        section: "academic",
        requiredPermission: ["ViewAny:Course", "ViewAny:Subject"],
        allowedRoles: [...SYSTEM_ADMIN_ROLES, UserRole.Dean, UserRole.AssociateDean, UserRole.DepartmentHead, UserRole.ProgramChair],
    },
    {
        id: "admin-programs",
        title: "Program Management",
        icon: <IconSchool className="size-4" />,
        link: "/administrators/curriculum/programs",
        section: "academic",
        requiredPermission: "ViewAny:Course",
        allowedRoles: [...SYSTEM_ADMIN_ROLES, UserRole.Dean, UserRole.AssociateDean, UserRole.DepartmentHead, UserRole.ProgramChair],
    },
    {
        id: "admin-grade-management",
        title: "Grade Management",
        icon: <IconClipboardCheck className="size-4" />,
        link: "/administrators/grades",
        section: "academic",
        disabled: true,
        disabledTooltip: "Grade management coming soon",
        requiredPermission: "ViewAny:Student",
        allowedRoles: [...SYSTEM_ADMIN_ROLES, UserRole.Registrar, UserRole.AssistantRegistrar, UserRole.Dean, UserRole.DepartmentHead],
    },

    // ============================================
    // STUDENT SERVICES
    // ============================================
    {
        id: "admin-students",
        title: "Student Records",
        icon: <IconUser className="size-4" />,
        link: "/administrators/students",
        section: "student_services",
        requiredPermission: "ViewAny:Student",
        subs: [
            {
                title: "All Students",
                link: "/administrators/students",
                icon: <IconUsers className="size-4" />,
            },
            {
                title: "Documents",
                link: "/administrators/students/documents",
                icon: <IconFileDescription className="size-4" />,
            },
        ],
        allowedRoles: [
            ...SYSTEM_ADMIN_ROLES,
            ...STUDENT_SERVICES_ROLES,
            ...FINANCE_ROLES,
            UserRole.Dean,
            UserRole.DepartmentHead,
            UserRole.ProgramChair,
        ],
    },
    {
        id: "admin-enrollments",
        title: "Enrollments",
        icon: <IconChecklist className="size-4" />,
        link: "/administrators/enrollments",
        section: "student_services",
        requiredPermission: "ViewAny:StudentEnrollment",
        subs: [
            {
                title: "Students",
                link: "/administrators/enrollments",
                icon: <IconUsers className="size-4" />,
            },
            {
                title: "Applicants",
                link: "/administrators/enrollments/applicants",
                icon: <IconUserPlus className="size-4" />,
            },
        ],
        allowedRoles: [...SYSTEM_ADMIN_ROLES, UserRole.Registrar, UserRole.AssistantRegistrar, ...FINANCE_ROLES, UserRole.DepartmentHead],
    },
    {
        id: "admin-document-requests",
        title: "Document Requests",
        icon: <IconCertificate className="size-4" />,
        link: "/administrators/document-requests",
        section: "student_services",
        disabled: true,
        disabledTooltip: "Document request tracking coming soon",
        requiredPermission: "ViewAny:Student",
        allowedRoles: [...SYSTEM_ADMIN_ROLES, UserRole.Registrar, UserRole.AssistantRegistrar],
    },
    {
        id: "admin-student-clearance",
        title: "Student Clearance",
        icon: <IconUserCheck className="size-4" />,
        link: "/administrators/clearance",
        section: "student_services",
        disabled: true,
        disabledTooltip: "Clearance management coming soon",
        requiredPermission: "View:ManageStudentClearances",
        allowedRoles: [...SYSTEM_ADMIN_ROLES, ...STUDENT_SERVICES_ROLES, ...FINANCE_ROLES],
    },
    {
        id: "admin-library",
        title: "Library System",
        icon: <IconBooks className="size-4" />,
        link: "/administrators/library",
        section: "library",
        requiredPermission: ["ViewAny:Book", "ViewAny:Author", "ViewAny:Category", "ViewAny:BorrowRecord"],
        allowedRoles: [...SYSTEM_ADMIN_ROLES, ...STUDENT_SERVICES_ROLES],
        subs: [
            {
                title: "Overview",
                link: "/administrators/library",
                icon: <IconDashboard className="size-4" />,
            },
            {
                title: "Books",
                link: "/administrators/library/books",
                icon: <IconBook className="size-4" />,
            },
            {
                title: "Authors",
                link: "/administrators/library/authors",
                icon: <IconUser className="size-4" />,
            },
            {
                title: "Categories",
                link: "/administrators/library/categories",
                icon: <IconChecklist className="size-4" />,
            },
            {
                title: "Borrow Records",
                link: "/administrators/library/borrow-records",
                icon: <IconClipboardCheck className="size-4" />,
            },
            {
                title: "Research Papers",
                link: "/administrators/library/research-papers",
                icon: <IconFileDescription className="size-4" />,
            },
        ],
    },
    {
        id: "admin-guidance",
        title: "Guidance & Counseling",
        icon: <IconUserCircle className="size-4" />,
        link: "/administrators/guidance",
        section: "student_services",
        disabled: true,
        disabledTooltip: "Guidance module coming soon",
        requiredPermission: "ViewAny:Student",
        allowedRoles: [...SYSTEM_ADMIN_ROLES, UserRole.GuidanceCounselor, UserRole.StudentAffairsOfficer],
    },
    {
        id: "admin-medical-records",
        title: "Medical Records",
        icon: <IconMedicalCross className="size-4" />,
        link: "/administrators/medical-records",
        section: "student_services",
        disabled: true,
        disabledTooltip: "Medical records coming soon",
        requiredPermission: "ViewAny:MedicalRecord",
        allowedRoles: SYSTEM_ADMIN_ROLES,
    },

    // ============================================
    // FINANCE & BILLING
    // ============================================
    {
        id: "admin-finance-overview",
        title: "Finance Overview",
        icon: <IconChartBar className="size-4" />,
        link: "/administrators/finance",
        section: "finance",
        requiredPermission: "View:Cashier",
        allowedRoles: [...SYSTEM_ADMIN_ROLES, ...FINANCE_ROLES, UserRole.President, UserRole.VicePresident],
    },
    {
        id: "admin-invoices",
        title: "Invoices & Billing",
        icon: <IconFileDescription className="size-4" />,
        link: "/administrators/finance/invoices",
        section: "finance",
        requiredPermission: "View:Cashier",
        allowedRoles: [...SYSTEM_ADMIN_ROLES, ...FINANCE_ROLES],
    },
    {
        id: "admin-payments",
        title: "Payment History",
        icon: <IconCash className="size-4" />,
        link: "/administrators/finance/payments",
        section: "finance",
        requiredPermission: "View:Cashier",
        allowedRoles: [...SYSTEM_ADMIN_ROLES, ...FINANCE_ROLES, UserRole.Cashier],
    },
    {
        id: "admin-financial-reports",
        title: "Financial Reports",
        icon: <IconReportAnalytics className="size-4" />,
        link: "/administrators/finance/reports",
        section: "finance",
        requiredPermission: "View:Cashier",
        allowedRoles: [...SYSTEM_ADMIN_ROLES, UserRole.AccountingOfficer, UserRole.BursarOfficer, UserRole.President, UserRole.VicePresident],
    },
    {
        id: "admin-scholarships",
        title: "Scholarships & Discounts",
        icon: <IconGavel className="size-4" />,
        link: "/administrators/scholarships",
        section: "finance",
        disabled: true,
        disabledTooltip: "Scholarship management coming soon",
        requiredPermission: "View:ScholarshipStatsWidget",
        allowedRoles: [...SYSTEM_ADMIN_ROLES, ...FINANCE_ROLES],
    },

    // ============================================
    // HUMAN RESOURCES
    // ============================================
    {
        id: "admin-employees",
        title: "Employee Management",
        icon: <IconUsersGroup className="size-4" />,
        link: "/administrators/employees",
        section: "hr",
        disabled: true,
        disabledTooltip: "Employee management coming soon",
        requiredPermission: "ViewAny:Faculty",
        allowedRoles: [...SYSTEM_ADMIN_ROLES, ...HR_ROLES],
    },
    {
        id: "admin-departments",
        title: "Departments",
        icon: <IconBuilding className="size-4" />,
        link: "/administrators/departments",
        section: "hr",
        disabled: true,
        disabledTooltip: "Department management coming soon",
        requiredPermission: "ViewAny:Department",
        allowedRoles: [...SYSTEM_ADMIN_ROLES, ...HR_ROLES, UserRole.President, UserRole.VicePresident],
    },
    {
        id: "admin-payroll",
        title: "Payroll",
        icon: <IconBriefcase className="size-4" />,
        link: "/administrators/payroll",
        section: "hr",
        disabled: true,
        disabledTooltip: "Payroll system coming soon",
        requiredPermission: "View:Cashier",
        allowedRoles: [...SYSTEM_ADMIN_ROLES, ...HR_ROLES, ...FINANCE_ROLES],
    },

    // ============================================
    // SYSTEM ADMINISTRATION
    // ============================================
    {
        id: "admin-inventory",
        title: "Inventory System",
        icon: <IconTools className="size-4" />,
        link: "/administrators/inventory",
        section: "inventory",
        requiredPermission: ["ViewAny:InventoryProduct", "ViewAny:InventoryBorrowing"],
        allowedRoles: [...SYSTEM_ADMIN_ROLES, ...IT_SUPPORT_ROLES],
        subs: [
            {
                title: "Overview",
                link: "/administrators/inventory",
                icon: <IconDashboard className="size-4" />,
            },
            {
                title: "Tool Inventory",
                link: "/administrators/inventory/items?item_type=tool",
                icon: <IconChecklist className="size-4" />,
            },
            {
                title: "Network Devices",
                link: "/administrators/inventory/items?item_type=network",
                icon: <IconServer className="size-4" />,
            },
            {
                title: "Borrow Logs",
                link: "/administrators/inventory/borrowings",
                icon: <IconClipboardCheck className="size-4" />,
            },
        ],
    },
    {
        id: "admin-users",
        title: "User Management",
        icon: <IconShieldLock className="size-4" />,
        link: "/administrators/users",
        section: "system",
        requiredPermission: "ViewAny:User",
        allowedRoles: SYSTEM_ADMIN_ROLES,
    },
    {
        id: "admin-roles",
        title: "Roles & Permissions",
        icon: <IconShieldLock className="size-4" />,
        link: "/administrators/roles",
        section: "system",
        requiredPermission: "ViewAny:User",
        allowedRoles: [UserRole.Developer, UserRole.SuperAdmin],
    },
    {
        id: "admin-sanity-content",
        title: "Content Management",
        icon: <IconNews className="size-4" />,
        link: "/administrators/sanity-content",
        section: "system",
        requiredPermission: "View:ManageSiteSettings",
        allowedRoles: SYSTEM_ADMIN_ROLES,
    },
    {
        id: "admin-audit-logs",
        title: "Audit Logs",
        icon: <IconHistory className="size-4" />,
        link: "/administrators/audit-logs",
        section: "system",
        requiredPermission: "View:LogTable",
        allowedRoles: SYSTEM_ADMIN_ROLES,
    },
    {
        id: "admin-onboarding-features",
        title: " Feature Flags",
        icon: <IconSparkles className="size-4" />,
        link: "/administrators/onboarding-features",
        section: "system",
        requiredPermission: "View:ModuleManager",
        allowedRoles: SYSTEM_ADMIN_ROLES,
        badge: "New",
    },
    {
        id: "admin-reports",
        title: "Reports & Analytics",
        icon: <IconReportAnalytics className="size-4" />,
        link: "/administrators/reports",
        section: "system",
        disabled: true,
        disabledTooltip: "Advanced reports coming soon",
        requiredPermission: "View:StudentAnalyticsStatsOverview",
        allowedRoles: SYSTEM_ADMIN_ROLES,
    },
    {
        id: "admin-system-health",
        title: "System Health",
        icon: <IconServer className="size-4" />,
        link: "/administrators/system-health",
        section: "system",
        disabled: true,
        disabledTooltip: "System monitoring coming soon",
        requiredPermission: "View:GeneralSettings",
        allowedRoles: [UserRole.Developer, UserRole.SuperAdmin],
    },
    {
        id: "admin-approvals",
        title: "Approval Workflows",
        icon: <IconFileAnalytics className="size-4" />,
        link: "/administrators/approvals",
        section: "system",
        disabled: true,
        disabledTooltip: "Approval workflows coming soon",
        requiredPermission: "ViewAny:User",
        allowedRoles: SYSTEM_ADMIN_ROLES,
    },
    {
        id: "admin-settings",
        title: "Profile",
        icon: <IconUser className="size-4" />,
        link: "/administrators/settings",
        section: "system",
        requiredPermission: "View:EditProfilePage",
        allowedRoles: SYSTEM_ADMIN_ROLES,
    },
    {
        id: "admin-system-management",
        title: "System Management",
        icon: <IconSettings className="size-4" />,
        link: "/administrators/system-management/school",
        section: "system",
        requiredPermission: [
            "View:SystemManagementSchool",
            "Update:SystemManagementSchool",
            "View:SystemManagementEnrollmentPipeline",
            "Update:SystemManagementEnrollmentPipeline",
            "View:SystemManagementSeo",
            "Update:SystemManagementSeo",
            "View:SystemManagementAnalytics",
            "Update:SystemManagementAnalytics",
            "View:SystemManagementBrand",
            "Update:SystemManagementBrand",
            "View:SystemManagementSanity",
            "Update:SystemManagementSanity",
            "View:SystemManagementSocialite",
            "Update:SystemManagementSocialite",
            "View:SystemManagementMail",
            "Update:SystemManagementMail",
            "View:SystemManagementApi",
            "Update:SystemManagementApi",
            "View:SystemManagementNotifications",
            "Update:SystemManagementNotifications",
            "View:SystemManagementPulse",
        ],
        allowedRoles: SYSTEM_ADMIN_ROLES,
        subs: [
            {
                title: "School & Campus",
                link: "/administrators/system-management/school",
                icon: <IconBuilding className="size-4" />,
            },
            {
                title: "Enrollment Pipeline",
                link: "/administrators/system-management/enrollment-pipeline",
                icon: <IconChecklist className="size-4" />,
            },
            {
                title: "SEO & Metadata",
                link: "/administrators/system-management/seo",
                icon: <IconFileAnalytics className="size-4" />,
            },
            {
                title: "Analytics",
                link: "/administrators/system-management/analytics",
                icon: <IconChartBar className="size-4" />,
            },
            {
                title: "Brand & Appearance",
                link: "/administrators/system-management/brand",
                icon: <IconSparkles className="size-4" />,
            },
            {
                title: "Content (Sanity)",
                link: "/administrators/system-management/sanity",
                icon: <IconNews className="size-4" />,
            },
            {
                title: "Social Auth",
                link: "/administrators/system-management/socialite",
                icon: <IconUserCircle className="size-4" />,
            },
            {
                title: "Mail Server",
                link: "/administrators/system-management/mail",
                icon: <IconBell className="size-4" />,
            },
            {
                title: "API Management",
                link: "/administrators/system-management/api",
                icon: <IconServer className="size-4" />,
            },
            {
                title: "Notifications",
                link: "/administrators/system-management/notifications",
                icon: <IconBell className="size-4" />,
            },
            {
                title: "System Pulse",
                link: "/administrators/system-management/pulse",
                icon: <IconServer className="size-4" />,
            },
        ],
    },

    // ============================================
    // SUPPORT
    // ============================================
    {
        id: "admin-help-tickets",
        title: "Help Desk",
        icon: <IconHelp className="size-4" />,
        link: "/administrators/help-tickets",
        section: "support",
        allowedRoles: [...SYSTEM_ADMIN_ROLES, ...IT_SUPPORT_ROLES, UserRole.StudentAffairsOfficer],
    },
    {
        id: "admin-help",
        title: "Help & Documentation",
        icon: <IconBook className="size-4" />,
        link: "/help",
        section: "support",
        separator: true,
    },
];

/**
 * Check if a role is a system administrator (has full access)
 */
function isSystemAdminRole(role: string): boolean {
    // Check against enum values string AND labels
    return [
        UserRole.Developer,
        UserRole.SuperAdmin,
        UserRole.Admin,
        "admin",
        "super_admin",
        "developer",
        "System Administrator",
        "Super Administrator",
        "System Developer",
    ].includes(role as any);
}

/**
 * Check if user has the required permission(s) for a route
 */
export function hasRequiredPermission(userPermissions: string[], requiredPermission?: string | string[]): boolean {
    // No permission required = everyone has access
    if (!requiredPermission) {
        return true;
    }

    // Normalize to array
    const permissions = Array.isArray(requiredPermission) ? requiredPermission : [requiredPermission];

    // User needs at least one of the required permissions
    return permissions.some((perm) => userPermissions.includes(perm));
}

/**
 * Check if user matches the allowed roles for a route.
 */
export function hasAllowedRole(userRole: string, allowedRoles?: UserRole[]): boolean {
    if (!allowedRoles || allowedRoles.length === 0) {
        return true;
    }

    return allowedRoles.includes(userRole as UserRole);
}

/**
 * Check if a user can access a route.
 *
 * Permission-protected routes are permission-driven.
 * Role checks are only used for routes without an explicit permission.
 */
export function canAccessRoute(route: AdminRoute, userRole: string, userPermissions: string[] = []): boolean {
    if (isSystemAdminRole(userRole)) {
        return true;
    }

    if (route.requiredPermission) {
        return hasRequiredPermission(userPermissions, route.requiredPermission);
    }

    return hasAllowedRole(userRole, route.allowedRoles);
}

/**
 * Filter routes based on user role and permissions.
 *
 * Access is granted if ANY of these conditions are met:
 * 1. User is a system admin (developer, super_admin, admin) - full access
 * 2. Route declares requiredPermission and user has at least one of them (Spatie/Shield)
 * 3. Route has no requiredPermission and the user's role is in allowedRoles
 */
export function getRoutesForRole(userRole: string, userPermissions: string[] = []): AdminRoute[] {
    return ADMIN_ROUTES.filter((route) => canAccessRoute(route, userRole, userPermissions));
}

/**
 * Get routes grouped by section for a specific role
 */
export function getGroupedRoutesForRole(userRole: string, userPermissions: string[] = []): Map<RouteSection, AdminRoute[]> {
    const routes = getRoutesForRole(userRole, userPermissions);
    const grouped = new Map<RouteSection, AdminRoute[]>();

    routes.forEach((route) => {
        const section = route.section || "core";
        const existing = grouped.get(section) || [];
        grouped.set(section, [...existing, route]);
    });

    return grouped;
}

/**
 * Get visible sections for a specific role
 */
export function getVisibleSections(userRole: string, userPermissions: string[] = []): SectionConfig[] {
    const groupedRoutes = getGroupedRoutesForRole(userRole, userPermissions);

    return ROUTE_SECTIONS.filter((section) => (groupedRoutes.get(section.id)?.length ?? 0) > 0);
}

/**
 * Get section title by ID
 */
export function getSectionTitle(sectionId: RouteSection): string {
    const section = ROUTE_SECTIONS.find((s) => s.id === sectionId);
    return section?.title || sectionId;
}
