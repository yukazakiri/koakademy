"use client";

import { IconBooks, IconBriefcase, IconCash, IconDashboard, IconHelp, IconSchool, IconServer, IconTools, IconUser } from "@tabler/icons-react";
import * as React from "react";

import { NavUser } from "@/components/nav-user";
import { SchoolSwitcher } from "@/components/school-switcher";
import { NotificationsPopover } from "@/components/sidebar-03/nav-notifications";
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarGroup,
    SidebarGroupContent,
    SidebarHeader,
    SidebarInput,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    SidebarMenuSub,
    SidebarMenuSubButton,
    SidebarMenuSubItem,
    useSidebar,
} from "@/components/ui/sidebar";
import { getRoutesForRoleWithModules, ROUTE_SECTIONS, type AdminRoute, type ModuleAdminRoute, type RouteSection } from "@/config/admin-routes";
import { resolveBranding, type Branding } from "@/lib/branding";
import type { User } from "@/types/user";
import { USER_ROLE_LABELS, UserRole } from "@/types/user-role";
import { Link, usePage } from "@inertiajs/react";

interface PageProps {
    auth?: {
        user?: User | null;
    };
    version?: string;
    branding?: Partial<Branding> | null;
    unresolvedHelpTicketsCount?: number;
    adminSidebarCounts?: AdminSidebarCounts | null;
    moduleAdminRoutes?: ModuleAdminRoute[];
    [key: string]: unknown;
}

interface AdminSidebarCounts {
    students: number;
    enrollments: number;
    faculties: number;
    users: number;
}

// Section icons mapping
const SECTION_ICONS: Record<RouteSection, React.ElementType> = {
    core: IconDashboard,
    academic: IconSchool,
    student_services: IconUser,
    finance: IconCash,
    hr: IconBriefcase,
    system: IconServer,
    library: IconBooks,
    inventory: IconTools,
    support: IconHelp,
};

// Section labels for tooltips
const SECTION_LABELS: Record<RouteSection, string> = {
    core: "Overview",
    academic: "Academics",
    student_services: "Registrar",
    finance: "Finance",
    hr: "HR",
    system: "System",
    library: "Library",
    inventory: "Property",
    support: "Support",
};

const ADMIN_SIDEBAR_ICON_WIDTH = "3rem";
const ADMIN_SIDEBAR_WIDTH = "16rem";
const ADMIN_SIDEBAR_CONTENT_WIDTH = `calc(${ADMIN_SIDEBAR_WIDTH} - ${ADMIN_SIDEBAR_ICON_WIDTH})`;

/**
 * Helper to normalize role (convert label back to ID if needed)
 */
function normalizeRole(role: string): string {
    if (Object.values(UserRole).includes(role as UserRole)) {
        return role;
    }

    const entry = Object.entries(USER_ROLE_LABELS).find(([, label]) => label === role);
    if (entry) {
        return entry[0];
    }

    return role;
}

interface SearchableRoute extends AdminRoute {
    sectionId: RouteSection;
    sectionLabel: string;
    isSub?: boolean;
    parentTitle?: string;
}

/**
 * Get routes organized by section for a specific user role and permissions
 */
