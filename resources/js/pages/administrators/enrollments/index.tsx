import AdminLayout from "@/components/administrators/admin-layout";
import { Alert, AlertDescription, AlertTitle } from "@/components/reui/alert";
import { Badge } from "@/components/reui/badge";
import { Filters, type FilterFieldConfig, type Filter as FilterType } from "@/components/reui/filters";
import { Frame, FramePanel } from "@/components/reui/frame";
import { Button, buttonVariants } from "@/components/ui/button";
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import { Input } from "@/components/ui/input";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { cn } from "@/lib/utils";
import { bulkAction as enrollmentBulkActionRoute } from "@/routes/administrators/enrollments";
import { store as storeTransitionRoute } from "@/routes/administrators/enrollments/transitions";
import { Head, Link, router, usePage } from "@inertiajs/react";
import axios from "axios";
import {
    AlertTriangle,
    ArrowUpRight,
    Ban,
    BookOpen,
    Building2,
    CalendarCheck2,
    CalendarClock,
    CircleDollarSign,
    Clock,
    Download,
    FileSpreadsheet,
    Filter,
    GraduationCap,
    KanbanSquare,
    ListFilter,
    Lock,
    MoreHorizontal,
    RotateCcw,
    Search,
    Settings2,
    SlidersHorizontal,
    Table2,
    UserPlus,
    Users,
    Workflow,
} from "lucide-react";
import { useCallback, useEffect, useMemo, useRef, useState } from "react";
import { toast } from "sonner";
import { route } from "ziggy-js";
import type { EnrollmentRow, EnrollmentTransitionOption } from "./columns";
import { EnrollmentConfigSheet, type EnrollmentConfigTab } from "./enrollment-config-sheet";
import { EnrollmentDataGrid } from "./enrollment-data-grid";
import { EnrollmentDetailSheet } from "./enrollment-detail-sheet";
import { BulkReportsDialog, DeleteEnrollmentDialog, ForceDeleteEnrollmentDialog, RestoreEnrollmentDialog } from "./enrollment-dialogs";
import { EnrollmentKanbanBoard } from "./enrollment-kanban-board";
import { EnrollmentPipelineStrip } from "./enrollment-pipeline-strip";
import { exportEnrollmentsToCsv } from "./enrollment-utils";
import type { Branding, BulkReportFilters, EnrollmentManagementProps, EnrollmentWorkspacePreferences } from "./types";

type ViewMode = "table" | "board";

const VIEW_MODE_STORAGE_KEY = "koakademy.administrators.enrollments.view_mode";
const WORKSPACE_PREFS_STORAGE_KEY = "koakademy.administrators.enrollments.workspace_prefs";

const DEFAULT_WORKSPACE_PREFS: EnrollmentWorkspacePreferences = {
    defaultView: "table",
    density: "comfortable",
    showPipelineStrip: true,
    showDepartmentChips: true,
    showTuitionBars: true,
    collapseCompletedLane: true,
    completedPreviewLimit: 4,
};

function loadWorkspacePrefs(): EnrollmentWorkspacePreferences {
    if (typeof window === "undefined") return DEFAULT_WORKSPACE_PREFS;
    try {
        const raw = window.localStorage.getItem(WORKSPACE_PREFS_STORAGE_KEY);
        if (!raw) return DEFAULT_WORKSPACE_PREFS;
        return { ...DEFAULT_WORKSPACE_PREFS, ...(JSON.parse(raw) as Partial<EnrollmentWorkspacePreferences>) };
    } catch {
        return DEFAULT_WORKSPACE_PREFS;
    }
}

function createInitialEnrollmentFilters(filters: EnrollmentManagementProps["filters"]): FilterType[] {
    const initialFilters: FilterType[] = [];

    if (filters.status_filter && filters.status_filter !== "all") {
        initialFilters.push({ id: "status_filter", field: "status_filter", operator: "is", values: [filters.status_filter] });
    }

    if (filters.department_filter && filters.department_filter !== "all") {
        initialFilters.push({ id: "department_filter", field: "department_filter", operator: "is", values: [filters.department_filter] });
    }

    if (filters.year_level_filter && filters.year_level_filter !== "all") {
        initialFilters.push({ id: "year_level_filter", field: "year_level_filter", operator: "is", values: [filters.year_level_filter] });
    }

    if (filters.course_filter && filters.course_filter !== "all") {
        initialFilters.push({ id: "course_filter", field: "course_filter", operator: "is", values: [filters.course_filter] });
    }

    if (filters.payment_filter && filters.payment_filter !== "all") {
        initialFilters.push({ id: "payment_filter", field: "payment_filter", operator: "is", values: [filters.payment_filter] });
    }

    return initialFilters;
}

function getActiveFilterValue(filters: FilterType[], field: string): string {
    const value = filters.find((filter) => filter.field === field)?.values[0];
    return value === undefined || value === null ? "all" : String(value);
}

function upsertSingleFilter(filters: FilterType[], field: string, value: string): FilterType[] {
    const nextFilters = filters.filter((filter) => filter.field !== field);
    if (value === "all") {
        return nextFilters;
    }

    return [...nextFilters, { id: field, field, operator: "is", values: [value] }];
}

function semesterLabel(semester?: number | null): string {
    if (semester === 1) return "1st Sem";
    if (semester === 2) return "2nd Sem";
    if (semester === 3) return "Summer";
    return semester ? `Sem ${semester}` : "Current term";
}

