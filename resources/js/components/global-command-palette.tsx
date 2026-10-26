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
import { adminVisit } from "@/lib/admin-navigation";
import type { FeatureFlags } from "@/types/feature-flags";
import type { User } from "@/types/user";
import { router, usePage } from "@inertiajs/react";
import { route } from "ziggy-js";
import {
    IconBell,
    IconCalendarEvent,
    IconCalendarStats,
    IconChecklist,
    IconChevronRight,
    IconClock,
    IconDownload,
    IconGridDots,
    IconHelp,
    IconHome,
    IconSchool,
    IconSettings,
    IconShieldLock,
    IconUser,
    IconUsers,
} from "@tabler/icons-react";
import { useEffect, useMemo, useRef, useState, type ReactNode } from "react";
import { toast } from "sonner";
import {
    MIN_QUERY_LENGTH,
    RESULT_LIMIT,
    type SearchGroup,
    type SearchScope,
    type StudentSearchResult,
    useGlobalSearch,
} from "@/hooks/use-global-search";
import { highlightSearchTerms } from "@/lib/highlight-search-terms";
import { isAdministratorPortalRole, isFacultyPortalRole, normalizePortalRole } from "@/lib/portal-role";

const OPEN_EVENT_NAME = "koakademy:commandPalette:open";
const RECENT_SEARCHES_KEY = "koakademy:commandPalette:recentSearches";
const MAX_RECENT_SEARCHES = 5;

/** Group headings, in the order the palette renders them. */
const GROUP_HEADINGS: Record<SearchGroup, string> = {
    students: "Students",
    classes: "Classes",
    faculty: "Faculty",
    users: "Users",
    enrollments: "Enrollments",
    subjects: "Subjects",
    courses: "Courses",
    schedule: "Schedule",
};

/** Each portal's full-page results view, where "View all" lands. */
const RESULTS_ROUTES: Record<SearchScope, string> = {
    administrator: "administrators.search.results",
    faculty: "faculty.search.results",
    student: "student.search.results",
};

type ActionItem = {
    id: string;
    label: string;
    keywords?: string;
    shortcut?: string;
    icon: ReactNode;
    closeOnSelect?: boolean;
    onSelect: () => void;
};

type PageProps = {
    auth?: {
        user?: User | null;
    };
    moduleAdminRoutes?: ModuleAdminRoute[];
    /** Role-scoped dashboard desks, already filtered by DashboardRegistry::navigationFor(). */
    deskRoutes?: ModuleAdminRoute[];
    featureFlags?: FeatureFlags;
};

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

function readRecentSearches(): string[] {
    try {
        const parsed: unknown = JSON.parse(window.localStorage.getItem(RECENT_SEARCHES_KEY) ?? "[]");

        return Array.isArray(parsed)
            ? parsed.filter((value): value is string => typeof value === "string").slice(0, MAX_RECENT_SEARCHES)
            : [];
    } catch {
        return [];
    }
}

/**
 * Remember a query the user acted on, newest first and without duplicates.
 */
function persistRecentSearch(query: string): string[] {
    const trimmed = query.trim();

    if (trimmed.length < MIN_QUERY_LENGTH) {
        return readRecentSearches();
    }

    const next = [trimmed, ...readRecentSearches().filter((value) => value.toLowerCase() !== trimmed.toLowerCase())].slice(
        0,
        MAX_RECENT_SEARCHES,
    );

    setLocalStorageSafe(RECENT_SEARCHES_KEY, JSON.stringify(next));

    return next;
}

