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
import { show as showStudent } from "@/routes/administrators/students";
import { ColumnDef } from "@tanstack/react-table";
import {
    ArrowUpDown,
    Check,
    CheckCircle,
    Clock,
    Copy,
    Eye,
    FileCheck2,
    FileWarning,
    KeyRound,
    MoreHorizontal,
    Pencil,
    RotateCcw,
    ShieldAlert,
    Trash2,
    XCircle,
} from "lucide-react";
import { useState } from "react";
import type { IdentifierPolicy, SchoolProfile } from "./shared-types";

declare let route: (name: string, params?: Record<string, unknown> | string | number) => string;

export type Student = {
    id: number;
    student_id: number | string | null;
    lrn?: string | null;
    name: string;
    email?: string | null;
    phone?: string | null;
    user_id?: number | null;
    portal_account?: {
        id: number;
        email: string;
        email_verified?: boolean;
        suspended?: boolean;
    } | null;
    avatar_url: string | null;
    course_id: number | null;
    department_id: number | null;
    course: string | null;
    course_title: string | null;
    shs_strand_id?: number | null;
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
    active_holds_count?: number;
    docs_summary?: {
        required_total: number;
        compliant_count: number;
        pending_review_count: number;
        rejected_count: number;
        missing_count: number;
        compliance_percent: number;
        is_complete: boolean;
    };
    created_at: string | null;
    deleted_at: string | null;
    filament?: {
        view_url: string;
        edit_url: string;
    };
};

export interface ColumnActionHandlers {
    returnUrl?: string;
    identifierPolicy?: IdentifierPolicy;
    schoolProfile?: SchoolProfile;
    typeLabels?: Record<string, string>;
    onInspect?: (student: Student, initialTab?: "overview" | "documents" | "holds" | "portal") => void;
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
        case "on_leave":
            return "bg-amber-100 text-amber-800 hover:bg-amber-100/80 dark:bg-amber-950/40 dark:text-amber-300 border-amber-200 dark:border-amber-800";
        case "applicant":
            return "bg-purple-100 text-purple-800 hover:bg-purple-100/80 dark:bg-purple-950/40 dark:text-purple-300 border-purple-200 dark:border-purple-800";
        default:
            return "bg-slate-100 text-slate-800 hover:bg-slate-100/80 dark:bg-slate-800 dark:text-slate-300 border-slate-200 dark:border-slate-700";
    }
};

function CopyableId({ value, lrn }: { value: string | number | null; lrn?: string | null }) {
    const [copied, setCopied] = useState(false);

    if (!value) return <span className="text-muted-foreground text-xs">—</span>;

    const handleCopy = (e: React.MouseEvent) => {
        e.stopPropagation();
        navigator.clipboard.writeText(String(value));
        setCopied(true);
        setTimeout(() => setCopied(false), 1800);
    };

    const showLrn = Boolean(lrn && String(lrn) !== String(value));

    return (
        <div className="space-y-0.5">
            <div
                onClick={handleCopy}
                className="group bg-muted/40 hover:bg-muted text-foreground/80 hover:text-foreground inline-flex cursor-pointer items-center gap-1.5 rounded-md px-2 py-1 font-mono text-xs font-medium tabular-nums transition-colors"
                title="Click to copy ID"
            >
                <span>{value}</span>
                {copied ? (
                    <Check className="h-3 w-3 text-emerald-500" />
                ) : (
                    <Copy className="h-3 w-3 opacity-0 transition-opacity group-hover:opacity-100" />
                )}
            </div>
            {showLrn && <p className="text-muted-foreground pl-1 font-mono text-[10px]">LRN: {lrn}</p>}
        </div>
    );
}

