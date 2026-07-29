"use client";

import { getFacultyPortalNavigation, type FacultyPortalClass } from "@/components/faculty/faculty-navigation";
import { NavMain, type NavItem } from "@/components/nav-main";
import { NavSecondary } from "@/components/nav-secondary";
import { NavUser } from "@/components/nav-user";
import type { SemesterSelectorProps } from "@/components/semester-selector";
import { NotificationsPopover } from "@/components/sidebar-03/nav-notifications";
import { getStudentPortalNavigation, type StudentPortalClass } from "@/components/student/student-navigation";
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarGroup,
    SidebarGroupContent,
    SidebarGroupLabel,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    useSidebar,
} from "@/components/ui/sidebar";
import { resolveBranding, type Branding } from "@/lib/branding";
import { isFacultyPortalRole, isStudentPortalRole, normalizePortalRole } from "@/lib/portal-role";
import { cn } from "@/lib/utils";
import { inbox as facultyNotificationsInbox } from "@/routes/faculty/notifications";
import { index as libraryIndex } from "@/routes/library";
import { inbox as studentNotificationsInbox } from "@/routes/student/notifications";
import { User } from "@/types/user";
import { Link, router, usePage } from "@inertiajs/react";
import {
    IconBooks,
    IconBriefcase,
    IconCalendar,
    IconChartBar,
    IconChevronDown,
    IconDashboard,
    IconHelp,
    IconReceipt,
    IconSchool,
    IconSettings,
    IconSpeakerphone,
    IconUsers,
} from "@tabler/icons-react";
import * as React from "react";
import { useMemo, useState } from "react";

type FacultyClass = FacultyPortalClass;

interface PageProps {
    auth?: {
        user?: User | null;
    };
    version?: string;
    featureFlags?: {
        experimentalKeys?: string[];
        enabledRoutes?: Record<string, boolean>;
    };
    branding?: Partial<Branding> | null;
    facultyClasses?: FacultyClass[];
    studentClasses?: StudentPortalClass[];
    settings?: SemesterSelectorProps;
    [key: string]: unknown;
}

function useFeatureFlagRoutes(
    isStudent: boolean,
    isFaculty: boolean,
    facultyClasses: FacultyClass[] = [],
    studentClasses: StudentPortalClass[] = [],
): NavItem[] {
    const { props } = usePage<PageProps>();
    const enabledRoutes = props.featureFlags?.enabledRoutes || {};

    return useMemo(() => {
        if (isStudent) {
            return getStudentRoutes(enabledRoutes, studentClasses);
        }
        if (isFaculty) {
            return getFacultyRoutes(enabledRoutes, facultyClasses);
        }
        return getStaffRoutes(enabledRoutes);
    }, [isStudent, isFaculty, enabledRoutes, facultyClasses, studentClasses]);
}

function getStudentRoutes(enabledRoutes: Record<string, boolean>, studentClasses: StudentPortalClass[] = []): NavItem[] {
    return getStudentPortalNavigation(enabledRoutes, studentClasses);
}

function getFacultyRoutes(enabledRoutes: Record<string, boolean>, facultyClasses: FacultyClass[] = []): NavItem[] {
    return getFacultyPortalNavigation(enabledRoutes, facultyClasses);
}