function useOrganizedRoutes(userRole: string, userPermissions: string[] = [], moduleRoutes: ModuleAdminRoute[] = []) {
    return React.useMemo(() => {
        const normalizedRole = normalizeRole(userRole);
        const allowedRoutes = getRoutesForRoleWithModules(normalizedRole, userPermissions, moduleRoutes);

        // Group routes by section
        const groupedRoutes = new Map<RouteSection, AdminRoute[]>();

        allowedRoutes.forEach((route) => {
            if (route.disabled) return;

            const section = route.section || "core";
            const existing = groupedRoutes.get(section) || [];

            const processedRoute = { ...route };
            if (processedRoute.subs) {
                processedRoute.subs = processedRoute.subs.filter((sub) => !sub.disabled);
            }

            groupedRoutes.set(section, [...existing, processedRoute]);
        });

        // Filter sections that have routes
        const sectionsWithRoutes = ROUTE_SECTIONS.filter((section) => {
            const routes = groupedRoutes.get(section.id);
            return routes && routes.length > 0;
        });

        // Flatten all routes for search (include sub-routes as separate items)
        const allSearchableRoutes: SearchableRoute[] = [];
        sectionsWithRoutes.forEach((section) => {
            const routes = groupedRoutes.get(section.id) || [];
            routes.forEach((route) => {
                allSearchableRoutes.push({
                    ...route,
                    sectionId: section.id,
                    sectionLabel: section.title,
                });
                // Add sub-routes as searchable items
                if (route.subs) {
                    route.subs.forEach((sub) => {
                        allSearchableRoutes.push({
                            ...sub,
                            id: `${route.id}-${sub.link}`,
                            sectionId: section.id,
                            sectionLabel: section.title,
                            isSub: true,
                            parentTitle: route.title,
                        } as SearchableRoute);
                    });
                }
            });
        });

        return { groupedRoutes, sectionsWithRoutes, allSearchableRoutes, normalizedRole };
    }, [moduleRoutes, userPermissions, userRole]);
}

function isRouteActive(currentUrl: string, routeLink: string, exact = false): boolean {
    if (exact) {
        return currentUrl === routeLink;
    }

    return currentUrl === routeLink || currentUrl.startsWith(`${routeLink}/`);
}

function isParentRouteActive(currentUrl: string, routeLink: string, subs?: { link: string }[]): boolean {
    if (currentUrl === routeLink) {
        return true;
    }
    if (subs) {
        for (const sub of subs) {
            if (currentUrl.startsWith(sub.link)) {
                return false;
            }
        }
    }
    return currentUrl.startsWith(`${routeLink}/`);
}

function getActiveSectionFromUrl(
    currentUrl: string,
    groupedRoutes: Map<RouteSection, AdminRoute[]>,
    sectionsWithRoutes: { id: RouteSection }[],
): RouteSection {
    // Find which section contains the current URL
    for (const section of sectionsWithRoutes) {
        const routes = groupedRoutes.get(section.id) || [];
        for (const route of routes) {
            if (currentUrl.startsWith(route.link)) {
                return section.id;
            }
            // Check sub-routes too
            if (route.subs) {
                for (const sub of route.subs) {
                    if (currentUrl.startsWith(sub.link)) {
                        return section.id;
                    }
                }
            }
        }
    }
    // Default to first section if no match found
    return sectionsWithRoutes.length > 0 ? sectionsWithRoutes[0].id : "core";
}

