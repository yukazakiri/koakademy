import {
    advancePipelineStep,
    createAssessmentPdf,
    enrollInClass,
    notifyClassScheduleChanges,
    resendAssessment,
    retryEnrollment,
    show as showEnrollment,
    undoCashierVerification,
    undoHeadDeptVerification,
    verifyHeadDept,
} from "@/actions/App/Http/Controllers/AdministratorEnrollmentManagementController";
import {
    reviewRequirement,
    transition as transitionPolicyEnrollment,
} from "@/actions/App/Http/Controllers/AdministratorEnrollmentPolicyController";
import AdminLayout from "@/components/administrators/admin-layout";
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from "@/components/reui/alert-dialog";
import { Badge } from "@/components/reui/badge";
import { Frame, FramePanel } from "@/components/reui/frame";
import {
    Stepper,
    StepperIndicator,
    StepperItem,
    StepperNav,
    StepperSeparator,
    StepperTitle,
    StepperTrigger,
} from "@/components/reui/stepper";
import { Button } from "@/components/ui/button";
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from "@/components/ui/dialog";
import { Label } from "@/components/ui/label";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { Textarea } from "@/components/ui/textarea";
import { cn } from "@/lib/utils";
import type { User } from "@/types/user";
import { Head, router, usePage } from "@inertiajs/react";
import axios from "axios";
import {
    ArrowRight,
    BellRing,
    BookOpen,
    Check,
    CreditCard,
    FileText,
    History,
    Loader2,
    ShieldCheck,
} from "lucide-react";
import { useCallback, useEffect, useMemo, useRef, useState } from "react";
import { toast } from "sonner";
import { EnrollmentLoadChangeDialog } from "./enrollment-load-change-dialog";
import { EnrollmentTransferDialog } from "./enrollment-transfer-dialog";
import { resolvePipelineColor } from "./enrollment-utils";
import { BillingLedgerPanel } from "./show/billing-ledger-panel";
import { DocumentsStudioPanel } from "./show/documents-studio-panel";
import {
    CancelEnrollmentDialog,
    DeleteRestoreDialog,
    type DeleteRestoreDialogMode,
    EditTransactionDialog,
    QuickEnrollDialog,
    ReopenEnrollmentDialog,
    RestoreSubjectsDialog,
    VerifyCashierDialog,
} from "./show/enrollment-action-dialogs";
import { LoadSchedulePanel } from "./show/load-schedule-panel";
import { ReadinessActionRail } from "./show/readiness-action-rail";
import { RequirementsHoldsPanel } from "./show/requirements-holds-panel";
import { StageFocusHero } from "./show/stage-focus-hero";
import type {
    Branding,
    EnrollmentData,
    EnrollmentViewLayoutMode,
    ShowEnrollmentPageProps,
} from "./show/types";
import { UnifiedTimelinePanel } from "./show/unified-timeline-panel";
import { ViewGuardBanners } from "./show/view-guard-banners";
import {
    type ViewDialogKind,
    ViewHeaderBar,
    type ViewSimpleActionKind,
} from "./show/view-header-bar";
import { detectDraftScheduleConflicts } from "./workbench/schedule-conflicts";
import type { DraftSubjectEnrollment } from "./workbench/types";

const LAYOUT_STORAGE_KEY = "koakademy:enrollment-view-layout";

type DossierTab =
    | "load"
    | "billing"
    | "documents"
    | "requirements_holds"
    | "timeline";

function readInitialLayoutMode(): EnrollmentViewLayoutMode {
    if (typeof window === "undefined") {
        return "lifecycle";
    }
    const queryView = new URLSearchParams(window.location.search).get("view");
    if (queryView === "command" || queryView === "lifecycle") {
        return queryView;
    }
    try {
        const stored = window.localStorage.getItem(LAYOUT_STORAGE_KEY);
        if (stored === "command" || stored === "lifecycle") {
            return stored;
        }
    } catch {
        // Ignore storage restrictions
    }
    return "lifecycle";
}

function createIdempotencyKey(prefix: string): string {
    if (
        typeof crypto !== "undefined" &&
        typeof crypto.randomUUID === "function"
    ) {
        return `${prefix}:${crypto.randomUUID()}`;
    }
    return `${prefix}:${Date.now()}-${Math.random().toString(36).slice(2)}`;
}

function extractErrorMessage(error: unknown, fallback: string): string {
    if (axios.isAxiosError(error)) {
        const data = error.response?.data as
            | { message?: string; errors?: Record<string, string[] | string> }
            | undefined;
        if (data?.message) return data.message;
        const first = Object.values(data?.errors ?? {})[0];
        if (Array.isArray(first) && first[0]) return first[0];
        if (typeof first === "string" && first) return first;
    }
    return fallback;
}

