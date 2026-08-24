import AdminLayout from "@/components/administrators/admin-layout";
import { Filters, type Filter, type FilterFieldConfig } from "@/components/reui/filters";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Empty, EmptyContent, EmptyDescription, EmptyHeader, EmptyMedia, EmptyTitle } from "@/components/ui/empty";
import { Input } from "@/components/ui/input";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { cn } from "@/lib/utils";
import type { User } from "@/types/user";
import { Head, Link, router } from "@inertiajs/react";
import {
    AlertTriangle,
    BookOpen,
    Building2,
    CheckCircle2,
    FileSpreadsheet,
    Filter as FilterIcon,
    KeyRound,
    PauseCircle,
    Plus,
    Search,
    Users,
    UsersRound,
    XCircle,
} from "lucide-react";
import { useCallback, useEffect, useMemo, useRef, useState } from "react";
import { useDebouncedCallback } from "use-debounce";
import { route } from "ziggy-js";
import { getColumns, type FacultyRow } from "./columns";
import { DataTable } from "./data-table";
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

    const handleDelete = useCallback((id: string, name: string) => {
        if (confirm(`Delete ${name}? This cannot be undone.`)) {
            router.delete(route("administrators.faculties.destroy", id));
        }
    }, []);

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
                onSuccess: () => setSelectedRows([]),
            },
        );
    };

    const columns = useMemo(() => getColumns({ onDelete: handleDelete }), [handleDelete]);

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
                            <PauseCircle className="h-4 w-4 text-yellow-600" />
                        ) : (
                            <CheckCircle2 className="h-4 w-4 text-green-600" />
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
                label: "Portal",
                type: "select",
                icon: <KeyRound className="h-4 w-4" />,
                options: options.portal.map((option) => ({
                    ...option,
                    icon: <KeyRound className="text-muted-foreground h-4 w-4" />,
                })),
            },
            {
                key: "profile",
                label: "Profile",
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

    const statCards = [
        { label: "Active", value: stats.active, hint: "Ready for assignment", icon: CheckCircle2 },
        { label: "Needs classes", value: stats.needs_classes, hint: "No current load", icon: BookOpen },
        { label: "Portal issues", value: stats.portal_not_linked, hint: "Access not linked", icon: KeyRound },
        { label: "Incomplete", value: stats.incomplete_profile, hint: "Missing records", icon: AlertTriangle },
    ];

    const activeSegment = filters.segment || "all";
    const hasAnyControlsActive = search.trim().length > 0 || activeFilters.length > 0 || activeSegment !== "all";

    const segmentCount = (segment: string): number => {
        if (segment === "needs_classes") return stats.needs_classes;
        if (segment === "on_leave") return stats.on_leave;
        if (segment === "portal_not_linked") return stats.portal_not_linked;
        if (segment === "incomplete_profile") return stats.incomplete_profile;

        return stats.total;
    };

    const tableToolbar = (
        <>
            <div className="relative w-full md:max-w-sm">
                <Search className="text-muted-foreground absolute top-2.5 left-2.5 h-4 w-4" />
                <Input
                    placeholder="Search faculty ID, name, or email"
                    className="pl-8"
                    value={search}
                    onChange={(event) => {
                        setSearch(event.target.value);
                        handleSearch(event.target.value);
                    }}
                />
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
                            <Badge variant="secondary" className="h-5 min-w-5 rounded-full px-1.5 text-xs">
                                {activeFilters.length}
                            </Badge>
                        ) : null}
                    </Button>
                }
            />

            {hasAnyControlsActive ? (
                <Button variant="ghost" size="sm" onClick={handleReset} className="text-muted-foreground hover:text-foreground">
                    <XCircle className="mr-2 h-4 w-4" />
                    Reset
                </Button>
            ) : null}

            <div className="hidden items-center gap-2 text-sm lg:flex">
                <Badge variant="outline" className="gap-1">
                    <Users className="h-3.5 w-3.5" /> Showing {faculties.data.length}
                </Badge>
                <Badge variant="outline" className="gap-1">
                    <UsersRound className="h-3.5 w-3.5" /> Total {faculties.total}
                </Badge>
            </div>
        </>
    );

    return (
        <AdminLayout user={user} title="Faculties">
            <Head title="Administrators - Faculties" />

            <div className="flex flex-col gap-6">
                <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h2 className="text-2xl font-bold tracking-tight">Faculty Operations</h2>
                        <p className="text-muted-foreground">Monitor workload, portal readiness, and faculty profile quality from one console.</p>
                    </div>

                    <div className="flex flex-wrap gap-2">
                        <Button asChild variant="outline">
                            <a href={route("administrators.faculties.imports.template")}>
                                <FileSpreadsheet className="mr-2 h-4 w-4" /> Download template
                            </a>
                        </Button>
                        <FacultyImportDialog />
                        <Button asChild variant="outline">
                            <a href={filament.faculties.index_url} target="_blank" rel="noreferrer">
                                Open Filament
                            </a>
                        </Button>
                        <Button asChild>
                            <Link href={route("administrators.faculties.create")}>
                                <Plus className="mr-2 h-4 w-4" /> Add Faculty
                            </Link>
                        </Button>
                    </div>
                </div>

                <div className="grid gap-3 md:grid-cols-4">
                    {statCards.map((stat) => {
                        const Icon = stat.icon;

                        return (
                            <Card key={stat.label}>
                                <CardHeader className="pb-2">
                                    <CardTitle className="text-muted-foreground flex items-center gap-2 text-sm font-medium">
                                        <Icon className="h-4 w-4" />
                                        {stat.label}
                                    </CardTitle>
                                </CardHeader>
                                <CardContent className="space-y-1">
                                    <div className="text-2xl font-semibold tracking-tight">{stat.value}</div>
                                    <div className="text-muted-foreground text-xs">{stat.hint}</div>
                                </CardContent>
                            </Card>
                        );
                    })}
                </div>

                <div className="flex gap-2 overflow-x-auto pb-1">
                    {segments.map((segment) => (
                        <Button
                            key={segment.value}
                            variant={activeSegment === segment.value ? "default" : "outline"}
                            size="sm"
                            onClick={() => handleSegment(segment.value)}
                            className={cn("shrink-0 gap-2", activeSegment === segment.value && "shadow-sm")}
                        >
                            {segment.label}
                            <Badge variant={activeSegment === segment.value ? "secondary" : "outline"}>{segmentCount(segment.value)}</Badge>
                        </Button>
                    ))}
                </div>

                <Card>
                    <CardHeader className="gap-4 pb-3 lg:flex-row lg:items-center lg:justify-between">
                        <div>
                            <CardTitle>Faculty Directory</CardTitle>
                            <CardDescription>Sort, filter, and apply common administrative actions in bulk.</CardDescription>
                        </div>
                        <div className="flex flex-wrap gap-2">
                            <Select value={filters.sort || "faculty"} onValueChange={handleSort}>
                                <SelectTrigger className="w-44">
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
                                <SelectTrigger className="w-28">
                                    <SelectValue placeholder="Direction" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="asc">Asc</SelectItem>
                                    <SelectItem value="desc">Desc</SelectItem>
                                </SelectContent>
                            </Select>
                            <Select value={String(faculties.per_page)} onValueChange={handlePerPage}>
                                <SelectTrigger className="w-28">
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
                    <CardContent className="border-t p-0">
                        {selectedRows.length > 0 ? (
                            <div className="bg-muted/40 flex flex-col gap-3 border-b p-3 sm:flex-row sm:items-center sm:justify-between">
                                <div className="text-sm font-medium">{selectedRows.length} faculty selected</div>
                                <div className="flex flex-wrap gap-2">
                                    <Select value={bulkStatus} onValueChange={(value) => value && setBulkStatus(value)}>
                                        <SelectTrigger className="w-40">
                                            <SelectValue placeholder="Status" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {options.statuses.map((option) => (
                                                <SelectItem key={option.value} value={option.value}>
                                                    {option.label}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    <Button onClick={applyBulkStatus}>Apply status</Button>
                                </div>
                            </div>
                        ) : null}
                        <DataTable
                            columns={columns}
                            data={faculties.data}
                            toolbar={tableToolbar}
                            onSelectionChange={setSelectedRows}
                            emptyState={
                                <div className="p-6">
                                    <Empty>
                                        <EmptyHeader>
                                            <EmptyMedia variant="icon">
                                                <CheckCircle2 className="h-6 w-6" />
                                            </EmptyMedia>
                                            <EmptyTitle>No results</EmptyTitle>
                                            <EmptyDescription>Try clearing filters or add a new faculty profile.</EmptyDescription>
                                        </EmptyHeader>
                                        <EmptyContent>
                                            <Button asChild>
                                                <Link href={route("administrators.faculties.create")}>
                                                    <Plus className="mr-2 h-4 w-4" /> Add Faculty
                                                </Link>
                                            </Button>
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
                    </CardContent>
                </Card>
            </div>
        </AdminLayout>
    );
}
