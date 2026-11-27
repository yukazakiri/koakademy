import AdminLayout from "@/components/administrators/admin-layout";
import { AdminDeferredSection } from "@/components/administrators/admin-skeleton";
import { Filters, type FilterFieldConfig, type Filter as FilterType } from "@/components/reui/filters";
import { IconTile } from "@/components/reui/icon-tile";
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from "@/components/ui/alert-dialog";
import { Badge } from "@/components/ui/badge";
import { Button, buttonVariants } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Tabs, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { AdminLink } from "@/lib/admin-navigation";
import { cn } from "@/lib/utils";
import type { User } from "@/types/user";
import { Head, Link, router, useRemember } from "@inertiajs/react";
import type { SortingState } from "@tanstack/react-table";
import {
    AlertCircle,
    Award,
    Bookmark,
    BookOpen,
    Briefcase,
    Building2,
    CalendarCheck,
    CheckCircle,
    Clock,
    FileCheck2,
    FileSpreadsheet,
    FileWarning,
    Filter,
    GitMerge,
    GraduationCap,
    HelpCircle,
    KeyRound,
    Layers,
    LayoutGrid,
    List,
    Loader2,
    Plus,
    RotateCcw,
    Search,
    ShieldAlert,
    Trash2,
    UserCheck,
    UserPlus,
    Users,
    X,
    XCircle,
    Zap,
} from "lucide-react";
import { useEffect, useMemo, useRef, useState } from "react";
import { toast } from "sonner";
import { createColumns, type Student } from "./columns";
import {
    SchoolAdaptiveQuickCreateDrawer,
    type QuickCreateCourseOption,
    type QuickCreateStrandOption,
} from "./components/school-adaptive-quick-create-drawer";
import { StudentInspectorDrawer } from "./components/student-inspector-drawer";
import { DataTable } from "./data-table";
import type { IdentifierPolicy, SchoolProfile, StudentConfiguration, TriageCounts } from "./shared-types";

declare let route: (name: string, params?: Record<string, unknown> | string | number) => string;

type TriageKey = "all" | "needs_doc_review" | "missing_docs" | "active_holds" | "no_portal" | "incomplete_profile" | "trashed";

interface SavedView {
    id: string;
    name: string;
    triage: TriageKey;
    search: string;
    filters: FilterType[];
}

const SAVED_VIEWS_STORAGE_KEY = "koakademy:students:saved-views:v1";

function parseSortOption(value: string): { sort: string; direction: "asc" | "desc" } {
    const [sort = "created_at", direction = "desc"] = value.split(":");
    return { sort, direction: direction === "asc" ? "asc" : "desc" };
}

function extractFiltersFromProps(filters: StudentsIndexProps["filters"]): FilterType[] {
    const initialFilters: FilterType[] = [];
    const trashedValue = filters.trashed ?? "active";
    if (trashedValue !== "active" && filters.triage !== "trashed") {
        initialFilters.push({ id: "trashed", field: "trashed", operator: "is", values: [trashedValue] });
    }
    if (filters.type) initialFilters.push({ id: "type", field: "type", operator: "is", values: [filters.type] });
    if (filters.status) initialFilters.push({ id: "status", field: "status", operator: "is", values: [filters.status] });
    if (filters.course_id) initialFilters.push({ id: "course_id", field: "course_id", operator: "is", values: [String(filters.course_id)] });
    if (filters.department_id)
        initialFilters.push({ id: "department_id", field: "department_id", operator: "is", values: [String(filters.department_id)] });
    if (filters.year_level) initialFilters.push({ id: "year_level", field: "year_level", operator: "is", values: [String(filters.year_level)] });
    if (filters.current_enrollment)
        initialFilters.push({ id: "current_enrollment", field: "current_enrollment", operator: "is", values: [filters.current_enrollment] });
    if (filters.scholarship_type)
        initialFilters.push({ id: "scholarship_type", field: "scholarship_type", operator: "is", values: [filters.scholarship_type] });
    if (filters.employment_status)
        initialFilters.push({ id: "employment_status", field: "employment_status", operator: "is", values: [filters.employment_status] });
    if (filters.is_indigenous_person)
        initialFilters.push({ id: "is_indigenous_person", field: "is_indigenous_person", operator: "is", values: [filters.is_indigenous_person] });
    if (filters.previous_semester_cleared)
        initialFilters.push({
            id: "previous_semester_cleared",
            field: "previous_semester_cleared",
            operator: "is",
            values: [filters.previous_semester_cleared],
        });

    return initialFilters;
}

