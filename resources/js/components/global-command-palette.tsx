import {
    CommandDialog,
    CommandEmpty,
    CommandGroup,
    CommandInput,
    CommandItem,
    CommandList,
    CommandSeparator,
    CommandShortcut,
} from "@/components/ui/command";
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from "@/components/ui/dialog";
import { getRoutesForRoleWithModules, type ModuleAdminRoute } from "@/config/admin-routes";
import type { User } from "@/types/user";
import { router, usePage } from "@inertiajs/react";
import {
    IconBell,
    IconCalendarEvent,
    IconCalendarStats,
    IconChecklist,
    IconDownload,
    IconGridDots,
    IconHelp,
    IconHome,
    IconNews,
    IconSchool,
    IconSettings,
    IconShieldLock,
    IconUser,
    IconUsers,
} from "@tabler/icons-react";
import { useEffect, useMemo, useRef, useState, type ReactNode } from "react";
import { toast } from "sonner";

const OPEN_EVENT_NAME = "dccp:commandPalette:open";

type ActionItem = {
    id: string;
    label: string;
    keywords?: string;
    shortcut?: string;
    icon: ReactNode;
    closeOnSelect?: boolean;
    onSelect: () => void;
};

type ClassSearchResult = {
    id: number;
    record_title: string;
    subject_code: string;
    subject_title: string;
    section: string;
    school_year: string;
    semester: string;
    classification: string;
    faculty?: string;
    students_count?: number;
    maximum_slots?: number;
};

type UserSearchResult = {
    id: number;
    name: string;
    email: string;
    role: string;
    avatar: string | null;
};

type FacultySearchResult = {
    id: string;
    name: string;
    email: string;
    department: string;
    avatar: string | null;
};

type EnrollmentSearchResult = {
    id: number;
    student_name: string;
    course_code: string;
    year_level: string;
    status: string;
    school_year: string;
    semester: string;
};

type StudentSearchResult = {
    id: number;
    student_id: number | string;
    name: string;
    course: string | null;
    course_title: string | null;
    academic_year: string;
    type: string | null;
    status: string | null;
};

type SubjectSearchResult = {
    id: number;
    code: string;
    title: string;
    units: number;
};

type CourseSearchResult = {
    id: number;
    code: string;
    title: string;
    department: string;
};

interface PageProps {
    auth?: {
        user?: User | null;
    };
    moduleAdminRoutes?: ModuleAdminRoute[];
}

interface GlobalCommandPaletteProps {
    user: User;
    onSelect?: () => void;
}

type StudentPublicInfo = {
    id: number;
    student_id: number | string;
    lrn: string | null;
    name: string;
    email: string | null;
    phone: string | null;
    course: { name: string; code: string };
    academic_year: string;
    picture: string | null;
    age: number | null;
    birth_date: string | null;
};

function isMacPlatform(): boolean {
    return navigator.userAgent.toLowerCase().includes("mac");
}

function openCommandPalette(): void {
    if (typeof window === "undefined") {
        return;
    }

    window.dispatchEvent(new CustomEvent(OPEN_EVENT_NAME));
}

function setLocalStorageSafe(key: string, value: string): void {
    try {
        window.localStorage.setItem(key, value);
    } catch {
        // ignore
    }
}

async function downloadSchedulePdf(type: "timetable" | "matrix"): Promise<void> {
    const toastId = toast.loading("Queueing PDF export…", {
        description: `Preparing your schedule export (${type}).`,
    });

    try {
        const response = await fetch(`/download/schedule?type=${type}`, {
            method: "GET",
            headers: {
                Accept: "application/json",
            },
        });

        const payload = (await response.json().catch(() => ({}))) as { message?: string; error?: string };

        if (!response.ok) {
            const message = payload.error || payload.message || "Failed to queue PDF export";

            toast.error("Export Failed", {
                id: toastId,
                description: message,
            });

            return;
        }

        toast.success("PDF export queued", {
            id: toastId,
            description: payload.message || "You will be notified once your PDF is ready.",
        });
    } catch (error: unknown) {
        const message = error instanceof Error ? error.message : "An unexpected error occurred.";
        toast.error("Export Failed", {
            id: toastId,
            description: message,
        });
    }
}

function filterActions(actions: ActionItem[], query: string): ActionItem[] {
    if (!query) return actions;
    const lowerQuery = query.toLowerCase();
    return actions.filter((action) => {
        return action.label.toLowerCase().includes(lowerQuery) || action.keywords?.toLowerCase().includes(lowerQuery);
    });
}

