import { Avatar, AvatarFallback } from "@/components/ui/avatar";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Checkbox } from "@/components/ui/checkbox";
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import { cn } from "@/lib/utils";
import { Link } from "@inertiajs/react";
import { ColumnDef } from "@tanstack/react-table";
import { AlertTriangle, ArrowUpDown, Copy, Eye, FileText, MoreHorizontal, Pencil, RotateCcw, Trash2 } from "lucide-react";
import type { RefObject } from "react";
import { route } from "ziggy-js";

// Define the shape of our data based on the controller output
export type EnrollmentRow = {
    id: number;
    student_id: number | string | null;
    student_name: string | null;
    course_id: number | null;
    course: string | null;
    course_title: string | null;
    department: string | null;
    status: string | null;
    school_year: string | null;
    semester: number | null;
    academic_year: number | null;
    subjects_count: number;
    tuition: {
        overall: number;
        balance: number;
    } | null;
    created_at: string | null;
    deleted_at?: string | null;
    is_trashed?: boolean;
};

// Action handlers type for enrollment actions
export type EnrollmentActions = {
    onDelete?: (enrollment: EnrollmentRow) => void;
    onForceDelete?: (enrollment: EnrollmentRow) => void;
    onRestore?: (enrollment: EnrollmentRow) => void;
    selectionRefs?: EnrollmentSelectionRefs;
};

export type EnrollmentSelectionRefs = {
    anchorId: RefObject<string | null>;
    shiftPressed: RefObject<boolean>;
};

type PipelineDisplay = {
    finalStatus: string;
    statusClasses: Record<string, string>;
};

function formatMoney(value: number | null | undefined, currency: string = "PHP"): string {
    if (value === null || value === undefined) return "—";
    return new Intl.NumberFormat(currency === "USD" ? "en-US" : "en-PH", { style: "currency", currency: currency }).format(value);
}

const getInitials = (name: string | null) => {
    if (!name) return "ST";
    return name
        .split(" ")
        .map((n) => n[0])
        .join("")
        .toUpperCase()
        .slice(0, 2);
};

function formatDate(value: string | null): string {
    if (!value) return "—";

    const date = new Date(value);

    if (Number.isNaN(date.getTime())) return "—";

    return new Intl.DateTimeFormat("en-PH", {
        month: "short",
        day: "numeric",
        year: "numeric",
    }).format(date);
}