export default function ShowEnrollment({
    user,
    enrollment,
    hold_offices,
    enrollment_period,
    scheduling_policy,
    scholarship_options,
    document_links,
    queue_navigation,
    flash,
    auth,
    recent_deletions = [],
    enrollment_pipeline,
}: ShowEnrollmentPageProps) {
    const page = usePage<{ branding?: Branding }>();
    const currency = page.props.branding?.currency || "PHP";

    const [layoutMode, setLayoutMode] =
        useState<EnrollmentViewLayoutMode>(readInitialLayoutMode);
    const [activeTab, setActiveTab] = useState<DossierTab>("load");
    const tabsAnchorRef = useRef<HTMLDivElement | null>(null);

    // Action in-flight states
    const [busyAction, setBusyAction] = useState<string | null>(null);
    const [enrollingClassId, setEnrollingClassId] = useState<number | null>(
        null,
    );
    const [reviewingRequirementId, setReviewingRequirementId] = useState<
        number | null
    >(null);

    // Controlled dialog states (zero dialogs nested inside DropdownMenus)
    const [verifyCashierOpen, setVerifyCashierOpen] = useState(false);
    const [quickEnrollOpen, setQuickEnrollOpen] = useState(false);
    const [cancelOpen, setCancelOpen] = useState(false);
    const [reopenOpen, setReopenOpen] = useState(false);
    const [restoreSubjectsOpen, setRestoreSubjectsOpen] = useState(false);
    const [deleteRestoreMode, setDeleteRestoreMode] =
        useState<DeleteRestoreDialogMode | null>(null);
    const [loadChangeOpen, setLoadChangeOpen] = useState(false);
    const [transferOpen, setTransferOpen] = useState(false);
    const [scheduleNotifyOpen, setScheduleNotifyOpen] = useState(false);
    const [undoCashierOpen, setUndoCashierOpen] = useState(false);
    const [editingTransaction, setEditingTransaction] = useState<
        EnrollmentData["transactions"][number] | null
    >(null);

    // Audited reason dialogs for policy transitions & requirement waive/reset
    const [pendingTransition, setPendingTransition] = useState<
        EnrollmentData["workflow"]["allowed_transitions"][number] | null
    >(null);
    const [transitionReason, setTransitionReason] = useState("");
    const [submittingTransition, setSubmittingTransition] = useState(false);

    const [requirementDialog, setRequirementDialog] = useState<{
        id: number;
        label: string;
        action: "waive" | "reset";
    } | null>(null);
    const [requirementReason, setRequirementReason] = useState("");

    // Surface Laravel flash messages reliably (never swallow errors!)
    const lastFlashKeyRef = useRef<string | null>(null);
    useEffect(() => {
        if (!flash) return;
        const key = JSON.stringify(flash);
        if (key === lastFlashKeyRef.current || key === "{}") return;
        lastFlashKeyRef.current = key;

        if (flash.error) {
            toast.error(flash.error);
        } else if (flash.warning) {
            toast.warning(flash.warning);
        } else if (flash.success) {
            toast.success(flash.success);
        } else if (flash.info) {
            toast.info(flash.info);
        }
    }, [flash]);

    const handleLayoutModeChange = useCallback(
        (next: EnrollmentViewLayoutMode) => {
            setLayoutMode(next);
            try {
                window.localStorage.setItem(LAYOUT_STORAGE_KEY, next);
            } catch {
                // Ignore storage errors
            }
        },
        [],
    );

    const handleJumpToTab = useCallback((target: string) => {
        const mapped: DossierTab =
            target === "billing" || target === "financial"
                ? "billing"
                : target === "documents" || target === "resources"
                  ? "documents"
                  : target === "holds" ||
                      target === "requirements" ||
                      target === "requirements_holds"
                    ? "requirements_holds"
                    : target === "timeline" || target === "changes"
                      ? "timeline"
                      : "load";

        setActiveTab(mapped);
        tabsAnchorRef.current?.scrollIntoView({
            behavior: "smooth",
            block: "start",
        });
    }, []);

    // Build DraftSubjectEnrollment adapter for ScheduleMatrixPanel & live conflict engine
    const draftSubjectsForMatrix = useMemo<DraftSubjectEnrollment[]>(() => {
        return enrollment.subjects_enrolled
            .filter((se) => se.subject_id !== null)
            .map((se) => {
                const sectionOption =
                    se.class_id !== null
                        ? {
                              id: se.class_id,
                              section: se.section ?? "Assigned",
                              faculty: se.faculty || "TBA",
                              enrolled_count: 0,
                              max_slots: 0,
                              available_slots: null,
                              is_full: false,
                              schedule: se.schedule || "TBA",
                              schedules: se.schedules ?? [],
                              label: `Section ${se.section ?? "Assigned"}`,
                          }
                        : null;

                return {
                    id: se.id,
                    subject_id: se.subject_id as number,
                    subject_code: se.subject_code,
                    subject_title: se.subject_title,
                    class_id: se.class_id,
                    class_section: se.section,
                    lecture_units: se.lecture_units,
                    laboratory_units: se.laboratory_units,
                    lecture_fee: se.lecture_fee ?? 0,
                    laboratory_fee: se.lab_fee ?? 0,
                    lec_per_unit: 0,
                    lab_per_unit: 0,
                    is_modular: se.is_modular,
                    exclude_from_tuition: se.exclude_from_tuition,
                    has_grades: se.has_grades,
                    sections: sectionOption ? [sectionOption] : [],
                };
            });
    }, [enrollment.subjects_enrolled]);

    const scheduleConflicts = useMemo(
        () =>
            detectDraftScheduleConflicts(
                draftSubjectsForMatrix,
                scheduling_policy,
            ),
        [draftSubjectsForMatrix, scheduling_policy],
    );

    const handleNavigateQueue = useCallback(
        (targetId: number) => {
            const query: Record<string, string> = { view: layoutMode };
            if (queue_navigation.return_to) {
                query.return_to = queue_navigation.return_to;
            }
            router.visit(showEnrollment.url(targetId, { query }));
        },
        [layoutMode, queue_navigation.return_to],
    );

    const executePolicyTransition = useCallback(
        async (
            transition: EnrollmentData["workflow"]["allowed_transitions"][number],
            reason?: string,
        ) => {
            setSubmittingTransition(true);
            setBusyAction(`transition:${transition.key}`);
            try {
                await axios.post(
                    transitionPolicyEnrollment.url(enrollment.id),
                    {
                        transition_key: transition.key,
                        idempotency_key: createIdempotencyKey(
                            `transition-${enrollment.id}-${transition.key}`,
                        ),
                        payload: reason ? { reason } : {},
                    },
                );
                toast.success(`${transition.label} completed.`);
                setPendingTransition(null);
                setTransitionReason("");
                router.reload({ only: ["enrollment", "enrollment_pipeline"] });
            } catch (error) {
                toast.error(
                    extractErrorMessage(
                        error,
                        `Unable to run ${transition.label}.`,
                    ),
                );
            } finally {
                setSubmittingTransition(false);
                setBusyAction(null);
            }
        },
        [enrollment.id],
    );

    const handlePolicyTransition = useCallback(
        (
            transition: EnrollmentData["workflow"]["allowed_transitions"][number],
        ) => {
            if (transition.target_actions.includes("enrollment.verify_payment")) {
                setVerifyCashierOpen(true);
                return;
            }
            if (transition.requires_reason) {
                setPendingTransition(transition);
                setTransitionReason("");
                return;
            }
            void executePolicyTransition(transition);
        },
        [executePolicyTransition],
    );

    const handleSimpleAction = useCallback(
        (action: ViewSimpleActionKind) => {
            setBusyAction(action);
            const finish = () => setBusyAction(null);

            switch (action) {
                case "verify_head_dept":
                    router.post(
                        verifyHeadDept.url(enrollment.id),
                        {},
                        { preserveScroll: true, onFinish: finish },
                    );
                    break;
                case "undo_head_dept":
                    router.post(
                        undoHeadDeptVerification.url(enrollment.id),
                        {},
                        { preserveScroll: true, onFinish: finish },
                    );
                    break;
                case "advance_pipeline":
                    router.post(
                        advancePipelineStep.url(enrollment.id),
                        {},
                        { preserveScroll: true, onFinish: finish },
                    );
                    break;
                case "resend_assessment":
                    router.post(
                        resendAssessment.url(enrollment.id),
                        {},
                        { preserveScroll: true, onFinish: finish },
                    );
                    break;
                case "create_assessment_pdf":
                    router.post(
                        createAssessmentPdf.url(enrollment.id),
                        {},
                        { preserveScroll: true, onFinish: finish },
                    );
                    break;
                case "retry_enrollment":
                    router.post(
                        retryEnrollment.url(enrollment.id),
                        { force_enrollment: false },
                        { preserveScroll: true, onFinish: finish },
                    );
                    break;
            }
        },
        [enrollment.id],
    );

    const handleOpenDialog = useCallback(
        (dialog: ViewDialogKind) => {
            switch (dialog) {
                case "verify_cashier":
                    setVerifyCashierOpen(true);
                    break;
                case "quick_enroll":
                    setQuickEnrollOpen(true);
                    break;
                case "cancel":
                    setCancelOpen(true);
                    break;
                case "reopen":
                    setReopenOpen(true);
                    break;
                case "restore_subjects":
                    setRestoreSubjectsOpen(true);
                    break;
                case "soft_delete":
                    setDeleteRestoreMode("soft_delete");
                    break;
                case "restore":
                    setDeleteRestoreMode("restore");
                    break;
                case "force_delete":
                    setDeleteRestoreMode("force_delete");
                    break;
                case "load_change":
                    setLoadChangeOpen(true);
                    break;
                case "transfer":
                    setTransferOpen(true);
                    break;
                case "schedule_changes":
                    setScheduleNotifyOpen(true);
                    break;
                case "undo_cashier":
                    setUndoCashierOpen(true);
                    break;
                case "create_hold":
                    handleJumpToTab("requirements_holds");
                    break;
            }
        },
        [handleJumpToTab],
    );

    const handleEnrollClass = useCallback(
        (classId: number, force: boolean) => {
            setEnrollingClassId(classId);
            router.post(
                enrollInClass.url(enrollment.id),
                { class_id: classId, force_enrollment: force },
                {
                    preserveScroll: true,
                    onFinish: () => setEnrollingClassId(null),
                },
            );
        },
        [enrollment.id],
    );

    const submitRequirementAction = useCallback(
        async (
            requirementId: number,
            action: "verify" | "waive" | "reset",
            reason?: string,
        ) => {
            setReviewingRequirementId(requirementId);
            try {
                await axios.post(
                    reviewRequirement.url({
                        enrollment: enrollment.id,
                        requirement: requirementId,
                    }),
                    {
                        action,
                        reason: reason || undefined,
                        idempotency_key: createIdempotencyKey(
                            `req-${requirementId}-${action}`,
                        ),
                    },
                );
                toast.success(
                    action === "verify"
                        ? "Requirement marked as verified."
                        : action === "waive"
                          ? "Requirement waived."
                          : "Requirement reset to pending.",
                );
                setRequirementDialog(null);
                setRequirementReason("");
                router.reload({ only: ["enrollment", "enrollment_pipeline"] });
            } catch (error) {
                toast.error(
                    extractErrorMessage(
                        error,
                        "Unable to update requirement status.",
                    ),
                );
            } finally {
                setReviewingRequirementId(null);
            }
        },
        [enrollment.id],
    );

    const handleHeroReviewRequirement = useCallback(
        (requirementId: number, action: "verify" | "waive") => {
            if (action === "verify") {
                void submitRequirementAction(requirementId, "verify");
                return;
            }
            const req = enrollment.requirements.find(
                (r) => r.id === requirementId,
            );
            setRequirementDialog({
                id: requirementId,
                label: req?.label ?? "Requirement",
                action: "waive",
            });
            setRequirementReason("");
        },
        [enrollment.requirements, submitRequirementAction],
    );

    const handleResetRequirement = useCallback(
        (requirementId: number) => {
            const req = enrollment.requirements.find(
                (r) => r.id === requirementId,
            );
            setRequirementDialog({
                id: requirementId,
                label: req?.label ?? "Requirement",
                action: "reset",
            });
            setRequirementReason("");
        },
        [enrollment.requirements],
    );

    const handleConfirmUndoCashier = useCallback(() => {
        setBusyAction("undo_cashier");
        router.post(
            undoCashierVerification.url(enrollment.id),
            {},
            {
                preserveScroll: true,
                onFinish: () => {
                    setBusyAction(null);
                    setUndoCashierOpen(false);
                },
            },
        );
    }, [enrollment.id]);

    const handleConfirmNotifyScheduleChanges = useCallback(() => {
        setBusyAction("notify_schedule_changes");
        router.post(
            notifyClassScheduleChanges.url(enrollment.id),
            {},
            {
                preserveScroll: true,
                onFinish: () => {
                    setBusyAction(null);
                    setScheduleNotifyOpen(false);
                },
            },
        );
    }, [enrollment.id]);

    // Badge counts for the 5 reference dossier tabs
    const activeHoldsCount = useMemo(
        () => enrollment.holds.filter((h) => h.status === "active").length,
        [enrollment.holds],
    );
    const pendingRequirementsCount = useMemo(
        () =>
            enrollment.requirements.filter((r) => r.status === "pending")
                .length,
        [enrollment.requirements],
    );
    const loadAlertsCount =
        enrollment.missing_classes.length + scheduleConflicts.length;
    const balanceDue = enrollment.tuition?.balance_due ?? 0;

    // Resolve Cashier verification transition props (if policy_v1)
    const cashierTransition = useMemo(
        () =>
            enrollment.workflow.allowed_transitions.find((t) =>
                t.target_actions.includes("enrollment.verify_payment"),
            ) ?? null,
        [enrollment.workflow.allowed_transitions],
    );

    // Compact horizontal Stepper for Style A (Command Dossier)
    const commandStepperBar = useMemo(() => {
        const steps = enrollment_pipeline.steps ?? [];
        if (steps.length === 0) return null;

        const currentIndex =
            enrollment.workflow_runtime === "policy_v1" &&
            enrollment.workflow.current_step_key
                ? steps.findIndex(
                      (s) => s.key === enrollment.workflow.current_step_key,
                  )
                : steps.findIndex((s) => s.status === enrollment.status);
        const resolvedStepNumber =
            enrollment.workflow.is_completed
                ? steps.length + 1
                : currentIndex >= 0
                  ? currentIndex + 1
                  : 1;

        const primaryPolicyTransition =
            enrollment.workflow_runtime === "policy_v1"
                ? (enrollment.workflow.allowed_transitions[0] ?? null)
                : null;
        const legacyNext = enrollment_pipeline.next_step ?? null;

        return (
            <Frame spacing="xs" className="min-w-0">
                <FramePanel className="flex min-w-0 flex-col gap-3 px-3.5 py-2.5 lg:flex-row lg:items-center lg:justify-between">
                    <div className="min-w-0 flex-1 overflow-x-auto">
                        <Stepper
                            value={Math.min(resolvedStepNumber, steps.length)}
                            indicators={{
                                completed: <Check className="size-3.5" />,
                            }}
                        >
                            <StepperNav className="flex min-w-max items-center gap-0">
                                {steps.map((step, idx) => {
                                    const stepNum = idx + 1;
                                    const completed =
                                        enrollment.workflow.is_completed ||
                                        (currentIndex >= 0 &&
                                            idx < currentIndex);
                                    const isCurrent =
                                        !enrollment.workflow.is_completed &&
                                        idx === currentIndex;
                                    const tone = resolvePipelineColor(
                                        step.color,
                                    );
                                    return (
                                        <StepperItem
                                            key={step.key}
                                            step={stepNum}
                                            completed={completed}
                                        >
                                            <StepperTrigger
                                                render={<div />}
                                                className="cursor-default gap-2 px-1 py-0.5"
                                            >
                                                <StepperIndicator
                                                    className={cn(
                                                        isCurrent &&
                                                            tone?.bar &&
                                                            "ring-2 ring-primary/25",
                                                    )}
                                                >
                                                    {stepNum}
                                                </StepperIndicator>
                                                <StepperTitle className="text-xs font-medium whitespace-nowrap">
                                                    {step.label}
                                                </StepperTitle>
                                            </StepperTrigger>
                                            {idx < steps.length - 1 && (
                                                <StepperSeparator className="mx-2 min-w-5" />
                                            )}
                                        </StepperItem>
                                    );
                                })}
                            </StepperNav>
                        </Stepper>
                    </div>

                    {/* Quick Stage Action in Command Mode */}
                    {!enrollment.is_trashed &&
                        !enrollment.workflow.is_terminal_locked &&
                        !enrollment.workflow.is_completed && (
                            <div className="flex shrink-0 flex-wrap items-center gap-2">
                                {primaryPolicyTransition ? (
                                    <Button
                                        size="sm"
                                        disabled={busyAction !== null}
                                        onClick={() =>
                                            handlePolicyTransition(
                                                primaryPolicyTransition,
                                            )
                                        }
                                        className="gap-1.5"
                                    >
                                        {busyAction ===
                                            `transition:${primaryPolicyTransition.key}` && (
                                            <Loader2 className="size-3.5 animate-spin" />
                                        )}
                                        <span>
                                            {primaryPolicyTransition.label}
                                        </span>
                                        <ArrowRight className="size-3.5" />
                                    </Button>
                                ) : legacyNext?.action_type ===
                                      "cashier_verification" &&
                                  auth.can_verify_cashier ? (
                                    <Button
                                        size="sm"
                                        onClick={() =>
                                            setVerifyCashierOpen(true)
                                        }
                                        className="gap-1.5"
                                    >
                                        <CreditCard className="size-3.5" />
                                        <span>Verify Payment & Enroll</span>
                                    </Button>
                                ) : legacyNext?.action_type ===
                                      "department_verification" &&
                                  auth.can_verify_head ? (
                                    <Button
                                        size="sm"
                                        disabled={busyAction !== null}
                                        onClick={() =>
                                            handleSimpleAction(
                                                "verify_head_dept",
                                            )
                                        }
                                        className="gap-1.5"
                                    >
                                        {busyAction === "verify_head_dept" && (
                                            <Loader2 className="size-3.5 animate-spin" />
                                        )}
                                        <span>Verify Dept Step</span>
                                        <ArrowRight className="size-3.5" />
                                    </Button>
                                ) : legacyNext && auth.can_advance_pipeline ? (
                                    <Button
                                        size="sm"
                                        disabled={busyAction !== null}
                                        onClick={() =>
                                            handleSimpleAction(
                                                "advance_pipeline",
                                            )
                                        }
                                        className="gap-1.5"
                                    >
                                        {busyAction === "advance_pipeline" && (
                                            <Loader2 className="size-3.5 animate-spin" />
                                        )}
                                        <span>Advance: {legacyNext.label}</span>
                                        <ArrowRight className="size-3.5" />
                                    </Button>
                                ) : null}
                            </div>
                        )}
                </FramePanel>
            </Frame>
        );
    }, [
        enrollment_pipeline,
        enrollment.workflow_runtime,
        enrollment.workflow.current_step_key,
        enrollment.workflow.is_completed,
        enrollment.workflow.is_terminal_locked,
        enrollment.workflow.allowed_transitions,
        enrollment.status,
        enrollment.is_trashed,
        busyAction,
        auth.can_verify_cashier,
        auth.can_verify_head,
        auth.can_advance_pipeline,
        handlePolicyTransition,
        handleSimpleAction,
    ]);

    const dossierTabsSection = (
        <div ref={tabsAnchorRef} className="min-w-0 space-y-4">
            <Tabs
                value={activeTab}
                onValueChange={(value) => setActiveTab(value as DossierTab)}
                className="min-w-0 space-y-4"
            >
                <div className="overflow-x-auto pb-0.5">
                    <TabsList className="inline-flex h-auto min-w-max gap-1 p-1">
                        <TabsTrigger
                            value="load"
                            className="gap-1.5 px-2.5 py-1.5 text-xs"
                        >
                            <BookOpen className="size-3.5" />
                            <span>Load &amp; Schedule</span>
                            <Badge
                                variant={
                                    loadAlertsCount > 0
                                        ? "warning-light"
                                        : "secondary"
                                }
                                size="xs"
                                className="tabular-nums"
                            >
                                {enrollment.subjects_enrolled.length}
                                {loadAlertsCount > 0
                                    ? ` · !${loadAlertsCount}`
                                    : ""}
                            </Badge>
                        </TabsTrigger>

                        <TabsTrigger
                            value="billing"
                            className="gap-1.5 px-2.5 py-1.5 text-xs"
                        >
                            <CreditCard className="size-3.5" />
                            <span>Billing</span>
                            <Badge
                                variant={
                                    balanceDue > 0
                                        ? "warning-light"
                                        : "success-light"
                                }
                                size="xs"
                                className="tabular-nums"
                            >
                                {enrollment.transactions.length}
                            </Badge>
                        </TabsTrigger>

                        <TabsTrigger
                            value="documents"
                            className="gap-1.5 px-2.5 py-1.5 text-xs"
                        >
                            <FileText className="size-3.5" />
                            <span>Documents &amp; COR</span>
                            {enrollment.resources.length > 0 && (
                                <Badge
                                    variant="secondary"
                                    size="xs"
                                    className="tabular-nums"
                                >
                                    {enrollment.resources.length}
                                </Badge>
                            )}
                        </TabsTrigger>

                        <TabsTrigger
                            value="requirements_holds"
                            className="gap-1.5 px-2.5 py-1.5 text-xs"
                        >
                            <ShieldCheck className="size-3.5" />
                            <span>Holds &amp; Reqs</span>
                            {(activeHoldsCount > 0 ||
                                pendingRequirementsCount > 0) && (
                                <Badge
                                    variant={
                                        activeHoldsCount > 0
                                            ? "destructive-light"
                                            : "warning-light"
                                    }
                                    size="xs"
                                    className="tabular-nums"
                                >
                                    {activeHoldsCount +
                                        pendingRequirementsCount}
                                </Badge>
                            )}
                        </TabsTrigger>

                        <TabsTrigger
                            value="timeline"
                            className="gap-1.5 px-2.5 py-1.5 text-xs"
                        >
                            <History className="size-3.5" />
                            <span>Audit</span>
                            <Badge
                                variant="secondary"
                                size="xs"
                                className="tabular-nums"
                            >
                                {enrollment.timeline.length}
                            </Badge>
                        </TabsTrigger>
                    </TabsList>
                </div>

                <TabsContent value="load" className="mt-0 min-w-0">
                    <LoadSchedulePanel
                        enrollment={enrollment}
                        schedulingPolicy={scheduling_policy}
                        auth={auth}
                        currency={currency}
                        scheduleConflicts={scheduleConflicts}
                        draftSubjectsForMatrix={draftSubjectsForMatrix}
                        onOpenLoadChange={() => setLoadChangeOpen(true)}
                        onOpenTransfer={() => setTransferOpen(true)}
                        onRetryAutoEnroll={() =>
                            handleSimpleAction("retry_enrollment")
                        }
                        retryingAutoEnroll={busyAction === "retry_enrollment"}
                        onEnrollClass={handleEnrollClass}
                        enrollingClassId={enrollingClassId}
                    />
                </TabsContent>

                <TabsContent value="billing" className="mt-0 min-w-0">
                    <BillingLedgerPanel
                        enrollment={enrollment}
                        auth={auth}
                        currency={currency}
                        documentLinks={document_links}
                        onEditTransaction={(tx) => setEditingTransaction(tx)}
                    />
                </TabsContent>

                <TabsContent value="documents" className="mt-0 min-w-0">
                    <DocumentsStudioPanel
                        enrollment={enrollment}
                        auth={auth}
                        documentLinks={document_links}
                        onRegeneratePdf={() =>
                            handleSimpleAction("create_assessment_pdf")
                        }
                        regeneratingPdf={busyAction === "create_assessment_pdf"}
                        onResendAssessment={() =>
                            handleSimpleAction("resend_assessment")
                        }
                        resendingAssessment={busyAction === "resend_assessment"}
                    />
                </TabsContent>

                <TabsContent
                    value="requirements_holds"
                    className="mt-0 min-w-0"
                >
                    <RequirementsHoldsPanel
                        enrollment={enrollment}
                        holdOffices={hold_offices}
                        auth={auth}
                        onVerifyRequirement={(id) =>
                            void submitRequirementAction(id, "verify")
                        }
                        onWaiveRequirement={(id, reason) =>
                            void submitRequirementAction(id, "waive", reason)
                        }
                        onResetRequirement={handleResetRequirement}
                        reviewingRequirementId={reviewingRequirementId}
                    />
                </TabsContent>

                <TabsContent value="timeline" className="mt-0 min-w-0">
                    <UnifiedTimelinePanel
                        enrollment={enrollment}
                        currency={currency}
                        onOpenLoadChange={() => setLoadChangeOpen(true)}
                        onOpenTransfer={() => setTransferOpen(true)}
                    />
                </TabsContent>
            </Tabs>
        </div>
    );

    return (
        <AdminLayout
            user={user as unknown as User}
            title={`Enrollment #${enrollment.id}`}
        >
            <Head
                title={`Enrollment #${enrollment.id} · ${enrollment.student.full_name}`}
            />

            <div className="mx-auto w-full max-w-[1440px] min-w-0 space-y-4">
                {/* Shared Top Header Bar with Style C / Style A Switcher & Queue Nav */}
                <ViewHeaderBar
                    enrollment={enrollment}
                    enrollmentPeriod={enrollment_period}
                    auth={auth}
                    documentLinks={document_links}
                    queueNavigation={queue_navigation}
                    layoutMode={layoutMode}
                    onLayoutModeChange={handleLayoutModeChange}
                    onOpenDialog={handleOpenDialog}
                    onNavigateQueue={handleNavigateQueue}
                    onSimpleAction={handleSimpleAction}
                    busyAction={busyAction}
                />

                {/* Unified Alert / Guard Banner Strip */}
                <ViewGuardBanners
                    enrollment={enrollment}
                    enrollmentPeriod={enrollment_period}
                    scheduleConflictsCount={scheduleConflicts.length}
                    documentLinks={document_links}
                    auth={auth}
                    onJumpToTab={handleJumpToTab}
                    onOpenDialog={handleOpenDialog}
                />

                {layoutMode === "lifecycle" ? (
                    /* ========================================================
                     * STYLE C (DEFAULT): GUIDED INTAKE-TO-COR LIFECYCLE HUB
                     * ======================================================== */
                    <div className="min-w-0 space-y-4">
                        <StageFocusHero
                            enrollment={enrollment}
                            enrollmentPipeline={enrollment_pipeline}
                            documentLinks={document_links}
                            auth={auth}
                            currency={currency}
                            scheduleConflictsCount={scheduleConflicts.length}
                            busyAction={busyAction}
                            reviewingRequirementId={reviewingRequirementId}
                            onPolicyTransition={handlePolicyTransition}
                            onSimpleAction={handleSimpleAction}
                            onOpenDialog={handleOpenDialog}
                            onReviewRequirement={handleHeroReviewRequirement}
                            onJumpToTab={handleJumpToTab}
                        />

                        <div className="grid min-w-0 grid-cols-1 items-start gap-4 xl:grid-cols-[minmax(0,1fr)_320px]">
                            {dossierTabsSection}
                            <ReadinessActionRail
                                enrollment={enrollment}
                                auth={auth}
                                documentLinks={document_links}
                                scholarshipOptions={scholarship_options}
                                currency={currency}
                                scheduleConflictsCount={
                                    scheduleConflicts.length
                                }
                                onJumpToTab={handleJumpToTab}
                                compact
                            />
                        </div>
                    </div>
                ) : (
                    /* ========================================================
                     * STYLE A (OPTION): REGISTRAR COMMAND & QUEUE DOSSIER
                     * ======================================================== */
                    <div className="min-w-0 space-y-4">
                        {commandStepperBar}

                        <div className="grid min-w-0 grid-cols-1 items-start gap-4 xl:grid-cols-[minmax(0,1fr)_340px]">
                            {dossierTabsSection}
                            <ReadinessActionRail
                                enrollment={enrollment}
                                auth={auth}
                                documentLinks={document_links}
                                scholarshipOptions={scholarship_options}
                                currency={currency}
                                scheduleConflictsCount={
                                    scheduleConflicts.length
                                }
                                onJumpToTab={handleJumpToTab}
                            />
                        </div>
                    </div>
                )}
            </div>

            {/* ================================================================
             * CONTROLLED MODALS & DIALOGS (Never nested inside DropdownMenus)
             * ================================================================ */}
            <VerifyCashierDialog
                enrollmentId={enrollment.id}
                tuition={enrollment.tuition}
                additionalFees={enrollment.additional_fees}
                transitionKey={cashierTransition?.key}
                requiresReason={cashierTransition?.requires_reason ?? false}
                receiptMode={
                    cashierTransition?.payment_configuration?.receipt_mode ??
                    "required"
                }
                allowedPaymentMethods={
                    enrollment.workflow.allowed_payment_methods
                }
                assessmentPreviewUrl={document_links.assessment_preview_url}
                open={verifyCashierOpen}
                onOpenChange={setVerifyCashierOpen}
                hideTrigger
            />

            {editingTransaction && (
                <EditTransactionDialog
                    enrollmentId={enrollment.id}
                    transaction={editingTransaction}
                    open={Boolean(editingTransaction)}
                    onOpenChange={(open) => {
                        if (!open) setEditingTransaction(null);
                    }}
                    hideTrigger
                />
            )}

            <QuickEnrollDialog
                enrollmentId={enrollment.id}
                studentName={enrollment.student.full_name}
                open={quickEnrollOpen}
                onOpenChange={setQuickEnrollOpen}
            />

            <CancelEnrollmentDialog
                enrollmentId={enrollment.id}
                studentName={enrollment.student.full_name}
                outstandingPayments={enrollment.workflow.outstanding_payments}
                currency={currency}
                open={cancelOpen}
                onOpenChange={setCancelOpen}
            />

            <ReopenEnrollmentDialog
                enrollment={enrollment}
                open={reopenOpen}
                onOpenChange={setReopenOpen}
            />

            <RestoreSubjectsDialog
                enrollmentId={enrollment.id}
                open={restoreSubjectsOpen}
                onOpenChange={setRestoreSubjectsOpen}
                recentDeletions={recent_deletions}
            />

            <DeleteRestoreDialog
                enrollmentId={enrollment.id}
                studentName={enrollment.student.full_name}
                mode={deleteRestoreMode}
                open={deleteRestoreMode !== null}
                onOpenChange={(open) => {
                    if (!open) setDeleteRestoreMode(null);
                }}
            />

            <EnrollmentLoadChangeDialog
                open={loadChangeOpen}
                onOpenChange={setLoadChangeOpen}
                enrollmentId={enrollment.id}
                courseId={enrollment.course_id}
                studentName={enrollment.student.full_name}
                currency={currency}
                onChanged={() =>
                    router.reload({
                        only: ["enrollment", "enrollment_pipeline"],
                    })
                }
            />

            <EnrollmentTransferDialog
                open={transferOpen}
                onOpenChange={setTransferOpen}
                enrollmentId={enrollment.id}
                studentName={enrollment.student.full_name}
                currency={currency}
                onTransferred={() =>
                    router.reload({
                        only: ["enrollment", "enrollment_pipeline"],
                    })
                }
            />

            {/* Pending Class Schedule Changes Preview & Notify Dialog */}
            <Dialog
                open={scheduleNotifyOpen}
                onOpenChange={setScheduleNotifyOpen}
            >
                <DialogContent className="sm:max-w-xl">
                    <DialogHeader>
                        <DialogTitle className="flex items-center gap-2">
                            <BellRing className="size-4 text-amber-500" />
                            Notify Student of Class Schedule Changes
                        </DialogTitle>
                        <DialogDescription>
                            Queue an email notification to{" "}
                            <span className="font-medium text-foreground">
                                {enrollment.student.full_name}
                            </span>{" "}
                            ({enrollment.student.email || "no email on file"})
                            summarizing the pending schedule updates below.
                        </DialogDescription>
                    </DialogHeader>

                    <div className="max-h-72 space-y-2.5 overflow-y-auto py-1">
                        {enrollment.pending_class_schedule_changes.length ===
                        0 ? (
                            <p className="text-sm text-muted-foreground">
                                No pending class schedule changes found.
                            </p>
                        ) : (
                            enrollment.pending_class_schedule_changes.map(
                                (change) => (
                                    <div
                                        key={change.id}
                                        className="rounded-lg border bg-muted/30 p-3 text-xs"
                                    >
                                        <div className="flex items-center justify-between gap-2">
                                            <span className="font-semibold text-foreground">
                                                {change.subject_code ??
                                                    change.class_title}
                                                {change.section
                                                    ? ` · Sec ${change.section}`
                                                    : ""}
                                            </span>
                                            {change.faculty && (
                                                <span className="text-muted-foreground">
                                                    {change.faculty}
                                                </span>
                                            )}
                                        </div>
                                        <div className="mt-2 grid grid-cols-1 gap-1.5 sm:grid-cols-2">
                                            <div className="rounded bg-background/70 p-2">
                                                <div className="text-[10px] font-medium text-muted-foreground uppercase">
                                                    Previous
                                                </div>
                                                <div className="mt-0.5 text-muted-foreground line-through">
                                                    {change.old_schedule.join(
                                                        ", ",
                                                    ) || "TBA"}
                                                </div>
                                            </div>
                                            <div className="rounded bg-emerald-500/10 p-2">
                                                <div className="text-[10px] font-medium text-emerald-700 uppercase dark:text-emerald-300">
                                                    Updated
                                                </div>
                                                <div className="mt-0.5 font-medium text-foreground">
                                                    {change.new_schedule.join(
                                                        ", ",
                                                    ) || "TBA"}
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                ),
                            )
                        )}
                    </div>

                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => setScheduleNotifyOpen(false)}
                        >
                            Cancel
                        </Button>
                        <Button
                            type="button"
                            disabled={
                                busyAction === "notify_schedule_changes" ||
                                enrollment.pending_class_schedule_changes
                                    .length === 0
                            }
                            onClick={handleConfirmNotifyScheduleChanges}
                        >
                            {busyAction === "notify_schedule_changes" && (
                                <Loader2 className="mr-1.5 size-3.5 animate-spin" />
                            )}
                            Send Notification
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            {/* Policy Transition Audited Reason Dialog */}
            <Dialog
                open={pendingTransition !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setPendingTransition(null);
                        setTransitionReason("");
                    }
                }}
            >
                <DialogContent className="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>
                            {pendingTransition?.label ?? "Confirm Transition"}
                        </DialogTitle>
                        <DialogDescription>
                            This workflow transition requires an audited reason
                            before continuing.
                        </DialogDescription>
                    </DialogHeader>
                    <div className="space-y-2 py-2">
                        <Label htmlFor="policy-transition-reason">Reason</Label>
                        <Textarea
                            id="policy-transition-reason"
                            rows={3}
                            placeholder="Explain why this workflow transition is being applied…"
                            value={transitionReason}
                            onChange={(e) =>
                                setTransitionReason(e.target.value)
                            }
                        />
                    </div>
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => setPendingTransition(null)}
                        >
                            Cancel
                        </Button>
                        <Button
                            type="button"
                            disabled={
                                submittingTransition ||
                                !transitionReason.trim() ||
                                !pendingTransition
                            }
                            onClick={() => {
                                if (pendingTransition) {
                                    void executePolicyTransition(
                                        pendingTransition,
                                        transitionReason.trim(),
                                    );
                                }
                            }}
                        >
                            {submittingTransition && (
                                <Loader2 className="mr-1.5 size-3.5 animate-spin" />
                            )}
                            Confirm Transition
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            {/* Requirement Waive / Reset Audited Reason Dialog */}
            <Dialog
                open={requirementDialog !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setRequirementDialog(null);
                        setRequirementReason("");
                    }
                }}
            >
                <DialogContent className="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>
                            {requirementDialog?.action === "reset"
                                ? "Reset Requirement to Pending"
                                : "Waive Enrollment Requirement"}
                        </DialogTitle>
                        <DialogDescription>
                            Provide an audit reason for{" "}
                            <span className="font-medium text-foreground">
                                {requirementDialog?.label}
                            </span>
                            .
                        </DialogDescription>
                    </DialogHeader>
                    <div className="space-y-2 py-2">
                        <Label htmlFor="requirement-audit-reason">Reason</Label>
                        <Textarea
                            id="requirement-audit-reason"
                            rows={3}
                            placeholder={
                                requirementDialog?.action === "reset"
                                    ? "e.g., Uploaded document was illegible; requesting resubmission"
                                    : "e.g., Approved temporary waiver by Registrar until finals"
                            }
                            value={requirementReason}
                            onChange={(e) =>
                                setRequirementReason(e.target.value)
                            }
                        />
                    </div>
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => setRequirementDialog(null)}
                        >
                            Cancel
                        </Button>
                        <Button
                            type="button"
                            disabled={
                                reviewingRequirementId !== null ||
                                !requirementReason.trim() ||
                                !requirementDialog
                            }
                            onClick={() => {
                                if (requirementDialog) {
                                    void submitRequirementAction(
                                        requirementDialog.id,
                                        requirementDialog.action,
                                        requirementReason.trim(),
                                    );
                                }
                            }}
                        >
                            {reviewingRequirementId !== null && (
                                <Loader2 className="mr-1.5 size-3.5 animate-spin" />
                            )}
                            {requirementDialog?.action === "reset"
                                ? "Reset Requirement"
                                : "Confirm Waiver"}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            {/* Undo Cashier Confirmation AlertDialog */}
            <AlertDialog
                open={undoCashierOpen}
                onOpenChange={setUndoCashierOpen}
            >
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>
                            Undo Cashier Payment Verification?
                        </AlertDialogTitle>
                        <AlertDialogDescription>
                            This reverts the enrollment out of the cashier
                            verified state and records an audited correction
                            event. Recorded financial ledger entries remain
                            preserved for reconciliation.
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel>Cancel</AlertDialogCancel>
                        <AlertDialogAction
                            disabled={busyAction === "undo_cashier"}
                            onClick={handleConfirmUndoCashier}
                        >
                            {busyAction === "undo_cashier" && (
                                <Loader2 className="mr-1.5 size-3.5 animate-spin" />
                            )}
                            Undo Verification
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </AdminLayout>
    );
}
