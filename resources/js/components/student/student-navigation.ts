import type { NavItem } from "@/components/nav-main";
import { IconCalendar, IconDashboard, IconReceipt, IconSchool, IconSpeakerphone } from "@tabler/icons-react";

export function getStudentPortalNavigation(enabledRoutes: Record<string, boolean>): NavItem[] {
    const isEnabled = (routeId: string): boolean => {
        return enabledRoutes[routeId] === true;
    };

    return [
        {
            id: "dashboard",
            title: "Dashboard",
            icon: IconDashboard,
            url: "/student/dashboard",
        },
        {
            id: "classes",
            title: "My Academics",
            icon: IconSchool,
            url: isEnabled("classes") ? "/student/classes" : "#",
            disabled: !isEnabled("classes"),
            disabledTooltip: "My Academics is not enabled for this account",
        },
        {
            id: "tuition",
            title: "Tuition & Fees",
            icon: IconReceipt,
            url: isEnabled("tuition") ? "/student/tuition" : "#",
            disabled: !isEnabled("tuition"),
            disabledTooltip: "Tuition & Fees is not enabled for this account",
        },
        {
            id: "schedule",
            title: "Class Schedule",
            icon: IconCalendar,
            url: isEnabled("schedule") ? "/student/schedule" : "#",
            disabled: !isEnabled("schedule"),
            disabledTooltip: "Class Schedule is not enabled for this account",
        },
        {
            id: "announcements",
            title: "Announcements",
            icon: IconSpeakerphone,
            url: "/student/announcements",
        },
    ];
}