export function GlobalCommandContent({
    user,
    isOpen,
    onSelect,
    searchPlaceholder = "Search commands, students, classes, or users...",
    listClassName,
}: {
    user: User;
    isOpen: boolean;
    onSelect?: () => void;
    searchPlaceholder?: string;
    listClassName?: string;
}) {
    const { props } = usePage<PageProps>();
    const [searchText, setSearchText] = useState("");
    const [isSearching, setIsSearching] = useState(false);
    const [classResults, setClassResults] = useState<ClassSearchResult[]>([]);
    const [studentResults, setStudentResults] = useState<StudentSearchResult[]>([]);
    const [userResults, setUserResults] = useState<UserSearchResult[]>([]);
    const [facultyResults, setFacultyResults] = useState<FacultySearchResult[]>([]);
    const [enrollmentResults, setEnrollmentResults] = useState<EnrollmentSearchResult[]>([]);
    const [subjectResults, setSubjectResults] = useState<SubjectSearchResult[]>([]);
    const [courseResults, setCourseResults] = useState<CourseSearchResult[]>([]);
    const [studentDialogOpen, setStudentDialogOpen] = useState(false);
    const [studentDialogLoading, setStudentDialogLoading] = useState(false);
    const [studentDialogStudent, setStudentDialogStudent] = useState<StudentPublicInfo | null>(null);
    const studentDialogAbortController = useRef<AbortController | null>(null);

    const isAdminContext = typeof window !== "undefined" && window.location.pathname.startsWith("/administrators");
    const moduleAdminRoutes = props.moduleAdminRoutes ?? [];
    const sharedAuthUser = props.auth?.user;
    const resolvedUserRole = sharedAuthUser?.role ?? user.role ?? "";
    const resolvedUserPermissions = sharedAuthUser?.permissions ?? user.permissions ?? [];
    const isInstructor = /faculty|instructor/i.test(resolvedUserRole);
    const isStudentContext = !isAdminContext && !isInstructor;
    const allowedAdminRoutes = useMemo(
        () => getRoutesForRoleWithModules(resolvedUserRole, resolvedUserPermissions, moduleAdminRoutes),
        [moduleAdminRoutes, resolvedUserPermissions, resolvedUserRole],
    );
    const allowedAdminRouteIds = useMemo(() => new Set(allowedAdminRoutes.map((route) => route.id)), [allowedAdminRoutes]);

    const actions = useMemo(() => {
        if (isAdminContext) {
            const navigationCandidates: Array<ActionItem & { routeId?: string }> = [
                {
                    id: "nav:admin-dashboard",
                    routeId: "admin-dashboard",
                    label: "Dashboard",
                    keywords: "admin home overview",
                    shortcut: "G D",
                    icon: <IconHome className="h-4 w-4" />,
                    onSelect: () => router.visit("/administrators/dashboard"),
                },
                {
                    id: "nav:admin-students",
                    routeId: "admin-students",
                    label: "Student Records",
                    keywords: "admin students records",
                    shortcut: "G S",
                    icon: <IconUser className="h-4 w-4" />,
                    onSelect: () => router.visit("/administrators/students"),
                },
                {
                    id: "nav:admin-classes",
                    routeId: "admin-classes",
                    label: "Class Sections",
                    keywords: "admin classes sections",
                    shortcut: "G C",
                    icon: <IconUsers className="h-4 w-4" />,
                    onSelect: () => router.visit("/administrators/classes"),
                },
                {
                    id: "nav:admin-faculties",
                    routeId: "admin-faculty",
                    label: "Faculty & Staff",
                    keywords: "admin faculty staff instructors",
                    shortcut: "G F",
                    icon: <IconUsers className="h-4 w-4" />,
                    onSelect: () => router.visit("/administrators/faculties"),
                },
                {
                    id: "nav:admin-enrollments",
                    routeId: "admin-enrollments",
                    label: "Enrollment Management",
                    keywords: "admin enrollments management",
                    shortcut: "G E",
                    icon: <IconChecklist className="h-4 w-4" />,
                    onSelect: () => router.visit("/administrators/enrollments"),
                },
                {
                    id: "nav:admin-scheduling",
                    routeId: "admin-scheduling-analytics",
                    label: "Scheduling & Timetable",
                    keywords: "admin scheduling timetable analytics",
                    icon: <IconCalendarStats className="h-4 w-4" />,
                    onSelect: () => router.visit("/administrators/scheduling-analytics"),
                },
                {
                    id: "nav:admin-sanity",
                    routeId: "admin-sanity-content",
                    label: "Website Content (CMS)",
                    keywords: "admin website content cms sanity",
                    icon: <IconNews className="h-4 w-4" />,
                    onSelect: () => router.visit("/administrators/sanity-content"),
                },
                {
                    id: "nav:admin-users",
                    routeId: "admin-users",
                    label: "User Management",
                    keywords: "admin users",
                    shortcut: "G U",
                    icon: <IconShieldLock className="h-4 w-4" />,
                    onSelect: () => router.visit("/administrators/users"),
                },
                {
                    id: "nav:admin-settings",
                    routeId: "admin-settings",
                    label: "My Profile",
                    keywords: "admin profile settings",
                    icon: <IconSettings className="h-4 w-4" />,
                    onSelect: () => router.visit("/administrators/settings"),
                },
                {
                    id: "nav:help",
                    label: "Help & Support",
                    keywords: "help support documentation",
                    icon: <IconHelp className="h-4 w-4" />,
                    onSelect: () => router.visit("/help"),
                },
            ];
            const moduleNavigation = allowedAdminRoutes
                .filter((route) => route.moduleSource)
                .filter((route) => !navigationCandidates.some((candidate) => candidate.routeId === route.id))
                .map((route) => ({
                    id: `nav:${route.id}`,
                    routeId: route.id,
                    label: route.title,
                    keywords: `${route.title} ${route.moduleSource ?? ""}`.trim(),
                    icon: route.icon ?? <IconGridDots className="h-4 w-4" />,
                    onSelect: () => router.visit(route.link),
                }));
            const navigation = [...navigationCandidates, ...moduleNavigation].filter(
                (item) => !item.routeId || allowedAdminRouteIds.has(item.routeId),
            );

            return { navigation, productivity: [] };
        }

        const scheduleViewKey = `dccp:scheduleView:v1:${user.email}`;

        const navigation: ActionItem[] = isInstructor
            ? [
                  {
                      id: "nav:dashboard",
                      label: "Go to Dashboard",
                      keywords: "home overview",
                      shortcut: "G D",
                      icon: <IconHome className="h-4 w-4" />,
                      onSelect: () => router.visit("/dashboard"),
                  },
                  {
                      id: "nav:classes",
                      label: "Go to Classes",
                      keywords: "teaching sections",
                      shortcut: "G C",
                      icon: <IconUsers className="h-4 w-4" />,
                      onSelect: () => router.visit("/classes"),
                  },
                  {
                      id: "nav:schedule",
                      label: "Go to Schedule",
                      keywords: "timetable agenda",
                      shortcut: "G S",
                      icon: <IconCalendarEvent className="h-4 w-4" />,
                      onSelect: () => router.visit("/schedule"),
                  },
                  {
                      id: "nav:announcements",
                      label: "Go to Announcements",
                      keywords: "news notifications",
                      shortcut: "G A",
                      icon: <IconBell className="h-4 w-4" />,
                      onSelect: () => router.visit("/announcements"),
                  },
                  {
                      id: "nav:profile",
                      label: "Go to Profile",
                      keywords: "settings account",
                      shortcut: "G P",
                      icon: <IconUser className="h-4 w-4" />,
                      onSelect: () => router.visit("/profile"),
                  },
              ]
            : [
                  {
                      id: "nav:student-dashboard",
                      label: "Go to Dashboard",
                      keywords: "student home overview",
                      shortcut: "G D",
                      icon: <IconHome className="h-4 w-4" />,
                      onSelect: () => router.visit("/student/dashboard"),
                  },
                  {
                      id: "nav:student-classes",
                      label: "Go to My Academics",
                      keywords: "subjects curriculum classes academics",
                      shortcut: "G C",
                      icon: <IconSchool className="h-4 w-4" />,
                      onSelect: () => router.visit("/student/classes"),
                  },
                  {
                      id: "nav:student-tuition",
                      label: "Go to Tuition & Fees",
                      keywords: "payments tuition fees soa enrollment billing",
                      shortcut: "G T",
                      icon: <IconChecklist className="h-4 w-4" />,
                      onSelect: () => router.visit("/student/tuition"),
                  },
                  {
                      id: "nav:student-schedule",
                      label: "Go to Class Schedule",
                      keywords: "schedule timetable agenda",
                      shortcut: "G S",
                      icon: <IconCalendarEvent className="h-4 w-4" />,
                      onSelect: () => router.visit("/student/schedule"),
                  },
                  {
                      id: "nav:student-announcements",
                      label: "Go to Announcements",
                      keywords: "news notifications updates",
                      shortcut: "G A",
                      icon: <IconBell className="h-4 w-4" />,
                      onSelect: () => router.visit("/student/announcements"),
                  },
              ];

        const productivityBase: ActionItem[] = [
            {
                id: "prod:profile-account",
                label: "Edit Profile (Account)",
                keywords: "profile account name email",
                shortcut: "P A",
                icon: <IconUser className="h-4 w-4" />,
                onSelect: () => router.visit("/profile#profile-form"),
            },
            {
                id: "prod:announcements",
                label: "Check Announcements",
                keywords: "announcements updates",
                shortcut: "A A",
                icon: <IconBell className="h-4 w-4" />,
                onSelect: () => router.visit("/announcements"),
            },
        ];

        const productivityInstructor: ActionItem[] = [
            {
                id: "prod:classes-board",
                label: "Open Classes Board",
                keywords: "classes board visualize",
                shortcut: "C B",
                icon: <IconGridDots className="h-4 w-4" />,
                onSelect: () => {
                    setLocalStorageSafe("dccp.classes.viewMode", "board");
                    router.visit("/classes");
                },
            },
            {
                id: "prod:classes-create",
                label: "Create a Class",
                keywords: "new add class",
                shortcut: "C N",
                icon: <IconUsers className="h-4 w-4" />,
                onSelect: () => router.visit("/classes?create=1"),
            },
            {
                id: "prod:classes-reset",
                label: "Reset Classes Filters",
                keywords: "clear filters",
                icon: <IconSettings className="h-4 w-4" />,
                onSelect: () => {
                    setLocalStorageSafe("dccp.classes.search", "");
                    setLocalStorageSafe("dccp.classes.filter.classification", "all");
                    setLocalStorageSafe("dccp.classes.filter.day", "all");
                    setLocalStorageSafe("dccp.classes.filter.room", "all");
                    setLocalStorageSafe("dccp.classes.filter.onlyConflicts", "false");
                    setLocalStorageSafe("dccp.classes.filter.onlyUnscheduled", "false");
                    router.visit("/classes");
                },
            },
            {
                id: "prod:schedule-agenda",
                label: "Open Schedule (Agenda)",
                keywords: "schedule today agenda",
                shortcut: "S A",
                icon: <IconCalendarEvent className="h-4 w-4" />,
                onSelect: () => {
                    setLocalStorageSafe(scheduleViewKey, "agenda");
                    router.visit("/schedule");
                },
            },
            {
                id: "prod:schedule-overview",
                label: "Open Schedule (Overview)",
                keywords: "schedule overview grid",
                shortcut: "S O",
                icon: <IconCalendarEvent className="h-4 w-4" />,
                onSelect: () => {
                    setLocalStorageSafe(scheduleViewKey, "overview");
                    router.visit("/schedule");
                },
            },
            {
                id: "prod:schedule-export-timetable",
                label: "Export Schedule PDF (Timetable)",
                keywords: "schedule export pdf download timetable",
                shortcut: "S E",
                icon: <IconDownload className="h-4 w-4" />,
                onSelect: () => downloadSchedulePdf("timetable"),
            },
            {
                id: "prod:schedule-export-matrix",
                label: "Export Schedule PDF (Matrix)",
                keywords: "schedule export pdf download matrix",
                icon: <IconDownload className="h-4 w-4" />,
                onSelect: () => downloadSchedulePdf("matrix"),
            },
            {
                id: "prod:profile-faculty",
                label: "Edit Profile (Faculty)",
                keywords: "profile faculty office hours",
                shortcut: "P F",
                icon: <IconUser className="h-4 w-4" />,
                onSelect: () => router.visit("/profile#faculty-form"),
            },
        ];

        const productivityStudent: ActionItem[] = [
            {
                id: "prod:student-academics",
                label: "Open My Academics",
                keywords: "subjects curriculum checklist",
                icon: <IconSchool className="h-4 w-4" />,
                onSelect: () => router.visit("/student/classes"),
            },
            {
                id: "prod:student-tuition",
                label: "Open Tuition & Fees",
                keywords: "soa enrollment tuition fees billing",
                icon: <IconChecklist className="h-4 w-4" />,
                onSelect: () => router.visit("/student/tuition"),
            },
            {
                id: "prod:student-announcements",
                label: "Check Announcements",
                keywords: "announcements updates",
                shortcut: "A A",
                icon: <IconBell className="h-4 w-4" />,
                onSelect: () => router.visit("/student/announcements"),
            },
        ];

        const productivity = isInstructor ? [...productivityInstructor, ...productivityBase] : productivityStudent;

        return { navigation, productivity };
    }, [allowedAdminRouteIds, allowedAdminRoutes, isAdminContext, isInstructor, user.email, user.role]);

    const filteredNavigation = useMemo(() => filterActions(actions.navigation, searchText), [actions.navigation, searchText]);
    const filteredProductivity = useMemo(() => filterActions(actions.productivity, searchText), [actions.productivity, searchText]);

    useEffect(() => {
        if (!isOpen) {
            setIsSearching(false);
            return;
        }

        const query = searchText.trim();

        if (query.length < 2) {
            setIsSearching(false);
            setClassResults([]);
            setStudentResults([]);
            setUserResults([]);
            setFacultyResults([]);
            setEnrollmentResults([]);
            setSubjectResults([]);
            setCourseResults([]);
            return;
        }

        const abortController = new AbortController();
        const handle = window.setTimeout(async () => {
            setIsSearching(true);

            try {
                if (isAdminContext) {
                    // Use fetch for JSON API search (not Inertia since this returns plain JSON)
                    const response = await fetch(`/administrators/search?q=${encodeURIComponent(query)}`, {
                        method: "GET",
                        headers: {
                            Accept: "application/json",
                            "X-Requested-With": "XMLHttpRequest",
                        },
                        signal: abortController.signal,
                    });

                    if (!response.ok) throw new Error("Search failed");

                    const data = (await response.json()) as {
                        students?: StudentSearchResult[];
                        classes?: ClassSearchResult[];
                        users?: UserSearchResult[];
                        faculty?: FacultySearchResult[];
                        enrollments?: EnrollmentSearchResult[];
                    };

                    setStudentResults(Array.isArray(data.students) ? data.students : []);
                    setClassResults(Array.isArray(data.classes) ? data.classes : []);
                    setUserResults(Array.isArray(data.users) ? data.users : []);
                    setFacultyResults(Array.isArray(data.faculty) ? data.faculty : []);
                    setEnrollmentResults(Array.isArray(data.enrollments) ? data.enrollments : []);
                } else if (isInstructor) {
                    // Concurrent search for instructors
                    const [classesRes, studentsRes] = await Promise.all([
                        fetch(`/search/classes?q=${encodeURIComponent(query)}`, {
                            signal: abortController.signal,
                        }),
                        fetch(`/search/students?q=${encodeURIComponent(query)}`, {
                            signal: abortController.signal,
                        }),
                    ]);

                    if (classesRes.ok) {
                        const data = (await classesRes.json()) as { classes?: ClassSearchResult[] };
                        setClassResults(Array.isArray(data.classes) ? data.classes : []);
                    }

                    if (studentsRes.ok) {
                        const data = (await studentsRes.json()) as { students?: StudentSearchResult[] };
                        setStudentResults(Array.isArray(data.students) ? data.students : []);
                    }

                    setUserResults([]);
                    setFacultyResults([]);
                    setEnrollmentResults([]);
                    setSubjectResults([]);
                    setCourseResults([]);
                } else {
                    // Search for students
                    const response = await fetch(`/student/search?q=${encodeURIComponent(query)}`, {
                        method: "GET",
                        headers: {
                            Accept: "application/json",
                            "X-Requested-With": "XMLHttpRequest",
                        },
                        signal: abortController.signal,
                    });

                    if (response.ok) {
                        const data = (await response.json()) as {
                            subjects?: SubjectSearchResult[];
                            classes?: ClassSearchResult[];
                            courses?: CourseSearchResult[];
                            enrollments?: EnrollmentSearchResult[];
                        };
                        setSubjectResults(Array.isArray(data.subjects) ? data.subjects : []);
                        setClassResults(Array.isArray(data.classes) ? data.classes : []);
                        setCourseResults(Array.isArray(data.courses) ? data.courses : []);
                        setEnrollmentResults(Array.isArray(data.enrollments) ? data.enrollments : []);
                    }
                    setStudentResults([]);
                    setUserResults([]);
                    setFacultyResults([]);
                }
            } catch (error: unknown) {
                if (error instanceof DOMException && error.name === "AbortError") {
                    return;
                }
                // Silent error for search
            } finally {
                setIsSearching(false);
            }
        }, 250);

        return () => {
            abortController.abort();
            window.clearTimeout(handle);
        };
    }, [isInstructor, isOpen, searchText, isAdminContext]);

    function run(action: ActionItem): void {
        if (action.closeOnSelect !== false && onSelect) {
            onSelect();
        }
        action.onSelect();
    }

    function handleStudentDialogOpenChange(nextOpen: boolean): void {
        setStudentDialogOpen(nextOpen);

        if (!nextOpen) {
            studentDialogAbortController.current?.abort();
            studentDialogAbortController.current = null;
            setStudentDialogStudent(null);
            setStudentDialogLoading(false);
        }
    }

    async function openStudentDetails(student: StudentSearchResult): Promise<void> {
        if (isAdminContext) {
            if (onSelect) onSelect();
            router.visit(`/administrators/students/${student.id}`);
            return;
        }

        // We don't close the parent if opening a student dialog in non-admin context?
        // Actually, we should probably keep the parent open or handle it.
        // If it's a dialog on top of a dialog/drawer, it works.
        // If it navigates, we should close.
        // For now, let's keep it consistent.

        setStudentDialogOpen(true);
        setStudentDialogLoading(true);
        setStudentDialogStudent(null);

        studentDialogAbortController.current?.abort();

        const abortController = new AbortController();
        studentDialogAbortController.current = abortController;

        try {
            const response = await fetch(`/students/${student.id}`, {
                method: "GET",
                signal: abortController.signal,
            });

            if (!response.ok) throw new Error("Failed to load student");

            const payload = (await response.json()) as { student?: StudentPublicInfo };

            if (payload.student) {
                setStudentDialogStudent(payload.student);
            }
        } catch (error) {
            if (error instanceof DOMException && error.name === "AbortError") return;
            toast.error("Unable to load student details");
            setStudentDialogOpen(false);
        } finally {
            setStudentDialogLoading(false);
        }
    }

    const hint = isMacPlatform() ? "⌘ K" : "Ctrl K";
    const hasSearchResults =
        studentResults.length > 0 ||
        classResults.length > 0 ||
        userResults.length > 0 ||
        facultyResults.length > 0 ||
        enrollmentResults.length > 0 ||
        subjectResults.length > 0 ||
        courseResults.length > 0;

    return (
        <>
            <CommandInput value={searchText} onValueChange={setSearchText} placeholder={searchPlaceholder} />
            <CommandList className={listClassName}>
                {!hasSearchResults && filteredNavigation.length === 0 && filteredProductivity.length === 0 && !isSearching ? (
                    <CommandEmpty>No matches found.</CommandEmpty>
                ) : null}

                {isSearching ? <div className="text-muted-foreground py-6 text-center text-sm">Searching...</div> : null}

                {/* Search Results First */}
                {enrollmentResults.length > 0 && (
                    <CommandGroup heading="Enrollments">
                        {enrollmentResults.map((result) => (
                            <CommandItem
                                key={`enrollment:${result.id}`}
                                onSelect={() => {
                                    if (onSelect) onSelect();
                                    if (isAdminContext) {
                                        router.visit(`/administrators/enrollments/${result.id}`);
                                    } else if (isStudentContext) {
                                        router.visit(`/student/tuition`);
                                    } else {
                                        router.visit("/tuition");
                                    }
                                }}
                                className="flex items-center gap-3 py-3"
                            >
                                <div className="flex size-8 shrink-0 items-center justify-center rounded-full bg-orange-100 text-orange-600 dark:bg-orange-500/20 dark:text-orange-400">
                                    <IconSchool className="size-4" />
                                </div>
                                <div className="flex flex-col gap-0.5">
                                    <div className="font-medium">{result.student_name}</div>
                                    <div className="text-muted-foreground text-xs">
                                        {result.course_code} · {result.year_level} · {result.semester}
                                    </div>
                                </div>
                                <div className="text-muted-foreground ml-auto text-xs font-medium">{result.status}</div>
                            </CommandItem>
                        ))}
                    </CommandGroup>
                )}

                {studentResults.length > 0 && (
                    <CommandGroup heading="Students">
                        {studentResults.map((result) => (
                            <CommandItem
                                key={`student:${result.id}`}
                                onSelect={() => void openStudentDetails(result)}
                                className="flex items-center gap-3 py-3"
                            >
                                <div className="flex size-8 shrink-0 items-center justify-center rounded-full bg-blue-100 text-blue-600 dark:bg-blue-500/20 dark:text-blue-400">
                                    <IconUser className="size-4" />
                                </div>
                                <div className="flex flex-col gap-0.5">
                                    <div className="font-medium">{result.name}</div>
                                    <div className="text-muted-foreground text-xs">
                                        ID: {result.student_id} {result.course ? `· ${result.course}` : ""}
                                    </div>
                                </div>
                                {result.status && <div className="text-muted-foreground ml-auto text-xs font-medium">{result.status}</div>}
                            </CommandItem>
                        ))}
                    </CommandGroup>
                )}

                {classResults.length > 0 && (
                    <CommandGroup heading="Classes">
                        {classResults.map((result) => (
                            <CommandItem
                                key={`class:${result.id}`}
                                onSelect={() => {
                                    if (onSelect) onSelect();
                                    if (isAdminContext) {
                                        router.visit(`/administrators/classes/${result.id}`);
                                    } else if (isInstructor) {
                                        router.visit(`/classes/${result.id}`);
                                    } else {
                                        router.visit(`/student/classes/${result.id}`);
                                    }
                                }}
                                className="flex items-center gap-3 py-3"
                            >
                                <div className="flex size-8 shrink-0 items-center justify-center rounded-full bg-indigo-100 text-indigo-600 dark:bg-indigo-500/20 dark:text-indigo-400">
                                    <IconUsers className="size-4" />
                                </div>
                                <div className="min-w-0 flex-1">
                                    <div className="flex items-center gap-2">
                                        <span className="truncate font-medium">{result.record_title}</span>
                                        {result.section && (
                                            <span className="inline-flex items-center rounded-full border px-1.5 py-0.5 text-[10px] font-medium opacity-80">
                                                {result.section}
                                            </span>
                                        )}
                                    </div>
                                    <div className="text-muted-foreground truncate text-xs">
                                        {result.subject_title} ({result.subject_code})
                                    </div>
                                </div>
                                {result.maximum_slots ? (
                                    <div className="text-muted-foreground text-xs">
                                        {result.students_count}/{result.maximum_slots}
                                    </div>
                                ) : null}
                            </CommandItem>
                        ))}
                    </CommandGroup>
                )}

                {facultyResults.length > 0 && (
                    <CommandGroup heading="Faculty">
                        {facultyResults.map((result) => (
                            <CommandItem
                                key={`faculty:${result.id}`}
                                onSelect={() => {
                                    if (onSelect) onSelect();
                                    router.visit(`/administrators/faculties/${result.id}`);
                                }}
                                className="flex items-center gap-3 py-3"
                            >
                                {result.avatar ? (
                                    <img src={result.avatar} alt="" className="size-8 rounded-full object-cover" />
                                ) : (
                                    <div className="flex size-8 shrink-0 items-center justify-center rounded-full bg-pink-100 text-pink-600 dark:bg-pink-500/20 dark:text-pink-400">
                                        <IconUser className="size-4" />
                                    </div>
                                )}
                                <div className="flex flex-col gap-0.5">
                                    <div className="font-medium">{result.name}</div>
                                    <div className="text-muted-foreground text-xs">
                                        {result.department} · {result.email}
                                    </div>
                                </div>
                            </CommandItem>
                        ))}
                    </CommandGroup>
                )}

                {userResults.length > 0 && (
                    <CommandGroup heading="Users">
                        {userResults.map((result) => (
                            <CommandItem
                                key={`user:${result.id}`}
                                onSelect={() => {
                                    if (onSelect) onSelect();
                                    router.visit(`/administrators/users/${result.id}/edit`);
                                }}
                                className="flex items-center gap-3 py-3"
                            >
                                {result.avatar ? (
                                    <img src={result.avatar} alt="" className="size-8 rounded-full object-cover" />
                                ) : (
                                    <div className="flex size-8 shrink-0 items-center justify-center rounded-full bg-slate-100 text-slate-600 dark:bg-slate-500/20 dark:text-slate-400">
                                        <IconShieldLock className="size-4" />
                                    </div>
                                )}
                                <div className="flex flex-col gap-0.5">
                                    <div className="font-medium">{result.name}</div>
                                    <div className="text-muted-foreground text-xs">
                                        {result.role} · {result.email}
                                    </div>
                                </div>
                            </CommandItem>
                        ))}
                    </CommandGroup>
                )}

                {subjectResults.length > 0 && (
                    <CommandGroup heading="Subjects">
                        {subjectResults.map((result) => (
                            <CommandItem
                                key={`subject:${result.id}`}
                                onSelect={() => {
                                    if (onSelect) onSelect();
                                    router.visit(`/student/classes?search=${encodeURIComponent(result.code)}`);
                                }}
                                className="flex items-center gap-3 py-3"
                            >
                                <div className="flex size-8 shrink-0 items-center justify-center rounded-full bg-teal-100 text-teal-600 dark:bg-teal-500/20 dark:text-teal-400">
                                    <IconChecklist className="size-4" />
                                </div>
                                <div className="flex flex-col gap-0.5">
                                    <div className="font-medium">{result.title}</div>
                                    <div className="text-muted-foreground text-xs">
                                        {result.code} · {result.units} Units
                                    </div>
                                </div>
                            </CommandItem>
                        ))}
                    </CommandGroup>
                )}

                {courseResults.length > 0 && (
                    <CommandGroup heading="Courses">
                        {courseResults.map((result) => (
                            <CommandItem
                                key={`course:${result.id}`}
                                onSelect={() => {
                                    if (onSelect) onSelect();
                                    router.visit(`/student/classes?search=${encodeURIComponent(result.code)}`);
                                }}
                                className="flex items-center gap-3 py-3"
                            >
                                <div className="flex size-8 shrink-0 items-center justify-center rounded-full bg-orange-100 text-orange-600 dark:bg-orange-500/20 dark:text-orange-400">
                                    <IconSchool className="size-4" />
                                </div>
                                <div className="flex flex-col gap-0.5">
                                    <div className="font-medium">{result.title}</div>
                                    <div className="text-muted-foreground text-xs">
                                        {result.code} · {result.department}
                                    </div>
                                </div>
                            </CommandItem>
                        ))}
                    </CommandGroup>
                )}

                {/* Static Commands */}
                {hasSearchResults && (filteredNavigation.length > 0 || filteredProductivity.length > 0) && <CommandSeparator className="my-2" />}

                {filteredProductivity.length > 0 && (
                    <CommandGroup heading="Productivity">
                        {filteredProductivity.map((action) => (
                            <CommandItem key={action.id} onSelect={() => run(action)}>
                                {action.icon}
                                <span className="ml-2 flex-1">{action.label}</span>
                                {action.shortcut ? <CommandShortcut>{action.shortcut}</CommandShortcut> : null}
                            </CommandItem>
                        ))}
                    </CommandGroup>
                )}

                {filteredNavigation.length > 0 && (
                    <CommandGroup heading="Navigation">
                        {filteredNavigation.map((action) => (
                            <CommandItem key={action.id} onSelect={() => run(action)}>
                                {action.icon}
                                <span className="ml-2 flex-1">{action.label}</span>
                                {action.shortcut ? <CommandShortcut>{action.shortcut}</CommandShortcut> : null}
                            </CommandItem>
                        ))}
                    </CommandGroup>
                )}

                {!searchText && (
                    <CommandGroup heading="Tip" className="mt-2">
                        <CommandItem
                            onSelect={() => {
                                if (onSelect) onSelect();
                            }}
                        >
                            <IconChecklist className="h-4 w-4" />
                            <span className="ml-2 flex-1">Press {hint} anytime to open this.</span>
                        </CommandItem>
                    </CommandGroup>
                )}
            </CommandList>

            <Dialog open={studentDialogOpen} onOpenChange={handleStudentDialogOpenChange}>
                <DialogContent className="sm:max-w-lg">
                    <DialogHeader>
                        <DialogTitle>Student</DialogTitle>
                        <DialogDescription>Public details for quick reference.</DialogDescription>
                    </DialogHeader>

                    {studentDialogLoading ? (
                        <div className="text-muted-foreground flex h-20 items-center justify-center text-sm">
                            <div className="flex items-center gap-2">
                                <span className="size-2 animate-bounce rounded-full bg-current" style={{ animationDelay: "0ms" }} />
                                <span className="size-2 animate-bounce rounded-full bg-current" style={{ animationDelay: "150ms" }} />
                                <span className="size-2 animate-bounce rounded-full bg-current" style={{ animationDelay: "300ms" }} />
                            </div>
                        </div>
                    ) : studentDialogStudent ? (
                        <div className="flex gap-4">
                            {studentDialogStudent.picture ? (
                                <img
                                    src={studentDialogStudent.picture}
                                    alt={studentDialogStudent.name}
                                    className="ring-border size-16 rounded-md object-cover ring-1"
                                />
                            ) : (
                                <div className="bg-muted text-muted-foreground flex size-16 shrink-0 items-center justify-center rounded-md">
                                    <IconUser className="size-8 opacity-50" />
                                </div>
                            )}

                            <div className="min-w-0 flex-1 space-y-1">
                                <div className="truncate text-base font-semibold">{studentDialogStudent.name}</div>
                                <div className="text-muted-foreground text-sm">
                                    Student ID: {studentDialogStudent.student_id}
                                    {studentDialogStudent.lrn ? ` · LRN: ${studentDialogStudent.lrn}` : ""}
                                </div>
                                <div className="text-muted-foreground text-sm">
                                    {studentDialogStudent.course.code !== "N/A" ? studentDialogStudent.course.code : ""}
                                    {studentDialogStudent.course.name !== "N/A" ? ` · ${studentDialogStudent.course.name}` : ""}
                                    {studentDialogStudent.academic_year ? ` · ${studentDialogStudent.academic_year}` : ""}
                                </div>
                                {studentDialogStudent.email && <div className="text-muted-foreground text-sm">{studentDialogStudent.email}</div>}
                                {studentDialogStudent.phone && <div className="text-muted-foreground text-sm">{studentDialogStudent.phone}</div>}
                            </div>
                        </div>
                    ) : (
                        <div className="text-muted-foreground text-sm">No student details available.</div>
                    )}
                </DialogContent>
            </Dialog>
        </>
    );
}

export function GlobalCommandPalette({ user }: { user: User }) {
    const [open, setOpen] = useState(false);

    useEffect(() => {
        function onKeyDown(event: KeyboardEvent): void {
            const isMac = isMacPlatform();
            const isK = event.key.toLowerCase() === "k";

            if (isK && (isMac ? event.metaKey : event.ctrlKey)) {
                event.preventDefault();
                setOpen(true);
            }
        }

        function onExternalOpen(): void {
            setOpen(true);
        }

        window.addEventListener("keydown", onKeyDown);
        window.addEventListener(OPEN_EVENT_NAME, onExternalOpen);

        return () => {
            window.removeEventListener("keydown", onKeyDown);
            window.removeEventListener(OPEN_EVENT_NAME, onExternalOpen);
        };
    }, []);

    return (
        <CommandDialog open={open} onOpenChange={setOpen} commandProps={{ shouldFilter: false }}>
            <GlobalCommandContent user={user} isOpen={open} onSelect={() => setOpen(false)} />
        </CommandDialog>
    );
}

export function triggerGlobalCommandPalette(): void {
    openCommandPalette();
}
