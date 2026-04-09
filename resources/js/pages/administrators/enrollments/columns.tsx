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

declare let route: any;

// Define the shape of our data based on the controller output
export type EnrollmentRow = {
    id: number;
    student_id: number | string | null;
    student_name: string | null;
    course: string | null;
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

export const createColumns = (
    actions?: EnrollmentActions,
    currency: string = "PHP",
    pipeline?: PipelineDisplay,
): ColumnDef<EnrollmentRow>[] => [
    {
        id: "select",
        header: ({ table }) => (
            <Checkbox
                checked={table.getIsAllPageRowsSelected() || (table.getIsSomePageRowsSelected() && "indeterminate")}
                onCheckedChange={(value) => table.toggleAllPageRowsSelected(!!value)}
                aria-label="Select all"
                className="translate-y-[2px]"
            />
        ),
        cell: ({ row }) => (
            <Checkbox
                checked={row.getIsSelected()}
                onCheckedChange={(value) => row.toggleSelected(!!value)}
                aria-label="Select row"
                className="translate-y-[2px]"
                onClick={(e) => e.stopPropagation()}
            />
        ),
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
                <div className="flex items-center gap-4">
                    <div className="flex h-10 w-10 items-center justify-center rounded-full bg-emerald-50 text-emerald-600 dark:bg-emerald-900/20 dark:text-emerald-400">
                        <Avatar className="h-8 w-8 border">
                            <AvatarFallback
                                className={cn(
                                    "text-xs font-medium",
                                    enrollment.is_trashed
                                        ? "bg-red-100 text-red-700 dark:bg-red-900 dark:text-red-400"
                                        : "bg-emerald-100 text-emerald-700 dark:bg-emerald-900 dark:text-emerald-400",
                                )}
                            >
                                {getInitials(enrollment.student_name)}
                            </AvatarFallback>
                        </Avatar>
                    </div>
                    <div className="flex-1">
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
                        <p className="text-muted-foreground text-xs">{enrollment.course ?? "—"}</p>
                    </div>
                </div>
            );
        },
    },
    {
        accessorKey: "school_year",
        header: "Academic Period",
        cell: ({ row }) => {
            const enrollment = row.original;
            return (
                <div className="space-y-1">
                    <p className="text-sm font-medium">{enrollment.school_year}</p>
                    <div className="text-muted-foreground flex items-center gap-2 text-xs">
                        <span>Sem {enrollment.semester}</span>
                        <span className="bg-muted-foreground/30 h-1 w-1 rounded-full" />
                        <span>Year {enrollment.academic_year}</span>
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
                <Badge variant="secondary" className={cn("font-medium", statusClass)}>
                    {status}
                </Badge>
            );
        },
    },
    {
        accessorKey: "tuition",
        header: "Tuition",
        cell: ({ row }) => {
            const tuition = row.original.tuition;
            return (
                <div>
                    <p className="text-foreground text-sm font-semibold">{formatMoney(tuition?.overall, currency)}</p>
                    {tuition?.balance && tuition.balance > 0 ? (
                        <p className="text-xs text-red-500">Balance: {formatMoney(tuition.balance, currency)}</p>
                    ) : (
                        <p className="text-xs text-emerald-600">Fully Paid</p>
                    )}
                </div>
            );
        },
    },
    {
        id: "actions",
        cell: ({ row }) => {
            const enrollment = row.original;
            return (
                <div className="flex items-center justify-end gap-1">
                    {!enrollment.is_trashed && (
                        <Button variant="outline" size="sm" asChild className="h-8 px-2" onClick={(e) => e.stopPropagation()}>
                            <Link
                                href={route("administrators.enrollments.edit", enrollment.id)}
                                className="flex items-center"
                                onClick={(e) => e.stopPropagation()}
                            >
                                <Pencil className="mr-2 h-4 w-4" />
                                Edit
                            </Link>
                        </Button>
                    )}
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
