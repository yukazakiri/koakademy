"use client";

import { Logo } from "@/components/sidebar-03/logo";
import DashboardNavigation from "@/components/sidebar-03/nav-main";
import { NotificationsPopover } from "@/components/sidebar-03/nav-notifications";
import { TeamSwitcher } from "@/components/sidebar-03/team-switcher";
import { Sidebar, SidebarContent, SidebarFooter, SidebarHeader, useSidebar } from "@/components/ui/sidebar";
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from "@/components/ui/tooltip";
import { cn } from "@/lib/utils";
import { User } from "@/types/user";
import { Link, usePage } from "@inertiajs/react";
import {
    IconBook,
    IconBriefcase,
    IconCalendar,
    IconChartBar,
    IconChecklist,
    IconDashboard,
    IconFileDescription,
    IconHelp,
    IconReceipt,
    IconSchool,
    IconSettings,
    IconSparkles,
    IconSpeakerphone,
    IconUserCheck,
} from "@tabler/icons-react";
import { useMemo } from "react";
import type { Route } from "./nav-main";

interface PageProps {
    version?: string;
    featureFlags?: {
        experimentalKeys?: string[];
        enabledRoutes?: Record<string, boolean>;
    };
    branding?: {
        appName: string;
        appShortName: string;
        organizationShortName: string;
        logo?: string | null;
    };
    [key: string]: unknown;
}

/**
 * Hook to get feature-flag-aware routes
 * Routes can be enabled/disabled based on Pennant feature flags
 */
function useFeatureFlagRoutes(isStudent: boolean): Route[] {
    const { props } = usePage<PageProps>();
    const enabledRoutes = props.featureFlags?.enabledRoutes || {};

    return useMemo(() => {
        if (isStudent) {
            return getStudentRoutes(enabledRoutes);
        }
        return getFacultyRoutes(enabledRoutes);
    }, [isStudent, enabledRoutes]);
}

/**
 * Get student routes with feature flags applied
 */
function getStudentRoutes(enabledRoutes: Record<string, boolean>): Route[] {
    return [
        {
            id: "dashboard",
            title: "Dashboard",
            icon: <IconDashboard className="size-4" />,
            link: "/student/dashboard",
        },
        {
            id: "classes",
            title: "My Academics",
            icon: <IconSchool className="size-4" />,
            link: "/student/classes",
            // Could be feature flagged if needed
            // disabled: !enabledRoutes['classes'],
            // disabledTooltip: "Coming soon",
        },
        {
            id: "tuition",
            title: "Tuition & Fees",
            icon: <IconReceipt className="size-4" />,
            link: "/student/tuition",
        },
        {
            id: "schedule",
            title: "Class Schedule",
            icon: <IconCalendar className="size-4" />,
            link: "/student/schedule",
        },
        {
            id: "announcements",
            title: "Announcements",
            icon: <IconSpeakerphone className="size-4" />,
            link: "/student/announcements",
        },
        {
            id: "docs",
            title: "Help & Docs",
            icon: <IconBook className="size-4" />,
            link: "/docs/v1",
        },
        {
            id: "help",
            title: "Help & Support",
            icon: <IconHelp className="size-4" />,
            link: "/help",
        },
    ];
}

/**
 * Get faculty routes with feature flags applied
 * Feature flags control which routes are enabled vs disabled (coming soon)
 */