function getStaffRoutes(enabledRoutes: Record<string, boolean>): NavItem[] {
    const isEnabled = (routeId: string): boolean => {
        return enabledRoutes[routeId] === true;
    };

    return [
        {
            id: "dashboard",
            title: "Dashboard",
            icon: IconDashboard,
            url: "/admin/dashboard",
        },
        ...(isEnabled("library")
            ? [
                  {
                      id: "library",
                      title: "Digital Library",
                      icon: IconBooks,
                      url: libraryIndex.url(),
                  },
              ]
            : []),
        {
            id: "users",
            title: "User Management",
            icon: IconUsers,
            url: isEnabled("users") ? "/admin/users" : "#",
            disabled: !isEnabled("users"),
            disabledTooltip: "User management coming soon",
        },
        {
            id: "students",
            title: "Student Management",
            icon: IconSchool,
            url: isEnabled("students") ? "/admin/students" : "#",
            disabled: !isEnabled("students"),
            disabledTooltip: "Student management coming soon",
        },
        {
            id: "faculty",
            title: "Faculty Management",
            icon: IconBriefcase,
            url: isEnabled("faculty") ? "/admin/faculty" : "#",
            disabled: !isEnabled("faculty"),
            disabledTooltip: "Faculty management coming soon",
        },
        {
            id: "finances",
            title: "Finance",
            icon: IconReceipt,
            url: isEnabled("finances") ? "/admin/finances" : "#",
            disabled: !isEnabled("finances"),
            disabledTooltip: "Finance management coming soon",
        },
        {
            id: "reports",
            title: "Reports & Analytics",
            icon: IconChartBar,
            url: isEnabled("reports") ? "/admin/reports" : "#",
            disabled: !isEnabled("reports"),
            disabledTooltip: "Reports coming soon",
        },
        {
            id: "announcements",
            title: "Announcements",
            icon: IconSpeakerphone,
            url: "/admin/announcements",
        },
        {
            id: "settings",
            title: "Settings",
            icon: IconSettings,
            url: "/admin/settings",
            separator: true,
        },
    ];
}

function getSecondaryRoutes(isStudent: boolean, isStaff: boolean): NavItem[] {
    return [
        {
            id: "settings",
            title: "Settings",
            icon: IconSettings,
            url: isStudent ? "/student/profile" : isStaff ? "/admin/settings" : "/faculty/profile",
        },
        {
            id: "help",
            title: "Help & Support",
            icon: IconHelp,
            url: "/help",
        },
    ];
}

/**
 * Compact semester / school-year selector designed for the sidebar.
 * Shown for student and faculty portals where the header version
 * is hidden on mobile.
 */
