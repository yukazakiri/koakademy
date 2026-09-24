"use client";

import {
    IconBooks,
    IconBriefcase,
    IconCash,
    IconDashboard,
    IconHelp,
    IconSchool,
    IconServer,
    IconTools,
    IconUser,
} from "@tabler/icons-react";
import { SearchX } from "lucide-react";
import { motion } from "framer-motion";
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
function useOrganizedRoutes(userRole: string, userPermissions: string[] = [], moduleRoutes: ModuleAdminRoute[] = [], libraryEnabled = false) {
    return React.useMemo(() => {
        const normalizedRole = normalizeRole(userRole);
        const allowedRoutes = getRoutesForRoleWithModules(normalizedRole, userPermissions, moduleRoutes).filter(
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
    }, [libraryEnabled, moduleRoutes, userPermissions, userRole]);
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
            <span className="rounded-xs bg-primary/20 text-primary font-medium px-0.5">{match}</span>
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
                    className="tabular-nums font-mono text-[10px] h-4.5 min-w-4.5 px-1.5 font-medium bg-muted/80 text-muted-foreground"
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
                    <Badge
                        variant="primary-light"
                        size="xs"
                        radius="default"
                        className="font-semibold text-[10px] tracking-wide"
                    >
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
                <span className="ml-auto inline-flex items-center gap-1.5 shrink-0">
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
                <SidebarHeader className="gap-2.5 border-b border-sidebar-border/60 p-3">
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
                                className="text-xs text-muted-foreground hover:text-foreground font-medium transition-colors"
                            >
                                Clear
                            </button>
                        )}
                    </div>

                    {/* Section Selector Pills */}
                    <div className="-mx-1 flex gap-1.5 overflow-x-auto px-1 pb-1 scrollbar-none">
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
                                            const isActive = isRouteActive(currentUrl, route.link);
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
                                                                <span className="size-4 shrink-0 text-muted-foreground [&_svg]:size-4">
                                                                    {route.icon}
                                                                </span>
                                                            )}
                                                            <div className="flex flex-col min-w-0 flex-1">
                                                                <span className="truncate text-xs font-medium">
                                                                    <Highlighted text={route.title} query={searchQuery} />
                                                                </span>
                                                                <div className="flex items-center gap-1.5 mt-0.5">
                                                                    <Badge
                                                                        variant="outline"
                                                                        size="xs"
                                                                        radius="default"
                                                                        className="text-[9px] h-3.5 px-1 py-0 font-normal border-sidebar-border/80 text-muted-foreground"
                                                                    >
                                                                        {route.sectionLabel}
                                                                    </Badge>
                                                                    {route.isSub && route.parentTitle && (
                                                                        <span className="text-[10px] text-muted-foreground/70 truncate">
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
                                                <div className="flex size-10 items-center justify-center rounded-full bg-muted/60 mb-2">
                                                    <SearchX className="size-5 text-muted-foreground/70" />
                                                </div>
                                                <p className="text-xs font-medium text-foreground">No matches found</p>
                                                <p className="text-[11px] text-muted-foreground mt-0.5">
                                                    No navigation items matching &ldquo;{searchQuery}&rdquo;
                                                </p>
                                                <button
                                                    type="button"
                                                    onClick={() => setSearchQuery("")}
                                                    className="mt-3 text-xs text-primary font-medium hover:underline"
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
                                            const isActive = isParentRouteActive(currentUrl, route.link, route.subs);
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
                                                                <span className="size-4 shrink-0 text-muted-foreground [&_svg]:size-4">
                                                                    {route.icon}
                                                                </span>
                                                            )}
                                                            <span className="truncate flex-1">{route.title}</span>
                                                            {badgeContent}
                                                        </AdminLink>
                                                    </SidebarMenuButton>
                                                    <SidebarMenuSub className="relative ml-4 pl-2 border-l border-sidebar-border/60 space-y-0.5 my-1">
                                                        {route.subs?.map((sub, idx) => {
                                                            const isSubActive = isRouteActive(currentUrl, sub.link, sub.link === route.link);
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

                                        const isActive = isRouteActive(currentUrl, route.link);
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
                                                            <span className="size-4 shrink-0 text-muted-foreground [&_svg]:size-4">
                                                                {route.icon}
                                                            </span>
                                                        )}
                                                        <span className="truncate flex-1">{route.title}</span>
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

                <SidebarFooter className="border-t border-sidebar-border/60 p-3">
                    <div className="flex items-center justify-between w-full">
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
                                className="gap-1.5 font-mono text-[10px] font-medium border-sidebar-border/70 bg-sidebar/50 text-muted-foreground hover:text-foreground"
                            >
                                <span className="relative flex size-1.5">
                                    <span className="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-400 opacity-75" />
                                    <span className="relative inline-flex size-1.5 rounded-full bg-emerald-500" />
                                </span>
                                v{version}
                            </Badge>
                        </AdminLink>
                        <span className="text-[11px] font-medium text-muted-foreground/70 truncate max-w-[130px]">
                            {organizationShortName}
                        </span>
                    </div>
                </SidebarFooter>
            </Sidebar>
        );
    }

    // Desktop Dual-Sidebar layout
    return (
        <Sidebar
            collapsible="icon"
            className="overflow-hidden border-r border-sidebar-border/60"
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
                    className="bg-sidebar border-r border-sidebar-border/60 flex flex-col justify-between"
                    style={
                        {
                            "--sidebar-width": ADMIN_SIDEBAR_ICON_WIDTH,
                        } as React.CSSProperties
                    }
                >
                    <SidebarHeader className="p-2 flex items-center justify-center">
                        <SidebarMenu>
                            <SidebarMenuItem>
                                <SidebarMenuButton
                                    size="lg"
                                    asChild
                                    className="h-9 w-9 p-0 flex items-center justify-center rounded-xl"
                                    tooltip={{
                                        children: `${appName} • Dashboard`,
                                        hidden: false,
                                    }}
                                >
                                    <AdminLink href="/administrators/dashboard" prefetch cacheFor="30s">
                                        <motion.div
                                            whileHover={{ scale: 1.05 }}
                                            whileTap={{ scale: 0.95 }}
                                            className="bg-sidebar-primary/10 text-sidebar-primary border border-sidebar-border/60 flex aspect-square size-8 items-center justify-center overflow-hidden rounded-lg shadow-2xs transition-colors hover:bg-sidebar-primary/20"
                                        >
                                            <img
                                                src={branding.logo}
                                                alt={`${organizationShortName} Logo`}
                                                className="size-5 object-contain"
                                            />
                                        </motion.div>
                                    </AdminLink>
                                </SidebarMenuButton>
                            </SidebarMenuItem>
                        </SidebarMenu>
                    </SidebarHeader>

                    <SidebarContent className="flex-1 px-1 py-1">
                        <SidebarGroup className="p-0">
                            <SidebarGroupContent>
                                <SidebarMenu className="gap-1 items-center">
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
                                            <SidebarMenuItem key={section.id} className="w-full flex justify-center">
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
                                                        "relative h-9 w-9 p-0 flex items-center justify-center rounded-lg transition-all duration-150",
                                                        isActive
                                                            ? "bg-sidebar-accent text-sidebar-primary font-medium shadow-2xs"
                                                            : "text-sidebar-foreground/70 hover:text-sidebar-foreground hover:bg-sidebar-accent/50",
                                                    )}
                                                >
                                                    {isActive && (
                                                        <motion.span
                                                            layoutId="rail-active-indicator"
                                                            className="absolute left-0 top-1.5 bottom-1.5 w-1 rounded-r-full bg-primary"
                                                            transition={{ type: "spring", stiffness: 450, damping: 35 }}
                                                        />
                                                    )}
                                                    <Icon
                                                        className={cn(
                                                            "size-4 shrink-0 transition-transform",
                                                            isActive && "scale-105",
                                                        )}
                                                    />
                                                    <span className="sr-only">{getSectionTitle(section.id)}</span>
                                                    {badgeCount > 0 && (
                                                        <Badge
                                                            variant="destructive"
                                                            size="xs"
                                                            radius="full"
                                                            className="absolute -top-1 -right-1 font-bold h-4 min-w-4 px-1 text-[9px] shadow-xs"
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

                    <SidebarFooter className="p-2 gap-2 flex flex-col items-center border-t border-sidebar-border/40 [&_[data-sidebar=menu-button]_.grid]:hidden [&_[data-sidebar=menu-button]_.ml-auto]:hidden">
                        {/* Spectrum UI Notification Bell with Popover Trigger */}
                        <NotificationsPopover
                            baseUrl="/administrators/notifications"
                            inboxUrl="/administrators/notifications/inbox"
                            renderTrigger={(unreadCount) => (
                                <NotificationBell
                                    count={unreadCount}
                                    size="sm"
                                    className="h-8 w-8 rounded-lg"
                                />
                            )}
                        />
                        <NavUser user={navUserData} />
                    </SidebarFooter>
                </Sidebar>

                {/* Second Sidebar - Section Content Pane */}
                <Sidebar
                    collapsible="none"
                    className="flex-1 bg-sidebar/50"
                    style={
                        {
                            "--sidebar-width": ADMIN_SIDEBAR_CONTENT_WIDTH,
                        } as React.CSSProperties
                    }
                >
                    <SidebarHeader className="gap-2.5 border-b border-sidebar-border/60 p-3">
                        <SchoolSwitcher />

                        {/* Section Title Banner with ReUI Badge */}
                        <div className="flex w-full items-center justify-between">
                            {searchQuery.trim() ? (
                                <div className="flex items-center justify-between w-full">
                                    <div className="flex items-center gap-1.5 min-w-0">
                                        <span className="text-xs font-semibold text-foreground truncate">
                                            Search
                                        </span>
                                        <Badge variant="primary-light" size="xs" radius="full">
                                            {filteredRoutes?.length ?? 0}
                                        </Badge>
                                    </div>
                                    <button
                                        type="button"
                                        onClick={() => setSearchQuery("")}
                                        className="text-[11px] text-muted-foreground hover:text-foreground transition-colors font-medium"
                                    >
                                        Clear
                                    </button>
                                </div>
                            ) : (
                                <div className="flex items-center justify-between w-full">
                                    <div className="flex items-center gap-2 min-w-0">
                                        {CurrentSectionIcon && (
                                            <span className="flex size-6 shrink-0 items-center justify-center rounded-md bg-primary/10 text-primary">
                                                <CurrentSectionIcon className="size-3.5" />
                                            </span>
                                        )}
                                        <span className="text-sm font-semibold tracking-tight text-foreground truncate">
                                            {getSectionTitle(displayedSection)}
                                        </span>
                                    </div>
                                    <Badge
                                        variant="secondary"
                                        size="xs"
                                        radius="full"
                                        className="font-mono text-[10px]"
                                    >
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
                                                const isActive = isRouteActive(currentUrl, route.link);
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
                                                                    <span className="size-4 shrink-0 text-muted-foreground group-hover/search-item:text-foreground transition-colors [&_svg]:size-4">
                                                                        {route.icon}
                                                                    </span>
                                                                )}
                                                                <div className="flex flex-col min-w-0 flex-1">
                                                                    <span className="truncate text-xs font-medium">
                                                                        <Highlighted text={route.title} query={searchQuery} />
                                                                    </span>
                                                                    <div className="flex items-center gap-1.5 mt-0.5">
                                                                        <Badge
                                                                            variant="outline"
                                                                            size="xs"
                                                                            radius="default"
                                                                            className="text-[9px] h-3.5 px-1 py-0 font-normal border-sidebar-border/80 text-muted-foreground"
                                                                        >
                                                                            {route.sectionLabel}
                                                                        </Badge>
                                                                        {route.isSub && route.parentTitle && (
                                                                            <span className="text-[10px] text-muted-foreground/70 truncate">
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
                                                    <div className="flex size-10 items-center justify-center rounded-full bg-muted/60 mb-2">
                                                        <SearchX className="size-5 text-muted-foreground/70" />
                                                    </div>
                                                    <p className="text-xs font-medium text-foreground">No matches found</p>
                                                    <p className="text-[11px] text-muted-foreground mt-0.5">
                                                        No routes matching &ldquo;{searchQuery}&rdquo;
                                                    </p>
                                                    <button
                                                        type="button"
                                                        onClick={() => setSearchQuery("")}
                                                        className="mt-3 text-xs text-primary font-medium hover:underline"
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
                                                const isActive = isParentRouteActive(currentUrl, route.link, route.subs);
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
                                                                    <span className="size-4 shrink-0 text-muted-foreground group-hover/item:text-foreground transition-colors [&_svg]:size-4">
                                                                        {route.icon}
                                                                    </span>
                                                                )}
                                                                <span className="truncate flex-1 text-xs">{route.title}</span>
                                                                {badgeContent}
                                                            </AdminLink>
                                                        </SidebarMenuButton>
                                                        <SidebarMenuSub className="relative ml-4 pl-2 border-l border-sidebar-border/60 space-y-0.5 my-0.5">
                                                            {route.subs?.map((sub, idx) => {
                                                                const isSubActive = isRouteActive(currentUrl, sub.link, sub.link === route.link);
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
                                                                                    <span className="absolute left-[-9px] top-1/2 -translate-y-1/2 size-1.5 rounded-full bg-primary" />
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

                                            const isActive = isRouteActive(currentUrl, route.link);
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
                                                                <span className="size-4 shrink-0 text-muted-foreground group-hover/item:text-foreground transition-colors [&_svg]:size-4">
                                                                    {route.icon}
                                                                </span>
                                                            )}
                                                            <span className="truncate flex-1 text-xs">{route.title}</span>
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

                    <SidebarFooter className="border-t border-sidebar-border/60 p-3">
                        <div className="flex items-center justify-between w-full">
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
                                    className="gap-1.5 font-mono text-[10px] font-medium border-sidebar-border/70 bg-sidebar/50 text-muted-foreground hover:text-foreground"
                                >
                                    <span className="relative flex size-1.5">
                                        <span className="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-400 opacity-75" />
                                        <span className="relative inline-flex size-1.5 rounded-full bg-emerald-500" />
                                    </span>
                                    v{version}
                                </Badge>
                            </AdminLink>
                            <span className="text-[11px] font-medium text-muted-foreground/70 truncate max-w-[120px]">
                                {organizationShortName}
                            </span>
                        </div>
                    </SidebarFooter>
                </Sidebar>
            </div>
        </Sidebar>
    );
}

export default AdministratorSidebar;
