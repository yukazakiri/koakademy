import { Avatar, AvatarFallback, AvatarImage } from "@/components/ui/avatar";
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
import { AdminLink } from "@/lib/admin-navigation";
import { ColumnDef } from "@tanstack/react-table";
import {
    ArrowUpDown,
    Check,
    CheckCircle,
    Copy,
    Eye,
    FileText,
    HelpCircle,
    MinusCircle,
    MoreHorizontal,
    RotateCcw,
    Trash2,
    UserCheck,
    Zap,
} from "lucide-react";
import { useState } from "react";

declare let route: (name: string, params?: Record<string, unknown> | string | number) => string;

// This type is used to define the shape of our data.
export type Student = {
    id: number;
    student_id: number | string | null;
    name: string;
    avatar_url: string | null;
    course_id: number | null;
    department_id: number | null;
    course: string | null;
    course_title: string | null;
    year_level: number | null;
    academic_year: string;
    type: string | null;
    status: string | null;
    scholarship_type_value: string | null;
    scholarship_type: string;
    employment_status_value: string | null;
    employment_status: string;
    is_indigenous_person: boolean;
    region_of_origin: string | null;
    previous_sem_clearance: "cleared" | "not_cleared" | "no_record";
    created_at: string | null;
    deleted_at: string | null;
    filament: {
        view_url: string;
        edit_url: string;
    };
};

export interface ColumnActionHandlers {
    onSoftDelete?: (student: Student) => void;
    onForceDelete?: (student: Student) => void;
    onRestore?: (student: Student) => void;
}

export const getInitials = (name: string) => {
    return (
        name
            .split(" ")
            .filter(Boolean)
            .map((n) => n[0])
            .join("")
            .toUpperCase()
            .slice(0, 2) || "ST"
    );
};

export const getStatusColor = (status: string | null) => {
    switch (status?.toLowerCase()) {
        case "enrolled":
            return "bg-emerald-100 text-emerald-800 hover:bg-emerald-100/80 dark:bg-emerald-950/40 dark:text-emerald-300 border-emerald-200 dark:border-emerald-800";
        case "graduated":
            return "bg-blue-100 text-blue-800 hover:bg-blue-100/80 dark:bg-blue-950/40 dark:text-blue-300 border-blue-200 dark:border-blue-800";
        case "dropped":
        case "withdrawn":
            return "bg-rose-100 text-rose-800 hover:bg-rose-100/80 dark:bg-rose-950/40 dark:text-rose-300 border-rose-200 dark:border-rose-800";
        case "applicant":
            return "bg-amber-100 text-amber-800 hover:bg-amber-100/80 dark:bg-amber-950/40 dark:text-amber-300 border-amber-200 dark:border-amber-800";
        default:
            return "bg-muted text-muted-foreground border-border";
    }
};

const addedDateFormatter = new Intl.DateTimeFormat("en-US", {
    month: "short",
    day: "numeric",
    year: "numeric",
});

const addedTimeFormatter = new Intl.DateTimeFormat("en-US", {
    hour: "numeric",
    minute: "2-digit",
});

function StudentIdCell({ studentId }: { studentId: string | number | null }) {
    const [copied, setCopied] = useState(false);

    if (!studentId) return <span className="text-muted-foreground font-mono text-xs">—</span>;

    const handleCopy = async (e: React.MouseEvent) => {
        e.stopPropagation();
        try {
            await navigator.clipboard.writeText(String(studentId));
            setCopied(true);
            setTimeout(() => setCopied(false), 1500);
        } catch {
            // fallback
        }
    };

    return (
        <Button
            variant="ghost"
            size="sm"
            className="group/id text-muted-foreground hover:text-foreground -ml-2 h-7 gap-1.5 px-2 font-mono text-xs"
            onClick={handleCopy}
            title={copied ? "Copied to clipboard!" : "Click to copy student ID"}
            aria-label={`Copy student ID ${studentId}`}
        >
            <span>{studentId}</span>
            {copied ? (
                <Check className="size-3.5 text-emerald-600 dark:text-emerald-400" />
            ) : (
                <Copy className="size-3.5 opacity-0 transition-opacity group-hover/id:opacity-100" />
            )}
        </Button>
    );
}

