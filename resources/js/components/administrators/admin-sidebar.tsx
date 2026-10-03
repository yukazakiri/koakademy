"use client";

import {
    IconBooks,
    IconBriefcase,
    IconCash,
    IconDashboard,
    IconHelp,
    IconReportAnalytics,
    IconSchool,
    IconServer,
    IconTools,
    IconUser,
} from "@tabler/icons-react";
import { motion } from "framer-motion";
import { SearchX } from "lucide-react";
import * as React from "react";

import { NavUser } from "@/components/nav-user";
import { Badge } from "@/components/reui/badge";
import { SchoolSwitcher } from "@/components/school-switcher";
import { NotificationsPopover } from "@/components/sidebar-03/nav-notifications";
import { BeamSearch, KbdKey, NotificationBell } from "@/components/spectrumui";
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarGroup,
    SidebarGroupContent,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    SidebarMenuSub,
    SidebarMenuSubButton,
    SidebarMenuSubItem,
    useSidebar,
} from "@/components/ui/sidebar";
import {
    getRoutesForRoleWithModules,
    getSectionTitle,
    isRouteActive,
    ROUTE_SECTIONS,
    type AdminRoute,
    type ModuleAdminRoute,
    type RouteSection,
} from "@/config/admin-routes";
import { useIsMobile } from "@/hooks/use-mobile";
import { AdminLink } from "@/lib/admin-navigation";
import { resolveBranding, type Branding } from "@/lib/branding";
import { cn } from "@/lib/utils";
import type { User } from "@/types/user";
import { USER_ROLE_LABELS, UserRole } from "@/types/user-role";
import { usePage } from "@inertiajs/react";

interface PageProps {
    auth?: {
        user?: User | null;
    };
    version?: string;
    branding?: Partial<Branding> | null;
    unresolvedHelpTicketsCount?: number;
    adminSidebarCounts?: AdminSidebarCounts | null;
    moduleAdminRoutes?: ModuleAdminRoute[];
    /** Role-scoped dashboard desks, already filtered by DashboardRegistry::navigationFor(). */
    deskRoutes?: ModuleAdminRoute[];
    featureFlags?: {
        library?: boolean;
    };
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
    desks: IconReportAnalytics,
    academic: IconSchool,
    student_services: IconUser,
    finance: IconCash,
    hr: IconBriefcase,
    system: IconServer,
    library: IconBooks,
    inventory: IconTools,
    support: IconHelp,
};

const ADMIN_SIDEBAR_ICON_WIDTH = "3.25rem";
const ADMIN_SIDEBAR_WIDTH = "16.5rem";
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
function useOrganizedRoutes(
    userRole: string,
    userPermissions: string[] = [],
    moduleRoutes: ModuleAdminRoute[] = [],
    deskRoutes: ModuleAdminRoute[] = [],
    libraryEnabled = false,
) {
    return React.useMemo(() => {
        const normalizedRole = normalizeRole(userRole);
        const allowedRoutes = getRoutesForRoleWithModules(normalizedRole, userPermissions, moduleRoutes, deskRoutes).filter(
            (route) => route.id !== "admin-digital-library" || libraryEnabled,
        );

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

        // Flatten all routes for search (include sub-routes as searchable items)
        const allSearchableRoutes: SearchableRoute[] = [];
        sectionsWithRoutes.forEach((section) => {
            const routes = groupedRoutes.get(section.id) || [];
            routes.forEach((route) => {
                allSearchableRoutes.push({
                    ...route,
                    sectionId: section.id,
                    sectionLabel: getSectionTitle(section.id),
                });
                // Add sub-routes as searchable items
                if (route.subs) {
                    route.subs.forEach((sub) => {
                        allSearchableRoutes.push({
                            ...sub,
                            id: `${route.id}-${sub.link}`,
                            sectionId: section.id,
                            sectionLabel: getSectionTitle(section.id),
                            isSub: true,
                            parentTitle: route.title,
                        } as SearchableRoute);
                    });
                }
            });
        });

        return { groupedRoutes, sectionsWithRoutes, allSearchableRoutes, normalizedRole };
    }, [deskRoutes, libraryEnabled, moduleRoutes, userPermissions, userRole]);
}

function isParentRouteActive(currentUrl: string, routeLink: string, subs?: { link: string; exact?: boolean }[], exact = false): boolean {
    if (exact) {
        return isRouteActive(currentUrl, routeLink, true);
    }
    if (isRouteActive(currentUrl, routeLink, true)) {
        return true;
    }
    if (subs) {
        for (const sub of subs) {
            if (isRouteActive(currentUrl, sub.link, sub.exact)) {
                return false;
            }
        }
    }
    return isRouteActive(currentUrl, routeLink, false);
}