function getFacultyRoutes(enabledRoutes: Record<string, boolean>): Route[] {
    // Helper to check if a route is enabled via feature flag
    const isEnabled = (routeId: string): boolean => {
        return enabledRoutes[routeId] === true;
    };

    return [
        // Main Navigation - Always enabled
        {
            id: "dashboard",
            title: "Dashboard",
            icon: <IconDashboard className="size-4" />,
            link: "/faculty/dashboard",
        },
        {
            id: "action-center",
            title: "Action Center",
            icon: <IconChecklist className="size-4" />,
            link: isEnabled("action-center") ? "/faculty/action-center" : "#",
            disabled: !isEnabled("action-center"),
            disabledTooltip: "Action Center coming soon",
        },
        {
            id: "classes",
            title: "My Classes",
            icon: <IconSchool className="size-4" />,
            link: "/faculty/classes",
        },
        {
            id: "schedule",
            title: "My Schedule",
            icon: <IconCalendar className="size-4" />,
            link: "/faculty/schedule",
        },

        // Faculty Toolkit - Feature flagged sub-items
        {
            id: "faculty-toolkit",
            title: "Faculty Toolkit",
            icon: <IconBriefcase className="size-4" />,
            link: "#",
            separator: true,
            // Show sparkle badge if any toolkit feature is enabled
            badge: isEnabled("faculty-toolkit") ? <IconSparkles className="size-3 text-amber-500" /> : undefined,
            subs: [
                {
                    title: "At-Risk Alerts",
                    link: isEnabled("faculty-toolkit-at-risk") ? "/faculty/at-risk-alerts" : "#",
                    disabled: !isEnabled("faculty-toolkit-at-risk"),
                    disabledTooltip: "Early warning alerts coming soon",
                },
                {
                    title: "Assessments",
                    link: isEnabled("faculty-toolkit-assessments") ? "/faculty/assessments" : "#",
                    disabled: !isEnabled("faculty-toolkit-assessments"),
                    disabledTooltip: "Quizzes, rubrics & grading queue coming soon",
                },
                {
                    title: "Inbox",
                    link: isEnabled("faculty-toolkit-inbox") ? "/faculty/inbox" : "#",
                    disabled: !isEnabled("faculty-toolkit-inbox"),
                    disabledTooltip: "Messaging and templates coming soon",
                },
                {
                    title: "Office Hours",
                    link: isEnabled("faculty-toolkit-office-hours") ? "/faculty/office-hours" : "#",
                    disabled: !isEnabled("faculty-toolkit-office-hours"),
                    disabledTooltip: "Student appointment booking coming soon",
                },
                {
                    title: "Requests & Approvals",
                    link: isEnabled("faculty-toolkit-requests") ? "/faculty/requests" : "#",
                    disabled: !isEnabled("faculty-toolkit-requests"),
                    disabledTooltip: "Excusals, make-up exams & approvals coming soon",
                },
                {
                    title: "Insights",
                    link: isEnabled("faculty-toolkit-insights") ? "/faculty/insights" : "#",
                    disabled: !isEnabled("faculty-toolkit-insights"),
                    disabledTooltip: "Class analytics and trends coming soon",
                },
            ],
        },

        // Academic Tools - Feature flagged
        {
            id: "grades",
            title: "Grades & Reports",
            icon: <IconChartBar className="size-4" />,
            link: isEnabled("grades") ? "/faculty/grades" : "#",
            separator: true,
            disabled: !isEnabled("grades"),
            disabledTooltip: "Grade management coming soon",
        },
        {
            id: "attendance",
            title: "Attendance",
            icon: <IconUserCheck className="size-4" />,
            link: isEnabled("attendance") ? "/faculty/attendance" : "#",
            disabled: !isEnabled("attendance"),
            disabledTooltip: "Attendance tracking coming soon",
        },
        {
            id: "announcements",
            title: "Announcements",
            icon: <IconSpeakerphone className="size-4" />,
            link: "/faculty/announcements",
        },

        // Resources & Admin - Feature flagged
        {
            id: "resources",
            title: "Resources",
            icon: <IconBook className="size-4" />,
            link: isEnabled("resources") ? "/faculty/resources" : "#",
            separator: true,
            disabled: !isEnabled("resources"),
            disabledTooltip: "Library & teaching resources coming soon",
        },
        {
            id: "forms",
            title: "Faculty Forms",
            icon: <IconFileDescription className="size-4" />,
            link: isEnabled("forms") ? "/faculty/forms" : "#",
            disabled: !isEnabled("forms"),
            disabledTooltip: "Leave requests, requisitions & forms coming soon",
        },
        {
            id: "settings",
            title: "Settings",
            icon: <IconSettings className="size-4" />,
            link: "/faculty/profile",
        },
        {
            id: "docs",
            title: "Help & Docs",
            icon: <IconBook className="size-4" />,
            link: "/docs/v1",
        },
        {
            id: "help",
            title: "Help & Support",
            icon: <IconHelp className="size-4" />,
            link: "/help",
        },
    ];
}

