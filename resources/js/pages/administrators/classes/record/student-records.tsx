import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from "@/components/ui/dropdown-menu";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table";
import { exportStudentList } from "@/routes/administrators/classes";
import { show as showEnrollment } from "@/routes/administrators/enrollments";
import { router } from "@inertiajs/react";
import {
    flexRender,
    getCoreRowModel,
    getPaginationRowModel,
    getSortedRowModel,
    useReactTable,
    type ColumnDef,
    type SortingState,
} from "@tanstack/react-table";
import {
    ArrowDown,
    ArrowRightLeft,
    ArrowUp,
    ArrowUpDown,
    ChevronLeft,
    ChevronRight,
    Download,
    ExternalLink,
    MoreHorizontal,
    Search,
} from "lucide-react";
import { useId, useMemo, useState } from "react";
import type { ClassShowData, EnrollmentRow } from "./types";

export function StudentRecords({
    classItem,
    enrollments,
    onMove,
    mode = "roster",
}: {
    classItem: ClassShowData;
    enrollments: EnrollmentRow[];
    onMove: (student: EnrollmentRow) => void;
    mode?: "roster" | "assessments";
}) {
    const [search, setSearch] = useState("");
    const [sorting, setSorting] = useState<SortingState>([]);
    const searchId = useId();
    const pageSizeId = useId();
    const headingId = useId();
    const assessments = mode === "assessments";
    const filtered = useMemo(() => {
        const term = search.trim().toLowerCase();
        if (!term) return enrollments;
        return enrollments.filter(
            ({ student }) =>
                student &&
                [student.name, student.student_id, student.course].some((value) =>
                    String(value ?? "")
                        .toLowerCase()
                        .includes(term),
                ),
        );
    }, [enrollments, search]);
    const columns = useMemo<ColumnDef<EnrollmentRow>[]>(
        () => [
            {
                id: "student",
                accessorFn: (row) => row.student?.name || "",
                header: "Student",
                cell: ({ row }) => (
                    <div className="min-w-40 space-y-1">
                        <p className="font-medium">{row.original.student?.name || "Student unavailable"}</p>
                        <p className="text-muted-foreground text-sm">{row.original.student?.student_id || "No student ID"}</p>
                    </div>
                ),
            },
            ...(assessments
                ? ([
                      { accessorKey: "prelim_grade", header: "Prelim", cell: ({ getValue }) => <Grade value={getValue() as number | null} /> },
                      { accessorKey: "midterm_grade", header: "Midterm", cell: ({ getValue }) => <Grade value={getValue() as number | null} /> },
                      { accessorKey: "finals_grade", header: "Finals", cell: ({ getValue }) => <Grade value={getValue() as number | null} /> },
                  ] satisfies ColumnDef<EnrollmentRow>[])
                : ([
                      {
                          id: "course",
                          accessorFn: (row) => row.student?.course || "",
                          header: "Course & year",
                          cell: ({ row }) => (
                              <div className="space-y-1">
                                  <p>{row.original.student?.course || "Not specified"}</p>
                                  <p className="text-muted-foreground text-sm">
                                      {row.original.student?.academic_year ? `Year ${row.original.student.academic_year}` : "Year not specified"}
                                  </p>
                              </div>
                          ),
                      },
                      {
                          accessorKey: "status",
                          header: "Status",
                          cell: ({ row }) => (
                              <Badge
                                  variant="outline"
                                  className={
                                      row.original.status ? "border-emerald-600/25 text-emerald-700 dark:text-emerald-400" : "text-muted-foreground"
                                  }
                              >
                                  {row.original.status ? "Active" : "Inactive"}
                              </Badge>
                          ),
                      },
                  ] satisfies ColumnDef<EnrollmentRow>[])),
            { accessorKey: "total_average", header: "Average", cell: ({ getValue }) => <Grade value={getValue() as number | null} /> },
            ...(assessments
                ? ([
                      {
                          accessorKey: "remarks",
                          header: "Remarks",
                          cell: ({ row }) => <span className="text-muted-foreground text-sm">{row.original.remarks || "—"}</span>,
                      },
                  ] satisfies ColumnDef<EnrollmentRow>[])
                : []),
            {
                id: "actions",
                header: () => <span className="sr-only">Student actions</span>,
                enableSorting: false,
                cell: ({ row }) => <StudentActions enrollment={row.original} onMove={onMove} />,
            },
        ],
        [assessments, onMove],
    );
    const table = useReactTable({
        data: filtered,
        columns,
        getCoreRowModel: getCoreRowModel(),
        getSortedRowModel: getSortedRowModel(),
        getPaginationRowModel: getPaginationRowModel(),
        onSortingChange: setSorting,
        state: { sorting },
        initialState: { pagination: { pageSize: 25 } },
    });
    const { pageIndex, pageSize } = table.getState().pagination;
    const recorded = enrollments.filter((enrollment) => enrollment.total_average !== null).length;
    const partial = enrollments.filter(
        (enrollment) =>
            enrollment.total_average === null &&
            [enrollment.prelim_grade, enrollment.midterm_grade, enrollment.finals_grade].some((grade) => grade !== null),
    ).length;

    return (
        <div className="space-y-6">
            {assessments && (
                <dl className="grid grid-cols-1 gap-4 sm:grid-cols-3">
                    <AssessmentStat label="Average recorded" count={recorded} />
                    <AssessmentStat label="Grades in progress" count={partial} />
                    <AssessmentStat label="Awaiting grades" count={enrollments.length - recorded - partial} />
                </dl>
            )}
            <section className="bg-card min-w-0 overflow-hidden rounded-xl border" aria-labelledby={headingId}>
                <div className="flex flex-col gap-4 p-4 sm:p-6">
                    <div className="flex flex-wrap items-start justify-between gap-4">
                        <div className="space-y-1">
                            <h2 id={headingId} className="text-base font-semibold">
                                {assessments ? "Assessment & grades" : "Class roster"}
                            </h2>
                            <p className="text-muted-foreground text-sm">
                                {assessments
                                    ? "Recorded grades by grading period. Open an enrollment for its full record."
                                    : `${classItem.students_count} students enrolled. Manage enrollment records and section transfers.`}
                            </p>
                        </div>
                        <DropdownMenu>
                            <DropdownMenuTrigger render={<Button variant="outline" className="h-9 px-3" />}>
                                <Download className="size-4" aria-hidden="true" />
                                Export students
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="end" className="w-48">
                                <DropdownMenuItem
                                    onClick={() =>
                                        window.open(
                                            exportStudentList.url(classItem.id, { query: { format: "excel" } }),
                                            "_blank",
                                            "noopener,noreferrer",
                                        )
                                    }
                                >
                                    Download Excel
                                </DropdownMenuItem>
                                <DropdownMenuItem
                                    onClick={() =>
                                        window.open(
                                            exportStudentList.url(classItem.id, { query: { format: "pdf" } }),
                                            "_blank",
                                            "noopener,noreferrer",
                                        )
                                    }
                                >
                                    Download PDF
                                </DropdownMenuItem>
                            </DropdownMenuContent>
                        </DropdownMenu>
                    </div>
                    <div className="max-w-md space-y-2">
                        <Label htmlFor={searchId} className="sr-only">
                            Search students by name, ID, or course
                        </Label>
                        <div className="relative">
                            <Search className="text-muted-foreground absolute top-1/2 left-3 size-4 -translate-y-1/2" aria-hidden="true" />
                            <Input
                                id={searchId}
                                type="search"
                                placeholder="Search name, student ID, or course…"
                                value={search}
                                onChange={(event) => {
                                    setSearch(event.target.value);
                                    table.setPageIndex(0);
                                }}
                                className="h-10 pl-10"
                            />
                        </div>
                    </div>
                </div>
                <div className="overflow-x-auto border-y">
                    <Table>
                        <TableHeader className="bg-muted/40">
                            {table.getHeaderGroups().map((group) => (
                                <TableRow key={group.id}>
                                    {group.headers.map((header) => (
                                        <TableHead
                                            key={header.id}
                                            className="h-12 px-4 text-sm"
                                            aria-sort={
                                                header.column.getIsSorted() === "asc"
                                                    ? "ascending"
                                                    : header.column.getIsSorted() === "desc"
                                                      ? "descending"
                                                      : undefined
                                            }
                                        >
                                            {header.isPlaceholder ? null : header.column.getCanSort() ? (
                                                <button
                                                    type="button"
                                                    onClick={header.column.getToggleSortingHandler()}
                                                    className="text-foreground hover:text-primary focus-visible:outline-ring inline-flex min-h-8 items-center gap-2 rounded-sm text-sm font-medium focus-visible:outline-2"
                                                >
                                                    {flexRender(header.column.columnDef.header, header.getContext())}
                                                    {header.column.getIsSorted() === "asc" ? (
                                                        <ArrowUp className="size-3" aria-hidden="true" />
                                                    ) : header.column.getIsSorted() === "desc" ? (
                                                        <ArrowDown className="size-3" aria-hidden="true" />
                                                    ) : (
                                                        <ArrowUpDown className="text-muted-foreground size-3" aria-hidden="true" />
                                                    )}
                                                </button>
                                            ) : (
                                                flexRender(header.column.columnDef.header, header.getContext())
                                            )}
                                        </TableHead>
                                    ))}
                                </TableRow>
                            ))}
                        </TableHeader>
                        <TableBody>
                            {table.getRowModel().rows.length ? (
                                table.getRowModel().rows.map((row) => (
                                    <TableRow key={row.id}>
                                        {row.getVisibleCells().map((cell) => (
                                            <TableCell key={cell.id} className="px-4 py-4 text-sm">
                                                {flexRender(cell.column.columnDef.cell, cell.getContext())}
                                            </TableCell>
                                        ))}
                                    </TableRow>
                                ))
                            ) : (
                                <TableRow>
                                    <TableCell colSpan={columns.length} className="h-40 px-6 text-center">
                                        <p className="text-sm font-medium">{search ? "No matching students" : "No students enrolled"}</p>
                                        <p className="text-muted-foreground mt-1 text-sm">
                                            {search
                                                ? "Try another name, student ID, or course."
                                                : "Student records will appear here when enrollments are added."}
                                        </p>
                                        {search && (
                                            <Button variant="ghost" className="mt-3" onClick={() => setSearch("")}>
                                                Clear search
                                            </Button>
                                        )}
                                    </TableCell>
                                </TableRow>
                            )}
                        </TableBody>
                    </Table>
                </div>
                <div className="flex flex-wrap items-center justify-between gap-4 p-4 text-sm sm:px-6">
                    <p className="text-muted-foreground" aria-live="polite">
                        {filtered.length === 0
                            ? "0 students"
                            : `${pageIndex * pageSize + 1}–${Math.min((pageIndex + 1) * pageSize, filtered.length)} of ${filtered.length} students`}
                    </p>
                    <div className="flex flex-wrap items-center gap-4">
                        <div className="flex items-center gap-2">
                            <Label htmlFor={pageSizeId} className="text-muted-foreground text-sm font-normal">
                                Rows
                            </Label>
                            <Select value={String(pageSize)} onValueChange={(value) => table.setPageSize(Number(value))}>
                                <SelectTrigger id={pageSizeId} className="w-20">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent side="top">
                                    {[10, 25, 50, 75, 100].map((size) => (
                                        <SelectItem key={size} value={String(size)}>
                                            {size}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                        <div className="flex items-center gap-2">
                            <Button
                                variant="outline"
                                size="icon"
                                className="size-9"
                                aria-label="Previous page of students"
                                disabled={!table.getCanPreviousPage()}
                                onClick={() => table.previousPage()}
                            >
                                <ChevronLeft className="size-4" />
                            </Button>
                            <span className="text-muted-foreground tabular-nums">
                                {pageIndex + 1} / {table.getPageCount() || 1}
                            </span>
                            <Button
                                variant="outline"
                                size="icon"
                                className="size-9"
                                aria-label="Next page of students"
                                disabled={!table.getCanNextPage()}
                                onClick={() => table.nextPage()}
                            >
                                <ChevronRight className="size-4" />
                            </Button>
                        </div>
                    </div>
                </div>
            </section>
        </div>
    );
}

function Grade({ value }: { value: number | null }) {
    return (
        <span className={`tabular-nums ${value === null ? "text-muted-foreground" : "font-medium"}`}>
            {value !== null ? Number(value).toFixed(2) : "—"}
        </span>
    );
}

function AssessmentStat({ label, count }: { label: string; count: number }) {
    return (
        <div className="bg-card space-y-2 rounded-xl border p-4 sm:p-6">
            <dt className="text-muted-foreground text-sm">{label}</dt>
            <dd className="text-2xl font-semibold tabular-nums">{count}</dd>
        </div>
    );
}

function StudentActions({ enrollment, onMove }: { enrollment: EnrollmentRow; onMove: (student: EnrollmentRow) => void }) {
    return (
        <DropdownMenu>
            <DropdownMenuTrigger
                render={<Button variant="ghost" size="icon" className="size-9" aria-label={`Actions for ${enrollment.student?.name || "student"}`} />}
            >
                <MoreHorizontal className="size-4" aria-hidden="true" />
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" className="w-48">
                {enrollment.student_enrollment_id && (
                    <DropdownMenuItem onClick={() => router.get(showEnrollment.url(enrollment.student_enrollment_id!))}>
                        <ExternalLink className="size-4" aria-hidden="true" />
                        View enrollment
                    </DropdownMenuItem>
                )}
                <DropdownMenuItem onClick={() => onMove(enrollment)}>
                    <ArrowRightLeft className="size-4" aria-hidden="true" />
                    Move to section
                </DropdownMenuItem>
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
