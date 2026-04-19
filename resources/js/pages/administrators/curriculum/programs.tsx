import AdminLayout from "@/components/administrators/admin-layout";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from "@/components/ui/dialog";
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from "@/components/ui/dropdown-menu";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table";
import { Textarea } from "@/components/ui/textarea";
import type { User } from "@/types/user";
import { Head, Link, router, useForm } from "@inertiajs/react";
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
import {
    ArrowUpDown,
    Book,
    Calculator,
    ChevronLeft,
    GraduationCap,
    Link2,
    MoreHorizontal,
    Plus,
    Search,
} from "lucide-react";
import { type FormEvent, useEffect, useMemo, useState } from "react";
import { route } from "ziggy-js";

interface DepartmentOption {
    id: number;
    name: string;
    code: string;
}

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
    departments: DepartmentOption[];
    versions: CurriculumVersion[];
}

type ProgramSummary = {
    id: number;
    code: string;
    title: string;
    department: string | null;
    department_id: number | null;
    department_name: string | null;
    curriculum_year: string | null;
    subjects_count: number;
    total_units: number;
    prerequisites_count: number;
    is_active: boolean;
    updated_at: string | null;
};

type CurriculumVersion = {
    curriculum_year: string;
    program_count: number;
    active_program_count: number;
    subject_count: number;
};

const departmentBadgeColors: Record<string, string> = {};
const colorPalette = [
    "bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300",
    "bg-violet-100 text-violet-700 dark:bg-violet-900/40 dark:text-violet-300",
    "bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300",
    "bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300",
    "bg-rose-100 text-rose-700 dark:bg-rose-900/40 dark:text-rose-300",
    "bg-cyan-100 text-cyan-700 dark:bg-cyan-900/40 dark:text-cyan-300",
    "bg-orange-100 text-orange-700 dark:bg-orange-900/40 dark:text-orange-300",
    "bg-teal-100 text-teal-700 dark:bg-teal-900/40 dark:text-teal-300",
];

function getDepartmentColor(code: string): string {
    if (!departmentBadgeColors[code]) {
        const idx = Object.keys(departmentBadgeColors).length % colorPalette.length;
        departmentBadgeColors[code] = colorPalette[idx];
    }
    return departmentBadgeColors[code];
}

const FieldError = ({ message }: { message?: string }) =>
    message ? <p className="text-destructive mt-1 text-xs font-medium">{message}</p> : null;

