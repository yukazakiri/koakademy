import { show as showEnrollmentAction } from "@/actions/App/Http/Controllers/AdministratorEnrollmentManagementController";
import { Alert, AlertAction, AlertDescription, AlertTitle } from "@/components/reui/alert";
import { Badge } from "@/components/reui/badge";
import { buttonVariants } from "@/components/ui/button";
import { Checkbox } from "@/components/ui/checkbox";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { cn } from "@/lib/utils";
import { Link } from "@inertiajs/react";
import { AlertTriangle, Ban, CalendarClock, Lock, ShieldAlert, Wallet } from "lucide-react";
import type { EnrollmentDraftState } from "./use-enrollment-draft";

interface GuardBannersProps {
    draft: EnrollmentDraftState;
}

export function GuardBanners({ draft }: GuardBannersProps) {
    const {
        isEdit,
        isTerminalLocked,
        enrollment,
        selectedStudent,
        enrollmentPeriod,
        windowRequiresOverride,
        overrideWindow,
        setOverrideWindow,
        blockingHolds,
        holdsRequireOverride,
        overrideHolds,
        setOverrideHolds,
        overrideHoldsReason,
        setOverrideHoldsReason,
        formatCurrency,
    } = draft;

    const tuition = enrollment?.tuition;
    const isRevisionLocked = Boolean(isEdit && tuition?.active_revision_id);
    const totalPaid = Number(tuition?.total_paid ?? 0);
    const hasPayments = isEdit && totalPaid > 0;
    const existingEnrollment = !isEdit ? selectedStudent?.existing_enrollment : null;

    return (
        <div className="space-y-2.5">
            {isTerminalLocked && (
                <Alert variant="destructive">
                    <Ban className="size-4" />
                    <AlertTitle>Enrollment record is locked ({enrollment?.status})</AlertTitle>
                    <AlertDescription>
                        Cancelled or rejected enrollments cannot be edited directly. Restore or undo cancellation from the enrollment dossier first.
                    </AlertDescription>
                </Alert>
            )}

            {existingEnrollment && (
                <Alert variant="destructive">
                    <AlertTriangle className="size-4" />
                    <AlertTitle>Duplicate term enrollment detected</AlertTitle>
                    <AlertDescription>
                        {selectedStudent?.full_name} already has an active enrollment (#{existingEnrollment.id} · {existingEnrollment.status}) for{" "}
                        {existingEnrollment.school_year} Sem {existingEnrollment.semester}. Edit the existing record instead of creating a duplicate.
                    </AlertDescription>
                    <AlertAction>
                        <Link
                            href={showEnrollmentAction.url(existingEnrollment.id)}
                            className={cn(buttonVariants({ variant: "outline", size: "sm" }))}
                        >
                            Open #{existingEnrollment.id}
                        </Link>
                    </AlertAction>
                </Alert>
            )}

            {isRevisionLocked && (
                <Alert variant="warning">
                    <Lock className="size-4" />
                    <AlertTitle>Finance assessment revision is locked</AlertTitle>
                    <AlertDescription>
                        Revision #{tuition?.active_revision_id} is active. You can update academic subjects/sections, and Finance will be flagged to reconcile totals while discounts and charges stay frozen.
                    </AlertDescription>
                </Alert>
            )}

            {hasPayments && !isRevisionLocked && (
                <Alert variant="info">
                    <Wallet className="size-4" />
                    <AlertTitle>
                        Payments already recorded ({formatCurrency(totalPaid)} paid
                        {enrollment?.latest_invoice?.document_number
                            ? ` · Invoice ${enrollment.latest_invoice.document_number}`
                            : ""}
                        )
                    </AlertTitle>
                    <AlertDescription>
                        Updating subjects or fees will recalculate the remaining balance due. If the new total falls below {formatCurrency(totalPaid)}, an overpayment credit will be created.
                    </AlertDescription>
                </Alert>
            )}

            {enrollmentPeriod && (windowRequiresOverride || (!isEdit && enrollmentPeriod.is_late)) && (
                <Alert variant={windowRequiresOverride ? "destructive" : "warning"}>
                    <CalendarClock className="size-4" />
                    <AlertTitle className="flex flex-wrap items-center gap-2">
                        <span>
                            {windowRequiresOverride
                                ? isEdit
                                    ? "Add / Drop window is closed"
                                    : `Registration window: ${enrollmentPeriod.phase_label}`
                                : `Late registration active (${enrollmentPeriod.summary_text})`}
                        </span>
                        <Badge variant={windowRequiresOverride ? "destructive-light" : "warning-light"} size="xs">
                            {enrollmentPeriod.phase_label}
                        </Badge>
                    </AlertTitle>
                    <AlertDescription className="space-y-2">
                        <p>
                            {windowRequiresOverride
                                ? isEdit
                                    ? "Adding or dropping subjects after the Add/Drop cutoff requires an administrator override."
                                    : enrollmentPeriod.summary_text
                                : enrollmentPeriod.auto_apply_late_fee && enrollmentPeriod.late_fee_amount > 0
                                  ? `A late fee (${enrollmentPeriod.late_fee_label}: ${formatCurrency(enrollmentPeriod.late_fee_amount)}) will be appended automatically on submit.`
                                  : enrollmentPeriod.summary_text}
                        </p>
                        {windowRequiresOverride && (
                            <div className="flex items-center gap-2 pt-1">
                                <Checkbox
                                    id="wb-override-window"
                                    checked={overrideWindow}
                                    onCheckedChange={(checked) => setOverrideWindow(checked === true)}
                                />
                                <Label htmlFor="wb-override-window" className="cursor-pointer text-xs font-medium">
                                    Enable administrator window override
                                </Label>
                            </div>
                        )}
                    </AlertDescription>
                </Alert>
            )}

            {blockingHolds.length > 0 && (
                <Alert variant={holdsRequireOverride ? "destructive" : "warning"}>
                    <ShieldAlert className="size-4" />
                    <AlertTitle className="flex flex-wrap items-center gap-2">
                        <span>
                            {blockingHolds.length} active blocking hold{blockingHolds.length === 1 ? "" : "s"}
                        </span>
                        <Badge variant="destructive-light" size="xs">
                            {isEdit ? "Blocks Add/Drop" : "Blocks Enrollment"}
                        </Badge>
                    </AlertTitle>
                    <AlertDescription className="space-y-2.5">
                        <ul className="space-y-1 text-xs">
                            {blockingHolds.map((hold) => (
                                <li key={hold.id} className="flex flex-wrap items-center gap-1.5">
                                    <Badge variant="outline" size="xs">
                                        {hold.office_label}
                                    </Badge>
                                    <span className="font-medium text-foreground">{hold.title}</span>
                                    {hold.reason && (
                                        <span className="text-muted-foreground">— {hold.reason}</span>
                                    )}
                                </li>
                            ))}
                        </ul>

                        {holdsRequireOverride && (
                            <div className="space-y-2 rounded-md border border-destructive/25 bg-background/60 p-2.5">
                                <div className="flex items-center gap-2">
                                    <Checkbox
                                        id="wb-override-holds"
                                        checked={overrideHolds}
                                        onCheckedChange={(checked) => setOverrideHolds(checked === true)}
                                    />
                                    <Label htmlFor="wb-override-holds" className="cursor-pointer text-xs font-medium">
                                        Override blocking hold(s) with audited justification
                                    </Label>
                                </div>
                                {overrideHolds && (
                                    <Input
                                        value={overrideHoldsReason}
                                        onChange={(e) => setOverrideHoldsReason(e.target.value)}
                                        placeholder="Enter approval memo or registrar justification (min 5 chars)…"
                                        className="h-8 text-xs"
                                    />
                                )}
                            </div>
                        )}
                    </AlertDescription>
                </Alert>
            )}
        </div>
    );
}
