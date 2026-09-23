import AdminLayout from "@/components/administrators/admin-layout";
import { AdminDeferredSection } from "@/components/administrators/admin-skeleton";
import { Filters, type FilterFieldConfig, type Filter as FilterType } from "@/components/reui/filters";
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
import { Card, CardContent } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Separator } from "@/components/ui/separator";
import { Tabs, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { AdminLink } from "@/lib/admin-navigation";
import type { User } from "@/types/user";
import { Head, router } from "@inertiajs/react";
import type { SortingState } from "@tanstack/react-table";
import {
    Award,
    BookOpen,
    Briefcase,
    Building2,
    CalendarCheck,
    CheckCircle,
    Filter,
    GraduationCap,
    HelpCircle,
    Layers,
    LayoutGrid,
    List,
    Loader2,
    MapPin,
    Plus,
    RotateCcw,
    Search,
    Trash2,
    UserCheck,
    UserIcon,
    UserPlus,
    Users,
    X,
    XCircle,
    Zap,
} from "lucide-react";
import { useEffect, useMemo, useRef, useState } from "react";
import { toast } from "sonner";
import { createColumns, Student } from "./columns";
import { DataTable } from "./data-table";

declare let route: (name: string, params?: Record<string, unknown> | string | number) => string;

function parseSortOption(value: string): { sort: string; direction: "asc" | "desc" } {
    const [sort = "created_at", direction = "desc"] = value.split(":");

    return {
        sort,
        direction: direction === "asc" ? "asc" : "desc",
    };
}

interface StudentsIndexProps {
    user: User;
    filament: {
        students: {
            index_url: string;
            create_url: string;
        };
    };
    students?: {
        data: Student[];
        total: number;
        from: number;
        to: number;
        current_page: number;
        last_page: number;
        per_page: number;
        next_page_url: string | null;
        prev_page_url: string | null;
    };
    stats: {
        total_students: number;
        total_enrolled: number;
        total_applicants: number;
        total_graduated: number;
    };
    filters: {
        search?: string | null;
        type?: string | null;
        status?: string | null;
        course_id?: number | null;
        department_id?: number | null;
        year_level?: number | null;
        current_enrollment?: "enrolled" | "not_enrolled" | null;
        scholarship_type?: string | null;
        employment_status?: string | null;
        is_indigenous_person?: string | null;
        previous_semester_cleared?: string | null;
        trashed?: "active" | "trashed" | "all" | null;
        sort?: string | null;
        direction?: "asc" | "desc" | null;
        per_page?: number;
    };
    options: {
        types: { value: string; label: string }[];
        statuses: { value: string; label: string }[];
        courses: { value: string; label: string }[];
        departments: { value: string; label: string }[];
        year_levels: { value: string; label: string }[];
        scholarship_types: { value: string; label: string }[];
        employment_statuses: { value: string; label: string }[];
    };
}

type QuickCohort = "all" | "enrolled" | "applicant" | "graduated" | "trashed";

export default function AdministratorStudentsIndex({ user, students, stats, filters, options }: StudentsIndexProps) {
    const studentRows = students?.data ?? [];
    const [search, setSearch] = useState(filters.search || "");
    const searchInputRef = useRef<HTMLInputElement>(null);
    const debouncedSearchRef = useRef<ReturnType<typeof setTimeout> | null>(null);

    const [viewMode, setViewMode] = useState<"list" | "grid">("list");
    const [sortOption, setSortOption] = useState(`${filters.sort ?? "created_at"}:${filters.direction ?? "desc"}`);
    const [sorting, setSorting] = useState<SortingState>(() => {
        const initialSort = parseSortOption(`${filters.sort ?? "created_at"}:${filters.direction ?? "desc"}`);

        return [{ id: initialSort.sort, desc: initialSort.direction === "desc" }];
    });

    const [activeFilters, setActiveFilters] = useState<FilterType[]>([]);

    // Keyboard shortcut to focus search input: '/' or 'Cmd+K' / 'Ctrl+K'
    useEffect(() => {
        const handleKeyDown = (event: KeyboardEvent) => {
            if (
                (event.key === "/" || ((event.metaKey || event.ctrlKey) && event.key === "k")) &&
                document.activeElement?.tagName !== "INPUT" &&
                document.activeElement?.tagName !== "TEXTAREA"
            ) {
                event.preventDefault();
                searchInputRef.current?.focus();
            }
        };

        window.addEventListener("keydown", handleKeyDown);
        return () => window.removeEventListener("keydown", handleKeyDown);
    }, []);

    // Synchronize initial filters from server props
    useEffect(() => {
        setSearch(filters.search || "");
        const initialFilters: FilterType[] = [];
        const trashedValue = filters.trashed ?? "active";
        if (trashedValue !== "active") {
            initialFilters.push({ id: "trashed", field: "trashed", operator: "is", values: [trashedValue] });
        }
        if (filters.type) initialFilters.push({ id: "type", field: "type", operator: "is", values: [filters.type] });
        if (filters.status) initialFilters.push({ id: "status", field: "status", operator: "is", values: [filters.status] });
        if (filters.course_id) initialFilters.push({ id: "course_id", field: "course_id", operator: "is", values: [String(filters.course_id)] });
        if (filters.department_id)
            initialFilters.push({ id: "department_id", field: "department_id", operator: "is", values: [String(filters.department_id)] });
        if (filters.year_level) initialFilters.push({ id: "year_level", field: "year_level", operator: "is", values: [String(filters.year_level)] });
        if (filters.current_enrollment)
            initialFilters.push({
                id: "current_enrollment",
                field: "current_enrollment",
                operator: "is",
                values: [filters.current_enrollment],
            });
        if (filters.scholarship_type)
            initialFilters.push({ id: "scholarship_type", field: "scholarship_type", operator: "is", values: [filters.scholarship_type] });
        if (filters.employment_status)
            initialFilters.push({ id: "employment_status", field: "employment_status", operator: "is", values: [filters.employment_status] });
        if (filters.is_indigenous_person)
            initialFilters.push({
                id: "is_indigenous_person",
                field: "is_indigenous_person",
                operator: "is",
                values: [filters.is_indigenous_person],
            });
        if (filters.previous_semester_cleared)
            initialFilters.push({
                id: "previous_semester_cleared",
                field: "previous_semester_cleared",
                operator: "is",
                values: [filters.previous_semester_cleared],
            });
        setActiveFilters(initialFilters);

        const nextSortOption = `${filters.sort ?? "created_at"}:${filters.direction ?? "desc"}`;
        const nextSort = parseSortOption(nextSortOption);
        setSortOption(nextSortOption);
        setSorting([{ id: nextSort.sort, desc: nextSort.direction === "desc" }]);
    }, [filters]);

    const activeFilterValues = useMemo(
        () =>
            Object.fromEntries(
                activeFilters
                    .map((filter) => [filter.field, filter.values[0]] as const)
                    .filter((entry): entry is readonly [string, string | number] => typeof entry[1] === "string" || typeof entry[1] === "number")
                    .map(([field, value]) => [field, String(value)]),
            ),
        [activeFilters],
    );

    // Identify active quick cohort
    const currentCohort: QuickCohort = useMemo(() => {
        if (activeFilterValues.trashed === "trashed") {
            return "trashed";
        }
        const currentStatus = activeFilterValues.status;
        if (currentStatus === "enrolled") return "enrolled";
        if (currentStatus === "applicant") return "applicant";
        if (currentStatus === "graduated") return "graduated";
        return "all";
    }, [activeFilterValues.status, activeFilterValues.trashed]);

    const buildQueryParams = (
        overrides: {
            search?: string;
            filters?: FilterType[];
            sort?: string;
            direction?: "asc" | "desc";
            page?: number;
            per_page?: number;
        } = {},
    ) => {
        const nextFilters = overrides.filters ?? activeFilters;
        const filterMap = Object.fromEntries(
            nextFilters
                .map((filter) => [filter.field, filter.values[0]] as const)
                .filter((entry): entry is readonly [string, string | number] => typeof entry[1] === "string" || typeof entry[1] === "number")
                .map(([field, value]) => [field, String(value)]),
        );

        const activeSearch = overrides.search !== undefined ? overrides.search : search;
        const activeSort = overrides.sort !== undefined ? overrides.sort : (sorting[0]?.id ?? filters.sort ?? "created_at");
        const activeDirection = overrides.direction !== undefined ? overrides.direction : sorting[0]?.desc ? "desc" : "asc";
        const activePerPage = overrides.per_page !== undefined ? overrides.per_page : (filters.per_page ?? 20);
        const activePage = overrides.page !== undefined ? overrides.page : (students?.current_page ?? 1);

        const params: Record<string, string | number | null> = {
            search: activeSearch.trim() ? activeSearch.trim() : null,
            sort: activeSort,
            direction: activeDirection,
            per_page: activePerPage,
            page: activePage,
        };

        if (filterMap.trashed && filterMap.trashed !== "active") params.trashed = filterMap.trashed;
        if (filterMap.type) params.type = filterMap.type;
        if (filterMap.status) params.status = filterMap.status;
        if (filterMap.course_id) params.course_id = filterMap.course_id;
        if (filterMap.department_id) params.department_id = filterMap.department_id;
        if (filterMap.year_level) params.year_level = filterMap.year_level;
        if (filterMap.current_enrollment) params.current_enrollment = filterMap.current_enrollment;
        if (filterMap.scholarship_type) params.scholarship_type = filterMap.scholarship_type;
        if (filterMap.employment_status) params.employment_status = filterMap.employment_status;
        if (filterMap.is_indigenous_person) params.is_indigenous_person = filterMap.is_indigenous_person;
        if (filterMap.previous_semester_cleared) params.previous_semester_cleared = filterMap.previous_semester_cleared;

        return params;
    };

    const cancelPendingSearch = () => {
        if (debouncedSearchRef.current) {
            clearTimeout(debouncedSearchRef.current);
            debouncedSearchRef.current = null;
        }
    };

    useEffect(() => {
        return () => {
            cancelPendingSearch();
        };
    }, []);

    const navigateWithParams = (params: Record<string, string | number | null>) => {
        cancelPendingSearch();
        router.get(route("administrators.students.index"), params, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    };

    const handleSearchChange = (nextSearch: string) => {
        setSearch(nextSearch);
        cancelPendingSearch();
        debouncedSearchRef.current = setTimeout(() => {
            debouncedSearchRef.current = null;
            router.get(route("administrators.students.index"), buildQueryParams({ search: nextSearch, page: 1 }), {
                preserveState: true,
                preserveScroll: true,
                replace: true,
            });
        }, 350);
    };

    const handleClearSearch = () => {
        setSearch("");
        cancelPendingSearch();
        navigateWithParams(buildQueryParams({ search: "", page: 1 }));
    };

    const handleSelectCohort = (cohort: QuickCohort) => {
        const nextFilters = activeFilters.filter((f) => f.field !== "status" && f.field !== "trashed");

        if (cohort === "trashed") {
            nextFilters.push({ id: "trashed", field: "trashed", operator: "is", values: ["trashed"] });
        } else if (cohort !== "all") {
            nextFilters.push({ id: "status", field: "status", operator: "is", values: [cohort] });
        }

        setActiveFilters(nextFilters);
        navigateWithParams(buildQueryParams({ filters: nextFilters, page: 1 }));
    };

    const handleFiltersChange = (newFilters: FilterType[]) => {
        setActiveFilters(newFilters);
        navigateWithParams(buildQueryParams({ filters: newFilters, page: 1 }));
    };

    const clearFilters = () => {
        setActiveFilters([]);
        navigateWithParams(buildQueryParams({ filters: [], page: 1 }));
    };

    const clearAllFiltersAndSearch = () => {
        setActiveFilters([]);
        setSearch("");
        if (debouncedSearchRef.current) {
            clearTimeout(debouncedSearchRef.current);
        }
        navigateWithParams(buildQueryParams({ filters: [], search: "", page: 1 }));
    };

    const removeFilter = (field: string) => {
        const nextFilters = activeFilters.filter((f) => f.field !== field);
        setActiveFilters(nextFilters);
        navigateWithParams(buildQueryParams({ filters: nextFilters, page: 1 }));
    };

    const handleSortChange = (value: string | null) => {
        if (!value) return;
        setSortOption(value);
        const sort = parseSortOption(value);
        setSorting([{ id: sort.sort, desc: sort.direction === "desc" }]);
        navigateWithParams(buildQueryParams({ sort: sort.sort, direction: sort.direction, page: 1 }));
    };

    const handleTableSortingChange = (nextSorting: SortingState) => {
        setSorting(nextSorting);
        const nextSort = nextSorting[0];
        const sortField = nextSort?.id ?? "created_at";
        const direction = nextSort?.desc ? "desc" : "asc";
        setSortOption(`${sortField}:${direction}`);
        navigateWithParams(buildQueryParams({ sort: sortField, direction, page: 1 }));
    };

    const handlePageIndexChange = (newPageIndex: number) => {
        navigateWithParams(buildQueryParams({ page: newPageIndex + 1 }));
    };

    const handlePageSizeChange = (newPageSize: number) => {
        navigateWithParams(buildQueryParams({ per_page: newPageSize, page: 1 }));
    };

    const filterFields: FilterFieldConfig[] = useMemo(
        () => [
            {
                key: "trashed",
                label: "Record Visibility",
                type: "select",
                icon: <Filter className="size-4" />,
                options: [
                    { value: "active", label: "Active Records", icon: <CheckCircle className="size-4 text-emerald-500" /> },
                    { value: "trashed", label: "Trashed Records", icon: <Trash2 className="size-4 text-red-500" /> },
                    { value: "all", label: "All Records", icon: <Users className="size-4" /> },
                ],
            },
            {
                key: "status",
                label: "Enrollment Status",
                type: "select",
                icon: <GraduationCap className="size-4" />,
                options: options.statuses.map((opt) => ({ ...opt, icon: <GraduationCap className="text-muted-foreground size-4" /> })),
            },
            {
                key: "type",
                label: "Student Type",
                type: "select",
                icon: <UserIcon className="size-4" />,
                options: options.types.map((opt) => ({ ...opt, icon: <UserIcon className="text-muted-foreground size-4" /> })),
            },
            {
                key: "course_id",
                label: "Course / Degree",
                type: "select",
                icon: <BookOpen className="size-4" />,
                options: options.courses.map((opt) => ({ ...opt, icon: <BookOpen className="text-muted-foreground size-4" /> })),
            },
            {
                key: "department_id",
                label: "Department",
                type: "select",
                icon: <Building2 className="size-4" />,
                options: options.departments.map((opt) => ({ ...opt, icon: <Building2 className="text-muted-foreground size-4" /> })),
            },
            {
                key: "year_level",
                label: "Year Level",
                type: "select",
                icon: <Layers className="size-4" />,
                options: options.year_levels.map((opt) => ({ ...opt, icon: <Layers className="text-muted-foreground size-4" /> })),
            },
            {
                key: "current_enrollment",
                label: "Current Period Enrollment",
                type: "select",
                icon: <CalendarCheck className="size-4" />,
                options: [
                    { value: "enrolled", label: "Currently enrolled", icon: <UserCheck className="size-4 text-emerald-500" /> },
                    { value: "not_enrolled", label: "Not currently enrolled", icon: <XCircle className="size-4 text-red-500" /> },
                ],
            },
            {
                key: "previous_semester_cleared",
                label: "Clearance Status",
                type: "select",
                icon: <CheckCircle className="size-4" />,
                options: [
                    { value: "true", label: "Cleared", icon: <CheckCircle className="size-4 text-emerald-500" /> },
                    { value: "false", label: "Pending", icon: <HelpCircle className="size-4 text-amber-500" /> },
                ],
            },
            {
                key: "scholarship_type",
                label: "Scholarship",
                type: "select",
                icon: <Award className="size-4" />,
                options: options.scholarship_types.map((opt) => ({ ...opt, icon: <Award className="text-muted-foreground size-4" /> })),
            },
            {
                key: "employment_status",
                label: "Employment Status",
                type: "select",
                icon: <Briefcase className="size-4" />,
                options: options.employment_statuses.map((opt) => ({ ...opt, icon: <Briefcase className="text-muted-foreground size-4" /> })),
            },
            {
                key: "is_indigenous_person",
                label: "Indigenous Person",
                type: "select",
                icon: <MapPin className="size-4" />,
                options: [
                    { value: "yes", label: "Yes", icon: <CheckCircle className="size-4 text-emerald-500" /> },
                    { value: "no", label: "No", icon: <XCircle className="size-4 text-red-500" /> },
                ],
            },
        ],
        [options],
    );

    const getFilterFieldLabel = (field: string): string => {
        switch (field) {
            case "trashed":
                return "Visibility";
            case "status":
                return "Status";
            case "type":
                return "Type";
            case "course_id":
                return "Course";
            case "department_id":
                return "Department";
            case "year_level":
                return "Year";
            case "current_enrollment":
                return "Enrollment";
            case "previous_semester_cleared":
                return "Clearance";
            case "scholarship_type":
                return "Scholarship";
            case "employment_status":
                return "Employment";
            case "is_indigenous_person":
                return "Indigenous";
            default:
                return field;
        }
    };

    const getFilterValueLabel = (field: string, value: string | number): string => {
        const stringValue = String(value);
        switch (field) {
            case "trashed":
                return stringValue === "trashed" ? "Trashed" : stringValue === "all" ? "All" : "Active";
            case "course_id": {
                const found = options.courses.find((c) => c.value === stringValue);
                return found ? found.label.split(" - ")[0] : stringValue;
            }
            case "department_id": {
                const found = options.departments.find((d) => d.value === stringValue);
                return found ? found.label.split(" - ")[0] : stringValue;
            }
            case "year_level":
                return `Year ${stringValue}`;
            case "current_enrollment":
                return stringValue === "enrolled" ? "Enrolled" : "Not Enrolled";
            case "previous_semester_cleared":
                return stringValue === "true" ? "Cleared" : "Pending";
            case "is_indigenous_person":
                return stringValue === "yes" ? "Yes" : "No";
            case "status":
                return options.statuses.find((s) => s.value === stringValue)?.label ?? stringValue;
            case "type":
                return options.types.find((t) => t.value === stringValue)?.label ?? stringValue;
            case "scholarship_type":
                return options.scholarship_types.find((s) => s.value === stringValue)?.label ?? stringValue;
            case "employment_status":
                return options.employment_statuses.find((e) => e.value === stringValue)?.label ?? stringValue;
            default:
                return stringValue;
        }
    };

    const [softDeleteTarget, setSoftDeleteTarget] = useState<Student | null>(null);
    const [forceDeleteTarget, setForceDeleteTarget] = useState<Student | null>(null);
    const [restoreTarget, setRestoreTarget] = useState<Student | null>(null);
    const [confirmForceText, setConfirmForceText] = useState("");
    const [deleting, setDeleting] = useState(false);

    useEffect(() => {
        const onSoft = (e: Event) => setSoftDeleteTarget((e as CustomEvent<Student>).detail);
        const onForce = (e: Event) => {
            setForceDeleteTarget((e as CustomEvent<Student>).detail);
            setConfirmForceText("");
        };
        const onRestore = (e: Event) => setRestoreTarget((e as CustomEvent<Student>).detail);

        window.addEventListener("students:soft-delete", onSoft as EventListener);
        window.addEventListener("students:force-delete", onForce as EventListener);
        window.addEventListener("students:restore", onRestore as EventListener);

        return () => {
            window.removeEventListener("students:soft-delete", onSoft as EventListener);
            window.removeEventListener("students:force-delete", onForce as EventListener);
            window.removeEventListener("students:restore", onRestore as EventListener);
        };
    }, []);

    const handleConfirmSoftDelete = () => {
        if (!softDeleteTarget) return;
        setDeleting(true);
        router.delete(route("administrators.students.destroy", softDeleteTarget.id), {
            preserveScroll: true,
            onSuccess: () => {
                toast.success(`Student "${softDeleteTarget.name}" has been moved to trash.`);
                setSoftDeleteTarget(null);
            },
            onError: () => toast.error("Failed to delete student."),
            onFinish: () => setDeleting(false),
        });
    };

    const handleConfirmForceDelete = () => {
        if (!forceDeleteTarget || confirmForceText.trim() !== String(forceDeleteTarget.student_id ?? "").trim()) {
            toast.error("Student ID confirmation does not match.");
            return;
        }
        setDeleting(true);
        router.delete(route("administrators.students.force-destroy", forceDeleteTarget.id), {
            preserveScroll: true,
            onSuccess: () => {
                toast.success(`Student "${forceDeleteTarget.name}" has been permanently deleted.`);
                setForceDeleteTarget(null);
                setConfirmForceText("");
            },
            onError: () => toast.error("Failed to permanently delete student."),
            onFinish: () => setDeleting(false),
        });
    };

    const handleConfirmRestore = () => {
        if (!restoreTarget) return;
        setDeleting(true);
        router.post(
            route("administrators.students.restore", restoreTarget.id),
            {},
            {
                preserveScroll: true,
                onSuccess: () => {
                    toast.success(`Student "${restoreTarget.name}" has been restored.`);
                    setRestoreTarget(null);
                },
                onError: () => toast.error("Failed to restore student."),
                onFinish: () => setDeleting(false),
            },
        );
    };

    const tableColumns = useMemo(
        () =>
            createColumns({
                onSoftDelete: setSoftDeleteTarget,
                onForceDelete: (student) => {
                    setForceDeleteTarget(student);
                    setConfirmForceText("");
                },
                onRestore: setRestoreTarget,
            }),
        [],
    );

    const statCards = [
        {
            key: "all" as const,
            label: "Total records",
            value: stats.total_students,
            detail: "All active profiles",
            icon: Users,
            tone: "text-primary",
            bgTone: "bg-primary/10",
        },
        {
            key: "enrolled" as const,
            label: "Enrolled",
            value: stats.total_enrolled,
            detail: "Current period",
            icon: UserCheck,
            tone: "text-emerald-600 dark:text-emerald-400",
            bgTone: "bg-emerald-500/10",
        },
        {
            key: "applicant" as const,
            label: "Applicants",
            value: stats.total_applicants,
            detail: "Admissions pending",
            icon: UserPlus,
            tone: "text-amber-600 dark:text-amber-400",
            bgTone: "bg-amber-500/10",
        },
        {
            key: "graduated" as const,
            label: "Graduated",
            value: stats.total_graduated,
            detail: "Completed records",
            icon: GraduationCap,
            tone: "text-blue-600 dark:text-blue-400",
            bgTone: "bg-blue-500/10",
        },
    ];

    return (
        <AdminLayout user={user} title="Student Directory">
            <Head title="Administrators • Student Directory" />

            <div className="flex flex-col gap-5">
                {/* Header */}
                <header className="flex flex-col gap-4 border-b pb-5 sm:flex-row sm:items-end sm:justify-between">
                    <div className="space-y-1">
                        <div className="flex items-center gap-2">
                            <p className="text-muted-foreground text-[11px] font-semibold tracking-[0.16em] uppercase">Student Records</p>
                            <span className="text-muted-foreground/60 text-xs">•</span>
                            <span className="text-muted-foreground text-xs">Directory</span>
                        </div>
                        <h1 className="text-2xl font-bold tracking-tight sm:text-3xl">Student Directory</h1>
                        <p className="text-muted-foreground max-w-2xl text-xs leading-relaxed sm:text-sm">
                            Search, manage, and audit student profiles, enrollment statuses, and clearances across academic departments.
                        </p>
                    </div>

                    <div className="flex items-center gap-2">
                        <AdminLink
                            href={route("administrators.students.create")}
                            className={buttonVariants({ className: "h-9 w-full gap-2 text-xs font-semibold shadow-xs sm:w-auto sm:text-sm" })}
                        >
                            <Plus className="size-4" aria-hidden="true" />
                            Create student
                        </AdminLink>
                    </div>
                </header>

                {/* Interactive Metric Summary Strip */}
                <div className="grid grid-cols-2 gap-2.5 sm:grid-cols-2 xl:grid-cols-4">
                    {statCards.map((metric) => {
                        const isActive = currentCohort === metric.key;

                        return (
                            <Card
                                key={metric.key}
                                onClick={() => handleSelectCohort(metric.key)}
                                className={`hover:border-primary/50 cursor-pointer gap-0 py-0 transition-all hover:shadow-xs ${
                                    isActive ? "ring-primary border-primary/60 bg-primary/5 shadow-xs ring-2" : ""
                                }`}
                                role="button"
                                tabIndex={0}
                                onKeyDown={(e) => {
                                    if (e.key === "Enter" || e.key === " ") {
                                        e.preventDefault();
                                        handleSelectCohort(metric.key);
                                    }
                                }}
                                aria-label={`Filter by ${metric.label}`}
                            >
                                <CardContent className="flex items-center justify-between gap-3 p-3.5">
                                    <div className="min-w-0">
                                        <div className="flex items-center gap-1.5">
                                            <p className="text-muted-foreground truncate text-[10px] font-semibold tracking-wide uppercase sm:text-[11px]">
                                                {metric.label}
                                            </p>
                                            {isActive && <div className="bg-primary size-1.5 shrink-0 rounded-full" />}
                                        </div>
                                        <p className="text-foreground mt-0.5 text-xl font-bold tracking-tight tabular-nums sm:text-2xl">
                                            {metric.value}
                                        </p>
                                        <p className="text-muted-foreground mt-0.5 truncate text-[11px]">{metric.detail}</p>
                                    </div>
                                    <div className={`flex size-9 shrink-0 items-center justify-center rounded-lg sm:size-10 ${metric.bgTone}`}>
                                        <metric.icon className={`size-4 sm:size-5 ${metric.tone}`} aria-hidden="true" />
                                    </div>
                                </CardContent>
                            </Card>
                        );
                    })}
                </div>

                {/* Main Filter & Search Control Panel */}
                <Card className="border-border/80 gap-3 py-3 shadow-xs">
                    <CardContent className="space-y-3 px-3.5 sm:px-4">
                        {/* Quick Cohort Tabs */}
                        <div className="flex flex-wrap items-center gap-1.5 border-b pb-2.5">
                            <span className="text-muted-foreground mr-1 hidden text-xs font-semibold sm:inline">Cohort:</span>
                            <Button
                                variant={currentCohort === "all" ? "default" : "outline"}
                                size="sm"
                                onClick={() => handleSelectCohort("all")}
                                className="h-7 rounded-md px-2.5 text-xs"
                            >
                                All Records
                            </Button>
                            <Button
                                variant={currentCohort === "enrolled" ? "default" : "outline"}
                                size="sm"
                                onClick={() => handleSelectCohort("enrolled")}
                                className="h-7 gap-1.5 rounded-md px-2.5 text-xs"
                            >
                                <span className="size-1.5 rounded-full bg-emerald-500" />
                                Enrolled
                            </Button>
                            <Button
                                variant={currentCohort === "applicant" ? "default" : "outline"}
                                size="sm"
                                onClick={() => handleSelectCohort("applicant")}
                                className="h-7 gap-1.5 rounded-md px-2.5 text-xs"
                            >
                                <span className="size-1.5 rounded-full bg-amber-500" />
                                Applicants
                            </Button>
                            <Button
                                variant={currentCohort === "graduated" ? "default" : "outline"}
                                size="sm"
                                onClick={() => handleSelectCohort("graduated")}
                                className="h-7 gap-1.5 rounded-md px-2.5 text-xs"
                            >
                                <span className="size-1.5 rounded-full bg-blue-500" />
                                Graduated
                            </Button>
                            <Button
                                variant={currentCohort === "trashed" ? "destructive" : "ghost"}
                                size="sm"
                                onClick={() => handleSelectCohort("trashed")}
                                className="text-muted-foreground hover:text-foreground ml-auto h-7 gap-1.5 rounded-md px-2.5 text-xs"
                            >
                                <Trash2 className="size-3.5" />
                                Trashed
                            </Button>
                        </div>

                        {/* Search + Action Bar */}
                        <div className="flex flex-col justify-between gap-2.5 lg:flex-row lg:items-center">
                            {/* Search Field */}
                            <div className="relative min-w-0 flex-1">
                                <Search
                                    className="text-muted-foreground pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2"
                                    aria-hidden="true"
                                />
                                <Input
                                    ref={searchInputRef}
                                    placeholder="Search by student name, ID, course, or status... (Press '/' to focus)"
                                    className="bg-background/50 focus:bg-background h-9 pr-8 pl-9 text-xs sm:text-sm"
                                    value={search}
                                    onChange={(event) => handleSearchChange(event.target.value)}
                                />
                                {search && (
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        className="text-muted-foreground hover:text-foreground absolute top-1/2 right-2 size-5 -translate-y-1/2 rounded-full p-0"
                                        onClick={handleClearSearch}
                                        aria-label="Clear search"
                                    >
                                        <X className="size-3.5" />
                                    </Button>
                                )}
                            </div>

                            {/* Toolbar actions */}
                            <div className="flex flex-wrap items-center gap-2">
                                <Select value={sortOption} onValueChange={handleSortChange}>
                                    <SelectTrigger className="h-9 w-[170px] text-xs">
                                        <SelectValue placeholder="Sort students" />
                                    </SelectTrigger>
                                    <SelectContent align="end">
                                        <SelectItem value="created_at:desc" className="text-xs">
                                            Latest added
                                        </SelectItem>
                                        <SelectItem value="created_at:asc" className="text-xs">
                                            Oldest added
                                        </SelectItem>
                                        <SelectItem value="name:asc" className="text-xs">
                                            Name A–Z
                                        </SelectItem>
                                        <SelectItem value="name:desc" className="text-xs">
                                            Name Z–A
                                        </SelectItem>
                                        <SelectItem value="student_id:asc" className="text-xs">
                                            Student ID (ascending)
                                        </SelectItem>
                                        <SelectItem value="student_id:desc" className="text-xs">
                                            Student ID (descending)
                                        </SelectItem>
                                    </SelectContent>
                                </Select>

                                <Filters
                                    fields={filterFields}
                                    filters={activeFilters}
                                    onChange={handleFiltersChange}
                                    trigger={
                                        <Button variant="outline" className="relative h-9 gap-1.5 text-xs" size="sm">
                                            <Filter className="size-3.5" aria-hidden="true" />
                                            <span>Filters</span>
                                            {activeFilters.length > 0 && (
                                                <Badge
                                                    variant="secondary"
                                                    className="ml-0.5 flex size-5 items-center justify-center rounded-full p-0 text-[10px]"
                                                >
                                                    {activeFilters.length}
                                                </Badge>
                                            )}
                                        </Button>
                                    }
                                />

                                {activeFilters.length > 0 && (
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        onClick={clearFilters}
                                        className="text-muted-foreground hover:text-foreground h-9 gap-1 px-2 text-xs"
                                        title="Clear all filters"
                                    >
                                        <RotateCcw className="size-3" aria-hidden="true" />
                                        <span className="hidden sm:inline">Reset</span>
                                    </Button>
                                )}

                                <Separator orientation="vertical" className="mx-1 hidden h-6 sm:block" />

                                <Tabs value={viewMode} onValueChange={(value) => setViewMode(value as "list" | "grid")}>
                                    <TabsList className="grid h-9 grid-cols-2 p-0.5">
                                        <TabsTrigger value="list" className="h-8 px-2.5 text-xs" title="Table view" aria-label="Table view">
                                            <List className="size-4" aria-hidden="true" />
                                        </TabsTrigger>
                                        <TabsTrigger value="grid" className="h-8 px-2.5 text-xs" title="Grid cards view" aria-label="Grid cards view">
                                            <LayoutGrid className="size-4" aria-hidden="true" />
                                        </TabsTrigger>
                                    </TabsList>
                                </Tabs>
                            </div>
                        </div>

                        {/* Active Filter Removable Chips */}
                        {activeFilters.length > 0 && (
                            <div className="flex flex-wrap items-center gap-1.5 border-t pt-2.5">
                                <span className="text-muted-foreground mr-1 text-[11px] font-medium">Active filters:</span>
                                {activeFilters.map((f) => (
                                    <Badge
                                        key={f.id}
                                        variant="secondary"
                                        className="bg-muted hover:bg-muted/80 text-foreground gap-1.5 px-2 py-0.5 text-[11px] font-normal"
                                    >
                                        <span className="text-muted-foreground font-semibold">{getFilterFieldLabel(f.field)}:</span>
                                        <span>{getFilterValueLabel(f.field, f.values[0])}</span>
                                        <button
                                            type="button"
                                            onClick={() => removeFilter(f.field)}
                                            className="text-muted-foreground hover:bg-background hover:text-foreground ml-0.5 rounded-full p-0.5 transition-colors"
                                            aria-label={`Remove filter ${f.field}`}
                                        >
                                            <X className="size-3" />
                                        </button>
                                    </Badge>
                                ))}
                                <Button
                                    variant="link"
                                    size="sm"
                                    onClick={clearFilters}
                                    className="text-muted-foreground hover:text-foreground h-6 px-1.5 text-[11px]"
                                >
                                    Clear all
                                </Button>
                            </div>
                        )}
                    </CardContent>
                </Card>

                {/* Deferred Student Data Directory Section */}
                <AdminDeferredSection data="students" label="Loading student records" name="admin-administrators-students-index" variant="list">
                    <DataTable
                        columns={tableColumns}
                        data={studentRows}
                        pageIndex={(students?.current_page ?? 1) - 1}
                        pageSize={students?.per_page ?? filters.per_page ?? 20}
                        pageCount={students?.last_page ?? 1}
                        totalCount={students?.total ?? studentRows.length}
                        from={students?.from ?? (studentRows.length > 0 ? 1 : 0)}
                        to={students?.to ?? studentRows.length}
                        sorting={sorting}
                        viewMode={viewMode}
                        onPageIndexChange={handlePageIndexChange}
                        onPageSizeChange={handlePageSizeChange}
                        onSortingChange={handleTableSortingChange}
                        bulkActions={{ statusOptions: options.statuses }}
                        onSoftDelete={setSoftDeleteTarget}
                        onForceDelete={(student) => {
                            setForceDeleteTarget(student);
                            setConfirmForceText("");
                        }}
                        onRestore={setRestoreTarget}
                        onClearFilters={clearAllFiltersAndSearch}
                    />
                </AdminDeferredSection>
            </div>

            {/* Individual Soft Delete Dialog */}
            <AlertDialog open={!!softDeleteTarget} onOpenChange={(open) => !open && setSoftDeleteTarget(null)}>
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle className="flex items-center gap-2">
                            <Trash2 className="size-5 text-amber-600" />
                            Move Student to Trash?
                        </AlertDialogTitle>
                        <AlertDialogDescription>
                            <strong className="text-foreground">{softDeleteTarget?.name}</strong> will be moved to trash and hidden from default
                            views. You can restore them at any time from the Trashed view.
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel disabled={deleting}>Cancel</AlertDialogCancel>
                        <AlertDialogAction
                            onClick={handleConfirmSoftDelete}
                            disabled={deleting}
                            className="bg-amber-600 text-white hover:bg-amber-700 dark:bg-amber-600 dark:hover:bg-amber-700"
                        >
                            {deleting ? <Loader2 className="mr-2 size-4 animate-spin" /> : <Trash2 className="mr-2 size-4" />}
                            Move to Trash
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>

            {/* Individual Permanent Delete Dialog */}
            <AlertDialog open={!!forceDeleteTarget} onOpenChange={(open) => !open && !deleting && setForceDeleteTarget(null)}>
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle className="text-destructive flex items-center gap-2">
                            <Zap className="size-5" />
                            Permanently Delete Student?
                        </AlertDialogTitle>
                        <AlertDialogDescription>
                            This will permanently erase <strong className="text-foreground">{forceDeleteTarget?.name}</strong> along with all
                            enrollments, tuition, transactions, clearances, grades, and records. This action{" "}
                            <strong className="text-foreground">cannot be undone</strong>.
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <div className="space-y-2 py-1">
                        <Label htmlFor="index-force-confirm">
                            Type <span className="text-foreground font-mono font-semibold">{forceDeleteTarget?.student_id}</span> to confirm:
                        </Label>
                        <Input
                            id="index-force-confirm"
                            value={confirmForceText}
                            onChange={(e) => setConfirmForceText(e.target.value)}
                            placeholder={String(forceDeleteTarget?.student_id ?? "")}
                            autoComplete="off"
                            disabled={deleting}
                        />
                    </div>
                    <AlertDialogFooter>
                        <AlertDialogCancel disabled={deleting}>Cancel</AlertDialogCancel>
                        <Button
                            onClick={handleConfirmForceDelete}
                            disabled={deleting || confirmForceText.trim() !== String(forceDeleteTarget?.student_id ?? "").trim()}
                            className="bg-destructive text-destructive-foreground hover:bg-destructive/90"
                        >
                            {deleting ? <Loader2 className="mr-2 size-4 animate-spin" /> : <Zap className="mr-2 size-4" />}
                            Permanently Delete
                        </Button>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>

            {/* Individual Restore Dialog */}
            <AlertDialog open={!!restoreTarget} onOpenChange={(open) => !open && setRestoreTarget(null)}>
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle className="flex items-center gap-2">
                            <RotateCcw className="text-primary size-5" />
                            Restore Student Record?
                        </AlertDialogTitle>
                        <AlertDialogDescription>
                            <strong className="text-foreground">{restoreTarget?.name}</strong> will be restored to active status and reappear in the
                            active student directory.
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel disabled={deleting}>Cancel</AlertDialogCancel>
                        <AlertDialogAction onClick={handleConfirmRestore} disabled={deleting}>
                            {deleting ? <Loader2 className="mr-2 size-4 animate-spin" /> : <RotateCcw className="mr-2 size-4" />}
                            Restore Student
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </AdminLayout>
    );
}