export const createColumns = (actions?: EnrollmentActions, currency: string = "PHP", pipeline?: PipelineDisplay): ColumnDef<EnrollmentRow>[] => [
    {
        id: "select",
        header: ({ table }) => (
            <div className="flex h-full items-center">
                <Checkbox
                    checked={table.getIsAllPageRowsSelected() || (table.getIsSomePageRowsSelected() && "indeterminate")}
                    onCheckedChange={(value) => table.toggleAllPageRowsSelected(!!value)}
                    aria-label="Select all"
                />
            </div>
        ),
        cell: ({ row, table }) => {
            const handleCheckedChange = (value: boolean | "indeterminate") => {
                const selectionRefs = actions?.selectionRefs;

                if (selectionRefs) {
                    if (selectionRefs.shiftPressed.current) {
                        selectionRefs.shiftPressed.current = false;

                        const rows = table.getRowModel().rows;
                        const currentIndex = rows.findIndex((candidate) => candidate.id === row.id);
                        const anchorIndex =
                            selectionRefs.anchorId.current === null
                                ? -1
                                : rows.findIndex((candidate) => candidate.id === selectionRefs.anchorId.current);

                        if (currentIndex !== -1 && anchorIndex !== -1) {
                            const [start, end] = anchorIndex <= currentIndex ? [anchorIndex, currentIndex] : [currentIndex, anchorIndex];

                            for (let index = start; index <= end; index++) {
                                rows[index]?.toggleSelected(!!value);
                            }

                            return;
                        }
                    }

                    selectionRefs.anchorId.current = row.id;
                }

                row.toggleSelected(!!value);
            };

            return (
                <div className="flex h-full items-center">
                    <Checkbox
                        checked={row.getIsSelected()}
                        onCheckedChange={handleCheckedChange}
                        aria-label="Select row"
                        onClick={(event) => {
                            event.stopPropagation();

                            if (actions?.selectionRefs) {
                                actions.selectionRefs.shiftPressed.current = event.shiftKey;
                            }
                        }}
                    />
                </div>
            );
        },
        enableSorting: false,
        enableHiding: false,
    },
    {
        accessorKey: "student_name",
        header: ({ column }) => {
            return (
                <Button variant="ghost" onClick={() => column.toggleSorting(column.getIsSorted() === "asc")} className="-ml-4">
                    Student
                    <ArrowUpDown className="ml-2 h-4 w-4" />
                </Button>
            );
        },
        cell: ({ row }) => {
            const enrollment = row.original;
            return (
                <div className="flex min-w-[220px] items-center gap-3">
                    <Avatar className="border-border/70 size-9 border">
                        <AvatarFallback
                            className={cn(
                                "text-xs font-semibold",
                                enrollment.is_trashed
                                    ? "bg-red-100 text-red-700 dark:bg-red-900/50 dark:text-red-300"
                                    : "bg-emerald-100 text-emerald-700 dark:bg-emerald-900/50 dark:text-emerald-300",
                            )}
                        >
                            {getInitials(enrollment.student_name)}
                        </AvatarFallback>
                    </Avatar>
                    <div className="min-w-0 flex-1">
                        <div className="flex items-center gap-2">
                            <p
                                className={cn(
                                    "text-sm font-semibold",
                                    enrollment.is_trashed ? "text-muted-foreground line-through" : "text-foreground",
                                )}
                            >
                                {enrollment.student_name ?? "Unknown"}
                            </p>
                            {enrollment.is_trashed && (
                                <Badge variant="destructive" className="px-1.5 py-0 text-[10px]">
                                    Deleted
                                </Badge>
                            )}
                        </div>
                        <p className="text-muted-foreground truncate text-xs">
                            {enrollment.student_id ? `ID ${enrollment.student_id}` : "No student ID"}
                        </p>
                    </div>
                </div>
            );
        },
    },
    {
        accessorKey: "course",
        header: "Course / Program",
        cell: ({ row }) => {
            const enrollment = row.original;

            return (
                <div className="min-w-[175px]">
                    <p className="text-sm font-medium">{enrollment.course ?? "—"}</p>
                    <p className="text-muted-foreground max-w-[220px] truncate text-xs">{enrollment.course_title ?? "Program not assigned"}</p>
                </div>
            );
        },
    },
    {
        accessorKey: "department",
        header: "Department",
        cell: ({ row }) => <span className="text-muted-foreground min-w-[95px] text-sm font-medium">{row.original.department ?? "Unassigned"}</span>,
    },
    {
        accessorKey: "school_year",
        header: "Academic Period",
        cell: ({ row }) => {
            const enrollment = row.original;
            return (
                <div className="min-w-[125px] space-y-1">
                    <p className="text-sm font-medium">{enrollment.school_year ?? "—"}</p>
                    <div className="text-muted-foreground flex items-center gap-2 text-[11px]">
                        <span>Sem {enrollment.semester ?? "—"}</span>
                        <span className="bg-muted-foreground/30 h-1 w-1 rounded-full" aria-hidden="true" />
                        <span>Year {enrollment.academic_year ?? "—"}</span>
                    </div>
                </div>
            );
        },
    },
    {
        accessorKey: "status",
        header: "Status",
        cell: ({ row }) => {
            const status = row.original.status;
            const statusClass =
                (status ? pipeline?.statusClasses?.[status] : undefined) ??
                "bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-400 border-gray-200";
            return (
                <Badge variant="secondary" className={cn("rounded-full px-2.5 font-medium", statusClass)}>
                    {status ?? "Unspecified"}
                </Badge>
            );
        },
    },
    {
        accessorKey: "subjects_count",
        header: "Subjects",
        cell: ({ row }) => <span className="min-w-[70px] text-sm font-medium tabular-nums">{row.original.subjects_count.toLocaleString()}</span>,
    },
    {
        accessorKey: "tuition",
        header: "Tuition",
        cell: ({ row }) => {
            const tuition = row.original.tuition;
            return (
                <div className="min-w-[110px]">
                    <p className="text-foreground text-sm font-semibold tabular-nums">{formatMoney(tuition?.overall, currency)}</p>
                    <p className="text-muted-foreground text-[11px]">Total tuition</p>
                </div>
            );
        },
    },
    {
        id: "balance",
        header: "Balance",
        cell: ({ row }) => {
            const balance = row.original.tuition?.balance ?? 0;

            return (
                <div className="min-w-[110px]">
                    <p className={cn("text-sm font-semibold tabular-nums", balance > 0 ? "text-red-500" : "text-emerald-600 dark:text-emerald-400")}>
                        {balance > 0 ? formatMoney(balance, currency) : "Paid in full"}
                    </p>
                    <p className="text-muted-foreground text-[11px]">{balance > 0 ? "Outstanding" : "Cleared"}</p>
                </div>
            );
        },
    },
    {
        accessorKey: "created_at",
        header: "Enrolled",
        cell: ({ row }) => <span className="text-muted-foreground min-w-[105px] text-xs">{formatDate(row.original.created_at)}</span>,
    },
    {
        id: "actions",
        cell: ({ row }) => {
            const enrollment = row.original;
            return (
                <div className="flex items-center justify-end gap-1">
                    <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                            <Button variant="ghost" className="h-8 w-8 p-0" onClick={(e) => e.stopPropagation()}>
                                <span className="sr-only">Open menu</span>
                                <MoreHorizontal className="h-4 w-4" />
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end">
                            <DropdownMenuLabel>Actions</DropdownMenuLabel>
                            <DropdownMenuItem
                                onClick={(e) => {
                                    e.stopPropagation();
                                    navigator.clipboard.writeText(String(enrollment.student_id));
                                }}
                            >
                                <Copy className="mr-2 h-4 w-4" />
                                Copy Student ID
                            </DropdownMenuItem>
                            <DropdownMenuSeparator />
                            <DropdownMenuItem asChild>
                                <Link
                                    href={route("administrators.enrollments.show", enrollment.id)}
                                    className="flex w-full cursor-pointer items-center"
                                >
                                    <Eye className="mr-2 h-4 w-4" />
                                    View Enrollment
                                </Link>
                            </DropdownMenuItem>
                            <DropdownMenuItem asChild>
                                <Link
                                    href={route("administrators.enrollments.edit", enrollment.id)}
                                    className="flex w-full cursor-pointer items-center"
                                >
                                    <Pencil className="mr-2 h-4 w-4" />
                                    Edit Enrollment
                                </Link>
                            </DropdownMenuItem>
                            {enrollment.status === pipeline?.finalStatus && (
                                <DropdownMenuItem
                                    onClick={(e) => {
                                        e.stopPropagation();
                                        window.open(route("assessment.download", { record: enrollment.id }), "_blank");
                                    }}
                                >
                                    <FileText className="mr-2 h-4 w-4" />
                                    View Assessment
                                </DropdownMenuItem>
                            )}
                            <DropdownMenuSeparator />
                            {enrollment.is_trashed ? (
                                <>
                                    <DropdownMenuItem
                                        onClick={(e) => {
                                            e.stopPropagation();
                                            actions?.onRestore?.(enrollment);
                                        }}
                                        className="text-emerald-600 focus:text-emerald-600"
                                    >
                                        <RotateCcw className="mr-2 h-4 w-4" />
                                        Restore
                                    </DropdownMenuItem>
                                    <DropdownMenuItem
                                        onClick={(e) => {
                                            e.stopPropagation();
                                            actions?.onForceDelete?.(enrollment);
                                        }}
                                        className="text-red-600 focus:text-red-600"
                                    >
                                        <AlertTriangle className="mr-2 h-4 w-4" />
                                        Force Delete
                                    </DropdownMenuItem>
                                </>
                            ) : (
                                <DropdownMenuItem
                                    onClick={(e) => {
                                        e.stopPropagation();
                                        actions?.onDelete?.(enrollment);
                                    }}
                                    className="text-red-600 focus:text-red-600"
                                >
                                    <Trash2 className="mr-2 h-4 w-4" />
                                    Delete
                                </DropdownMenuItem>
                            )}
                        </DropdownMenuContent>
                    </DropdownMenu>
                </div>
            );
        },
    },
];

// Keep the old export for backward compatibility
export const columns = createColumns();
