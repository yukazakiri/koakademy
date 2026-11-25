import {
    index as holdsIndexRoute,
    override as overrideHoldRoute,
    release as releaseHoldRoute,
    store as storeHoldRoute,
} from "@/actions/App/Http/Controllers/AdministratorStudentHoldController";
import { Alert, AlertDescription, AlertTitle } from "@/components/reui/alert";
import { Badge } from "@/components/reui/badge";
import { Frame, FrameDescription, FrameHeader, FramePanel, FrameTitle } from "@/components/reui/frame";
import { IconTile } from "@/components/reui/icon-tile";
import { Stepper, StepperIndicator, StepperItem, StepperNav, StepperSeparator, StepperTitle, StepperTrigger } from "@/components/reui/stepper";
import {
    Timeline,
    TimelineContent,
    TimelineDate,
    TimelineHeader,
    TimelineIndicator,
    TimelineItem,
    TimelineSeparator,
    TimelineTitle,
} from "@/components/reui/timeline";
import { Avatar, AvatarFallback } from "@/components/ui/avatar";
import { Button, buttonVariants } from "@/components/ui/button";
import { Checkbox } from "@/components/ui/checkbox";
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from "@/components/ui/collapsible";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Progress } from "@/components/ui/progress";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Sheet, SheetContent, SheetDescription, SheetFooter, SheetHeader, SheetTitle } from "@/components/ui/sheet";
import { Skeleton } from "@/components/ui/skeleton";
import { cn } from "@/lib/utils";
import {
    cancel as cancelEnrollmentRoute,
    summary as enrollmentSummaryRoute,
    reopen as reopenEnrollmentRoute,
    undoCancellation as undoCancellationRoute,
} from "@/routes/administrators/enrollments";
import { review as reviewRequirementRoute } from "@/routes/administrators/enrollments/requirements";
import { store as storeTransitionRoute } from "@/routes/administrators/enrollments/transitions";
import { Link, router } from "@inertiajs/react";
import axios from "axios";
import {
    AlertCircle,
    AlertTriangle,
    ArrowRight,
    ArrowUpRight,
    Ban,
    BookOpen,
    Check,
    CheckCircle2,
    ChevronDown,
    ChevronLeft,
    ChevronRight,
    Copy,
    CreditCard,
    Download,
    FileText,
    Loader2,
    Mail,
    Pencil,
    Phone,
    Plus,
    Receipt,
    RotateCcw,
    Send,
    ShieldAlert,
    ShieldCheck,
    ShieldOff,
    Trash2,
    Undo2,
    Unlock,
    UserRound,
} from "lucide-react";
import { useCallback, useEffect, useMemo, useState } from "react";
import { toast } from "sonner";
import { route } from "ziggy-js";
import type { EnrollmentRow, EnrollmentTransitionOption } from "./columns";
import { formatMoney, formatShortDate, getInitials, resolveStatusMeta, tuitionSettlementPercent } from "./enrollment-utils";
import type { EnrollmentDetailSummary, EnrollmentPipeline } from "./types";

type EnrollmentDetailSheetProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    enrollment: EnrollmentRow | null;
    pipeline: EnrollmentPipeline;
    currency: string;
    initialOpenCashier?: boolean;
    initialTransitionKey?: string | null;
    hasPrevious?: boolean;
    hasNext?: boolean;
    onPrevious?: () => void;
    onNext?: () => void;
    onDelete?: (enrollment: EnrollmentRow) => void;
    onRestore?: (enrollment: EnrollmentRow) => void;
    onMutationSuccess?: () => void;
};

const DEFAULT_PAYMENT_METHODS = ["Cash", "Bank Transfer", "GCash", "Maya", "Check", "Online Payment"];

function makeIdempotencyKey(prefix: string): string {
    if (typeof crypto !== "undefined" && typeof crypto.randomUUID === "function") {
        return `${prefix}-${crypto.randomUUID()}`;
    }
    return `${prefix}-${Date.now()}-${Math.random().toString(36).slice(2, 10)}`;
}

