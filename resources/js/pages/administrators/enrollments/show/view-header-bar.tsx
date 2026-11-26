import {
    create as createEnrollment,
    edit as editEnrollment,
    index as enrollmentIndex,
} from "@/actions/App/Http/Controllers/AdministratorEnrollmentManagementController";
import { Badge, type BadgeProps } from "@/components/reui/badge";
import { Button, buttonVariants } from "@/components/ui/button";
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuGroup,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import { cn } from "@/lib/utils";
import { Link } from "@inertiajs/react";
import {
    ArrowLeft,
    ArrowLeftRight,
    Ban,
    ChevronLeft,
    ChevronRight,
    FileDown,
    FileText,
    GraduationCap,
    Layers,
    Mail,
    MoreVertical,
    PanelRight,
    Pencil,
    Plus,
    Printer,
    Receipt,
    RefreshCcw,
    RotateCcw,
    ShieldAlert,
    SlidersHorizontal,
    Trash2,
    Undo2,
} from "lucide-react";
import type { EnrollmentPeriodSummary } from "../types";
import type {
    DocumentLinks,
    EnrollmentData,
    EnrollmentViewLayoutMode,
    QueueNavigation,
    ShowEnrollmentPageProps,
} from "./types";

export type ViewDialogKind =
    | "verify_cashier"
    | "quick_enroll"
    | "cancel"
    | "reopen"
    | "restore_subjects"
    | "soft_delete"
    | "restore"
    | "force_delete"
    | "load_change"
    | "transfer"
    | "schedule_changes"
    | "undo_cashier"
    | "create_hold";

export type ViewSimpleActionKind =
    | "verify_head_dept"
    | "undo_head_dept"
    | "advance_pipeline"
    | "resend_assessment"
    | "create_assessment_pdf"
    | "retry_enrollment";

export interface ViewHeaderBarProps {
    enrollment: EnrollmentData;
    enrollmentPeriod: EnrollmentPeriodSummary | null | undefined;
    auth: ShowEnrollmentPageProps["auth"];
    documentLinks: DocumentLinks;
    queueNavigation: QueueNavigation;
    layoutMode: EnrollmentViewLayoutMode;
    onLayoutModeChange: (mode: EnrollmentViewLayoutMode) => void;
    onOpenDialog: (dialog: ViewDialogKind) => void;
    onNavigateQueue: (enrollmentId: number) => void;
    onSimpleAction: (action: ViewSimpleActionKind) => void;
    busyAction?: string | null;
}

function resolveStatusBadge(enrollment: EnrollmentData): {
    label: string;
    variant: NonNullable<BadgeProps["variant"]>;
} {
    if (enrollment.is_trashed) {
        return { label: "Trashed", variant: "destructive-light" };
    }

    if (enrollment.workflow.terminal_outcome === "cancelled") {
        return { label: "Cancelled", variant: "destructive-light" };
    }

    if (enrollment.workflow.is_completed) {
        return { label: enrollment.status || "Enrolled", variant: "success-light" };
    }

    const normalized = (enrollment.status ?? "").toLowerCase();
    if (/pending|intake|submitted/.test(normalized)) {
        return { label: enrollment.status, variant: "warning-light" };
    }
    if (/cashier|payment|billing/.test(normalized)) {
        return { label: enrollment.status, variant: "primary-light" };
    }
    if (/dept|head|academic|review|advis/.test(normalized)) {
        return { label: enrollment.status, variant: "info-light" };
    }

    return { label: enrollment.status || "Active", variant: "secondary" };
}

function resolvePeriodBadgeVariant(
    period: EnrollmentPeriodSummary | null | undefined,
): NonNullable<BadgeProps["variant"]> {
    if (!period) return "secondary";
    switch (period.tone) {
        case "success":
            return "success-light";
        case "warning":
            return "warning-light";
        case "destructive":
            return "destructive-light";
        case "info":
            return "info-light";
        default:
            return "secondary";
    }
}

