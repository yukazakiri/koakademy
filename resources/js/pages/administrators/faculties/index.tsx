import AdminLayout from "@/components/administrators/admin-layout";
import { Alert, AlertDescription, AlertTitle } from "@/components/reui/alert";
import { Badge } from "@/components/reui/badge";
import { Filters, type Filter, type FilterFieldConfig } from "@/components/reui/filters";
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
import { Button, buttonVariants } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Checkbox } from "@/components/ui/checkbox";
import { Empty, EmptyContent, EmptyDescription, EmptyHeader, EmptyMedia, EmptyTitle } from "@/components/ui/empty";
import { Input } from "@/components/ui/input";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { cn } from "@/lib/utils";
import type { User } from "@/types/user";
import { Head, Link, router } from "@inertiajs/react";
import {
    AlertTriangle,
    ArrowUpDown,
    BookOpen,
    Building2,
    CheckCircle2,
    Download,
    ExternalLink,
    FileSpreadsheet,
    Filter as FilterIcon,
    KeyRound,
    LayoutGrid,
    List,
    PauseCircle,
    Plus,
    Search,
    ShieldAlert,
    Trash2,
    Users,
    UsersRound,
    X,
    XCircle,
} from "lucide-react";
import { useCallback, useEffect, useMemo, useRef, useState } from "react";
import { toast } from "sonner";
import { useDebouncedCallback } from "use-debounce";
import { route } from "ziggy-js";
import { getColumns, type FacultyRow } from "./columns";
import { DataTable } from "./data-table";
import { FacultyCard } from "./faculty-card";
import { FacultyImportDialog } from "./faculty-import-dialog";

type Segment = {
    value: string;
    label: string;
};

interface FacultiesIndexProps {
    user: User;
    filament: {
        faculties: {
            index_url: string;
            create_url: string;
        };
    };
    stats: {
        total: number;
        active: number;
        inactive: number;
        on_leave: number;
        with_current_classes: number;
        needs_classes: number;
        portal_not_linked: number;
        incomplete_profile: number;
        unassigned_current_classes: number;
    };
    segments: Segment[];
    faculties: {
        data: FacultyRow[];
        prev_page_url: string | null;
        next_page_url: string | null;
        total: number;
        from: number;
        to: number;
        current_page: number;
        last_page: number;
        per_page: number;
    };
    filters: {
        search?: string | null;
        department?: string | null;
        status?: string | null;
        current_classes?: string | null;
        portal?: string | null;
        profile?: string | null;
        segment?: string | null;
        sort?: string | null;
        direction?: "asc" | "desc";
        per_page?: number;
    };
    options: {
        departments: string[];
        statuses: { value: string; label: string }[];
        current_classes: { value: string; label: string }[];
        portal: { value: string; label: string }[];
        profile: { value: string; label: string }[];
        sorts: { value: string; label: string }[];
    };
}

function normalizeFilterValue(value: string | null | undefined): string | null {
    return value && value !== "all" ? value : null;
}