export function AdministratorSidebar({ user }: { user: User }) {
    const { props, url: currentUrl } = usePage<PageProps>();
    const { setOpen } = useSidebar();
    const version = props.version || "1.0.0";
    const unresolvedHelpTicketsCount = props.unresolvedHelpTicketsCount || 0;
    const branding = resolveBranding(props.branding);
    const adminSidebarCounts = props.adminSidebarCounts ?? null;
    const moduleAdminRoutes = props.moduleAdminRoutes ?? [];
    const appName = branding.appName;
    const organizationShortName = branding.organizationShortName;
    const sharedAuthUser = props.auth?.user;
    const resolvedUserPermissions = sharedAuthUser?.permissions ?? user.permissions ?? [];
    const resolvedUserRole = sharedAuthUser?.role ?? user.role ?? "";
    const resolvedUserName = sharedAuthUser?.name ?? user.name ?? "";
    const resolvedUserEmail = sharedAuthUser?.email ?? user.email ?? "";
    const resolvedUserAvatar = sharedAuthUser?.avatar ?? user.avatar ?? "";

    const { groupedRoutes, sectionsWithRoutes, allSearchableRoutes } = useOrganizedRoutes(
        resolvedUserRole,
        resolvedUserPermissions,
        moduleAdminRoutes,
    );

    // Derive active section from current URL
    const activeSection = React.useMemo(() => {
        return getActiveSectionFromUrl(currentUrl, groupedRoutes, sectionsWithRoutes);
    }, [currentUrl, groupedRoutes, sectionsWithRoutes]);

    // Track user-selected section for when they click on a section icon
    const [userSelectedSection, setUserSelectedSection] = React.useState<RouteSection | null>(null);

    React.useEffect(() => {
        setUserSelectedSection(null);
    }, [currentUrl]);

    // Search functionality
    const [searchQuery, setSearchQuery] = React.useState("");

    // Filter routes based on search query
    const filteredRoutes = React.useMemo(() => {
        if (!searchQuery.trim()) {
            return null;
        }

        const query = searchQuery.toLowerCase().trim();
        return allSearchableRoutes.filter((route) => {
            const titleMatch = route.title.toLowerCase().includes(query);
            const sectionMatch = route.sectionLabel.toLowerCase().includes(query);
            const parentMatch = route.parentTitle?.toLowerCase().includes(query) ?? false;
            return titleMatch || sectionMatch || parentMatch;
        });
    }, [searchQuery, allSearchableRoutes]);

    // Use user-selected section if set, otherwise derive from URL
    const displayedSection = userSelectedSection || activeSection;
    const activeRoutes = groupedRoutes.get(displayedSection) || [];

    const navUserData = {
        name: resolvedUserName,
        email: resolvedUserEmail,
        avatar: resolvedUserAvatar || "",
    };

    const numberFormatter = React.useMemo(() => new Intl.NumberFormat(), []);
    const routeCountMap = React.useMemo<Record<string, number | undefined>>(
        () => ({
            "admin-students": adminSidebarCounts?.students,
            "admin-enrollments": adminSidebarCounts?.enrollments,
            "admin-faculty": adminSidebarCounts?.faculties,
            "admin-users": adminSidebarCounts?.users,
        }),
        [adminSidebarCounts],
    );

    const renderCountBadge = React.useCallback(
        (count?: number) => {
            if (count === null || count === undefined) {
                return null;
            }

            return (
                <span className="bg-muted text-muted-foreground inline-flex h-5 min-w-5 items-center justify-center rounded-full px-1.5 text-[10px] font-medium tabular-nums">
                    {numberFormatter.format(count)}
                </span>
            );
        },
        [numberFormatter],
    );

    const renderRouteBadge = React.useCallback(
        (routeId: string, badge?: AdminRoute["badge"]) => {
            const countBadge = renderCountBadge(routeCountMap[routeId]);
            const routeBadge = badge ? (
                typeof badge === "string" ? (
                    <span className="bg-primary/10 text-primary rounded px-1.5 py-0.5 text-xs">{badge}</span>
                ) : (
                    badge
                )
            ) : null;

            if (!countBadge && !routeBadge) {
                return null;
            }

            return (
                <span className="ml-auto inline-flex items-center gap-1">
                    {countBadge}
                    {routeBadge}
                </span>
            );
        },
        [renderCountBadge, routeCountMap],
    );

    return (
        <Sidebar
            collapsible="icon"
            className="overflow-hidden"
            style={
                {
                    "--sidebar-width": ADMIN_SIDEBAR_WIDTH,
                    "--sidebar-width-icon": ADMIN_SIDEBAR_ICON_WIDTH,
                } as React.CSSProperties
            }
        >
            <div className="flex h-full w-full flex-row">
                {/* First Sidebar - Icon Navigation */}
                <Sidebar
                    collapsible="none"
                    className="border-r bg-sidebar"
                    style={
                        {
                            "--sidebar-width": ADMIN_SIDEBAR_ICON_WIDTH,
                        } as React.CSSProperties
                    }
                >
                    <SidebarHeader>
                        <SidebarMenu>
                            <SidebarMenuItem>
                                <SidebarMenuButton size="lg" asChild className="md:h-8 md:p-0">
                                    <Link href="/administrators/dashboard">
                                        <div className="bg-sidebar-primary text-sidebar-primary-foreground flex aspect-square size-8 items-center justify-center overflow-hidden rounded-lg">
                                            <img
                                                src={branding.logo}
                                                alt={`${organizationShortName} Logo`}
                                                className="size-5 object-contain"
                                            />
                                        </div>
                                        <div className="hidden">
                                            <span className="truncate font-medium">{appName}</span>
                                            <span className="truncate text-xs">Administrator</span>
                                        </div>
                                    </Link>
                                </SidebarMenuButton>
                            </SidebarMenuItem>
                        </SidebarMenu>
                    </SidebarHeader>

                    <SidebarContent>
                        <SidebarGroup>
                            <SidebarGroupContent className="px-1.5 md:px-0">
                                <SidebarMenu>
                                    {sectionsWithRoutes.map((section) => {
                                        const Icon = SECTION_ICONS[section.id];
                                        const isActive = displayedSection === section.id;
                                        // Calculate badge count for this section
                                        let badgeCount = 0;
                                        if (section.id === "support" && unresolvedHelpTicketsCount > 0) {
                                            badgeCount = unresolvedHelpTicketsCount;
                                        }

                                        return (
                                            <SidebarMenuItem key={section.id}>
                                                <SidebarMenuButton
                                                    tooltip={{
                                                        children: SECTION_LABELS[section.id],
                                                        hidden: false,
                                                    }}
                                                    onClick={() => {
                                                        setUserSelectedSection(section.id);
                                                        setOpen(true);
                                                    }}
                                                    isActive={isActive}
                                                    className="px-2.5 md:px-2"
                                                >
                                                    <Icon className="size-4" />
                                                    <span className="sr-only">{SECTION_LABELS[section.id]}</span>
                                                    {badgeCount > 0 && (
                                                        <span className="ml-auto flex h-4 w-4 items-center justify-center rounded-full bg-red-500 text-[10px] font-medium text-white">
                                                            {badgeCount > 9 ? "9+" : badgeCount}
                                                        </span>
                                                    )}
                                                </SidebarMenuButton>
                                            </SidebarMenuItem>
                                        );
                                    })}
                                </SidebarMenu>
                            </SidebarGroupContent>
                        </SidebarGroup>
                    </SidebarContent>

                    <SidebarFooter className="[&_[data-sidebar=menu-button]_.grid]:hidden [&_[data-sidebar=menu-button]_.ml-auto]:hidden">
                        <SidebarMenu>
                            <SidebarMenuItem>
                                <NotificationsPopover baseUrl="/administrators/notifications" />
                            </SidebarMenuItem>
                        </SidebarMenu>
                        <NavUser user={navUserData} />
                    </SidebarFooter>
                </Sidebar>

                {/* Second Sidebar - Section Content */}
                <Sidebar
                    collapsible="none"
                    className="flex-1"
                    style={
                        {
                            "--sidebar-width": ADMIN_SIDEBAR_CONTENT_WIDTH,
                        } as React.CSSProperties
                    }
                >
                    <SidebarHeader className="gap-2 border-b p-4">
                        <SchoolSwitcher />
                        <div className="flex w-full items-center justify-between">
                            <div className="text-foreground text-base font-medium">
                                {searchQuery.trim() ? `Search: "${searchQuery}"` : SECTION_LABELS[displayedSection]}
                            </div>
                        </div>
                        <SidebarInput placeholder="Search navigation..." value={searchQuery} onChange={(e) => setSearchQuery(e.target.value)} />
                    </SidebarHeader>

                    <SidebarContent>
                        <SidebarGroup className="px-0">
                            <SidebarGroupContent>
                                <SidebarMenu>
                                    {searchQuery.trim() ? (
                                        // Search results
                                        filteredRoutes && filteredRoutes.length > 0 ? (
                                            filteredRoutes.map((route) => {
                                                const isActive = isRouteActive(currentUrl, route.link);
                                                const badgeContent = renderRouteBadge(route.id, route.badge);
                                                return (
                                                    <SidebarMenuItem key={route.id}>
                                                        <SidebarMenuButton asChild isActive={isActive}>
                                                            <Link href={route.link}>
                                                                {route.icon}
                                                                <div className="flex flex-col">
                                                                    <span>{route.title}</span>
                                                                    {route.isSub && route.parentTitle && (
                                                                        <span className="text-muted-foreground text-xs">
                                                                            {route.parentTitle} • {route.sectionLabel}
                                                                        </span>
                                                                    )}
                                                                    {!route.isSub && (
                                                                        <span className="text-muted-foreground text-xs">{route.sectionLabel}</span>
                                                                    )}
                                                                </div>
                                                                {badgeContent}
                                                            </Link>
                                                        </SidebarMenuButton>
                                                    </SidebarMenuItem>
                                                );
                                            })
                                        ) : (
                                            <SidebarMenuItem>
                                                <div className="text-muted-foreground px-4 py-2 text-sm">No results found for "{searchQuery}"</div>
                                            </SidebarMenuItem>
                                        )
                                    ) : (
                                        // Normal section routes
                                        activeRoutes.map((route) => {
                                            const hasSubs = route.subs && route.subs.length > 0;

                                            if (hasSubs) {
                                                const isActive = isParentRouteActive(currentUrl, route.link, route.subs);
                                                const badgeContent = renderRouteBadge(route.id, route.badge);

                                                return (
                                                    <SidebarMenuItem key={route.id}>
                                                        <SidebarMenuButton asChild isActive={isActive}>
                                                            <Link href={route.link}>
                                                                {route.icon}
                                                                <span>{route.title}</span>
                                                                {badgeContent}
                                                            </Link>
                                                        </SidebarMenuButton>
                                                        <SidebarMenuSub>
                                                            {route.subs?.map((sub, idx) => {
                                                                const isSubActive = isRouteActive(currentUrl, sub.link, sub.link === route.link);
                                                                return (
                                                                    <SidebarMenuSubItem key={idx}>
                                                                        <SidebarMenuSubButton asChild isActive={isSubActive}>
                                                                            <Link href={sub.link}>
                                                                                {sub.icon}
                                                                                <span>{sub.title}</span>
                                                                            </Link>
                                                                        </SidebarMenuSubButton>
                                                                    </SidebarMenuSubItem>
                                                                );
                                                            })}
                                                        </SidebarMenuSub>
                                                    </SidebarMenuItem>
                                                );
                                            }

                                            const isActive = isRouteActive(currentUrl, route.link);
                                            const badgeContent = renderRouteBadge(route.id, route.badge);

                                            return (
                                                <SidebarMenuItem key={route.id}>
                                                    <SidebarMenuButton asChild isActive={isActive}>
                                                        <Link href={route.link}>
                                                            {route.icon}
                                                            <span>{route.title}</span>
                                                            {badgeContent}
                                                        </Link>
                                                    </SidebarMenuButton>
                                                </SidebarMenuItem>
                                            );
                                        })
                                    )}
                                </SidebarMenu>
                            </SidebarGroupContent>
                        </SidebarGroup>
                    </SidebarContent>

                    <SidebarFooter className="border-t p-4">
                        <div className="flex items-center justify-between">
                            <Link
                                href="/changelog"
                                className="text-muted-foreground hover:text-foreground inline-flex items-center gap-1 rounded px-1.5 py-0.5 text-xs font-medium transition-colors"
                            >
                                <span className="inline-flex items-center gap-1">
                                    <span className="h-1.5 w-1.5 rounded-full bg-green-500"></span>v{version}
                                </span>
                            </Link>
                            <span className="text-muted-foreground text-xs">{organizationShortName}</span>
                        </div>
                    </SidebarFooter>
                </Sidebar>
            </div>
        </Sidebar>
    );
}

export default AdministratorSidebar;
