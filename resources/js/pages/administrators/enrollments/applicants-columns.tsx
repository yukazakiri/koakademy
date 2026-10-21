import { Avatar, AvatarFallback } from "@/components/ui/avatar";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import { Progress } from "@/components/ui/progress";
import { cn } from "@/lib/utils";
import { Link } from "@inertiajs/react";
import type { ColumnDef } from "@tanstack/react-table";
import {
    AlertTriangle,
    ArrowUpDown,
    Award,
    CircleSlash2,
    Copy,
    Eye,
    FileSearch,
    Loader2,
    Mail,
    MailCheck,
    MoreHorizontal,
    Pencil,
    Trash2,
    UserPlus,
} from "lucide-react";
import { route } from "ziggy-js";
import {
    STAGE_BADGE_CLASSES,
    STAGE_FALLBACK_LABELS,
    avatarToneClass,
    formatDate,
    formatRelativeTime,
    getInitials,
    stageLabel,
    studentTypeAbbreviation,
} from "./applicants-utils";
import type { ApplicantRow, ApplicantStage } from "./types";

export type ApplicantActions = {
    onOpenReview: (applicant: ApplicantRow) => void;
    onManageScholarship: (applicant: ApplicantRow) => void;
    onNotifyApproval: (applicant: ApplicantRow) => void;
    onCopy: (value: string, label: string) => void;
    notifyingApplicantId?: number | null;
    onCancel: (applicant: ApplicantRow) => void;
    onDelete: (applicant: ApplicantRow) => void;
    onForceDelete: (applicant: ApplicantRow) => void;
};

type ColumnOptions = {
    actions: ApplicantActions;
    stageMeta?: Record<string, { label: string }>;
    pipelineStatusLabels?: Record<string, string>;
};

function SortableHeader({
    column,
    label,
    className,
}: {
    column: { toggleSorting: (desc?: boolean) => void; getIsSorted: () => false | "asc" | "desc" };
    label: string;
    className?: string;
}) {
    const sorted = column.getIsSorted();

    return (
        <Button
            variant="ghost"
            size="sm"
            onClick={() => column.toggleSorting(sorted === "asc")}
            className={cn("-ml-3 h-8 gap-1.5 px-3 text-[11px] font-semibold tracking-[0.1em] uppercase", className)}
        >
            {label}
            <ArrowUpDown className={cn("size-3.5", sorted ? "opacity-100" : "opacity-40")} aria-hidden="true" />
        </Button>
    );
}