export default function AdministratorFacultiesIndex({ user, filament, stats, segments, faculties, filters, options }: FacultiesIndexProps) {
    const [search, setSearch] = useState(filters.search || "");
    const [activeFilters, setActiveFilters] = useState<Filter[]>([]);
    const [selectedRows, setSelectedRows] = useState<FacultyRow[]>([]);
    const [bulkStatus, setBulkStatus] = useState("active");
    const [viewMode, setViewMode] = useState<"table" | "grid">("table");
    const [deleteModal, setDeleteModal] = useState<{ open: boolean; id: string; name: string }>({
        open: false,
        id: "",
        name: "",
    });

    const activeFiltersRef = useRef<Filter[]>([]);

    useEffect(() => {
        activeFiltersRef.current = activeFilters;
    }, [activeFilters]);

    const baseQuery = useCallback(
        (newFilters = activeFiltersRef.current) => {
            const applied: Record<string, string | number | null | undefined> = {
                department: null,
                status: null,
                current_classes: null,
                portal: null,
                profile: null,
                segment: filters.segment || "all",
                sort: filters.sort || "faculty",
                direction: filters.direction || "asc",
                per_page: faculties.per_page,
            };

            newFilters.forEach((filter) => {
                applied[filter.field] = filter.values.length > 0 ? String(filter.values[0]) : null;
            });

            return applied;
        },
        [faculties.per_page, filters.direction, filters.segment, filters.sort],
    );

    useEffect(() => {
        setSearch(filters.search || "");

        const initial: Filter[] = [];
        const fields = ["department", "status", "current_classes", "portal", "profile"] as const;

        fields.forEach((field) => {
            const value = normalizeFilterValue(filters[field]);
            if (value) {
                initial.push({ id: field, field, operator: "is", values: [value] });
            }
        });

        setActiveFilters(initial);
    }, [filters]);

    const visitIndex = (query: Record<string, string | number | null | undefined>) => {
        router.get(route("administrators.faculties.index"), query, { preserveState: true, replace: true });
    };

    const handleSearch = useDebouncedCallback(
        (term: string) => {
            visitIndex({
                ...baseQuery(),
                search: term.trim() ? term : null,
                page: 1,
            });
        },
        300,
        { maxWait: 750 },
    );

    const handleFiltersChange = (newFilters: Filter[]) => {
        setActiveFilters(newFilters);
        visitIndex({
            ...baseQuery(newFilters),
            search: search.trim() ? search : null,
            page: 1,
        });
    };

    const removeFilter = (field: string) => {
        const next = activeFilters.filter((f) => f.field !== field);
        handleFiltersChange(next);
    };

    const handleSegment = (segment: string) => {
        visitIndex({
            ...baseQuery(),
            search: search.trim() ? search : null,
            segment,
            page: 1,
        });
    };

    const handleSort = (sort: string | null) => {
        if (!sort) return;

        visitIndex({
            ...baseQuery(),
            search: search.trim() ? search : null,
            sort,
            page: 1,
        });
    };

    const handleDirection = (direction: string | null) => {
        if (!direction) return;

        visitIndex({
            ...baseQuery(),
            search: search.trim() ? search : null,
            direction,
            page: 1,
        });
    };

    const handlePerPage = (perPage: string | null) => {
        if (!perPage) return;

        visitIndex({
            ...baseQuery(),
            search: search.trim() ? search : null,
            per_page: Number(perPage),
            page: 1,
        });
    };

    const handleQuickDepartment = (department: string) => {
        const existing = activeFilters.filter((f) => f.field !== "department");
        if (department === "all") {
            handleFiltersChange(existing);
        } else {
            handleFiltersChange([...existing, { id: "department", field: "department", operator: "is", values: [department] }]);
        }
    };

    const handleReset = () => {
        setSearch("");
        setActiveFilters([]);
        visitIndex({
            search: null,
            department: null,
            status: null,
            current_classes: null,
            portal: null,
            profile: null,
            segment: "all",
            sort: "faculty",
            direction: "asc",
            per_page: faculties.per_page,
            page: 1,
        });
    };

    const promptDelete = useCallback((id: string, name: string) => {
        setDeleteModal({ open: true, id, name });
    }, []);

    const confirmDelete = () => {
        if (!deleteModal.id) return;

        router.delete(route("administrators.faculties.destroy", deleteModal.id), {
            preserveScroll: true,
            onSuccess: () => {
                toast.success(`Faculty ${deleteModal.name} deleted`);
                setDeleteModal({ open: false, id: "", name: "" });
            },
        });
    };

    const applyBulkStatus = () => {
        if (selectedRows.length === 0) return;

        router.patch(
            route("administrators.faculties.bulk.status"),
            {
                faculty_ids: selectedRows.map((row) => row.id),
                status: bulkStatus,
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    toast.success(`Updated status for ${selectedRows.length} faculty`);
                    setSelectedRows([]);
                },
            },
        );
    };

    const exportToCSV = (rowsToExport = selectedRows.length > 0 ? selectedRows : faculties.data) => {
        if (rowsToExport.length === 0) {
            toast.error("No faculty records available to export");
            return;
        }

        const headers = ["Faculty ID", "Name", "Email", "Department", "Status", "Classes Count", "Workload", "Portal Status", "Profile %"];
        const rows = rowsToExport.map((row) => [
            `"${row.faculty_id_number || ""}"`,
            `"${row.name.replace(/"/g, '""')}"`,
            `"${row.email}"`,
            `"${(row.department || "None").replace(/"/g, '""')}"`,
            `"${row.status || "Unknown"}"`,
            row.current_classes_count,
            `"${row.workload_summary.label}"`,
            `"${row.portal_account.label}"`,
            `${row.profile_completion.percent}%`,
        ]);

        const csvContent = [headers.join(","), ...rows.map((r) => r.join(","))].join("\n");
        const blob = new Blob([csvContent], { type: "text/csv;charset=utf-8;" });
        const url = URL.createObjectURL(blob);
        const link = document.createElement("a");
        link.setAttribute("href", url);
        link.setAttribute("download", `faculty_export_${new Date().toISOString().slice(0, 10)}.csv`);
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        toast.success(`Exported ${rowsToExport.length} faculty records to CSV`);
    };

    const columns = useMemo(() => getColumns({ onDelete: promptDelete }), [promptDelete]);

    const filterFields: FilterFieldConfig[] = useMemo(
        () => [
            {
                key: "department",
                label: "Department",
                type: "select",
                icon: <Building2 className="h-4 w-4" />,
                options: options.departments.map((department) => ({
                    value: department,
                    label: department,
                    icon: <Building2 className="text-muted-foreground h-4 w-4" />,
                })),
            },
            {
                key: "status",
                label: "Status",
                type: "select",
                icon: <CheckCircle2 className="h-4 w-4" />,
                options: options.statuses.map((option) => ({
                    ...option,
                    icon:
                        option.value === "on_leave" ? (
                            <PauseCircle className="text-warning h-4 w-4" />
                        ) : option.value === "active" ? (
                            <CheckCircle2 className="text-success h-4 w-4" />
                        ) : (
                            <XCircle className="text-muted-foreground h-4 w-4" />
                        ),
                })),
            },
            {
                key: "current_classes",
                label: "Current Classes",
                type: "select",
                icon: <BookOpen className="h-4 w-4" />,
                options: options.current_classes.map((option) => ({
                    ...option,
                    icon: <BookOpen className="text-muted-foreground h-4 w-4" />,
                })),
            },
            {
                key: "portal",
                label: "Portal Access",
                type: "select",
                icon: <KeyRound className="h-4 w-4" />,
                options: options.portal.map((option) => ({
                    ...option,
                    icon: <KeyRound className="text-muted-foreground h-4 w-4" />,
                })),
            },
            {
                key: "profile",
                label: "Profile Completeness",
                type: "select",
                icon: <AlertTriangle className="h-4 w-4" />,
                options: options.profile.map((option) => ({
                    ...option,
                    icon: <AlertTriangle className="text-muted-foreground h-4 w-4" />,
                })),
            },
        ],
        [options],
    );

    const activeSegment = filters.segment || "all";
    const hasAnyControlsActive = search.trim().length > 0 || activeFilters.length > 0 || activeSegment !== "all";

    const segmentCount = (segment: string): number => {
        if (segment === "needs_classes") return stats.needs_classes;
        if (segment === "on_leave") return stats.on_leave;
        if (segment === "portal_not_linked") return stats.portal_not_linked;
        if (segment === "incomplete_profile") return stats.incomplete_profile;

        return stats.total;
    };

    const toggleSelectRowById = (id: string) => {
        const exists = selectedRows.some((row) => row.id === id);
        if (exists) {
            setSelectedRows(selectedRows.filter((row) => row.id !== id));
        } else {
            const found = faculties.data.find((row) => row.id === id);
            if (found) {
                setSelectedRows([...selectedRows, found]);
            }
        }
    };

    const toggleSelectAllGrid = () => {
        if (selectedRows.length === faculties.data.length) {
            setSelectedRows([]);
        } else {
            setSelectedRows([...faculties.data]);
        }
    };

    const tableToolbar = (
        <div className="flex flex-wrap items-center gap-2">
            <div className="relative w-full sm:w-72">
                <Search className="text-muted-foreground absolute top-2.5 left-2.5 h-4 w-4" />
                <Input
                    placeholder="Search ID, name, or email..."
                    className="pr-8 pl-8"
                    value={search}
                    onChange={(event) => {
                        setSearch(event.target.value);
                        handleSearch(event.target.value);
                    }}
                />
                {search ? (
                    <button
                        type="button"
                        onClick={() => {
                            setSearch("");
                            handleSearch("");
                        }}
                        className="text-muted-foreground hover:text-foreground absolute top-2.5 right-2.5"
                    >
                        <X className="h-4 w-4" />
                    </button>
                ) : null}
            </div>

            <Filters
                fields={filterFields}
                filters={activeFilters}
                onChange={handleFiltersChange}
                trigger={
                    <Button variant="outline" size="sm" className="gap-2">
                        <FilterIcon className="h-4 w-4" />
                        Filters
                        {activeFilters.length > 0 ? (
                            <Badge variant="secondary" size="xs" radius="full">
                                {activeFilters.length}
                            </Badge>
                        ) : null}
                    </Button>
                }
            />

            {options.departments.length > 0 ? (
                <Select value={filters.department || "all"} onValueChange={(val) => handleQuickDepartment(val)}>
                    <SelectTrigger className="h-8 w-40 text-xs">
                        <Building2 className="text-muted-foreground mr-1.5 h-3.5 w-3.5" />
                        <SelectValue placeholder="Department" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="all">All Departments</SelectItem>
                        {options.departments.map((dept) => (
                            <SelectItem key={dept} value={dept}>
                                {dept}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
            ) : null}

            {hasAnyControlsActive ? (
                <Button variant="ghost" size="sm" onClick={handleReset} className="text-muted-foreground hover:text-foreground">
                    <XCircle className="mr-1.5 h-4 w-4" />
                    Reset
                </Button>
            ) : null}
        </div>
    );

    return (
        <AdminLayout user={user} title="Faculties">
            <Head title="Administrators - Faculty Management" />

            <div className="flex flex-col gap-6">
                {/* Page Hero Header */}
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div className="flex flex-col gap-1">
                        <div className="flex items-center gap-2">
                            <h2 className="text-2xl font-bold tracking-tight">Faculty Management</h2>
                            <Badge variant="primary-light" size="sm" radius="full" className="gap-1 font-mono">
                                <Users className="h-3 w-3" />
                                <span className="tabular-nums">{stats.total}</span> total
                            </Badge>
                        </div>
                        <p className="text-muted-foreground text-sm">
                            Oversee academic workload, portal credentials, class assignments, and faculty profile readiness.
                        </p>
                    </div>

                    <div className="flex flex-wrap items-center gap-2">
                        <a href={route("administrators.faculties.imports.template")} className={buttonVariants({ variant: "outline", size: "sm" })}>
                            <FileSpreadsheet className="mr-1.5 h-4 w-4" /> Download Template
                        </a>

                        <FacultyImportDialog />

                        <Button variant="outline" size="sm" onClick={() => exportToCSV()}>
                            <Download className="mr-1.5 h-4 w-4" /> Export CSV
                        </Button>

                        <a
                            href={filament.faculties.index_url}
                            target="_blank"
                            rel="noreferrer"
                            className={buttonVariants({ variant: "outline", size: "sm" })}
                        >
                            <ExternalLink className="mr-1.5 h-4 w-4" /> Filament
                        </a>

                        <Link href={route("administrators.faculties.create")} className={buttonVariants({ size: "sm" })}>
                            <Plus className="mr-1.5 h-4 w-4" /> Add Faculty
                        </Link>
                    </div>
                </div>

                {/* ReUI Alert for Unassigned Classes */}
                {stats.unassigned_current_classes > 0 ? (
                    <Alert variant="warning">
                        <ShieldAlert />
                        <AlertTitle>Unassigned Academic Load</AlertTitle>
                        <AlertDescription className="flex items-center justify-between gap-3">
                            <span>
                                <strong className="tabular-nums">{stats.unassigned_current_classes} active class(es)</strong> currently have no
                                assigned faculty member.
                            </span>
                            <Button variant="outline" size="sm" className="h-7 text-xs" onClick={() => handleSegment("needs_classes")}>
                                Review Available Faculty
                            </Button>
                        </AlertDescription>
                    </Alert>
                ) : null}

                {/* Operational KPI Metric Cards with ReUI IconTile */}
                <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    <Card
                        className={cn(
                            "hover:border-primary/50 cursor-pointer transition-colors",
                            activeSegment === "all" && "border-primary/60 bg-primary/[0.02]",
                        )}
                        onClick={() => handleSegment("all")}
                    >
                        <CardHeader className="flex flex-row items-center justify-between pb-2">
                            <CardTitle className="text-muted-foreground text-xs font-medium tracking-wider uppercase">Faculty Pool</CardTitle>
                            <IconTile variant="soft" size="sm" className="text-primary">
                                <UsersRound />
                            </IconTile>
                        </CardHeader>
                        <CardContent className="flex flex-col gap-1">
                            <div className="text-2xl font-bold tracking-tight tabular-nums">{stats.total}</div>
                            <div className="text-muted-foreground flex items-center gap-2 text-xs">
                                <span className="text-success font-medium tabular-nums">{stats.active} active</span>
                                <span>&bull;</span>
                                <span className="tabular-nums">{stats.on_leave} on leave</span>
                                <span>&bull;</span>
                                <span className="tabular-nums">{stats.inactive} inactive</span>
                            </div>
                        </CardContent>
                    </Card>

                    <Card
                        className={cn(
                            "hover:border-destructive/50 cursor-pointer transition-colors",
                            activeSegment === "needs_classes" && "border-destructive/60 bg-destructive/[0.02]",
                        )}
                        onClick={() => handleSegment("needs_classes")}
                    >
                        <CardHeader className="flex flex-row items-center justify-between pb-2">
                            <CardTitle className="text-muted-foreground text-xs font-medium tracking-wider uppercase">Needs Teaching Load</CardTitle>
                            <IconTile variant="soft" size="sm" className="text-destructive">
                                <BookOpen />
                            </IconTile>
                        </CardHeader>
                        <CardContent className="flex flex-col gap-1">
                            <div className="flex items-center gap-2">
                                <span className="text-2xl font-bold tracking-tight tabular-nums">{stats.needs_classes}</span>
                                {stats.needs_classes > 0 ? (
                                    <Badge variant="destructive-light" size="xs" radius="full">
                                        Action needed
                                    </Badge>
                                ) : (
                                    <Badge variant="success-light" size="xs" radius="full">
                                        All assigned
                                    </Badge>
                                )}
                            </div>
                            <div className="text-muted-foreground text-xs">
                                <span className="text-foreground font-medium tabular-nums">{stats.with_current_classes}</span> faculty currently
                                teaching
                            </div>
                        </CardContent>
                    </Card>

                    <Card
                        className={cn(
                            "hover:border-warning/50 cursor-pointer transition-colors",
                            activeSegment === "portal_not_linked" && "border-warning/60 bg-warning/[0.02]",
                        )}
                        onClick={() => handleSegment("portal_not_linked")}
                    >
                        <CardHeader className="flex flex-row items-center justify-between pb-2">
                            <CardTitle className="text-muted-foreground text-xs font-medium tracking-wider uppercase">Portal Access</CardTitle>
                            <IconTile variant="soft" size="sm" className="text-warning">
                                <KeyRound />
                            </IconTile>
                        </CardHeader>
                        <CardContent className="flex flex-col gap-1">
                            <div className="flex items-center gap-2">
                                <span className="text-2xl font-bold tracking-tight tabular-nums">{stats.portal_not_linked}</span>
                                {stats.portal_not_linked > 0 ? (
                                    <Badge variant="warning-light" size="xs" radius="full">
                                        Unlinked
                                    </Badge>
                                ) : (
                                    <Badge variant="success-light" size="xs" radius="full">
                                        100% Synced
                                    </Badge>
                                )}
                            </div>
                            <div className="text-muted-foreground text-xs">
                                <span className="text-foreground font-medium tabular-nums">{stats.total - stats.portal_not_linked}</span> linked
                                faculty accounts
                            </div>
                        </CardContent>
                    </Card>

                    <Card
                        className={cn(
                            "hover:border-info/50 cursor-pointer transition-colors",
                            activeSegment === "incomplete_profile" && "border-info/60 bg-info/[0.02]",
                        )}
                        onClick={() => handleSegment("incomplete_profile")}
                    >
                        <CardHeader className="flex flex-row items-center justify-between pb-2">
                            <CardTitle className="text-muted-foreground text-xs font-medium tracking-wider uppercase">Profile Quality</CardTitle>
                            <IconTile variant="soft" size="sm" className="text-info">
                                <AlertTriangle />
                            </IconTile>
                        </CardHeader>
                        <CardContent className="flex flex-col gap-1">
                            <div className="flex items-center gap-2">
                                <span className="text-2xl font-bold tracking-tight tabular-nums">{stats.incomplete_profile}</span>
                                {stats.incomplete_profile > 0 ? (
                                    <Badge variant="info-light" size="xs" radius="full">
                                        Incomplete
                                    </Badge>
                                ) : (
                                    <Badge variant="success-light" size="xs" radius="full">
                                        Complete
                                    </Badge>
                                )}
                            </div>
                            <div className="text-muted-foreground text-xs">Missing contact, bio, or office hours</div>
                        </CardContent>
                    </Card>
                </div>

                {/* Segments Pill Filter */}
                <div className="flex items-center justify-between gap-2 overflow-x-auto pb-1">
                    <div className="flex items-center gap-2">
                        {segments.map((segment) => {
                            const count = segmentCount(segment.value);
                            const isActive = activeSegment === segment.value;

                            return (
                                <Button
                                    key={segment.value}
                                    variant={isActive ? "default" : "outline"}
                                    size="sm"
                                    onClick={() => handleSegment(segment.value)}
                                    className={cn("h-8 shrink-0 gap-2 text-xs font-medium", isActive && "shadow-xs")}
                                >
                                    {segment.label}
                                    <Badge
                                        variant={isActive ? "secondary" : "outline"}
                                        size="xs"
                                        radius="full"
                                        className={cn("tabular-nums", isActive && "bg-background/20 text-current")}
                                    >
                                        {count}
                                    </Badge>
                                </Button>
                            );
                        })}
                    </div>

                    <div className="flex shrink-0 items-center gap-1">
                        <Button
                            variant={viewMode === "table" ? "secondary" : "ghost"}
                            size="sm"
                            className="h-8 w-8 p-0"
                            onClick={() => setViewMode("table")}
                            title="Table View"
                        >
                            <List className="h-4 w-4" />
                        </Button>
                        <Button
                            variant={viewMode === "grid" ? "secondary" : "ghost"}
                            size="sm"
                            className="h-8 w-8 p-0"
                            onClick={() => setViewMode("grid")}
                            title="Directory Grid View"
                        >
                            <LayoutGrid className="h-4 w-4" />
                        </Button>
                    </div>
                </div>

                {/* Main Directory Card */}
                <Card className="overflow-hidden">
                    <CardHeader className="bg-muted/10 gap-4 border-b pb-3 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <CardTitle className="text-lg">Faculty Directory</CardTitle>
                            <CardDescription>
                                Browse, filter, sort, and execute administrative bulk operations across faculty records.
                            </CardDescription>
                        </div>

                        <div className="flex flex-wrap items-center gap-2">
                            <Select value={filters.sort || "faculty"} onValueChange={handleSort}>
                                <SelectTrigger className="h-8 w-40 text-xs">
                                    <ArrowUpDown className="text-muted-foreground mr-1.5 h-3.5 w-3.5" />
                                    <SelectValue placeholder="Sort by" />
                                </SelectTrigger>
                                <SelectContent>
                                    {options.sorts.map((option) => (
                                        <SelectItem key={option.value} value={option.value}>
                                            {option.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>

                            <Select value={filters.direction || "asc"} onValueChange={handleDirection}>
                                <SelectTrigger className="h-8 w-24 text-xs">
                                    <SelectValue placeholder="Order" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="asc">Ascending</SelectItem>
                                    <SelectItem value="desc">Descending</SelectItem>
                                </SelectContent>
                            </Select>

                            <Select value={String(faculties.per_page)} onValueChange={handlePerPage}>
                                <SelectTrigger className="h-8 w-24 text-xs">
                                    <SelectValue placeholder="Per page" />
                                </SelectTrigger>
                                <SelectContent>
                                    {[10, 20, 50, 100].map((size) => (
                                        <SelectItem key={size} value={String(size)}>
                                            {size} / page
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                    </CardHeader>

                    {/* Active Filters Chips Bar */}
                    {activeFilters.length > 0 ? (
                        <div className="bg-muted/20 flex flex-wrap items-center gap-1.5 border-b px-4 py-2">
                            <span className="text-muted-foreground text-xs font-medium">Applied Filters:</span>
                            {activeFilters.map((f) => (
                                <Badge key={f.id} variant="secondary" size="xs" radius="full" className="gap-1">
                                    <span className="capitalize">{f.field.replace("_", " ")}:</span>
                                    <span className="font-semibold">{String(f.values[0])}</span>
                                    <button
                                        type="button"
                                        onClick={() => removeFilter(f.field)}
                                        className="text-muted-foreground hover:text-foreground ml-0.5 cursor-pointer"
                                    >
                                        <X className="h-3 w-3" />
                                    </button>
                                </Badge>
                            ))}
                            <Button
                                variant="ghost"
                                size="xs"
                                onClick={handleReset}
                                className="text-muted-foreground hover:text-foreground h-6 text-xs"
                            >
                                Clear all
                            </Button>
                        </div>
                    ) : null}

                    {/* Bulk Actions Floating Bar */}
                    {selectedRows.length > 0 ? (
                        <div className="bg-primary/5 animate-in fade-in-50 flex flex-wrap items-center justify-between gap-3 border-b px-4 py-2.5">
                            <div className="flex items-center gap-2">
                                <Badge variant="default" size="sm" radius="full" className="font-semibold tabular-nums">
                                    {selectedRows.length} selected
                                </Badge>
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    onClick={() => setSelectedRows([])}
                                    className="text-muted-foreground hover:text-foreground h-7 text-xs"
                                >
                                    Deselect all
                                </Button>
                            </div>

                            <div className="flex flex-wrap items-center gap-2">
                                <Select value={bulkStatus} onValueChange={(val) => setBulkStatus(val)}>
                                    <SelectTrigger className="h-8 w-36 text-xs">
                                        <SelectValue placeholder="Set status..." />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {options.statuses.map((option) => (
                                            <SelectItem key={option.value} value={option.value}>
                                                {option.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>

                                <Button size="sm" className="h-8 text-xs" onClick={applyBulkStatus}>
                                    Apply Status
                                </Button>

                                <Button variant="outline" size="sm" className="h-8 text-xs" onClick={() => exportToCSV(selectedRows)}>
                                    <Download className="mr-1 h-3.5 w-3.5" /> Export Selected
                                </Button>
                            </div>
                        </div>
                    ) : null}

                    <CardContent className="p-0">
                        {viewMode === "table" ? (
                            <DataTable
                                columns={columns}
                                data={faculties.data}
                                toolbar={tableToolbar}
                                onSelectionChange={setSelectedRows}
                                emptyState={
                                    <div className="p-8">
                                        <Empty>
                                            <EmptyHeader>
                                                <EmptyMedia variant="icon">
                                                    <UsersRound className="text-muted-foreground h-8 w-8" />
                                                </EmptyMedia>
                                                <EmptyTitle>No faculty profiles found</EmptyTitle>
                                                <EmptyDescription>
                                                    {hasAnyControlsActive
                                                        ? "Try clearing or adjusting search filters to see matching faculty records."
                                                        : "No faculty profiles are registered in the system yet."}
                                                </EmptyDescription>
                                            </EmptyHeader>
                                            <EmptyContent className="flex gap-2">
                                                {hasAnyControlsActive ? (
                                                    <Button variant="outline" size="sm" onClick={handleReset}>
                                                        Reset Filters
                                                    </Button>
                                                ) : null}
                                                <Link href={route("administrators.faculties.create")} className={buttonVariants({ size: "sm" })}>
                                                    <Plus className="mr-1.5 h-4 w-4" /> Add New Faculty
                                                </Link>
                                            </EmptyContent>
                                        </Empty>
                                    </div>
                                }
                                pagination={{
                                    current_page: faculties.current_page,
                                    last_page: faculties.last_page,
                                    per_page: faculties.per_page,
                                    total: faculties.total,
                                    next_page_url: faculties.next_page_url,
                                    prev_page_url: faculties.prev_page_url,
                                    from: faculties.from,
                                    to: faculties.to,
                                }}
                                filters={{ ...filters, per_page: faculties.per_page }}
                            />
                        ) : (
                            /* Grid / Directory Cards View */
                            <div className="flex flex-col gap-4 p-4">
                                <div className="flex items-center justify-between gap-2">
                                    {tableToolbar}
                                    <div className="flex items-center gap-2">
                                        <Checkbox
                                            checked={
                                                selectedRows.length > 0 && selectedRows.length === faculties.data.length
                                                    ? true
                                                    : selectedRows.length > 0
                                                      ? "indeterminate"
                                                      : false
                                            }
                                            onCheckedChange={toggleSelectAllGrid}
                                            id="select-all-grid"
                                        />
                                        <label htmlFor="select-all-grid" className="text-muted-foreground cursor-pointer text-xs">
                                            Select all on page
                                        </label>
                                    </div>
                                </div>

                                {faculties.data.length === 0 ? (
                                    <div className="py-12">
                                        <Empty>
                                            <EmptyHeader>
                                                <EmptyMedia variant="icon">
                                                    <UsersRound className="text-muted-foreground h-8 w-8" />
                                                </EmptyMedia>
                                                <EmptyTitle>No faculty profiles found</EmptyTitle>
                                                <EmptyDescription>
                                                    {hasAnyControlsActive
                                                        ? "Try clearing active filters or searching for another faculty member."
                                                        : "No faculty members have been registered yet."}
                                                </EmptyDescription>
                                            </EmptyHeader>
                                            <EmptyContent className="flex gap-2">
                                                {hasAnyControlsActive ? (
                                                    <Button variant="outline" size="sm" onClick={handleReset}>
                                                        Reset Filters
                                                    </Button>
                                                ) : null}
                                                <Link href={route("administrators.faculties.create")} className={buttonVariants({ size: "sm" })}>
                                                    <Plus className="mr-1.5 h-4 w-4" /> Add New Faculty
                                                </Link>
                                            </EmptyContent>
                                        </Empty>
                                    </div>
                                ) : (
                                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                                        {faculties.data.map((faculty) => (
                                            <FacultyCard
                                                key={faculty.id}
                                                faculty={faculty}
                                                isSelected={selectedRows.some((r) => r.id === faculty.id)}
                                                onToggleSelect={toggleSelectRowById}
                                                onDelete={promptDelete}
                                            />
                                        ))}
                                    </div>
                                )}

                                {/* Grid Pagination Controls */}
                                <div className="flex flex-col items-center justify-between gap-3 border-t pt-4 sm:flex-row">
                                    <div className="text-muted-foreground text-xs">
                                        Showing <span className="font-medium tabular-nums">{faculties.from || 0}</span> to{" "}
                                        <span className="font-medium tabular-nums">{faculties.to || 0}</span> of{" "}
                                        <span className="font-medium tabular-nums">{faculties.total}</span> faculty
                                    </div>
                                    <div className="flex items-center gap-2">
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            disabled={!faculties.prev_page_url}
                                            onClick={() => faculties.prev_page_url && router.visit(faculties.prev_page_url)}
                                        >
                                            Previous
                                        </Button>
                                        <span className="px-2 text-xs font-medium">
                                            Page <span className="tabular-nums">{faculties.current_page}</span> of{" "}
                                            <span className="tabular-nums">{faculties.last_page}</span>
                                        </span>
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            disabled={!faculties.next_page_url}
                                            onClick={() => faculties.next_page_url && router.visit(faculties.next_page_url)}
                                        >
                                            Next
                                        </Button>
                                    </div>
                                </div>
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>

            {/* Accessible Radix Alert Dialog for Delete Confirmation */}
            <AlertDialog open={deleteModal.open} onOpenChange={(open) => !open && setDeleteModal({ open: false, id: "", name: "" })}>
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>Delete Faculty Profile</AlertDialogTitle>
                        <AlertDialogDescription>
                            Are you sure you want to delete <strong>{deleteModal.name}</strong>? This action will permanently remove their faculty
                            profile, unassign linked classes, and cannot be undone.
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel>Cancel</AlertDialogCancel>
                        <AlertDialogAction onClick={confirmDelete} className="bg-destructive text-destructive-foreground hover:bg-destructive/90">
                            <Trash2 className="mr-1.5 h-4 w-4" /> Delete Faculty
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </AdminLayout>
    );
}