function getActiveSectionFromUrl(
    currentUrl: string,
    groupedRoutes: Map<RouteSection, AdminRoute[]>,
    sectionsWithRoutes: { id: RouteSection }[],
): RouteSection {
    // 1. Exact match first: prefer a section containing a route or subroute matching exactly
    for (const section of sectionsWithRoutes) {
        const routes = groupedRoutes.get(section.id) || [];
        for (const route of routes) {
            if (isRouteActive(currentUrl, route.link, true)) {
                return section.id;
            }
            if (route.subs) {
                for (const sub of route.subs) {
                    if (isRouteActive(currentUrl, sub.link, true)) {
                        return section.id;
                    }
                }
            }
        }
    }

    // 2. Prefix match: pick section with longest matching route link (skipping exact-only routes)
    let bestSection: RouteSection | null = null;
    let longestMatchLen = -1;

    for (const section of sectionsWithRoutes) {
        const routes = groupedRoutes.get(section.id) || [];
        for (const route of routes) {
            if (!route.exact && isRouteActive(currentUrl, route.link, false) && route.link.length > longestMatchLen) {
                longestMatchLen = route.link.length;
                bestSection = section.id;
            }
            if (route.subs) {
                for (const sub of route.subs) {
                    if (!sub.exact && isRouteActive(currentUrl, sub.link, false) && sub.link.length > longestMatchLen) {
                        longestMatchLen = sub.link.length;
                        bestSection = section.id;
                    }
                }
            }
        }
    }

    if (bestSection) {
        return bestSection;
    }

    // Default to first section if no match found
    return sectionsWithRoutes.length > 0 ? sectionsWithRoutes[0].id : "core";
}

/**
 * Search text matching highlight component (Spectrum UI style)
 */
function Highlighted({ text, query }: { text: string; query: string }) {
    if (!query.trim()) return <>{text}</>;
    const needle = query.trim().toLowerCase();
    const index = text.toLowerCase().indexOf(needle);
    if (index === -1) return <>{text}</>;
    const before = text.slice(0, index);
    const match = text.slice(index, index + needle.length);
    const after = text.slice(index + needle.length);
    return (
        <>
            {before}
            <span className="bg-primary/20 text-primary rounded-xs px-0.5 font-medium">{match}</span>
            {after}
        </>
    );
}