export const createApplicantColumns = ({ actions, stageMeta, pipelineStatusLabels }: ColumnOptions): ColumnDef<ApplicantRow>[] => [
    {
        accessorKey: "name",
        header: ({ column }) => <SortableHeader column={column} label="Applicant" />,
        cell: ({ row }) => {
            const applicant = row.original;

            return (
                <div className="flex min-w-0 items-center gap-3">
                    <Avatar className="size-9 shrink-0 border">
                        <AvatarFallback className={avatarToneClass(applicant.is_trashed)}>{getInitials(applicant.name)}</AvatarFallback>
                    </Avatar>
                    <div className="min-w-0">
                        <div className="flex items-center gap-1.5">
                            <p
                                className={cn(
                                    "truncate text-sm font-semibold",
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
                        <p className="text-muted-foreground truncate text-xs">
                            {applicant.student_id ? `ID ${applicant.student_id}` : "ID pending"}
                            {applicant.email ? ` · ${applicant.email}` : " · No email on file"}
                        </p>
                    </div>
                </div>
            );
        },
    },
    {
        accessorKey: "course",
        header: ({ column }) => <SortableHeader column={column} label="Program" />,
        cell: ({ row }) => {
            const applicant = row.original;
            const abbreviation = studentTypeAbbreviation(applicant.student_type);

            return (
                <div className="min-w-0 space-y-1">
                    <div className="flex items-center gap-1.5">
                        <span className="truncate text-sm font-medium">{applicant.course ?? "—"}</span>
                        {abbreviation && (
                            <Badge variant="outline" className="shrink-0 px-1.5 py-0 text-[10px] font-medium">
                                {abbreviation}
                            </Badge>
                        )}
                    </div>
                    <p className="text-muted-foreground truncate text-xs">
                        {applicant.department ? `${applicant.department} · ${applicant.course_title ?? "Program"}` : (applicant.course_title ?? "—")}
                    </p>
                </div>
            );
        },
    },
    {
        id: "stage",
        accessorFn: (row: ApplicantRow) => row.stage,
        header: "Stage",
        enableSorting: false,
        cell: ({ row }) => {
            const applicant = row.original;
            const stage = applicant.stage as ApplicantStage;
            const pipelineLabel = applicant.pipeline_status ? (pipelineStatusLabels?.[applicant.pipeline_status] ?? applicant.pipeline_status) : null;

            return (
                <div className="space-y-1">
                    <Badge variant="outline" className={cn("rounded-full px-2 py-0.5 font-medium", STAGE_BADGE_CLASSES[stage])}>
                        {stageLabel(stage, stageMeta)}
                    </Badge>
                    {pipelineLabel && <p className="text-muted-foreground truncate text-[11px]">Pipeline: {pipelineLabel}</p>}
                </div>
            );
        },
    },
    {
        id: "requirements",
        accessorFn: (row: ApplicantRow) => row.requirements_submitted,
        header: "Documents",
        enableSorting: false,
        cell: ({ row }) => {
            const applicant = row.original;

            if (applicant.requirements_total === 0) {
                return <span className="text-muted-foreground text-xs">No checklist tracked</span>;
            }

            const submitted = applicant.requirements_submitted;
            const total = applicant.requirements_total;
            const complete = submitted === total;

            return (
                <div className="w-36 space-y-1.5">
                    <div className="flex items-center justify-between gap-2 text-[11px]">
                        <span
                            className={cn("font-medium", complete ? "text-emerald-600 dark:text-emerald-400" : "text-amber-600 dark:text-amber-400")}
                        >
                            {submitted}/{total} uploaded
                        </span>
                        {applicant.requirements_reviewed > 0 && (
                            <span className="text-muted-foreground">{applicant.requirements_reviewed} checked</span>
                        )}
                    </div>
                    <Progress
                        value={(submitted / total) * 100}
                        className={cn(
                            "h-1.5",
                            complete ? "[&>[data-slot=progress-indicator]]:bg-emerald-500" : "[&>[data-slot=progress-indicator]]:bg-amber-500",
                        )}
                    />
                </div>
            );
        },
    },
    {
        accessorKey: "scholarship_type",
        header: ({ column }) => <SortableHeader column={column} label="Scholarship" />,
        cell: ({ row }) => {
            const applicant = row.original;

            if (!applicant.scholarship_label) {
                return <span className="text-muted-foreground text-sm">—</span>;
            }

            return (
                <Badge variant="secondary" className="rounded-full px-2 font-normal">
                    {applicant.scholarship_label}
                </Badge>
            );
        },
    },
    {
        accessorKey: "created_at",
        header: ({ column }) => <SortableHeader column={column} label="Applied" />,
        cell: ({ row }) => {
            const applicant = row.original;

            return (
                <div className="space-y-0.5">
                    <p className="text-sm whitespace-nowrap">{formatRelativeTime(applicant.created_at)}</p>
                    <p className="text-muted-foreground text-[11px] whitespace-nowrap">{formatDate(applicant.created_at)}</p>
                </div>
            );
        },
    },
    {
        id: "actions",
        enableSorting: false,
        enableHiding: false,
        header: () => <span className="sr-only">Actions</span>,
        cell: ({ row }) => {
            const applicant = row.original;
            const isNotifying = actions.notifyingApplicantId === applicant.id;

            return (
                <div className="flex items-center justify-end gap-1.5">
                    {!applicant.is_trashed && (
                        <Button
                            size="sm"
                            variant="outline"
                            className="h-8 gap-1.5 px-2.5 text-xs"
                            onClick={(event) => {
                                event.stopPropagation();
                                actions.onOpenReview(applicant);
                            }}
                        >
                            <FileSearch className="size-3.5" aria-hidden="true" />
                            Review
                        </Button>
                    )}
                    <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                            <Button variant="ghost" className="size-8 p-0" onClick={(event) => event.stopPropagation()}>
                                <span className="sr-only">Open menu</span>
                                <MoreHorizontal className="size-4" />
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end" className="w-56">
                            <DropdownMenuLabel>Applicant actions</DropdownMenuLabel>

                            {!applicant.is_trashed && (
                                <DropdownMenuItem
                                    onClick={(event) => {
                                        event.stopPropagation();
                                        actions.onOpenReview(applicant);
                                    }}
                                >
                                    <FileSearch className="mr-2 size-4" />
                                    Open review
                                </DropdownMenuItem>
                            )}

                            {applicant.email && (
                                <DropdownMenuItem
                                    onClick={(event) => {
                                        event.stopPropagation();
                                        actions.onCopy(applicant.email ?? "", "Email address");
                                    }}
                                >
                                    <Mail className="mr-2 size-4" />
                                    Copy email
                                </DropdownMenuItem>
                            )}

                            {applicant.student_id && (
                                <DropdownMenuItem
                                    onClick={(event) => {
                                        event.stopPropagation();
                                        actions.onCopy(String(applicant.student_id), "Student ID");
                                    }}
                                >
                                    <Copy className="mr-2 size-4" />
                                    Copy student ID
                                </DropdownMenuItem>
                            )}

                            <DropdownMenuSeparator />

                            {!applicant.is_trashed && (
                                <DropdownMenuItem asChild>
                                    <Link
                                        href={route("administrators.enrollments.create", { student_id: applicant.id })}
                                        className="flex w-full cursor-pointer items-center"
                                    >
                                        <UserPlus className="mr-2 size-4" />
                                        Process enrollment
                                    </Link>
                                </DropdownMenuItem>
                            )}

                            <DropdownMenuItem asChild>
                                <Link href={route("administrators.students.show", applicant.id)} className="flex w-full cursor-pointer items-center">
                                    <Eye className="mr-2 size-4" />
                                    View student record
                                </Link>
                            </DropdownMenuItem>

                            <DropdownMenuItem asChild>
                                <Link href={route("administrators.students.edit", applicant.id)} className="flex w-full cursor-pointer items-center">
                                    <Pencil className="mr-2 size-4" />
                                    Edit record
                                </Link>
                            </DropdownMenuItem>

                            {!applicant.is_trashed && (
                                <>
                                    <DropdownMenuSeparator />
                                    <DropdownMenuItem
                                        disabled={isNotifying}
                                        onClick={(event) => {
                                            event.stopPropagation();
                                            actions.onNotifyApproval(applicant);
                                        }}
                                    >
                                        {isNotifying ? <Loader2 className="mr-2 size-4 animate-spin" /> : <MailCheck className="mr-2 size-4" />}
                                        Notify approval
                                    </DropdownMenuItem>
                                    <DropdownMenuItem
                                        onClick={(event) => {
                                            event.stopPropagation();
                                            actions.onManageScholarship(applicant);
                                        }}
                                    >
                                        <Award className="mr-2 size-4" />
                                        Manage scholarship
                                    </DropdownMenuItem>
                                    <DropdownMenuItem
                                        onClick={(event) => {
                                            event.stopPropagation();
                                            actions.onCancel(applicant);
                                        }}
                                        className="text-amber-700 focus:text-amber-700 dark:text-amber-400 dark:focus:text-amber-300"
                                    >
                                        <CircleSlash2 className="mr-2 size-4" />
                                        Close application
                                    </DropdownMenuItem>
                                </>
                            )}

                            <DropdownMenuSeparator />

                            {applicant.is_trashed ? (
                                <DropdownMenuItem
                                    onClick={(event) => {
                                        event.stopPropagation();
                                        actions.onForceDelete(applicant);
                                    }}
                                    className="text-red-600 focus:text-red-600"
                                >
                                    <AlertTriangle className="mr-2 size-4" />
                                    Permanently delete
                                </DropdownMenuItem>
                            ) : (
                                <>
                                    <DropdownMenuItem
                                        onClick={(event) => {
                                            event.stopPropagation();
                                            actions.onDelete(applicant);
                                        }}
                                        className="text-red-600 focus:text-red-600"
                                    >
                                        <Trash2 className="mr-2 size-4" />
                                        Delete applicant
                                    </DropdownMenuItem>
                                    <DropdownMenuItem
                                        onClick={(event) => {
                                            event.stopPropagation();
                                            actions.onForceDelete(applicant);
                                        }}
                                        className="text-red-600 focus:text-red-600"
                                    >
                                        <AlertTriangle className="mr-2 size-4" />
                                        Permanently delete
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

export const APPLICANT_STAGE_FALLBACK_LABELS = STAGE_FALLBACK_LABELS;
