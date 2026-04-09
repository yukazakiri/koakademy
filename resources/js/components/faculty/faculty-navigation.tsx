import type { NavItem, NavSubItem } from "@/components/nav-main";
import { Badge } from "@/components/ui/badge";
import {
    IconBook,
    IconBriefcase,
    IconCalendar,
    IconChartBar,
    IconChecklist,
    IconDashboard,
    IconFileDescription,
    IconSchool,
    IconSparkles,
    IconSpeakerphone,
    IconUserCheck,
    IconUsers,
} from "@tabler/icons-react";

export interface FacultyPortalClass {
    id: number;
    subject_code: string;
    subject_title: string;
    section: string;
    classification: string;
    students_count: number;
    accent_color: string;
}

export function getFacultyPortalNavigation(enabledRoutes: Record<string, boolean>, facultyClasses: FacultyPortalClass[] = []): NavItem[] {
    const isEnabled = (routeId: string): boolean => {
        return enabledRoutes[routeId] === true;
    };

    const myClassesItems: NavSubItem[] =
        facultyClasses.length > 0
            ? facultyClasses.map((cls) => ({
                  title: `${cls.subject_code} • ${cls.section}`,
                  url: `/faculty/classes/${cls.id}`,
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
                      url: "/faculty/classes",
                  },
              ];

    return [
        {
            id: "dashboard",
            title: "Dashboard",
            icon: IconDashboard,
            url: "/faculty/dashboard",
        },
        {
            id: "action-center",
            title: "Action Center",
            icon: IconChecklist,
            url: isEnabled("action-center") ? "/faculty/action-center" : "#",
            disabled: !isEnabled("action-center"),
            disabledTooltip: "Action Center coming soon",
        },
        {
            id: "classes",
            title: "My Classes",
            icon: IconSchool,
            url: "/faculty/classes",
            badge:
                facultyClasses.length > 0 ? (
                    <Badge
                        variant="outline"
                        className="border-primary/30 bg-primary/10 text-primary ml-1 flex h-5 min-w-5 items-center justify-center rounded-full px-1.5 text-[10px] font-medium tabular-nums"
                    >
                        {facultyClasses.length}
                    </Badge>
                ) : undefined,
            items: myClassesItems,
        },
        {
            id: "schedule",
            title: "My Schedule",
            icon: IconCalendar,
            url: "/faculty/schedule",
        },
        {
            id: "faculty-toolkit",
            title: "Faculty Toolkit",
            icon: IconBriefcase,
            url: "#",
            separator: true,
            badge: isEnabled("faculty-toolkit") ? <IconSparkles className="size-3 text-amber-500" /> : undefined,
            items: [
                {
                    title: "At-Risk Alerts",
                    url: isEnabled("faculty-toolkit-at-risk") ? "/faculty/at-risk-alerts" : "#",
                    disabled: !isEnabled("faculty-toolkit-at-risk"),
                    disabledTooltip: "Early warning alerts coming soon",
                },
                {
                    title: "Assessments",
                    url: isEnabled("faculty-toolkit-assessments") ? "/faculty/assessments" : "#",
                    disabled: !isEnabled("faculty-toolkit-assessments"),
                    disabledTooltip: "Quizzes, rubrics & grading queue coming soon",
                },
                {
                    title: "Inbox",
                    url: isEnabled("faculty-toolkit-inbox") ? "/faculty/inbox" : "#",
                    disabled: !isEnabled("faculty-toolkit-inbox"),
                    disabledTooltip: "Messaging and templates coming soon",
                },
                {
                    title: "Office Hours",
                    url: isEnabled("faculty-toolkit-office-hours") ? "/faculty/office-hours" : "#",
                    disabled: !isEnabled("faculty-toolkit-office-hours"),
                    disabledTooltip: "Student appointment booking coming soon",
                },
                {
                    title: "Requests & Approvals",
                    url: isEnabled("faculty-toolkit-requests") ? "/faculty/requests" : "#",
                    disabled: !isEnabled("faculty-toolkit-requests"),
                    disabledTooltip: "Excusals, make-up exams & approvals coming soon",
                },
                {
                    title: "Insights",
                    url: isEnabled("faculty-toolkit-insights") ? "/faculty/insights" : "#",
                    disabled: !isEnabled("faculty-toolkit-insights"),
                    disabledTooltip: "Class analytics and trends coming soon",
                },
            ],
        },
        {
            id: "grades",
            title: "Grades & Reports",
            icon: IconChartBar,
            url: isEnabled("grades") ? "/faculty/grades" : "#",
            separator: true,
            disabled: !isEnabled("grades"),
            disabledTooltip: "Grade management coming soon",
        },
        {
            id: "attendance",
            title: "Attendance",
            icon: IconUserCheck,
            url: isEnabled("attendance") ? "/faculty/attendance" : "#",
            disabled: !isEnabled("attendance"),
            disabledTooltip: "Attendance tracking coming soon",
        },
        {
            id: "announcements",
            title: "Announcements",
            icon: IconSpeakerphone,
            url: "/faculty/announcements",
        },
        {
            id: "resources",
            title: "Resources",
            icon: IconBook,
            url: isEnabled("resources") ? "/faculty/resources" : "#",
            separator: true,
            disabled: !isEnabled("resources"),
            disabledTooltip: "Library & teaching resources coming soon",
        },
        {
            id: "forms",
            title: "Faculty Forms",
            icon: IconFileDescription,
            url: isEnabled("forms") ? "/faculty/forms" : "#",
            disabled: !isEnabled("forms"),
            disabledTooltip: "Leave requests, requisitions & forms coming soon",
        },
    ];
}