interface StudentsIndexProps {
    user: User;
    students?: {
        data: Student[];
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
        from: number | null;
        to: number | null;
    };
    stats: {
        total_students: number;
        total_enrolled: number;
        total_applicants: number;
        total_graduated: number;
    };
    triage_counts?: TriageCounts;
    filters: {
        search: string | null;
        type: string | null;
        status: string | null;
        course_id: number | null;
        department_id: number | null;
        year_level: number | null;
        current_enrollment: "enrolled" | "not_enrolled" | null;
        scholarship_type: string | null;
        employment_status: string | null;
        is_indigenous_person: string | null;
        region_of_origin: string | null;
        previous_semester_cleared: string | null;
        triage?: TriageKey | null;
        trashed: "active" | "trashed" | "all";
        sort: string;
        direction: "asc" | "desc";
        per_page: number;
    };
    options: {
        types: { value: string; label: string }[];
        statuses: { value: string; label: string }[];
        courses: { value: string; label: string }[];
        quick_create_courses?: QuickCreateCourseOption[];
        shs_strands?: QuickCreateStrandOption[];
        departments: { value: string; label: string }[];
        year_levels: { value: string; label: string }[];
        scholarship_types: { value: string; label: string }[];
        employment_statuses: { value: string; label: string }[];
        student_configuration?: StudentConfiguration;
        identifier_policy?: IdentifierPolicy;
        school_profile?: SchoolProfile;
    };
}