export function DashboardSidebar({ user }: { user: User }) {
    const { state } = useSidebar();
    const isCollapsed = state === "collapsed";
    const { props } = usePage<PageProps>();
    const version = props.version || "1.23.0";
    const branding = props.branding;
    const appName = branding?.appName || "School Portal";
    const organizationShortName = branding?.organizationShortName || "UNI";

    const isStudent = ["student", "shs_student", "Student"].includes(user.role);
    const routes = useFeatureFlagRoutes(isStudent);

    const teams = [
        {
            id: "1",
            name: user.name || "User",
            logo: Logo,
            plan: user.role || "Portal",
        },
    ];

    return (
        <Sidebar variant="floating" collapsible="icon">
            <SidebarHeader className={cn("flex items-center justify-between gap-2 px-3 py-2 md:pt-3", isCollapsed ? "flex-col" : "flex-row")}>
                <Link href={isStudent ? "/student/dashboard" : "/dashboard"} className={cn("flex items-center gap-2", isCollapsed && "flex-col")}>
                    <div className="flex aspect-square size-7 items-center justify-center overflow-hidden rounded bg-white">
                        <img src={branding?.logo || "/web-app-manifest-192x192.png"} alt={`${organizationShortName} Logo`} className="size-5 object-contain" />
                    </div>
                    {!isCollapsed && (
                        <div className="flex flex-col">
                            <span className="text-foreground text-sm font-semibold">{appName}</span>
                        </div>
                    )}
                </Link>

                <div className={cn("flex items-center gap-1", isCollapsed && "mt-1 flex-col")}>
                    {!isCollapsed && (
                        <TooltipProvider>
                            <Tooltip>
                                <TooltipTrigger asChild>
                                    <Link
                                        href="/changelog"
                                        className="text-muted-foreground hover:text-foreground inline-flex items-center gap-1 rounded px-1.5 py-0.5 text-xs font-medium transition-colors"
                                    >
                                        <span className="inline-flex items-center gap-1">
                                            <span className="h-1.5 w-1.5 rounded-full bg-green-500"></span>v{version}
                                        </span>
                                    </Link>
                                </TooltipTrigger>
                                <TooltipContent side="right">
                                    <p>View changelog</p>
                                </TooltipContent>
                            </Tooltip>
                        </TooltipProvider>
                    )}
                    <NotificationsPopover />
                </div>

                {isCollapsed && (
                    <TooltipProvider>
                        <Tooltip>
                            <TooltipTrigger asChild>
                                <Link
                                    href="/changelog"
                                    className="text-muted-foreground hover:text-foreground inline-flex items-center gap-1 rounded px-1.5 py-0.5 text-xs font-medium transition-colors"
                                >
                                    <span className="inline-flex items-center gap-1">
                                        <span className="h-1.5 w-1.5 rounded-full bg-green-500"></span>v{version}
                                    </span>
                                </Link>
                            </TooltipTrigger>
                            <TooltipContent side="right">
                                <p>View changelog</p>
                            </TooltipContent>
                        </Tooltip>
                    </TooltipProvider>
                )}
            </SidebarHeader>
            <SidebarContent className="gap-4 px-2 py-4">
                <DashboardNavigation routes={routes} />
            </SidebarContent>
            <SidebarFooter className="px-2">
                <TeamSwitcher teams={teams} user={user} />
            </SidebarFooter>
        </Sidebar>
    );
}
