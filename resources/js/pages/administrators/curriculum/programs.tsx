import AdminLayout from "@/components/administrators/admin-layout";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from "@/components/ui/dropdown-menu";
import { Input } from "@/components/ui/input";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table";
import type { User } from "@/types/user";
import { Head, Link, router } from "@inertiajs/react";
import {
    ColumnDef,
    ColumnFiltersState,
    SortingState,
    VisibilityState,
    flexRender,
    getCoreRowModel,
    getFilteredRowModel,
    getPaginationRowModel,
    getSortedRowModel,
    useReactTable,
} from "@tanstack/react-table";
import { ArrowUpDown, Book, Calculator, GraduationCap, Link2, MoreHorizontal, Plus, Search } from "lucide-react";
import { useMemo, useState } from "react";
import { route } from "ziggy-js";

interface CurriculumProgramsProps {
    user: User;
    stats: {
        programs: number;
        active_programs: number;
        subjects: number;
        subjects_with_requisites: number;
        curriculum_versions: number;
    };
    programs: ProgramSummary[];
    filament: {
        courses: {
            index_url: string;
            create_url: string;
        };
    };
}

type ProgramSummary = {
    id: number;
    code: string;
    title: string;
    department: string | null;
    curriculum_year: string | null;
    subjects_count: number;
    total_units: number;
    prerequisites_count: number;
    is_active: boolean;
    updated_at: string | null;
};