export function createColumns(handlers?: ColumnActionHandlers): ColumnDef<Student>[] {
    return [
        {
            id: "select",
            header: ({ table }) => (
                <Checkbox
                    checked={table.getIsAllPageRowsSelected() || (table.getIsSomePageRowsSelected() && "indeterminate")}
                    onCheckedChange={(value) => table.toggleAllPageRowsSelected(!!value)}
                    aria-label="Select all on this page"
                    className="translate-y-[2px]"
                />
            ),
            cell: ({ row }) => (
                <Checkbox
                    checked={row.getIsSelected()}
                    onCheckedChange={(value) => row.toggleSelected(!!value)}
                    aria-label={`Select student ${row.original.name}`}
                    className="translate-y-[2px]"
                />
            ),
            enableSorting: false,
            enableHiding: false,
        },
        {
            accessorKey: "student_id",
            header: ({ column }) => {
                return (
                    <Button variant="ghost" size="sm" onClick={() => column.toggleSorting(column.getIsSorted() === "asc")} className="-ml-3 h-8">
                        ID
                        <ArrowUpDown className="ml-1.5 size-3.5" />
                    </Button>
                );
            },
            cell: ({ row }) => <StudentIdCell studentId={row.getValue("student_id")} />,
        },
        {
            accessorKey: "name",
            header: ({ column }) => {
                return (
                    <Button variant="ghost" size="sm" onClick={() => column.toggleSorting(column.getIsSorted() === "asc")} className="-ml-3 h-8">
                        Student
                        <ArrowUpDown className="ml-1.5 size-3.5" />
                    </Button>
                );
            },
            cell: ({ row }) => {
                const student = row.original;
                return (
                    <div className="flex items-center gap-3">
                        <Avatar className="size-8 shrink-0 border">
                            <AvatarImage src={student.avatar_url ?? undefined} alt={student.name} />
                            <AvatarFallback className="bg-primary/10 text-primary text-xs font-semibold">{getInitials(student.name)}</AvatarFallback>
                        </Avatar>
                        <div className="flex min-w-0 flex-col">
                            <AdminLink
                                href={route("administrators.students.show", student.id)}
                                className="text-foreground truncate text-sm font-medium hover:underline"
                                title={student.name}
                            >
                                {student.name}
                            </AdminLink>
                            {student.is_indigenous_person && (
                                <span className="text-[10px] font-medium text-amber-600 dark:text-amber-400">Indigenous Person</span>
                            )}
                        </div>
                    </div>
                );
            },
        },
        {
            accessorKey: "course",
            header: "Course",
            cell: ({ row }) => {
                const course = row.original.course;
                const title = row.original.course_title;
                return (
                    <div className="flex max-w-[170px] flex-col">
                        <span className="text-foreground text-xs font-medium">{course ?? "—"}</span>
                        {title && (
                            <span className="text-muted-foreground truncate text-[11px]" title={title}>
                                {title}
                            </span>
                        )}
                    </div>
                );
            },
        },
        {
            accessorKey: "status",
            header: "Status",
            cell: ({ row }) => {
                const status = row.getValue("status") as string | null;
                return (
                    <Badge variant="outline" className={`text-[10px] font-semibold tracking-wide uppercase shadow-none ${getStatusColor(status)}`}>
                        {status ?? "Unknown"}
                    </Badge>
                );
            },
        },
        {
            accessorKey: "type",
            header: "Type",
            cell: ({ row }) => {
                return (
                    <span className="text-muted-foreground text-xs capitalize">{row.original.type ? row.original.type.replace(/_/g, " ") : "—"}</span>
                );
            },
        },
        {
            accessorKey: "previous_sem_clearance",
            header: "Clearance",
            cell: ({ row }) => {
                const status = row.original.previous_sem_clearance;
                if (status === "cleared") {
                    return (
                        <div className="inline-flex items-center gap-1.5 text-xs font-medium text-emerald-600 dark:text-emerald-400">
                            <CheckCircle className="size-3.5 shrink-0" />
                            <span>Cleared</span>
                        </div>
                    );
                }
                if (status === "not_cleared") {
                    return (
                        <div className="inline-flex items-center gap-1.5 text-xs font-medium text-amber-600 dark:text-amber-400">
                            <HelpCircle className="size-3.5 shrink-0" />
                            <span>Pending</span>
                        </div>
                    );
                }
                return (
                    <div className="text-muted-foreground inline-flex items-center gap-1.5 text-xs">
                        <MinusCircle className="size-3.5 shrink-0" />
                        <span>No record</span>
                    </div>
                );
            },
        },
        {
            accessorKey: "scholarship_type",
            header: "Scholarship",
            cell: ({ row }) => {
                const scholarship = row.getValue("scholarship_type") as string;
                if (!scholarship || scholarship === "None") return <span className="text-muted-foreground text-xs">—</span>;

                return (
                    <Badge variant="outline" className="max-w-[130px] truncate text-[10px] font-normal" title={scholarship}>
                        {scholarship}
                    </Badge>
                );
            },
        },
        {
            accessorKey: "created_at",
            header: ({ column }) => (
                <Button variant="ghost" size="sm" onClick={() => column.toggleSorting(column.getIsSorted() === "asc")} className="-ml-3 h-8">
                    Added
                    <ArrowUpDown className="ml-1.5 size-3.5" />
                </Button>
            ),
            cell: ({ row }) => {
                const createdAt = row.original.created_at;

                if (!createdAt) {
                    return <span className="text-muted-foreground text-xs">—</span>;
                }

                const date = new Date(createdAt);
                if (Number.isNaN(date.getTime())) {
                    return <span className="text-muted-foreground text-xs">—</span>;
                }

                return (
                    <div className="min-w-[7rem] text-xs" title={date.toLocaleString()}>
                        <div className="text-foreground font-medium">{addedDateFormatter.format(date)}</div>
                        <div className="text-muted-foreground text-[11px]">{addedTimeFormatter.format(date)}</div>
                    </div>
                );
            },
        },
        {
            id: "actions",
            cell: ({ row }) => {
                const student = row.original;

                const triggerSoftDelete = () => {
                    if (handlers?.onSoftDelete) {
                        handlers.onSoftDelete(student);
                    } else {
                        window.dispatchEvent(new CustomEvent("students:soft-delete", { detail: student }));
                    }
                };

                const triggerForceDelete = () => {
                    if (handlers?.onForceDelete) {
                        handlers.onForceDelete(student);
                    } else {
                        window.dispatchEvent(new CustomEvent("students:force-delete", { detail: student }));
                    }
                };

                const triggerRestore = () => {
                    if (handlers?.onRestore) {
                        handlers.onRestore(student);
                    } else {
                        window.dispatchEvent(new CustomEvent("students:restore", { detail: student }));
                    }
                };

                return (
                    <DropdownMenu>
                        <DropdownMenuTrigger
                            render={<Button variant="ghost" size="icon" className="size-8" />}
                            aria-label={`Actions for ${student.name}`}
                        >
                            <MoreHorizontal className="size-4" />
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end" className="w-48">
                            <DropdownMenuLabel>Actions</DropdownMenuLabel>
                            {student.student_id && (
                                <DropdownMenuItem onClick={() => navigator.clipboard.writeText(String(student.student_id))}>
                                    <Copy className="mr-2 size-4" /> Copy ID
                                </DropdownMenuItem>
                            )}
                            <DropdownMenuSeparator />
                            <DropdownMenuItem render={<AdminLink href={route("administrators.students.show", student.id)} />}>
                                <Eye className="mr-2 size-4" /> View Details
                            </DropdownMenuItem>
                            <DropdownMenuItem render={<AdminLink href={route("administrators.students.edit", student.id)} />}>
                                <UserCheck className="mr-2 size-4" /> Edit Profile
                            </DropdownMenuItem>
                            {student.filament?.view_url && (
                                <>
                                    <DropdownMenuSeparator />
                                    <DropdownMenuItem
                                        render={<a href={student.filament.view_url} target="_blank" rel="noreferrer" />}
                                        className="opacity-80"
                                    >
                                        <FileText className="mr-2 size-4" /> View in Filament
                                    </DropdownMenuItem>
                                </>
                            )}
                            <DropdownMenuSeparator />
                            {student.deleted_at ? (
                                <DropdownMenuItem onClick={triggerRestore}>
                                    <RotateCcw className="mr-2 size-4" /> Restore Student
                                </DropdownMenuItem>
                            ) : (
                                <DropdownMenuItem onClick={triggerSoftDelete} className="text-amber-600 focus:text-amber-600">
                                    <Trash2 className="mr-2 size-4" /> Move to Trash
                                </DropdownMenuItem>
                            )}
                            <DropdownMenuItem onClick={triggerForceDelete} className="text-destructive focus:text-destructive">
                                <Zap className="mr-2 size-4" /> Force Delete
                            </DropdownMenuItem>
                        </DropdownMenuContent>
                    </DropdownMenu>
                );
            },
        },
    ];
}

export const columns = createColumns();