export default function CurriculumPrograms({ user, stats, programs, departments }: CurriculumProgramsProps) {
    const [sorting, setSorting] = useState<SortingState>([]);
    const [columnFilters, setColumnFilters] = useState<ColumnFiltersState>([]);
    const [columnVisibility, setColumnVisibility] = useState<VisibilityState>({});
    const [rowSelection, setRowSelection] = useState({});
    const [globalFilter, setGlobalFilter] = useState("");
    const [isCreateOpen, setIsCreateOpen] = useState(false);

    // Department filter from URL params
    const urlParams = new URLSearchParams(typeof window !== "undefined" ? window.location.search : "");
    const initialDeptFilter = urlParams.get("department") ?? "all";
    const [activeDepartment, setActiveDepartment] = useState<string>(initialDeptFilter);

    // Departments that actually have programs
    const departmentsWithPrograms = useMemo(() => {
        const deptIdsWithPrograms = new Set(programs.map((p) => p.department_id).filter(Boolean));
        return departments.filter((d) => deptIdsWithPrograms.has(d.id));
    }, [departments, programs]);

    // Filter programs by selected department
    const filteredPrograms = useMemo(() => {
        if (activeDepartment === "all") return programs;
        const deptId = Number(activeDepartment);
        return programs.filter((p) => p.department_id === deptId);
    }, [programs, activeDepartment]);

    // Filtered stats
    const filteredStats = useMemo(() => {
        if (activeDepartment === "all") return stats;
        return {
            programs: filteredPrograms.length,
            active_programs: filteredPrograms.filter((p) => p.is_active).length,
            subjects: filteredPrograms.reduce((sum, p) => sum + p.subjects_count, 0),
            subjects_with_requisites: filteredPrograms.reduce((sum, p) => sum + p.prerequisites_count, 0),
            curriculum_versions: stats.curriculum_versions,
        };
    }, [filteredPrograms, stats, activeDepartment]);

    const createForm = useForm({
        code: "",
        title: "",
        description: "",
        department_id: "",
        curriculum_year: "",
        lec_per_unit: "",
        lab_per_unit: "",
        miscelaneous: "",
        remarks: "",
    });

    // Pre-fill department when filter is active
    useEffect(() => {
        if (activeDepartment !== "all" && isCreateOpen) {
            createForm.setData("department_id", activeDepartment);
        }
    }, [isCreateOpen, activeDepartment]);

    const handleCreateProgram = (e: FormEvent) => {
        e.preventDefault();
        createForm.post(route("administrators.curriculum.programs.store"), {
            preserveScroll: true,
            onSuccess: () => {
                setIsCreateOpen(false);
                createForm.reset();
                createForm.clearErrors();
            },
        });
    };

    const toggleStatus = (program: ProgramSummary) => {
        router.put(
            route("administrators.curriculum.programs.toggle-status", program.id),
            {},
            { preserveScroll: true },
        );
    };

    const columns: ColumnDef<ProgramSummary>[] = useMemo(
        () => [
            {
                accessorKey: "code",
                header: ({ column }) => (
                    <Button
                        variant="ghost"
                        className="data-[state=open]:bg-accent -ml-4 h-8"
                        onClick={() => column.toggleSorting(column.getIsSorted() === "asc")}
                    >
                        Program
                        <ArrowUpDown className="ml-2 h-4 w-4" />
                    </Button>
                ),
                cell: ({ row }) => {
                    const program = row.original;
                    const deptCode = program.department || "N/A";
                    return (
                        <div className="flex max-w-[300px] flex-col gap-1">
                            <div className="flex items-center gap-2">
                                <span className="text-foreground leading-none font-semibold">{program.code}</span>
                                {program.is_active ? (
                                    <Badge
                                        variant="secondary"
                                        className="h-4 bg-emerald-100 px-1.5 py-0 text-[10px] font-medium text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300"
                                    >
                                        Active
                                    </Badge>
                                ) : (
                                    <Badge variant="outline" className="text-muted-foreground h-4 px-1.5 py-0 text-[10px]">
                                        Inactive
                                    </Badge>
                                )}
                            </div>
                            <span className="text-muted-foreground line-clamp-1 text-xs leading-snug" title={program.title}>
                                {program.title}
                            </span>
                            <div className="mt-0.5 flex flex-wrap gap-1">
                                <Badge
                                    variant="secondary"
                                    className={`h-4 px-1.5 py-0 text-[10px] font-semibold ${getDepartmentColor(deptCode)}`}
                                >
                                    {deptCode}
                                </Badge>
                                {program.curriculum_year && (
                                    <Badge variant="outline" className="bg-background h-4 px-1.5 py-0 text-[10px]">
                                        {program.curriculum_year}
                                    </Badge>
                                )}
                            </div>
                        </div>
                    );
                },
                filterFn: (row, _id, value) => {
                    const rowValue =
                        `${row.original.code} ${row.original.title} ${row.original.department || ""} ${row.original.curriculum_year || ""}`.toLowerCase();
                    return rowValue.includes((value as string).toLowerCase());
                },
            },
            {
                id: "curriculum_size",
                header: "Stats",
                cell: ({ row }) => {
                    const program = row.original;
                    return (
                        <div className="grid w-fit grid-cols-1 gap-y-1 text-sm">
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
                            <div className="text-muted-foreground flex items-center gap-1.5">
                                <Link2 className="h-3.5 w-3.5 text-emerald-500" />
                                <span className="text-foreground font-medium">{program.prerequisites_count}</span>
                                <span className="text-xs">Prereqs</span>
                            </div>
                        </div>
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
        data: filteredPrograms,
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
                {/* Hero Header */}
                <Card className="border-none bg-gradient-to-br from-slate-50 via-white to-slate-50 shadow-sm dark:from-slate-900 dark:via-slate-900 dark:to-slate-950">
                    <CardHeader className="flex flex-col gap-4 pb-6 lg:flex-row lg:items-center lg:justify-between">
                        <div className="flex items-center gap-4">
                            <div className="bg-primary/10 rounded-xl p-3">
                                <GraduationCap className="text-primary size-6" />
                            </div>
                            <div className="space-y-1">
                                <CardTitle className="text-2xl">Program Directory</CardTitle>
                                <CardDescription className="max-w-xl text-sm">
                                    Create, manage, and organize all academic programs across departments.
                                </CardDescription>
                            </div>
                        </div>
                        <div className="flex flex-wrap items-center gap-3">
                            <Button asChild variant="ghost" size="sm" className="h-9">
                                <Link href={route("administrators.curriculum.index")}>
                                    <ChevronLeft className="mr-1 size-4" />
                                    Back to Overview
                                </Link>
                            </Button>
                            <Button size="sm" className="h-9" onClick={() => setIsCreateOpen(true)}>
                                <Plus className="mr-2 size-4" />
                                Create Course
                            </Button>
                        </div>
                    </CardHeader>
                    <CardContent className="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                        <div className="bg-card rounded-xl border p-4 shadow-sm transition-all hover:shadow-md">
                            <div className="text-muted-foreground text-xs font-semibold tracking-wider uppercase">Total Programs</div>
                            <div className="text-foreground mt-2 text-3xl font-bold">{filteredStats.programs}</div>
                            <p className="text-muted-foreground mt-1 text-xs font-medium">
                                <span className="text-emerald-500">{filteredStats.active_programs}</span> currently active
                            </p>
                        </div>
                        <div className="bg-card rounded-xl border p-4 shadow-sm transition-all hover:shadow-md">
                            <div className="text-muted-foreground text-xs font-semibold tracking-wider uppercase">Total Subjects</div>
                            <div className="text-foreground mt-2 text-3xl font-bold">{filteredStats.subjects}</div>
                            <p className="text-muted-foreground mt-1 text-xs font-medium">Across programs</p>
                        </div>
                        <div className="bg-card rounded-xl border p-4 shadow-sm transition-all hover:shadow-md">
                            <div className="text-muted-foreground text-xs font-semibold tracking-wider uppercase">Prerequisites</div>
                            <div className="text-foreground mt-2 text-3xl font-bold">{filteredStats.subjects_with_requisites}</div>
                            <p className="text-muted-foreground mt-1 text-xs font-medium">Subjects with rules</p>
                        </div>
                        <div className="bg-card rounded-xl border p-4 shadow-sm transition-all hover:shadow-md">
                            <div className="text-muted-foreground text-xs font-semibold tracking-wider uppercase">Curriculum Years</div>
                            <div className="text-foreground mt-2 text-3xl font-bold">{filteredStats.curriculum_versions}</div>
                            <p className="text-muted-foreground mt-1 text-xs font-medium">Active versions</p>
                        </div>
                    </CardContent>
                </Card>

                {/* Department Filter Tabs + Table */}
                <Card className="border shadow-sm">
                    <CardHeader className="bg-muted/20 border-b pb-4">
                        <div className="flex flex-col gap-4">
                            <div className="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
                                <div>
                                    <CardTitle className="text-lg">Programs</CardTitle>
                                    <CardDescription>Search, filter, and manage your academic programs.</CardDescription>
                                </div>
                                <div className="flex items-center gap-2">
                                    <div className="relative w-full sm:w-64">
                                        <Search className="text-muted-foreground absolute top-2.5 left-2.5 h-4 w-4" />
                                        <Input
                                            id="program-search"
                                            placeholder="Search program, dept, or year..."
                                            value={globalFilter ?? ""}
                                            onChange={(event) => setGlobalFilter(event.target.value)}
                                            className="bg-background h-9 w-full pl-9"
                                        />
                                    </div>
                                </div>
                            </div>
                            {/* Department Filter Pills */}
                            {departmentsWithPrograms.length > 1 && (
                                <div className="flex flex-wrap gap-1.5">
                                    <button
                                        type="button"
                                        onClick={() => setActiveDepartment("all")}
                                        className={`rounded-full border px-3 py-1 text-xs font-medium transition-colors ${
                                            activeDepartment === "all"
                                                ? "bg-primary text-primary-foreground border-primary"
                                                : "bg-muted/50 text-muted-foreground hover:bg-muted border-transparent"
                                        }`}
                                    >
                                        All ({programs.length})
                                    </button>
                                    {departmentsWithPrograms.map((dept) => {
                                        const count = programs.filter((p) => p.department_id === dept.id).length;
                                        return (
                                            <button
                                                type="button"
                                                key={dept.id}
                                                onClick={() => setActiveDepartment(String(dept.id))}
                                                className={`rounded-full border px-3 py-1 text-xs font-medium transition-colors ${
                                                    activeDepartment === String(dept.id)
                                                        ? "bg-primary text-primary-foreground border-primary"
                                                        : "bg-muted/50 text-muted-foreground hover:bg-muted border-transparent"
                                                }`}
                                            >
                                                {dept.code} ({count})
                                            </button>
                                        );
                                    })}
                                </div>
                            )}
                        </div>
                    </CardHeader>
                    <CardContent className="p-0">
                        <Table>
                            <TableHeader className="bg-muted/30">
                                {table.getHeaderGroups().map((headerGroup) => (
                                    <TableRow key={headerGroup.id} className="hover:bg-transparent">
                                        {headerGroup.headers.map((header) => (
                                            <TableHead key={header.id} className="py-3">
                                                {header.isPlaceholder ? null : flexRender(header.column.columnDef.header, header.getContext())}
                                            </TableHead>
                                        ))}
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

            {/* Create Course Dialog */}
            <Dialog
                open={isCreateOpen}
                onOpenChange={(open) => {
                    setIsCreateOpen(open);
                    if (!open) {
                        createForm.reset();
                        createForm.clearErrors();
                    }
                }}
            >
                <DialogContent className="bg-card border shadow-lg sm:max-w-2xl">
                    <DialogHeader className="mb-4 border-b pb-4">
                        <DialogTitle className="text-xl">Create New Course</DialogTitle>
                        <DialogDescription>Add a new academic program to the curriculum.</DialogDescription>
                    </DialogHeader>
                    <form className="grid gap-5" onSubmit={handleCreateProgram}>
                        <div className="grid gap-4 md:grid-cols-2">
                            <div className="grid gap-2">
                                <Label htmlFor="create-code" className="text-foreground/80 font-semibold">
                                    Program Code
                                </Label>
                                <Input
                                    id="create-code"
                                    placeholder="e.g. BSIT"
                                    value={createForm.data.code}
                                    onChange={(e) => createForm.setData("code", e.target.value)}
                                />
                                <FieldError message={createForm.errors.code} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="create-dept" className="text-foreground/80 font-semibold">
                                    Department
                                </Label>
                                <Select
                                    value={createForm.data.department_id}
                                    onValueChange={(value) => createForm.setData("department_id", value)}
                                >
                                    <SelectTrigger id="create-dept">
                                        <SelectValue placeholder="Select department" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {departments.map((dept) => (
                                            <SelectItem key={dept.id} value={String(dept.id)}>
                                                {dept.code} — {dept.name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <FieldError message={createForm.errors.department_id} />
                            </div>
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="create-title" className="text-foreground/80 font-semibold">
                                Program Title
                            </Label>
                            <Input
                                id="create-title"
                                placeholder="e.g. Bachelor of Science in Information Technology"
                                value={createForm.data.title}
                                onChange={(e) => createForm.setData("title", e.target.value)}
                            />
                            <FieldError message={createForm.errors.title} />
                        </div>
                        <div className="grid gap-4 md:grid-cols-2">
                            <div className="grid gap-2">
                                <Label htmlFor="create-curriculum-year" className="text-foreground/80 font-semibold">
                                    Curriculum Year
                                </Label>
                                <Input
                                    id="create-curriculum-year"
                                    placeholder="e.g. 2024-2025"
                                    value={createForm.data.curriculum_year}
                                    onChange={(e) => createForm.setData("curriculum_year", e.target.value)}
                                />
                                <FieldError message={createForm.errors.curriculum_year} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="create-misc" className="text-foreground/80 font-semibold">
                                    Misc Fee (₱)
                                </Label>
                                <Input
                                    id="create-misc"
                                    type="number"
                                    min="0"
                                    placeholder="e.g. 3500"
                                    value={createForm.data.miscelaneous}
                                    onChange={(e) => createForm.setData("miscelaneous", e.target.value)}
                                />
                                <FieldError message={createForm.errors.miscelaneous} />
                            </div>
                        </div>
                        <div className="grid gap-4 md:grid-cols-2">
                            <div className="grid gap-2">
                                <Label htmlFor="create-lec" className="text-foreground/80 font-semibold">
                                    Lecture Rate / Unit
                                </Label>
                                <Input
                                    id="create-lec"
                                    type="number"
                                    min="0"
                                    value={createForm.data.lec_per_unit}
                                    onChange={(e) => createForm.setData("lec_per_unit", e.target.value)}
                                />
                                <FieldError message={createForm.errors.lec_per_unit} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="create-lab" className="text-foreground/80 font-semibold">
                                    Lab Rate / Unit
                                </Label>
                                <Input
                                    id="create-lab"
                                    type="number"
                                    min="0"
                                    value={createForm.data.lab_per_unit}
                                    onChange={(e) => createForm.setData("lab_per_unit", e.target.value)}
                                />
                                <FieldError message={createForm.errors.lab_per_unit} />
                            </div>
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="create-description" className="text-foreground/80 font-semibold">
                                Description
                            </Label>
                            <Textarea
                                id="create-description"
                                className="min-h-[80px] resize-none"
                                placeholder="Brief description of this program..."
                                value={createForm.data.description}
                                onChange={(e) => createForm.setData("description", e.target.value)}
                            />
                            <FieldError message={createForm.errors.description} />
                        </div>
                        <DialogFooter className="mt-2 border-t pt-4">
                            <Button type="button" variant="ghost" onClick={() => setIsCreateOpen(false)}>
                                Cancel
                            </Button>
                            <Button type="submit" disabled={createForm.processing}>
                                <Plus className="mr-2 size-4" />
                                Create Course
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </AdminLayout>
    );
}