export function createColumns(handlers?: ColumnActionHandlers): ColumnDef<Student>[] {
    const idLabel = handlers?.identifierPolicy?.display_label || "ID";
    const placementLabel = handlers?.schoolProfile?.placement_label || "Program & Level";
    const isMultiDept = handlers?.schoolProfile?.is_multi_department ?? false;
    const isPhilippine = handlers?.schoolProfile?.philippine_reporting ?? false;

    const cols: ColumnDef<Student>[] = [
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
                <div onClick={(e) => e.stopPropagation()}>
                    <Checkbox
                        checked={row.getIsSelected()}
                        onCheckedChange={(value) => row.toggleSelected(!!value)}
                        aria-label="Select row"
                        className="translate-y-[2px]"
                    />
                </div>
            ),
            enableSorting: false,
            enableHiding: false,
        },
        {
            accessorKey: "student_id",
            header: ({ column }) => (
                <Button
                    variant="ghost"
                    size="sm"
                    className="-ml-3 h-8 text-xs font-medium"
                    onClick={() => column.toggleSorting(column.getIsSorted() === "asc")}
                >
                    {idLabel}
                    <ArrowUpDown className="ml-1.5 h-3 w-3" />
                </Button>
            ),
            cell: ({ row }) => <CopyableId value={row.getValue("student_id")} lrn={row.original.lrn} />,
        },
        {
            accessorKey: "name",
            header: ({ column }) => (
                <Button
                    variant="ghost"
                    size="sm"
                    className="-ml-3 h-8 text-xs font-medium"
                    onClick={() => column.toggleSorting(column.getIsSorted() === "asc")}
                >
                    Student
                    <ArrowUpDown className="ml-1.5 h-3 w-3" />
                </Button>
            ),
            cell: ({ row }) => {
                const student = row.original;
                return (
                    <div className="flex items-center gap-3 py-0.5">
                        <Avatar className="border-border/60 h-9 w-9 border">
                            <AvatarImage src={student.avatar_url ?? undefined} alt={student.name} className="object-cover" />
                            <AvatarFallback className="bg-primary/10 text-primary text-xs font-semibold">
                                {getInitials(student.name)}
                            </AvatarFallback>
                        </Avatar>
                        <div className="flex min-w-0 flex-col">
                            <AdminLink
                                href={showStudent.url(
                                    student.id,
                                    handlers?.returnUrl ? { query: { return: handlers.returnUrl } } : undefined,
                                )}
                                onClick={(e) => e.stopPropagation()}
                                className="hover:text-primary truncate font-medium transition-colors"
                            >
                                {student.name}
                            </AdminLink>
                            <div className="text-muted-foreground flex flex-wrap items-center gap-1.5 text-[11px]">
                                <span className="truncate">{student.email || student.phone || "No contact info"}</span>
                                {isPhilippine && student.is_indigenous_person && (
                                    <span className="font-medium text-amber-600 dark:text-amber-400">· IP</span>
                                )}
                            </div>
                        </div>
                    </div>
                );
            },
        },
        {
            accessorKey: "course",
            header: placementLabel,
            cell: ({ row }) => {
                const student = row.original;
                return (
                    <div className="flex flex-col gap-0.5">
                        <div className="flex items-center gap-1.5">
                            <span className="text-foreground text-xs font-semibold">{student.course || "Unassigned"}</span>
                            <Badge variant="secondary" className="h-4 px-1.5 text-[10px] font-medium">
                                {student.academic_year}
                            </Badge>
                        </div>
                        {student.course_title && (
                            <span className="text-muted-foreground max-w-[200px] truncate text-[11px]" title={student.course_title}>
                                {student.course_title}
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
                    <Badge variant="outline" className={`font-medium capitalize ${getStatusColor(status)}`}>
                        {(status ?? "unknown").replace("_", " ")}
                    </Badge>
                );
            },
        },
        {
            id: "documents",
            header: "Documents",
            cell: ({ row }) => {
                const student = row.original;
                const docs = student.docs_summary;
                if (!docs || docs.required_total === 0) {
                    return <span className="text-muted-foreground text-xs">—</span>;
                }

                const hasPending = docs.pending_review_count > 0;
                const hasRejected = docs.rejected_count > 0;

                return (
                    <button
                        type="button"
                        onClick={(e) => {
                            e.stopPropagation();
                            handlers?.onInspect?.(student, "documents");
                        }}
                        className="inline-flex items-center gap-1.5 text-left"
                        title="Open document checklist in inspector"
                    >
                        <Badge
                            variant="outline"
                            className={`gap-1 font-mono text-[11px] tabular-nums transition-colors ${
                                docs.is_complete
                                    ? "border-emerald-500/35 bg-emerald-500/10 text-emerald-700 hover:bg-emerald-500/20 dark:text-emerald-300"
                                    : hasRejected
                                      ? "border-rose-500/35 bg-rose-500/10 text-rose-700 hover:bg-rose-500/20 dark:text-rose-300"
                                      : hasPending
                                        ? "border-amber-500/35 bg-amber-500/10 text-amber-700 hover:bg-amber-500/20 dark:text-amber-300"
                                        : "border-border bg-muted/60 text-muted-foreground hover:bg-muted"
                            }`}
                        >
                            {docs.is_complete ? (
                                <FileCheck2 className="size-3" />
                            ) : hasPending ? (
                                <Clock className="size-3" />
                            ) : (
                                <FileWarning className="size-3" />
                            )}
                            {docs.compliant_count}/{docs.required_total}
                        </Badge>
                    </button>
                );
            },
        },
        {
            id: "holds_clearance",
            header: "Holds & Clearance",
            cell: ({ row }) => {
                const student = row.original;
                const holds = student.active_holds_count ?? 0;
                const clearance = student.previous_sem_clearance;

                return (
                    <div className="flex flex-wrap items-center gap-1">
                        {holds > 0 && (
                            <Badge
                                variant="outline"
                                className="border-rose-500/35 bg-rose-500/10 text-rose-700 dark:text-rose-300 gap-1 text-[10px]"
                            >
                                <ShieldAlert className="size-3" />
                                {holds} {holds === 1 ? "Hold" : "Holds"}
                            </Badge>
                        )}
                        {clearance === "cleared" ? (
                            <Badge
                                variant="outline"
                                className="border-emerald-500/30 bg-emerald-500/10 text-emerald-700 dark:text-emerald-300 gap-1 text-[10px]"
                            >
                                <CheckCircle className="size-2.5" />
                                Cleared
                            </Badge>
                        ) : clearance === "not_cleared" ? (
                            <Badge
                                variant="outline"
                                className="border-amber-500/35 bg-amber-500/10 text-amber-700 dark:text-amber-300 gap-1 text-[10px]"
                            >
                                <XCircle className="size-2.5" />
                                Pending
                            </Badge>
                        ) : holds === 0 ? (
                            <span className="text-muted-foreground text-xs">—</span>
                        ) : null}
                    </div>
                );
            },
        },
        {
            id: "portal",
            header: "Portal",
            cell: ({ row }) => {
                const student = row.original;
                const portal = student.portal_account;
                if (!portal) {
                    return (
                        <button
                            type="button"
                            onClick={(e) => {
                                e.stopPropagation();
                                handlers?.onInspect?.(student, "portal");
                            }}
                            className="text-muted-foreground hover:text-foreground inline-flex items-center gap-1 text-[11px]"
                        >
                            <KeyRound className="size-3 opacity-60" />
                            Unlinked
                        </button>
                    );
                }

                return (
                    <Badge
                        variant="outline"
                        className={`text-[10px] ${
                            portal.suspended
                                ? "border-rose-500/35 bg-rose-500/10 text-rose-700 dark:text-rose-300"
                                : "border-emerald-500/30 bg-emerald-500/10 text-emerald-700 dark:text-emerald-300"
                        }`}
                    >
                        {portal.suspended ? "Suspended" : "Linked"}
                    </Badge>
                );
            },
        },
    ];

    if (isMultiDept) {
        cols.splice(4, 0, {
            accessorKey: "type",
            header: "Category",
            cell: ({ row }) => {
                const val = row.getValue("type") as string | null;
                const label = (val && handlers?.typeLabels?.[val]) || val || "—";
                return (
                    <Badge variant="secondary" className="text-[10px] font-medium">
                        {label}
                    </Badge>
                );
            },
        });
    }

    cols.push({
        id: "actions",
        enableHiding: false,
        cell: ({ row }) => {
            const student = row.original;
            const isTrashed = Boolean(student.deleted_at);

            return (
                <div className="flex items-center justify-end gap-1" onClick={(e) => e.stopPropagation()}>
                    {handlers?.onInspect && (
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            className="text-muted-foreground hover:text-foreground h-8 w-8"
                            title="Quick inspect"
                            aria-label={`Quick inspect ${student.name}`}
                            onClick={() => handlers.onInspect?.(student, "overview")}
                        >
                            <Eye className="h-4 w-4" />
                        </Button>
                    )}
                    <DropdownMenu>
                        <DropdownMenuTrigger
                            render={
                                <Button variant="ghost" className="text-muted-foreground hover:text-foreground h-8 w-8 p-0">
                                    <span className="sr-only">Open menu</span>
                                    <MoreHorizontal className="h-4 w-4" />
                                </Button>
                            }
                        />
                        <DropdownMenuContent align="end" className="w-52">
                            <DropdownMenuLabel>Actions</DropdownMenuLabel>
                            {handlers?.onInspect && (
                                <DropdownMenuItem onClick={() => handlers.onInspect?.(student, "overview")}>
                                    <Eye className="mr-2 h-4 w-4" />
                                    Quick Inspect
                                </DropdownMenuItem>
                            )}
                            <DropdownMenuItem
                                onClick={() => navigator.clipboard.writeText(String(student.student_id ?? student.id))}
                            >
                                <Copy className="mr-2 h-4 w-4" />
                                Copy {idLabel}
                            </DropdownMenuItem>
                            <DropdownMenuSeparator />
                            <DropdownMenuItem
                                render={
                                    <AdminLink
                                        href={showStudent.url(
                                            student.id,
                                            handlers?.returnUrl ? { query: { return: handlers.returnUrl } } : undefined,
                                        )}
                                    />
                                }
                            >
                                Full Profile
                            </DropdownMenuItem>
                            <DropdownMenuItem render={<AdminLink href={route("administrators.students.edit", student.id)} />}>
                                <Pencil className="mr-2 h-4 w-4" />
                                Edit Student
                            </DropdownMenuItem>
                            <DropdownMenuItem
                                render={<AdminLink href={route("administrators.students.documents.index", student.id)} />}
                            >
                                <FileCheck2 className="mr-2 h-4 w-4" />
                                Manage Documents
                            </DropdownMenuItem>
                            <DropdownMenuSeparator />
                            {isTrashed ? (
                                <>
                                    <DropdownMenuItem
                                        onClick={() => handlers?.onRestore?.(student)}
                                        className="text-emerald-600 focus:text-emerald-600 dark:text-emerald-400"
                                    >
                                        <RotateCcw className="mr-2 h-4 w-4" />
                                        Restore Student
                                    </DropdownMenuItem>
                                    <DropdownMenuItem
                                        onClick={() => handlers?.onForceDelete?.(student)}
                                        className="text-destructive focus:text-destructive"
                                    >
                                        <Trash2 className="mr-2 h-4 w-4" />
                                        Delete Permanently
                                    </DropdownMenuItem>
                                </>
                            ) : (
                                <>
                                    <DropdownMenuItem
                                        onClick={() => handlers?.onSoftDelete?.(student)}
                                        className="text-amber-600 focus:text-amber-600 dark:text-amber-400"
                                    >
                                        <Trash2 className="mr-2 h-4 w-4" />
                                        Move to Trash
                                    </DropdownMenuItem>
                                    <DropdownMenuItem
                                        onClick={() => handlers?.onForceDelete?.(student)}
                                        className="text-destructive focus:text-destructive"
                                    >
                                        <Trash2 className="mr-2 h-4 w-4" />
                                        Delete Permanently
                                    </DropdownMenuItem>
                                </>
                            )}
                        </DropdownMenuContent>
                    </DropdownMenu>
                </div>
            );
        },
    });

    return cols;
}
