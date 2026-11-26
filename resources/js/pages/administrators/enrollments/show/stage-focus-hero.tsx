import { edit as editEnrollment } from "@/actions/App/Http/Controllers/AdministratorEnrollmentManagementController";
import { Badge } from "@/components/reui/badge";
import {
    Frame,
    FrameDescription,
    FrameHeader,
    FramePanel,
    FrameTitle,
} from "@/components/reui/frame";
import { IconTile } from "@/components/reui/icon-tile";
import {
    Stepper,
    StepperIndicator,
    StepperItem,
    StepperNav,
    StepperSeparator,
    StepperTitle,
    StepperTrigger,
} from "@/components/reui/stepper";
import { Button, buttonVariants } from "@/components/ui/button";
import { cn } from "@/lib/utils";
import { Link } from "@inertiajs/react";
import {
    ArrowLeftRight,
    ArrowRight,
    Ban,
    Banknote,
    BookOpen,
    Check,
    CheckCircle2,
    ClipboardCheck,
    CreditCard,
    FileCheck2,
    FileText,
    Mail,
    Pencil,
    Printer,
    Receipt,
    RefreshCw,
    RotateCcw,
    SlidersHorizontal,
    Trash2,
    UserCheck,
    XCircle,
} from "lucide-react";
import { formatMoney, resolvePipelineColor } from "../enrollment-utils";
import { VerifyCashierDialog } from "./enrollment-action-dialogs";
import type { DocumentLinks, EnrollmentData, ShowEnrollmentPageProps } from "./types";
import type { ViewDialogKind, ViewSimpleActionKind } from "./view-header-bar";

export interface StageFocusHeroProps {
    enrollment: EnrollmentData;
    enrollmentPipeline: ShowEnrollmentPageProps["enrollment_pipeline"];
    documentLinks: DocumentLinks;
    auth: ShowEnrollmentPageProps["auth"];
    currency: string;
    scheduleConflictsCount: number;
    busyAction?: string | null;
    reviewingRequirementId?: number | null;
    onPolicyTransition: (
        transition: EnrollmentData["workflow"]["allowed_transitions"][number],
    ) => void;
    onSimpleAction: (action: ViewSimpleActionKind) => void;
    onOpenDialog: (dialog: ViewDialogKind) => void;
    onReviewRequirement: (requirementId: number, action: "verify" | "waive") => void;
    onJumpToTab: (tab: string) => void;
}

type ResolvedStepperNode = {
    key: string;
    label: string;
    color: string | undefined;
    isCompleted: boolean;
    isCurrent: boolean;
};