export default function AdministratorStudentsIndex({
    user,
    students,
    stats,
    triage_counts,
    filters: serverFilters,
    options,
}: StudentsIndexProps) {
    const schoolProfile = options.school_profile;
    const identifierPolicy = options.identifier_policy;
    const studentConfig = options.student_configuration;

    const [searchTerm, setSearchTerm] = useState(serverFilters.search ?? "");
    const [activeTriage, setActiveTriage] = useState<TriageKey>((serverFilters.triage as TriageKey) || "all");
    const [filters, setFilters] = useState<FilterType[]>(() => extractFiltersFromProps(serverFilters));
    const [sort, setSort] = useState(serverFilters.sort || "created_at");
    const [direction, setDirection] = useState<"asc" | "desc">(serverFilters.direction === "asc" ? "asc" : "desc");
    const [perPage, setPerPage] = useState(serverFilters.per_page || 20);
    const [viewMode, setViewMode] = useRemember<"list" | "grid">("list", "admin-students-view-mode");
    const [isTableLoading, setIsTableLoading] = useState(false);

    // Quick-create & Inspector drawers
    const [quickCreateOpen, setQuickCreateOpen] = useState(false);
    const [inspectedStudentId, setInspectedStudentId] = useState<number | null>(null);
    const [inspectorTab, setInspectorTab] = useState<"overview" | "documents" | "holds" | "portal">("overview");

    // Saved views
    const [savedViews, setSavedViews] = useState<SavedView[]>(() => {
        if (typeof window === "undefined") return [];
        try {
            const raw = window.localStorage.getItem(SAVED_VIEWS_STORAGE_KEY);
            return raw ? (JSON.parse(raw) as SavedView[]) : [];
        } catch {
            return [];
        }
    });

    // Row actions modal state
    const [softDeleteTarget, setSoftDeleteTarget] = useState<Student | null>(null);
    const [forceDeleteTarget, setForceDeleteTarget] = useState<Student | null>(null);
    const [forceDeleteConfirmText, setForceDeleteConfirmText] = useState("");
    const [restoreTarget, setRestoreTarget] = useState<Student | null>(null);
    const [isMutating, setIsMutating] = useState(false);

    const searchInputRef = useRef<HTMLInputElement>(null);
    const searchDebounceRef = useRef<ReturnType<typeof setTimeout> | null>(null);

    const studentRows = students?.data ?? [];

    useEffect(() => {
        setSearchTerm(serverFilters.search ?? "");
        setActiveTriage((serverFilters.triage as TriageKey) || "all");
        setFilters(extractFiltersFromProps(serverFilters));
        setSort(serverFilters.sort || "created_at");
        setDirection(serverFilters.direction === "asc" ? "asc" : "desc");
        setPerPage(serverFilters.per_page || 20);
    }, [serverFilters]);

    // Press `/` (ONLY `/` — never hijack global Cmd/Ctrl+K command palette!)
    useEffect(() => {
        const onKeyDown = (event: KeyboardEvent) => {
            const target = event.target as HTMLElement | null;
            const isEditable =
                target && (target.tagName === "INPUT" || target.tagName === "TEXTAREA" || target.isContentEditable);
            if (event.key === "/" && !isEditable && !event.metaKey && !event.ctrlKey && !event.altKey) {
                event.preventDefault();
                searchInputRef.current?.focus();
            }
        };
        window.addEventListener("keydown", onKeyDown);
        return () => window.removeEventListener("keydown", onKeyDown);
    }, []);

    useEffect(() => {
        return () => {
            if (searchDebounceRef.current) clearTimeout(searchDebounceRef.current);
        };
    }, []);

    const buildQueryParams = (overrides?: {
        search?: string;
        triage?: TriageKey;
        filters?: FilterType[];
        sort?: string;
        direction?: "asc" | "desc";
        perPage?: number;
        page?: number;
    }): Record<string, string | number> => {
        const nextSearch = overrides?.search ?? searchTerm;
        const nextTriage = overrides?.triage ?? activeTriage;
        const nextFilters = overrides?.filters ?? filters;
        const nextSort = overrides?.sort ?? sort;
        const nextDirection = overrides?.direction ?? direction;
        const nextPerPage = overrides?.perPage ?? perPage;
        const nextPage = overrides?.page ?? 1;

        const params: Record<string, string | number> = {
            sort: nextSort,
            direction: nextDirection,
            per_page: nextPerPage,
            page: nextPage,
        };

        if (nextSearch.trim() !== "") params.search = nextSearch.trim();
        if (nextTriage !== "all") params.triage = nextTriage;
        if (nextTriage === "trashed") params.trashed = "trashed";

        for (const f of nextFilters) {
            const val = f.values[0];
            if (!val) continue;
            params[f.field] = String(val);
        }

        return params;
    };

    const navigateDirectory = (overrides?: Parameters<typeof buildQueryParams>[0]) => {
        const params = buildQueryParams(overrides);
        setIsTableLoading(true);
        router.get(route("administrators.students.index"), params, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
            only: ["students", "filters"],
            onFinish: () => setIsTableLoading(false),
        });
    };

    const handleSearchChange = (value: string) => {
        setSearchTerm(value);
        if (searchDebounceRef.current) clearTimeout(searchDebounceRef.current);
        searchDebounceRef.current = setTimeout(() => {
            navigateDirectory({ search: value, page: 1 });
        }, 280);
    };

    const handleSelectTriage = (nextTriage: TriageKey) => {
        setActiveTriage(nextTriage);
        const cleaned = filters.filter((f) => f.field !== "trashed");
        setFilters(cleaned);
        navigateDirectory({ triage: nextTriage, filters: cleaned, page: 1 });
    };

    const handleFiltersChange = (nextFilters: FilterType[]) => {
        setFilters(nextFilters);
        navigateDirectory({ filters: nextFilters, page: 1 });
    };

    const handleResetAll = () => {
        setSearchTerm("");
        setActiveTriage("all");
        setFilters([]);
        setSort("created_at");
        setDirection("desc");
        navigateDirectory({ search: "", triage: "all", filters: [], sort: "created_at", direction: "desc", page: 1 });
    };

    const handleSaveCurrentView = () => {
        const name = window.prompt("Name this saved view (e.g. Missing Docs — 1st Year):");
        if (!name || name.trim() === "") return;
        const next: SavedView[] = [
            ...savedViews.filter((v) => v.name.toLowerCase() !== name.trim().toLowerCase()),
            {
                id: String(Date.now()),
                name: name.trim(),
                triage: activeTriage,
                search: searchTerm,
                filters,
            },
        ].slice(0, 6);
        setSavedViews(next);
        try {
            window.localStorage.setItem(SAVED_VIEWS_STORAGE_KEY, JSON.stringify(next));
        } catch {
            // ignore storage errors
        }
        toast.success(`Saved view "${name.trim()}"`);
    };

    const handleDeleteSavedView = (id: string) => {
        const next = savedViews.filter((v) => v.id !== id);
        setSavedViews(next);
        try {
            window.localStorage.setItem(SAVED_VIEWS_STORAGE_KEY, JSON.stringify(next));
        } catch {
            // ignore
        }
    };

    // Build school-config adaptive filter fields
    const filterFields = useMemo<FilterFieldConfig[]>(() => {
        const isPhilippine = schoolProfile?.philippine_reporting ?? false;
        const fields: FilterFieldConfig[] = [
            {
                key: "status",
                label: "Status",
                type: "select",
                icon: <GraduationCap className="size-3.5" />,
                options: options.statuses.map((s) => ({ value: s.value, label: s.label })),
            },
            {
                key: "course_id",
                label: schoolProfile?.is_tvet ? "Qualification" : "Program / Course",
                type: "select",
                icon: <BookOpen className="size-3.5" />,
                options: options.courses,
            },
            {
                key: "department_id",
                label: "Department",
                type: "select",
                icon: <Building2 className="size-3.5" />,
                options: options.departments,
            },
            {
                key: "year_level",
                label: schoolProfile?.is_basic_ed ? "Grade Level" : "Academic Level",
                type: "select",
                icon: <Layers className="size-3.5" />,
                options: options.year_levels,
            },
            {
                key: "current_enrollment",
                label: `Current ${schoolProfile?.term_label || "Term"}`,
                type: "select",
                icon: <CalendarCheck className="size-3.5" />,
                options: [
                    { value: "enrolled", label: "Enrolled" },
                    { value: "not_enrolled", label: "Not Enrolled" },
                ],
            },
            {
                key: "previous_semester_cleared",
                label: "Grade Clearance",
                type: "select",
                icon: <CheckCircle className="size-3.5" />,
                options: [
                    { value: "true", label: "Cleared" },
                    { value: "false", label: "Pending" },
                ],
            },
        ];

        if (options.types.length > 1) {
            fields.unshift({
                key: "type",
                label: "Student Category",
                type: "select",
                icon: <Users className="size-3.5" />,
                options: options.types,
            });
        }

        if (isPhilippine) {
            fields.push(
                {
                    key: "scholarship_type",
                    label: "Scholarship",
                    type: "select",
                    icon: <Award className="size-3.5" />,
                    options: options.scholarship_types,
                },
                {
                    key: "is_indigenous_person",
                    label: "Indigenous Person",
                    type: "select",
                    icon: <UserCheck className="size-3.5" />,
                    options: [
                        { value: "yes", label: "Yes" },
                        { value: "no", label: "No" },
                    ],
                },
                {
                    key: "employment_status",
                    label: "Graduate Employment",
                    type: "select",
                    icon: <Briefcase className="size-3.5" />,
                    options: options.employment_statuses,
                },
            );
        }

        return fields;
    }, [options, schoolProfile]);

    const returnUrl = useMemo(() => {
        const params = buildQueryParams({ page: students?.current_page ?? 1 });
        const qs = new URLSearchParams(Object.entries(params).map(([k, v]) => [k, String(v)])).toString();
        return `/administrators/students${qs ? `?${qs}` : ""}`;
    }, [searchTerm, activeTriage, filters, sort, direction, perPage, students?.current_page]);

    const columns = useMemo(
        () =>
            createColumns({
                returnUrl,
                identifierPolicy,
                schoolProfile,
                typeLabels: studentConfig?.type_labels,
                onInspect: (student, tab = "overview") => {
                    setInspectorTab(tab);
                    setInspectedStudentId(student.id);
                },
                onSoftDelete: (student) => setSoftDeleteTarget(student),
                onForceDelete: (student) => {
                    setForceDeleteConfirmText("");
                    setForceDeleteTarget(student);
                },
                onRestore: (student) => setRestoreTarget(student),
            }),
        [returnUrl, identifierPolicy, schoolProfile, studentConfig?.type_labels],
    );

    const inspectedIndex = useMemo(
        () => (inspectedStudentId === null ? -1 : studentRows.findIndex((s) => s.id === inspectedStudentId)),
        [inspectedStudentId, studentRows],
    );
    const inspectedStudent = inspectedIndex >= 0 ? studentRows[inspectedIndex] : null;

    const sortingState: SortingState = useMemo(
        () => [{ id: sort, desc: direction === "desc" }],
        [sort, direction],
    );

    const handleSortingChange = (next: SortingState) => {
        const primary = next[0];
        const nextSort = primary?.id || "created_at";
        const nextDir: "asc" | "desc" = primary?.desc ? "desc" : "asc";
        setSort(nextSort);
        setDirection(nextDir);
        navigateDirectory({ sort: nextSort, direction: nextDir, page: 1 });
    };

    const handleSoftDelete = () => {
        if (!softDeleteTarget || isMutating) return;
        setIsMutating(true);
        router.delete(route("administrators.students.destroy", softDeleteTarget.id), {
            preserveScroll: true,
            onSuccess: () => {
                setSoftDeleteTarget(null);
                toast.success("Student moved to trash.");
            },
            onFinish: () => setIsMutating(false),
        });
    };

    const handleRestore = () => {
        if (!restoreTarget || isMutating) return;
        setIsMutating(true);
        router.post(route("administrators.students.restore", restoreTarget.id), {}, {
            preserveScroll: true,
            onSuccess: () => {
                setRestoreTarget(null);
                toast.success("Student restored.");
            },
            onFinish: () => setIsMutating(false),
        });
    };

    const handleForceDelete = () => {
        if (!forceDeleteTarget || isMutating) return;
        setIsMutating(true);
        router.delete(route("administrators.students.force-destroy", forceDeleteTarget.id), {
            preserveScroll: true,
            onSuccess: () => {
                setForceDeleteTarget(null);
                toast.success("Student permanently deleted.");
            },
            onFinish: () => setIsMutating(false),
        });
    };

    const triageQueues: Array<{
        key: TriageKey;
        label: string;
        count?: number;
        icon: React.ReactNode;
        toneClass: string;
    }> = [
        {
            key: "all",
            label: "All Active",
            count: triage_counts?.all ?? stats.total_students,
            icon: <Users className="size-4" />,
            toneClass: "text-primary",
        },
        {
            key: "needs_doc_review",
            label: "Needs Doc Review",
            count: triage_counts?.needs_doc_review,
            icon: <Clock className="size-4" />,
            toneClass: "text-amber-600 dark:text-amber-400",
        },
        {
            key: "missing_docs",
            label: "Missing Required Docs",
            count: triage_counts?.missing_docs,
            icon: <FileWarning className="size-4" />,
            toneClass: "text-orange-600 dark:text-orange-400",
        },
        {
            key: "active_holds",
            label: "Active Holds",
            count: triage_counts?.active_holds,
            icon: <ShieldAlert className="size-4" />,
            toneClass: "text-rose-600 dark:text-rose-400",
        },
        {
            key: "no_portal",
            label: "No Portal Account",
            count: triage_counts?.no_portal,
            icon: <KeyRound className="size-4" />,
            toneClass: "text-sky-600 dark:text-sky-400",
        },
        {
            key: "incomplete_profile",
            label: "Incomplete Profile",
            count: triage_counts?.incomplete_profile,
            icon: <AlertCircle className="size-4" />,
            toneClass: "text-violet-600 dark:text-violet-400",
        },
        {
            key: "trashed",
            label: "Trashed",
            count: triage_counts?.trashed,
            icon: <Trash2 className="size-4" />,
            toneClass: "text-muted-foreground",
        },
    ];

    const hasAnyFilter = searchTerm.trim() !== "" || filters.length > 0 || activeTriage !== "all";

    return (
        <AdminLayout user={user} title="Students">
            <Head title="Administrators • Students Directory" />

            <div className="mx-auto flex w-full max-w-[1600px] flex-col gap-5">
                {/* Clean Operational Header */}
                <div className="flex flex-col justify-between gap-3 md:flex-row md:items-end">
                    <div className="space-y-1">
                        <div className="flex flex-wrap items-center gap-2">
                            <span className="text-muted-foreground text-[11px] font-semibold tracking-wider uppercase">
                                Student Records · Directory
                            </span>
                            {schoolProfile && (
                                <Badge variant="secondary" className="text-[10px] font-medium">
                                    {schoolProfile.school_level_label}
                                </Badge>
                            )}
                            <Badge variant="outline" className="font-mono text-[10px] tabular-nums">
                                {stats.total_enrolled} Enrolled · {stats.total_applicants} Applicants
                            </Badge>
                        </div>
                        <h1 className="text-2xl font-semibold tracking-tight sm:text-3xl">Student Directory</h1>
                    </div>

                    <div className="flex flex-wrap items-center gap-2">
                        <Button
                            render={<AdminLink href="/administrators/students/documents" />}
                            nativeButton={false}
                            variant="outline"
                            size="sm"
                            className="h-9 gap-1.5 text-xs"
                        >
                            <FileCheck2 className="size-3.5" />
                            Documents Hub
                        </Button>
                        <Button
                            render={<AdminLink href="/administrators/students/duplicates" />}
                            nativeButton={false}
                            variant="outline"
                            size="sm"
                            className="h-9 gap-1.5 text-xs"
                        >
                            <GitMerge className="size-3.5" />
                            Duplicates
                        </Button>
                        <Button
                            render={<AdminLink href="/administrators/students/create?mode=batch" />}
                            nativeButton={false}
                            variant="outline"
                            size="sm"
                            className="h-9 gap-1.5 text-xs"
                        >
                            <FileSpreadsheet className="size-3.5" />
                            Batch Intake
                        </Button>
                        <Button
                            type="button"
                            variant="secondary"
                            size="sm"
                            className="h-9 gap-1.5 text-xs"
                            onClick={() => setQuickCreateOpen(true)}
                        >
                            <UserPlus className="size-3.5" />
                            Quick Add
                        </Button>
                        <Link
                            href={route("administrators.students.create")}
                            className={buttonVariants({ size: "sm", className: "h-9 gap-1.5 text-xs" })}
                        >
                            <Plus className="size-3.5" />
                            Full Intake
                        </Link>
                    </div>
                </div>

                {/* Main 2-Column Triage Workbench */}
                <div className="grid grid-cols-1 items-start gap-5 lg:grid-cols-[15rem_minmax(0,1fr)]">
                    {/* Left Sticky Triage Queues Rail */}
                    <aside className="space-y-4 lg:sticky lg:top-4">
                        <div className="border-border/70 bg-card rounded-xl border p-2.5">
                            <p className="text-muted-foreground px-2 pb-2 text-[10px] font-semibold tracking-wider uppercase">
                                Triage Queues
                            </p>
                            <nav className="flex gap-1 overflow-x-auto lg:flex-col lg:overflow-visible">
                                {triageQueues.map((q) => {
                                    const active = activeTriage === q.key;
                                    return (
                                        <button
                                            key={q.key}
                                            type="button"
                                            onClick={() => handleSelectTriage(q.key)}
                                            className={cn(
                                                "flex shrink-0 items-center justify-between gap-2 rounded-lg px-2 py-2 text-left text-xs font-medium transition-colors lg:w-full",
                                                active
                                                    ? "bg-primary/10 text-primary"
                                                    : "text-foreground/80 hover:bg-muted hover:text-foreground",
                                            )}
                                        >
                                            <span className="flex min-w-0 items-center gap-2">
                                                <IconTile
                                                    variant={active ? "soft" : "outline"}
                                                    size="xs"
                                                    className={active ? "text-primary" : q.toneClass}
                                                >
                                                    {q.icon}
                                                </IconTile>
                                                <span className="truncate">{q.label}</span>
                                            </span>
                                            {typeof q.count === "number" && (
                                                <Badge
                                                    variant={active ? "default" : "secondary"}
                                                    className="h-5 min-w-6 justify-center px-1.5 font-mono text-[10px] tabular-nums"
                                                >
                                                    {q.count}
                                                </Badge>
                                            )}
                                        </button>
                                    );
                                })}
                            </nav>
                        </div>

                        {/* Saved Views */}
                        <div className="border-border/70 bg-card hidden rounded-xl border p-2.5 lg:block">
                            <div className="flex items-center justify-between px-2 pb-1.5">
                                <span className="text-muted-foreground text-[10px] font-semibold tracking-wider uppercase">
                                    Saved Views
                                </span>
                                <button
                                    type="button"
                                    onClick={handleSaveCurrentView}
                                    className="text-primary hover:underline text-[11px] font-medium"
                                >
                                    + Save
                                </button>
                            </div>
                            {savedViews.length === 0 ? (
                                <p className="text-muted-foreground px-2 py-1.5 text-[11px]">
                                    Save any filter combo for 1-click daily access.
                                </p>
                            ) : (
                                <div className="space-y-1">
                                    {savedViews.map((sv) => (
                                        <div
                                            key={sv.id}
                                            className="hover:bg-muted group flex items-center justify-between gap-1 rounded-md px-2 py-1.5 text-xs"
                                        >
                                            <button
                                                type="button"
                                                onClick={() => {
                                                    setActiveTriage(sv.triage);
                                                    setSearchTerm(sv.search);
                                                    setFilters(sv.filters);
                                                    navigateDirectory({
                                                        triage: sv.triage,
                                                        search: sv.search,
                                                        filters: sv.filters,
                                                        page: 1,
                                                    });
                                                }}
                                                className="flex min-w-0 flex-1 items-center gap-1.5 truncate text-left"
                                            >
                                                <Bookmark className="text-muted-foreground size-3 shrink-0" />
                                                <span className="truncate">{sv.name}</span>
                                            </button>
                                            <button
                                                type="button"
                                                onClick={() => handleDeleteSavedView(sv.id)}
                                                className="text-muted-foreground hover:text-destructive opacity-0 transition-opacity group-hover:opacity-100"
                                                aria-label={`Remove ${sv.name}`}
                                            >
                                                <X className="size-3" />
                                            </button>
                                        </div>
                                    ))}
                                </div>
                            )}
                        </div>
                    </aside>

                    {/* Right Main Directory Surface */}
                    <div className="min-w-0 space-y-4">
                        {/* Search, Filters & Sort Toolbar */}
                        <div className="border-border/70 bg-card flex flex-col gap-3 rounded-xl border p-3.5">
                            <div className="flex flex-col gap-2.5 sm:flex-row sm:items-center">
                                <div className="relative flex-1">
                                    <Search className="text-muted-foreground pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2" />
                                    <Input
                                        ref={searchInputRef}
                                        value={searchTerm}
                                        onChange={(e) => handleSearchChange(e.target.value)}
                                        placeholder={`Search name, ${identifierPolicy?.display_label || "ID"}, LRN, email... (press /)`}
                                        className="h-9 pr-8 pl-9 text-xs"
                                    />
                                    {searchTerm && (
                                        <button
                                            type="button"
                                            onClick={() => handleSearchChange("")}
                                            className="text-muted-foreground hover:text-foreground absolute top-1/2 right-2.5 -translate-y-1/2"
                                        >
                                            <X className="size-3.5" />
                                        </button>
                                    )}
                                </div>

                                <div className="flex flex-wrap items-center gap-2">
                                    <Select
                                        value={`${sort}:${direction}`}
                                        onValueChange={(value) => {
                                            const parsed = parseSortOption(value);
                                            setSort(parsed.sort);
                                            setDirection(parsed.direction);
                                            navigateDirectory({ sort: parsed.sort, direction: parsed.direction, page: 1 });
                                        }}
                                    >
                                        <SelectTrigger className="h-9 w-[160px] text-xs">
                                            <SelectValue placeholder="Sort by" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="created_at:desc">Newest Added</SelectItem>
                                            <SelectItem value="created_at:asc">Oldest Added</SelectItem>
                                            <SelectItem value="name:asc">Name (A–Z)</SelectItem>
                                            <SelectItem value="name:desc">Name (Z–A)</SelectItem>
                                            <SelectItem value="student_id:asc">ID Ascending</SelectItem>
                                            <SelectItem value="student_id:desc">ID Descending</SelectItem>
                                        </SelectContent>
                                    </Select>

                                    <Tabs value={viewMode} onValueChange={(v) => setViewMode(v as "list" | "grid")}>
                                        <TabsList className="h-9">
                                            <TabsTrigger value="list" className="px-2.5" aria-label="List view">
                                                <List className="size-3.5" />
                                            </TabsTrigger>
                                            <TabsTrigger value="grid" className="px-2.5" aria-label="Grid view">
                                                <LayoutGrid className="size-3.5" />
                                            </TabsTrigger>
                                        </TabsList>
                                    </Tabs>

                                    {hasAnyFilter && (
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="sm"
                                            className="h-9 gap-1 px-2.5 text-xs"
                                            onClick={handleResetAll}
                                        >
                                            <RotateCcw className="size-3.5" />
                                            Reset
                                        </Button>
                                    )}
                                </div>
                            </div>

                            <div className="flex flex-wrap items-center gap-2">
                                <Filters filters={filters} fields={filterFields} onChange={handleFiltersChange} />
                            </div>
                        </div>

                        {/* Deferred Directory Table / Grid */}
                        <div className="relative">
                            {isTableLoading && (
                                <div className="bg-background/50 absolute inset-0 z-20 flex items-center justify-center rounded-xl backdrop-blur-[1px]">
                                    <Loader2 className="text-primary size-6 animate-spin" />
                                </div>
                            )}

                            <AdminDeferredSection data="students" name="student-directory" variant="list" label="Loading students">
                                <DataTable
                                    columns={columns}
                                    data={studentRows}
                                    pageIndex={(students?.current_page ?? 1) - 1}
                                    pageSize={students?.per_page ?? perPage}
                                    pageCount={students?.last_page ?? 1}
                                    totalCount={students?.total ?? 0}
                                    from={students?.from ?? undefined}
                                    to={students?.to ?? undefined}
                                    sorting={sortingState}
                                    onSortingChange={handleSortingChange}
                                    onPageIndexChange={(nextPageIndex) => navigateDirectory({ page: nextPageIndex + 1 })}
                                    onPageSizeChange={(nextPerPage) => {
                                        setPerPage(nextPerPage);
                                        navigateDirectory({ perPage: nextPerPage, page: 1 });
                                    }}
                                    viewMode={viewMode}
                                    returnUrl={returnUrl}
                                    isTrashedView={activeTriage === "trashed"}
                                    onRowClick={(student) => {
                                        setInspectorTab("overview");
                                        setInspectedStudentId(student.id);
                                    }}
                                    onSoftDelete={(student) => setSoftDeleteTarget(student)}
                                    onForceDelete={(student) => {
                                        setForceDeleteConfirmText("");
                                        setForceDeleteTarget(student);
                                    }}
                                    onRestore={(student) => setRestoreTarget(student)}
                                    onClearFilters={handleResetAll}
                                    bulkActions={{
                                        statusOptions: options.statuses,
                                        yearLevelOptions: options.year_levels,
                                    }}
                                />
                            </AdminDeferredSection>
                        </div>
                    </div>
                </div>
            </div>

            {/* Walk-in Quick-Create Drawer */}
            <SchoolAdaptiveQuickCreateDrawer
                open={quickCreateOpen}
                onOpenChange={setQuickCreateOpen}
                configuration={studentConfig}
                identifierPolicy={identifierPolicy}
                schoolProfile={schoolProfile}
                courses={options.quick_create_courses ?? []}
                shsStrands={options.shs_strands ?? []}
            />

            {/* Deep 4-Tab Keyboard-Steppable Inspector Drawer */}
            <StudentInspectorDrawer
                student={inspectedStudent}
                open={inspectedStudent !== null}
                onOpenChange={(open) => {
                    if (!open) setInspectedStudentId(null);
                }}
                initialTab={inspectorTab}
                statusOptions={options.statuses}
                studentConfiguration={studentConfig}
                hasPrev={inspectedIndex > 0}
                hasNext={inspectedIndex >= 0 && inspectedIndex < studentRows.length - 1}
                onPrev={() => {
                    if (inspectedIndex > 0) setInspectedStudentId(studentRows[inspectedIndex - 1].id);
                }}
                onNext={() => {
                    if (inspectedIndex >= 0 && inspectedIndex < studentRows.length - 1) {
                        setInspectedStudentId(studentRows[inspectedIndex + 1].id);
                    }
                }}
            />

            {/* Row Soft Delete Dialog */}
            <AlertDialog open={softDeleteTarget !== null} onOpenChange={(open) => !open && setSoftDeleteTarget(null)}>
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>Move student to trash?</AlertDialogTitle>
                        <AlertDialogDescription>
                            {softDeleteTarget?.name} will be moved to the Trashed queue and can be restored anytime.
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel>Cancel</AlertDialogCancel>
                        <AlertDialogAction onClick={handleSoftDelete} disabled={isMutating}>
                            Move to Trash
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>

            {/* Row Restore Dialog */}
            <AlertDialog open={restoreTarget !== null} onOpenChange={(open) => !open && setRestoreTarget(null)}>
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>Restore student record?</AlertDialogTitle>
                        <AlertDialogDescription>
                            {restoreTarget?.name} will be restored to the active student directory.
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel>Cancel</AlertDialogCancel>
                        <AlertDialogAction onClick={handleRestore} disabled={isMutating}>
                            Restore Student
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>

            {/* Row Force Delete Dialog */}
            <AlertDialog open={forceDeleteTarget !== null} onOpenChange={(open) => !open && setForceDeleteTarget(null)}>
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle className="text-destructive flex items-center gap-2">
                            <Zap className="size-4" />
                            Permanently delete student?
                        </AlertDialogTitle>
                        <AlertDialogDescription>
                            This permanently deletes <strong>{forceDeleteTarget?.name}</strong> and associated records. Type{" "}
                            <code className="font-mono font-semibold">DELETE</code> to confirm.
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <div className="py-2">
                        <Label htmlFor="single-force-delete-confirm" className="text-xs">
                            Confirmation
                        </Label>
                        <Input
                            id="single-force-delete-confirm"
                            value={forceDeleteConfirmText}
                            onChange={(e) => setForceDeleteConfirmText(e.target.value)}
                            placeholder="DELETE"
                            className="mt-1.5 font-mono text-xs"
                        />
                    </div>
                    <AlertDialogFooter>
                        <AlertDialogCancel>Cancel</AlertDialogCancel>
                        <AlertDialogAction
                            onClick={handleForceDelete}
                            disabled={isMutating || forceDeleteConfirmText !== "DELETE"}
                            className="bg-destructive text-destructive-foreground hover:bg-destructive/90"
                        >
                            Delete Permanently
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </AdminLayout>
    );
}