export function ViewHeaderBar({
    enrollment,
    enrollmentPeriod,
    auth,
    documentLinks,
    queueNavigation,
    layoutMode,
    onLayoutModeChange,
    onOpenDialog,
    onNavigateQueue,
    onSimpleAction,
    busyAction,
}: ViewHeaderBarProps) {
    const backHref = queueNavigation.return_to ?? enrollmentIndex.url();
    const newEnrollmentHref = documentLinks.new_enrollment_url || createEnrollment.url();
    const editWorkbenchHref = editEnrollment.url(enrollment.id);

    const statusBadge = resolveStatusBadge(enrollment);
    const isTesda =
        enrollment.curriculum_kind === "tesda" || Boolean(enrollment.tesda_program_type);
    const canEditWorkbench =
        auth.can_manage_enrollment &&
        !enrollment.is_trashed &&
        !enrollment.workflow.is_terminal_locked;
    const isActiveRecord = !enrollment.is_trashed && !enrollment.workflow.is_terminal_locked;
    const canReopen =
        !enrollment.is_trashed &&
        auth.can_reopen &&
        (enrollment.workflow.is_terminal_locked ||
            enrollment.workflow.terminal_outcome === "cancelled" ||
            enrollment.workflow.available_reopen_steps.length > 0);
    const isBusy = Boolean(busyAction);

    const openExternal = (url: string | null | undefined) => {
        if (!url) return;
        window.open(url, "_blank", "noopener,noreferrer");
    };

    return (
        <div className="bg-card/80 flex flex-col gap-3 rounded-xl border px-3.5 py-3 shadow-xs backdrop-blur-xs lg:flex-row lg:items-center lg:justify-between">
            {/* Left identity & queue cluster */}
            <div className="flex min-w-0 items-start gap-2.5 sm:items-center">
                <div className="flex shrink-0 items-center gap-1">
                    <Link
                        href={backHref}
                        className={cn(
                            buttonVariants({ variant: "outline", size: "sm" }),
                            "h-8 gap-1.5 px-2.5",
                        )}
                    >
                        <ArrowLeft className="size-3.5" />
                        <span className="hidden sm:inline">Enrollments</span>
                    </Link>

                    <div
                        className="border-border inline-flex items-center overflow-hidden rounded-md border"
                        role="group"
                        aria-label="Queue navigation"
                    >
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            className="h-8 w-7 rounded-none border-r"
                            disabled={queueNavigation.prev_id === null}
                            aria-label="Previous enrollment"
                            title="Previous enrollment in queue"
                            onClick={() => {
                                if (queueNavigation.prev_id !== null) {
                                    onNavigateQueue(queueNavigation.prev_id);
                                }
                            }}
                        >
                            <ChevronLeft className="size-3.5" />
                        </Button>
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            className="h-8 w-7 rounded-none"
                            disabled={queueNavigation.next_id === null}
                            aria-label="Next enrollment"
                            title="Next enrollment in queue"
                            onClick={() => {
                                if (queueNavigation.next_id !== null) {
                                    onNavigateQueue(queueNavigation.next_id);
                                }
                            }}
                        >
                            <ChevronRight className="size-3.5" />
                        </Button>
                    </div>
                </div>

                <div className="min-w-0 flex-1 space-y-1">
                    <div className="flex min-w-0 flex-wrap items-center gap-x-2 gap-y-1">
                        <h1 className="text-foreground truncate text-base font-semibold tracking-tight sm:text-lg">
                            {enrollment.student.full_name}
                        </h1>
                        <span className="text-muted-foreground shrink-0 font-mono text-xs tabular-nums">
                            #{enrollment.id}
                        </span>
                        <Badge variant={statusBadge.variant} size="default">
                            {statusBadge.label}
                        </Badge>
                    </div>

                    <div className="text-muted-foreground flex flex-wrap items-center gap-x-2 gap-y-1 text-xs">
                        <span className="font-medium tabular-nums">
                            {enrollment.school_year} · {enrollment.semester_label}
                        </span>

                        {enrollmentPeriod && (
                            <Badge
                                variant={resolvePeriodBadgeVariant(enrollmentPeriod)}
                                size="sm"
                            >
                                {enrollmentPeriod.phase_label}
                            </Badge>
                        )}

                        {isTesda && (
                            <Badge variant="info-light" size="sm">
                                <GraduationCap className="size-3" />
                                TESDA
                                {enrollment.qualification_level
                                    ? ` · ${enrollment.qualification_level}`
                                    : ""}
                            </Badge>
                        )}

                        {enrollment.course_code && (
                            <span className="hidden truncate sm:inline">
                                {enrollment.course_code}
                                {enrollment.academic_year
                                    ? ` · Year ${enrollment.academic_year}`
                                    : ""}
                            </span>
                        )}
                    </div>
                </div>
            </div>

            {/* Right controls cluster */}
            <div className="flex shrink-0 flex-wrap items-center gap-2 lg:justify-end">
                {/* Layout Mode Segmented Switcher */}
                <div
                    role="group"
                    aria-label="View layout mode"
                    className="bg-muted/70 border-border inline-flex items-center rounded-lg border p-0.5"
                >
                    <button
                        type="button"
                        onClick={() => onLayoutModeChange("lifecycle")}
                        aria-pressed={layoutMode === "lifecycle"}
                        className={cn(
                            "inline-flex h-7 items-center gap-1.5 rounded-md px-2.5 text-xs font-medium transition-colors",
                            layoutMode === "lifecycle"
                                ? "bg-background text-foreground shadow-xs"
                                : "text-muted-foreground hover:text-foreground",
                        )}
                    >
                        <Layers className="size-3.5 shrink-0" />
                        <span>Lifecycle Hub</span>
                    </button>
                    <button
                        type="button"
                        onClick={() => onLayoutModeChange("command")}
                        aria-pressed={layoutMode === "command"}
                        className={cn(
                            "inline-flex h-7 items-center gap-1.5 rounded-md px-2.5 text-xs font-medium transition-colors",
                            layoutMode === "command"
                                ? "bg-background text-foreground shadow-xs"
                                : "text-muted-foreground hover:text-foreground",
                        )}
                    >
                        <PanelRight className="size-3.5 shrink-0" />
                        <span>Command Dossier</span>
                    </button>
                </div>

                <Link
                    href={newEnrollmentHref}
                    className={cn(
                        buttonVariants({ variant: "outline", size: "sm" }),
                        "h-8 gap-1.5 px-2.5",
                    )}
                >
                    <Plus className="size-3.5" />
                    <span>New Enrollment</span>
                </Link>

                {canEditWorkbench && (
                    <Link
                        href={editWorkbenchHref}
                        className={cn(
                            buttonVariants({ variant: "secondary", size: "sm" }),
                            "h-8 gap-1.5 px-2.5",
                        )}
                    >
                        <Pencil className="size-3.5" />
                        <span>Edit Workbench</span>
                    </Link>
                )}

                <DropdownMenu>
                    <DropdownMenuTrigger asChild>
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            className="h-8 gap-1 px-2.5"
                            disabled={isBusy}
                            aria-label="More enrollment actions"
                        >
                            <span>More</span>
                            <MoreVertical className="size-3.5" />
                        </Button>
                    </DropdownMenuTrigger>

                    <DropdownMenuContent align="end" className="w-60">
                        <DropdownMenuLabel>Documents &amp; Notices</DropdownMenuLabel>
                        <DropdownMenuGroup>
                            {enrollment.workflow.can_print_assessment && (
                                <DropdownMenuItem
                                    onSelect={() =>
                                        openExternal(documentLinks.assessment_preview_url)
                                    }
                                >
                                    <Printer className="mr-2 size-4" />
                                    Print Assessment Form
                                </DropdownMenuItem>
                            )}
                            <DropdownMenuItem
                                onSelect={() => onSimpleAction("create_assessment_pdf")}
                            >
                                <FileDown className="mr-2 size-4" />
                                Generate Assessment PDF
                            </DropdownMenuItem>
                            {enrollment.workflow.can_resend_assessment && (
                                <DropdownMenuItem
                                    onSelect={() => onSimpleAction("resend_assessment")}
                                >
                                    <Mail className="mr-2 size-4" />
                                    Resend Assessment Email
                                </DropdownMenuItem>
                            )}
                            {auth.can_export_documents && documentLinks.cor_pdf_url && (
                                <DropdownMenuItem
                                    onSelect={() => openExternal(documentLinks.cor_pdf_url)}
                                >
                                    <FileText className="mr-2 size-4" />
                                    Certificate of Registration (COR)
                                </DropdownMenuItem>
                            )}
                            {auth.can_export_documents && documentLinks.soa_url && (
                                <DropdownMenuItem
                                    onSelect={() => openExternal(documentLinks.soa_url)}
                                >
                                    <Receipt className="mr-2 size-4" />
                                    Statement of Account (SOA)
                                </DropdownMenuItem>
                            )}
                        </DropdownMenuGroup>

                        {isActiveRecord && auth.can_manage_enrollment && (
                            <>
                                <DropdownMenuSeparator />
                                <DropdownMenuLabel>Load &amp; Schedule</DropdownMenuLabel>
                                <DropdownMenuGroup>
                                    <DropdownMenuItem onSelect={() => onOpenDialog("load_change")}>
                                        <SlidersHorizontal className="mr-2 size-4" />
                                        Add / Drop / Swap (COL)
                                    </DropdownMenuItem>
                                    <DropdownMenuItem onSelect={() => onOpenDialog("transfer")}>
                                        <ArrowLeftRight className="mr-2 size-4" />
                                        Shift Program / Transfer Section
                                    </DropdownMenuItem>
                                    {enrollment.missing_classes.length > 0 && (
                                        <DropdownMenuItem
                                            onSelect={() => onSimpleAction("retry_enrollment")}
                                        >
                                            <RefreshCcw className="mr-2 size-4" />
                                            Retry Missing Class Assignments
                                        </DropdownMenuItem>
                                    )}
                                    <DropdownMenuItem
                                        onSelect={() => onOpenDialog("restore_subjects")}
                                    >
                                        <Undo2 className="mr-2 size-4" />
                                        Restore Deleted Subjects
                                    </DropdownMenuItem>
                                    {enrollment.pending_class_schedule_changes.length > 0 && (
                                        <DropdownMenuItem
                                            onSelect={() => onOpenDialog("schedule_changes")}
                                        >
                                            <Mail className="mr-2 size-4" />
                                            Notify Schedule Changes (
                                            {enrollment.pending_class_schedule_changes.length})
                                        </DropdownMenuItem>
                                    )}
                                </DropdownMenuGroup>
                            </>
                        )}

                        <DropdownMenuSeparator />
                        <DropdownMenuLabel>Workflow &amp; Lifecycle</DropdownMenuLabel>
                        <DropdownMenuGroup>
                            {enrollment.workflow_runtime === "legacy" &&
                                isActiveRecord &&
                                auth.can_verify_head &&
                                !enrollment.workflow.is_completed && (
                                    <DropdownMenuItem
                                        onSelect={() => onSimpleAction("undo_head_dept")}
                                    >
                                        <Undo2 className="mr-2 size-4" />
                                        Undo Dept Head Verification
                                    </DropdownMenuItem>
                                )}

                            {enrollment.workflow_runtime === "legacy" &&
                                isActiveRecord &&
                                enrollment.workflow.is_completed &&
                                (auth.can_verify_cashier || auth.can_reopen) && (
                                    <DropdownMenuItem
                                        onSelect={() => onOpenDialog("undo_cashier")}
                                    >
                                        <Undo2 className="mr-2 size-4" />
                                        Undo Cashier Verification
                                    </DropdownMenuItem>
                                )}

                            {auth.is_super_admin &&
                                isActiveRecord &&
                                !enrollment.workflow.is_completed && (
                                    <DropdownMenuItem
                                        onSelect={() => onOpenDialog("quick_enroll")}
                                    >
                                        <ShieldAlert className="mr-2 size-4 text-amber-600" />
                                        Quick Enroll (Super Admin)
                                    </DropdownMenuItem>
                                )}

                            {canReopen && (
                                <DropdownMenuItem onSelect={() => onOpenDialog("reopen")}>
                                    <RotateCcw className="mr-2 size-4" />
                                    {enrollment.workflow_runtime === "policy_v1"
                                        ? "Reopen Enrollment"
                                        : "Undo Cancellation"}
                                </DropdownMenuItem>
                            )}

                            {enrollment.workflow.can_cancel && !enrollment.is_trashed && (
                                <DropdownMenuItem
                                    onSelect={() => onOpenDialog("cancel")}
                                    className="text-destructive focus:text-destructive"
                                >
                                    <Ban className="mr-2 size-4" />
                                    Cancel Enrollment
                                </DropdownMenuItem>
                            )}
                        </DropdownMenuGroup>

                        {(auth.can_delete || auth.can_restore || auth.can_force_delete) && (
                            <>
                                <DropdownMenuSeparator />
                                <DropdownMenuGroup>
                                    {!enrollment.is_trashed && auth.can_delete && (
                                        <DropdownMenuItem
                                            onSelect={() => onOpenDialog("soft_delete")}
                                            className="text-destructive focus:text-destructive"
                                        >
                                            <Trash2 className="mr-2 size-4" />
                                            Move to Trash
                                        </DropdownMenuItem>
                                    )}
                                    {enrollment.is_trashed && auth.can_restore && (
                                        <DropdownMenuItem onSelect={() => onOpenDialog("restore")}>
                                            <RotateCcw className="mr-2 size-4 text-emerald-600" />
                                            Restore from Trash
                                        </DropdownMenuItem>
                                    )}
                                    {enrollment.is_trashed && auth.can_force_delete && (
                                        <DropdownMenuItem
                                            onSelect={() => onOpenDialog("force_delete")}
                                            className="text-destructive focus:text-destructive"
                                        >
                                            <Trash2 className="mr-2 size-4" />
                                            Permanently Delete
                                        </DropdownMenuItem>
                                    )}
                                </DropdownMenuGroup>
                            </>
                        )}
                    </DropdownMenuContent>
                </DropdownMenu>
            </div>
        </div>
    );
}