export default function CurriculumPrograms({ user, stats, programs, filament }: CurriculumProgramsProps) {
    const [sorting, setSorting] = useState<SortingState>([]);
    const [columnFilters, setColumnFilters] = useState<ColumnFiltersState>([]);
    const [columnVisibility, setColumnVisibility] = useState<VisibilityState>({});
    const [rowSelection, setRowSelection] = useState({});
    const [globalFilter, setGlobalFilter] = useState("");

    const toggleStatus = (program: ProgramSummary) => {
        router.put(
            route("administrators.curriculum.programs.toggle-status", program.id),
            {},
            {
                preserveScroll: true,
            },
        );
    };

    const columns: ColumnDef<ProgramSummary>[] = useMemo(
        () => [
            {
                accessorKey: "code",
                header: ({ column }) => {
                    return (
                        <Button
                            variant="ghost"
                            className="data-[state=open]:bg-accent -ml-4 h-8"
                            onClick={() => column.toggleSorting(column.getIsSorted() === "asc")}
                        >
                            Program Details
                            <ArrowUpDown className="ml-2 h-4 w-4" />
                        </Button>
                    );
                },
                cell: ({ row }) => {
                    const program = row.original;
                    return (
                        <div className="flex max-w-[280px] flex-col gap-1">
                            <span className="text-foreground leading-none font-semibold">{program.code}</span>
                            <span className="text-muted-foreground line-clamp-2 text-xs leading-snug" title={program.title}>
                                {program.title}
                            </span>
                            <div className="mt-1 flex flex-wrap gap-1">
                                <Badge variant="secondary" className="h-4 px-1.5 py-0 text-[10px] font-medium">
                                    {program.department || "No Dept"}
                                </Badge>
                                {program.curriculum_year && (
                                    <Badge variant="outline" className="bg-background h-4 px-1.5 py-0 text-[10px]">
                                        Version {program.curriculum_year}
                                    </Badge>
                                )}
                            </div>
                        </div>
                    );
                },
                filterFn: (row, id, value) => {
                    const rowValue =
                        `${row.original.code} ${row.original.title} ${row.original.department || ""} ${row.original.curriculum_year || ""}`.toLowerCase();
                    return rowValue.includes((value as string).toLowerCase());
                },
            },
            {
                id: "curriculum_size",
                header: "Curriculum Stats",
                cell: ({ row }) => {
                    const program = row.original;
                    return (
                        <div className="grid w-fit grid-cols-2 gap-x-4 gap-y-1 text-sm">
                            <div className="text-muted-foreground flex items-center gap-1.5">
                                <Book className="h-3.5 w-3.5 text-blue-500" />
                                <span className="text-foreground font-medium">{program.subjects_count}</span>
                                <span className="text-xs">Subjects</span>
                            </div>
                            <div className="text-muted-foreground flex items-center gap-1.5">
                                <Calculator className="h-3.5 w-3.5 text-amber-500" />
                                <span className="text-foreground font-medium">{program.total_units}</span>
                                <span className="text-xs">Units</span>
                            </div>
                            <div className="text-muted-foreground col-span-2 flex items-center gap-1.5">
                                <Link2 className="h-3.5 w-3.5 text-emerald-500" />
                                <span className="text-foreground font-medium">{program.prerequisites_count}</span>
                                <span className="text-xs">Prerequisites</span>
                            </div>
                        </div>
                    );
                },
            },
            {
                accessorKey: "is_active",
                header: "Status",
                cell: ({ row }) => {
                    const isActive = row.getValue("is_active") as boolean;
                    return isActive ? (
                        <Badge variant="secondary" className="bg-emerald-100 text-emerald-800 dark:bg-emerald-900 dark:text-emerald-300">
                            Active
                        </Badge>
                    ) : (
                        <Badge variant="outline" className="text-muted-foreground">
                            Inactive
                        </Badge>
                    );
                },
            },
            {
                id: "actions",
                cell: ({ row }) => {
                    const program = row.original;

                    return (
                        <div className="flex items-center justify-end gap-2 pr-2">
                            <Button asChild variant="secondary" size="sm" className="h-8">
                                <Link href={route("administrators.curriculum.programs.show", program.id)}>Manage</Link>
                            </Button>
                            <DropdownMenu>
                                <DropdownMenuTrigger asChild>
                                    <Button variant="ghost" className="h-8 w-8 p-0">
                                        <span className="sr-only">Open menu</span>
                                        <MoreHorizontal className="h-4 w-4" />
                                    </Button>
                                </DropdownMenuTrigger>
                                <DropdownMenuContent align="end">
                                    <DropdownMenuItem onClick={() => toggleStatus(program)}>
                                        {program.is_active ? "Deactivate Program" : "Activate Program"}
                                    </DropdownMenuItem>
                                </DropdownMenuContent>
                            </DropdownMenu>
                        </div>
                    );
                },
            },
        ],
        [],
    );

    const table = useReactTable({
        data: programs,
        columns,
        onSortingChange: setSorting,
        onColumnFiltersChange: setColumnFilters,
        getCoreRowModel: getCoreRowModel(),
        getPaginationRowModel: getPaginationRowModel(),
        getSortedRowModel: getSortedRowModel(),
        getFilteredRowModel: getFilteredRowModel(),
        onColumnVisibilityChange: setColumnVisibility,
        onRowSelectionChange: setRowSelection,
        globalFilterFn: "includesString",
        onGlobalFilterChange: setGlobalFilter,
        state: {
            sorting,
            columnFilters,
            columnVisibility,
            rowSelection,
            globalFilter,
        },
        initialState: {
            pagination: { pageSize: 15 },
        },
    });

    return (
        <AdminLayout user={user} title="Program Management">
            <Head title="Program Management" />
            <div className="flex flex-col gap-6">
                <Card className="border-none bg-gradient-to-br from-slate-50 via-white to-slate-50 shadow-sm dark:from-slate-900 dark:via-slate-900 dark:to-slate-950">
                    <CardHeader className="flex flex-col gap-4 pb-6 lg:flex-row lg:items-center lg:justify-between">
                        <div className="flex items-center gap-4">
                            <div className="bg-primary/10 rounded-xl p-3">
                                <GraduationCap className="text-primary size-6" />
                            </div>
                            <div className="space-y-1">
                                <CardTitle className="text-2xl">Program Directory</CardTitle>
                                <CardDescription className="max-w-xl text-sm">
                                    Centralized control for all academic programs, subjects, and curriculum tracking.
                                </CardDescription>
                            </div>
                        </div>
                        <div className="flex flex-wrap items-center gap-3">
                            <Button asChild variant="outline" size="sm" className="h-9">
                                <a href={filament.courses.index_url} target="_blank" rel="noreferrer">
                                    Open Advanced Editor
                                </a>
                            </Button>
                            <Button asChild size="sm" className="h-9">
                                <a href={filament.courses.create_url} target="_blank" rel="noreferrer">
                                    <Plus className="mr-2 size-4" />
                                    New Program
                                </a>
                            </Button>
                        </div>
                    </CardHeader>
                    <CardContent className="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                        <div className="bg-card rounded-xl border p-4 shadow-sm transition-all hover:shadow-md">
                            <div className="text-muted-foreground text-xs font-semibold tracking-wider uppercase">Total Programs</div>
                            <div className="text-foreground mt-2 text-3xl font-bold">{stats.programs}</div>
                            <p className="text-muted-foreground mt-1 text-xs font-medium">
                                <span className="text-emerald-500">{stats.active_programs}</span> currently active
                            </p>
                        </div>
                        <div className="bg-card rounded-xl border p-4 shadow-sm transition-all hover:shadow-md">
                            <div className="text-muted-foreground text-xs font-semibold tracking-wider uppercase">Total Subjects</div>
                            <div className="text-foreground mt-2 text-3xl font-bold">{stats.subjects}</div>
                            <p className="text-muted-foreground mt-1 text-xs font-medium">Across all programs</p>
                        </div>
                        <div className="bg-card rounded-xl border p-4 shadow-sm transition-all hover:shadow-md">
                            <div className="text-muted-foreground text-xs font-semibold tracking-wider uppercase">Prerequisites</div>
                            <div className="text-foreground mt-2 text-3xl font-bold">{stats.subjects_with_requisites}</div>
                            <p className="text-muted-foreground mt-1 text-xs font-medium">Subjects with rules</p>
                        </div>
                        <div className="bg-card rounded-xl border p-4 shadow-sm transition-all hover:shadow-md">
                            <div className="text-muted-foreground text-xs font-semibold tracking-wider uppercase">Curriculum Years</div>
                            <div className="text-foreground mt-2 text-3xl font-bold">{stats.curriculum_versions}</div>
                            <p className="text-muted-foreground mt-1 text-xs font-medium">Active versions</p>
                        </div>
                    </CardContent>
                </Card>

                <Card className="border shadow-sm">
                    <CardHeader className="bg-muted/20 border-b pb-4">
                        <div className="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
                            <div>
                                <CardTitle className="text-lg">Programs Datagrid</CardTitle>
                                <CardDescription>Search, filter, and manage your academic programs.</CardDescription>
                            </div>
                            <div className="flex items-center gap-2">
                                <div className="relative w-full sm:w-64">
                                    <Search className="text-muted-foreground absolute top-2.5 left-2.5 h-4 w-4" />
                                    <Input
                                        placeholder="Search program, dept, or year..."
                                        value={globalFilter ?? ""}
                                        onChange={(event) => setGlobalFilter(event.target.value)}
                                        className="bg-background h-9 w-full pl-9"
                                    />
                                </div>
                            </div>
                        </div>
                    </CardHeader>
                    <CardContent className="p-0">
                        <Table>
                            <TableHeader className="bg-muted/30">
                                {table.getHeaderGroups().map((headerGroup) => (
                                    <TableRow key={headerGroup.id} className="hover:bg-transparent">
                                        {headerGroup.headers.map((header) => {
                                            return (
                                                <TableHead key={header.id} className="py-3">
                                                    {header.isPlaceholder ? null : flexRender(header.column.columnDef.header, header.getContext())}
                                                </TableHead>
                                            );
                                        })}
                                    </TableRow>
                                ))}
                            </TableHeader>
                            <TableBody>
                                {table.getRowModel().rows?.length ? (
                                    table.getRowModel().rows.map((row) => (
                                        <TableRow key={row.id} data-state={row.getIsSelected() && "selected"} className="group">
                                            {row.getVisibleCells().map((cell) => (
                                                <TableCell key={cell.id} className="py-3">
                                                    {flexRender(cell.column.columnDef.cell, cell.getContext())}
                                                </TableCell>
                                            ))}
                                        </TableRow>
                                    ))
                                ) : (
                                    <TableRow>
                                        <TableCell colSpan={columns.length} className="text-muted-foreground h-32 text-center">
                                            No programs found matching your criteria.
                                        </TableCell>
                                    </TableRow>
                                )}
                            </TableBody>
                        </Table>
                    </CardContent>
                    {table.getPageCount() > 1 && (
                        <div className="flex items-center justify-end space-x-2 border-t p-4">
                            <Button variant="outline" size="sm" onClick={() => table.previousPage()} disabled={!table.getCanPreviousPage()}>
                                Previous
                            </Button>
                            <span className="text-muted-foreground text-sm">
                                Page {table.getState().pagination.pageIndex + 1} of {table.getPageCount()}
                            </span>
                            <Button variant="outline" size="sm" onClick={() => table.nextPage()} disabled={!table.getCanNextPage()}>
                                Next
                            </Button>
                        </div>
                    )}
                </Card>
            </div>
        </AdminLayout>
    );
}