export function AdministratorSidebar({ user }: { user: User }) {
    const isMobile = useIsMobile();
    const { props, url: currentUrl } = usePage<PageProps>();
    const { setOpen } = useSidebar();
    const searchInputRef = React.useRef<HTMLInputElement>(null);
    const mobileSearchInputRef = React.useRef<HTMLInputElement>(null);

    const version = props.version || "1.0.0";
    const unresolvedHelpTicketsCount = props.unresolvedHelpTicketsCount || 0;
    const branding = resolveBranding(props.branding);
    const adminSidebarCounts = props.adminSidebarCounts ?? null;
    const moduleAdminRoutes = props.moduleAdminRoutes ?? [];
    const deskRoutes = props.deskRoutes ?? [];
    const libraryEnabled = props.featureFlags?.library === true;
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
        deskRoutes,
        libraryEnabled,
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

    // Global keyboard shortcut to focus search input (⌘K or Ctrl+K)
    React.useEffect(() => {
        const handleKeyDown = (e: KeyboardEvent) => {
            if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === "k") {
                e.preventDefault();
                if (isMobile) {
                    mobileSearchInputRef.current?.focus();
                } else {
                    searchInputRef.current?.focus();
                }
            }
        };
        window.addEventListener("keydown", handleKeyDown);
        return () => window.removeEventListener("keydown", handleKeyDown);
    }, [isMobile]);

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
    const CurrentSectionIcon = SECTION_ICONS[displayedSection];

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

    // ReUI Badge for route entity counts
    const renderCountBadge = React.useCallback(
        (count?: number) => {
            if (count === null || count === undefined) {
                return null;
            }

            return (
                <Badge
                    variant="secondary"
                    size="xs"
                    radius="full"
                    className="bg-muted/80 text-muted-foreground h-4.5 min-w-4.5 px-1.5 font-mono text-[10px] font-medium tabular-nums"
                >
                    {numberFormatter.format(count)}
                </Badge>
            );
        },
        [numberFormatter],
    );

    // ReUI Badge for route badges ("NEW", "BETA", custom badges)
    const renderRouteBadge = React.useCallback(
        (routeId: string, badge?: AdminRoute["badge"]) => {
            const countBadge = renderCountBadge(routeCountMap[routeId]);
            const routeBadge = badge ? (
                typeof badge === "string" ? (
                    <Badge variant="primary-light" size="xs" radius="default" className="text-[10px] font-semibold tracking-wide">
                        {badge}
                    </Badge>
                ) : (
                    badge
                )
            ) : null;

            if (!countBadge && !routeBadge) {
                return null;
            }

            return (
                <span className="ml-auto inline-flex shrink-0 items-center gap-1.5">
                    {countBadge}
                    {routeBadge}
                </span>
            );
        },
        [renderCountBadge, routeCountMap],
    );

    // Mobile layout
    if (isMobile) {
        return (
            <Sidebar collapsible="offcanvas" className="overflow-hidden">
                <SidebarHeader className="border-sidebar-border/60 gap-2.5 border-b p-3">
                    <SchoolSwitcher />

                    <div className="flex items-center justify-between">
                        <div className="text-foreground text-sm font-semibold tracking-tight">
                            {searchQuery.trim() ? (
                                <span className="inline-flex items-center gap-1.5">
                                    <span>Search</span>
                                    <Badge variant="primary-light" size="xs" radius="full">
                                        {filteredRoutes?.length ?? 0}
                                    </Badge>
                                </span>
                            ) : (
                                <span className="inline-flex items-center gap-1.5">
                                    <span>{getSectionTitle(displayedSection)}</span>
                                    <Badge variant="secondary" size="xs" radius="full" className="font-mono">
                                        {activeRoutes.length}
                                    </Badge>
                                </span>
                            )}
                        </div>
                        {searchQuery.trim() && (
                            <button
                                type="button"
                                onClick={() => setSearchQuery("")}
                                className="text-muted-foreground hover:text-foreground text-xs font-medium transition-colors"
                            >
                                Clear
                            </button>
                        )}
                    </div>

                    {/* Section Selector Pills */}
                    <div className="-mx-1 flex scrollbar-none gap-1.5 overflow-x-auto px-1 pb-1">
                        {sectionsWithRoutes.map((section) => {
                            const isActive = displayedSection === section.id;
                            const Icon = SECTION_ICONS[section.id];
                            return (
                                <button
                                    key={section.id}
                                    type="button"
                                    onClick={() => setUserSelectedSection(section.id)}
                                    className={cn(
                                        "inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-medium whitespace-nowrap transition-all",
                                        isActive
                                            ? "bg-primary text-primary-foreground shadow-xs"
                                            : "bg-muted/70 text-muted-foreground hover:bg-accent hover:text-foreground",
                                    )}
                                >
                                    <Icon className="size-3.5" />
                                    <span>{getSectionTitle(section.id)}</span>
                                </button>
                            );
                        })}
                    </div>

                    {/* Spectrum UI BeamSearch for Mobile */}
                    <BeamSearch
                        ref={mobileSearchInputRef}
                        placeholder="Search navigation..."
                        value={searchQuery}
                        onChange={setSearchQuery}
                        onClear={() => setSearchQuery("")}
                    />
                </SidebarHeader>

                <SidebarContent>
                    <SidebarGroup className="px-1 py-2">
                        <SidebarGroupContent>
                            <SidebarMenu>
                                {searchQuery.trim() ? (
                                    filteredRoutes && filteredRoutes.length > 0 ? (
                                        filteredRoutes.map((route) => {
                                            const isActive = isRouteActive(currentUrl, route.link, route.exact);
                                            const badgeContent = renderRouteBadge(route.id, route.badge);
                                            return (
                                                <SidebarMenuItem key={route.id}>
                                                    <SidebarMenuButton
                                                        asChild
                                                        isActive={isActive}
                                                        className={cn(
                                                            "rounded-lg px-2.5 py-2 transition-all",
                                                            isActive && "bg-sidebar-accent text-sidebar-accent-foreground font-medium",
                                                        )}
                                                    >
                                                        <AdminLink href={route.link} prefetch cacheFor="30s">
                                                            {route.icon && (
                                                                <span className="text-muted-foreground size-4 shrink-0 [&_svg]:size-4">
                                                                    {route.icon}
                                                                </span>
                                                            )}
                                                            <div className="flex min-w-0 flex-1 flex-col">
                                                                <span className="truncate text-xs font-medium">
                                                                    <Highlighted text={route.title} query={searchQuery} />
                                                                </span>
                                                                <div className="mt-0.5 flex items-center gap-1.5">
                                                                    <Badge
                                                                        variant="outline"
                                                                        size="xs"
                                                                        radius="default"
                                                                        className="border-sidebar-border/80 text-muted-foreground h-3.5 px-1 py-0 text-[9px] font-normal"
                                                                    >
                                                                        {route.sectionLabel}
                                                                    </Badge>
                                                                    {route.isSub && route.parentTitle && (
                                                                        <span className="text-muted-foreground/70 truncate text-[10px]">
                                                                            via {route.parentTitle}
                                                                        </span>
                                                                    )}
                                                                </div>
                                                            </div>
                                                            {badgeContent}
                                                        </AdminLink>
                                                    </SidebarMenuButton>
                                                </SidebarMenuItem>
                                            );
                                        })
                                    ) : (
                                        <SidebarMenuItem>
                                            <div className="flex flex-col items-center justify-center p-6 text-center">
                                                <div className="bg-muted/60 mb-2 flex size-10 items-center justify-center rounded-full">
                                                    <SearchX className="text-muted-foreground/70 size-5" />
                                                </div>
                                                <p className="text-foreground text-xs font-medium">No matches found</p>
                                                <p className="text-muted-foreground mt-0.5 text-[11px]">
                                                    No navigation items matching &ldquo;{searchQuery}&rdquo;
                                                </p>
                                                <button
                                                    type="button"
                                                    onClick={() => setSearchQuery("")}
                                                    className="text-primary mt-3 text-xs font-medium hover:underline"
                                                >
                                                    Clear search
                                                </button>
                                            </div>
                                        </SidebarMenuItem>
                                    )
                                ) : (
                                    activeRoutes.map((route) => {
                                        const hasSubs = route.subs && route.subs.length > 0;

                                        if (hasSubs) {
                                            const isActive = isParentRouteActive(currentUrl, route.link, route.subs, route.exact);
                                            const badgeContent = renderRouteBadge(route.id, route.badge);

                                            return (
                                                <SidebarMenuItem key={route.id}>
                                                    <SidebarMenuButton
                                                        asChild
                                                        isActive={isActive}
                                                        className={cn(
                                                            "rounded-lg px-2.5 py-2 transition-all",
                                                            isActive && "bg-sidebar-accent text-sidebar-accent-foreground font-medium",
                                                        )}
                                                    >
                                                        <AdminLink href={route.link} prefetch cacheFor="30s">
                                                            {route.icon && (
                                                                <span className="text-muted-foreground size-4 shrink-0 [&_svg]:size-4">
                                                                    {route.icon}
                                                                </span>
                                                            )}
                                                            <span className="flex-1 truncate">{route.title}</span>
                                                            {badgeContent}
                                                        </AdminLink>
                                                    </SidebarMenuButton>
                                                    <SidebarMenuSub className="border-sidebar-border/60 relative my-1 ml-4 space-y-0.5 border-l pl-2">
                                                        {route.subs?.map((sub, idx) => {
                                                            const isSubActive = isRouteActive(
                                                                currentUrl,
                                                                sub.link,
                                                                sub.exact ?? sub.link === route.link,
                                                            );
                                                            return (
                                                                <SidebarMenuSubItem key={idx}>
                                                                    <SidebarMenuSubButton
                                                                        asChild
                                                                        isActive={isSubActive}
                                                                        className={cn(
                                                                            "rounded-md text-xs transition-colors",
                                                                            isSubActive
                                                                                ? "bg-sidebar-accent text-sidebar-primary font-medium"
                                                                                : "text-sidebar-foreground/75 hover:text-sidebar-foreground",
                                                                        )}
                                                                    >
                                                                        <AdminLink href={sub.link} prefetch cacheFor="30s">
                                                                            {sub.icon && (
                                                                                <span className="size-3.5 shrink-0 [&_svg]:size-3.5">{sub.icon}</span>
                                                                            )}
                                                                            <span className="truncate">{sub.title}</span>
                                                                        </AdminLink>
                                                                    </SidebarMenuSubButton>
                                                                </SidebarMenuSubItem>
                                                            );
                                                        })}
                                                    </SidebarMenuSub>
                                                </SidebarMenuItem>
                                            );
                                        }

                                        const isActive = isRouteActive(currentUrl, route.link, route.exact);
                                        const badgeContent = renderRouteBadge(route.id, route.badge);

                                        return (
                                            <SidebarMenuItem key={route.id}>
                                                <SidebarMenuButton
                                                    asChild
                                                    isActive={isActive}
                                                    className={cn(
                                                        "rounded-lg px-2.5 py-2 transition-all",
                                                        isActive && "bg-sidebar-accent text-sidebar-accent-foreground font-medium",
                                                    )}
                                                >
                                                    <AdminLink href={route.link} prefetch cacheFor="30s">
                                                        {route.icon && (
                                                            <span className="text-muted-foreground size-4 shrink-0 [&_svg]:size-4">{route.icon}</span>
                                                        )}
                                                        <span className="flex-1 truncate">{route.title}</span>
                                                        {badgeContent}
                                                    </AdminLink>
                                                </SidebarMenuButton>
                                            </SidebarMenuItem>
                                        );
                                    })
                                )}
                            </SidebarMenu>
                        </SidebarGroupContent>
                    </SidebarGroup>
                </SidebarContent>

                <SidebarFooter className="border-sidebar-border/60 border-t p-3">
                    <div className="flex w-full items-center justify-between">
                        <AdminLink href="/changelog" prefetch cacheFor="30s" className="inline-flex items-center transition-opacity hover:opacity-85">
                            <Badge
                                variant="outline"
                                size="sm"
                                radius="full"
                                className="border-sidebar-border/70 bg-sidebar/50 text-muted-foreground hover:text-foreground gap-1.5 font-mono text-[10px] font-medium"
                            >
                                <span className="relative flex size-1.5">
                                    <span className="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-400 opacity-75" />
                                    <span className="relative inline-flex size-1.5 rounded-full bg-emerald-500" />
                                </span>
                                v{version}
                            </Badge>
                        </AdminLink>
                        <span className="text-muted-foreground/70 max-w-[130px] truncate text-[11px] font-medium">{organizationShortName}</span>
                    </div>
                </SidebarFooter>
            </Sidebar>
        );
    }

    // Desktop Dual-Sidebar layout
    return (
        <Sidebar
            collapsible="icon"
            className="border-sidebar-border/60 overflow-hidden border-r"
            style={
                {
                    "--sidebar-width": ADMIN_SIDEBAR_WIDTH,
                    "--sidebar-width-icon": ADMIN_SIDEBAR_ICON_WIDTH,
                } as React.CSSProperties
            }
        >
            <div className="flex h-full w-full flex-row">
                {/* First Sidebar - Icon Rail Navigation */}
                <Sidebar
                    collapsible="none"
                    className="bg-sidebar border-sidebar-border/60 flex flex-col justify-between border-r"
                    style={
                        {
                            "--sidebar-width": ADMIN_SIDEBAR_ICON_WIDTH,
                        } as React.CSSProperties
                    }
                >
                    <SidebarHeader className="flex items-center justify-center p-2">
                        <SidebarMenu>
                            <SidebarMenuItem>
                                <SidebarMenuButton
                                    size="lg"
                                    asChild
                                    className="flex h-9 w-9 items-center justify-center rounded-xl p-0"
                                    tooltip={{
                                        children: `${appName} • Dashboard`,
                                        hidden: false,
                                    }}
                                >
                                    <AdminLink href="/administrators/dashboard" prefetch cacheFor="30s">
                                        <motion.div
                                            whileHover={{ scale: 1.05 }}
                                            whileTap={{ scale: 0.95 }}
                                            className="bg-sidebar-primary/10 text-sidebar-primary border-sidebar-border/60 hover:bg-sidebar-primary/20 flex aspect-square size-8 items-center justify-center overflow-hidden rounded-lg border shadow-2xs transition-colors"
                                        >
                                            <img src={branding.logo} alt={`${organizationShortName} Logo`} className="size-5 object-contain" />
                                        </motion.div>
                                    </AdminLink>
                                </SidebarMenuButton>
                            </SidebarMenuItem>
                        </SidebarMenu>
                    </SidebarHeader>

                    <SidebarContent className="flex-1 px-1 py-1">
                        <SidebarGroup className="p-0">
                            <SidebarGroupContent>
                                <SidebarMenu className="items-center gap-1">
                                    {sectionsWithRoutes.map((section) => {
                                        const Icon = SECTION_ICONS[section.id];
                                        const isActive = displayedSection === section.id;
                                        const sectionRoutesCount = (groupedRoutes.get(section.id) || []).length;

                                        // Badge count for support or other sections
                                        let badgeCount = 0;
                                        if (section.id === "support" && unresolvedHelpTicketsCount > 0) {
                                            badgeCount = unresolvedHelpTicketsCount;
                                        }

                                        return (
                                            <SidebarMenuItem key={section.id} className="flex w-full justify-center">
                                                <SidebarMenuButton
                                                    tooltip={{
                                                        children: `${getSectionTitle(section.id)} (${sectionRoutesCount} modules)`,
                                                        hidden: false,
                                                    }}
                                                    onClick={() => {
                                                        setUserSelectedSection(section.id);
                                                        setOpen(true);
                                                    }}
                                                    isActive={isActive}
                                                    className={cn(
                                                        "relative flex h-9 w-9 items-center justify-center rounded-lg p-0 transition-all duration-150",
                                                        isActive
                                                            ? "bg-sidebar-accent text-sidebar-primary font-medium shadow-2xs"
                                                            : "text-sidebar-foreground/70 hover:text-sidebar-foreground hover:bg-sidebar-accent/50",
                                                    )}
                                                >
                                                    {isActive && (
                                                        <motion.span
                                                            layoutId="rail-active-indicator"
                                                            className="bg-primary absolute top-1.5 bottom-1.5 left-0 w-1 rounded-r-full"
                                                            transition={{ type: "spring", stiffness: 450, damping: 35 }}
                                                        />
                                                    )}
                                                    <Icon className={cn("size-4 shrink-0 transition-transform", isActive && "scale-105")} />
                                                    <span className="sr-only">{getSectionTitle(section.id)}</span>
                                                    {badgeCount > 0 && (
                                                        <Badge
                                                            variant="destructive"
                                                            size="xs"
                                                            radius="full"
                                                            className="absolute -top-1 -right-1 h-4 min-w-4 px-1 text-[9px] font-bold shadow-xs"
                                                        >
                                                            {badgeCount > 9 ? "9+" : badgeCount}
                                                        </Badge>
                                                    )}
                                                </SidebarMenuButton>
                                            </SidebarMenuItem>
                                        );
                                    })}
                                </SidebarMenu>
                            </SidebarGroupContent>
                        </SidebarGroup>
                    </SidebarContent>

                    <SidebarFooter className="border-sidebar-border/40 flex flex-col items-center gap-2 border-t p-2 [&_[data-sidebar=menu-button]_.grid]:hidden [&_[data-sidebar=menu-button]_.ml-auto]:hidden">
                        {/* Spectrum UI Notification Bell with Popover Trigger */}
                        <NotificationsPopover
                            baseUrl="/administrators/notifications"
                            inboxUrl="/administrators/notifications/inbox"
                            renderTrigger={(unreadCount) => <NotificationBell count={unreadCount} size="sm" className="h-8 w-8 rounded-lg" />}
                        />
                        <NavUser user={navUserData} />
                    </SidebarFooter>
                </Sidebar>

                {/* Second Sidebar - Section Content Pane */}
                <Sidebar
                    collapsible="none"
                    className="bg-sidebar/50 flex-1"
                    style={
                        {
                            "--sidebar-width": ADMIN_SIDEBAR_CONTENT_WIDTH,
                        } as React.CSSProperties
                    }
                >
                    <SidebarHeader className="border-sidebar-border/60 gap-2.5 border-b p-3">
                        <SchoolSwitcher />

                        {/* Section Title Banner with ReUI Badge */}
                        <div className="flex w-full items-center justify-between">
                            {searchQuery.trim() ? (
                                <div className="flex w-full items-center justify-between">
                                    <div className="flex min-w-0 items-center gap-1.5">
                                        <span className="text-foreground truncate text-xs font-semibold">Search</span>
                                        <Badge variant="primary-light" size="xs" radius="full">
                                            {filteredRoutes?.length ?? 0}
                                        </Badge>
                                    </div>
                                    <button
                                        type="button"
                                        onClick={() => setSearchQuery("")}
                                        className="text-muted-foreground hover:text-foreground text-[11px] font-medium transition-colors"
                                    >
                                        Clear
                                    </button>
                                </div>
                            ) : (
                                <div className="flex w-full items-center justify-between">
                                    <div className="flex min-w-0 items-center gap-2">
                                        {CurrentSectionIcon && (
                                            <span className="bg-primary/10 text-primary flex size-6 shrink-0 items-center justify-center rounded-md">
                                                <CurrentSectionIcon className="size-3.5" />
                                            </span>
                                        )}
                                        <span className="text-foreground truncate text-sm font-semibold tracking-tight">
                                            {getSectionTitle(displayedSection)}
                                        </span>
                                    </div>
                                    <Badge variant="secondary" size="xs" radius="full" className="font-mono text-[10px]">
                                        {activeRoutes.length}
                                    </Badge>
                                </div>
                            )}
                        </div>

                        {/* Spectrum UI BeamSearch input with KbdKey shortcut hint */}
                        <BeamSearch
                            ref={searchInputRef}
                            placeholder="Search navigation..."
                            value={searchQuery}
                            onChange={setSearchQuery}
                            onClear={() => setSearchQuery("")}
                            trailing={<KbdKey size="xs">⌘K</KbdKey>}
                        />
                    </SidebarHeader>

                    <SidebarContent className="px-1 py-1">
                        <SidebarGroup className="p-0">
                            <SidebarGroupContent>
                                <SidebarMenu className="gap-0.5">
                                    {searchQuery.trim() ? (
                                        // Search results list
                                        filteredRoutes && filteredRoutes.length > 0 ? (
                                            filteredRoutes.map((route) => {
                                                const isActive = isRouteActive(currentUrl, route.link, route.exact);
                                                const badgeContent = renderRouteBadge(route.id, route.badge);
                                                return (
                                                    <SidebarMenuItem key={route.id}>
                                                        <SidebarMenuButton
                                                            asChild
                                                            isActive={isActive}
                                                            className={cn(
                                                                "group/search-item rounded-lg px-2 py-1.5 transition-all",
                                                                isActive && "bg-sidebar-accent text-sidebar-accent-foreground font-medium",
                                                            )}
                                                        >
                                                            <AdminLink href={route.link} prefetch cacheFor="30s">
                                                                {route.icon && (
                                                                    <span className="text-muted-foreground group-hover/search-item:text-foreground size-4 shrink-0 transition-colors [&_svg]:size-4">
                                                                        {route.icon}
                                                                    </span>
                                                                )}
                                                                <div className="flex min-w-0 flex-1 flex-col">
                                                                    <span className="truncate text-xs font-medium">
                                                                        <Highlighted text={route.title} query={searchQuery} />
                                                                    </span>
                                                                    <div className="mt-0.5 flex items-center gap-1.5">
                                                                        <Badge
                                                                            variant="outline"
                                                                            size="xs"
                                                                            radius="default"
                                                                            className="border-sidebar-border/80 text-muted-foreground h-3.5 px-1 py-0 text-[9px] font-normal"
                                                                        >
                                                                            {route.sectionLabel}
                                                                        </Badge>
                                                                        {route.isSub && route.parentTitle && (
                                                                            <span className="text-muted-foreground/70 truncate text-[10px]">
                                                                                via {route.parentTitle}
                                                                            </span>
                                                                        )}
                                                                    </div>
                                                                </div>
                                                                {badgeContent}
                                                            </AdminLink>
                                                        </SidebarMenuButton>
                                                    </SidebarMenuItem>
                                                );
                                            })
                                        ) : (
                                            <SidebarMenuItem>
                                                <div className="flex flex-col items-center justify-center p-6 text-center">
                                                    <div className="bg-muted/60 mb-2 flex size-10 items-center justify-center rounded-full">
                                                        <SearchX className="text-muted-foreground/70 size-5" />
                                                    </div>
                                                    <p className="text-foreground text-xs font-medium">No matches found</p>
                                                    <p className="text-muted-foreground mt-0.5 text-[11px]">
                                                        No routes matching &ldquo;{searchQuery}&rdquo;
                                                    </p>
                                                    <button
                                                        type="button"
                                                        onClick={() => setSearchQuery("")}
                                                        className="text-primary mt-3 text-xs font-medium hover:underline"
                                                    >
                                                        Clear search filter
                                                    </button>
                                                </div>
                                            </SidebarMenuItem>
                                        )
                                    ) : (
                                        // Standard section routes
                                        activeRoutes.map((route) => {
                                            const hasSubs = route.subs && route.subs.length > 0;

                                            if (hasSubs) {
                                                const isActive = isParentRouteActive(currentUrl, route.link, route.subs, route.exact);
                                                const badgeContent = renderRouteBadge(route.id, route.badge);

                                                return (
                                                    <SidebarMenuItem key={route.id}>
                                                        <SidebarMenuButton
                                                            asChild
                                                            isActive={isActive}
                                                            className={cn(
                                                                "group/item rounded-lg px-2.5 py-1.5 transition-all",
                                                                isActive && "bg-sidebar-accent text-sidebar-accent-foreground font-medium",
                                                            )}
                                                        >
                                                            <AdminLink href={route.link} prefetch cacheFor="30s">
                                                                {route.icon && (
                                                                    <span className="text-muted-foreground group-hover/item:text-foreground size-4 shrink-0 transition-colors [&_svg]:size-4">
                                                                        {route.icon}
                                                                    </span>
                                                                )}
                                                                <span className="flex-1 truncate text-xs">{route.title}</span>
                                                                {badgeContent}
                                                            </AdminLink>
                                                        </SidebarMenuButton>
                                                        <SidebarMenuSub className="border-sidebar-border/60 relative my-0.5 ml-4 space-y-0.5 border-l pl-2">
                                                            {route.subs?.map((sub, idx) => {
                                                                const isSubActive = isRouteActive(
                                                                    currentUrl,
                                                                    sub.link,
                                                                    sub.exact ?? sub.link === route.link,
                                                                );
                                                                return (
                                                                    <SidebarMenuSubItem key={idx}>
                                                                        <SidebarMenuSubButton
                                                                            asChild
                                                                            isActive={isSubActive}
                                                                            className={cn(
                                                                                "relative rounded-md text-xs transition-colors",
                                                                                isSubActive
                                                                                    ? "bg-sidebar-accent text-sidebar-primary font-medium"
                                                                                    : "text-sidebar-foreground/75 hover:text-sidebar-foreground hover:bg-sidebar-accent/50",
                                                                            )}
                                                                        >
                                                                            <AdminLink href={sub.link} prefetch cacheFor="30s">
                                                                                {isSubActive && (
                                                                                    <span className="bg-primary absolute top-1/2 left-[-9px] size-1.5 -translate-y-1/2 rounded-full" />
                                                                                )}
                                                                                {sub.icon && (
                                                                                    <span className="size-3.5 shrink-0 [&_svg]:size-3.5">
                                                                                        {sub.icon}
                                                                                    </span>
                                                                                )}
                                                                                <span className="truncate">{sub.title}</span>
                                                                            </AdminLink>
                                                                        </SidebarMenuSubButton>
                                                                    </SidebarMenuSubItem>
                                                                );
                                                            })}
                                                        </SidebarMenuSub>
                                                    </SidebarMenuItem>
                                                );
                                            }

                                            const isActive = isRouteActive(currentUrl, route.link, route.exact);
                                            const badgeContent = renderRouteBadge(route.id, route.badge);

                                            return (
                                                <SidebarMenuItem key={route.id}>
                                                    <SidebarMenuButton
                                                        asChild
                                                        isActive={isActive}
                                                        className={cn(
                                                            "group/item rounded-lg px-2.5 py-1.5 transition-all",
                                                            isActive && "bg-sidebar-accent text-sidebar-accent-foreground font-medium",
                                                        )}
                                                    >
                                                        <AdminLink href={route.link} prefetch cacheFor="30s">
                                                            {route.icon && (
                                                                <span className="text-muted-foreground group-hover/item:text-foreground size-4 shrink-0 transition-colors [&_svg]:size-4">
                                                                    {route.icon}
                                                                </span>
                                                            )}
                                                            <span className="flex-1 truncate text-xs">{route.title}</span>
                                                            {badgeContent}
                                                        </AdminLink>
                                                    </SidebarMenuButton>
                                                </SidebarMenuItem>
                                            );
                                        })
                                    )}
                                </SidebarMenu>
                            </SidebarGroupContent>
                        </SidebarGroup>
                    </SidebarContent>

                    <SidebarFooter className="border-sidebar-border/60 border-t p-3">
                        <div className="flex w-full items-center justify-between">
                            <AdminLink
                                href="/changelog"
                                prefetch
                                cacheFor="30s"
                                className="inline-flex items-center transition-opacity hover:opacity-85"
                            >
                                <Badge
                                    variant="outline"
                                    size="sm"
                                    radius="full"
                                    className="border-sidebar-border/70 bg-sidebar/50 text-muted-foreground hover:text-foreground gap-1.5 font-mono text-[10px] font-medium"
                                >
                                    <span className="relative flex size-1.5">
                                        <span className="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-400 opacity-75" />
                                        <span className="relative inline-flex size-1.5 rounded-full bg-emerald-500" />
                                    </span>
                                    v{version}
                                </Badge>
                            </AdminLink>
                            <span className="text-muted-foreground/70 max-w-[120px] truncate text-[11px] font-medium">{organizationShortName}</span>
                        </div>
                    </SidebarFooter>
                </Sidebar>
            </div>
        </Sidebar>
    );
}

export default AdministratorSidebar;