export function StageFocusHero({
    enrollment,
    enrollmentPipeline,
    documentLinks,
    auth,
    currency,
    scheduleConflictsCount,
    busyAction,
    reviewingRequirementId,
    onPolicyTransition,
    onSimpleAction,
    onOpenDialog,
    onReviewRequirement,
    onJumpToTab,
}: StageFocusHeroProps) {
    const pipelineSteps = enrollmentPipeline.steps ?? [];
    const nextStep = enrollmentPipeline.next_step ?? null;
    const completionStep =
        pipelineSteps.find((step) => step.is_completion) ??
        pipelineSteps[pipelineSteps.length - 1];
    const completionStatus =
        completionStep?.status ?? enrollmentPipeline.cashier_verified_status;

    const isTrashedOrCancelled =
        enrollment.is_trashed ||
        enrollment.workflow.terminal_outcome === "cancelled" ||
        enrollment.workflow.is_terminal_locked;

    const isCompleted =
        !isTrashedOrCancelled &&
        (enrollment.workflow.is_completed || enrollment.status === completionStatus);

    const paymentTransition =
        enrollment.workflow_runtime === "policy_v1"
            ? (enrollment.workflow.allowed_transitions.find((transition) =>
                  transition.target_actions.includes("enrollment.verify_payment"),
              ) ?? null)
            : null;

    const isPendingCashier =
        !isTrashedOrCancelled &&
        !isCompleted &&
        (Boolean(paymentTransition) ||
            (enrollment.workflow_runtime === "legacy" &&
                nextStep?.action_type === "cashier_verification") ||
            /cashier|payment|billing/i.test(enrollment.status ?? ""));

    // Build stepper nodes
    const stepperNodes: ResolvedStepperNode[] = (() => {
        if (enrollment.workflow_runtime === "policy_v1" && enrollment.workflow.steps.length > 0) {
            const currentIdx = enrollment.workflow.steps.findIndex(
                (step) => step.key === enrollment.workflow.current_step_key,
            );
            return enrollment.workflow.steps.map((step, index) => {
                const matchedPipelineStep = pipelineSteps.find(
                    (item) => item.key === step.key || item.label === step.label,
                );
                const stepCompleted =
                    isCompleted ||
                    (currentIdx !== -1 && index < currentIdx && !isTrashedOrCancelled);
                const stepCurrent = !isCompleted && !isTrashedOrCancelled && index === currentIdx;
                return {
                    key: step.key,
                    label: step.label,
                    color: matchedPipelineStep?.color ?? (step.terminal ? "green" : "blue"),
                    isCompleted: stepCompleted,
                    isCurrent: stepCurrent,
                };
            });
        }

        const currentIdx = pipelineSteps.findIndex((step) => step.status === enrollment.status);
        const submittedNode: ResolvedStepperNode = {
            key: "__submitted__",
            label: enrollmentPipeline.submitted_label || "Submitted",
            color: "green",
            isCompleted: true,
            isCurrent: false,
        };
        const mapped = pipelineSteps.map((step, index) => ({
            key: step.key || step.status,
            label: step.label || step.status,
            color: step.color,
            isCompleted:
                isCompleted || (currentIdx !== -1 && index < currentIdx && !isTrashedOrCancelled),
            isCurrent: !isCompleted && !isTrashedOrCancelled && index === currentIdx,
        }));

        return [submittedNode, ...mapped];
    })();

    const activeStepperIndex = (() => {
        if (isCompleted) return stepperNodes.length + 1;
        const idx = stepperNodes.findIndex((node) => node.isCurrent);
        return idx === -1 ? 1 : idx + 1;
    })();

    // Metrics
    const totalUnits = enrollment.subjects_enrolled.reduce(
        (sum, subject) => sum + Number(subject.units ?? 0),
        0,
    );
    const totalAssessment = Number(enrollment.tuition?.overall_tuition ?? 0);
    const totalPaid = Number(enrollment.tuition?.total_paid ?? 0);
    const balanceDue = Number(
        enrollment.tuition?.balance_due ?? enrollment.tuition?.total_balance ?? 0,
    );
    const requiredDownpayment = Number(
        enrollment.tuition?.required_downpayment ?? enrollment.tuition?.downpayment ?? 0,
    );
    const discountPercent = Number(enrollment.tuition?.discount ?? 0);
    const pendingRequirements = enrollment.requirements.filter(
        (requirement) => requirement.status === "pending",
    );
    const activeHoldsCount = enrollment.holds.filter((hold) => hold.status === "active").length;
    const prereqIssuesCount = enrollment.subjects_enrolled.filter(
        (subject) => !subject.prerequisite_satisfied,
    ).length;
    const retakesCount =
        enrollment.checklist_summary?.failed_retakes_count ??
        enrollment.subjects_enrolled.filter((subject) => subject.is_failed_retake).length;
    const checklistProgress = enrollment.checklist_summary?.progress_percentage ?? null;

    const latestOutcomeNote =
        enrollment.timeline.find(
            (event) => event.type === "deleted" || event.tone === "destructive",
        )?.description ??
        enrollment.remarks ??
        null;

    const openExternal = (url: string | null | undefined) => {
        if (!url) return;
        window.open(url, "_blank", "noopener,noreferrer");
    };

    return (
        <div className="min-w-0 space-y-3">
            {/* Horizontal ReUI Stepper Pipeline Strip */}
            {stepperNodes.length > 0 && (
                <Frame spacing="xs" className="min-w-0">
                    <FramePanel className="overflow-x-auto py-2.5">
                        <div className="flex min-w-max items-center justify-between gap-4 px-1">
                            <Stepper
                                value={activeStepperIndex}
                                orientation="horizontal"
                                className="min-w-0 flex-1"
                            >
                                <StepperNav className="items-center gap-1">
                                    {stepperNodes.map((node, index) => {
                                        const stepNumber = index + 1;
                                        const colorMeta = resolvePipelineColor(node.color);
                                        return (
                                            <StepperItem
                                                key={node.key}
                                                step={stepNumber}
                                                completed={node.isCompleted}
                                                disabled
                                                className="min-w-0"
                                            >
                                                <StepperTrigger
                                                    render={<div />}
                                                    className="cursor-default gap-2 rounded-md px-1.5 py-1 disabled:opacity-100"
                                                >
                                                    <StepperIndicator
                                                        className={cn(
                                                            "size-6 text-[11px] font-semibold",
                                                            node.isCompleted &&
                                                                "bg-emerald-600 text-white dark:bg-emerald-500",
                                                            node.isCurrent && colorMeta.dot,
                                                            node.isCurrent && "text-white",
                                                        )}
                                                    >
                                                        {node.isCompleted ? (
                                                            <Check className="size-3.5" />
                                                        ) : (
                                                            stepNumber
                                                        )}
                                                    </StepperIndicator>
                                                    <StepperTitle
                                                        className={cn(
                                                            "text-xs whitespace-nowrap",
                                                            node.isCurrent
                                                                ? "text-foreground font-semibold"
                                                                : node.isCompleted
                                                                  ? "text-foreground/85"
                                                                  : "text-muted-foreground font-normal",
                                                        )}
                                                    >
                                                        {node.label}
                                                    </StepperTitle>
                                                </StepperTrigger>
                                                {index < stepperNodes.length - 1 && (
                                                    <StepperSeparator
                                                        className={cn(
                                                            "mx-1 min-w-6",
                                                            node.isCompleted &&
                                                                "bg-emerald-500/60",
                                                        )}
                                                    />
                                                )}
                                            </StepperItem>
                                        );
                                    })}
                                </StepperNav>
                            </Stepper>

                            {enrollment.policy_snapshot_name && (
                                <Badge variant="outline" size="sm" className="shrink-0">
                                    {enrollment.policy_snapshot_name}
                                    {enrollment.policy_snapshot_version
                                        ? ` v${enrollment.policy_snapshot_version}`
                                        : ""}
                                </Badge>
                            )}
                        </div>
                    </FramePanel>
                </Frame>
            )}

            {/* Dynamic Stage Focus Frame */}
            {isTrashedOrCancelled ? (
                <Frame spacing="sm" className="border-destructive/30">
                    <FramePanel className="space-y-4">
                        <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                            <div className="flex min-w-0 items-start gap-3">
                                <IconTile
                                    variant="soft"
                                    size="default"
                                    className="text-destructive shrink-0"
                                >
                                    {enrollment.is_trashed ? (
                                        <Trash2 className="size-5" />
                                    ) : (
                                        <Ban className="size-5" />
                                    )}
                                </IconTile>
                                <div className="min-w-0 space-y-1">
                                    <div className="flex flex-wrap items-center gap-2">
                                        <h2 className="text-foreground text-base font-semibold">
                                            {enrollment.is_trashed
                                                ? "Enrollment Archived in Trash"
                                                : "Enrollment Cancelled / Closed"}
                                        </h2>
                                        <Badge variant="destructive-light" size="sm">
                                            {enrollment.is_trashed
                                                ? "Trashed"
                                                : (enrollment.workflow.terminal_outcome ??
                                                  "Terminal")}
                                        </Badge>
                                    </div>
                                    <p className="text-muted-foreground text-sm">
                                        {latestOutcomeNote ??
                                            "This enrollment is inactive. Any reserved class seats were released and must be reassigned separately if reactivated."}
                                    </p>
                                </div>
                            </div>

                            <div className="flex shrink-0 flex-wrap items-center gap-2">
                                {enrollment.is_trashed && auth.can_restore && (
                                    <Button
                                        type="button"
                                        onClick={() => onOpenDialog("restore")}
                                    >
                                        <RotateCcw className="mr-1.5 size-4" />
                                        Restore Enrollment
                                    </Button>
                                )}
                                {!enrollment.is_trashed && auth.can_reopen && (
                                    <Button
                                        type="button"
                                        onClick={() => onOpenDialog("reopen")}
                                    >
                                        <RotateCcw className="mr-1.5 size-4" />
                                        {enrollment.workflow_runtime === "policy_v1"
                                            ? "Reopen Enrollment"
                                            : "Undo Cancellation"}
                                    </Button>
                                )}
                            </div>
                        </div>

                        <div className="bg-muted/40 grid grid-cols-2 gap-3 rounded-lg border p-3 text-xs sm:grid-cols-4">
                            <div>
                                <p className="text-muted-foreground">Recorded Subjects</p>
                                <p className="mt-0.5 font-semibold tabular-nums">
                                    {enrollment.subjects_enrolled.length} ({totalUnits} units)
                                </p>
                            </div>
                            <div>
                                <p className="text-muted-foreground">Total Assessment</p>
                                <p className="mt-0.5 font-semibold tabular-nums">
                                    {formatMoney(totalAssessment, currency)}
                                </p>
                            </div>
                            <div>
                                <p className="text-muted-foreground">Recorded Payments</p>
                                <p className="mt-0.5 font-semibold tabular-nums">
                                    {formatMoney(totalPaid, currency)}
                                </p>
                            </div>
                            <div>
                                <p className="text-muted-foreground">Seat Status</p>
                                <p className="text-destructive mt-0.5 font-medium">
                                    Seats released
                                </p>
                            </div>
                        </div>
                    </FramePanel>
                </Frame>
            ) : isCompleted ? (
                <Frame spacing="sm" className="border-emerald-500/25">
                    <FramePanel className="space-y-4">
                        <div className="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                            <div className="flex min-w-0 items-start gap-3">
                                <IconTile
                                    variant="soft"
                                    size="default"
                                    className="shrink-0 text-emerald-600 dark:text-emerald-400"
                                >
                                    <CheckCircle2 className="size-5" />
                                </IconTile>
                                <div className="min-w-0 space-y-0.5">
                                    <div className="flex flex-wrap items-center gap-2">
                                        <h2 className="text-foreground text-base font-semibold">
                                            Officially Enrolled
                                        </h2>
                                        <Badge variant="success-light" size="sm">
                                            {enrollment.status}
                                        </Badge>
                                    </div>
                                    <p className="text-muted-foreground text-xs sm:text-sm">
                                        Registration is finalized. Issue official documents, manage
                                        post-enrollment load changes, or collect remaining tuition
                                        balance.
                                    </p>
                                </div>
                            </div>

                            <div className="grid shrink-0 grid-cols-4 gap-2 rounded-lg border bg-emerald-500/[0.04] px-3 py-2 text-xs">
                                <div className="min-w-0">
                                    <p className="text-muted-foreground text-[11px]">Load</p>
                                    <p className="font-semibold tabular-nums">
                                        {enrollment.subjects_enrolled.length} subj
                                    </p>
                                </div>
                                <div className="min-w-0">
                                    <p className="text-muted-foreground text-[11px]">Units</p>
                                    <p className="font-semibold tabular-nums">{totalUnits}</p>
                                </div>
                                <div className="min-w-0">
                                    <p className="text-muted-foreground text-[11px]">Paid</p>
                                    <p className="font-semibold text-emerald-600 tabular-nums dark:text-emerald-400">
                                        {formatMoney(totalPaid, currency)}
                                    </p>
                                </div>
                                <div className="min-w-0">
                                    <p className="text-muted-foreground text-[11px]">Balance</p>
                                    <p
                                        className={cn(
                                            "font-semibold tabular-nums",
                                            balanceDue > 0
                                                ? "text-amber-600 dark:text-amber-400"
                                                : "text-emerald-600 dark:text-emerald-400",
                                        )}
                                    >
                                        {formatMoney(balanceDue, currency)}
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div className="flex flex-wrap items-center gap-2">
                            {documentLinks.cor_pdf_url && (
                                <Button
                                    type="button"
                                    size="sm"
                                    onClick={() => openExternal(documentLinks.cor_pdf_url)}
                                >
                                    <FileCheck2 className="mr-1.5 size-3.5" />
                                    COR PDF
                                </Button>
                            )}
                            {documentLinks.reg_form_student_url && (
                                <Button
                                    type="button"
                                    size="sm"
                                    variant="secondary"
                                    onClick={() =>
                                        openExternal(documentLinks.reg_form_student_url)
                                    }
                                >
                                    <FileText className="mr-1.5 size-3.5" />
                                    Registration Form
                                </Button>
                            )}
                            {documentLinks.soa_url && (
                                <Button
                                    type="button"
                                    size="sm"
                                    variant="outline"
                                    onClick={() => openExternal(documentLinks.soa_url)}
                                >
                                    <Receipt className="mr-1.5 size-3.5" />
                                    SOA PDF
                                </Button>
                            )}
                            {enrollment.workflow.can_print_assessment && (
                                <Button
                                    type="button"
                                    size="sm"
                                    variant="outline"
                                    onClick={() =>
                                        openExternal(documentLinks.assessment_preview_url)
                                    }
                                >
                                    <Printer className="mr-1.5 size-3.5" />
                                    Print Assessment
                                </Button>
                            )}
                            {auth.can_manage_enrollment && (
                                <>
                                    <Button
                                        type="button"
                                        size="sm"
                                        variant="outline"
                                        onClick={() => onOpenDialog("load_change")}
                                    >
                                        <SlidersHorizontal className="mr-1.5 size-3.5" />
                                        Add / Drop / Swap
                                    </Button>
                                    <Button
                                        type="button"
                                        size="sm"
                                        variant="outline"
                                        onClick={() => onOpenDialog("transfer")}
                                    >
                                        <ArrowLeftRight className="mr-1.5 size-3.5" />
                                        Shift / Transfer
                                    </Button>
                                </>
                            )}
                            <Link
                                href={documentLinks.re_enroll_url}
                                className={cn(
                                    buttonVariants({ variant: "outline", size: "sm" }),
                                    "gap-1.5",
                                )}
                            >
                                <RefreshCw className="size-3.5" />
                                Re-enroll Next Term
                            </Link>
                            {balanceDue > 0 && documentLinks.cashier_payment_url && (
                                <Link
                                    href={documentLinks.cashier_payment_url}
                                    className={cn(
                                        buttonVariants({ variant: "default", size: "sm" }),
                                        "gap-1.5 bg-emerald-600 text-white hover:bg-emerald-700",
                                    )}
                                >
                                    <CreditCard className="size-3.5" />
                                    Record Payment
                                </Link>
                            )}
                        </div>
                    </FramePanel>
                </Frame>
            ) : isPendingCashier ? (
                <Frame spacing="sm" className="border-primary/25">
                    <FrameHeader className="flex-row items-center justify-between gap-3">
                        <div className="flex min-w-0 items-center gap-2.5">
                            <IconTile variant="soft" size="sm" className="text-primary shrink-0">
                                <Banknote className="size-4" />
                            </IconTile>
                            <div className="min-w-0">
                                <FrameTitle className="truncate">
                                    Awaiting Cashier Verification &amp; Payment
                                </FrameTitle>
                                <FrameDescription className="truncate text-xs">
                                    Verify downpayment settlement or open the cashier desk to
                                    finalize enrollment.
                                </FrameDescription>
                            </div>
                        </div>
                        <Badge variant="primary-light" size="sm" className="shrink-0">
                            {enrollment.status}
                        </Badge>
                    </FrameHeader>
                    <FramePanel className="space-y-4">
                        <div className="grid grid-cols-2 gap-2.5 sm:grid-cols-3 lg:grid-cols-5">
                            <div className="bg-muted/35 min-w-0 rounded-lg border p-2.5">
                                <p className="text-muted-foreground truncate text-[11px]">
                                    Total Assessment
                                </p>
                                <p className="mt-0.5 truncate text-sm font-semibold tabular-nums">
                                    {formatMoney(totalAssessment, currency)}
                                </p>
                            </div>
                            <div className="bg-muted/35 min-w-0 rounded-lg border p-2.5">
                                <p className="text-muted-foreground truncate text-[11px]">
                                    Scholarship / Discount
                                </p>
                                <p className="mt-0.5 truncate text-sm font-semibold tabular-nums">
                                    {discountPercent > 0
                                        ? `${discountPercent}%${enrollment.tuition?.discount_name ? ` · ${enrollment.tuition.discount_name}` : ""}`
                                        : (enrollment.student.scholarship_label ?? "None")}
                                </p>
                            </div>
                            <div className="bg-muted/35 min-w-0 rounded-lg border p-2.5">
                                <p className="text-muted-foreground truncate text-[11px]">
                                    Required Downpayment
                                </p>
                                <p className="mt-0.5 truncate text-sm font-semibold tabular-nums">
                                    {formatMoney(requiredDownpayment, currency)}
                                </p>
                            </div>
                            <div className="bg-muted/35 min-w-0 rounded-lg border p-2.5">
                                <p className="text-muted-foreground truncate text-[11px]">Paid</p>
                                <p className="mt-0.5 truncate text-sm font-semibold text-emerald-600 tabular-nums dark:text-emerald-400">
                                    {formatMoney(totalPaid, currency)}
                                </p>
                            </div>
                            <div className="bg-muted/35 min-w-0 rounded-lg border p-2.5">
                                <p className="text-muted-foreground truncate text-[11px]">
                                    Balance Due
                                </p>
                                <p className="mt-0.5 truncate text-sm font-semibold text-amber-600 tabular-nums dark:text-amber-400">
                                    {formatMoney(balanceDue, currency)}
                                </p>
                            </div>
                        </div>

                        <div className="flex flex-wrap items-center gap-2">
                            {paymentTransition ? (
                                <VerifyCashierDialog
                                    enrollmentId={enrollment.id}
                                    tuition={enrollment.tuition}
                                    additionalFees={enrollment.additional_fees}
                                    label={paymentTransition.label || "Verify Payment & Enroll"}
                                    transitionKey={paymentTransition.key}
                                    requiresReason={paymentTransition.requires_reason}
                                    receiptMode={
                                        paymentTransition.payment_configuration?.receipt_mode ??
                                        "required"
                                    }
                                    allowedPaymentMethods={
                                        enrollment.workflow.allowed_payment_methods
                                    }
                                    assessmentPreviewUrl={documentLinks.assessment_preview_url}
                                    buttonSize="sm"
                                />
                            ) : auth.can_verify_cashier ? (
                                <VerifyCashierDialog
                                    enrollmentId={enrollment.id}
                                    tuition={enrollment.tuition}
                                    additionalFees={enrollment.additional_fees}
                                    label="Verify Payment & Enroll"
                                    allowedPaymentMethods={
                                        enrollment.workflow.allowed_payment_methods
                                    }
                                    assessmentPreviewUrl={documentLinks.assessment_preview_url}
                                    buttonSize="sm"
                                />
                            ) : null}

                            {enrollment.workflow.can_print_assessment && (
                                <Button
                                    type="button"
                                    size="sm"
                                    variant="outline"
                                    onClick={() =>
                                        openExternal(documentLinks.assessment_preview_url)
                                    }
                                >
                                    <Printer className="mr-1.5 size-3.5" />
                                    Print Draft Assessment
                                </Button>
                            )}

                            {documentLinks.cashier_payment_url && (
                                <Link
                                    href={documentLinks.cashier_payment_url}
                                    className={cn(
                                        buttonVariants({ variant: "outline", size: "sm" }),
                                        "gap-1.5",
                                    )}
                                >
                                    <CreditCard className="size-3.5" />
                                    Open Cashier Desk
                                </Link>
                            )}

                            {enrollment.workflow.can_resend_assessment && (
                                <Button
                                    type="button"
                                    size="sm"
                                    variant="outline"
                                    disabled={busyAction === "resend_assessment"}
                                    onClick={() => onSimpleAction("resend_assessment")}
                                >
                                    <Mail className="mr-1.5 size-3.5" />
                                    {busyAction === "resend_assessment"
                                        ? "Sending…"
                                        : "Send eInvoice"}
                                </Button>
                            )}
                        </div>
                    </FramePanel>
                </Frame>
            ) : (
                /* Default: Pending Review / Dept Head / Intake */
                <Frame spacing="sm">
                    <FrameHeader className="flex-row items-center justify-between gap-3">
                        <div className="flex min-w-0 items-center gap-2.5">
                            <IconTile variant="soft" size="sm" className="text-info shrink-0">
                                <ClipboardCheck className="size-4" />
                            </IconTile>
                            <div className="min-w-0">
                                <FrameTitle className="truncate">
                                    Advising &amp; Department Review
                                </FrameTitle>
                                <FrameDescription className="truncate text-xs">
                                    Verify academic load, prerequisites, and pending requirements
                                    before advancing the enrollment.
                                </FrameDescription>
                            </div>
                        </div>
                        <Badge variant="warning-light" size="sm" className="shrink-0">
                            {enrollment.status}
                        </Badge>
                    </FrameHeader>
                    <FramePanel className="space-y-4">
                        {/* Advising Readiness Strip */}
                        <div className="grid grid-cols-2 gap-2 sm:grid-cols-3 lg:grid-cols-6">
                            <button
                                type="button"
                                onClick={() => onJumpToTab("subjects")}
                                className="bg-muted/35 hover:bg-muted/60 min-w-0 rounded-lg border p-2.5 text-left transition-colors"
                            >
                                <p className="text-muted-foreground truncate text-[11px]">Units</p>
                                <p className="mt-0.5 text-sm font-semibold tabular-nums">
                                    {totalUnits}{" "}
                                    <span className="text-muted-foreground text-[11px] font-normal">
                                        ({enrollment.subjects_enrolled.length} subj)
                                    </span>
                                </p>
                            </button>

                            <div className="bg-muted/35 min-w-0 rounded-lg border p-2.5">
                                <p className="text-muted-foreground truncate text-[11px]">
                                    Curriculum
                                </p>
                                <p className="mt-0.5 text-sm font-semibold tabular-nums">
                                    {checklistProgress !== null
                                        ? `${Math.round(checklistProgress)}%`
                                        : "—"}
                                </p>
                            </div>

                            <button
                                type="button"
                                onClick={() => onJumpToTab("subjects")}
                                className="bg-muted/35 hover:bg-muted/60 min-w-0 rounded-lg border p-2.5 text-left transition-colors"
                            >
                                <p className="text-muted-foreground truncate text-[11px]">
                                    Retakes
                                </p>
                                <p
                                    className={cn(
                                        "mt-0.5 text-sm font-semibold tabular-nums",
                                        retakesCount > 0 && "text-amber-600 dark:text-amber-400",
                                    )}
                                >
                                    {retakesCount}
                                </p>
                            </button>

                            <button
                                type="button"
                                onClick={() => onJumpToTab("subjects")}
                                className="bg-muted/35 hover:bg-muted/60 min-w-0 rounded-lg border p-2.5 text-left transition-colors"
                            >
                                <p className="text-muted-foreground truncate text-[11px]">
                                    Prereqs
                                </p>
                                <p
                                    className={cn(
                                        "mt-0.5 text-sm font-semibold tabular-nums",
                                        prereqIssuesCount > 0
                                            ? "text-destructive"
                                            : "text-emerald-600 dark:text-emerald-400",
                                    )}
                                >
                                    {prereqIssuesCount > 0 ? `${prereqIssuesCount} missing` : "OK"}
                                </p>
                            </button>

                            <button
                                type="button"
                                onClick={() => onJumpToTab("schedule")}
                                className="bg-muted/35 hover:bg-muted/60 min-w-0 rounded-lg border p-2.5 text-left transition-colors"
                            >
                                <p className="text-muted-foreground truncate text-[11px]">
                                    Clashes
                                </p>
                                <p
                                    className={cn(
                                        "mt-0.5 text-sm font-semibold tabular-nums",
                                        scheduleConflictsCount > 0
                                            ? "text-destructive"
                                            : "text-emerald-600 dark:text-emerald-400",
                                    )}
                                >
                                    {scheduleConflictsCount}
                                </p>
                            </button>

                            <button
                                type="button"
                                onClick={() => onJumpToTab("holds")}
                                className="bg-muted/35 hover:bg-muted/60 min-w-0 rounded-lg border p-2.5 text-left transition-colors"
                            >
                                <p className="text-muted-foreground truncate text-[11px]">
                                    Active Holds
                                </p>
                                <p
                                    className={cn(
                                        "mt-0.5 text-sm font-semibold tabular-nums",
                                        activeHoldsCount > 0
                                            ? "text-destructive"
                                            : "text-emerald-600 dark:text-emerald-400",
                                    )}
                                >
                                    {activeHoldsCount}
                                </p>
                            </button>
                        </div>

                        {/* Inline Pending Requirements (up to 3) */}
                        {pendingRequirements.length > 0 && (
                            <div className="bg-muted/25 space-y-2 rounded-lg border p-3">
                                <div className="flex items-center justify-between gap-2">
                                    <p className="text-xs font-semibold">
                                        Pending Requirements ({pendingRequirements.length})
                                    </p>
                                    {pendingRequirements.length > 3 && (
                                        <button
                                            type="button"
                                            onClick={() => onJumpToTab("requirements")}
                                            className="text-primary text-xs font-medium hover:underline"
                                        >
                                            View all {pendingRequirements.length}
                                        </button>
                                    )}
                                </div>
                                <div className="divide-y">
                                    {pendingRequirements.slice(0, 3).map((requirement) => {
                                        const isReviewing =
                                            reviewingRequirementId === requirement.id;
                                        return (
                                            <div
                                                key={requirement.id}
                                                className="flex flex-wrap items-center justify-between gap-2 py-2 first:pt-0 last:pb-0"
                                            >
                                                <div className="min-w-0 flex-1">
                                                    <div className="flex items-center gap-1.5">
                                                        <BookOpen className="text-muted-foreground size-3.5 shrink-0" />
                                                        <span className="truncate text-xs font-medium">
                                                            {requirement.label}
                                                        </span>
                                                        {requirement.required && (
                                                            <Badge variant="warning-light" size="xs">
                                                                Required
                                                            </Badge>
                                                        )}
                                                    </div>
                                                    {requirement.description && (
                                                        <p className="text-muted-foreground mt-0.5 truncate text-[11px]">
                                                            {requirement.description}
                                                        </p>
                                                    )}
                                                </div>

                                                {auth.can_review_requirements && (
                                                    <div className="flex shrink-0 items-center gap-1.5">
                                                        <Button
                                                            type="button"
                                                            size="sm"
                                                            variant="secondary"
                                                            className="h-7 px-2.5 text-xs"
                                                            disabled={isReviewing}
                                                            onClick={() =>
                                                                onReviewRequirement(
                                                                    requirement.id,
                                                                    "verify",
                                                                )
                                                            }
                                                        >
                                                            <Check className="mr-1 size-3" />
                                                            Verify
                                                        </Button>
                                                        <Button
                                                            type="button"
                                                            size="sm"
                                                            variant="ghost"
                                                            className="h-7 px-2 text-xs"
                                                            disabled={isReviewing}
                                                            onClick={() =>
                                                                onReviewRequirement(
                                                                    requirement.id,
                                                                    "waive",
                                                                )
                                                            }
                                                        >
                                                            <XCircle className="mr-1 size-3" />
                                                            Waive
                                                        </Button>
                                                    </div>
                                                )}
                                            </div>
                                        );
                                    })}
                                </div>
                            </div>
                        )}

                        {/* Primary Step Advance & Advising Actions */}
                        <div className="flex flex-wrap items-center gap-2">
                            {enrollment.workflow_runtime === "policy_v1" &&
                                enrollment.workflow.allowed_transitions.map((transition) =>
                                    transition.target_actions.includes(
                                        "enrollment.verify_payment",
                                    ) ? (
                                        <VerifyCashierDialog
                                            key={transition.key}
                                            enrollmentId={enrollment.id}
                                            tuition={enrollment.tuition}
                                            additionalFees={enrollment.additional_fees}
                                            label={transition.label}
                                            transitionKey={transition.key}
                                            requiresReason={transition.requires_reason}
                                            receiptMode={
                                                transition.payment_configuration?.receipt_mode ??
                                                "required"
                                            }
                                            allowedPaymentMethods={
                                                enrollment.workflow.allowed_payment_methods
                                            }
                                            assessmentPreviewUrl={
                                                documentLinks.assessment_preview_url
                                            }
                                            buttonSize="sm"
                                        />
                                    ) : (
                                        <Button
                                            key={transition.key}
                                            type="button"
                                            size="sm"
                                            disabled={Boolean(busyAction)}
                                            onClick={() => onPolicyTransition(transition)}
                                        >
                                            <CheckCircle2 className="mr-1.5 size-3.5" />
                                            {transition.label}
                                        </Button>
                                    ),
                                )}

                            {enrollment.workflow_runtime === "legacy" &&
                                nextStep?.action_type === "department_verification" &&
                                auth.can_verify_head && (
                                    <Button
                                        type="button"
                                        size="sm"
                                        disabled={busyAction === "verify_head_dept"}
                                        onClick={() => onSimpleAction("verify_head_dept")}
                                    >
                                        <UserCheck className="mr-1.5 size-3.5" />
                                        {busyAction === "verify_head_dept"
                                            ? "Verifying…"
                                            : `Verify Dept Head (${nextStep.label})`}
                                    </Button>
                                )}

                            {enrollment.workflow_runtime === "legacy" &&
                                auth.can_advance_pipeline &&
                                nextStep &&
                                nextStep.action_type !== "department_verification" &&
                                nextStep.action_type !== "cashier_verification" && (
                                    <Button
                                        type="button"
                                        size="sm"
                                        disabled={busyAction === "advance_pipeline"}
                                        onClick={() => onSimpleAction("advance_pipeline")}
                                    >
                                        <ArrowRight className="mr-1.5 size-3.5" />
                                        {busyAction === "advance_pipeline"
                                            ? "Advancing…"
                                            : `Advance: ${nextStep.label}`}
                                    </Button>
                                )}

                            {auth.can_manage_enrollment && (
                                <Link
                                    href={editEnrollment.url(enrollment.id)}
                                    className={cn(
                                        buttonVariants({ variant: "outline", size: "sm" }),
                                        "gap-1.5",
                                    )}
                                >
                                    <Pencil className="size-3.5" />
                                    Edit Load
                                </Link>
                            )}

                            {enrollment.workflow.can_print_assessment && (
                                <Button
                                    type="button"
                                    size="sm"
                                    variant="outline"
                                    onClick={() =>
                                        openExternal(documentLinks.assessment_preview_url)
                                    }
                                >
                                    <Printer className="mr-1.5 size-3.5" />
                                    Print Draft Assessment
                                </Button>
                            )}
                        </div>
                    </FramePanel>
                </Frame>
            )}
        </div>
    );
}
