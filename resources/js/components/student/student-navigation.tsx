import type { NavItem, NavSubItem } from "@/components/nav-main";
import { Badge } from "@/components/ui/badge";
import {
    IconCalendar,
    IconDashboard,
    IconReceipt,
    IconSchool,
    IconSpeakerphone,
    IconUsers,
} from "@tabler/icons-react";

export interface StudentPortalClass {
    id: number;
    subject_code: string;
    subject_title: string;
    section: string;
    classification: string;
    students_count: number;
    accent_color: string;
}

export function getStudentPortalNavigation(
    enabledRoutes: Record<string, boolean>,
    studentClasses: StudentPortalClass[] = [],
): NavItem[] {
    const isEnabled = (routeId: string): boolean => {
        return enabledRoutes[routeId] === true;
    };

    const myClassesItems: NavSubItem[] =
        studentClasses.length > 0
            ? studentClasses.map((cls) => ({
                  title: `${cls.subject_code} • ${cls.section}`,
                  url: `/student/classes/${cls.id}`,
                  description: cls.subject_title,
                  badge:
                      cls.students_count > 0 ? (
                          <Badge
                              variant="secondary"
                              className="flex h-5 min-w-5 items-center justify-center gap-0.5 rounded-full px-1.5 text-[10px] font-medium tabular-nums"
                          >
                              <IconUsers className="size-3" />
                              {cls.students_count}
                          </Badge>
                      ) : undefined,
                  accentColor: cls.accent_color,
              }))
            : [
                  {
                      title: "View All Classes",
                      url: "/student/classes",
                  },
              ];

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
            url: "/student/classes",
            badge:
                studentClasses.length > 0 ? (
                    <Badge
                        variant="outline"
                        className="border-primary/30 bg-primary/10 text-primary ml-1 flex h-5 min-w-5 items-center justify-center rounded-full px-1.5 text-[10px] font-medium tabular-nums"
                    >
                        {studentClasses.length}
                    </Badge>
                ) : undefined,
            items: myClassesItems,
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
