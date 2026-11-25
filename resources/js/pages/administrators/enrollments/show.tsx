import { reviewRequirement, transition as transitionEnrollment } from "@/actions/App/Http/Controllers/AdministratorEnrollmentPolicyController";
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
} from "@/components/ui/alert-dialog";
import { Avatar, AvatarFallback } from "@/components/ui/avatar";
import { Badge } from "@/components/ui/badge";
import { Button, buttonVariants } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from "@/components/ui/dialog";
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import { Label } from "@/components/ui/label";
import { Progress } from "@/components/ui/progress";
import { Separator } from "@/components/ui/separator";
import { Tabs, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { Textarea } from "@/components/ui/textarea";
import { cn } from "@/lib/utils";
import { Head, Link, router, usePage } from "@inertiajs/react";
import axios from "axios";
import {
    ArrowLeft,
    ArrowLeftRight,
    Ban,
    Banknote,
    CalendarDays,
    CheckCircle2,
    ClipboardList,
    ExternalLink,
    Files,
    FileText,
    ListChecks,
    MoreVertical,
    Pencil,
    Printer,
    RefreshCcw,
    School,
    Undo2,
    UserCheck,
    User as UserIcon,
    WalletCards,
} from "lucide-react";
import React, { useState } from "react";
import { toast } from "sonner";
import { route } from "ziggy-js";
import { EnrollmentLoadChangeDialog } from "./enrollment-load-change-dialog";
import { EnrollmentTransferDialog } from "./enrollment-transfer-dialog";
import {
    CancelEnrollmentDialog,
    QuickEnrollDialog,
    RestoreSubjectsDialog,
    VerifyCashierDialog,
} from "./show/enrollment-action-dialogs";
import { EnrollmentDetailTabs } from "./show/enrollment-detail-tabs";
import type { Branding, EnrollmentData, ShowEnrollmentPageProps as PageProps } from "./show/types";

export default function ShowEnrollment({ user, enrollment, auth, recent_deletions = [], enrollment_pipeline }: PageProps) {
    const studentUrl = enrollment.student.student_url || `/administrators/students/${enrollment.student.id}`;
    const { props } = usePage<{ branding?: Branding }>();
    const currency = props.branding?.currency || "PHP";

    const formatMoney = (amount: number | null | undefined) => {
        if (amount === null || amount === undefined) return currency === "USD" ? "$0.00" : "₱0.00";
        return new Intl.NumberFormat(currency === "USD" ? "en-US" : "en-PH", {
            style: "currency",
            currency: currency,
        }).format(amount);
    };

    const [activeTab, setActiveTab] = useState("classes");
    const [showUndoDialog, setShowUndoDialog] = useState(false);
    const [showLoadChangeDialog, setShowLoadChangeDialog] = useState(false);
    const [showTransferDialog, setShowTransferDialog] = useState(false);
    const [showScheduleChangesDialog, setShowScheduleChangesDialog] = useState(false);
    const [selectedDeletions, setSelectedDeletions] = useState<number[]>([]);
    const [restoringSubjects, setRestoringSubjects] = useState(false);
    const [waivingRequirementId, setWaivingRequirementId] = useState<number | null>(null);
    const [waiverReason, setWaiverReason] = useState("");
    const [reviewingRequirementId, setReviewingRequirementId] = useState<number | null>(null);
    const pendingScheduleChanges = enrollment.pending_class_schedule_changes ?? [];

    const pendingStatus = enrollment_pipeline.pending_status;
    const departmentStep = enrollment_pipeline.steps.find((step) => step.action_type === "department_verification") ?? null;
    const cashierStep = enrollment_pipeline.steps.find((step) => step.action_type === "cashier_verification") ?? null;
    const completionStep =
        enrollment_pipeline.steps.find((step) => step.is_completion) ?? enrollment_pipeline.steps[enrollment_pipeline.steps.length - 1];
    const departmentVerifiedStatus = departmentStep?.status ?? enrollment_pipeline.department_verified_status;
    const cashierVerifiedStatus = cashierStep?.status ?? enrollment_pipeline.cashier_verified_status;
    const completionStatus = completionStep?.status ?? enrollment_pipeline.cashier_verified_status;
    const statusClasses = enrollment_pipeline.status_classes ?? {};
    const nextStep = enrollment_pipeline.next_step ?? null;
    const balanceDue = enrollment.tuition?.balance_due ?? enrollment.tuition?.total_balance ?? 0;
    const totalPaid = enrollment.tuition?.total_paid ?? 0;
    const requiredDownpayment = enrollment.tuition?.required_downpayment ?? enrollment.tuition?.downpayment ?? 0;
    const totalUnits = enrollment.subjects_enrolled.reduce((total, subject) => total + subject.units, 0);
    const pendingRequirements = enrollment.requirements.filter((requirement) => requirement.status === "pending").length;
    const reviewedRequirements = enrollment.requirements.filter((requirement) => requirement.status !== "pending").length;
    const requirementsProgress = enrollment.requirements.length > 0 ? (reviewedRequirements / enrollment.requirements.length) * 100 : 100;
    const requirementReviewKey = () => (typeof crypto.randomUUID === "function" ? crypto.randomUUID() : `requirement-${Date.now()}-${Math.random()}`);

    const reviewEnrollmentRequirement = (requirementId: number, action: "verify" | "waive", reason?: string) => {
        setReviewingRequirementId(requirementId);
        axios
            .post(reviewRequirement.url({ enrollment: enrollment.id, requirement: requirementId }), {
                action,
                reason: reason || null,
                idempotency_key: requirementReviewKey(),
            })
            .then(() => {
                setWaivingRequirementId(null);
                setWaiverReason("");
                toast.success(action === "verify" ? "Requirement verified." : "Requirement waived with an audit reason.");
                router.reload({ only: ["enrollment"] });
            })
            .catch((error: unknown) => {
                const message = axios.isAxiosError(error)
                    ? (Object.values(error.response?.data?.errors ?? {})[0] as string[] | undefined)?.[0]
                    : null;
                toast.error(message ?? "The requirement could not be reviewed.");
            })
            .finally(() => setReviewingRequirementId(null));
    };

    const getStatusColor = (status: string) => {
        return statusClasses[status] ?? "bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-400 border-gray-200";
    };

    // --- Actions ---

    const handleVerifyHeadDept = () => {
        router.post(
            route("administrators.enrollments.verify-head-dept", enrollment.id),
            {},
            {
                onSuccess: () => toast.success("Verified as Department Head"),
                onError: () => toast.error("Verification failed"),
            },
        );
    };

    const handleUndoHeadDept = () => {
        router.post(
            route("administrators.enrollments.undo-head-dept", enrollment.id),
            {},
            {
                onSuccess: () => toast.success("Department Head verification undone"),
                onError: () => toast.error("Action failed"),
            },
        );
    };

    const [showUndoCashierDialog, setShowUndoCashierDialog] = useState(false);

    const handleUndoCashier = () => {
        setShowUndoCashierDialog(true);
    };

    const confirmUndoCashier = () => {
        setShowUndoCashierDialog(false);
        router.post(
            route("administrators.enrollments.undo-cashier", enrollment.id),
            {},
            {
                onSuccess: () => toast.success("Cashier verification undone"),
                onError: () => toast.error("Action failed"),
            },
        );
    };

    const [showUndoCancellationDialog, setShowUndoCancellationDialog] = useState(false);
    const [undoCancellationReason, setUndoCancellationReason] = useState("");

    const handleUndoCancellation = () => {
        setUndoCancellationReason("");
        setShowUndoCancellationDialog(true);
    };

    const submitUndoCancellation = (e: React.FormEvent) => {
        e.preventDefault();
        router.post(
            route("administrators.enrollments.undo-cancellation", enrollment.id),
            { reason: undoCancellationReason },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setShowUndoCancellationDialog(false);
                    toast.success("Cancellation undone. The enrollment is active again.");
                },
                onError: () => toast.error("The cancellation could not be undone."),
            },
        );
    };

    const handleResendAssessment = () => {
        router.post(
            route("administrators.enrollments.resend-assessment", enrollment.id),
            {},
            {
                onSuccess: () => toast.success("Assessment queued for resending"),
                onError: () => toast.error("Action failed"),
            },
        );
    };

    const handleRetryEnrollment = () => {
        router.post(
            route("administrators.enrollments.retry-enrollment", enrollment.id),
            { force_enrollment: true },
            {
                onSuccess: () => toast.success("Class enrollment retried"),
                onError: () => toast.error("Action failed"),
            },
        );
    };

    const handleNotifyClassScheduleChanges = () => {
        router.post(
            route("administrators.enrollments.class-schedule-changes.notify", enrollment.id),
            {},
            {
                preserveScroll: true,
                onSuccess: () => {
                    setShowScheduleChangesDialog(false);
                    toast.success("Class schedule change notification queued");
                },
                onError: () => toast.error("Failed to notify student"),
            },
        );
    };

    const handleAdvancePipelineStep = () => {
        router.post(
            route("administrators.enrollments.advance-pipeline-step", enrollment.id),
            {},
            {
                onSuccess: () => toast.success("Advanced to the next pipeline step"),
                onError: () => toast.error("Failed to advance pipeline step"),
            },
        );
    };

    const [pendingPolicyTransition, setPendingPolicyTransition] = useState<EnrollmentData["workflow"]["allowed_transitions"][number] | null>(null);
    const [policyTransitionReason, setPolicyTransitionReason] = useState("");
    const [submittingPolicyTransition, setSubmittingPolicyTransition] = useState(false);

    const executePolicyTransition = (
        workflowTransition: EnrollmentData["workflow"]["allowed_transitions"][number],
        reason?: string | null,
    ) => {
        setSubmittingPolicyTransition(true);
        axios
            .post(transitionEnrollment.url(enrollment.id), {
                transition_key: workflowTransition.key,
                idempotency_key: requirementReviewKey(),
                payload: reason ? { reason: reason.trim() } : {},
            })
            .then(() => {
                setPendingPolicyTransition(null);
                setPolicyTransitionReason("");
                toast.success(`${workflowTransition.label} completed.`);
                router.reload({ only: ["enrollment", "enrollment_pipeline"] });
            })
            .catch((error: unknown) => {
                const message = axios.isAxiosError(error) ? error.response?.data?.message : null;
                toast.error(message ?? "The enrollment transition could not be completed.");
            })
            .finally(() => {
                setSubmittingPolicyTransition(false);
            });
    };

    const handlePolicyTransition = (workflowTransition: EnrollmentData["workflow"]["allowed_transitions"][number]) => {
        if (workflowTransition.requires_reason) {
            setPolicyTransitionReason("");
            setPendingPolicyTransition(workflowTransition);
            return;
        }

        executePolicyTransition(workflowTransition);
    };

    const handleRestoreSubjects = () => {
        if (selectedDeletions.length === 0) return;
        setRestoringSubjects(true);
        router.post(
            route("administrators.enrollments.restore-subjects", enrollment.id),
            { activity_ids: selectedDeletions },
            {
                onSuccess: () => {
                    toast.success("Subjects restored successfully");
                    setShowUndoDialog(false);
                    setSelectedDeletions([]);
                },
                onError: () => toast.error("Failed to restore subjects"),
                onFinish: () => setRestoringSubjects(false),
            },
        );
    };

    const toggleDeletionSelection = (id: number) => {
        setSelectedDeletions((prev) => (prev.includes(id) ? prev.filter((i) => i !== id) : [...prev, id]));
    };

    const selectAllDeletions = () => {
        if (selectedDeletions.length === recent_deletions.length) {
            setSelectedDeletions([]);
        } else {
            setSelectedDeletions(recent_deletions.map((d) => d.id));
        }
    };

    // Enrollment Steps Calculation
    const pipelineSteps = enrollment_pipeline.steps ?? [];
    const currentStepIndex = pipelineSteps.findIndex((step) => step.status === enrollment.status);
    const policyStepIndex = enrollment.workflow.steps.findIndex((step) => step.key === enrollment.workflow.current_step_key);
    const steps =
        enrollment.workflow_runtime === "policy_v1"
            ? enrollment.workflow.steps.map((step, index) => ({
                  label: step.label,
                  status:
                      enrollment.workflow.terminal_outcome || index < policyStepIndex
                          ? "completed"
                          : index === policyStepIndex
                            ? "current"
                            : "pending",
              }))
            : [
                  { label: enrollment_pipeline.submitted_label, status: "completed" },
                  ...pipelineSteps.map((step, index) => ({
                      label: step.label,
                      status:
                          currentStepIndex === -1
                              ? "pending"
                              : index < currentStepIndex
                                ? "completed"
                                : index === currentStepIndex
                                  ? "current"
                                  : "pending",
                  })),
              ];
    const workflowTotalSteps = steps.length;
    const workflowCompletedSteps = steps.filter((step) => step.status === "completed").length;
    const currentWorkflowStep = steps.find((step) => step.status === "current") ?? null;
    const workflowProgress = workflowTotalSteps > 0 ? (workflowCompletedSteps / workflowTotalSteps) * 100 : 0;
    const nextActionLabel =
        enrollment.workflow.terminal_outcome === "cancelled"
            ? "Enrollment cancelled"
            : (enrollment.workflow.allowed_transitions[0]?.label ??
              nextStep?.label ??
              (enrollment.workflow.terminal_outcome ? "Workflow complete" : "Review workflow"));
    const totalAssessment = enrollment.tuition?.overall_tuition ?? 0;
    const paymentProgress = totalAssessment > 0 ? Math.min(100, Math.round((totalPaid / totalAssessment) * 100)) : 0;
    const headerActions = (
        <section aria-label="Enrollment actions" className="border-primary/20 bg-primary/[0.035] rounded-2xl border p-3 shadow-sm sm:p-4">
            <div className="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                <div className="min-w-0 px-1">
                    <p className="text-primary text-[11px] font-semibold tracking-[0.14em] uppercase">Review action</p>
                    <p className="mt-1 truncate text-sm font-semibold">{nextActionLabel}</p>
                    <p className="text-muted-foreground mt-1 text-xs">Actions are permission-aware and recorded in the enrollment history.</p>
                </div>
                <div className="flex flex-wrap items-center gap-2 lg:justify-end">
                    {enrollment.workflow_runtime === "policy_v1" &&
                        enrollment.workflow.allowed_transitions.map((workflowTransition) =>
                            workflowTransition.target_actions.includes("enrollment.verify_payment") ? (
                                <VerifyCashierDialog
                                    key={workflowTransition.key}
                                    enrollmentId={enrollment.id}
                                    tuition={enrollment.tuition}
                                    additionalFees={enrollment.additional_fees}
                                    label={workflowTransition.label}
                                    transitionKey={workflowTransition.key}
                                    requiresReason={workflowTransition.requires_reason}
                                    receiptMode={workflowTransition.payment_configuration?.receipt_mode ?? "required"}
                                    allowedPaymentMethods={enrollment.workflow.allowed_payment_methods}
                                    buttonClassName="w-auto min-w-[10rem] rounded-xl"
                                />
                            ) : (
                                <Button
                                    key={workflowTransition.key}
                                    onClick={() => handlePolicyTransition(workflowTransition)}
                                    className="h-10 rounded-xl px-4 shadow-sm transition-[transform,box-shadow] duration-200 active:scale-[0.98]"
                                >
                                    <CheckCircle2 className="mr-2 size-4" />
                                    {workflowTransition.label}
                                </Button>
                            ),
                        )}

                    {enrollment.workflow_runtime === "legacy" &&
                        !enrollment.workflow.terminal_outcome &&
                        nextStep?.action_type === "department_verification" &&
                        auth.can_verify_head && (
                            <Button onClick={handleVerifyHeadDept} className="h-10 rounded-xl bg-blue-600 px-4 shadow-sm hover:bg-blue-700">
                                <UserCheck className="mr-2 size-4" />
                                Verify step
                            </Button>
                        )}

                    {enrollment.workflow_runtime === "legacy" &&
                        !enrollment.workflow.terminal_outcome &&
                        nextStep?.action_type === "cashier_verification" &&
                        auth.can_verify_cashier && (
                            <VerifyCashierDialog
                                enrollmentId={enrollment.id}
                                tuition={enrollment.tuition}
                                additionalFees={enrollment.additional_fees}
                                buttonClassName="w-auto min-w-[10rem] rounded-xl"
                            />
                        )}

                    {enrollment.workflow_runtime === "legacy" &&
                        !enrollment.workflow.terminal_outcome &&
                        auth.can_advance_pipeline &&
                        nextStep &&
                        nextStep.action_type !== "cashier_verification" &&
                        enrollment.status !== pendingStatus && (
                            <Button onClick={handleAdvancePipelineStep} className="h-10 rounded-xl px-4 shadow-sm">
                                <CheckCircle2 className="mr-2 size-4" />
                                Advance: {nextStep.label}
                            </Button>
                        )}

                    {enrollment.status === completionStatus && (
                        <Button
                            className="h-10 rounded-xl px-4"
                            variant="outline"
                            onClick={() => window.open(route("administrators.enrollments.assessment-preview", enrollment.id), "_blank")}
                        >
                            <Printer className="mr-2 size-4" /> Print assessment
                        </Button>
                    )}

                    <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                            <Button variant="outline" size="sm" className="h-10 rounded-xl px-3">
                                More options <MoreVertical className="ml-1 size-3" />
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end" className="w-56">
                            <DropdownMenuLabel>Administrative actions</DropdownMenuLabel>
                            <DropdownMenuSeparator />
                            <DropdownMenuItem asChild>
                                <Link href={studentUrl} className="flex w-full items-center">
                                    <UserIcon className="mr-2 h-4 w-4" /> View student record
                                </Link>
                            </DropdownMenuItem>
                            <DropdownMenuSeparator />
                            {enrollment.status === completionStatus && (
                                <DropdownMenuItem onClick={handleResendAssessment}>
                                    <FileText className="mr-2 h-4 w-4" /> Resend assessment email
                                </DropdownMenuItem>
                            )}
                            {pendingScheduleChanges.length > 0 && (
                                <DropdownMenuItem onClick={() => setShowScheduleChangesDialog(true)}>
                                    <CalendarDays className="mr-2 h-4 w-4" /> Notify student for class schedule changes
                                </DropdownMenuItem>
                            )}
                            {departmentStep && enrollment.status === departmentVerifiedStatus && auth.can_verify_head && (
                                <DropdownMenuItem onClick={handleUndoHeadDept} className="text-red-600 focus:text-red-600">
                                    <Undo2 className="mr-2 h-4 w-4" /> Undo step verification
                                </DropdownMenuItem>
                            )}
                            {cashierStep && enrollment.status === cashierVerifiedStatus && auth.can_verify_cashier && (
                                <DropdownMenuItem onClick={handleUndoCashier} className="text-red-600 focus:text-red-600">
                                    <Undo2 className="mr-2 h-4 w-4" /> Undo payment verification
                                </DropdownMenuItem>
                            )}
                            {auth.is_super_admin &&
                                !enrollment.workflow.terminal_outcome &&
                                ((enrollment.workflow_runtime === "legacy" && enrollment.status === pendingStatus) ||
                                    enrollment.allows_no_receipt_transition) && <QuickEnrollDialog enrollmentId={enrollment.id} />}
                            {enrollment.workflow.terminal_outcome === "cancelled" && auth.can_reopen && (
                                <DropdownMenuItem onClick={handleUndoCancellation} className="text-emerald-600 focus:text-emerald-600">
                                    <Undo2 className="mr-2 h-4 w-4" /> Undo cancellation
                                </DropdownMenuItem>
                            )}
                            {enrollment.workflow.can_cancel && (
                                <>
                                    <DropdownMenuSeparator />
                                    <CancelEnrollmentDialog
                                        enrollmentId={enrollment.id}
                                        studentName={enrollment.student.full_name}
                                        outstandingPayments={enrollment.workflow.outstanding_payments}
                                        formatMoney={formatMoney}
                                    />
                                </>
                            )}
                            <DropdownMenuSeparator />
                            <DropdownMenuItem onClick={handleRetryEnrollment}>
                                <RefreshCcw className="mr-2 h-4 w-4" /> Retry class enrollment
                            </DropdownMenuItem>
                            {recent_deletions.length > 0 && (
                                <DropdownMenuItem onClick={() => setShowUndoDialog(true)}>
                                    <Undo2 className="mr-2 h-4 w-4" /> Restore deleted subjects ({recent_deletions.length})
                                </DropdownMenuItem>
                            )}
                        </DropdownMenuContent>
                    </DropdownMenu>
                </div>
            </div>
        </section>
    );

    return (
        <AdminLayout user={user} title="Enrollment Management">
            <Head title={`Enrollment • ${enrollment.student.full_name}`} />

            <Dialog open={showScheduleChangesDialog} onOpenChange={setShowScheduleChangesDialog}>
                <DialogContent className="max-h-[85vh] overflow-y-auto sm:max-w-2xl">
                    <DialogHeader>
                        <DialogTitle>Notify student for class schedule changes</DialogTitle>
                        <DialogDescription>
                            Preview the schedule updates that will be sent to {enrollment.student.full_name}
                            {enrollment.student.email ? ` at ${enrollment.student.email}` : ""}.
                        </DialogDescription>
                    </DialogHeader>

                    <div className="space-y-4">
                        {pendingScheduleChanges.map((change) => (
                            <div key={change.id} className="rounded-lg border p-4">
                                <div className="flex flex-wrap items-start justify-between gap-3">
                                    <div>
                                        <div className="font-medium">{change.class_title}</div>
                                        <div className="text-muted-foreground text-sm">
                                            {[change.subject_code, change.subject_title, change.section ? `Section ${change.section}` : null]
                                                .filter(Boolean)
                                                .join(" • ")}
                                        </div>
                                    </div>
                                    <Badge variant="outline">{change.changed_at ?? "Pending"}</Badge>
                                </div>
                                <div className="mt-4 grid gap-4 md:grid-cols-2">
                                    <div>
                                        <div className="text-muted-foreground mb-2 text-xs font-medium uppercase">Previous schedule</div>
                                        <ul className="space-y-1 text-sm">
                                            {(change.old_schedule.length > 0 ? change.old_schedule : ["No previous schedule."]).map((item) => (
                                                <li key={item}>{item}</li>
                                            ))}
                                        </ul>
                                    </div>
                                    <div>
                                        <div className="text-muted-foreground mb-2 text-xs font-medium uppercase">New schedule</div>
                                        <ul className="space-y-1 text-sm">
                                            {(change.new_schedule.length > 0 ? change.new_schedule : ["No scheduled meetings."]).map((item) => (
                                                <li key={item}>{item}</li>
                                            ))}
                                        </ul>
                                    </div>
                                </div>
                                <div className="text-muted-foreground mt-3 text-xs">
                                    {change.faculty ? `Faculty: ${change.faculty}` : "Faculty: TBA"}
                                    {change.changed_by ? ` • Changed by ${change.changed_by}` : ""}
                                </div>
                            </div>
                        ))}
                    </div>

                    <DialogFooter>
                        <Button type="button" variant="ghost" onClick={() => setShowScheduleChangesDialog(false)}>
                            Cancel
                        </Button>
                        <Button type="button" onClick={handleNotifyClassScheduleChanges} disabled={pendingScheduleChanges.length === 0}>
                            Notify Student
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <EnrollmentLoadChangeDialog
                open={showLoadChangeDialog}
                onOpenChange={setShowLoadChangeDialog}
                enrollmentId={enrollment.id}
                courseId={null}
                studentName={enrollment.student.full_name}
                currency={currency}
                onChanged={() => router.reload()}
            />

            <EnrollmentTransferDialog
                open={showTransferDialog}
                onOpenChange={setShowTransferDialog}
                enrollmentId={enrollment.id}
                studentName={enrollment.student.full_name}
                currency={currency}
                onTransferred={() => router.reload()}
            />

            <div className="space-y-6 pb-20">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <div className="flex items-start gap-3 sm:gap-4">
                        <Link
                            href={route("administrators.enrollments.index")}
                            className={buttonVariants({ variant: "ghost", size: "icon", className: "mt-1 h-9 w-9 shrink-0 rounded-full" })}
                        >
                            <ArrowLeft className="size-4" />
                            <span className="sr-only">Back to enrollments</span>
                        </Link>
                        <div>
                            <p className="text-muted-foreground mb-1 text-[11px] font-semibold tracking-[0.16em] uppercase">
                                Enrollment review · {enrollment.school_year}
                            </p>
                            <h1 className="text-2xl font-semibold tracking-[-0.02em] sm:text-3xl">Review enrollment</h1>
                            <p className="text-muted-foreground mt-1 max-w-xl text-sm leading-6">
                                Confirm the student’s academic placement, requirements, and payment readiness before advancing the workflow.
                            </p>
                        </div>
                    </div>
                    <div className="flex flex-wrap items-center gap-2">
                        <Badge variant="outline" className={cn("rounded-full px-3 py-1 text-xs", getStatusColor(enrollment.status))}>
                            <span className="mr-2 size-1.5 rounded-full bg-current" aria-hidden="true" />
                            {enrollment.status}
                        </Badge>
                        <Link
                            href={studentUrl}
                            className={buttonVariants({
                                variant: "outline",
                                className: "rounded-full shadow-sm transition-[transform,box-shadow] duration-200 active:scale-[0.98]",
                            })}
                        >
                            <UserIcon className="mr-2 size-4" />
                            Student record
                        </Link>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => setShowLoadChangeDialog(true)}
                            className="rounded-full shadow-sm transition-[transform,box-shadow] duration-200 active:scale-[0.98]"
                        >
                            <ArrowLeftRight className="mr-2 size-4" />
                            Add / Drop / Swap
                        </Button>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => setShowTransferDialog(true)}
                            className="rounded-full shadow-sm transition-[transform,box-shadow] duration-200 active:scale-[0.98]"
                        >
                            <School className="mr-2 size-4" />
                            Shift / Transfer
                        </Button>
                        <Link
                            href={route("administrators.enrollments.edit", enrollment.id)}
                            className={buttonVariants({
                                variant: "outline",
                                className: "rounded-full shadow-sm transition-[transform,box-shadow] duration-200 active:scale-[0.98]",
                            })}
                        >
                            <Pencil className="mr-2 size-4" />
                            Edit enrollment
                        </Link>
                    </div>
                </div>

                {headerActions}

                <section
                    aria-labelledby="student-record-heading"
                    className="border-border/70 bg-card/80 overflow-hidden rounded-2xl border shadow-sm backdrop-blur-xl"
                >
                    <div className="flex flex-col gap-5 p-5 sm:p-6 lg:flex-row lg:items-center lg:justify-between">
                        <div className="flex min-w-0 items-center gap-4">
                            <Avatar className="border-background size-16 shrink-0 border-2 shadow-md sm:size-20">
                                <AvatarFallback className="bg-primary/10 text-primary text-xl font-semibold sm:text-2xl">
                                    {enrollment.student.full_name
                                        .split(" ")
                                        .map((name) => name.charAt(0))
                                        .join("")
                                        .slice(0, 2)
                                        .toUpperCase()}
                                </AvatarFallback>
                            </Avatar>
                            <div className="min-w-0">
                                <p className="text-muted-foreground text-[11px] font-semibold tracking-[0.16em] uppercase">Student record</p>
                                <h2 id="student-record-heading" className="mt-1 truncate text-xl font-semibold tracking-[-0.02em] sm:text-2xl">
                                    <Link
                                        href={studentUrl}
                                        className="hover:text-primary inline-flex items-center gap-1.5 transition-colors hover:underline"
                                        title="View Student Record"
                                    >
                                        <span>{enrollment.student.full_name}</span>
                                        <ExternalLink className="size-4 shrink-0 opacity-60" />
                                    </Link>
                                </h2>
                                <div className="text-muted-foreground mt-2 flex flex-wrap items-center gap-x-3 gap-y-1 text-sm">
                                    <span className="font-medium">ID {enrollment.student.student_id}</span>
                                    {enrollment.student.email && <span className="truncate">{enrollment.student.email}</span>}
                                    <span className="text-muted-foreground/40">•</span>
                                    <Link
                                        href={studentUrl}
                                        className="text-primary inline-flex items-center gap-1 text-xs font-medium hover:underline"
                                    >
                                        View profile
                                    </Link>
                                </div>
                            </div>
                        </div>
                        <div className="grid grid-cols-2 gap-x-8 gap-y-4 border-t pt-4 text-sm sm:grid-cols-4 lg:min-w-[520px] lg:border-t-0 lg:border-l lg:pt-0 lg:pl-8">
                            <div>
                                <p className="text-muted-foreground text-[11px] font-semibold tracking-[0.12em] uppercase">Program</p>
                                <p className="mt-1 truncate font-medium">{enrollment.student.course_code ?? "Not assigned"}</p>
                            </div>
                            <div>
                                <p className="text-muted-foreground text-[11px] font-semibold tracking-[0.12em] uppercase">Academic year</p>
                                <p className="mt-1 font-medium">Year {enrollment.academic_year}</p>
                            </div>
                            <div>
                                <p className="text-muted-foreground text-[11px] font-semibold tracking-[0.12em] uppercase">Semester</p>
                                <p className="mt-1 font-medium">{enrollment.semester === 1 ? "1st" : "2nd"} semester</p>
                            </div>
                            <div>
                                <p className="text-muted-foreground text-[11px] font-semibold tracking-[0.12em] uppercase">Units</p>
                                <p className="mt-1 font-medium tabular-nums">{totalUnits} enrolled</p>
                            </div>
                        </div>
                    </div>
                    <div className="bg-muted/20 grid border-t sm:grid-cols-3">
                        <div className="flex items-center justify-between gap-3 px-5 py-4 sm:px-6">
                            <div>
                                <p className="text-muted-foreground text-xs">Review readiness</p>
                                <p className="mt-1 font-semibold">{pendingRequirements === 0 ? "Ready for decision" : "Needs attention"}</p>
                                <div className="mt-2 flex items-center gap-2">
                                    <Progress value={requirementsProgress} className="h-1.5 w-20" />
                                    <span className="text-muted-foreground text-[11px]">
                                        {reviewedRequirements}/{enrollment.requirements.length || 0} reviewed
                                    </span>
                                </div>
                            </div>
                            <div
                                className={cn(
                                    "rounded-full px-2.5 py-1 text-xs font-semibold",
                                    pendingRequirements === 0 ? "bg-emerald-500/10 text-emerald-600" : "bg-amber-500/10 text-amber-600",
                                )}
                            >
                                {pendingRequirements === 0 ? "Complete" : `${pendingRequirements} pending`}
                            </div>
                        </div>
                        <div className="flex items-center justify-between gap-3 border-t px-5 py-4 sm:border-t-0 sm:border-l sm:px-6">
                            <div>
                                <p className="text-muted-foreground text-xs">Assessment</p>
                                <p className="mt-1 font-semibold tabular-nums">{formatMoney(enrollment.tuition?.overall_tuition)}</p>
                            </div>
                            <Banknote className="text-muted-foreground size-5" aria-hidden="true" />
                        </div>
                        <div className="flex items-center justify-between gap-3 border-t px-5 py-4 sm:border-t-0 sm:border-l sm:px-6">
                            <div>
                                <p className="text-muted-foreground text-xs">Outstanding balance</p>
                                <p className={cn("mt-1 font-semibold tabular-nums", balanceDue > 0 ? "text-amber-600" : "text-emerald-600")}>
                                    {formatMoney(balanceDue)}
                                </p>
                            </div>
                            <WalletCards className="text-muted-foreground size-5" aria-hidden="true" />
                        </div>
                    </div>
                </section>

                <div className="grid items-start gap-6 xl:grid-cols-[minmax(280px,0.82fr)_minmax(0,1.8fr)]">
                    {/* Review rail */}
                    <aside className="space-y-6 xl:sticky xl:top-4">
                        <Card className="border-border/70 overflow-hidden rounded-2xl shadow-sm">
                            <CardHeader className="border-border/70 bg-muted/20 gap-1 border-b px-5 py-5">
                                <div className="flex items-start justify-between gap-3">
                                    <div>
                                        <CardTitle className="flex items-center gap-2 text-base tracking-[-0.01em]">
                                            <span className="bg-primary/10 text-primary flex size-8 items-center justify-center rounded-lg">
                                                <CheckCircle2 className="size-4" aria-hidden="true" />
                                            </span>
                                            Workflow status
                                        </CardTitle>
                                        <CardDescription className="mt-2">Complete the next available review action.</CardDescription>
                                    </div>
                                    <Badge
                                        variant="outline"
                                        className={cn("shrink-0 rounded-full px-2.5 py-1 text-xs", getStatusColor(enrollment.status))}
                                    >
                                        {enrollment.status}
                                    </Badge>
                                </div>
                                {enrollment.workflow.terminal_outcome === "cancelled" && (
                                    <div className="mt-3 flex items-start gap-2 rounded-lg border border-red-200 bg-red-50/70 p-3 text-sm text-red-800 dark:border-red-900 dark:bg-red-950/40 dark:text-red-200">
                                        <Ban className="mt-0.5 h-4 w-4 shrink-0" aria-hidden="true" />
                                        <div>
                                            <p className="font-semibold">Enrollment cancelled</p>
                                            <p className="mt-0.5 text-xs">
                                                No further workflow action is available. Reopen it if this was recorded in error.
                                            </p>
                                        </div>
                                    </div>
                                )}
                                <div className="pt-3">
                                    <div className="mb-2 flex items-center justify-between gap-3 text-xs">
                                        <span className="text-muted-foreground font-medium">Review progress</span>
                                        <span className="font-semibold tabular-nums">
                                            {workflowCompletedSteps}/{workflowTotalSteps} steps
                                        </span>
                                    </div>
                                    <Progress value={workflowProgress} className="h-1.5" />
                                    <p className="text-muted-foreground mt-2 text-xs leading-5">
                                        {enrollment.workflow.terminal_outcome === "cancelled"
                                            ? "This enrollment stopped at cancellation and can no longer transition."
                                            : currentWorkflowStep
                                              ? `Current step: ${currentWorkflowStep.label}`
                                              : enrollment.workflow.terminal_outcome
                                                ? "All workflow steps are complete."
                                                : "Review the available action to continue."}
                                    </p>
                                </div>
                            </CardHeader>
                            <CardContent className="space-y-5 p-5">
                                <ol aria-label="Enrollment workflow steps" className="space-y-1">
                                    {steps.map((step, idx) => {
                                        const stepStateLabel =
                                            step.status === "completed" ? "Completed" : step.status === "current" ? "In review" : "Pending";

                                        return (
                                            <li
                                                key={idx}
                                                aria-current={step.status === "current" ? "step" : undefined}
                                                className="relative flex gap-3 py-2"
                                            >
                                                <div className="relative flex w-7 shrink-0 justify-center">
                                                    {idx < steps.length - 1 && (
                                                        <span
                                                            className={cn(
                                                                "absolute top-8 bottom-[-0.5rem] w-px",
                                                                step.status === "completed" ? "bg-primary/60" : "bg-border",
                                                            )}
                                                            aria-hidden="true"
                                                        />
                                                    )}
                                                    <span
                                                        className={cn(
                                                            "relative z-10 flex size-7 items-center justify-center rounded-full border text-xs font-semibold",
                                                            step.status === "completed" && "border-primary bg-primary text-primary-foreground",
                                                            step.status === "current" &&
                                                                "border-primary bg-primary/10 text-primary ring-primary/10 ring-4 motion-safe:transition-shadow motion-safe:duration-300",
                                                            step.status === "pending" && "border-border bg-background text-muted-foreground",
                                                        )}
                                                    >
                                                        {step.status === "completed" ? (
                                                            <CheckCircle2 className="size-4" aria-hidden="true" />
                                                        ) : (
                                                            idx + 1
                                                        )}
                                                    </span>
                                                </div>
                                                <div className="min-w-0 flex-1 pt-0.5">
                                                    <div className="flex flex-wrap items-center justify-between gap-x-3 gap-y-1">
                                                        <p
                                                            className={cn(
                                                                "text-sm font-semibold",
                                                                step.status === "pending" && "text-muted-foreground",
                                                            )}
                                                        >
                                                            {step.label}
                                                        </p>
                                                        <span
                                                            className={cn(
                                                                "text-[11px] font-medium",
                                                                step.status === "completed" && "text-emerald-600",
                                                                step.status === "current" && "text-primary",
                                                                step.status === "pending" && "text-muted-foreground",
                                                            )}
                                                        >
                                                            {stepStateLabel}
                                                        </span>
                                                    </div>
                                                </div>
                                            </li>
                                        );
                                    })}
                                </ol>

                                <div className="border-primary/20 bg-primary/[0.045] rounded-xl border p-4">
                                    <p className="text-primary text-[11px] font-semibold tracking-[0.12em] uppercase">Next action</p>
                                    <div className="mt-3 flex items-start gap-3">
                                        <div className="bg-primary/10 text-primary flex size-8 shrink-0 items-center justify-center rounded-lg">
                                            <UserCheck className="size-4" aria-hidden="true" />
                                        </div>
                                        <div className="min-w-0">
                                            <p className="text-sm font-semibold">{nextActionLabel}</p>
                                            <p className="text-muted-foreground mt-1 text-xs leading-5">
                                                {currentWorkflowStep
                                                    ? `The enrollment is currently at ${currentWorkflowStep.label}.`
                                                    : "No additional workflow step is currently active."}
                                            </p>
                                        </div>
                                    </div>
                                </div>

                                <Separator />
                            </CardContent>
                        </Card>

                        <Card aria-label="Tuition summary" className="border-border/70 rounded-2xl shadow-sm">
                            <CardHeader className="bg-muted/20 gap-1 border-b px-5 py-5">
                                <CardTitle className="flex items-center gap-2 text-base tracking-[-0.01em]">
                                    <span className="flex size-8 items-center justify-center rounded-lg bg-emerald-500/10 text-emerald-600">
                                        <Banknote className="size-4" aria-hidden="true" />
                                    </span>
                                    Tuition summary
                                </CardTitle>
                                <CardDescription>Payment position for this enrollment.</CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-4 p-5">
                                <div className="border-border/70 bg-background/60 rounded-xl border p-4">
                                    <div className="flex items-start justify-between gap-3">
                                        <div>
                                            <p className="text-muted-foreground text-[11px] font-semibold tracking-[0.12em] uppercase">Amount due</p>
                                            <p className="mt-1 text-3xl font-semibold tracking-[-0.03em] tabular-nums">{formatMoney(balanceDue)}</p>
                                        </div>
                                        <Badge
                                            variant="outline"
                                            className={cn(
                                                "rounded-full px-2.5 py-1 text-[11px]",
                                                totalAssessment === 0
                                                    ? "border-border text-muted-foreground"
                                                    : balanceDue > 0
                                                      ? "border-amber-500/30 bg-amber-500/10 text-amber-600"
                                                      : "border-emerald-500/30 bg-emerald-500/10 text-emerald-600",
                                            )}
                                        >
                                            {totalAssessment === 0 ? "Not assessed" : balanceDue > 0 ? "Outstanding" : "Paid in full"}
                                        </Badge>
                                    </div>
                                    <div className="mt-5 space-y-2">
                                        <div className="flex items-center justify-between text-xs">
                                            <span className="text-muted-foreground">Payment progress</span>
                                            <span className="font-semibold tabular-nums">{paymentProgress}%</span>
                                        </div>
                                        <Progress value={paymentProgress} className="h-1.5" />
                                        <div className="text-muted-foreground flex justify-between text-[11px]">
                                            <span>{formatMoney(totalPaid)} paid</span>
                                            <span>{formatMoney(totalAssessment)} total</span>
                                        </div>
                                    </div>
                                </div>

                                <div className="divide-border/70 border-border/70 divide-y overflow-hidden rounded-xl border">
                                    <div className="flex items-center justify-between gap-3 px-4 py-3 text-sm">
                                        <span className="text-muted-foreground">Total assessment</span>
                                        <span className="font-medium tabular-nums">{formatMoney(totalAssessment)}</span>
                                    </div>
                                    <div className="flex items-center justify-between gap-3 px-4 py-3 text-sm">
                                        <span className="text-muted-foreground">Required downpayment</span>
                                        <span className="font-medium tabular-nums">{formatMoney(requiredDownpayment)}</span>
                                    </div>
                                    <div className="flex items-center justify-between gap-3 px-4 py-3 text-sm">
                                        <span className="text-muted-foreground">Total paid</span>
                                        <span className="font-medium text-emerald-600 tabular-nums">{formatMoney(totalPaid)}</span>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                    </aside>

                    {/* Right Main Content */}
                    <main className="min-w-0">
                        <Tabs value={activeTab} onValueChange={setActiveTab} className="w-full">
                            <TabsList className="bg-muted/50 border-border/70 sticky top-2 z-20 h-auto w-full justify-start gap-1 rounded-xl border p-1.5 shadow-sm backdrop-blur-xl">
                                <TabsTrigger
                                    value="classes"
                                    className="data-[state=active]:bg-background flex-1 gap-2 rounded-lg py-2.5 shadow-none data-[state=active]:shadow-sm md:flex-none"
                                >
                                    <ClipboardList className="size-4" aria-hidden="true" />
                                    Classes
                                </TabsTrigger>
                                <TabsTrigger
                                    value="financial"
                                    className="data-[state=active]:bg-background flex-1 gap-2 rounded-lg py-2.5 shadow-none data-[state=active]:shadow-sm md:flex-none"
                                >
                                    <WalletCards className="size-4" aria-hidden="true" />
                                    Financial
                                </TabsTrigger>
                                <TabsTrigger
                                    value="resources"
                                    className="data-[state=active]:bg-background flex-1 gap-2 rounded-lg py-2.5 shadow-none data-[state=active]:shadow-sm md:flex-none"
                                >
                                    <Files className="size-4" aria-hidden="true" />
                                    Documents
                                </TabsTrigger>
                                {enrollment.requirements.length > 0 && (
                                    <TabsTrigger
                                        value="requirements"
                                        className="data-[state=active]:bg-background flex-1 gap-2 rounded-lg py-2.5 shadow-none data-[state=active]:shadow-sm md:flex-none"
                                    >
                                        <ListChecks className="size-4" aria-hidden="true" />
                                        Requirements
                                    </TabsTrigger>
                                )}
                            </TabsList>

                            <EnrollmentDetailTabs
                                enrollment={enrollment}
                                auth={auth}
                                formatMoney={formatMoney}
                                requiredDownpayment={requiredDownpayment}
                                totalPaid={totalPaid}
                                balanceDue={balanceDue}
                                reviewingRequirementId={reviewingRequirementId}
                                onReviewRequirement={reviewEnrollmentRequirement}
                                onStartWaiveRequirement={(requirementId) => {
                                    setWaivingRequirementId(requirementId);
                                    setWaiverReason("");
                                }}
                            />
                        </Tabs>
                    </main>
                </div>
            </div>

            {/* Undo Cancellation Dialog */}
            <Dialog open={showUndoCancellationDialog} onOpenChange={setShowUndoCancellationDialog}>
                <DialogContent className="sm:max-w-[500px]">
                    <DialogHeader>
                        <DialogTitle className="flex items-center gap-2">
                            <Undo2 className="h-5 w-5" /> Undo cancellation
                        </DialogTitle>
                        <DialogDescription>
                            This restores {enrollment.student.full_name} to the step they were on before the cancellation. Recorded payments are never
                            reversed here.
                        </DialogDescription>
                    </DialogHeader>

                    <form onSubmit={submitUndoCancellation} className="space-y-4 py-2">
                        <div className="grid gap-2">
                            <Label htmlFor="undo-cancellation-reason">Reason (required)</Label>
                            <Textarea
                                id="undo-cancellation-reason"
                                required
                                rows={3}
                                placeholder="Why was the cancellation recorded in error?"
                                value={undoCancellationReason}
                                onChange={(e) => setUndoCancellationReason(e.target.value)}
                            />
                        </div>

                        <DialogFooter>
                            <Button type="button" variant="ghost" onClick={() => setShowUndoCancellationDialog(false)}>
                                Keep cancelled
                            </Button>
                            <Button type="submit">Undo cancellation</Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            {/* Restore Deleted Subjects Dialog */}
            <RestoreSubjectsDialog
                open={showUndoDialog}
                onOpenChange={setShowUndoDialog}
                recentDeletions={recent_deletions}
                selectedDeletions={selectedDeletions}
                onToggleSelection={toggleDeletionSelection}
                onSelectAll={selectAllDeletions}
                onRestore={handleRestoreSubjects}
                restoring={restoringSubjects}
            />
            <Dialog
                open={waivingRequirementId !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setWaivingRequirementId(null);
                        setWaiverReason("");
                    }
                }}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Waive enrollment requirement</DialogTitle>
                        <DialogDescription>
                            This is an audited exception. Record a specific reason that another administrator can review later.
                        </DialogDescription>
                    </DialogHeader>
                    <div className="space-y-2">
                        <Label htmlFor="requirement-waiver-reason">Reason</Label>
                        <Textarea
                            id="requirement-waiver-reason"
                            value={waiverReason}
                            onChange={(event) => setWaiverReason(event.target.value)}
                            placeholder="Explain why this requirement may be waived"
                        />
                    </div>
                    <DialogFooter>
                        <Button type="button" variant="outline" onClick={() => setWaivingRequirementId(null)}>
                            Cancel
                        </Button>
                        <Button
                            type="button"
                            disabled={!waiverReason.trim() || reviewingRequirementId !== null}
                            onClick={() => {
                                if (waivingRequirementId !== null) {
                                    reviewEnrollmentRequirement(waivingRequirementId, "waive", waiverReason.trim());
                                }
                            }}
                        >
                            Confirm waiver
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <AlertDialog open={showUndoCashierDialog} onOpenChange={setShowUndoCashierDialog}>
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>Undo cashier verification?</AlertDialogTitle>
                        <AlertDialogDescription>
                            This will revert the enrollment status, but it will not automatically reverse any recorded financial transactions or
                            payments.
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel>Cancel</AlertDialogCancel>
                        <AlertDialogAction
                            className="bg-destructive text-destructive-foreground hover:bg-destructive/90"
                            onClick={confirmUndoCashier}
                        >
                            Undo Verification
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>

            <Dialog
                open={pendingPolicyTransition !== null}
                onOpenChange={(open) => {
                    if (!open && !submittingPolicyTransition) {
                        setPendingPolicyTransition(null);
                        setPolicyTransitionReason("");
                    }
                }}
            >
                <DialogContent className="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>{pendingPolicyTransition?.label ?? "Confirm transition"}</DialogTitle>
                        <DialogDescription>
                            This workflow transition requires an audited reason that will be recorded in the enrollment history.
                        </DialogDescription>
                    </DialogHeader>
                    <div className="space-y-2">
                        <Label htmlFor="policy-transition-reason">Audited Reason</Label>
                        <Textarea
                            id="policy-transition-reason"
                            value={policyTransitionReason}
                            onChange={(event) => setPolicyTransitionReason(event.target.value)}
                            placeholder="Explain why this transition is being performed"
                            rows={3}
                            autoFocus
                        />
                    </div>
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => {
                                setPendingPolicyTransition(null);
                                setPolicyTransitionReason("");
                            }}
                            disabled={submittingPolicyTransition}
                        >
                            Cancel
                        </Button>
                        <Button
                            type="button"
                            disabled={!policyTransitionReason.trim() || submittingPolicyTransition}
                            onClick={() => {
                                if (pendingPolicyTransition) {
                                    executePolicyTransition(pendingPolicyTransition, policyTransitionReason);
                                }
                            }}
                        >
                            {submittingPolicyTransition ? "Submitting..." : "Confirm Transition"}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </AdminLayout>
    );
}

