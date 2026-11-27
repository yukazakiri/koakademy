import AdminLayout from "@/components/administrators/admin-layout";
import { type FilterFieldConfig, type Filter as FilterType } from "@/components/reui/filters";
import { Badge } from "@/components/ui/badge";
import { Button, buttonVariants } from "@/components/ui/button";
import { Checkbox } from "@/components/ui/checkbox";
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from "@/components/ui/dialog";
import { Label } from "@/components/ui/label";
import { Input } from "@/components/ui/input";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Sheet, SheetContent, SheetDescription, SheetFooter, SheetHeader, SheetTitle } from "@/components/ui/sheet";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { cn } from "@/lib/utils";
import {
    index as classIndex,
    copy as copyClass,
    create as createClass,
    destroy as destroyClass,
    edit as editClass,
    forceDestroy as forceDestroyClass,
    restore as restoreClass,
    show as showClass,
} from "@/routes/administrators/classes";
import { index as schedulePlanner } from "@/routes/administrators/scheduling-analytics";
import type { User } from "@/types/user";
import { Head, Link, router, useRemember } from "@inertiajs/react";
import type { RowSelectionState } from "@tanstack/react-table";
import {
    AlertTriangle,
    BookOpen,
    Calendar,
    CalendarDays,
    CalendarIcon,
    Columns3,
    GitCompare,
    GraduationCap,
    Layers,
    LayoutGrid,
    List,
    MapPin,
    PanelRight,
    Pencil,
    Plus,
    Trash2,
    UserCheck,
    Users,
} from "lucide-react";
import * as React from "react";
import { toast } from "sonner";
import { ClassRow, type ClassCapabilities } from "./columns";
import { ClassCard } from "./components/class-card";
import { ClassCompareDialog } from "./components/class-compare-dialog";
import { semesterLabel } from "./components/class-list-details";
import { ClassListToolbar, type ClassWorkspaceFilters } from "./components/class-list-toolbar";
import type { ClassActionHandlers } from "./components/class-row-actions";
import { DeleteClassDialog } from "./components/delete-class-dialog";
import { EditClassDialog } from "./components/edit-class-dialog";
import { type PaginationMeta } from "./data-table";
import { DataTablePagination } from "./data-table-pagination";
import {
    deliveryModeLabel,
    isOnlineDeliveryMode,
    type ClassFormOptions,
    type ClassSchedule as ClassFormSchedule,
    type DeliveryMode,
    type DeliverySummary,
} from "./lib/class-defaults";
import { fetchClassWorkspace } from "./workspace/api";
import { DualModeClassStudio } from "./workspace/dual-mode-studio";
import { ExpandableClassLedger } from "./workspace/expandable-ledger";
import { MultiClassBulkDock } from "./workspace/multi-class-bulk-dock";
import { SectionRebalancerStudio } from "./workspace/section-studio";
import type { ClassWorkspacePayload, RoomOption } from "./workspace/types";

type SelectOption = { value: string; label: string };

type EntityOption = { id: string | number; label: string };

type ClassSchedule = ClassFormSchedule & {
    room?: EntityOption | null;
};

type SelectedClass = {
    id: number;
    record_title: string;
    classification: "college" | "shs" | string;
    delivery_mode?: DeliveryMode;
    delivery_summary?: DeliverySummary;
    subject_code: string;
    subject_title: string;
    section: string;
    school_year: string;
    semester: string | number;
    academic_year: number | null;
    grade_level: string | null;
    shs_track: EntityOption | null;
    shs_strand: EntityOption | null;
    faculty: (EntityOption & { email?: string | null }) | null;
    room: EntityOption | null;
    course_codes: string[];
    course_abbreviations: string[] | null;
    course_ids: number[];
    subject_ids: number[];
    subjects: { id: number; code: string; title: string }[];
    maximum_slots: number;
    students_count: number;
    settings: {
        background_color?: string;
        accent_color?: string;
        banner_image?: string | null;
        theme?: string;
        enable_announcements?: boolean;
        enable_grade_visibility?: boolean;
        enable_attendance_tracking?: boolean;
        enable_online_resources?: boolean;
        allow_late_submissions?: boolean;
        enable_discussion_board?: boolean;
        notify_students_on_schedule_changes?: boolean;
        custom?: Record<string, string>;
    };
    schedules: ClassSchedule[];
    enrollments: {
        id: number;
        student: {
            id: number;
            student_id: string | number | null;
            name: string;
            course: string | null;
            academic_year: string | number | null;
        } | null;
        status: string | null;
        prelim_grade: number | null;
        midterm_grade: number | null;
        finals_grade: number | null;
        total_average: number | null;
        remarks: string | null;
    }[];
    posts: {
        id: number;
        title: string;
        type: string;
        created_at: string | null;
    }[];
    filament: {
        view_url: string;
        edit_url: string;
    };
};

type ClassDialogTab = "details" | "schedule" | "settings";

export type ClassListPayload = {
    data: ClassRow[];
    meta: PaginationMeta & {
        total_students: number;
        total_capacity: number;
        queues: {
            unassigned_faculty: number;
            no_schedule: number;
            over_capacity: number;
        };
        classification_counts: {
            college: number;
            shs: number;
        };
    };
};

const DEFAULT_CAPABILITIES: ClassCapabilities = {
    create: false,
    update: false,
    delete: false,
    restore: false,
    force_delete: false,
};

export type ClassOptions = {
    classifications: SelectOption[];
    sections: SelectOption[];
    semesters: SelectOption[];
    grade_levels: SelectOption[];
    day_of_week: SelectOption[];
    courses: EntityOption[];
    faculties: EntityOption[];
    rooms: RoomOption[];
    shs_tracks: EntityOption[];
};

export type ClassDefaults = {
    semester: string;
    school_year: string;
};

interface ClassesIndexProps {
    user: User;
    filament: {
        classes: {
            index_url: string;
            create_url: string;
        };
    };
    classes?: ClassListPayload;
    capabilities?: ClassCapabilities;
    selected_class?: SelectedClass | null;
    filters?: {
        search?: string | null;
        classification?: string | null;
        course_id?: number | null;
        shs_track_id?: number | null;
        shs_strand_id?: number | null;
        subject_code?: string | null;
        room_id?: number | null;
        faculty_id?: string | null;
        academic_year?: number | null;
        grade_level?: string | null;
        semester?: string | null;
        available_slots?: boolean | null;
        fully_enrolled?: boolean | null;
        needs_faculty?: boolean | null;
        needs_schedule?: boolean | null;
        over_capacity?: boolean | null;
        trashed?: string | null;
        per_page?: number | string | null;
        sort?: string | null;
        direction?: string | null;
    };
    options?: Partial<ClassOptions>;
    defaults?: Partial<ClassDefaults>;
    flash?: { type: string; message: string } | null;
}

function createActiveFilters(filters?: ClassesIndexProps["filters"]): FilterType[] {
    const activeFilters: FilterType[] = [];

    if (!filters) {
        return activeFilters;
    }

    if (filters.course_id) activeFilters.push({ id: "course_id", field: "course_id", operator: "is", values: [filters.course_id] });
    if (filters.shs_track_id) activeFilters.push({ id: "shs_track_id", field: "shs_track_id", operator: "is", values: [filters.shs_track_id] });
    if (filters.room_id) activeFilters.push({ id: "room_id", field: "room_id", operator: "is", values: [filters.room_id] });
    if (filters.faculty_id) activeFilters.push({ id: "faculty_id", field: "faculty_id", operator: "is", values: [filters.faculty_id] });
    if (filters.academic_year) {
        activeFilters.push({ id: "academic_year", field: "academic_year", operator: "is", values: [filters.academic_year] });
    }
    if (filters.grade_level) activeFilters.push({ id: "grade_level", field: "grade_level", operator: "is", values: [filters.grade_level] });
    if (filters.semester) activeFilters.push({ id: "semester", field: "semester", operator: "is", values: [filters.semester] });
    if (filters.available_slots) activeFilters.push({ id: "available_slots", field: "available_slots", operator: "is", values: ["true"] });
    if (filters.fully_enrolled !== null && filters.fully_enrolled !== undefined) {
        activeFilters.push({
            id: "fully_enrolled",
            field: "fully_enrolled",
            operator: "is",
            values: [filters.fully_enrolled ? "true" : "false"],
        });
    }

    return activeFilters;
}