export function EnrollmentDetailSheet({
    open,
    onOpenChange,
    enrollment,
    pipeline,
    currency,
    initialOpenCashier = false,
    initialTransitionKey = null,
    hasPrevious = false,
    hasNext = false,
    onPrevious,
    onNext,
    onDelete,
    onRestore,
    onMutationSuccess,
}: EnrollmentDetailSheetProps) {
    const [detail, setDetail] = useState<EnrollmentDetailSummary | null>(null);
    const [isLoading, setIsLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [busyAction, setBusyAction] = useState<string | null>(null);
    const [subjectsOpen, setSubjectsOpen] = useState(false);
    const [cashierOpen, setCashierOpen] = useState(initialOpenCashier);
    const [activeTransitionKey, setActiveTransitionKey] = useState<string | null>(initialTransitionKey);

    // Inline reason transition state (for policy_v1 transitions with requires_reason)
    const [pendingReasonTransition, setPendingReasonTransition] = useState<EnrollmentTransitionOption | null>(null);
    const [transitionReason, setTransitionReason] = useState("");

    // Inline Cancel / Reopen state
    const [cancelOpen, setCancelOpen] = useState(false);
    const [cancelReason, setCancelReason] = useState("");
    const [acknowledgePayments, setAcknowledgePayments] = useState(false);
    const [reopenOpen, setReopenOpen] = useState(false);
    const [reopenReason, setReopenReason] = useState("");

    // Inline Student Holds state
    const [placeHoldOpen, setPlaceHoldOpen] = useState(false);
    const [holdOffice, setHoldOffice] = useState("registrar");
    const [holdSeverity, setHoldSeverity] = useState<"blocking" | "warning">("blocking");
    const [holdTitle, setHoldTitle] = useState("");
    const [holdReason, setHoldReason] = useState("");
    const [overrideHoldId, setOverrideHoldId] = useState<number | null>(null);
    const [overrideHoldReason, setOverrideHoldReason] = useState("");

    // Inline Cashier Verification form state
    const [invoiceNumber, setInvoiceNumber] = useState("");
    const [paymentMethod, setPaymentMethod] = useState("Cash");
    const [registrationFee, setRegistrationFee] = useState("0");
    const [tuitionFee, setTuitionFee] = useState("0");
    const [miscFee, setMiscFee] = useState("0");
    const [otherFee, setOtherFee] = useState("0");
    const [cashierReason, setCashierReason] = useState("");

    const enrollmentId = enrollment?.id ?? null;

    const loadSummary = useCallback(async () => {
        if (!enrollmentId) return;

        setIsLoading(true);
        setError(null);

        try {
            const response = await axios.get<EnrollmentDetailSummary>(enrollmentSummaryRoute.url(enrollmentId));
            setDetail(response.data);

            const t = response.data.tuition;
            if (t) {
                const defaultPay = Math.max(0, t.downpayment || t.total_balance || 0);
                setTuitionFee(defaultPay > 0 ? String(defaultPay) : "0");
                setRegistrationFee("0");
                setMiscFee("0");
                setOtherFee("0");
            }
            const allowedMethods = response.data.workflow?.allowed_payment_methods;
            if (Array.isArray(allowedMethods) && allowedMethods.length > 0) {
                setPaymentMethod(allowedMethods[0]);
            }
        } catch {
            setDetail(null);
            setError("Could not load this enrollment dossier. Try again or open the full record.");
        } finally {
            setIsLoading(false);
        }
    }, [enrollmentId]);

    useEffect(() => {
        if (!open) {
            setDetail(null);
            setError(null);
            setCashierOpen(false);
            setSubjectsOpen(false);
            setPendingReasonTransition(null);
            setTransitionReason("");
            setCancelOpen(false);
            setCancelReason("");
            setAcknowledgePayments(false);
            setReopenOpen(false);
            setReopenReason("");
            setInvoiceNumber("");
            setCashierReason("");
            setActiveTransitionKey(null);
            return;
        }

        setCashierOpen(initialOpenCashier);
        setActiveTransitionKey(initialTransitionKey);
        void loadSummary();
    }, [open, initialOpenCashier, initialTransitionKey, loadSummary]);

    // Keyboard stepping (Up/Down or J/K) when sheet is open and not typing in an input
    useEffect(() => {
        if (!open) return;

        const onKeyDown = (event: KeyboardEvent) => {
            const target = event.target as HTMLElement | null;
            const isTyping =
                target?.tagName === "INPUT" || target?.tagName === "TEXTAREA" || target?.tagName === "SELECT" || target?.isContentEditable;
            if (isTyping) return;

            if ((event.key === "ArrowDown" || event.key === "j") && hasNext && onNext) {
                event.preventDefault();
                onNext();
            } else if ((event.key === "ArrowUp" || event.key === "k") && hasPrevious && onPrevious) {
                event.preventDefault();
                onPrevious();
            }
        };

        window.addEventListener("keydown", onKeyDown);
        return () => window.removeEventListener("keydown", onKeyDown);
    }, [open, hasNext, hasPrevious, onNext, onPrevious]);

    const currentStatus = detail?.enrollment.status ?? enrollment?.status ?? "N/A";
    const currentStepKey = detail?.workflow?.current_step_key ?? enrollment?.current_step_key ?? null;
    const terminalOutcome = detail?.enrollment.terminal_outcome ?? enrollment?.terminal_outcome ?? null;
    const isTrashed = detail?.enrollment.is_trashed ?? enrollment?.is_trashed ?? false;
    const isPolicyRuntime = (detail?.workflow?.engine ?? (enrollment?.workflow_runtime === "policy_v1" ? "policy_v1" : "legacy")) === "policy_v1";
    const statusMeta = useMemo(() => resolveStatusMeta(currentStatus, terminalOutcome, pipeline), [currentStatus, terminalOutcome, pipeline]);

    const steps = detail?.workflow?.steps?.length ? detail.workflow.steps : pipeline.steps;
    const policyTransitions = detail?.workflow?.allowed_transitions ?? enrollment?.allowed_transitions ?? [];
    const nextStep = detail?.workflow?.next_step ?? enrollment?.next_step ?? null;

    const activeStepNumber = useMemo(() => {
        if (steps.length === 0) return 1;
        if (statusMeta.isCompleted) return steps.length + 1;
        if (currentStepKey) {
            const byKey = steps.findIndex((s) => s.key === currentStepKey);
            if (byKey >= 0) return byKey + 1;
        }
        const idx = steps.findIndex((s) => s.status === currentStatus);
        return idx >= 0 ? idx + 1 : 1;
    }, [steps, currentStepKey, currentStatus, statusMeta.isCompleted]);

    const nextStepNeedsCashier = nextStep?.action_type === "cashier_verification";
    const nextStepIsDept = nextStep?.action_type === "department_verification";
    const canAdvance = detail?.permissions?.can_advance_pipeline ?? enrollment?.can_advance ?? false;

    const refreshAfterAction = () => {
        void loadSummary();
        onMutationSuccess?.();
    };

    const runPostAction = (actionKey: string, routeName: string, payload: Record<string, unknown>, successMsg: string) => {
        if (!enrollmentId) return;
        setBusyAction(actionKey);
        router.post(route(routeName, enrollmentId, false), payload as never, {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                toast.success(successMsg);
                setCashierOpen(false);
                setInvoiceNumber("");
                setCashierReason("");
                refreshAfterAction();
            },
            onError: () => {
                toast.error("Action could not be completed. Check permissions or required fields.");
            },
            onFinish: () => {
                setBusyAction(null);
            },
        });
    };

    const executePolicyTransition = async (transition: EnrollmentTransitionOption, reason?: string) => {
        if (!enrollmentId) return;
        setBusyAction(`transition:${transition.key}`);
        try {
            await axios.post(storeTransitionRoute.url(enrollmentId), {
                transition_key: transition.key,
                payload: reason ? { reason } : {},
                idempotency_key: makeIdempotencyKey(`sheet-tr-${enrollmentId}`),
            });
            toast.success(`Moved to ${transition.to_status}.`);
            setPendingReasonTransition(null);
            setTransitionReason("");
            refreshAfterAction();
        } catch (err: unknown) {
            const message =
                axios.isAxiosError(err) && typeof err.response?.data?.message === "string"
                    ? err.response.data.message
                    : "Unable to execute this workflow transition.";
            toast.error(message);
        } finally {
            setBusyAction(null);
        }
    };

    const handlePolicyTransitionClick = (transition: EnrollmentTransitionOption) => {
        if (transition.needs_payment) {
            setActiveTransitionKey(transition.key);
            setCashierOpen((prev) => !prev);
            return;
        }
        if (transition.requires_reason) {
            setPendingReasonTransition(transition);
            setTransitionReason("");
            return;
        }
        void executePolicyTransition(transition);
    };

    const handlePrimaryLegacyAdvance = () => {
        if (!nextStep || !enrollmentId) return;

        if (nextStepNeedsCashier) {
            setCashierOpen((prev) => !prev);
            return;
        }

        if (nextStepIsDept) {
            runPostAction("advance", "administrators.enrollments.verify-head-dept", {}, "Verified by Department Head.");
            return;
        }

        runPostAction("advance", "administrators.enrollments.advance-pipeline-step", {}, `Moved to ${nextStep.status}.`);
    };

    const handleCashierSubmit = (event: React.FormEvent) => {
        event.preventDefault();
        if (!enrollmentId) return;

        if (!isPolicyRuntime && !invoiceNumber.trim()) {
            toast.error("Enter an official receipt or invoice number.");
            return;
        }

        runPostAction(
            "cashier",
            "administrators.enrollments.verify-cashier",
            {
                invoicenumber: invoiceNumber.trim() || null,
                payment_method: paymentMethod,
                reason: cashierReason.trim() || null,
                transition_key: activeTransitionKey || (nextStep && "transition_key" in nextStep ? nextStep.transition_key : null) || null,
                settlements: {
                    registration_fee: Number(registrationFee) || 0,
                    tuition_fee: Number(tuitionFee) || 0,
                    miscelanous_fee: Number(miscFee) || 0,
                    others: Number(otherFee) || 0,
                },
            },
            "Payment verified and enrollment updated.",
        );
    };

    const handleReviewRequirement = async (requirementId: number, action: "verify" | "waive") => {
        if (!enrollmentId) return;
        setBusyAction(`req:${requirementId}`);
        try {
            await axios.post(reviewRequirementRoute.url([enrollmentId, requirementId]), {
                action,
                reason: action === "waive" ? "Waived by administrator during enrollment review." : undefined,
                idempotency_key: makeIdempotencyKey(`req-${requirementId}`),
            });
            toast.success(action === "verify" ? "Requirement verified." : "Requirement waived.");
            refreshAfterAction();
        } catch (err: unknown) {
            const message =
                axios.isAxiosError(err) && typeof err.response?.data?.message === "string"
                    ? err.response.data.message
                    : "Failed to update requirement.";
            toast.error(message);
        } finally {
            setBusyAction(null);
        }
    };

    const handleCancelSubmit = async (event: React.FormEvent) => {
        event.preventDefault();
        if (!enrollmentId || !cancelReason.trim()) {
            toast.error("Provide a cancellation reason.");
            return;
        }

        setBusyAction("cancel");
        try {
            await axios.post(cancelEnrollmentRoute.url(enrollmentId), {
                reason: cancelReason.trim(),
                idempotency_key: makeIdempotencyKey(`cancel-${enrollmentId}`),
                acknowledge_outstanding_payments: acknowledgePayments,
            });
            toast.success("Enrollment cancelled.");
            setCancelOpen(false);
            setCancelReason("");
            setAcknowledgePayments(false);
            refreshAfterAction();
        } catch (err: unknown) {
            const message =
                axios.isAxiosError(err) && typeof err.response?.data?.message === "string"
                    ? err.response.data.message
                    : "Unable to cancel this enrollment.";
            toast.error(message);
        } finally {
            setBusyAction(null);
        }
    };

    const handleReopenSubmit = async (event: React.FormEvent) => {
        event.preventDefault();
        if (!enrollmentId || !reopenReason.trim()) {
            toast.error("Provide a reason to reopen this enrollment.");
            return;
        }

        setBusyAction("reopen");
        try {
            if (isPolicyRuntime) {
                await axios.post(reopenEnrollmentRoute.url(enrollmentId), {
                    reason: reopenReason.trim(),
                    idempotency_key: makeIdempotencyKey(`reopen-${enrollmentId}`),
                });
                toast.success("Enrollment reopened.");
                setReopenOpen(false);
                setReopenReason("");
                refreshAfterAction();
            } else {
                router.post(
                    undoCancellationRoute.url(enrollmentId),
                    { reason: reopenReason.trim() },
                    {
                        preserveScroll: true,
                        preserveState: true,
                        onSuccess: () => {
                            toast.success("Cancellation reverted.");
                            setReopenOpen(false);
                            setReopenReason("");
                            refreshAfterAction();
                        },
                        onError: () => toast.error("Could not revert cancellation."),
                        onFinish: () => setBusyAction(null),
                    },
                );
                return;
            }
        } catch (err: unknown) {
            const message =
                axios.isAxiosError(err) && typeof err.response?.data?.message === "string"
                    ? err.response.data.message
                    : "Could not reopen this enrollment.";
            toast.error(message);
        } finally {
            setBusyAction(null);
        }
    };

    const handleResendAssessment = () => {
        runPostAction("resend", "administrators.enrollments.resend-assessment", {}, "Assessment email queued for delivery.");
    };

    const handleUndoDept = () => {
        runPostAction("undo-dept", "administrators.enrollments.undo-head-dept", {}, "Department verification reverted.");
    };

    const handleUndoCashier = () => {
        runPostAction("undo-cashier", "administrators.enrollments.undo-cashier", {}, "Cashier verification reverted.");
    };

    const studentName = detail?.student.full_name ?? enrollment?.student_name ?? "Student";
    const studentNumber = detail?.student.student_number ?? enrollment?.student_number ?? enrollment?.student_id;
    const courseCode = detail?.enrollment.course_code ?? enrollment?.course;
    const courseTitle = detail?.enrollment.course_title ?? enrollment?.course_title;
    const department = detail?.enrollment.department ?? enrollment?.department;
    const academicYear = detail?.enrollment.academic_year ?? enrollment?.academic_year;

    const tuitionOverall = detail?.tuition?.overall_tuition ?? enrollment?.tuition?.overall ?? null;
    const tuitionBalance = detail?.tuition?.total_balance ?? enrollment?.tuition?.balance ?? null;
    const tuitionPaid =
        detail?.tuition?.total_paid ??
        enrollment?.tuition?.paid ??
        (tuitionOverall !== null && tuitionBalance !== null ? Math.max(0, tuitionOverall - tuitionBalance) : null);
    const settlementPct = tuitionSettlementPercent(tuitionOverall, tuitionBalance);
    const paymentMethods =
        detail?.workflow?.allowed_payment_methods && detail.workflow.allowed_payment_methods.length > 0
            ? detail.workflow.allowed_payment_methods
            : DEFAULT_PAYMENT_METHODS;

    const canUndoDept =
        !isPolicyRuntime &&
        !isTrashed &&
        !statusMeta.isCancelled &&
        (detail?.permissions?.can_verify_head ?? false) &&
        currentStatus === pipeline.department_verified_status;
    const canUndoCashier =
        !isPolicyRuntime &&
        !isTrashed &&
        !statusMeta.isCancelled &&
        (detail?.permissions?.can_verify_cashier ?? false) &&
        (currentStatus === pipeline.cashier_verified_status || currentStatus === "Enrolled");

    const outstandingPayments = detail?.workflow?.outstanding_payments;
    const hasOutstandingPayments = (outstandingPayments?.amount ?? 0) > 0 || (outstandingPayments?.count ?? 0) > 0;
    const canCancel = Boolean(detail?.permissions?.can_cancel && !isTrashed && !statusMeta.isCancelled);
    const canReopen = Boolean(detail?.permissions?.can_reopen && !isTrashed && statusMeta.isCancelled);
    const canReviewRequirements = Boolean(detail?.permissions?.can_review_requirements && !isTrashed && !statusMeta.isCancelled);

    return (
        <Sheet open={open} onOpenChange={onOpenChange}>
            <SheetContent className="flex w-full flex-col gap-0 overflow-hidden p-0 sm:max-w-xl">
                {/* Sticky Sheet Header with Quick Record Stepping */}
                <SheetHeader className="border-border/70 bg-card/60 shrink-0 border-b px-5 py-4">
                    <div className="flex items-center justify-between gap-2 pr-7">
                        <div className="flex flex-wrap items-center gap-1.5">
                            <Badge variant={statusMeta.badge} radius="full" size="lg" className="gap-1.5">
                                <span className={cn("size-1.5 rounded-full", statusMeta.dot)} aria-hidden="true" />
                                {statusMeta.label}
                            </Badge>
                            {isPolicyRuntime && (
                                <Badge variant="primary-outline" radius="full" size="sm">
                                    Policy v1
                                </Badge>
                            )}
                            {isTrashed && (
                                <Badge variant="destructive-light" radius="full" size="lg">
                                    Deleted
                                </Badge>
                            )}
                            {department && (
                                <Badge variant="outline" radius="full" size="lg">
                                    {department}
                                </Badge>
                            )}
                        </div>
                        {(onPrevious || onNext) && (
                            <div className="flex items-center gap-1">
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon-xs"
                                    disabled={!hasPrevious}
                                    onClick={onPrevious}
                                    title="Previous student (↑ / K)"
                                >
                                    <ChevronLeft className="size-3.5" />
                                    <span className="sr-only">Previous enrollment</span>
                                </Button>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon-xs"
                                    disabled={!hasNext}
                                    onClick={onNext}
                                    title="Next student (↓ / J)"
                                >
                                    <ChevronRight className="size-3.5" />
                                    <span className="sr-only">Next enrollment</span>
                                </Button>
                            </div>
                        )}
                    </div>

                    <div className="mt-3 flex items-start gap-3">
                        <Avatar className="border-border/70 size-11 shrink-0 border">
                            <AvatarFallback
                                className={cn(
                                    "text-xs font-semibold",
                                    isTrashed ? "bg-destructive/15 text-destructive" : "bg-primary/10 text-primary",
                                )}
                            >
                                {getInitials(studentName)}
                            </AvatarFallback>
                        </Avatar>
                        <div className="min-w-0 flex-1">
                            <SheetTitle className="truncate text-base font-semibold tracking-tight">{studentName}</SheetTitle>
                            <SheetDescription className="mt-0.5 flex flex-wrap items-center gap-x-2 gap-y-0.5 text-xs">
                                <button
                                    type="button"
                                    onClick={() => {
                                        if (!studentNumber) return;
                                        void navigator.clipboard.writeText(String(studentNumber));
                                        toast.success("Copied Student ID");
                                    }}
                                    className="text-foreground hover:text-primary inline-flex items-center gap-1 font-mono font-medium transition-colors"
                                    title="Copy Student ID"
                                >
                                    ID {studentNumber ?? "—"}
                                    <Copy className="size-3 opacity-60" aria-hidden="true" />
                                </button>
                                <span>·</span>
                                <span className="truncate">
                                    {courseCode ?? "Unassigned"}
                                    {academicYear ? ` · Year ${academicYear}` : ""}
                                </span>
                            </SheetDescription>
                            {courseTitle && <p className="text-muted-foreground mt-0.5 truncate text-xs">{courseTitle}</p>}
                        </div>
                    </div>

                    {/* Quick contact & profile links */}
                    <div className="mt-3 flex flex-wrap items-center gap-1.5">
                        {detail?.student.email && (
                            <a
                                href={`mailto:${detail.student.email}`}
                                className={buttonVariants({ variant: "outline", size: "xs", className: "h-7 gap-1.5 text-xs" })}
                            >
                                <Mail className="size-3" aria-hidden="true" />
                                Email
                            </a>
                        )}
                        {detail?.student.phone && (
                            <a
                                href={`tel:${detail.student.phone.replace(/\s+/g, "")}`}
                                className={buttonVariants({ variant: "outline", size: "xs", className: "h-7 gap-1.5 text-xs" })}
                            >
                                <Phone className="size-3" aria-hidden="true" />
                                Call
                            </a>
                        )}
                        {detail?.student.student_url && (
                            <Link
                                href={detail.student.student_url}
                                className={buttonVariants({ variant: "outline", size: "xs", className: "h-7 gap-1.5 text-xs" })}
                            >
                                <UserRound className="size-3" aria-hidden="true" />
                                Student profile
                            </Link>
                        )}
                        <Button
                            type="button"
                            variant="outline"
                            size="xs"
                            className="h-7 gap-1.5 text-xs"
                            disabled={isTrashed || busyAction !== null}
                            onClick={handleResendAssessment}
                        >
                            {busyAction === "resend" ? <Loader2 className="size-3 animate-spin" /> : <Send className="size-3" />}
                            Resend assessment
                        </Button>
                        {statusMeta.isCompleted && enrollmentId && (
                            <a
                                href={detail?.enrollment.assessment_download_url ?? route("assessment.download", { record: enrollmentId }, false)}
                                target="_blank"
                                rel="noreferrer"
                                className={buttonVariants({ variant: "outline", size: "xs", className: "h-7 gap-1.5 text-xs" })}
                            >
                                <Download className="size-3" aria-hidden="true" />
                                Assessment PDF
                            </a>
                        )}
                    </div>
                </SheetHeader>

                {/* Scrollable Dossier Body */}
                <div className="flex-1 space-y-5 overflow-y-auto px-5 py-5">
                    {error ? (
                        <Alert variant="destructive">
                            <AlertCircle className="size-4" />
                            <AlertTitle>Unable to load full details</AlertTitle>
                            <AlertDescription className="flex items-center justify-between gap-3">
                                <span>{error}</span>
                                <Button type="button" size="xs" variant="outline" onClick={() => void loadSummary()}>
                                    Retry
                                </Button>
                            </AlertDescription>
                        </Alert>
                    ) : null}

                    {/* 1. Dynamic N-Step ReUI Pipeline Stepper + Adaptive Action Console */}
                    <Frame spacing="sm">
                        <FrameHeader className="flex-row items-center justify-between gap-2">
                            <div>
                                <FrameTitle className="text-xs font-semibold tracking-wide uppercase">Workflow pipeline</FrameTitle>
                                <FrameDescription className="text-xs">
                                    {statusMeta.isCancelled
                                        ? "This enrollment was cancelled."
                                        : statusMeta.isCompleted
                                          ? "All pipeline verifications are complete."
                                          : isPolicyRuntime && policyTransitions.length > 0
                                            ? `${policyTransitions.length} available transition${policyTransitions.length === 1 ? "" : "s"}`
                                            : nextStep
                                              ? `Next step: ${nextStep.label || nextStep.status}`
                                              : "Awaiting status update"}
                                </FrameDescription>
                            </div>
                            <div className="flex items-center gap-1">
                                {!isTrashed && !statusMeta.isCancelled && (canUndoDept || canUndoCashier) && (
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="xs"
                                        className="text-muted-foreground hover:text-foreground h-7 gap-1 text-xs"
                                        disabled={busyAction !== null}
                                        onClick={canUndoCashier ? handleUndoCashier : handleUndoDept}
                                    >
                                        <Undo2 className="size-3" />
                                        Undo step
                                    </Button>
                                )}
                                {canCancel && (
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="xs"
                                        className="text-muted-foreground hover:text-destructive h-7 gap-1 text-xs"
                                        onClick={() => setCancelOpen((prev) => !prev)}
                                    >
                                        <Ban className="size-3" />
                                        Cancel
                                    </Button>
                                )}
                                {canReopen && (
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="xs"
                                        className="h-7 gap-1 text-xs"
                                        onClick={() => setReopenOpen((prev) => !prev)}
                                    >
                                        <RotateCcw className="size-3" />
                                        Reopen
                                    </Button>
                                )}
                            </div>
                        </FrameHeader>
                        <FramePanel className="space-y-4">
                            {steps.length > 0 && (
                                <div className={cn(steps.length > 4 && "overflow-x-auto pb-1")}>
                                    <Stepper
                                        value={activeStepNumber}
                                        indicators={{
                                            completed: <Check className="size-3.5" />,
                                            loading: <Loader2 className="size-3.5 animate-spin" />,
                                        }}
                                    >
                                        <StepperNav
                                            className="grid w-full gap-2"
                                            style={{
                                                gridTemplateColumns:
                                                    steps.length > 4
                                                        ? `repeat(${steps.length}, minmax(110px, 1fr))`
                                                        : `repeat(${steps.length}, minmax(0, 1fr))`,
                                            }}
                                        >
                                            {steps.map((step, index) => {
                                                const stepNum = index + 1;
                                                const completed = statusMeta.isCompleted || stepNum < activeStepNumber;
                                                return (
                                                    <StepperItem
                                                        key={step.key || step.status}
                                                        step={stepNum}
                                                        completed={completed}
                                                        className="relative flex !flex-col !items-start gap-1.5"
                                                    >
                                                        <div className="flex w-full items-center gap-1.5">
                                                            <StepperTrigger className="pointer-events-none shrink-0 gap-1.5" tabIndex={-1}>
                                                                <StepperIndicator className="size-5 text-[10px]">{stepNum}</StepperIndicator>
                                                            </StepperTrigger>
                                                            {index < steps.length - 1 && (
                                                                <StepperSeparator className="flex-1 group-data-[state=completed]/step:bg-emerald-500" />
                                                            )}
                                                        </div>
                                                        <StepperTitle
                                                            className="w-full truncate text-[11px] leading-tight font-medium"
                                                            title={step.label || step.status}
                                                        >
                                                            {step.label || step.status}
                                                        </StepperTitle>
                                                    </StepperItem>
                                                );
                                            })}
                                        </StepperNav>
                                    </Stepper>
                                </div>
                            )}

                            {/* Policy v1 Multi-Branch Transitions OR Legacy Sequential Primary Action */}
                            {!isTrashed && !statusMeta.isCancelled && isPolicyRuntime && policyTransitions.length > 0 && (
                                <div className="space-y-2.5 border-t pt-3">
                                    <div className="flex flex-wrap gap-2">
                                        {policyTransitions.map((transition, index) => (
                                            <Button
                                                key={transition.key}
                                                type="button"
                                                variant={index === 0 ? "default" : "outline"}
                                                size="sm"
                                                className={cn("justify-between gap-2", policyTransitions.length === 1 && "w-full")}
                                                disabled={busyAction !== null}
                                                onClick={() => handlePolicyTransitionClick(transition)}
                                            >
                                                <span className="inline-flex items-center gap-1.5">
                                                    {busyAction === `transition:${transition.key}` ? (
                                                        <Loader2 className="size-3.5 animate-spin" />
                                                    ) : transition.needs_payment ? (
                                                        <CreditCard className="size-3.5" />
                                                    ) : (
                                                        <ShieldCheck className="size-3.5" />
                                                    )}
                                                    {transition.label}
                                                </span>
                                                <ArrowRight className="size-3.5" />
                                            </Button>
                                        ))}
                                    </div>

                                    {/* Reason prompt when a policy transition requires a reason */}
                                    {pendingReasonTransition && (
                                        <form
                                            onSubmit={(e) => {
                                                e.preventDefault();
                                                if (!transitionReason.trim()) {
                                                    toast.error("Please enter a reason for this transition.");
                                                    return;
                                                }
                                                void executePolicyTransition(pendingReasonTransition, transitionReason.trim());
                                            }}
                                            className="bg-muted/35 space-y-2.5 rounded-lg border p-3"
                                        >
                                            <Label htmlFor="sheet-tr-reason" className="text-xs font-semibold">
                                                Reason for “{pendingReasonTransition.label}”
                                            </Label>
                                            <Input
                                                id="sheet-tr-reason"
                                                value={transitionReason}
                                                onChange={(e) => setTransitionReason(e.target.value)}
                                                placeholder="Explain why this transition is being applied..."
                                                className="h-8 text-xs"
                                            />
                                            <div className="flex justify-end gap-2">
                                                <Button type="button" variant="ghost" size="xs" onClick={() => setPendingReasonTransition(null)}>
                                                    Cancel
                                                </Button>
                                                <Button type="submit" size="xs" disabled={busyAction !== null}>
                                                    Confirm transition
                                                </Button>
                                            </div>
                                        </form>
                                    )}
                                </div>
                            )}

                            {!isTrashed && !statusMeta.isCancelled && !isPolicyRuntime && nextStep && canAdvance && (
                                <div className="space-y-3 border-t pt-3">
                                    <Button
                                        type="button"
                                        className="w-full justify-between"
                                        disabled={busyAction !== null}
                                        onClick={handlePrimaryLegacyAdvance}
                                    >
                                        <span className="inline-flex items-center gap-2">
                                            {busyAction === "advance" ? (
                                                <Loader2 className="size-4 animate-spin" />
                                            ) : nextStepNeedsCashier ? (
                                                <CreditCard className="size-4" />
                                            ) : (
                                                <ShieldCheck className="size-4" />
                                            )}
                                            {nextStepNeedsCashier
                                                ? cashierOpen
                                                    ? "Hide Cashier Payment Form"
                                                    : "Record Payment & Verify Cashier"
                                                : nextStepIsDept
                                                  ? "Verify as Department Head"
                                                  : `Advance to ${nextStep.status}`}
                                        </span>
                                        <ArrowRight className="size-4" />
                                    </Button>
                                </div>
                            )}

                            {/* Inline Cashier Payment Form (shared by Legacy & Policy v1 payment steps) */}
                            {!isTrashed && !statusMeta.isCancelled && cashierOpen && (
                                <form onSubmit={handleCashierSubmit} className="bg-muted/35 space-y-3 rounded-lg border p-3.5">
                                    <div className="flex items-center justify-between gap-2">
                                        <p className="text-xs font-semibold">Quick Cashier Verification</p>
                                        {tuitionBalance !== null && tuitionBalance > 0 && (
                                            <span className="text-muted-foreground font-mono text-[11px] tabular-nums">
                                                Due: {formatMoney(tuitionBalance, currency)}
                                            </span>
                                        )}
                                    </div>
                                    <div className="grid grid-cols-1 gap-2.5 sm:grid-cols-2">
                                        <div className="space-y-1">
                                            <Label htmlFor="sheet-invoice" className="text-xs">
                                                OR / Receipt #{isPolicyRuntime ? " (optional)" : ""}
                                            </Label>
                                            <Input
                                                id="sheet-invoice"
                                                value={invoiceNumber}
                                                onChange={(e) => setInvoiceNumber(e.target.value)}
                                                placeholder="e.g. OR-2026-00412"
                                                className="h-8 text-xs"
                                            />
                                        </div>
                                        <div className="space-y-1">
                                            <Label htmlFor="sheet-method" className="text-xs">
                                                Payment method
                                            </Label>
                                            <Select value={paymentMethod} onValueChange={setPaymentMethod}>
                                                <SelectTrigger id="sheet-method" className="h-8 text-xs">
                                                    <SelectValue />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    {paymentMethods.map((method) => (
                                                        <SelectItem key={method} value={method}>
                                                            {method}
                                                        </SelectItem>
                                                    ))}
                                                </SelectContent>
                                            </Select>
                                        </div>
                                    </div>

                                    <div className="grid grid-cols-2 gap-2">
                                        <div className="space-y-1">
                                            <Label htmlFor="sheet-tuition-fee" className="text-[11px]">
                                                Tuition
                                            </Label>
                                            <Input
                                                id="sheet-tuition-fee"
                                                type="number"
                                                min="0"
                                                step="0.01"
                                                value={tuitionFee}
                                                onChange={(e) => setTuitionFee(e.target.value)}
                                                className="h-8 font-mono text-xs tabular-nums"
                                            />
                                        </div>
                                        <div className="space-y-1">
                                            <Label htmlFor="sheet-reg-fee" className="text-[11px]">
                                                Registration
                                            </Label>
                                            <Input
                                                id="sheet-reg-fee"
                                                type="number"
                                                min="0"
                                                step="0.01"
                                                value={registrationFee}
                                                onChange={(e) => setRegistrationFee(e.target.value)}
                                                className="h-8 font-mono text-xs tabular-nums"
                                            />
                                        </div>
                                        <div className="space-y-1">
                                            <Label htmlFor="sheet-misc-fee" className="text-[11px]">
                                                Miscellaneous
                                            </Label>
                                            <Input
                                                id="sheet-misc-fee"
                                                type="number"
                                                min="0"
                                                step="0.01"
                                                value={miscFee}
                                                onChange={(e) => setMiscFee(e.target.value)}
                                                className="h-8 font-mono text-xs tabular-nums"
                                            />
                                        </div>
                                        <div className="space-y-1">
                                            <Label htmlFor="sheet-other-fee" className="text-[11px]">
                                                Others
                                            </Label>
                                            <Input
                                                id="sheet-other-fee"
                                                type="number"
                                                min="0"
                                                step="0.01"
                                                value={otherFee}
                                                onChange={(e) => setOtherFee(e.target.value)}
                                                className="h-8 font-mono text-xs tabular-nums"
                                            />
                                        </div>
                                    </div>

                                    <div className="flex items-center justify-end gap-2 pt-1">
                                        <Button type="button" variant="ghost" size="xs" onClick={() => setCashierOpen(false)}>
                                            Cancel
                                        </Button>
                                        <Button type="submit" size="xs" disabled={busyAction !== null} className="gap-1.5">
                                            {busyAction === "cashier" && <Loader2 className="size-3 animate-spin" />}
                                            Confirm &amp; Enroll
                                        </Button>
                                    </div>
                                </form>
                            )}

                            {/* Inline Cancel Form */}
                            {cancelOpen && canCancel && (
                                <form
                                    onSubmit={handleCancelSubmit}
                                    className="border-destructive/30 bg-destructive/5 space-y-2.5 rounded-lg border p-3"
                                >
                                    <p className="text-destructive text-xs font-semibold">Cancel enrollment</p>
                                    <Input
                                        value={cancelReason}
                                        onChange={(e) => setCancelReason(e.target.value)}
                                        placeholder="Reason for cancellation (required)..."
                                        className="h-8 text-xs"
                                    />
                                    {hasOutstandingPayments && (
                                        <label className="flex items-start gap-2 text-xs">
                                            <Checkbox
                                                checked={acknowledgePayments}
                                                onCheckedChange={(v) => setAcknowledgePayments(Boolean(v))}
                                                className="mt-0.5"
                                            />
                                            <span className="text-muted-foreground">
                                                Acknowledge recorded payments ({formatMoney(outstandingPayments?.amount ?? 0, currency)}) remain for
                                                finance review.
                                            </span>
                                        </label>
                                    )}
                                    <div className="flex justify-end gap-2">
                                        <Button type="button" variant="ghost" size="xs" onClick={() => setCancelOpen(false)}>
                                            Dismiss
                                        </Button>
                                        <Button type="submit" variant="destructive" size="xs" disabled={busyAction !== null}>
                                            {busyAction === "cancel" && <Loader2 className="mr-1 size-3 animate-spin" />}
                                            Confirm cancel
                                        </Button>
                                    </div>
                                </form>
                            )}

                            {/* Inline Reopen Form */}
                            {reopenOpen && canReopen && (
                                <form onSubmit={handleReopenSubmit} className="bg-muted/40 space-y-2.5 rounded-lg border p-3">
                                    <p className="text-xs font-semibold">Reopen cancelled enrollment</p>
                                    <Input
                                        value={reopenReason}
                                        onChange={(e) => setReopenReason(e.target.value)}
                                        placeholder="Reason for reopening (required)..."
                                        className="h-8 text-xs"
                                    />
                                    <div className="flex justify-end gap-2">
                                        <Button type="button" variant="ghost" size="xs" onClick={() => setReopenOpen(false)}>
                                            Dismiss
                                        </Button>
                                        <Button type="submit" size="xs" disabled={busyAction !== null}>
                                            {busyAction === "reopen" && <Loader2 className="mr-1 size-3 animate-spin" />}
                                            Reopen enrollment
                                        </Button>
                                    </div>
                                </form>
                            )}
                        </FramePanel>
                    </Frame>

                    {/* 2. Tuition & Financial Snapshot */}
                    <Frame spacing="sm">
                        <FrameHeader className="flex-row items-center justify-between gap-2">
                            <div className="flex items-center gap-2">
                                <IconTile variant="soft" size="xs" className="text-emerald-600 dark:text-emerald-400">
                                    <Receipt />
                                </IconTile>
                                <FrameTitle className="text-xs font-semibold tracking-wide uppercase">Tuition &amp; balance</FrameTitle>
                            </div>
                            <Badge variant={tuitionBalance && tuitionBalance > 0 ? "warning-light" : "success-light"} radius="full" size="sm">
                                {tuitionBalance && tuitionBalance > 0 ? `${settlementPct}% settled` : "Cleared"}
                            </Badge>
                        </FrameHeader>
                        <FramePanel className="space-y-3">
                            <div className="grid grid-cols-3 gap-2">
                                <div>
                                    <p className="text-muted-foreground text-[11px]">Overall</p>
                                    <p className="mt-0.5 text-sm font-semibold tabular-nums">{formatMoney(tuitionOverall, currency)}</p>
                                </div>
                                <div>
                                    <p className="text-muted-foreground text-[11px]">Paid</p>
                                    <p className="mt-0.5 text-sm font-semibold text-emerald-600 tabular-nums dark:text-emerald-400">
                                        {formatMoney(tuitionPaid, currency)}
                                    </p>
                                </div>
                                <div>
                                    <p className="text-muted-foreground text-[11px]">Balance</p>
                                    <p
                                        className={cn(
                                            "mt-0.5 text-sm font-semibold tabular-nums",
                                            tuitionBalance && tuitionBalance > 0
                                                ? "text-amber-600 dark:text-amber-400"
                                                : "text-emerald-600 dark:text-emerald-400",
                                        )}
                                    >
                                        {tuitionBalance && tuitionBalance > 0 ? formatMoney(tuitionBalance, currency) : "Paid in full"}
                                    </p>
                                </div>
                            </div>
                            <Progress value={settlementPct} className="h-1.5" />

                            {detail?.transactions && detail.transactions.length > 0 && (
                                <div className="space-y-1.5 border-t pt-2.5">
                                    <p className="text-muted-foreground text-[11px] font-medium">Recent receipts</p>
                                    {detail.transactions.slice(0, 3).map((tx) => (
                                        <div key={tx.id} className="flex items-center justify-between gap-2 text-xs">
                                            <span className="text-muted-foreground truncate font-mono">
                                                {tx.invoicenumber || `TX-${tx.id}`} · {formatShortDate(tx.created_at)}
                                            </span>
                                            <span className="font-medium tabular-nums">{formatMoney(tx.amount, currency)}</span>
                                        </div>
                                    ))}
                                </div>
                            )}
                        </FramePanel>
                    </Frame>

                    {/* 3. Academic Load & Subjects */}
                    {isLoading && !detail ? (
                        <div className="space-y-3">
                            <Skeleton className="h-24 w-full rounded-xl" />
                            <Skeleton className="h-36 w-full rounded-xl" />
                        </div>
                    ) : detail ? (
                        <>
                            <Frame spacing="sm">
                                <Collapsible open={subjectsOpen} onOpenChange={setSubjectsOpen}>
                                    <FrameHeader className="flex-row items-center justify-between gap-2">
                                        <div className="flex items-center gap-2">
                                            <IconTile variant="soft" size="xs" className="text-blue-600 dark:text-blue-400">
                                                <BookOpen />
                                            </IconTile>
                                            <div>
                                                <FrameTitle className="text-xs font-semibold tracking-wide uppercase">
                                                    Subjects &amp; classes
                                                </FrameTitle>
                                                <FrameDescription className="text-xs tabular-nums">
                                                    {detail.subjects.length} subject{detail.subjects.length === 1 ? "" : "s"} · {detail.total_units}{" "}
                                                    units · {detail.class_enrollments_count} assigned class
                                                    {detail.class_enrollments_count === 1 ? "" : "es"}
                                                </FrameDescription>
                                            </div>
                                        </div>
                                        <div className="flex items-center gap-1.5">
                                            {detail.missing_classes_count > 0 && (
                                                <Badge variant="warning-light" radius="full" size="sm" className="gap-1">
                                                    <AlertTriangle className="size-3" />
                                                    {detail.missing_classes_count} unassigned
                                                </Badge>
                                            )}
                                            {detail.subjects.length > 0 && (
                                                <CollapsibleTrigger asChild>
                                                    <Button type="button" variant="ghost" size="xs" className="h-7 gap-1 text-xs">
                                                        {subjectsOpen ? "Hide" : "Inspect"}
                                                        <ChevronDown
                                                            className={cn("size-3.5 transition-transform", subjectsOpen && "rotate-180")}
                                                            aria-hidden="true"
                                                        />
                                                    </Button>
                                                </CollapsibleTrigger>
                                            )}
                                        </div>
                                    </FrameHeader>
                                    <CollapsibleContent>
                                        <FramePanel className="divide-y p-0">
                                            {detail.subjects.map((subject) => (
                                                <div key={subject.id} className="flex items-center justify-between gap-3 px-3 py-2 text-xs">
                                                    <div className="min-w-0">
                                                        <p className="font-medium">{subject.code}</p>
                                                        <p className="text-muted-foreground truncate text-[11px]">{subject.title}</p>
                                                    </div>
                                                    <Badge variant="outline" size="sm" className="shrink-0 tabular-nums">
                                                        {subject.units} u
                                                    </Badge>
                                                </div>
                                            ))}
                                        </FramePanel>
                                    </CollapsibleContent>
                                </Collapsible>
                            </Frame>

                            {/* 4. Interactive Requirements Checklist */}
                            {detail.requirements.length > 0 && (
                                <Frame spacing="sm">
                                    <FrameHeader>
                                        <FrameTitle className="text-xs font-semibold tracking-wide uppercase">
                                            Requirements ({detail.requirements.filter((r) => r.status === "verified" || r.status === "waived").length}
                                            /{detail.requirements.length})
                                        </FrameTitle>
                                    </FrameHeader>
                                    <FramePanel className="divide-y p-0">
                                        {detail.requirements.map((req) => {
                                            const isCleared = req.status === "verified" || req.status === "waived";
                                            const isReqBusy = busyAction === `req:${req.id}`;
                                            return (
                                                <div key={req.id} className="flex items-center justify-between gap-2 px-3 py-2 text-xs">
                                                    <div className="min-w-0 flex-1">
                                                        <span className="truncate font-medium">{req.label}</span>
                                                        {req.required && <span className="text-muted-foreground ml-1.5 text-[10px]">(Required)</span>}
                                                    </div>
                                                    <div className="flex shrink-0 items-center gap-1.5">
                                                        <Badge
                                                            variant={
                                                                isCleared
                                                                    ? "success-light"
                                                                    : req.status === "rejected"
                                                                      ? "destructive-light"
                                                                      : "warning-light"
                                                            }
                                                            radius="full"
                                                            size="sm"
                                                            className="capitalize"
                                                        >
                                                            {req.status}
                                                        </Badge>
                                                        {canReviewRequirements && !isCleared && (
                                                            <>
                                                                <Button
                                                                    type="button"
                                                                    size="xs"
                                                                    variant="secondary"
                                                                    disabled={isReqBusy}
                                                                    className="h-6 px-2 text-[11px]"
                                                                    onClick={() => void handleReviewRequirement(req.id, "verify")}
                                                                >
                                                                    {isReqBusy ? <Loader2 className="size-3 animate-spin" /> : "Verify"}
                                                                </Button>
                                                                <Button
                                                                    type="button"
                                                                    size="xs"
                                                                    variant="ghost"
                                                                    disabled={isReqBusy}
                                                                    className="text-muted-foreground h-6 px-1.5 text-[11px]"
                                                                    onClick={() => void handleReviewRequirement(req.id, "waive")}
                                                                >
                                                                    Waive
                                                                </Button>
                                                            </>
                                                        )}
                                                    </div>
                                                </div>
                                            );
                                        })}
                                    </FramePanel>
                                </Frame>
                            )}

                            {/* 4b. Cross-Office Student Holds & Clearances */}
                            {detail.student.id && (
                                <Frame spacing="sm">
                                    <FrameHeader className="flex-row items-center justify-between gap-2">
                                        <div className="flex items-center gap-2">
                                            <IconTile
                                                variant="soft"
                                                size="xs"
                                                className={
                                                    (detail.holds ?? []).some((h) => h.status === "active")
                                                        ? "text-destructive"
                                                        : "text-emerald-600 dark:text-emerald-400"
                                                }
                                            >
                                                <ShieldAlert />
                                            </IconTile>
                                            <div>
                                                <FrameTitle className="text-xs font-semibold tracking-wide uppercase">
                                                    Holds &amp; clearances ({(detail.holds ?? []).filter((h) => h.status === "active").length} active)
                                                </FrameTitle>
                                                <FrameDescription className="text-xs">
                                                    Cross-office holds blocking enrollment or add/drop
                                                </FrameDescription>
                                            </div>
                                        </div>
                                        <div className="flex items-center gap-1">
                                            <Link
                                                href={holdsIndexRoute.url()}
                                                className={buttonVariants({ variant: "ghost", size: "xs", className: "h-7 text-xs" })}
                                            >
                                                All holds
                                            </Link>
                                            <Button
                                                type="button"
                                                size="xs"
                                                variant="outline"
                                                className="h-7 gap-1 text-xs"
                                                onClick={() => setPlaceHoldOpen((prev) => !prev)}
                                            >
                                                <Plus className="size-3" />
                                                Place hold
                                            </Button>
                                        </div>
                                    </FrameHeader>
                                    <FramePanel className="space-y-2.5 p-3">
                                        {placeHoldOpen && (
                                            <div className="bg-muted/40 space-y-2 rounded-lg border p-3">
                                                <div className="grid grid-cols-2 gap-2">
                                                    <Select value={holdOffice} onValueChange={setHoldOffice}>
                                                        <SelectTrigger className="h-7 text-xs">
                                                            <SelectValue />
                                                        </SelectTrigger>
                                                        <SelectContent>
                                                            {(detail.hold_offices ?? [{ value: "registrar", label: "Registrar Office" }]).map(
                                                                (o) => (
                                                                    <SelectItem key={o.value} value={o.value}>
                                                                        {o.label}
                                                                    </SelectItem>
                                                                ),
                                                            )}
                                                        </SelectContent>
                                                    </Select>
                                                    <Select
                                                        value={holdSeverity}
                                                        onValueChange={(v) => setHoldSeverity(v as "blocking" | "warning")}
                                                    >
                                                        <SelectTrigger className="h-7 text-xs">
                                                            <SelectValue />
                                                        </SelectTrigger>
                                                        <SelectContent>
                                                            <SelectItem value="blocking">Blocking</SelectItem>
                                                            <SelectItem value="warning">Warning</SelectItem>
                                                        </SelectContent>
                                                    </Select>
                                                </div>
                                                <Input
                                                    value={holdTitle}
                                                    onChange={(e) => setHoldTitle(e.target.value)}
                                                    placeholder="Hold title (e.g. Overdue library book)"
                                                    className="h-7 text-xs"
                                                />
                                                <Input
                                                    value={holdReason}
                                                    onChange={(e) => setHoldReason(e.target.value)}
                                                    placeholder="Reason & clearance requirement"
                                                    className="h-7 text-xs"
                                                />
                                                <div className="flex justify-end gap-1.5">
                                                    <Button type="button" size="xs" variant="ghost" onClick={() => setPlaceHoldOpen(false)}>
                                                        Cancel
                                                    </Button>
                                                    <Button
                                                        type="button"
                                                        size="xs"
                                                        disabled={busyAction !== null || !holdTitle.trim() || !holdReason.trim()}
                                                        onClick={async () => {
                                                            if (!detail.student.id) return;
                                                            setBusyAction("hold:place");
                                                            try {
                                                                await axios.post(storeHoldRoute.url(), {
                                                                    student_id: detail.student.id,
                                                                    office: holdOffice,
                                                                    severity: holdSeverity,
                                                                    title: holdTitle.trim(),
                                                                    reason: holdReason.trim(),
                                                                    blocks_enrollment: true,
                                                                    blocks_add_drop: true,
                                                                });
                                                                toast.success("Hold placed on student.");
                                                                setHoldTitle("");
                                                                setHoldReason("");
                                                                setPlaceHoldOpen(false);
                                                                await loadSummary();
                                                                onMutationSuccess?.();
                                                            } catch {
                                                                toast.error("Failed to place hold.");
                                                            } finally {
                                                                setBusyAction(null);
                                                            }
                                                        }}
                                                    >
                                                        {busyAction === "hold:place" && <Loader2 className="mr-1 size-3 animate-spin" />}
                                                        Save hold
                                                    </Button>
                                                </div>
                                            </div>
                                        )}

                                        {(detail.holds ?? []).length === 0 ? (
                                            <p className="text-muted-foreground text-xs">No active or recent holds on this student.</p>
                                        ) : (
                                            <div className="divide-y">
                                                {(detail.holds ?? []).map((hold) => (
                                                    <div key={hold.id} className="space-y-1.5 py-2 first:pt-0 last:pb-0">
                                                        <div className="flex items-start justify-between gap-2">
                                                            <div className="min-w-0">
                                                                <div className="flex flex-wrap items-center gap-1.5">
                                                                    <span className="text-xs font-semibold">{hold.title}</span>
                                                                    <Badge variant="outline" size="xs" radius="full">
                                                                        {hold.office_label}
                                                                    </Badge>
                                                                    <Badge
                                                                        variant={
                                                                            hold.status === "released"
                                                                                ? "success-light"
                                                                                : hold.status === "overridden"
                                                                                  ? "info-light"
                                                                                  : hold.severity === "blocking"
                                                                                    ? "destructive-light"
                                                                                    : "warning-light"
                                                                        }
                                                                        size="xs"
                                                                        radius="full"
                                                                        className="capitalize"
                                                                    >
                                                                        {hold.status === "active" ? hold.severity : hold.status}
                                                                    </Badge>
                                                                </div>
                                                                <p className="text-muted-foreground mt-0.5 text-[11px]">{hold.reason}</p>
                                                                {hold.override_reason && (
                                                                    <p className="text-[11px] text-blue-600 dark:text-blue-400">
                                                                        Override ({hold.overridden_by}): {hold.override_reason}
                                                                    </p>
                                                                )}
                                                            </div>
                                                            {hold.status === "active" && (
                                                                <div className="flex shrink-0 items-center gap-1">
                                                                    <Button
                                                                        type="button"
                                                                        size="xs"
                                                                        variant="outline"
                                                                        className="h-6 gap-1 px-2 text-[11px]"
                                                                        disabled={busyAction !== null}
                                                                        onClick={async () => {
                                                                            setBusyAction(`hold:release:${hold.id}`);
                                                                            try {
                                                                                await axios.post(releaseHoldRoute.url(hold.id), {});
                                                                                toast.success("Hold released.");
                                                                                await loadSummary();
                                                                                onMutationSuccess?.();
                                                                            } catch {
                                                                                toast.error("Failed to release hold.");
                                                                            } finally {
                                                                                setBusyAction(null);
                                                                            }
                                                                        }}
                                                                    >
                                                                        <Unlock className="size-3" />
                                                                        Release
                                                                    </Button>
                                                                    <Button
                                                                        type="button"
                                                                        size="xs"
                                                                        variant="ghost"
                                                                        className="text-muted-foreground h-6 gap-1 px-1.5 text-[11px]"
                                                                        onClick={() => {
                                                                            setOverrideHoldId(hold.id);
                                                                            setOverrideHoldReason("");
                                                                        }}
                                                                    >
                                                                        <ShieldOff className="size-3" />
                                                                        Override
                                                                    </Button>
                                                                </div>
                                                            )}
                                                        </div>
                                                        {overrideHoldId === hold.id && (
                                                            <div className="flex items-center gap-1.5 pt-1">
                                                                <Input
                                                                    value={overrideHoldReason}
                                                                    onChange={(e) => setOverrideHoldReason(e.target.value)}
                                                                    placeholder="Audit reason for override..."
                                                                    className="h-7 flex-1 text-xs"
                                                                />
                                                                <Button
                                                                    type="button"
                                                                    size="xs"
                                                                    variant="destructive"
                                                                    disabled={busyAction !== null || overrideHoldReason.trim().length < 5}
                                                                    onClick={async () => {
                                                                        setBusyAction(`hold:override:${hold.id}`);
                                                                        try {
                                                                            await axios.post(overrideHoldRoute.url(hold.id), {
                                                                                override_reason: overrideHoldReason.trim(),
                                                                            });
                                                                            toast.success("Hold overridden with audit log.");
                                                                            setOverrideHoldId(null);
                                                                            await loadSummary();
                                                                            onMutationSuccess?.();
                                                                        } catch {
                                                                            toast.error("Failed to override hold.");
                                                                        } finally {
                                                                            setBusyAction(null);
                                                                        }
                                                                    }}
                                                                >
                                                                    Confirm
                                                                </Button>
                                                            </div>
                                                        )}
                                                    </div>
                                                ))}
                                            </div>
                                        )}
                                    </FramePanel>
                                </Frame>
                            )}

                            {/* 5. ReUI Activity & Handoff Timeline */}
                            {detail.timeline.length > 0 && (
                                <section className="space-y-3">
                                    <div className="flex items-center justify-between">
                                        <h3 className="text-muted-foreground text-[11px] font-semibold tracking-[0.08em] uppercase">
                                            Activity timeline
                                        </h3>
                                        <span className="text-muted-foreground text-[11px] tabular-nums">{detail.timeline.length} events</span>
                                    </div>
                                    <Timeline defaultValue={detail.timeline.length}>
                                        {detail.timeline.map((event, idx) => (
                                            <TimelineItem key={event.id} step={idx + 1} className="not-last:pb-4">
                                                <TimelineHeader>
                                                    <TimelineSeparator />
                                                    <TimelineDate className="text-[11px]">{event.date_label}</TimelineDate>
                                                    <TimelineTitle className="text-xs font-semibold">{event.title}</TimelineTitle>
                                                    <TimelineIndicator
                                                        className={cn(
                                                            "flex size-4 items-center justify-center border",
                                                            event.tone === "success"
                                                                ? "border-emerald-500 bg-emerald-500/15 text-emerald-600 dark:text-emerald-400"
                                                                : event.tone === "destructive"
                                                                  ? "border-rose-500 bg-rose-500/15 text-rose-600 dark:text-rose-400"
                                                                  : "border-blue-500 bg-blue-500/15 text-blue-600 dark:text-blue-400",
                                                        )}
                                                    >
                                                        {event.tone === "success" ? <CheckCircle2 className="size-2.5" /> : null}
                                                    </TimelineIndicator>
                                                </TimelineHeader>
                                                <TimelineContent className="text-xs">{event.description}</TimelineContent>
                                            </TimelineItem>
                                        ))}
                                    </Timeline>
                                </section>
                            )}
                        </>
                    ) : null}
                </div>

                {/* Sticky Sheet Footer */}
                <SheetFooter className="border-border/70 bg-card/60 shrink-0 flex-row items-center justify-between gap-2 border-t px-5 py-3">
                    <div className="flex items-center gap-1.5">
                        {enrollment &&
                            (isTrashed ? (
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    className="gap-1.5 text-emerald-600 hover:text-emerald-700"
                                    onClick={() => onRestore?.(enrollment)}
                                >
                                    <RotateCcw className="size-3.5" />
                                    Restore
                                </Button>
                            ) : (
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="sm"
                                    className="text-muted-foreground hover:text-destructive gap-1.5"
                                    onClick={() => onDelete?.(enrollment)}
                                >
                                    <Trash2 className="size-3.5" />
                                    Delete
                                </Button>
                            ))}
                    </div>

                    <div className="flex items-center gap-2">
                        {enrollmentId && (
                            <>
                                <Link
                                    href={detail?.enrollment.edit_url ?? route("administrators.enrollments.edit", enrollmentId, false)}
                                    className={buttonVariants({ variant: "outline", size: "sm", className: "gap-1.5" })}
                                >
                                    <Pencil className="size-3.5" />
                                    Edit
                                </Link>
                                <Link
                                    href={detail?.enrollment.show_url ?? route("administrators.enrollments.show", enrollmentId, false)}
                                    className={buttonVariants({ variant: "default", size: "sm", className: "gap-1.5" })}
                                >
                                    <FileText className="size-3.5" />
                                    Full record
                                    <ArrowUpRight className="size-3.5" />
                                </Link>
                            </>
                        )}
                    </div>
                </SheetFooter>
            </SheetContent>
        </Sheet>
    );
}
