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
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from "@/components/ui/tooltip";
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
    MoreHorizontal,
    RotateCcw,
    Trash2,
    UserCheck,
    XCircle,
    Zap,
} from "lucide-react";
import { useState } from "react";

declare let route: any;

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

const getInitials = (name: string) => {
    return name
        .split(" ")
        .map((n) => n[0])
        .join("")
        .toUpperCase()
        .slice(0, 2);
};

const getStatusColor = (status: string | null) => {
    switch (status?.toLowerCase()) {
        case "enrolled":
            return "bg-green-100 text-green-800 hover:bg-green-100/80 dark:bg-green-900/30 dark:text-green-400 border-transparent";
        case "graduated":
            return "bg-blue-100 text-blue-800 hover:bg-blue-100/80 dark:bg-blue-900/30 dark:text-blue-400 border-transparent";
        case "dropped":
        case "withdrawn":
            return "bg-red-100 text-red-800 hover:bg-red-100/80 dark:bg-red-900/30 dark:text-red-400 border-transparent";
        case "applicant":
            return "bg-yellow-100 text-yellow-800 hover:bg-yellow-100/80 dark:bg-yellow-900/30 dark:text-yellow-400 border-transparent";
        default:
            return "bg-gray-100 text-gray-800 hover:bg-gray-100/80 dark:bg-gray-800 dark:text-gray-400 border-transparent";
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

export const columns: ColumnDef<Student>[] = [
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
            />
        ),
        enableSorting: false,
        enableHiding: false,
    },
    {
        accessorKey: "student_id",
        header: ({ column }) => {
            return (
                <Button variant="ghost" onClick={() => column.toggleSorting(column.getIsSorted() === "asc")} className="-ml-4">
                    ID
                    <ArrowUpDown className="ml-2 h-4 w-4" />
                </Button>
            );
        },
        cell: ({ row }) => {
            const studentId = row.getValue("student_id") as string | number | null;
            const [copied, setCopied] = useState(false);

            if (!studentId) return <div className="font-mono text-xs">—</div>;

            const handleCopy = async () => {
                await navigator.clipboard.writeText(String(studentId));
                setCopied(true);
                setTimeout(() => setCopied(false), 1500);
            };

            return (
                <TooltipProvider delay={0}>
                    <Tooltip open={copied || undefined}>
                        <TooltipTrigger
                            render={<Button variant="ghost" size="sm" className="-ml-2 h-7 gap-1.5 px-2 font-mono text-xs" onClick={handleCopy} />}
                        >
                            {studentId}
                            {copied ? <Check className="h-3.5 w-3.5 text-green-500" /> : <Copy className="text-muted-foreground h-3.5 w-3.5" />}
                        </TooltipTrigger>
                        <TooltipContent side="top" sideOffset={4}>
                            {copied ? "Copied!" : "Copy ID"}
                        </TooltipContent>
                    </Tooltip>
                </TooltipProvider>
            );
        },
    },
    {
        accessorKey: "name",
        header: ({ column }) => {
            return (
                <Button variant="ghost" onClick={() => column.toggleSorting(column.getIsSorted() === "asc")} className="-ml-4">
                    Student
                    <ArrowUpDown className="ml-2 h-4 w-4" />
                </Button>
            );
        },
        cell: ({ row }) => {
            const student = row.original;
            return (
                <div className="flex items-center gap-3">
                    <Avatar className="h-8 w-8 border">
                        <AvatarImage src={student.avatar_url ?? undefined} alt={student.name} />
                        <AvatarFallback className="bg-primary/10 text-primary text-xs font-medium">{getInitials(student.name)}</AvatarFallback>
                    </Avatar>
                    <div className="flex flex-col">
                        <span className="text-sm font-medium">{student.name}</span>
                        {student.is_indigenous_person && <span className="text-muted-foreground text-[10px]">Indigenous Person</span>}
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
                <div className="flex max-w-[150px] flex-col">
                    <span className="text-xs font-medium">{course ?? "—"}</span>
                    <span className="text-muted-foreground truncate text-[10px]" title={title || ""}>
                        {title}
                    </span>
                </div>
            );
        },
    },
    {
        accessorKey: "status",
        header: "Status",
        cell: ({ row }) => {
            const status = row.getValue("status") as string;
            return <Badge className={`text-[10px] font-bold uppercase shadow-none ${getStatusColor(status)}`}>{status ?? "Unknown"}</Badge>;
        },
    },
    {
        accessorKey: "type",
        header: "Type",
        cell: ({ row }) => {
            return <div className="text-xs capitalize">{row.original.type?.replace(/_/g, " ")}</div>;
        },
    },
    {
        accessorKey: "previous_sem_clearance",
        header: "Clearance",
        cell: ({ row }) => {
            const status = row.original.previous_sem_clearance;
            return (
                <div className="flex items-center gap-2" title={status.replace("_", " ")}>
                    {status === "cleared" ? (
                        <CheckCircle className="h-4 w-4 text-green-500" />
                    ) : status === "not_cleared" ? (
                        <XCircle className="h-4 w-4 text-red-500" />
                    ) : (
                        <HelpCircle className="text-muted-foreground h-4 w-4" />
                    )}
                    <span className="hidden text-xs capitalize lg:inline-block">
                        {status === "cleared" ? "Cleared" : status === "not_cleared" ? "Pending" : "N/A"}
                    </span>
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
                <Badge variant="outline" className="max-w-[120px] truncate text-[10px] font-normal" title={scholarship}>
                    {scholarship}
                </Badge>
            );
        },
    },
    {
        accessorKey: "created_at",
        header: ({ column }) => (
            <Button variant="ghost" onClick={() => column.toggleSorting(column.getIsSorted() === "asc")} className="-ml-4">
                Added
                <ArrowUpDown className="ml-2 h-4 w-4" />
            </Button>
        ),
        cell: ({ row }) => {
            const createdAt = row.original.created_at;

            if (!createdAt) {
                return <span className="text-muted-foreground text-xs">Unknown</span>;
            }

            const date = new Date(createdAt);
            if (Number.isNaN(date.getTime())) {
                return <span className="text-muted-foreground text-xs">Unknown</span>;
            }

            return (
                <div className="min-w-[7rem] text-xs" title={date.toLocaleString()}>
                    <div className="text-foreground font-medium">{addedDateFormatter.format(date)}</div>
                    <div className="text-muted-foreground mt-0.5">{addedTimeFormatter.format(date)}</div>
                </div>
            );
        },
    },
    {
        id: "actions",
        cell: ({ row }) => {
            const student = row.original;

            return (
                <DropdownMenu>
                    <DropdownMenuTrigger render={<Button variant="ghost" className="h-8 w-8 p-0" />}>
                        <span className="sr-only">Open menu</span>
                        <MoreHorizontal className="h-4 w-4" />
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end">
                        <DropdownMenuLabel>Actions</DropdownMenuLabel>
                        <DropdownMenuItem onClick={() => navigator.clipboard.writeText(String(student.student_id))}>Copy Student ID</DropdownMenuItem>
                        <DropdownMenuSeparator />
                        <DropdownMenuItem render={<AdminLink href={route("administrators.students.show", student.id)} />}>
                            <Eye className="mr-2 h-4 w-4" /> View Details
                        </DropdownMenuItem>
                        <DropdownMenuItem render={<AdminLink href={route("administrators.students.edit", student.id)} />}>
                            <UserCheck className="mr-2 h-4 w-4" /> Edit Student
                        </DropdownMenuItem>
                        <DropdownMenuSeparator />
                        <DropdownMenuItem render={<a href={student.filament.view_url} target="_blank" rel="noreferrer" />} className="opacity-70">
                            <FileText className="mr-2 h-4 w-4" /> View in Filament
                        </DropdownMenuItem>
                        <DropdownMenuSeparator />
                        {student.deleted_at ? (
                            <DropdownMenuItem onClick={() => (window as any).dispatchEvent(new CustomEvent("students:restore", { detail: student }))}>
                                <RotateCcw className="mr-2 h-4 w-4" /> Restore
                            </DropdownMenuItem>
                        ) : (
                            <DropdownMenuItem
                                onClick={() => (window as any).dispatchEvent(new CustomEvent("students:soft-delete", { detail: student }))}
                                className="text-orange-600 focus:text-orange-600"
                            >
                                <Trash2 className="mr-2 h-4 w-4" /> Soft Delete
                            </DropdownMenuItem>
                        )}
                        <DropdownMenuItem
                            onClick={() => (window as any).dispatchEvent(new CustomEvent("students:force-delete", { detail: student }))}
                            className="text-destructive focus:text-destructive"
                        >
                            <Zap className="mr-2 h-4 w-4" /> Force Delete
                        </DropdownMenuItem>
                    </DropdownMenuContent>
                </DropdownMenu>
            );
        },
    },
];