function SidebarSemesterSelector({ settings }: { settings: SemesterSelectorProps }) {
    const [open, setOpen] = useState(false);

    const { currentSemester, currentSchoolYear, systemSemester, systemSchoolYear, availableSemesters, availableSchoolYears } = settings;

    const safeAvailableSemesters: Record<number, string> = availableSemesters ?? {};
    const safeAvailableSchoolYears: Record<number, string> = availableSchoolYears ?? {};

    const currentSemesterLabel = currentSemester != null ? (safeAvailableSemesters[currentSemester] ?? "\u2014") : "\u2014";
    const currentSchoolYearLabel = currentSchoolYear != null ? (safeAvailableSchoolYears[currentSchoolYear] ?? "\u2014") : "\u2014";

    const hasSemesterOverride = systemSemester != null && currentSemester != null && systemSemester !== currentSemester;
    const hasSchoolYearOverride = systemSchoolYear != null && currentSchoolYear != null && systemSchoolYear !== currentSchoolYear;
    const hasAnyOverride = hasSemesterOverride || hasSchoolYearOverride;

    function resolveSettingsEndpoint(path: "semester" | "school-year"): string {
        if (typeof window !== "undefined") {
            const pathname = window.location.pathname;
            if (pathname.startsWith("/student")) return `/student/settings/${path}`;
            if (pathname.startsWith("/faculty")) return `/faculty/settings/${path}`;
        }
        return `/settings/${path}`;
    }

    const handleSemesterChange = (value: string) => {
        router.put(resolveSettingsEndpoint("semester"), { semester: parseInt(value) }, { preserveScroll: true });
    };

    const handleSchoolYearChange = (value: string) => {
        router.put(resolveSettingsEndpoint("school-year"), { school_year_start: parseInt(value) }, { preserveScroll: true });
    };

    return (
        <SidebarGroup className="mt-2">
            <SidebarGroupLabel asChild>
                <button 
                    type="button" 
                    onClick={() => setOpen((v) => !v)} 
                    className="hover:text-foreground flex w-full items-center justify-between transition-colors"
                >
                    <span className="flex items-center gap-2 font-bold tracking-tight">
                        <IconCalendar className="text-primary size-4" />
                        Academic Period
                    </span>
                    <IconChevronDown className={cn("size-3.5 transition-transform duration-300", open && "rotate-180")} />
                </button>
            </SidebarGroupLabel>

            {!open && (
                <SidebarGroupContent>
                    <button
                        type="button"
                        onClick={() => setOpen(true)}
                        className="bg-muted/40 hover:bg-muted border-border/40 text-muted-foreground hover:text-foreground mx-2 mb-1 mt-1 flex items-center gap-2.5 rounded-xl border p-2 transition-all duration-200"
                    >
                        <div className="bg-primary/10 flex size-8 shrink-0 items-center justify-center rounded-lg">
                             <div className="bg-primary size-2 rounded-full animate-pulse" />
                        </div>
                        <div className="flex flex-1 flex-col items-start overflow-hidden">
                            <div className="flex w-full items-center justify-between gap-1">
                                <span className="truncate text-[10px] font-bold tracking-tight text-foreground uppercase">
                                    {currentSemesterLabel}
                                </span>
                                {hasAnyOverride && (
                                    <span className="bg-primary/15 text-primary shrink-0 rounded px-1 py-0.5 text-[7px] font-black uppercase leading-none">
                                        Custom
                                    </span>
                                )}
                            </div>
                            <span className="truncate text-[10px] font-medium opacity-60">
                                {currentSchoolYearLabel}
                            </span>
                        </div>
                    </button>
                </SidebarGroupContent>
            )}

            {open && (
                <SidebarGroupContent>
                    <div className="flex flex-col gap-2 px-2 pb-2">
                        {/* Semester select */}
                        <div className="flex flex-col gap-1">
                            <label className="text-muted-foreground px-0.5 text-[10px] font-medium tracking-wider uppercase">Semester</label>
                            <select
                                value={currentSemester?.toString() ?? ""}
                                onChange={(e) => handleSemesterChange(e.target.value)}
                                className="bg-muted/50 text-foreground border-border hover:bg-muted focus:ring-primary h-8 w-full rounded-lg border px-2.5 text-xs transition-colors focus:ring-1 focus:outline-none"
                            >
                                {Object.entries(safeAvailableSemesters).map(([key, label]) => (
                                    <option key={key} value={key}>
                                        {label}
                                    </option>
                                ))}
                            </select>
                            {hasSemesterOverride && (
                                <span className="text-muted-foreground/70 px-0.5 text-[10px]">
                                    System: {systemSemester != null ? (safeAvailableSemesters[systemSemester] ?? "Default") : "Default"}
                                </span>
                            )}
                        </div>

                        {/* School Year select */}
                        <div className="flex flex-col gap-1">
                            <label className="text-muted-foreground px-0.5 text-[10px] font-medium tracking-wider uppercase">School Year</label>
                            <select
                                value={currentSchoolYear?.toString() ?? ""}
                                onChange={(e) => handleSchoolYearChange(e.target.value)}
                                className="bg-muted/50 text-foreground border-border hover:bg-muted focus:ring-primary h-8 w-full rounded-lg border px-2.5 text-xs transition-colors focus:ring-1 focus:outline-none"
                            >
                                {Object.entries(safeAvailableSchoolYears).map(([key, label]) => (
                                    <option key={key} value={key}>
                                        {label}
                                    </option>
                                ))}
                            </select>
                            {hasSchoolYearOverride && (
                                <span className="text-muted-foreground/70 px-0.5 text-[10px]">
                                    System: {systemSchoolYear != null ? (safeAvailableSchoolYears[systemSchoolYear] ?? "Default") : "Default"}
                                </span>
                            )}
                        </div>
                    </div>
                </SidebarGroupContent>
            )}
        </SidebarGroup>
    );
}