export default function AdministratorEnrollmentsIndex({
    user,
    workflow_setup_required,
    filament,
    applicantsCount,
    enrollments,
    board_enrollments,
    pipeline_meta,
    enrollment_period,
    analytics,
    filters,
    available_courses,
    enrollment_pipeline,
    assessment_export_options,
}: EnrollmentManagementProps) {
    const { props } = usePage<{ branding?: Branding; flash?: { success?: string; error?: string; warning?: string } }>();
    const currency = props.branding?.currency || "PHP";

    const [workspacePrefs, setWorkspacePrefs] = useState<EnrollmentWorkspacePreferences>(() => loadWorkspacePrefs());
    const [configSheetOpen, setConfigSheetOpen] = useState(false);
    const [configSheetTab, setConfigSheetTab] = useState<EnrollmentConfigTab>("workspace");

    const openConfigSheet = useCallback((tab: EnrollmentConfigTab = "workspace") => {
        setConfigSheetTab(tab);
        setConfigSheetOpen(true);
    }, []);

    const handleWorkspacePrefsChange = useCallback((next: EnrollmentWorkspacePreferences) => {
        setWorkspacePrefs(next);
        if (typeof window !== "undefined") {
            window.localStorage.setItem(WORKSPACE_PREFS_STORAGE_KEY, JSON.stringify(next));
        }
    }, []);

    const handleResetWorkspacePrefs = useCallback(() => {
        setWorkspacePrefs(DEFAULT_WORKSPACE_PREFS);
        if (typeof window !== "undefined") {
            window.localStorage.removeItem(WORKSPACE_PREFS_STORAGE_KEY);
        }
        toast.success("Workspace preferences reset to defaults.");
    }, []);

    // Persistent Table / Board view switcher
    const [viewMode, setViewMode] = useState<ViewMode>(() => {
        if (typeof window === "undefined") return "table";
        const stored = window.localStorage.getItem(VIEW_MODE_STORAGE_KEY);
        if (stored === "board" || stored === "table") return stored;
        return loadWorkspacePrefs().defaultView;
    });

    const handleViewModeChange = useCallback((nextMode: ViewMode) => {
        setViewMode(nextMode);
        if (typeof window !== "undefined") {
            window.localStorage.setItem(VIEW_MODE_STORAGE_KEY, nextMode);
        }
    }, []);

    // Search, filters, sorting & loading
    const [enrollmentSearch, setEnrollmentSearch] = useState(filters.search || "");
    const searchInputRef = useRef<HTMLInputElement>(null);
    const searchInputFocusedRef = useRef(false);
    const searchDraftRef = useRef(filters.search || "");
    const debouncedSearchRef = useRef<ReturnType<typeof setTimeout> | null>(null);

    const [activeFilters, setActiveFilters] = useState<FilterType[]>(() => createInitialEnrollmentFilters(filters));
    const [sortOption, setSortOption] = useState(`${filters.sort ?? "created_at"}:${filters.direction ?? "desc"}`);
    const [isNavigating, setIsNavigating] = useState(false);
    const [isBulkBusy, setIsBulkBusy] = useState(false);

    // Slide-over inspection sheet state
    const [inspectedEnrollmentId, setInspectedEnrollmentId] = useState<number | null>(null);
    const [sheetOpenCashier, setSheetOpenCashier] = useState(false);
    const [sheetTransitionKey, setSheetTransitionKey] = useState<string | null>(null);

    // Dialog states
    const [deleteEnrollment, setDeleteEnrollment] = useState<EnrollmentRow | null>(null);
    const [forceDeleteEnrollment, setForceDeleteEnrollment] = useState<EnrollmentRow | null>(null);
    const [restoreEnrollment, setRestoreEnrollment] = useState<EnrollmentRow | null>(null);
    const [isEnrollmentDeleting, setIsEnrollmentDeleting] = useState(false);

    // Bulk export assessments PDF dialog state
    const [bulkReportOpen, setBulkReportOpen] = useState(false);
    const [isBulkReportGenerating, setIsBulkReportGenerating] = useState(false);
    const [bulkReportFilters, setBulkReportFilters] = useState<BulkReportFilters>({
        course_id: null,
        year_level: null,
        student_limit: null,
        include_deleted: false,
    });

    // Surface Inertia flash notifications cleanly
    useEffect(() => {
        if (props.flash?.success) toast.success(props.flash.success);
        if (props.flash?.error) toast.error(props.flash.error);
        if (props.flash?.warning) toast.warning(props.flash.warning);
    }, [props.flash]);

    // Keyboard shortcuts: '/' or Cmd/Ctrl+K focuses search, 'b' toggles Board/Table
    useEffect(() => {
        const handleKeyDown = (event: KeyboardEvent) => {
            const target = event.target as HTMLElement | null;
            const isTyping =
                target?.tagName === "INPUT" || target?.tagName === "TEXTAREA" || target?.tagName === "SELECT" || target?.isContentEditable;
            if (isTyping) return;

            if (event.key === "/" || ((event.metaKey || event.ctrlKey) && event.key.toLowerCase() === "k")) {
                event.preventDefault();
                searchInputRef.current?.focus();
            } else if (event.key.toLowerCase() === "b" && !event.metaKey && !event.ctrlKey && !event.altKey && inspectedEnrollmentId === null) {
                event.preventDefault();
                handleViewModeChange(viewMode === "table" ? "board" : "table");
            }
        };

        window.addEventListener("keydown", handleKeyDown);
        return () => window.removeEventListener("keydown", handleKeyDown);
    }, [handleViewModeChange, viewMode, inspectedEnrollmentId]);

    const cancelPendingSearch = () => {
        if (debouncedSearchRef.current) {
            clearTimeout(debouncedSearchRef.current);
            debouncedSearchRef.current = null;
        }
    };

    useEffect(() => () => cancelPendingSearch(), []);

    useEffect(() => {
        const serverSearch = filters.search || "";
        if (!searchInputFocusedRef.current && debouncedSearchRef.current === null) {
            setEnrollmentSearch(serverSearch);
            searchDraftRef.current = serverSearch;
        } else if (searchDraftRef.current === serverSearch) {
            setEnrollmentSearch(serverSearch);
        }

        setActiveFilters(createInitialEnrollmentFilters(filters));

        if (filters.sort) {
            setSortOption(`${filters.sort}:${filters.direction ?? "desc"}`);
        }
    }, [filters]);

    const buildQueryParams = useCallback(
        (
            overrides: {
                search?: string;
                filters?: FilterType[];
                sort?: string;
                direction?: "asc" | "desc";
                page?: number;
                per_page?: string | number;
            } = {},
        ) => {
            const nextFilters = overrides.filters ?? activeFilters;
            const filterMap = Object.fromEntries(
                nextFilters
                    .map((filter) => [filter.field, filter.values[0]] as const)
                    .filter((entry): entry is readonly [string, string | number] => typeof entry[1] === "string" || typeof entry[1] === "number")
                    .map(([field, value]) => [field, String(value)]),
            );

            const activeSearch = overrides.search !== undefined ? overrides.search : enrollmentSearch;
            const activeSort = overrides.sort !== undefined ? overrides.sort : (filters.sort ?? "created_at");
            const activeDirection = overrides.direction !== undefined ? overrides.direction : (filters.direction ?? "desc");
            const activePerPage = overrides.per_page !== undefined ? overrides.per_page : (filters.per_page ?? 20);
            const activePage = overrides.page !== undefined ? overrides.page : (enrollments?.current_page ?? 1);

            const params: Record<string, string | number | null> = {
                search: activeSearch.trim() ? activeSearch.trim() : null,
                sort: activeSort,
                direction: activeDirection,
                per_page: activePerPage,
                page: activePage,
            };

            if (filterMap.status_filter && filterMap.status_filter !== "all") params.status_filter = filterMap.status_filter;
            if (filterMap.department_filter && filterMap.department_filter !== "all") params.department_filter = filterMap.department_filter;
            if (filterMap.year_level_filter && filterMap.year_level_filter !== "all") params.year_level_filter = filterMap.year_level_filter;
            if (filterMap.course_filter && filterMap.course_filter !== "all") params.course_filter = filterMap.course_filter;
            if (filterMap.payment_filter && filterMap.payment_filter !== "all") params.payment_filter = filterMap.payment_filter;

            return params;
        },
        [activeFilters, enrollmentSearch, filters.sort, filters.direction, filters.per_page, enrollments?.current_page],
    );

    const navigateWithParams = useCallback((params: Record<string, string | number | null>) => {
        cancelPendingSearch();
        router.get(route("administrators.enrollments.index"), params, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
            only: ["enrollments", "board_enrollments", "filters"],
            onStart: () => setIsNavigating(true),
            onFinish: () => setIsNavigating(false),
        });
    }, []);

    const reloadWorkspace = useCallback(() => {
        router.reload({
            only: ["enrollments", "board_enrollments", "analytics", "filters", "applicantsCount"],
        });
    }, []);

    const handleEnrollmentSearchChange = (value: string) => {
        searchDraftRef.current = value;
        setEnrollmentSearch(value);
        cancelPendingSearch();
        debouncedSearchRef.current = setTimeout(() => {
            debouncedSearchRef.current = null;
            navigateWithParams(buildQueryParams({ search: value, page: 1 }));
        }, 280);
    };

    const handleSingleFilterChange = (field: string, value: string) => {
        const nextFilters = upsertSingleFilter(activeFilters, field, value);
        setActiveFilters(nextFilters);
        navigateWithParams(buildQueryParams({ filters: nextFilters, page: 1 }));
    };

    const handleFiltersChange = (nextFilters: FilterType[]) => {
        setActiveFilters(nextFilters);
        navigateWithParams(buildQueryParams({ filters: nextFilters, page: 1 }));
    };

    const handleSortChange = (newSortOption: string) => {
        setSortOption(newSortOption);
        const [sort = "created_at", direction = "desc"] = newSortOption.split(":");
        navigateWithParams(buildQueryParams({ sort, direction: direction as "asc" | "desc", page: 1 }));
    };

    const clearFilters = () => {
        searchDraftRef.current = "";
        setEnrollmentSearch("");
        setActiveFilters([]);
        setSortOption("created_at:desc");
        cancelPendingSearch();
        navigateWithParams(buildQueryParams({ search: "", filters: [], sort: "created_at", direction: "desc", page: 1 }));
    };

    const statusFilter = getActiveFilterValue(activeFilters, "status_filter");
    const departmentFilter = getActiveFilterValue(activeFilters, "department_filter");
    const paymentFilter = getActiveFilterValue(activeFilters, "payment_filter");
    const hasActiveFilters = enrollmentSearch.trim() !== "" || activeFilters.length > 0 || sortOption !== "created_at:desc";

    const enrollmentsData = useMemo(() => (Array.isArray(enrollments?.data) ? enrollments.data : []), [enrollments?.data]);
    const boardData = useMemo(() => (Array.isArray(board_enrollments) ? board_enrollments : enrollmentsData), [board_enrollments, enrollmentsData]);

    const activeRowCollection = viewMode === "board" ? boardData : enrollmentsData;

    const inspectedEnrollment = useMemo(() => {
        if (inspectedEnrollmentId === null) return null;
        return enrollmentsData.find((row) => row.id === inspectedEnrollmentId) ?? boardData.find((row) => row.id === inspectedEnrollmentId) ?? null;
    }, [inspectedEnrollmentId, enrollmentsData, boardData]);

    const inspectedIndex = useMemo(() => {
        if (inspectedEnrollmentId === null) return -1;
        return activeRowCollection.findIndex((row) => row.id === inspectedEnrollmentId);
    }, [inspectedEnrollmentId, activeRowCollection]);

    const handleInspect = useCallback((enrollment: EnrollmentRow, options?: { openCashier?: boolean; transitionKey?: string }) => {
        setSheetOpenCashier(Boolean(options?.openCashier));
        setSheetTransitionKey(options?.transitionKey ?? null);
        setInspectedEnrollmentId(enrollment.id);
    }, []);

    // Dynamic department chips from real database analytics (fixes hardcoded IT/HM/BA/TESDA)
    const departmentChips = useMemo(() => {
        const entries = (analytics?.by_department ?? [])
            .filter((item) => item.department && item.department.trim() !== "")
            .map((item) => ({
                value: item.department.trim(),
                label: item.department.trim(),
                count: Number(item.count),
            }))
            .sort((a, b) => b.count - a.count || a.label.localeCompare(b.label));

        const total = entries.reduce((sum, item) => sum + item.count, 0);

        return [{ value: "all", label: "All Departments", count: total }, ...entries];
    }, [analytics?.by_department]);

    const enrollmentCourseOptions = useMemo(() => {
        if (Array.isArray(available_courses) && available_courses.length > 0) {
            return available_courses;
        }

        const courses = new Map<number, { id: number; code: string; title: string | null }>();
        for (const enrollment of enrollmentsData) {
            if (enrollment.course_id === null || !enrollment.course) continue;
            courses.set(enrollment.course_id, {
                id: enrollment.course_id,
                code: enrollment.course,
                title: enrollment.course_title,
            });
        }

        return [...courses.values()].sort((left, right) => left.code.localeCompare(right.code));
    }, [available_courses, enrollmentsData]);

    const filterFields: FilterFieldConfig[] = useMemo(
        () => [
            {
                key: "status_filter",
                label: "Stage",
                type: "select",
                icon: <Users className="h-4 w-4" />,
                options: [
                    { value: "active", label: "Active records", icon: <Users className="text-muted-foreground h-4 w-4" /> },
                    { value: "trashed", label: "Deleted records", icon: <Users className="text-muted-foreground h-4 w-4" /> },
                    { value: "cancelled", label: "Cancelled", icon: <Ban className="text-muted-foreground h-4 w-4" /> },
                    ...enrollment_pipeline.status_options.map((option) => ({
                        value: option.value,
                        label: option.label,
                        icon: <Users className="text-muted-foreground h-4 w-4" />,
                    })),
                ],
            },
            {
                key: "payment_filter",
                label: "Payment",
                type: "select",
                icon: <CircleDollarSign className="h-4 w-4" />,
                options: [
                    { value: "unpaid", label: "Unpaid balance", icon: <CircleDollarSign className="h-4 w-4 text-amber-500" /> },
                    { value: "paid", label: "Paid in full", icon: <CircleDollarSign className="h-4 w-4 text-emerald-500" /> },
                ],
            },
            {
                key: "department_filter",
                label: "Department",
                type: "select",
                icon: <Building2 className="h-4 w-4" />,
                options: departmentChips
                    .filter((department) => department.value !== "all")
                    .map((department) => ({
                        value: department.value,
                        label: `${department.label} (${department.count})`,
                        icon: <Building2 className="text-muted-foreground h-4 w-4" />,
                    })),
            },
            {
                key: "course_filter",
                label: "Course",
                type: "select",
                searchable: true,
                icon: <BookOpen className="h-4 w-4" />,
                options: enrollmentCourseOptions.map((course) => ({
                    value: String(course.id),
                    label: course.title ? `${course.code} — ${course.title}` : course.code,
                    icon: <BookOpen className="text-muted-foreground h-4 w-4" />,
                })),
            },
            {
                key: "year_level_filter",
                label: "Year Level",
                type: "select",
                icon: <GraduationCap className="h-4 w-4" />,
                options: [1, 2, 3, 4].map((yearLevel) => ({
                    value: String(yearLevel),
                    label: `Year ${yearLevel}`,
                    icon: <GraduationCap className="text-muted-foreground h-4 w-4" />,
                })),
            },
        ],
        [departmentChips, enrollmentCourseOptions, enrollment_pipeline.status_options],
    );

    // Inline row/card workflow actions (supports both Dynamic Policy v1 & Legacy sequential pipeline)
    const handleQuickAdvance = useCallback(
        (enrollment: EnrollmentRow, transition?: EnrollmentTransitionOption) => {
            if (enrollment.workflow_runtime === "policy_v1") {
                const chosen = transition ?? enrollment.allowed_transitions?.[0];
                if (!chosen) return;

                if (chosen.needs_payment || chosen.requires_reason) {
                    handleInspect(enrollment, { openCashier: chosen.needs_payment, transitionKey: chosen.key });
                    return;
                }

                const idempotencyKey =
                    typeof crypto !== "undefined" && typeof crypto.randomUUID === "function"
                        ? `row-tr-${enrollment.id}-${crypto.randomUUID()}`
                        : `row-tr-${enrollment.id}-${Date.now()}`;

                void axios
                    .post(storeTransitionRoute.url(enrollment.id), {
                        transition_key: chosen.key,
                        payload: {},
                        idempotency_key: idempotencyKey,
                    })
                    .then(() => {
                        toast.success(`Moved to ${chosen.to_status}.`);
                        reloadWorkspace();
                    })
                    .catch((err: unknown) => {
                        const msg =
                            axios.isAxiosError(err) && typeof err.response?.data?.message === "string"
                                ? err.response.data.message
                                : "Unable to execute workflow transition.";
                        toast.error(msg);
                    });
                return;
            }

            const nextStep = enrollment.next_step;
            if (!nextStep) return;

            if (nextStep.action_type === "cashier_verification") {
                handleInspect(enrollment, { openCashier: true });
                return;
            }

            const routeName =
                nextStep.action_type === "department_verification"
                    ? "administrators.enrollments.verify-head-dept"
                    : "administrators.enrollments.advance-pipeline-step";

            router.post(
                route(routeName, enrollment.id, false),
                {},
                {
                    preserveScroll: true,
                    preserveState: true,
                    onSuccess: () => {
                        reloadWorkspace();
                    },
                    onError: () => {
                        toast.error("Unable to advance enrollment stage.");
                    },
                },
            );
        },
        [handleInspect, reloadWorkspace],
    );

    const handleResendAssessment = useCallback((enrollment: EnrollmentRow) => {
        router.post(
            route("administrators.enrollments.resend-assessment", enrollment.id),
            {},
            {
                preserveScroll: true,
                preserveState: true,
                onError: () => toast.error("Failed to queue assessment notification."),
            },
        );
    }, []);

    const handleBulkAction = useCallback(
        (action: "advance" | "resend_assessment" | "delete" | "restore", rows: EnrollmentRow[]) => {
            if (rows.length === 0) return;

            setIsBulkBusy(true);
            router.post(
                enrollmentBulkActionRoute.url(),
                {
                    action,
                    ids: rows.map((row) => row.id),
                },
                {
                    preserveScroll: true,
                    preserveState: true,
                    onSuccess: () => {
                        reloadWorkspace();
                    },
                    onError: () => {
                        toast.error("Bulk operation failed. Please try again.");
                    },
                    onFinish: () => {
                        setIsBulkBusy(false);
                    },
                },
            );
        },
        [reloadWorkspace],
    );

    const handleDeleteEnrollment = () => {
        if (!deleteEnrollment) return;

        setIsEnrollmentDeleting(true);
        router.delete(route("administrators.enrollments.destroy", { enrollment: deleteEnrollment.id }), {
            preserveScroll: true,
            onSuccess: () => {
                setDeleteEnrollment(null);
                if (inspectedEnrollmentId === deleteEnrollment.id) {
                    setInspectedEnrollmentId(null);
                }
                reloadWorkspace();
            },
            onError: () => toast.error("Failed to delete enrollment."),
            onFinish: () => setIsEnrollmentDeleting(false),
        });
    };

    const handleForceDeleteEnrollment = () => {
        if (!forceDeleteEnrollment) return;

        setIsEnrollmentDeleting(true);
        router.delete(route("administrators.enrollments.force-destroy", { enrollment: forceDeleteEnrollment.id }), {
            preserveScroll: true,
            onSuccess: () => {
                setForceDeleteEnrollment(null);
                if (inspectedEnrollmentId === forceDeleteEnrollment.id) {
                    setInspectedEnrollmentId(null);
                }
                reloadWorkspace();
            },
            onError: () => toast.error("Failed to permanently delete enrollment."),
            onFinish: () => setIsEnrollmentDeleting(false),
        });
    };

    const handleRestoreEnrollment = () => {
        if (!restoreEnrollment) return;

        setIsEnrollmentDeleting(true);
        router.post(
            route("administrators.enrollments.restore", { enrollment: restoreEnrollment.id }),
            {},
            {
                preserveScroll: true,
                onSuccess: () => {
                    setRestoreEnrollment(null);
                    reloadWorkspace();
                },
                onError: () => toast.error("Failed to restore enrollment."),
                onFinish: () => setIsEnrollmentDeleting(false),
            },
        );
    };

    const handleGenerateBulkReports = async () => {
        setIsBulkReportGenerating(true);
        try {
            await axios.post(route("administrators.enrollments.reports.bulk-assessments", undefined, false), bulkReportFilters);
            toast.success("Bulk assessment PDF generation queued. You will be notified when ready.");
            setBulkReportOpen(false);
        } catch {
            toast.error("Could not queue bulk assessment export.");
        } finally {
            setIsBulkReportGenerating(false);
        }
    };

    const activeTermBadge = `${semesterLabel(filters.currentSemester)} · ${
        filters.currentSchoolYear ? `${filters.currentSchoolYear}–${filters.currentSchoolYear + 1}` : "Current SY"
    }`;

    const [currentSort = "created_at", currentDirection = "desc"] = sortOption.split(":");

    const pipelineConfigureUrl = pipeline_meta?.configure_url ?? "/administrators/system-management/enrollment-pipeline";

    return (
        <AdminLayout user={user} title="Enrollment Records">
            <Head title="Administrators • Enrollment Records" />

            <div className="space-y-4 pb-10">
                {/* Compact Minimalist Page Header */}
                <header className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div className="flex flex-wrap items-center gap-2">
                        <h1 className="text-2xl font-semibold tracking-[-0.025em]">Enrollments</h1>
                        <Badge variant="outline" radius="full" size="lg" className="text-muted-foreground font-normal">
                            {activeTermBadge}
                        </Badge>
                        {!workflow_setup_required && pipeline_meta && (
                            <button
                                type="button"
                                onClick={() => openConfigSheet("pipeline")}
                                className="focus-visible:ring-ring inline-flex items-center rounded-full hover:opacity-90 focus-visible:ring-2 focus-visible:outline-none"
                                title="Inspect or configure active enrollment pipeline"
                            >
                                <Badge
                                    variant={pipeline_meta.engine === "policy_v1" ? "info-light" : "secondary"}
                                    radius="full"
                                    size="lg"
                                    className="gap-1.5 font-normal"
                                >
                                    <Workflow className="size-3" aria-hidden="true" />
                                    {pipeline_meta.engine === "policy_v1"
                                        ? `${pipeline_meta.policy_name ?? "Policy"}${pipeline_meta.policy_version ? ` · v${pipeline_meta.policy_version}` : ""}`
                                        : `Pipeline · ${enrollment_pipeline.steps.length} stages`}
                                </Badge>
                            </button>
                        )}
                        {enrollment_period && (
                            <button
                                type="button"
                                onClick={() => openConfigSheet("window")}
                                className="focus-visible:ring-ring inline-flex items-center rounded-full hover:opacity-90 focus-visible:ring-2 focus-visible:outline-none"
                                title={enrollment_period.summary_text}
                            >
                                <Badge
                                    variant={
                                        enrollment_period.tone === "success"
                                            ? "success-light"
                                            : enrollment_period.tone === "warning"
                                              ? "warning-light"
                                              : enrollment_period.tone === "destructive"
                                                ? "destructive-light"
                                                : enrollment_period.tone === "info"
                                                  ? "info-light"
                                                  : "outline"
                                    }
                                    radius="full"
                                    size="lg"
                                    className="gap-1.5 font-normal"
                                >
                                    {enrollment_period.phase === "regular" ? (
                                        <CalendarCheck2 className="size-3" aria-hidden="true" />
                                    ) : enrollment_period.phase === "late" ? (
                                        <Clock className="size-3" aria-hidden="true" />
                                    ) : enrollment_period.phase === "closed" ? (
                                        <Lock className="size-3" aria-hidden="true" />
                                    ) : (
                                        <CalendarClock className="size-3" aria-hidden="true" />
                                    )}
                                    {enrollment_period.configured ? enrollment_period.phase_label : "Set window"}
                                </Badge>
                            </button>
                        )}
                    </div>

                    {workflow_setup_required ? (
                        <div className="flex flex-wrap items-center gap-2">
                            {pipeline_meta?.can_configure && (
                                <Button variant="outline" size="sm" className="h-8 gap-1.5" onClick={() => setConfigSheetOpen(true)}>
                                    <SlidersHorizontal className="size-3.5" aria-hidden="true" />
                                    Quick setup
                                </Button>
                            )}
                            <Link href={pipelineConfigureUrl} className={buttonVariants({ size: "sm", className: "h-8 gap-1.5" })}>
                                <Settings2 className="size-3.5" aria-hidden="true" />
                                Configure pipeline
                            </Link>
                        </div>
                    ) : (
                        <div className="flex flex-wrap items-center gap-2">
                            {/* View Switcher: Pipeline Table | Workflow Board */}
                            <div
                                role="group"
                                aria-label="Enrollment view mode"
                                className="border-border/80 bg-muted/50 inline-flex items-center rounded-lg border p-0.5"
                            >
                                <button
                                    type="button"
                                    onClick={() => handleViewModeChange("table")}
                                    className={cn(
                                        "inline-flex items-center gap-1.5 rounded-md px-2.5 py-1 text-xs font-medium transition-colors",
                                        viewMode === "table"
                                            ? "bg-background text-foreground shadow-xs"
                                            : "text-muted-foreground hover:text-foreground",
                                    )}
                                    aria-pressed={viewMode === "table"}
                                >
                                    <Table2 className="size-3.5" aria-hidden="true" />
                                    Pipeline
                                </button>
                                <button
                                    type="button"
                                    onClick={() => handleViewModeChange("board")}
                                    className={cn(
                                        "inline-flex items-center gap-1.5 rounded-md px-2.5 py-1 text-xs font-medium transition-colors",
                                        viewMode === "board"
                                            ? "bg-background text-foreground shadow-xs"
                                            : "text-muted-foreground hover:text-foreground",
                                    )}
                                    aria-pressed={viewMode === "board"}
                                    title="Switch to Workflow Board (B)"
                                >
                                    <KanbanSquare className="size-3.5" aria-hidden="true" />
                                    Board
                                </button>
                            </div>

                            {/* Workspace & Pipeline Config Sheet Trigger */}
                            <Button variant="outline" size="sm" className="h-8 gap-1.5" onClick={() => setConfigSheetOpen(true)}>
                                <SlidersHorizontal className="size-3.5" aria-hidden="true" />
                                <span className="hidden sm:inline">Configure</span>
                            </Button>

                            {/* Secondary tools dropdown */}
                            <DropdownMenu>
                                <DropdownMenuTrigger asChild>
                                    <Button variant="outline" size="sm" className="h-8 gap-1.5">
                                        <MoreHorizontal className="size-4" aria-hidden="true" />
                                        <span className="hidden sm:inline">Tools</span>
                                    </Button>
                                </DropdownMenuTrigger>
                                <DropdownMenuContent align="end" className="w-60">
                                    <DropdownMenuLabel>Enrollment tools</DropdownMenuLabel>
                                    <DropdownMenuItem
                                        onClick={() => {
                                            exportEnrollmentsToCsv(activeRowCollection, `enrollments-${new Date().toISOString().slice(0, 10)}.csv`);
                                            toast.success(`Exported ${activeRowCollection.length} records to CSV.`);
                                        }}
                                    >
                                        <FileSpreadsheet className="mr-2 size-4" />
                                        Export current view (CSV)
                                    </DropdownMenuItem>
                                    <DropdownMenuItem onClick={() => setBulkReportOpen(true)}>
                                        <Download className="mr-2 size-4" />
                                        Bulk export assessments (PDF)
                                    </DropdownMenuItem>
                                    <DropdownMenuSeparator />
                                    <DropdownMenuItem onClick={() => setConfigSheetOpen(true)}>
                                        <SlidersHorizontal className="mr-2 size-4" />
                                        Workspace &amp; pipeline config
                                    </DropdownMenuItem>
                                    <DropdownMenuItem asChild>
                                        <Link href={pipelineConfigureUrl} className="flex items-center">
                                            <Workflow className="mr-2 size-4" />
                                            Pipeline Blueprint Studio
                                        </Link>
                                    </DropdownMenuItem>
                                    <DropdownMenuSeparator />
                                    <DropdownMenuItem asChild>
                                        <a
                                            href={filament.student_enrollments.index_url}
                                            target="_blank"
                                            rel="noreferrer"
                                            className="flex items-center"
                                        >
                                            <ArrowUpRight className="mr-2 size-4" />
                                            Advanced Filament table
                                        </a>
                                    </DropdownMenuItem>
                                </DropdownMenuContent>
                            </DropdownMenu>

                            <Link
                                href={route("administrators.enrollments.create")}
                                className={buttonVariants({ size: "sm", className: "h-8 gap-1.5" })}
                            >
                                <UserPlus className="size-3.5" aria-hidden="true" />
                                New enrollment
                            </Link>
                        </div>
                    )}
                </header>

                {workflow_setup_required ? (
                    <Frame spacing="sm">
                        <FramePanel>
                            <Alert variant="warning" className="border-0 bg-transparent p-0 shadow-none">
                                <AlertTriangle className="size-4" />
                                <AlertTitle>Enrollment workflow setup required</AlertTitle>
                                <AlertDescription className="mt-1 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                    <span>
                                        Define at least one pipeline stage (or publish an Enrollment Policy) and assign responsible roles before
                                        managing term enrollments.
                                    </span>
                                    <div className="flex shrink-0 flex-wrap items-center gap-2">
                                        {pipeline_meta?.can_configure && (
                                            <Button variant="outline" size="sm" className="gap-1.5" onClick={() => setConfigSheetOpen(true)}>
                                                <SlidersHorizontal className="size-3.5" aria-hidden="true" />
                                                Quick setup here
                                            </Button>
                                        )}
                                        <Link
                                            href={pipelineConfigureUrl}
                                            className={buttonVariants({ variant: "outline", size: "sm", className: "shrink-0 gap-1.5" })}
                                        >
                                            <Settings2 className="size-3.5" aria-hidden="true" />
                                            Open Blueprint Studio
                                        </Link>
                                    </div>
                                </AlertDescription>
                            </Alert>
                        </FramePanel>
                    </Frame>
                ) : (
                    <>
                        {/* 1. Single Interactive Term Pipeline Ribbon */}
                        {workspacePrefs.showPipelineStrip && (
                            <EnrollmentPipelineStrip
                                analytics={analytics}
                                pipeline={enrollment_pipeline}
                                applicantsCount={applicantsCount}
                                activeStatusFilter={statusFilter}
                                activePaymentFilter={paymentFilter}
                                currency={currency}
                                onStatusSelect={(nextStatus) => {
                                    handleSingleFilterChange("status_filter", nextStatus);
                                    if (viewMode === "board" && (nextStatus === "trashed" || nextStatus === "cancelled")) {
                                        handleViewModeChange("table");
                                    }
                                }}
                                onPaymentToggle={(mode) => handleSingleFilterChange("payment_filter", mode)}
                            />
                        )}

                        {/* 2. Unified Workspace Controls (Dynamic Departments + Search + ReUI Filters) */}
                        <Frame spacing="xs" className="w-full">
                            <FramePanel className="space-y-3 p-3">
                                {/* Dynamic DB-backed Department Scope Chips */}
                                {workspacePrefs.showDepartmentChips && departmentChips.length > 1 && (
                                    <div className="flex items-center gap-1.5 overflow-x-auto pb-0.5">
                                        {departmentChips.map((dept) => {
                                            const active = departmentFilter === dept.value;
                                            return (
                                                <button
                                                    key={dept.value}
                                                    type="button"
                                                    onClick={() => handleSingleFilterChange("department_filter", dept.value)}
                                                    className={cn(
                                                        "inline-flex shrink-0 items-center gap-1.5 rounded-md border px-2.5 py-1 text-xs font-medium transition-colors",
                                                        active
                                                            ? "border-foreground/20 bg-foreground text-background"
                                                            : "border-border/70 bg-background/60 text-muted-foreground hover:text-foreground hover:bg-muted/60",
                                                    )}
                                                >
                                                    <span>{dept.label}</span>
                                                    <span
                                                        className={cn(
                                                            "text-[10.5px] tabular-nums",
                                                            active ? "text-background/75" : "text-muted-foreground",
                                                        )}
                                                    >
                                                        {dept.count}
                                                    </span>
                                                </button>
                                            );
                                        })}
                                    </div>
                                )}

                                {/* Search, Sort & ReUI Filters Toolbar */}
                                <div className="flex flex-col gap-2 lg:flex-row lg:items-center lg:justify-between">
                                    <div className="relative min-w-0 flex-1">
                                        <Search
                                            className="text-muted-foreground pointer-events-none absolute top-1/2 left-2.5 size-3.5 -translate-y-1/2"
                                            aria-hidden="true"
                                        />
                                        <Input
                                            ref={searchInputRef}
                                            placeholder="Search student, ID, course, or stage..."
                                            className="border-border/70 bg-background/80 h-8 pr-10 pl-8 text-xs shadow-none"
                                            value={enrollmentSearch}
                                            onFocus={() => {
                                                searchInputFocusedRef.current = true;
                                            }}
                                            onBlur={() => {
                                                searchInputFocusedRef.current = false;
                                            }}
                                            onChange={(e) => handleEnrollmentSearchChange(e.target.value)}
                                        />
                                        <kbd className="border-border text-muted-foreground pointer-events-none absolute top-1/2 right-2 hidden -translate-y-1/2 rounded border px-1.5 py-0.5 font-mono text-[10px] sm:inline-block">
                                            /
                                        </kbd>
                                    </div>

                                    <div className="flex flex-wrap items-center gap-2">
                                        {viewMode === "table" && (
                                            <Select value={sortOption} onValueChange={handleSortChange}>
                                                <SelectTrigger className="border-border/70 bg-background/80 h-8 w-[160px] text-xs">
                                                    <SelectValue placeholder="Sort enrollments" />
                                                </SelectTrigger>
                                                <SelectContent align="end">
                                                    <SelectItem value="created_at:desc">Latest enrolled</SelectItem>
                                                    <SelectItem value="created_at:asc">Oldest enrolled</SelectItem>
                                                    <SelectItem value="student_name:asc">Student A–Z</SelectItem>
                                                    <SelectItem value="student_name:desc">Student Z–A</SelectItem>
                                                    <SelectItem value="tuition:desc">Highest tuition</SelectItem>
                                                    <SelectItem value="tuition:asc">Lowest tuition</SelectItem>
                                                </SelectContent>
                                            </Select>
                                        )}

                                        <div className="flex items-center gap-1.5">
                                            <ListFilter className="text-muted-foreground hidden size-3.5 sm:block" aria-hidden="true" />
                                            <Filters
                                                fields={filterFields}
                                                filters={activeFilters}
                                                onChange={handleFiltersChange}
                                                trigger={
                                                    <Button variant="outline" size="sm" className="h-8 gap-1.5 text-xs">
                                                        <Filter className="size-3.5" aria-hidden="true" />
                                                        Filters
                                                        {activeFilters.length > 0 && (
                                                            <Badge variant="secondary" size="xs" radius="full" className="ml-0.5 tabular-nums">
                                                                {activeFilters.length}
                                                            </Badge>
                                                        )}
                                                    </Button>
                                                }
                                            />
                                        </div>

                                        {hasActiveFilters && (
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="sm"
                                                onClick={clearFilters}
                                                className="text-muted-foreground hover:text-foreground h-8 gap-1 px-2 text-xs"
                                            >
                                                <RotateCcw className="size-3.5" aria-hidden="true" />
                                                Reset
                                            </Button>
                                        )}
                                    </div>
                                </div>
                            </FramePanel>
                        </Frame>

                        {/* 3. Main View: Pipeline Directory (ReUI DataGrid) OR Workflow Board (ReUI Kanban) */}
                        {viewMode === "board" ? (
                            <EnrollmentKanbanBoard
                                enrollments={boardData}
                                pipeline={enrollment_pipeline}
                                currency={currency}
                                preferences={workspacePrefs}
                                onInspect={handleInspect}
                                onQuickAdvance={handleQuickAdvance}
                                onSwitchToTableForStatus={(status) => {
                                    handleSingleFilterChange("status_filter", status);
                                    handleViewModeChange("table");
                                }}
                            />
                        ) : (
                            <EnrollmentDataGrid
                                data={enrollmentsData}
                                pagination={enrollments}
                                pipeline={enrollment_pipeline}
                                currency={currency}
                                sort={currentSort}
                                direction={currentDirection === "asc" ? "asc" : "desc"}
                                isLoading={isNavigating}
                                bulkBusy={isBulkBusy}
                                preferences={workspacePrefs}
                                onInspect={handleInspect}
                                onQuickAdvance={handleQuickAdvance}
                                onResendAssessment={handleResendAssessment}
                                onDelete={setDeleteEnrollment}
                                onForceDelete={setForceDeleteEnrollment}
                                onRestore={setRestoreEnrollment}
                                onBulkAction={handleBulkAction}
                                onPaginationChange={(page, perPage) => navigateWithParams(buildQueryParams({ page, per_page: perPage }))}
                                onSortingChange={(nextSort, nextDirection) => {
                                    setSortOption(`${nextSort}:${nextDirection}`);
                                    navigateWithParams(buildQueryParams({ sort: nextSort, direction: nextDirection, page: 1 }));
                                }}
                            />
                        )}
                    </>
                )}
            </div>

            {/* Workspace & Pipeline Configurator Sheet */}
            <EnrollmentConfigSheet
                open={configSheetOpen}
                onOpenChange={setConfigSheetOpen}
                initialTab={configSheetTab}
                preferences={workspacePrefs}
                onPreferencesChange={handleWorkspacePrefsChange}
                onResetPreferences={handleResetWorkspacePrefs}
                pipeline={enrollment_pipeline}
                pipelineMeta={pipeline_meta}
                enrollmentPeriod={enrollment_period}
                currency={currency}
                onPipelineSaved={() => router.reload()}
            />

            {/* Slide-Over Enrollment Dossier Sheet */}
            {!workflow_setup_required && (
                <>
                    <EnrollmentDetailSheet
                        open={inspectedEnrollmentId !== null}
                        onOpenChange={(open) => {
                            if (!open) {
                                setInspectedEnrollmentId(null);
                                setSheetOpenCashier(false);
                                setSheetTransitionKey(null);
                            }
                        }}
                        enrollment={inspectedEnrollment}
                        pipeline={enrollment_pipeline}
                        currency={currency}
                        initialOpenCashier={sheetOpenCashier}
                        initialTransitionKey={sheetTransitionKey}
                        hasPrevious={inspectedIndex > 0}
                        hasNext={inspectedIndex >= 0 && inspectedIndex < activeRowCollection.length - 1}
                        onPrevious={() => {
                            if (inspectedIndex > 0) {
                                setSheetOpenCashier(false);
                                setSheetTransitionKey(null);
                                setInspectedEnrollmentId(activeRowCollection[inspectedIndex - 1].id);
                            }
                        }}
                        onNext={() => {
                            if (inspectedIndex >= 0 && inspectedIndex < activeRowCollection.length - 1) {
                                setSheetOpenCashier(false);
                                setSheetTransitionKey(null);
                                setInspectedEnrollmentId(activeRowCollection[inspectedIndex + 1].id);
                            }
                        }}
                        onDelete={setDeleteEnrollment}
                        onRestore={setRestoreEnrollment}
                        onMutationSuccess={reloadWorkspace}
                    />

                    <DeleteEnrollmentDialog
                        open={!!deleteEnrollment}
                        enrollment={deleteEnrollment}
                        isDeleting={isEnrollmentDeleting}
                        onOpenChange={(open) => !open && setDeleteEnrollment(null)}
                        onConfirm={handleDeleteEnrollment}
                    />
                    <ForceDeleteEnrollmentDialog
                        open={!!forceDeleteEnrollment}
                        enrollment={forceDeleteEnrollment}
                        isDeleting={isEnrollmentDeleting}
                        onOpenChange={(open) => !open && setForceDeleteEnrollment(null)}
                        onConfirm={handleForceDeleteEnrollment}
                    />
                    <RestoreEnrollmentDialog
                        open={!!restoreEnrollment}
                        enrollment={restoreEnrollment}
                        isDeleting={isEnrollmentDeleting}
                        onOpenChange={(open) => !open && setRestoreEnrollment(null)}
                        onConfirm={handleRestoreEnrollment}
                    />
                    <BulkReportsDialog
                        open={bulkReportOpen}
                        onOpenChange={setBulkReportOpen}
                        filters={bulkReportFilters}
                        onFiltersChange={setBulkReportFilters}
                        isGenerating={isBulkReportGenerating}
                        onGenerate={handleGenerateBulkReports}
                        courseOptions={enrollmentCourseOptions}
                        isLoadingCourses={false}
                        courseOptionsError={null}
                        onRetryCourses={() => undefined}
                        studentLimitOptions={assessment_export_options?.student_limits ?? [50, 100, 250]}
                    />
                </>
            )}
        </AdminLayout>
    );
}
