import AdminLayout from "@/components/administrators/admin-layout";
import { Filters, type Filter, type FilterFieldConfig } from "@/components/reui/filters";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Empty, EmptyContent, EmptyDescription, EmptyHeader, EmptyMedia, EmptyTitle } from "@/components/ui/empty";
import { Input } from "@/components/ui/input";
import type { User } from "@/types/user";
import { Head, Link, router } from "@inertiajs/react";
import { BookOpen, Building2, CheckCircle2, Filter as FilterIcon, PauseCircle, Plus, Search, Users, UsersRound, XCircle } from "lucide-react";
import { useCallback, useEffect, useMemo, useRef, useState } from "react";
import { useDebouncedCallback } from "use-debounce";
import { route } from "ziggy-js";
import { getColumns, type FacultyRow } from "./columns";
import { DataTable } from "./data-table";

type StatCard = {
    label: string;
    value: number;
    hint: string;
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
    };
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
        per_page?: number;
    };
    options: {
        departments: string[];
        statuses: { value: string; label: string }[];
        current_classes: { value: string; label: string }[];
    };
}

export default function AdministratorFacultiesIndex({ user, filament, stats, faculties, filters, options }: FacultiesIndexProps) {
    const [search, setSearch] = useState(filters.search || "");
    const [activeFilters, setActiveFilters] = useState<Filter[]>([]);

    const activeFiltersRef = useRef<Filter[]>([]);

    useEffect(() => {
        activeFiltersRef.current = activeFilters;
    }, [activeFilters]);

    const buildAppliedFilters = useCallback((newFilters: Filter[]) => {
        const applied: Record<string, string | null> = {
            department: null,
            status: null,
            current_classes: null,
        };

        newFilters.forEach((f) => {
            if (f.values.length > 0) {
                applied[f.field] = String(f.values[0]);
            }
        });

        return applied;
    }, []);

    useEffect(() => {
        setSearch(filters.search || "");

        const initial: Filter[] = [];
        if (filters.department) {
            initial.push({ id: "department", field: "department", operator: "is", values: [filters.department] });
        }
        if (filters.status) {
            initial.push({ id: "status", field: "status", operator: "is", values: [filters.status] });
        }
        if (filters.current_classes) {
            initial.push({ id: "current_classes", field: "current_classes", operator: "is", values: [filters.current_classes] });
        }

        setActiveFilters(initial);
    }, [filters]);

    const handleSearch = useDebouncedCallback(
        (term: string) => {
            const applied = buildAppliedFilters(activeFiltersRef.current);

            router.get(
                route("administrators.faculties.index"),
                {
                    ...applied,
                    search: term.trim() ? term : null,
                    per_page: faculties.per_page,
                    page: 1,
                },
                { preserveState: true, replace: true },
            );
        },
        300,
        { maxWait: 750 },
    );

    const handleFiltersChange = (newFilters: Filter[]) => {
        setActiveFilters(newFilters);

        const applied = buildAppliedFilters(newFilters);

        router.get(
            route("administrators.faculties.index"),
            {
                ...applied,
                search: search.trim() ? search : null,
                per_page: faculties.per_page,
                page: 1,
            },
            { preserveState: true, replace: true },
        );
    };

    const handleReset = () => {
        setSearch("");
        setActiveFilters([]);

        router.get(
            route("administrators.faculties.index"),
            {
                search: null,
                department: null,
                status: null,
                current_classes: null,
                per_page: faculties.per_page,
                page: 1,
            },
            { preserveState: false, replace: true },
        );
    };

    const handleDelete = useCallback((id: string, name: string) => {
        if (confirm(`Delete ${name}? This cannot be undone.`)) {
            router.delete(route("administrators.faculties.destroy", id));
        }
    }, []);

    const columns = useMemo(() => getColumns({ onDelete: handleDelete }), [handleDelete]);

    const statusOptionIcon = (value: string) => {
        if (value === "active") {
            return <CheckCircle2 className="h-4 w-4 text-green-600" />;
        }

        if (value === "inactive") {
            return <XCircle className="text-muted-foreground h-4 w-4" />;
        }

        if (value === "on_leave") {
            return <PauseCircle className="h-4 w-4 text-yellow-600" />;
        }

        return <CheckCircle2 className="text-muted-foreground h-4 w-4" />;
    };

    const currentClassesOptionIcon = (value: string) => {
        const normalized = value.toLowerCase();

        if (normalized.includes("with") || normalized.includes("yes") || normalized === "true") {
            return <BookOpen className="h-4 w-4 text-green-600" />;
        }

        return <BookOpen className="text-muted-foreground h-4 w-4" />;
    };

    const filterFields: FilterFieldConfig[] = useMemo(
        () => [
            {
                key: "department",
                label: "Department",
                type: "select",
                icon: <Building2 className="h-4 w-4" />,
                options: options.departments.map((dept) => ({
                    value: dept,
                    label: dept,
                    icon: <Building2 className="text-muted-foreground h-4 w-4" />,
                })),
            },
            {
                key: "status",
                label: "Status",
                type: "select",
                icon: <CheckCircle2 className="h-4 w-4" />,
                options: options.statuses.map((opt) => ({
                    ...opt,
                    icon: statusOptionIcon(opt.value),
                })),
            },
            {
                key: "current_classes",
                label: "Current Classes",
                type: "select",
                icon: <BookOpen className="h-4 w-4" />,
                options: options.current_classes.map((opt) => ({
                    ...opt,
                    icon: currentClassesOptionIcon(opt.value),
                })),
            },
        ],
        [options],
    );

    const statCards: StatCard[] = [
        {
            label: "Total Faculty",
            value: stats.total,
            hint: "All faculty profiles in the system.",
        },
        {
            label: "Active",
            value: stats.active,
            hint: "Can be scheduled and assigned classes.",
        },
        {
            label: "On Leave",
            value: stats.on_leave,
            hint: "Temporarily unavailable.",
        },
        {
            label: "With Current Classes",
            value: stats.with_current_classes,
            hint: "Teaching this semester/school year.",
        },
    ];

    const hasAnyControlsActive = search.trim().length > 0 || activeFilters.length > 0;

    const tableToolbar = (
        <>
            <div className="relative w-full md:max-w-sm">
                <Search className="text-muted-foreground absolute top-2.5 left-2.5 h-4 w-4" />
                <Input
                    placeholder="Search faculty ID, name, or email…"
                    className="pl-8"
                    value={search}
                    onChange={(e) => {
                        setSearch(e.target.value);
                        handleSearch(e.target.value);
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
                        {activeFilters.length > 0 && (
                            <Badge variant="secondary" className="h-5 min-w-5 rounded-full px-1.5 text-xs">
                                {activeFilters.length}
                            </Badge>
                        )}
                    </Button>
                }
            />

            {hasAnyControlsActive && (
                <Button variant="ghost" size="sm" onClick={handleReset} className="text-muted-foreground hover:text-foreground">
                    <XCircle className="mr-2 h-4 w-4" />
                    Reset
                </Button>
            )}

            <div className="hidden items-center gap-2 text-sm lg:flex">
                <Badge variant="outline" className="gap-1">
                    <Users className="h-3.5 w-3.5" /> Showing {faculties.data.length} on this page
                </Badge>
                <Badge variant="outline" className="gap-1">
                    <UsersRound className="h-3.5 w-3.5" /> Total {faculties.total}
                </Badge>
            </div>
        </>
    );

    return (
        <AdminLayout user={user} title="Faculties">
            <Head title="Administrators • Faculties" />

            <div className="flex flex-col gap-6">
                <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h2 className="text-2xl font-bold tracking-tight">Faculty Management</h2>
                        <p className="text-muted-foreground">Quickly find a teacher, update details, and manage class assignments.</p>
                    </div>

                    <div className="flex flex-wrap gap-2">
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
                    {statCards.map((stat) => (
                        <Card key={stat.label}>
                            <CardHeader className="pb-2">
                                <CardTitle className="text-muted-foreground text-sm font-medium">{stat.label}</CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-1">
                                <div className="text-2xl font-semibold tracking-tight">{stat.value}</div>
                                <div className="text-muted-foreground text-xs">{stat.hint}</div>
                            </CardContent>
                        </Card>
                    ))}
                </div>

                <Card>
                    <CardHeader className="pb-3">
                        <CardTitle>Faculty List</CardTitle>
                        <CardDescription>Click “View” to manage classes and see full details.</CardDescription>
                    </CardHeader>
                    <CardContent className="border-t p-0">
                        <div className="p-0">
                            <DataTable
                                columns={columns}
                                data={faculties.data}
                                toolbar={tableToolbar}
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
                        </div>
                    </CardContent>
                </Card>
            </div>
        </AdminLayout>
    );
}