export function PortalSidebar({ user, ...props }: React.ComponentProps<typeof Sidebar> & { user?: User }) {
    const { props: pageProps, url } = usePage<PageProps>();
    const branding = resolveBranding(pageProps.branding);
    const appName = branding.appName;
    const organizationShortName = branding.organizationShortName;
    const version = pageProps.version || "1.0.0";
    const facultyClasses = pageProps.facultyClasses || [];
    const studentClasses = pageProps.studentClasses || [];
    const pathname = url.split("?")[0];
    const resolvedUser = pageProps.auth?.user ?? user;
    const normalizedRole = normalizePortalRole(resolvedUser?.role);
    const isStudent = pathname.startsWith("/student") || isStudentPortalRole(normalizedRole);
    const isFaculty = pathname.startsWith("/faculty") || isFacultyPortalRole(normalizedRole);
    const isStaff = !isStudent && !isFaculty;
    const mainRoutes = useFeatureFlagRoutes(isStudent, isFaculty, facultyClasses, studentClasses);
    const secondaryRoutes = getSecondaryRoutes(isStudent, isStaff);
    const { state } = useSidebar();

    const getDashboardUrl = (): string => {
        if (isStudent) return "/student/dashboard";
        if (isFaculty) return "/faculty/dashboard";
        return "/admin/dashboard";
    };

    const getPortalLabel = (): string => {
        if (isStudent) return "Student Portal";
        if (isFaculty) return "Faculty Portal";
        return "Admin Portal";
    };

    const getNotificationsUrl = (): string => {
        if (isStudent) return "/student/notifications";
        if (isFaculty) return "/faculty/notifications";
        return "/admin/notifications";
    };

    const getNotificationsInboxUrl = (): string | undefined => {
        if (isStudent) return studentNotificationsInbox.url();
        if (isFaculty) return facultyNotificationsInbox.url();
        return undefined;
    };

    return (
        <Sidebar collapsible="offcanvas" {...props}>
            <SidebarHeader
                className={cn(
                    "border-sidebar-border/60 flex items-center justify-between gap-2 border-b px-3 py-3",
                    state === "collapsed" ? "flex-col" : "flex-row",
                )}
            >
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild className="rounded-xl">
                            <Link href={getDashboardUrl()}>
                                <div className="border-border/50 flex aspect-square size-9 items-center justify-center overflow-hidden rounded-lg border bg-white shadow-sm">
                                    <img src={branding.logo} alt={`${organizationShortName} Logo`} className="size-5 object-contain" />
                                </div>
                                <div className="grid flex-1 text-left text-sm leading-tight">
                                    <span className="truncate font-semibold">{appName}</span>
                                    <span className="truncate text-xs">{getPortalLabel()}</span>
                                </div>
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
                <NotificationsPopover baseUrl={getNotificationsUrl()} inboxUrl={getNotificationsInboxUrl()} />
            </SidebarHeader>
            <SidebarContent>
                <NavMain items={mainRoutes} showQuickActions={false} />
                {(isStudent || isFaculty) && pageProps.settings ? <SidebarSemesterSelector settings={pageProps.settings} /> : null}
                <NavSecondary items={secondaryRoutes} className="mt-auto" />
            </SidebarContent>
            <SidebarFooter>
                <NavUser
                    user={{
                        name: resolvedUser?.name ?? "User",
                        email: resolvedUser?.email ?? "",
                        avatar: resolvedUser?.avatar ?? "",
                        role: resolvedUser?.role ?? "",
                    }}
                />
                <div className="px-2 py-1">
                    <Link
                        href="/changelog"
                        className="text-muted-foreground hover:text-foreground inline-flex items-center gap-1 rounded px-1.5 py-0.5 text-xs font-medium transition-colors"
                    >
                        <span className="inline-flex items-center gap-1">
                            <span className="h-1.5 w-1.5 rounded-full bg-green-500"></span>v{version}
                        </span>
                    </Link>
                </div>
            </SidebarFooter>
        </Sidebar>
    );
}