const DEFAULT_OPTIONS: ClassOptions = {
    classifications: [
        { value: "all", label: "All" },
        { value: "college", label: "College" },
        { value: "shs", label: "SHS" },
    ],
    sections: [
        { value: "A", label: "Section A" },
        { value: "B", label: "Section B" },
        { value: "C", label: "Section C" },
        { value: "D", label: "Section D" },
    ],
    semesters: [
        { value: "1", label: "1st Semester" },
        { value: "2", label: "2nd Semester" },
        { value: "summer", label: "Summer" },
    ],
    grade_levels: [
        { value: "Grade 11", label: "Grade 11" },
        { value: "Grade 12", label: "Grade 12" },
    ],
    day_of_week: [
        { value: "Monday", label: "Monday" },
        { value: "Tuesday", label: "Tuesday" },
        { value: "Wednesday", label: "Wednesday" },
        { value: "Thursday", label: "Thursday" },
        { value: "Friday", label: "Friday" },
        { value: "Saturday", label: "Saturday" },
        { value: "Sunday", label: "Sunday" },
    ],
    courses: [],
    faculties: [],
    rooms: [],
    shs_tracks: [],
};

const DEFAULT_DEFAULTS: ClassDefaults = {
    semester: "1",
    school_year: "",
};

export default function AdministratorClassesIndex({
    user,
    classes,
    capabilities: rawCapabilities,
    selected_class,
    filters = {},
    options: rawOptions,
    defaults: rawDefaults,
    flash,
}: ClassesIndexProps) {
    React.useEffect(() => {
        if (!flash?.message) return;
        if (flash.type === "warning") toast.warning(flash.message);
        else if (flash.type === "error") toast.error(flash.message);
        else toast.success(flash.message);
    }, [flash]);
    const options: ClassOptions = React.useMemo(
        () => ({
            classifications: rawOptions?.classifications ?? DEFAULT_OPTIONS.classifications,
            sections: rawOptions?.sections ?? DEFAULT_OPTIONS.sections,
            semesters: rawOptions?.semesters ?? DEFAULT_OPTIONS.semesters,
            grade_levels: rawOptions?.grade_levels ?? DEFAULT_OPTIONS.grade_levels,
            day_of_week: rawOptions?.day_of_week ?? DEFAULT_OPTIONS.day_of_week,
            courses: rawOptions?.courses ?? DEFAULT_OPTIONS.courses,
            faculties: rawOptions?.faculties ?? DEFAULT_OPTIONS.faculties,
            rooms: rawOptions?.rooms ?? DEFAULT_OPTIONS.rooms,
            shs_tracks: rawOptions?.shs_tracks ?? DEFAULT_OPTIONS.shs_tracks,
        }),
        [rawOptions],
    );

    const defaults: ClassDefaults = React.useMemo(
        () => ({
            semester: rawDefaults?.semester ?? DEFAULT_DEFAULTS.semester,
            school_year: rawDefaults?.school_year ?? DEFAULT_DEFAULTS.school_year,
        }),
        [rawDefaults],
    );

    const classList = React.useMemo<ClassRow[]>(() => classes?.data ?? [], [classes]);
    const classMeta = React.useMemo<
        PaginationMeta & {
            total_students: number;
            total_capacity: number;
            queues: { unassigned_faculty: number; no_schedule: number; over_capacity: number };
            classification_counts: { college: number; shs: number };
        }
    >(
        () =>
            classes?.meta ?? {
                current_page: 1,
                last_page: 1,
                per_page: 20,
                total: 0,
                from: 0,
                to: 0,
                total_students: 0,
                total_capacity: 0,
                queues: { unassigned_faculty: 0, no_schedule: 0, over_capacity: 0 },
                classification_counts: { college: 0, shs: 0 },
            },
        [classes],
    );
    const capabilities = React.useMemo<ClassCapabilities>(() => rawCapabilities ?? DEFAULT_CAPABILITIES, [rawCapabilities]);
    const [search, setSearch] = React.useState(filters?.search || "");
    const searchInputRef = React.useRef<HTMLInputElement>(null);
    const searchInputFocusedRef = React.useRef(false);
    const searchDraftRef = React.useRef(filters?.search || "");
    const debouncedSearchRef = React.useRef<ReturnType<typeof setTimeout> | null>(null);
    const [isUpdating, setIsUpdating] = React.useState(false);

    const [isSelectedClassLoading, setIsSelectedClassLoading] = React.useState(false);
    const [rawViewMode, setViewMode] = useRemember<"ledger" | "grid" | "studio" | "list">("ledger", "administrators.classes.index:viewMode");
    const viewMode: "ledger" | "grid" | "studio" = rawViewMode === "list" ? "ledger" : rawViewMode;
    const [density, setDensity] = useRemember<"compact" | "comfortable">("compact", "administrators.classes.index:density");
    const [expandedClassIds, setExpandedClassIds] = React.useState<number[]>([]);
    const [activeStudioClassId, setActiveStudioClassId] = React.useState<number | null>(null);
    const [studioMode, setStudioMode] = React.useState<"enroll" | "schedule">("enroll");
    const [sectionStudioAnchorId, setSectionStudioAnchorId] = React.useState<number | null>(null);
    const [workspaceCache, setWorkspaceCache] = React.useState<Record<number, ClassWorkspacePayload>>({});
    const [loadingWorkspaceIds, setLoadingWorkspaceIds] = React.useState<Record<number, boolean>>({});
    const [isDockViewport, setIsDockViewport] = React.useState<boolean>(() => (typeof window !== "undefined" ? window.innerWidth >= 1536 : false));
    const enrollSearchInputRef = React.useRef<HTMLInputElement>(null);
    const [sortOption, setSortOption] = React.useState(`${filters?.sort ?? "created_at"}:${filters?.direction ?? "desc"}`);
    const [activeFilters, setActiveFilters] = React.useState<FilterType[]>(() => createActiveFilters(filters));

    React.useEffect(() => {
        if (typeof window === "undefined") return;
        const mq = window.matchMedia("(min-width: 1536px)");
        const update = () => setIsDockViewport(mq.matches);
        update();
        mq.addEventListener("change", update);
        return () => mq.removeEventListener("change", update);
    }, []);

    const loadWorkspace = React.useCallback(
        async (classId: number, force = false) => {
            if (!force && workspaceCache[classId]) {
                return;
            }
            setLoadingWorkspaceIds((prev) => ({ ...prev, [classId]: true }));
            try {
                const payload = await fetchClassWorkspace(classId);
                setWorkspaceCache((prev) => ({ ...prev, [classId]: payload }));
            } catch (error) {
                toast.error(error instanceof Error ? error.message : "Failed to load class workspace.");
            } finally {
                setLoadingWorkspaceIds((prev) => ({ ...prev, [classId]: false }));
            }
        },
        [workspaceCache],
    );

    const refreshWorkspace = React.useCallback(async (classId: number) => {
        setLoadingWorkspaceIds((prev) => ({ ...prev, [classId]: true }));
        try {
            const payload = await fetchClassWorkspace(classId);
            setWorkspaceCache((prev) => ({ ...prev, [classId]: payload }));
        } catch (error) {
            toast.error(error instanceof Error ? error.message : "Failed to refresh class workspace.");
        } finally {
            setLoadingWorkspaceIds((prev) => ({ ...prev, [classId]: false }));
        }
    }, []);

    const refreshIndex = React.useCallback(() => {
        router.reload({ only: ["classes"] });
    }, []);

    const handleToggleExpand = React.useCallback(
        (classId: number) => {
            setExpandedClassIds((prev) => {
                if (prev.includes(classId)) {
                    return prev.filter((id) => id !== classId);
                }
                void loadWorkspace(classId);
                return [...prev, classId];
            });
        },
        [loadWorkspace],
    );

    const handleOpenStudio = React.useCallback(
        (classId: number, mode: "enroll" | "schedule") => {
            setActiveStudioClassId(classId);
            setStudioMode(mode);
            void loadWorkspace(classId);
            if (mode === "enroll") {
                setTimeout(() => enrollSearchInputRef.current?.focus(), 60);
            }
        },
        [loadWorkspace],
    );

    const handleOpenSectionStudio = React.useCallback((classId: number) => {
        setSectionStudioAnchorId(classId);
        setViewMode("studio");
    }, [setViewMode]);

    // Keyboard shortcuts: '/' or Cmd+K search, E quick-enroll, S schedule studio, J/K step classes, Esc collapse studio
    React.useEffect(() => {
        const handleKeyDown = (event: KeyboardEvent) => {
            const target = event.target as HTMLElement | null;
            const isTyping =
                target?.tagName === "INPUT" ||
                target?.tagName === "TEXTAREA" ||
                target?.tagName === "SELECT" ||
                Boolean(target?.isContentEditable);

            if ((event.key === "/" || ((event.metaKey || event.ctrlKey) && event.key.toLowerCase() === "k")) && !isTyping) {
                event.preventDefault();
                searchInputRef.current?.focus();
                return;
            }

            if (isTyping || event.metaKey || event.ctrlKey || event.altKey) {
                return;
            }

            const key = event.key.toLowerCase();
            if (key === "escape" && activeStudioClassId !== null) {
                setActiveStudioClassId(null);
                return;
            }

            if (classList.length === 0) return;

            if (key === "e") {
                event.preventDefault();
                const targetId = activeStudioClassId ?? classList[0].id;
                if (viewMode !== "ledger") setViewMode("ledger");
                handleOpenStudio(targetId, "enroll");
            } else if (key === "s") {
                event.preventDefault();
                const targetId = activeStudioClassId ?? classList[0].id;
                if (viewMode !== "ledger") setViewMode("ledger");
                handleOpenStudio(targetId, "schedule");
            } else if (key === "j" || key === "k") {
                event.preventDefault();
                const currentIdx = activeStudioClassId !== null ? classList.findIndex((c) => c.id === activeStudioClassId) : -1;
                const nextIdx =
                    key === "j"
                        ? currentIdx < 0
                            ? 0
                            : Math.min(classList.length - 1, currentIdx + 1)
                        : currentIdx <= 0
                          ? 0
                          : currentIdx - 1;
                const nextClass = classList[nextIdx];
                if (nextClass) {
                    setActiveStudioClassId(nextClass.id);
                    void loadWorkspace(nextClass.id);
                }
            }
        };

        window.addEventListener("keydown", handleKeyDown);
        return () => window.removeEventListener("keydown", handleKeyDown);
    }, [activeStudioClassId, classList, handleOpenStudio, loadWorkspace, setViewMode, viewMode]);

    const cancelPendingSearch = () => {
        if (debouncedSearchRef.current) {
            clearTimeout(debouncedSearchRef.current);
            debouncedSearchRef.current = null;
        }
    };

    React.useEffect(() => {
        return () => {
            cancelPendingSearch();
        };
    }, []);

    // Synchronize initial filters from server props without clobbering active search drafts
    React.useEffect(() => {
        const serverSearch = filters?.search || "";
        if (!searchInputFocusedRef.current && debouncedSearchRef.current === null) {
            setSearch(serverSearch);
            searchDraftRef.current = serverSearch;
        } else if (searchDraftRef.current === serverSearch) {
            setSearch(serverSearch);
        }

        setActiveFilters(createActiveFilters(filters));

        if (filters?.sort) {
            setSortOption(`${filters.sort}:${filters.direction ?? "desc"}`);
        }
    }, [filters]);
    const [isEditOpen, setIsEditOpen] = React.useState(false);
    const [isCopyOpen, setIsCopyOpen] = React.useState(false);
    const [isManageOpen, setIsManageOpen] = React.useState(false);
    const [editActiveTab, setEditActiveTab] = React.useState<ClassDialogTab>("details");
    const [copySourceId, setCopySourceId] = React.useState<number | null>(null);
    const [copySection, setCopySection] = React.useState("A");
    const [copySchedules, setCopySchedules] = React.useState(false);
    const [pendingDelete, setPendingDelete] = React.useState<ClassRow | null>(null);
    const [isCompareOpen, setIsCompareOpen] = React.useState(false);
    const [compareInitialClassIds, setCompareInitialClassIds] = React.useState<[number, number] | undefined>();
    const [rowSelection, setRowSelection] = React.useState<RowSelectionState>({});
    const selectedClasses = classList.filter((row) => rowSelection[String(row.id)]);

    React.useEffect(() => {
        setRowSelection({});
    }, [classList]);

    const openCompare = (classIds?: [number, number]) => {
        setCompareInitialClassIds(classIds);
        setIsCompareOpen(true);
    };

    // The server applies the search and every filter, so the rows on screen are
    // already the result set. Filtering them again here would drop rows the
    // server matched — searching a program code used to hide every class.
    const visibleClasses = classList;

    const parseSortOption = (value: string): { sort: string; direction: "asc" | "desc" } => {
        const [sort = "created_at", direction = "desc"] = value.split(":");

        return {
            sort,
            direction: direction === "asc" ? "asc" : "desc",
        };
    };

    const buildFilterParams = (
        searchTerm: string,
        filterValues: FilterType[] = activeFilters,
        selectedSortOption: string = sortOption,
        extra: Record<string, string | number> = {},
    ): Record<string, string | number> => {
        const sort = parseSortOption(selectedSortOption);
        const appliedFilters: Record<string, string | number> = {
            sort: sort.sort,
            direction: sort.direction,
        };

        if (searchTerm.trim()) {
            appliedFilters.search = searchTerm.trim();
        }

        // Carry over the filters the server understands but the toolbar does not
        // expose, so a filter that arrived in the URL is never silently dropped
        // by the next navigation.
        Object.entries(filters ?? {}).forEach(([key, value]) => {
            if (["search", "sort", "direction", ...filterFields.map((field) => field.key)].includes(key)) {
                return;
            }

            if (value === null || value === undefined || value === "") {
                return;
            }

            appliedFilters[key] = typeof value === "boolean" ? String(value) : value;
        });

        filterValues.forEach((filter) => {
            const value = filter.values[0];

            if (typeof value === "string" || typeof value === "number") {
                appliedFilters[filter.field] = value;
            }
        });

        return { ...appliedFilters, ...extra };
    };

    const navigateWithParams = (params: Record<string, string | number>, options: { replace?: boolean } = {}) => {
        cancelPendingSearch();
        setIsUpdating(true);
        router.get(classIndex.url(), params, {
            only: ["classes", "filters"],
            preserveScroll: true,
            preserveState: true,
            replace: options.replace ?? true,
            onFinish: () => setIsUpdating(false),
        });
    };

    const handleSearchChange = (nextSearch: string) => {
        searchDraftRef.current = nextSearch;
        setSearch(nextSearch);
        cancelPendingSearch();
        debouncedSearchRef.current = setTimeout(() => {
            debouncedSearchRef.current = null;
            setIsUpdating(true);
            router.get(classIndex.url(), buildFilterParams(nextSearch, activeFilters, sortOption, { page: 1 }), {
                only: ["classes", "filters"],
                preserveScroll: true,
                preserveState: true,
                replace: true,
                onFinish: () => setIsUpdating(false),
            });
        }, 300);
    };

    const handleFiltersChange = (newFilters: FilterType[]) => {
        setActiveFilters(newFilters);
        navigateWithParams(buildFilterParams(search, newFilters, sortOption, { page: 1 }));
    };

    const clearFilters = () => {
        searchDraftRef.current = "";
        setSearch("");
        setActiveFilters([]);
        setSortOption("created_at:desc");
        cancelPendingSearch();
        navigateWithParams({ sort: "created_at", direction: "desc", per_page: classMeta.per_page, page: 1 });
    };

    const handleSortChange = (value: string) => {
        setSortOption(value);
        navigateWithParams(buildFilterParams(search, activeFilters, value, { page: 1 }));
    };

    const handlePageChange = (page: number, perPage: number) => {
        // A page change is a navigation the user may want to go back to, so it
        // is pushed onto the history instead of replacing the current entry.
        navigateWithParams(buildFilterParams(search, activeFilters, sortOption, { page, per_page: perPage }), { replace: false });
    };

    /**
     * Workspace controls share the same server query as advanced filters.
     */
    const handleWorkspaceFilterChange = (next: ClassWorkspaceFilters): void => {
        const params = buildFilterParams(search, activeFilters, sortOption, { page: 1 });

        if (next.classification) {
            params.classification = next.classification;
        } else {
            delete params.classification;
        }

        if (next.needs_faculty) {
            params.needs_faculty = "true";
        } else {
            delete params.needs_faculty;
        }

        if (next.needs_schedule) {
            params.needs_schedule = "true";
        } else {
            delete params.needs_schedule;
        }

        if (next.over_capacity) {
            params.over_capacity = "true";
        } else {
            delete params.over_capacity;
        }

        if (next.trashed) {
            params.trashed = next.trashed;
        } else {
            delete params.trashed;
        }

        navigateWithParams(params);
    };

    const filterFields: FilterFieldConfig[] = React.useMemo(
        () => [
            {
                key: "course_id",
                label: "Course",
                type: "select",
                icon: <BookOpen className="h-4 w-4" />,
                options: (options.courses ?? []).map((option) => ({
                    value: option.id,
                    label: option.label,
                    icon: <BookOpen className="text-muted-foreground h-4 w-4" />,
                })),
            },
            {
                key: "shs_track_id",
                label: "SHS Track",
                type: "select",
                icon: <Layers className="h-4 w-4" />,
                options: (options.shs_tracks ?? []).map((option) => ({
                    value: option.id,
                    label: option.label,
                    icon: <Layers className="text-muted-foreground h-4 w-4" />,
                })),
            },
            {
                key: "faculty_id",
                label: "Faculty",
                type: "select",
                icon: <Users className="h-4 w-4" />,
                options: (options.faculties ?? []).map((option) => ({
                    value: option.id,
                    label: option.label,
                    icon: <Users className="text-muted-foreground h-4 w-4" />,
                })),
            },
            {
                key: "room_id",
                label: "Room",
                type: "select",
                icon: <MapPin className="h-4 w-4" />,
                options: (options.rooms ?? []).map((option) => ({
                    value: option.id,
                    label: option.label,
                    icon: <MapPin className="text-muted-foreground h-4 w-4" />,
                })),
            },
            {
                key: "semester",
                label: "Semester",
                type: "select",
                icon: <CalendarIcon className="h-4 w-4" />,
                options: (options.semesters ?? []).map((option) => ({ ...option, icon: <CalendarIcon className="text-muted-foreground h-4 w-4" /> })),
            },
            {
                key: "academic_year",
                label: "College Year",
                type: "select",
                icon: <GraduationCap className="h-4 w-4" />,
                options: [1, 2, 3, 4].map((year) => ({
                    value: year,
                    label: `${year} year`,
                    icon: <GraduationCap className="text-muted-foreground h-4 w-4" />,
                })),
            },
            {
                key: "grade_level",
                label: "SHS Grade",
                type: "select",
                icon: <GraduationCap className="h-4 w-4" />,
                options: (options.grade_levels ?? []).map((option) => ({
                    ...option,
                    icon: <GraduationCap className="text-muted-foreground h-4 w-4" />,
                })),
            },
            {
                key: "available_slots",
                label: "Available Slots",
                type: "select",
                icon: <Users className="h-4 w-4" />,
                options: [{ value: "true", label: "Has available slots", icon: <Users className="h-4 w-4 text-green-500" /> }],
            },
            {
                key: "fully_enrolled",
                label: "Enrollment Status",
                type: "select",
                icon: <Users className="h-4 w-4" />,
                options: [
                    { value: "true", label: "Fully enrolled", icon: <Users className="h-4 w-4 text-red-500" /> },
                    { value: "false", label: "Not fully enrolled", icon: <Users className="h-4 w-4 text-green-500" /> },
                ],
            },
        ],
        [options],
    );

    const openManage = (classId: number) => {
        setIsManageOpen(true);
        setIsSelectedClassLoading(true);

        router.get(classIndex.url(), buildFilterParams(search, activeFilters, sortOption, { selected: classId }), {
            preserveState: true,
            replace: true,
            only: ["selected_class"],
            onFinish: () => setIsSelectedClassLoading(false),
        });
    };

    const openEdit = (classId: number, tab: ClassDialogTab = "details") => {
        setIsEditOpen(true);
        setEditActiveTab(tab);
        setIsSelectedClassLoading(true);

        router.get(classIndex.url(), buildFilterParams(search, activeFilters, sortOption, { selected: classId }), {
            preserveState: true,
            replace: true,
            only: ["selected_class"],
            onFinish: () => setIsSelectedClassLoading(false),
        });
    };

    const openSchedule = (classId: number) => {
        cancelPendingSearch();
        router.visit(editClass.url(classId, { query: { step: "schedule" } }));
    };

    const confirmDelete = (row: ClassRow) => {
        setPendingDelete(row);
    };

    const performDelete = () => {
        if (!pendingDelete) return;

        router.delete(destroyClass.url(pendingDelete.id), {
            preserveScroll: true,
            onSuccess: () => {
                setPendingDelete(null);
                setIsManageOpen(false);
            },
        });
    };

    const performRestore = (row: ClassRow) => {
        router.post(
            restoreClass.url(row.id),
            {},
            {
                preserveScroll: true,
                onSuccess: () => toast.success(`${row.record_title} restored.`),
            },
        );
    };

    const performForceDelete = (row: ClassRow) => {
        router.delete(forceDestroyClass.url(row.id), {
            preserveScroll: true,
            onSuccess: () => toast.success(`${row.record_title} permanently deleted.`),
        });
    };

    const classActions: ClassActionHandlers = {
        onManage: openManage,
        onEdit: openEdit,
        onSchedule: (classId) => {
            if (viewMode === "ledger") {
                handleOpenStudio(classId, "schedule");
            } else {
                openSchedule(classId);
            }
        },
        onDelete: confirmDelete,
        onRestore: performRestore,
        onForceDelete: performForceDelete,
        onCopy: (id) => {
            setCopySourceId(id);
            setCopySection("A");
            setCopySchedules(false);
            setIsCopyOpen(true);
        },
        capabilities,
    };

    const activeStudioClass = React.useMemo(
        () => (activeStudioClassId !== null ? (classList.find((c) => c.id === activeStudioClassId) ?? null) : null),
        [activeStudioClassId, classList],
    );

    const pageConflictCount = React.useMemo(
        () => classList.filter((c) => c.schedule_health?.has_conflict || c.schedule_health?.is_room_overcrowded).length,
        [classList],
    );

    const pageIncompleteHoursCount = React.useMemo(
        () => classList.filter((c) => c.schedule_health && !c.schedule_health.is_complete && c.schedule_health.status !== "unscheduled").length,
        [classList],
    );

    const pageWaitlistedTotal = React.useMemo(
        () => classList.reduce((acc, c) => acc + (c.waitlist_count ?? 0), 0),
        [classList],
    );

    const hasListFilters =
        search.trim() !== "" ||
        Object.entries(filters).some(
            ([key, value]) => !["search", "sort", "direction", "per_page"].includes(key) && value !== null && value !== undefined && value !== "",
        );
    const emptyState = (
        <div className="flex flex-col items-center gap-4 px-6 py-16 text-center">
            <BookOpen className="text-muted-foreground size-8" aria-hidden="true" />
            <div className="space-y-2">
                <h3 className="text-base font-semibold">
                    {hasListFilters
                        ? "No classes match these filters"
                        : filters.trashed === "only"
                          ? "No archived classes"
                          : "No classes in this term"}
                </h3>
                <p className="text-muted-foreground max-w-md text-sm">
                    {hasListFilters
                        ? "Adjust your search or reset the filters to see more class sections."
                        : "Create a class section to assign faculty, enroll students, and plan its schedule."}
                </p>
            </div>
            {hasListFilters ? (
                <Button type="button" variant="outline" className="h-10 px-4 text-sm" onClick={clearFilters}>
                    Reset search and filters
                </Button>
            ) : capabilities.create ? (
                <Link href={createClass.url()} className={buttonVariants({ variant: "outline", className: "h-10 px-4 text-sm" })}>
                    Create class
                </Link>
            ) : null}
        </div>
    );

    return (
        <AdminLayout user={user} title="Classes">
            <Head title="Administrators • Classes" />

            <div className="flex min-w-0 flex-col gap-6 pb-12">
                {/* Compact Unified Command Header & Proportional Capacity + Triage Ribbon */}
                <header className="bg-card relative overflow-hidden rounded-2xl border p-4 shadow-xs sm:px-6 sm:py-5">
                    <div className="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                        <div className="flex min-w-0 items-center gap-3">
                            <div className="bg-primary/10 text-primary flex size-10 shrink-0 items-center justify-center rounded-xl">
                                <BookOpen className="size-5" />
                            </div>
                            <div className="min-w-0">
                                <div className="flex flex-wrap items-center gap-2">
                                    <h1 className="text-foreground text-xl font-bold tracking-tight sm:text-2xl">Class Offerings &amp; Rosters</h1>
                                    <Badge
                                        variant="outline"
                                        className="border-border/80 bg-muted/40 text-foreground gap-1.5 rounded-lg px-2.5 py-0.5 text-xs font-semibold"
                                    >
                                        <Calendar className="text-primary size-3" />
                                        {defaults.school_year || "Current Year"} · {semesterLabel(defaults.semester)}
                                    </Badge>
                                </div>
                                <p className="text-muted-foreground text-xs">
                                    Manage enrollees, inline grades, Lec/Lab room schedules, conflict fixes, and parallel sections in one workspace.
                                </p>
                            </div>
                        </div>

                        <div className="flex shrink-0 flex-wrap items-center gap-2">
                            <div className="text-muted-foreground bg-muted/40 hidden items-center gap-1.5 rounded-lg border px-2.5 py-1 text-[11px] 2xl:flex">
                                <kbd className="bg-background rounded border px-1 font-mono text-[10px] font-semibold">J</kbd>
                                <kbd className="bg-background rounded border px-1 font-mono text-[10px] font-semibold">K</kbd>
                                <span>step</span>
                                <span className="opacity-40">·</span>
                                <kbd className="bg-background rounded border px-1 font-mono text-[10px] font-semibold">E</kbd>
                                <span>enroll</span>
                                <span className="opacity-40">·</span>
                                <kbd className="bg-background rounded border px-1 font-mono text-[10px] font-semibold">S</kbd>
                                <span>schedule</span>
                            </div>

                            <Link
                                href={schedulePlanner.url()}
                                className={buttonVariants({
                                    variant: "outline",
                                    className: "h-9 gap-1.5 rounded-lg px-3 text-xs font-semibold shadow-2xs",
                                })}
                            >
                                <CalendarDays className="size-3.5" aria-hidden="true" />
                                Schedule planner
                            </Link>

                            {capabilities.create ? (
                                <Link
                                    href={createClass.url()}
                                    className={buttonVariants({ className: "h-9 gap-1.5 rounded-lg px-3.5 text-xs font-semibold shadow-xs" })}
                                >
                                    <Plus className="size-3.5" aria-hidden="true" />
                                    New class
                                </Link>
                            ) : null}
                        </div>
                    </div>

                    {/* Proportional Capacity & Triage Ribbon */}
                    <div className="mt-4 flex flex-wrap items-center justify-between gap-3 border-t pt-3.5">
                        <div className="flex flex-wrap items-center gap-3">
                            <div className="flex items-baseline gap-1.5 text-xs">
                                <span className="text-foreground text-sm font-bold tabular-nums">{classMeta.total.toLocaleString()}</span>
                                <span className="text-muted-foreground">offerings</span>
                                <Badge variant="secondary" className="ml-1 text-[10px] font-medium">
                                    {classMeta.classification_counts.college} College
                                </Badge>
                                <Badge variant="outline" className="text-[10px] font-medium">
                                    {classMeta.classification_counts.shs} SHS
                                </Badge>
                            </div>

                            <div className="hidden h-4 w-px bg-border sm:block" />

                            <div className="flex items-center gap-2 text-xs">
                                <div className="bg-muted h-1.5 w-24 overflow-hidden rounded-full">
                                    <div
                                        className="bg-primary h-full rounded-full transition-all duration-300"
                                        style={{
                                            width: `${classMeta.total_capacity > 0 ? Math.min((classMeta.total_students / classMeta.total_capacity) * 100, 100) : 0}%`,
                                        }}
                                    />
                                </div>
                                <span className="text-foreground font-semibold tabular-nums">{classMeta.total_students.toLocaleString()}</span>
                                <span className="text-muted-foreground tabular-nums">/ {classMeta.total_capacity.toLocaleString()} seats</span>
                                {classMeta.total_capacity > 0 ? (
                                    <Badge variant="outline" className="px-1.5 py-0 text-[10px] tabular-nums">
                                        {Math.round((classMeta.total_students / classMeta.total_capacity) * 100)}%
                                    </Badge>
                                ) : null}
                            </div>
                        </div>

                        <div className="flex flex-wrap items-center gap-1.5">
                            <span className="text-muted-foreground mr-1 text-[11px] font-semibold tracking-wider uppercase">Triage</span>

                            <button
                                type="button"
                                onClick={() => handleWorkspaceFilterChange({ ...filters, needs_faculty: filters.needs_faculty ? null : true })}
                                className={cn(
                                    "inline-flex cursor-pointer items-center gap-1.5 rounded-full border px-2.5 py-1 text-xs font-medium transition-colors",
                                    filters.needs_faculty
                                        ? "border-amber-500/60 bg-amber-500/15 text-amber-700 dark:text-amber-300"
                                        : "bg-background hover:bg-muted/60 text-foreground",
                                )}
                            >
                                <UserCheck className={cn("size-3", classMeta.queues.unassigned_faculty > 0 ? "text-amber-500" : "text-muted-foreground")} />
                                Needs Faculty
                                <span className="font-mono text-[11px] font-bold tabular-nums">{classMeta.queues.unassigned_faculty}</span>
                            </button>

                            <button
                                type="button"
                                onClick={() => handleWorkspaceFilterChange({ ...filters, needs_schedule: filters.needs_schedule ? null : true })}
                                className={cn(
                                    "inline-flex cursor-pointer items-center gap-1.5 rounded-full border px-2.5 py-1 text-xs font-medium transition-colors",
                                    filters.needs_schedule
                                        ? "border-indigo-500/60 bg-indigo-500/15 text-indigo-700 dark:text-indigo-300"
                                        : "bg-background hover:bg-muted/60 text-foreground",
                                )}
                            >
                                <CalendarDays className={cn("size-3", classMeta.queues.no_schedule > 0 ? "text-indigo-500" : "text-muted-foreground")} />
                                No Schedule
                                <span className="font-mono text-[11px] font-bold tabular-nums">{classMeta.queues.no_schedule}</span>
                            </button>

                            <button
                                type="button"
                                onClick={() => handleWorkspaceFilterChange({ ...filters, over_capacity: filters.over_capacity ? null : true })}
                                className={cn(
                                    "inline-flex cursor-pointer items-center gap-1.5 rounded-full border px-2.5 py-1 text-xs font-medium transition-colors",
                                    filters.over_capacity
                                        ? "border-rose-500/60 bg-rose-500/15 text-rose-700 dark:text-rose-300"
                                        : "bg-background hover:bg-muted/60 text-foreground",
                                )}
                            >
                                <Users className={cn("size-3", classMeta.queues.over_capacity > 0 ? "text-rose-500" : "text-muted-foreground")} />
                                Over Cap
                                <span className="font-mono text-[11px] font-bold tabular-nums">{classMeta.queues.over_capacity}</span>
                            </button>

                            {pageConflictCount > 0 ? (
                                <Badge variant="outline" className="gap-1 rounded-full border-rose-500/40 bg-rose-500/10 px-2.5 py-1 text-xs font-medium text-rose-700 dark:text-rose-300">
                                    <AlertTriangle className="size-3" />
                                    Conflicts <span className="font-mono font-bold">{pageConflictCount}</span>
                                </Badge>
                            ) : null}

                            {pageIncompleteHoursCount > 0 ? (
                                <Badge variant="outline" className="gap-1 rounded-full border-amber-500/40 bg-amber-500/10 px-2.5 py-1 text-xs font-medium text-amber-700 dark:text-amber-300">
                                    Incomplete Hrs <span className="font-mono font-bold">{pageIncompleteHoursCount}</span>
                                </Badge>
                            ) : null}

                            {pageWaitlistedTotal > 0 ? (
                                <Badge variant="outline" className="gap-1 rounded-full border-indigo-500/40 bg-indigo-500/10 px-2.5 py-1 text-xs font-medium text-indigo-700 dark:text-indigo-300">
                                    Waitlisted <span className="font-mono font-bold">{pageWaitlistedTotal}</span>
                                </Badge>
                            ) : null}
                        </div>
                    </div>
                </header>

                <section className="bg-card min-w-0 overflow-hidden rounded-2xl border shadow-xs" aria-labelledby="class-sections-heading">
                    <div className="flex flex-col gap-3 border-b p-4 sm:px-6 lg:flex-row lg:items-center lg:justify-between">
                        <div className="flex flex-wrap items-center gap-3">
                            <h2 id="class-sections-heading" className="text-base font-semibold">
                                {filters.trashed === "only" ? "Archived classes" : "Class sections"}
                                <span className="text-muted-foreground ml-2 font-normal tabular-nums">{classMeta.total.toLocaleString()}</span>
                            </h2>

                            {viewMode === "ledger" ? (
                                <div className="flex items-center gap-0.5 rounded-lg border p-0.5 text-xs" role="group" aria-label="Row density">
                                    <button
                                        type="button"
                                        onClick={() => setDensity("compact")}
                                        className={cn(
                                            "cursor-pointer rounded-md px-2 py-1 font-medium transition-colors",
                                            density === "compact" ? "bg-secondary text-secondary-foreground" : "text-muted-foreground hover:text-foreground",
                                        )}
                                    >
                                        Compact
                                    </button>
                                    <button
                                        type="button"
                                        onClick={() => setDensity("comfortable")}
                                        className={cn(
                                            "cursor-pointer rounded-md px-2 py-1 font-medium transition-colors",
                                            density === "comfortable" ? "bg-secondary text-secondary-foreground" : "text-muted-foreground hover:text-foreground",
                                        )}
                                    >
                                        Comfortable
                                    </button>
                                </div>
                            ) : null}
                        </div>

                        <div className="flex flex-wrap items-center gap-2.5">
                            <Button type="button" variant="ghost" className="h-9 gap-1.5 px-2.5 text-xs font-medium" onClick={() => openCompare()}>
                                <GitCompare className="size-3.5" aria-hidden="true" /> Compare
                            </Button>

                            {viewMode === "ledger" && classList.length > 0 ? (
                                <Button
                                    type="button"
                                    variant={activeStudioClassId !== null ? "secondary" : "outline"}
                                    className="h-9 gap-1.5 px-3 text-xs font-medium"
                                    onClick={() => {
                                        if (activeStudioClassId !== null) {
                                            setActiveStudioClassId(null);
                                        } else {
                                            handleOpenStudio(classList[0].id, studioMode);
                                        }
                                    }}
                                >
                                    <PanelRight className="size-3.5" aria-hidden="true" />
                                    {activeStudioClassId !== null ? "Hide Studio" : "Class Studio"}
                                </Button>
                            ) : null}

                            <Select value={sortOption} onValueChange={handleSortChange}>
                                <SelectTrigger className="h-9 w-40 text-xs" aria-label="Sort classes">
                                    <SelectValue placeholder="Sort classes" />
                                </SelectTrigger>
                                <SelectContent align="end">
                                    <SelectItem value="created_at:desc">Latest added</SelectItem>
                                    <SelectItem value="created_at:asc">Oldest added</SelectItem>
                                    <SelectItem value="record_title:asc">Class A to Z</SelectItem>
                                    <SelectItem value="record_title:desc">Class Z to A</SelectItem>
                                    <SelectItem value="faculty:asc">Faculty A to Z</SelectItem>
                                    <SelectItem value="faculty:desc">Faculty Z to A</SelectItem>
                                    <SelectItem value="students_count:desc">Most enrolled</SelectItem>
                                    <SelectItem value="students_count:asc">Least enrolled</SelectItem>
                                    <SelectItem value="period:asc">Earliest term</SelectItem>
                                    <SelectItem value="period:desc">Latest term</SelectItem>
                                </SelectContent>
                            </Select>

                            <div className="flex items-center gap-0.5 rounded-lg border p-1" role="group" aria-label="Class display">
                                <Button
                                    type="button"
                                    variant={viewMode === "ledger" ? "secondary" : "ghost"}
                                    size="sm"
                                    className="h-7 gap-1.5 px-2.5 text-xs"
                                    onClick={() => setViewMode("ledger")}
                                    aria-label="Ledger view"
                                    aria-pressed={viewMode === "ledger"}
                                >
                                    <List className="size-3.5" aria-hidden="true" />
                                    <span className="hidden sm:inline">Ledger</span>
                                </Button>
                                <Button
                                    type="button"
                                    variant={viewMode === "grid" ? "secondary" : "ghost"}
                                    size="sm"
                                    className="h-7 gap-1.5 px-2.5 text-xs"
                                    onClick={() => setViewMode("grid")}
                                    aria-label="Card view"
                                    aria-pressed={viewMode === "grid"}
                                >
                                    <LayoutGrid className="size-3.5" aria-hidden="true" />
                                    <span className="hidden sm:inline">Cards</span>
                                </Button>
                                <Button
                                    type="button"
                                    variant={viewMode === "studio" ? "secondary" : "ghost"}
                                    size="sm"
                                    className="h-7 gap-1.5 px-2.5 text-xs"
                                    onClick={() => {
                                        if (sectionStudioAnchorId === null && classList.length > 0) {
                                            setSectionStudioAnchorId(activeStudioClassId ?? classList[0].id);
                                        }
                                        setViewMode("studio");
                                    }}
                                    aria-label="Section Studio view"
                                    aria-pressed={viewMode === "studio"}
                                >
                                    <Columns3 className="size-3.5" aria-hidden="true" />
                                    <span className="hidden sm:inline">Section Studio</span>
                                </Button>
                            </div>
                        </div>
                    </div>
                    <ClassListToolbar
                        search={search}
                        searchInputRef={searchInputRef}
                        onSearchChange={handleSearchChange}
                        onSearchFocusChange={(focused) => {
                            searchInputFocusedRef.current = focused;
                        }}
                        filters={filters}
                        onWorkspaceFilterChange={handleWorkspaceFilterChange}
                        activeFilters={activeFilters}
                        filterFields={filterFields}
                        onFiltersChange={handleFiltersChange}
                        queues={classMeta.queues}
                        hasFilters={hasListFilters}
                        onReset={clearFilters}
                    />
                    {filters.trashed === "only" ? (
                        <p className="bg-muted/40 text-muted-foreground border-b px-4 py-4 text-sm sm:px-6">
                            Archived classes retain their schedules and enrollments. Restore a class to return it to the active list.
                        </p>
                    ) : null}
                    <div className="sr-only" role="status">
                        {isUpdating ? "Updating class list" : `${classMeta.total} classes found`}
                    </div>

                    {viewMode === "studio" ? (
                        <div className="p-4 sm:p-6">
                            {visibleClasses.length > 0 ? (
                                <SectionRebalancerStudio
                                    anchorClassId={sectionStudioAnchorId ?? activeStudioClassId ?? visibleClasses[0]?.id ?? null}
                                    availableClasses={visibleClasses}
                                    onChangeAnchorClass={(id) => setSectionStudioAnchorId(id)}
                                    onOpenScheduleInLedger={(id) => {
                                        setViewMode("ledger");
                                        handleOpenStudio(id, "schedule");
                                    }}
                                    onMutated={refreshIndex}
                                    canUpdate={capabilities.update}
                                />
                            ) : (
                                emptyState
                            )}
                        </div>
                    ) : viewMode === "grid" ? (
                        <div aria-busy={isUpdating}>
                            <div className={isUpdating ? "opacity-60" : undefined}>
                                {visibleClasses.length > 0 ? (
                                    <div className="grid gap-4 p-4 sm:p-6 md:grid-cols-2 2xl:grid-cols-3">
                                        {visibleClasses.map((row) => (
                                            <ClassCard
                                                key={row.id}
                                                classRow={row}
                                                selected={Boolean(rowSelection[String(row.id)])}
                                                onSelect={(selected) => setRowSelection((current) => ({ ...current, [String(row.id)]: selected }))}
                                                {...classActions}
                                            />
                                        ))}
                                    </div>
                                ) : (
                                    emptyState
                                )}
                            </div>
                            <DataTablePagination
                                page={classMeta.current_page}
                                lastPage={classMeta.last_page}
                                perPage={classMeta.per_page}
                                total={classMeta.total}
                                from={classMeta.from}
                                to={classMeta.to}
                                isLoading={isUpdating}
                                onPageChange={handlePageChange}
                            />
                        </div>
                    ) : (
                        <div
                            aria-busy={isUpdating}
                            className={cn(
                                "min-w-0",
                                activeStudioClassId !== null && isDockViewport && "2xl:grid 2xl:grid-cols-[minmax(0,1fr)_420px] 2xl:items-start",
                            )}
                        >
                            <div className={cn("min-w-0", isUpdating && "opacity-60")}>
                                {visibleClasses.length > 0 ? (
                                    <ExpandableClassLedger
                                        classes={visibleClasses}
                                        rowSelection={rowSelection}
                                        onRowSelectionChange={setRowSelection}
                                        expandedClassIds={expandedClassIds}
                                        onToggleExpand={handleToggleExpand}
                                        activeStudioClassId={activeStudioClassId}
                                        studioMode={studioMode}
                                        onOpenStudio={handleOpenStudio}
                                        onOpenSectionStudio={handleOpenSectionStudio}
                                        workspaceCache={workspaceCache}
                                        loadingWorkspaceIds={loadingWorkspaceIds}
                                        onRefreshWorkspace={refreshWorkspace}
                                        onRefreshIndex={refreshIndex}
                                        capabilities={capabilities}
                                        density={density}
                                        rowActions={classActions}
                                    />
                                ) : (
                                    emptyState
                                )}
                                <DataTablePagination
                                    page={classMeta.current_page}
                                    lastPage={classMeta.last_page}
                                    perPage={classMeta.per_page}
                                    total={classMeta.total}
                                    from={classMeta.from}
                                    to={classMeta.to}
                                    isLoading={isUpdating}
                                    onPageChange={handlePageChange}
                                />
                            </div>

                            {activeStudioClassId !== null && isDockViewport ? (
                                <aside className="border-border bg-card sticky top-0 border-l">
                                    <DualModeClassStudio
                                        classRow={activeStudioClass}
                                        workspace={workspaceCache[activeStudioClassId] ?? null}
                                        loading={Boolean(loadingWorkspaceIds[activeStudioClassId])}
                                        mode={studioMode}
                                        onModeChange={setStudioMode}
                                        onClose={() => setActiveStudioClassId(null)}
                                        rooms={options.rooms}
                                        selectedClassCount={selectedClasses.length}
                                        canUpdate={capabilities.update}
                                        enrollSearchInputRef={enrollSearchInputRef}
                                        onWorkspaceMutated={refreshWorkspace}
                                        onRefreshIndex={refreshIndex}
                                    />
                                </aside>
                            ) : null}
                        </div>
                    )}
                </section>
            </div>

            {/* Responsive Drawer for Dual-Mode Studio on <1536px viewports */}
            {!isDockViewport ? (
                <Sheet open={activeStudioClassId !== null} onOpenChange={(open) => !open && setActiveStudioClassId(null)}>
                    <SheetContent side="right" className="w-full overflow-y-auto p-0 sm:max-w-[500px]">
                        <SheetHeader className="sr-only">
                            <SheetTitle>{activeStudioClass?.record_title ?? "Class Studio"}</SheetTitle>
                            <SheetDescription>Manage class enrollees and schedule blocks</SheetDescription>
                        </SheetHeader>
                        {activeStudioClassId !== null ? (
                            <DualModeClassStudio
                                classRow={activeStudioClass}
                                workspace={workspaceCache[activeStudioClassId] ?? null}
                                loading={Boolean(loadingWorkspaceIds[activeStudioClassId])}
                                mode={studioMode}
                                onModeChange={setStudioMode}
                                onClose={() => setActiveStudioClassId(null)}
                                hideCollapseButton
                                rooms={options.rooms}
                                selectedClassCount={selectedClasses.length}
                                canUpdate={capabilities.update}
                                enrollSearchInputRef={enrollSearchInputRef}
                                onWorkspaceMutated={refreshWorkspace}
                                onRefreshIndex={refreshIndex}
                            />
                        ) : null}
                    </SheetContent>
                </Sheet>
            ) : null}

            {/* Floating Multi-Class Bulk Action Dock */}
            <MultiClassBulkDock
                selectedClasses={selectedClasses}
                faculties={options.faculties}
                canUpdate={capabilities.update}
                onClear={() => setRowSelection({})}
                onCompare={(ids) => openCompare(ids)}
                onCompleted={() => {
                    setRowSelection({});
                    refreshIndex();
                    if (activeStudioClassId !== null) {
                        void refreshWorkspace(activeStudioClassId);
                    }
                }}
            />

            <Dialog open={isCopyOpen} onOpenChange={setIsCopyOpen}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Copy class</DialogTitle>
                        <DialogDescription>
                            Creates a new section with the same curriculum, faculty, capacity, and classroom settings.
                        </DialogDescription>
                    </DialogHeader>

                    <div className="space-y-4">
                        <div className="space-y-2">
                            <Label htmlFor="copy-class-section">New section</Label>
                            <Input id="copy-class-section" value={copySection} maxLength={20} onChange={(event) => setCopySection(event.target.value)} />
                        </div>

                        <label
                            htmlFor="copy-class-schedules"
                            className="bg-muted/30 flex cursor-pointer items-start gap-2.5 rounded-lg border p-3 text-sm"
                        >
                            <Checkbox
                                id="copy-class-schedules"
                                checked={copySchedules}
                                onCheckedChange={(checked) => setCopySchedules(checked === true)}
                                className="mt-0.5"
                            />
                            <span className="space-y-0.5">
                                <span className="text-foreground block font-medium">Copy weekly schedules as well</span>
                                <span className="text-muted-foreground block text-xs">
                                    Leave unchecked to copy the offering without duplicating existing room or faculty time blocks.
                                </span>
                            </span>
                        </label>
                    </div>

                    <DialogFooter>
                        <Button variant="outline" onClick={() => setIsCopyOpen(false)}>
                            Cancel
                        </Button>
                        <Button
                            disabled={!copySection.trim()}
                            onClick={() => {
                                if (!copySourceId) return;
                                router.post(
                                    copyClass.url(copySourceId),
                                    { section: copySection.trim(), copy_schedules: copySchedules },
                                    { preserveScroll: true, onSuccess: () => setIsCopyOpen(false), onError: (errors) => toast.error(errors.section ?? "Could not copy this class.") },
                                );
                            }}
                        >
                            Copy
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <EditClassDialog
                open={isEditOpen}
                onOpenChange={(open) => {
                    setIsEditOpen(open);
                    if (!open) {
                        setIsSelectedClassLoading(false);
                    }
                }}
                selectedClass={selected_class ?? null}
                isLoading={isSelectedClassLoading}
                options={options as unknown as ClassFormOptions}
                defaults={defaults}
                initialTab={editActiveTab}
                onSuccess={() => {
                    setIsEditOpen(false);
                    setIsSelectedClassLoading(false);
                }}
            />

            <DeleteClassDialog pendingDelete={pendingDelete} onConfirm={performDelete} onCancel={() => setPendingDelete(null)} />

            <Sheet open={isManageOpen} onOpenChange={setIsManageOpen}>
                <SheetContent side="right" className="w-full sm:max-w-xl">
                    <SheetHeader>
                        <SheetTitle>{selected_class?.record_title ?? "Class"}</SheetTitle>
                        <SheetDescription>
                            {selected_class ? (
                                <span>
                                    {selected_class.subject_title} • {selected_class.school_year} • Sem {selected_class.semester}
                                </span>
                            ) : (
                                "Loading..."
                            )}
                        </SheetDescription>
                    </SheetHeader>

                    {!selected_class ? (
                        <div className="text-muted-foreground p-4 text-sm">Loading class details…</div>
                    ) : (
                        <div className="flex flex-1 flex-col gap-4 overflow-auto px-4">
                            <div className="flex flex-wrap gap-2">
                                <Badge variant="outline" className="capitalize">
                                    {selected_class.classification}
                                </Badge>
                                <Badge variant="outline">{deliveryModeLabel(selected_class.delivery_summary ?? selected_class.delivery_mode)}</Badge>
                                <Badge variant="secondary">
                                    {selected_class.students_count} / {selected_class.maximum_slots || "—"} enrolled
                                </Badge>
                            </div>

                            <Tabs defaultValue="overview">
                                <TabsList className="w-full justify-start">
                                    <TabsTrigger value="overview">Overview</TabsTrigger>
                                    <TabsTrigger value="schedule">Schedule</TabsTrigger>
                                    <TabsTrigger value="settings">Settings</TabsTrigger>
                                    <TabsTrigger value="relationships">Relationships</TabsTrigger>
                                </TabsList>

                                <TabsContent value="overview" className="space-y-3">
                                    <div className="grid gap-3 rounded-lg border p-4">
                                        <div className="grid gap-2">
                                            <div className="text-muted-foreground text-xs">Subject</div>
                                            <div className="text-foreground font-medium">
                                                {selected_class.subject_code} • {selected_class.subject_title}
                                            </div>
                                        </div>

                                        <div className="grid gap-2">
                                            <div className="text-muted-foreground text-xs">Faculty</div>
                                            <div className="text-foreground font-medium">{selected_class.faculty?.label ?? "TBA"}</div>
                                            {selected_class.faculty?.email ? (
                                                <div className="text-muted-foreground text-xs">{selected_class.faculty.email}</div>
                                            ) : null}
                                        </div>

                                        <div className="grid gap-2">
                                            <div className="text-muted-foreground text-xs">Default room</div>
                                            <div className="text-foreground font-medium">
                                                {isOnlineDeliveryMode(selected_class.delivery_mode)
                                                    ? deliveryModeLabel(selected_class.delivery_mode)
                                                    : (selected_class.room?.label ?? "Unassigned")}
                                            </div>
                                        </div>

                                        {selected_class.classification === "college" ? (
                                            <div className="grid gap-2">
                                                <div className="text-muted-foreground text-xs">Courses</div>
                                                <div className="text-foreground text-sm">
                                                    {selected_class.course_codes.length ? selected_class.course_codes.join(", ") : "—"}
                                                </div>
                                            </div>
                                        ) : (
                                            <div className="grid gap-2">
                                                <div className="text-muted-foreground text-xs">Track / Strand</div>
                                                <div className="text-foreground text-sm">
                                                    {[selected_class.shs_track?.label, selected_class.shs_strand?.label]
                                                        .filter(Boolean)
                                                        .join(" • ") || "—"}
                                                </div>
                                            </div>
                                        )}

                                        <div className="grid gap-2">
                                            <div className="text-muted-foreground text-xs">Academic period</div>
                                            <div className="text-foreground text-sm">
                                                {selected_class.school_year} • Sem {selected_class.semester} • Section {selected_class.section}
                                            </div>
                                        </div>
                                    </div>
                                </TabsContent>

                                <TabsContent value="schedule" className="space-y-3">
                                    <div className="grid gap-3">
                                        {selected_class.schedules.length === 0 ? (
                                            <div className="text-muted-foreground rounded-lg border p-4 text-sm">No schedules.</div>
                                        ) : (
                                            selected_class.schedules.map((schedule) => (
                                                <div
                                                    key={`${schedule.day_of_week}-${schedule.start_time}-${schedule.end_time}`}
                                                    className="rounded-lg border p-4"
                                                >
                                                    <div className="flex items-center justify-between">
                                                        <div className="text-foreground font-medium">{schedule.day_of_week}</div>
                                                        <div className="text-muted-foreground text-sm">
                                                            {schedule.start_time} – {schedule.end_time}
                                                        </div>
                                                    </div>
                                                    <div className="text-muted-foreground mt-2 text-sm">
                                                        {isOnlineDeliveryMode(schedule.delivery_mode ?? selected_class.delivery_mode)
                                                            ? `${deliveryModeLabel(schedule.delivery_mode ?? selected_class.delivery_mode)} · No room reserved`
                                                            : `Face to face · Room: ${schedule.room?.label ?? "Unassigned"}`}
                                                    </div>
                                                </div>
                                            ))
                                        )}

                                        <Button
                                            type="button"
                                            variant="outline"
                                            onClick={() => {
                                                openSchedule(selected_class.id);
                                            }}
                                        >
                                            Edit schedule
                                        </Button>
                                    </div>
                                </TabsContent>

                                <TabsContent value="settings" className="space-y-3">
                                    <div className="grid gap-3 rounded-lg border p-4">
                                        <div className="grid gap-1">
                                            <div className="text-muted-foreground text-xs">Theme</div>
                                            <div className="text-foreground text-sm font-medium">{selected_class.settings.theme ?? "default"}</div>
                                        </div>
                                        <div className="grid grid-cols-2 gap-3">
                                            <div className="grid gap-1">
                                                <div className="text-muted-foreground text-xs">Background</div>
                                                <div className="text-foreground text-sm">{selected_class.settings.background_color ?? "—"}</div>
                                            </div>
                                            <div className="grid gap-1">
                                                <div className="text-muted-foreground text-xs">Accent</div>
                                                <div className="text-foreground text-sm">{selected_class.settings.accent_color ?? "—"}</div>
                                            </div>
                                        </div>

                                        <div className="grid gap-2">
                                            <div className="text-muted-foreground text-xs">Features</div>
                                            <div className="flex flex-wrap gap-2">
                                                {(
                                                    [
                                                        { key: "enable_announcements", label: "Announcements" },
                                                        { key: "enable_grade_visibility", label: "Grade visibility" },
                                                        { key: "enable_attendance_tracking", label: "Attendance" },
                                                        { key: "allow_late_submissions", label: "Late submissions" },
                                                        { key: "enable_discussion_board", label: "Discussion board" },
                                                        { key: "notify_students_on_schedule_changes", label: "Schedule alerts" },
                                                    ] as const
                                                ).map((toggle) => (
                                                    <Badge key={toggle.key} variant={selected_class.settings[toggle.key] ? "default" : "outline"}>
                                                        {toggle.label}
                                                    </Badge>
                                                ))}
                                            </div>
                                        </div>
                                    </div>

                                    <Button
                                        type="button"
                                        variant="outline"
                                        onClick={() => {
                                            setEditActiveTab("settings");
                                            setIsEditOpen(true);
                                        }}
                                    >
                                        Edit settings
                                    </Button>
                                </TabsContent>

                                <TabsContent value="relationships" className="space-y-3">
                                    <Tabs defaultValue="students">
                                        <TabsList className="w-full justify-start">
                                            <TabsTrigger value="students">Enrolled students</TabsTrigger>
                                            <TabsTrigger value="posts">Class posts</TabsTrigger>
                                        </TabsList>

                                        <TabsContent value="students" className="space-y-3">
                                            <div className="rounded-lg border">
                                                <Table>
                                                    <TableHeader>
                                                        <TableRow>
                                                            <TableHead>Student</TableHead>
                                                            <TableHead>Status</TableHead>
                                                            <TableHead className="text-right">Final</TableHead>
                                                        </TableRow>
                                                    </TableHeader>
                                                    <TableBody>
                                                        {selected_class.enrollments.length === 0 ? (
                                                            <TableRow>
                                                                <TableCell colSpan={3} className="text-muted-foreground h-20 text-center text-sm">
                                                                    No enrollments loaded.
                                                                </TableCell>
                                                            </TableRow>
                                                        ) : (
                                                            selected_class.enrollments.map((enrollment) => (
                                                                <TableRow key={enrollment.id}>
                                                                    <TableCell>
                                                                        <div className="flex flex-col">
                                                                            <span className="text-foreground font-medium">
                                                                                {enrollment.student?.name ?? "Unknown"}
                                                                            </span>
                                                                            <span className="text-muted-foreground text-xs">
                                                                                {enrollment.student?.course ?? "—"} •{" "}
                                                                                {enrollment.student?.academic_year ?? "—"}
                                                                            </span>
                                                                        </div>
                                                                    </TableCell>
                                                                    <TableCell>
                                                                        <Badge variant="outline" className="capitalize">
                                                                            {typeof enrollment.status === "boolean"
                                                                                ? enrollment.status
                                                                                    ? "enrolled"
                                                                                    : "dropped"
                                                                                : (enrollment.status ?? "enrolled")}
                                                                        </Badge>
                                                                    </TableCell>
                                                                    <TableCell className="text-muted-foreground text-right text-sm">
                                                                        {enrollment.total_average ?? "—"}
                                                                    </TableCell>
                                                                </TableRow>
                                                            ))
                                                        )}
                                                    </TableBody>
                                                </Table>
                                            </div>

                                            <Link href={showClass.url(selected_class.id)} className={buttonVariants({ variant: "outline" })}>
                                                Open full enrollments page
                                            </Link>
                                        </TabsContent>

                                        <TabsContent value="posts" className="space-y-3">
                                            <div className="grid gap-2">
                                                {selected_class.posts.length === 0 ? (
                                                    <div className="text-muted-foreground rounded-lg border p-4 text-sm">No posts loaded.</div>
                                                ) : (
                                                    selected_class.posts.map((post) => (
                                                        <div key={post.id} className="rounded-lg border p-4">
                                                            <div className="flex items-start justify-between gap-3">
                                                                <div className="min-w-0">
                                                                    <div className="text-foreground truncate font-medium">{post.title}</div>
                                                                    <div className="text-muted-foreground text-xs">Type: {post.type}</div>
                                                                </div>
                                                                <Badge variant="outline">#{post.id}</Badge>
                                                            </div>
                                                        </div>
                                                    ))
                                                )}
                                            </div>

                                            <Link href={showClass.url(selected_class.id)} className={buttonVariants({ variant: "outline" })}>
                                                Open full posts page
                                            </Link>
                                        </TabsContent>
                                    </Tabs>
                                </TabsContent>
                            </Tabs>
                        </div>
                    )}

                    <SheetFooter>
                        <div className="flex w-full flex-col gap-2">
                            <Button
                                type="button"
                                onClick={() => {
                                    if (!selected_class) return;
                                    setIsEditOpen(true);
                                    setEditActiveTab("details");
                                }}
                            >
                                <Pencil className="mr-2 h-4 w-4" />
                                Edit class
                            </Button>
                            {selected_class ? (
                                <Link href={showClass.url(selected_class.id)} className={buttonVariants({ variant: "outline" })}>
                                    <BookOpen className="mr-2 h-4 w-4" />
                                    Open class page
                                </Link>
                            ) : null}
                            <Button
                                type="button"
                                variant="ghost"
                                className="text-destructive hover:text-destructive"
                                onClick={() => {
                                    if (!selected_class) return;
                                    setPendingDelete({
                                        id: selected_class.id,
                                        record_title: selected_class.record_title,
                                    } as ClassRow);
                                }}
                            >
                                <Trash2 className="mr-2 h-4 w-4" />
                                Delete class
                            </Button>
                        </div>
                    </SheetFooter>
                </SheetContent>
            </Sheet>

            <ClassCompareDialog
                open={isCompareOpen}
                onOpenChange={setIsCompareOpen}
                initialClassIds={compareInitialClassIds}
                classes={classList.map((c) => ({
                    id: c.id,
                    record_title: c.record_title,
                    subject_code: c.subject_code,
                    section: c.section,
                    delivery_mode: c.delivery_mode,
                    delivery_summary: c.delivery_summary,
                }))}
            />
        </AdminLayout>
    );
}
