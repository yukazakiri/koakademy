import type { NavItem, NavSubItem } from "@/components/nav-main";
import { Badge } from "@/components/ui/badge";
import { index as libraryIndex } from "@/routes/library";
import { IconBooks, IconCalendar, IconDashboard, IconReceipt, IconSchool, IconSpeakerphone, IconUsers } from "@tabler/icons-react";

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
                              variant="outline"
                              className="border-sidebar-border/80 bg-background/85 text-sidebar-foreground/80 dark:border-sidebar-border/70 dark:bg-sidebar-accent/70 dark:text-sidebar-foreground/85 flex h-[18px] min-w-[22px] items-center justify-center gap-1 rounded-md px-1.5 font-mono text-[10px] leading-none font-semibold tracking-tight tabular-nums shadow-2xs"
                          >
                              <IconUsers className="size-2.5 opacity-75" />
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
                        className="border-primary/30 bg-primary/15 text-primary ring-primary/10 dark:border-primary/40 dark:bg-primary/25 dark:text-primary ml-1 flex h-[18px] min-w-[22px] items-center justify-center rounded-md px-1.5 font-mono text-[10px] leading-none font-semibold tracking-tight tabular-nums shadow-2xs ring-1 ring-inset"
                    >
                        {studentClasses.length}
                    </Badge>
                ) : undefined,
            items: myClassesItems,
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
