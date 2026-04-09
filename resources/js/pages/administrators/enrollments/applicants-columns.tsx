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
import type { ColumnDef } from "@tanstack/react-table";
import { AlertTriangle, ArrowUpDown, Award, Copy, Eye, MoreHorizontal, Pencil, Trash2, UserPlus } from "lucide-react";
import type { ApplicantRow } from "./types";

declare let route: any;

type ApplicantActions = {
    onManageScholarship?: (applicant: ApplicantRow) => void;
    onDelete?: (applicant: ApplicantRow) => void;
    onForceDelete?: (applicant: ApplicantRow) => void;
};

const getInitials = (name: string | null) => {
    if (!name) return "ST";
    return name
        .split(" ")
        .map((n) => n[0])
        .join("")
        .toUpperCase()
        .slice(0, 2);
};

export const createApplicantColumns = (actions?: ApplicantActions): ColumnDef<ApplicantRow>[] => [
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
        accessorKey: "name",
        header: ({ column }) => (
            <Button variant="ghost" onClick={() => column.toggleSorting(column.getIsSorted() === "asc")} className="-ml-4">
                Applicant
                <ArrowUpDown className="ml-2 h-4 w-4" />
            </Button>
        ),
        cell: ({ row }) => {
            const applicant = row.original;
            return (
                <div className="flex items-center gap-4">
                    <Avatar className="h-9 w-9 border">
                        <AvatarFallback
                            className={cn(
                                "text-xs font-medium",
                                applicant.is_trashed
                                    ? "bg-red-100 text-red-700 dark:bg-red-900 dark:text-red-400"
                                    : "bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300",
                            )}
                        >
                            {getInitials(applicant.name)}
                        </AvatarFallback>
                    </Avatar>
                    <div className="flex-1">
                        <div className="flex items-center gap-2">
                            <p
                                className={cn(
                                    "text-sm font-semibold",
                                    applicant.is_trashed ? "text-muted-foreground line-through" : "text-foreground",
                                )}
                            >
                                {applicant.name}
                            </p>
                            {applicant.is_trashed && (
                                <Badge variant="destructive" className="px-1.5 py-0 text-[10px]">
                                    Deleted
                                </Badge>
                            )}
                        </div>
                        <p className="text-muted-foreground text-xs">ID: {applicant.student_id ?? "Pending"}</p>
                    </div>
                </div>
            );
        },
    },
    {
        accessorKey: "course",
        header: ({ column }) => (
            <Button variant="ghost" onClick={() => column.toggleSorting(column.getIsSorted() === "asc")} className="-ml-4">
                Program
                <ArrowUpDown className="ml-2 h-4 w-4" />
            </Button>
        ),
        cell: ({ row }) => {
            const course = row.original.course;
            return (
                <Badge variant="outline" className="font-normal">
                    {course || "—"}
                </Badge>
            );
        },
    },
    {
        accessorKey: "department",
        header: ({ column }) => (
            <Button variant="ghost" onClick={() => column.toggleSorting(column.getIsSorted() === "asc")} className="-ml-4">
                Department
                <ArrowUpDown className="ml-2 h-4 w-4" />
            </Button>
        ),
        cell: ({ row }) => <span className="text-muted-foreground text-sm">{row.original.department || "—"}</span>,
    },
    {
        accessorKey: "scholarship_type",
        header: "Scholarship",
        cell: ({ row }) => {
            const scholarshipType = row.original.scholarship_type;
            return scholarshipType && scholarshipType !== "none" ? (
                <Badge className="bg-blue-500/10 text-blue-600 hover:bg-blue-500/20 dark:text-blue-400">{scholarshipType}</Badge>
            ) : (
                <span className="text-muted-foreground text-sm">—</span>
            );
        },
    },
    {
        accessorKey: "created_at",
        header: ({ column }) => (
            <Button variant="ghost" onClick={() => column.toggleSorting(column.getIsSorted() === "asc")} className="-ml-4">
                Applied
                <ArrowUpDown className="ml-2 h-4 w-4" />
            </Button>
        ),
        cell: ({ row }) => <span className="text-muted-foreground text-sm">{row.original.created_at ?? "—"}</span>,
    },
    {
        id: "actions",
        cell: ({ row }) => {
            const applicant = row.original;
            return (
                <div className="flex items-center justify-end gap-2">
                    {!applicant.is_trashed && (
                        <Button size="sm" asChild className="h-8 px-2" onClick={(e) => e.stopPropagation()}>
                            <Link href={route("administrators.enrollments.create", { student_id: applicant.id })}>
                                <UserPlus className="mr-2 h-4 w-4" />
                                Process
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
                                    navigator.clipboard.writeText(String(applicant.student_id ?? ""));
                                }}
                            >
                                <Copy className="mr-2 h-4 w-4" />
                                Copy Student ID
                            </DropdownMenuItem>
                            <DropdownMenuSeparator />
                            <DropdownMenuItem asChild>
                                <Link href={route("administrators.students.show", applicant.id)} className="flex w-full cursor-pointer items-center">
                                    <Eye className="mr-2 h-4 w-4" />
                                    View Record
                                </Link>
                            </DropdownMenuItem>
                            <DropdownMenuItem asChild>
                                <Link href={route("administrators.students.edit", applicant.id)} className="flex w-full cursor-pointer items-center">
                                    <Pencil className="mr-2 h-4 w-4" />
                                    Edit Record
                                </Link>
                            </DropdownMenuItem>
                            {!applicant.is_trashed && (
                                <DropdownMenuItem
                                    onClick={(e) => {
                                        e.stopPropagation();
                                        actions?.onManageScholarship?.(applicant);
                                    }}
                                >
                                    <Award className="mr-2 h-4 w-4" />
                                    Manage Scholarship
                                </DropdownMenuItem>
                            )}
                            <DropdownMenuSeparator />
                            {applicant.is_trashed ? (
                                <DropdownMenuItem
                                    onClick={(e) => {
                                        e.stopPropagation();
                                        actions?.onForceDelete?.(applicant);
                                    }}
                                    className="text-red-600 focus:text-red-600"
                                >
                                    <AlertTriangle className="mr-2 h-4 w-4" />
                                    Force Delete
                                </DropdownMenuItem>
                            ) : (
                                <>
                                    <DropdownMenuItem
                                        onClick={(e) => {
                                            e.stopPropagation();
                                            actions?.onDelete?.(applicant);
                                        }}
                                        className="text-red-600 focus:text-red-600"
                                    >
                                        <Trash2 className="mr-2 h-4 w-4" />
                                        Delete
                                    </DropdownMenuItem>
                                    <DropdownMenuItem
                                        onClick={(e) => {
                                            e.stopPropagation();
                                            actions?.onForceDelete?.(applicant);
                                        }}
                                        className="text-red-600 focus:text-red-600"
                                    >
                                        <AlertTriangle className="mr-2 h-4 w-4" />
                                        Permanently Delete
                                    </DropdownMenuItem>
                                </>
                            )}
                        </DropdownMenuContent>
                    </DropdownMenu>
                </div>
            );
        },
    },
];