function prefetchAdministratorDestination(href: string): void {
    if (href !== "/administrators" && !href.startsWith("/administrators/")) {
        return;
    }

    router.prefetch(href, {}, { cacheFor: "30s" });
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

function ViewAllRow({ group, onSelect }: { group: SearchGroup; onSelect: () => void }) {
    return (
        <CommandItem value={`view-all-${group}`} onSelect={onSelect} className="text-muted-foreground">
            <IconChevronRight className="size-3.5" />
            <span className="ml-2 flex-1 text-xs font-medium">View all {GROUP_HEADINGS[group].toLowerCase()}</span>
        </CommandItem>
    );
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
    const [recentSearches, setRecentSearches] = useState<string[]>(readRecentSearches);
    const [studentDialogOpen, setStudentDialogOpen] = useState(false);
    const [studentDialogLoading, setStudentDialogLoading] = useState(false);
    const [studentDialogStudent, setStudentDialogStudent] = useState<StudentPublicInfo | null>(null);
    const studentDialogAbortController = useRef<AbortController | null>(null);

    const isAdminContext = typeof window !== "undefined" && window.location.pathname.startsWith("/administrators");
    const moduleAdminRoutes = props.moduleAdminRoutes ?? [];
    const deskRoutes = props.deskRoutes ?? [];
    const sharedAuthUser = props.auth?.user;
    const resolvedUserRole = sharedAuthUser?.role ?? user.role ?? "";
    const resolvedUserPermissions = sharedAuthUser?.permissions ?? user.permissions ?? [];

    // Portal role decides which search API is used. `portal-role.ts` is the shared
    // source of truth for role groupings, so every faculty role (professor,
    // associate/assistant professor, instructor, part-time) is covered.
    const portalRole = normalizePortalRole(resolvedUserRole);
    const isInstructor = isFacultyPortalRole(portalRole);
    const searchScope: SearchScope = isAdministratorPortalRole(portalRole)
        ? "administrator"
        : isInstructor
          ? "faculty"
          : "student";
    const { status, results, total, retry } = useGlobalSearch({
        scope: searchScope,
        query: searchText,
        enabled: isOpen,
    });
    const aiEnabled = props.featureFlags?.ai?.any === true;
    const allowedAdminRoutes = useMemo(
        () =>
            getRoutesForRoleWithModules(resolvedUserRole, resolvedUserPermissions, moduleAdminRoutes, deskRoutes).filter(
                (route) => route.id !== "admin-ai-chat" || aiEnabled,
            ),
        [aiEnabled, deskRoutes, moduleAdminRoutes, resolvedUserPermissions, resolvedUserRole],
    );
    const allowedAdminRouteIds = useMemo(() => new Set(allowedAdminRoutes.map((route) => route.id)), [allowedAdminRoutes]);

    const actions = useMemo(() => {
        if (isAdminContext) {
            const navigationCandidates: Array<ActionItem & { routeId?: string }> = [
                {
                    id: "nav:admin-dashboard",
                    routeId: "admin-dashboard-overview",
                    label: "Executive Overview",
                    keywords: "admin dashboard home overview executive",
                    shortcut: "G D",
                    icon: <IconHome className="h-4 w-4" />,
                    onSelect: () => adminVisit("/administrators/dashboard/overview"),
                },
                {
                    id: "nav:admin-dashboard-enrollment",
                    routeId: "admin-dashboard-enrollment",
                    label: "Enrollment Analytics",
                    keywords: "admin enrollment admissions funnel analytics",
                    icon: <IconHome className="h-4 w-4" />,
                    onSelect: () => adminVisit("/administrators/dashboard/enrollment"),
                },
                {
                    id: "nav:admin-dashboard-students",
                    routeId: "admin-dashboard-students",
                    label: "Student Demographics",
                    keywords: "admin student demographics breakdown population",
                    icon: <IconUser className="h-4 w-4" />,
                    onSelect: () => adminVisit("/administrators/dashboard/students"),
                },
                {
                    id: "nav:admin-dashboard-operations",
                    routeId: "admin-dashboard-operations",
                    label: "Operations Command",
                    keywords: "admin operations command activity incidents",
                    icon: <IconUsers className="h-4 w-4" />,
                    onSelect: () => adminVisit("/administrators/dashboard/operations"),
                },
                {
                    id: "nav:admin-students",
                    routeId: "admin-students",
                    label: "Student Records",
                    keywords: "admin students records",
                    shortcut: "G S",
                    icon: <IconUser className="h-4 w-4" />,
                    onSelect: () => adminVisit("/administrators/students"),
                },
                {
                    id: "nav:admin-classes",
                    routeId: "admin-classes",
                    label: "Class Sections",
                    keywords: "admin classes sections",
                    shortcut: "G C",
                    icon: <IconUsers className="h-4 w-4" />,
                    onSelect: () => adminVisit("/administrators/classes"),
                },
                {
                    id: "nav:admin-faculties",
                    routeId: "admin-faculty",
                    label: "Faculty & Staff",
                    keywords: "admin faculty staff instructors",
                    shortcut: "G F",
                    icon: <IconUsers className="h-4 w-4" />,
                    onSelect: () => adminVisit("/administrators/faculties"),
                },
                {
                    id: "nav:admin-enrollments",
                    routeId: "admin-enrollments",
                    label: "Enrollment Management",
                    keywords: "admin enrollments management",
                    shortcut: "G E",
                    icon: <IconChecklist className="h-4 w-4" />,
                    onSelect: () => adminVisit("/administrators/enrollments"),
                },
                {
                    id: "nav:admin-scheduling",
                    routeId: "admin-scheduling-analytics",
                    label: "Scheduling & Timetable",
                    keywords: "admin scheduling timetable analytics",
                    icon: <IconCalendarStats className="h-4 w-4" />,
                    onSelect: () => adminVisit("/administrators/scheduling-analytics"),
                },
                {
                    id: "nav:admin-users",
                    routeId: "admin-users",
                    label: "User Management",
                    keywords: "admin users",
                    shortcut: "G U",
                    icon: <IconShieldLock className="h-4 w-4" />,
                    onSelect: () => adminVisit("/administrators/users"),
                },
                {
                    id: "nav:admin-settings",
                    routeId: "admin-settings",
                    label: "My Profile",
                    keywords: "admin profile settings",
                    icon: <IconSettings className="h-4 w-4" />,
                    onSelect: () => adminVisit("/administrators/settings"),
                },
                {
                    id: "nav:admin-system-management",
                    routeId: "admin-system-management",
                    label: "System Settings",
                    keywords: "institution campus enrollment grading branding notifications newsletter api integrations system health",
                    icon: <IconSettings className="h-4 w-4" />,
                    onSelect: () => adminVisit("/administrators/system-management"),
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
                    onSelect: () => adminVisit(route.link),
                }));
            const navigation = [...navigationCandidates, ...moduleNavigation].filter(
                (item) => !item.routeId || allowedAdminRouteIds.has(item.routeId),
            );

            return { navigation, productivity: [] };
        }

        const scheduleViewKey = `koakademy:scheduleView:v1:${user.email}`;

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
                    setLocalStorageSafe("koakademy.classes.viewMode", "board");
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
                    setLocalStorageSafe("koakademy.classes.search", "");
                    setLocalStorageSafe("koakademy.classes.filter.classification", "all");
                    setLocalStorageSafe("koakademy.classes.filter.day", "all");
                    setLocalStorageSafe("koakademy.classes.filter.room", "all");
                    setLocalStorageSafe("koakademy.classes.filter.onlyConflicts", "false");
                    setLocalStorageSafe("koakademy.classes.filter.onlyUnscheduled", "false");
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
        if (searchScope === "administrator") {
            visitResult(student.url);
            return;
        }

        if (searchScope !== "faculty") {
            return;
        }

        setStudentDialogOpen(true);
        setStudentDialogLoading(true);
        setStudentDialogStudent(null);

        studentDialogAbortController.current?.abort();

        const abortController = new AbortController();
        studentDialogAbortController.current = abortController;

        try {
            const response = await fetch(route("faculty.students.show", { student: student.id }), {
                method: "GET",
                headers: {
                    Accept: "application/json",
                    "X-Requested-With": "XMLHttpRequest",
                },
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
    const { students, classes, users, faculty, enrollments, subjects, courses, schedule } = results;
    const showNoResults =
        status === "success" && total === 0 && filteredNavigation.length === 0 && filteredProductivity.length === 0;
    const statusMessage =
        status === "loading"
            ? "Searching"
            : status === "error"
              ? "Search failed"
              : status === "success"
                ? `${total} results`
                : "";

    function rememberSearch(): void {
        setRecentSearches(persistRecentSearch(searchText));
    }

    function openResults(group: SearchGroup): void {
        // The palette previews a few rows per group; long result sets paginate on
        // the portal's results page, which every portal now has.
        rememberSearch();
        if (onSelect) onSelect();
        router.visit(route(RESULTS_ROUTES[searchScope], { q: searchText.trim(), type: group }));
    }

    /**
     * Rows carry the destination the server chose, so every portal links the same
     * record to the same place from the palette and from its results page.
     */
    function visitResult(url: string | null | undefined): void {
        if (! url) return;

        rememberSearch();
        if (onSelect) onSelect();

        if (isAdminContext) {
            adminVisit(url);
            return;
        }

        router.visit(url);
    }

    return (
        <>
            <CommandInput
                value={searchText}
                onValueChange={setSearchText}
                placeholder={searchPlaceholder}
            />
            <CommandList className={listClassName} aria-busy={status === "loading"}>
                <div className="sr-only" role="status" aria-live="polite">
                    {statusMessage}
                </div>

                {status === "too-short" ? (
                    <div className="text-muted-foreground px-2 py-6 text-center text-sm">
                        Keep typing — search starts at {MIN_QUERY_LENGTH} characters.
                    </div>
                ) : null}

                {status === "error" ? (
                    <div className="px-2 py-6 text-center text-sm">
                        <div className="text-destructive font-medium">Search is unavailable right now.</div>
                        <button type="button" onClick={retry} className="text-muted-foreground mt-1 text-xs underline">
                            Try again
                        </button>
                    </div>
                ) : null}

                {status === "loading" ? (
                    <div className="text-muted-foreground py-6 text-center text-sm">Searching…</div>
                ) : null}

                {status === "idle" && recentSearches.length > 0 ? (
                    <CommandGroup heading="Recent searches">
                        {recentSearches.map((recent) => (
                            <CommandItem
                                key={`recent:${recent}`}
                                value={`recent ${recent}`}
                                onSelect={() => setSearchText(recent)}
                            >
                                <IconClock className="size-4 opacity-60" />
                                <span className="ml-2 flex-1 truncate">{recent}</span>
                            </CommandItem>
                        ))}
                    </CommandGroup>
                ) : null}

                {showNoResults ? <CommandEmpty>No matches found.</CommandEmpty> : null}


                {/* Search Results First */}
                {enrollments.length > 0 && (
                    <CommandGroup heading="Enrollments">
                        {enrollments.map((result) => (
                            <CommandItem
                                key={`enrollment:${result.id}`}
                                onMouseEnter={() => {
                                    if (isAdminContext && result.url) {
                                        prefetchAdministratorDestination(result.url);
                                    }
                                }}
                                onSelect={() => visitResult(result.url)}
                                className="flex items-center gap-3 py-3"
                            >
                                <div className="flex size-8 shrink-0 items-center justify-center rounded-full bg-orange-100 text-orange-600 dark:bg-orange-500/20 dark:text-orange-400">
                                    <IconSchool className="size-4" />
                                </div>
                                <div className="flex flex-col gap-0.5">
                                    <div className="font-medium">{highlightSearchTerms(result.student_name, searchText)}</div>
                                    <div className="text-muted-foreground text-xs">
                                        {result.course_code} · {result.year_level} · {result.semester}
                                    </div>
                                </div>
                                {result.status && <div className="text-muted-foreground ml-auto text-xs font-medium">{result.status}</div>}
                            </CommandItem>
                        ))}
                        {enrollments.length >= RESULT_LIMIT ? (
                            <ViewAllRow group="enrollments" onSelect={() => openResults("enrollments")} />
                        ) : null}
                    </CommandGroup>
                )}


                {students.length > 0 && (
                    <CommandGroup heading="Students">
                        {students.map((result) => (
                            <CommandItem
                                key={`student:${result.id}`}
                                onSelect={() => {
                                    rememberSearch();
                                    void openStudentDetails(result);
                                }}
                                className="flex items-center gap-3 py-3"
                            >
                                <div className="flex size-8 shrink-0 items-center justify-center rounded-full bg-blue-100 text-blue-600 dark:bg-blue-500/20 dark:text-blue-400">
                                    <IconUser className="size-4" />
                                </div>
                                <div className="flex flex-col gap-0.5">
                                    <div className="font-medium">{highlightSearchTerms(result.name, searchText)}</div>
                                    <div className="text-muted-foreground text-xs">
                                        ID: {result.student_id} {result.course ? `· ${result.course}` : ""}
                                    </div>
                                </div>
                                {result.status && <div className="text-muted-foreground ml-auto text-xs font-medium">{result.status}</div>}
                            </CommandItem>
                        ))}
                        {students.length >= RESULT_LIMIT ? (
                            <ViewAllRow group="students" onSelect={() => openResults("students")} />
                        ) : null}
                    </CommandGroup>
                )}

                {classes.length > 0 && (
                    <CommandGroup heading="Classes">
                        {classes.map((result) => (
                            <CommandItem
                                key={`class:${result.id}`}
                                onMouseEnter={() => {
                                    if (isAdminContext && result.url) {
                                        prefetchAdministratorDestination(result.url);
                                    }
                                }}
                                onSelect={() => visitResult(result.url)}
                                className="flex items-center gap-3 py-3"
                            >
                                <div className="flex size-8 shrink-0 items-center justify-center rounded-full bg-indigo-100 text-indigo-600 dark:bg-indigo-500/20 dark:text-indigo-400">
                                    <IconUsers className="size-4" />
                                </div>
                                <div className="min-w-0 flex-1">
                                    <div className="flex items-center gap-2">
                                        <span className="truncate font-medium">{highlightSearchTerms(result.record_title, searchText)}</span>
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
                        {classes.length >= RESULT_LIMIT ? (
                            <ViewAllRow group="classes" onSelect={() => openResults("classes")} />
                        ) : null}
                    </CommandGroup>
                )}

                {faculty.length > 0 && (
                    <CommandGroup heading="Faculty">
                        {faculty.map((result) => (
                            <CommandItem
                                key={`faculty:${result.id}`}
                                onMouseEnter={() => {
                                    if (result.url) prefetchAdministratorDestination(result.url);
                                }}
                                onSelect={() => visitResult(result.url)}
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
                                    <div className="font-medium">{highlightSearchTerms(result.name, searchText)}</div>
                                    <div className="text-muted-foreground text-xs">
                                        {result.department} · {result.email}
                                    </div>
                                </div>
                            </CommandItem>
                        ))}
                        {faculty.length >= RESULT_LIMIT ? (
                            <ViewAllRow group="faculty" onSelect={() => openResults("faculty")} />
                        ) : null}
                    </CommandGroup>
                )}

                {users.length > 0 && (
                    <CommandGroup heading="Users">
                        {users.map((result) => (
                            <CommandItem
                                key={`user:${result.id}`}
                                onMouseEnter={() => {
                                    if (result.url) prefetchAdministratorDestination(result.url);
                                }}
                                onSelect={() => visitResult(result.url)}
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
                                    <div className="font-medium">{highlightSearchTerms(result.name, searchText)}</div>
                                    <div className="text-muted-foreground text-xs">
                                        {result.role} · {result.email}
                                    </div>
                                </div>
                            </CommandItem>
                        ))}
                        {users.length >= RESULT_LIMIT ? (
                            <ViewAllRow group="users" onSelect={() => openResults("users")} />
                        ) : null}
                    </CommandGroup>
                )}

                {subjects.length > 0 && (
                    <CommandGroup heading="Subjects">
                        {subjects.map((result) => (
                            <CommandItem
                                key={`subject:${result.id}`}
                                onSelect={() => visitResult(result.url)}
                                className="flex items-center gap-3 py-3"
                            >
                                <div className="flex size-8 shrink-0 items-center justify-center rounded-full bg-teal-100 text-teal-600 dark:bg-teal-500/20 dark:text-teal-400">
                                    <IconChecklist className="size-4" />
                                </div>
                                <div className="flex flex-col gap-0.5">
                                    <div className="font-medium">{highlightSearchTerms(result.title, searchText)}</div>
                                    <div className="text-muted-foreground text-xs">
                                        {result.code} · {result.units} Units{result.course_code ? ` · ${result.course_code}` : ""}
                                    </div>
                                </div>
                            </CommandItem>
                        ))}
                        {subjects.length >= RESULT_LIMIT ? (
                            <ViewAllRow group="subjects" onSelect={() => openResults("subjects")} />
                        ) : null}
                    </CommandGroup>
                )}

                {courses.length > 0 && (
                    <CommandGroup heading="Courses">
                        {courses.map((result) => (
                            <CommandItem
                                key={`course:${result.id}`}
                                onSelect={() => visitResult(result.url)}
                                className="flex items-center gap-3 py-3"
                            >
                                <div className="flex size-8 shrink-0 items-center justify-center rounded-full bg-orange-100 text-orange-600 dark:bg-orange-500/20 dark:text-orange-400">
                                    <IconSchool className="size-4" />
                                </div>
                                <div className="flex flex-col gap-0.5">
                                    <div className="font-medium">{highlightSearchTerms(result.title, searchText)}</div>
                                    <div className="text-muted-foreground text-xs">
                                        {result.code} · {result.department}
                                    </div>
                                </div>
                            </CommandItem>
                        ))}
                        {courses.filter((result) => result.edit_url).map((result) => (
                            <CommandItem key={`edit-course:${result.id}`} value={`edit-course:${result.id}`} onSelect={() => visitResult(result.edit_url)}>
                                <IconSettings className="size-4" />
                                <span>Edit {result.code} curriculum</span>
                            </CommandItem>
                        ))}
                        {courses.length >= RESULT_LIMIT ? (
                            <ViewAllRow group="courses" onSelect={() => openResults("courses")} />
                        ) : null}
                    </CommandGroup>
                )}

                {schedule.length > 0 && (
                    <CommandGroup heading="Schedule">
                        {schedule.map((result) => (
                            <CommandItem key={`schedule:${result.id}`} value={`schedule:${result.id}`} onSelect={() => visitResult(result.url)}>
                                <IconCalendarEvent className="size-4" />
                                <div className="min-w-0 flex-1">
                                    <div className="truncate font-medium">{result.record_title}</div>
                                    <div className="text-muted-foreground text-xs">{result.day_of_week} · {result.time_range} · {result.room}</div>
                                </div>
                            </CommandItem>
                        ))}
                        <ViewAllRow group="schedule" onSelect={() => openResults("schedule")} />
                    </CommandGroup>
                )}

                {/* Static Commands */}
                {total > 0 && (filteredNavigation.length > 0 || filteredProductivity.length > 0) && <CommandSeparator className="my-2" />}

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
