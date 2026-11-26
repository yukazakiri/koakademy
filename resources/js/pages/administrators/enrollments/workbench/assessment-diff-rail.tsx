import { Alert, AlertDescription, AlertTitle } from "@/components/reui/alert";
import { Badge } from "@/components/reui/badge";
import { Frame, FramePanel } from "@/components/reui/frame";
import { IconTile } from "@/components/reui/icon-tile";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Switch } from "@/components/ui/switch";
import { Textarea } from "@/components/ui/textarea";
import { cn } from "@/lib/utils";
import {
    AlertTriangle,
    ArrowRight,
    Award,
    Calculator,
    CheckCircle2,
    Loader2,
    Lock,
    Plus,
    Receipt,
    Wallet,
} from "lucide-react";
import type { EnrollmentDraftState } from "./use-enrollment-draft";

const CUSTOM_LEGACY_DISCOUNT_VALUE = "__legacy__";

export interface AssessmentDiffRailProps {
    draft: EnrollmentDraftState;
    submitLabel?: string;
    hideSubmitButton?: boolean;
}

export function AssessmentDiffRail({
    draft,
    submitLabel,
    hideSubmitButton = false,
}: AssessmentDiffRailProps) {
    const {
        isEdit,
        isTerminalLocked,
        canManageTuitionFees,
        enrollment,
        selectedStudent,
        subjectsEnrolled,
        discountOptions,
        discount,
        discountId,
        selectedDiscountValue,
        handleDiscountChange,
        setDiscountDialogOpen,
        miscellaneousFee,
        setMiscellaneousFee,
        downpayment,
        setDownpayment,
        remarks,
        setRemarks,
        notifyStudent,
        setNotifyStudent,
        changeReason,
        setChangeReason,
        totals,
        diff,
        unitCap,
        isUnitOverload,
        submitting,
        fieldErrors,
        handleSubmit,
        formatCurrency,
    } = draft;

    const isRevisionLocked = Boolean(enrollment?.tuition?.active_revision_id);
    const totalPaid = enrollment?.tuition?.total_paid ?? 0;
    const tuitionControlsDisabled = !canManageTuitionFees || isRevisionLocked || isTerminalLocked;

    const unitRatio = unitCap > 0 ? Math.min(100, Math.round((totals.totalUnits / unitCap) * 100)) : 0;
    const discountPercent = parseInt(discount, 10) || 0;
    const hasScholarship = Boolean(
        selectedStudent?.scholarship_type && selectedStudent.scholarship_type !== "none",
    );
    const scholarshipLabel =
        selectedStudent?.scholarship_label || selectedStudent?.scholarship_type || "Scholar";

    const fieldErrorEntries = Object.entries(fieldErrors).filter(([, message]) => Boolean(message));

    const isSubmitDisabled =
        submitting ||
        isTerminalLocked ||
        subjectsEnrolled.length === 0 ||
        !selectedStudent ||
        (!isEdit && Boolean(selectedStudent?.existing_enrollment));

    const showDiffPanel = isEdit && (diff.isDirty || isRevisionLocked || totalPaid > 0);

    const effectiveSubmitLabel = submitLabel ?? (isEdit ? "Save Changes" : "Create Enrollment");

    return (
        <Frame
            stacked
            className="w-full min-w-0 overflow-x-hidden lg:sticky lg:top-5 lg:max-h-[calc(100vh-2.5rem)] lg:overflow-y-auto"
        >
            {/* Panel 1 — Load KPIs & Unit Cap Bar */}
            <FramePanel fit className="shrink-0 space-y-3.5">
                <div className="flex items-center justify-between gap-2">
                    <div className="flex min-w-0 items-center gap-2.5">
                        <IconTile variant="soft" size="sm" className="text-primary">
                            <Calculator />
                        </IconTile>
                        <div className="min-w-0">
                            <h2 className="truncate text-sm font-semibold tracking-tight text-foreground">
                                Assessment &amp; Load
                            </h2>
                            <p className="truncate text-xs text-muted-foreground">
                                Live credit units &amp; tuition ledger
                            </p>
                        </div>
                    </div>
                    {isEdit && diff.isDirty && (
                        <Badge variant="warning-light" size="sm">
                            Unsaved
                        </Badge>
                    )}
                </div>

                <div className="grid grid-cols-3 gap-2">
                    <div className="min-w-0 rounded-lg border border-border bg-muted/40 px-2.5 py-2 text-center">
                        <div className="text-base font-semibold tabular-nums text-foreground">
                            {subjectsEnrolled.length}
                        </div>
                        <div className="truncate text-[11px] text-muted-foreground">Subjects</div>
                    </div>
                    <div className="min-w-0 rounded-lg border border-border bg-muted/40 px-2.5 py-2 text-center">
                        <div className="text-base font-semibold tabular-nums text-foreground">
                            {totals.totalLectureUnits}
                        </div>
                        <div className="truncate text-[11px] text-muted-foreground">Lec Units</div>
                    </div>
                    <div className="min-w-0 rounded-lg border border-border bg-muted/40 px-2.5 py-2 text-center">
                        <div className="text-base font-semibold tabular-nums text-foreground">
                            {totals.totalLabUnits}
                        </div>
                        <div className="truncate text-[11px] text-muted-foreground">Lab Units</div>
                    </div>
                </div>

                <div className="space-y-1.5">
                    <div className="flex items-center justify-between gap-2 text-xs">
                        <span className="font-medium text-muted-foreground">Unit Load Cap</span>
                        <div className="flex items-center gap-1.5">
                            <span
                                className={cn(
                                    "font-semibold tabular-nums",
                                    isUnitOverload
                                        ? "text-warning-foreground dark:text-warning"
                                        : "text-foreground",
                                )}
                            >
                                {totals.totalUnits} / {unitCap} units
                            </span>
                            {isUnitOverload && (
                                <Badge variant="warning-light" size="xs">
                                    Overload
                                </Badge>
                            )}
                        </div>
                    </div>
                    <div
                        className="h-2 w-full overflow-hidden rounded-full bg-muted"
                        role="progressbar"
                        aria-valuenow={totals.totalUnits}
                        aria-valuemin={0}
                        aria-valuemax={unitCap}
                        aria-label="Unit load progress"
                    >
                        <div
                            className={cn(
                                "h-full rounded-full transition-all duration-300",
                                isUnitOverload ? "bg-warning" : "bg-primary",
                            )}
                            style={{ width: `${unitRatio}%` }}
                        />
                    </div>
                </div>
            </FramePanel>

            {/* Panel 2 — Live Before/After Diff Box (Edit mode) */}
            {showDiffPanel && (
                <FramePanel fit className="shrink-0 space-y-3 bg-muted/20">
                    {diff.isDirty && (
                        <div className="space-y-2.5">
                            <div className="flex items-center justify-between gap-2">
                                <span className="text-xs font-semibold tracking-tight text-foreground">
                                    Pending Changes
                                </span>
                                <div className="flex flex-wrap items-center justify-end gap-1">
                                    {diff.addedSubjectIds.length > 0 && (
                                        <Badge variant="success-light" size="xs" className="tabular-nums">
                                            +{diff.addedSubjectIds.length} added
                                        </Badge>
                                    )}
                                    {diff.removedSubjects.length > 0 && (
                                        <Badge
                                            variant="destructive-light"
                                            size="xs"
                                            className="tabular-nums"
                                        >
                                            -{diff.removedSubjects.length} removed
                                        </Badge>
                                    )}
                                    {diff.changedSectionSubjectIds.length > 0 && (
                                        <Badge variant="info-light" size="xs" className="tabular-nums">
                                            {diff.changedSectionSubjectIds.length} section{" "}
                                            {diff.changedSectionSubjectIds.length === 1 ? "swap" : "swaps"}
                                        </Badge>
                                    )}
                                </div>
                            </div>

                            <div className="rounded-lg border border-border bg-card px-3 py-2.5">
                                <div className="flex flex-wrap items-center justify-between gap-2 text-xs">
                                    <div className="flex min-w-0 flex-wrap items-center gap-1.5 tabular-nums">
                                        <span className="text-muted-foreground line-through">
                                            {formatCurrency(diff.previousTotal)}
                                        </span>
                                        <ArrowRight className="size-3 shrink-0 text-muted-foreground" />
                                        <span className="font-semibold text-foreground">
                                            {formatCurrency(totals.overallTotal)}
                                        </span>
                                    </div>
                                    <Badge
                                        variant={
                                            diff.tuitionDelta > 0
                                                ? "warning-light"
                                                : diff.tuitionDelta < 0
                                                  ? "success-light"
                                                  : "outline"
                                        }
                                        size="xs"
                                        className="tabular-nums"
                                    >
                                        Δ {diff.tuitionDelta > 0 ? "+" : ""}
                                        {diff.tuitionDelta < 0 ? "-" : ""}
                                        {formatCurrency(Math.abs(diff.tuitionDelta))}
                                    </Badge>
                                </div>
                            </div>
                        </div>
                    )}

                    {isRevisionLocked && (
                        <Alert variant="warning" className="py-2 text-xs">
                            <Lock className="size-3.5" />
                            <AlertTitle className="text-xs">Finance revision locked</AlertTitle>
                            <AlertDescription className="text-xs leading-relaxed">
                                Academic changes will flag Finance for review; assessment totals stay frozen
                                until Finance reconciles the revision.
                            </AlertDescription>
                        </Alert>
                    )}

                    {totalPaid > 0 && (
                        <div className="space-y-2">
                            <div className="grid grid-cols-2 gap-2">
                                <div className="min-w-0 rounded-lg border border-border bg-card px-2.5 py-2">
                                    <div className="flex items-center gap-1 text-[11px] text-muted-foreground">
                                        <Wallet className="size-3 shrink-0" />
                                        <span className="truncate">Paid</span>
                                    </div>
                                    <div className="mt-0.5 truncate text-xs font-semibold tabular-nums text-success-foreground dark:text-success">
                                        {formatCurrency(totalPaid)}
                                    </div>
                                </div>
                                <div className="min-w-0 rounded-lg border border-border bg-card px-2.5 py-2">
                                    <div className="flex items-center gap-1 text-[11px] text-muted-foreground">
                                        <Receipt className="size-3 shrink-0" />
                                        <span className="truncate">Projected Balance</span>
                                    </div>
                                    <div className="mt-0.5 truncate text-xs font-semibold tabular-nums text-foreground">
                                        {formatCurrency(diff.projectedBalanceAfterPaid)}
                                    </div>
                                </div>
                            </div>

                            {diff.projectedOverpaymentCredit > 0 && (
                                <Alert variant="warning" className="py-2 text-xs">
                                    <AlertTriangle className="size-3.5" />
                                    <AlertTitle className="text-xs">Overpayment credit</AlertTitle>
                                    <AlertDescription className="text-xs leading-relaxed">
                                        Reducing total tuition below paid receipts creates an overpayment
                                        credit of{" "}
                                        <span className="font-semibold tabular-nums text-foreground">
                                            {formatCurrency(diff.projectedOverpaymentCredit)}
                                        </span>
                                        .
                                    </AlertDescription>
                                </Alert>
                            )}
                        </div>
                    )}
                </FramePanel>
            )}

            {/* Panel 3 — Financial Controls & Breakdown */}
            <FramePanel fit className="shrink-0 space-y-3.5">
                {hasScholarship && (
                    <div className="space-y-1">
                        <Badge variant="info-light" size="default" className="max-w-full truncate">
                            <Award className="size-3 shrink-0" />
                            <span className="truncate">Scholar: {scholarshipLabel}</span>
                        </Badge>
                        {discountPercent === 0 && (
                            <p className="text-[11px] text-muted-foreground">
                                Consider applying a matching scholarship discount.
                            </p>
                        )}
                    </div>
                )}

                <div className="space-y-1.5">
                    <div className="flex items-center justify-between gap-2">
                        <Label htmlFor="workbench-discount" className="text-xs">
                            Discount
                        </Label>
                        {!canManageTuitionFees && (
                            <span className="text-[11px] text-muted-foreground">Finance managed</span>
                        )}
                    </div>
                    <div className="flex min-w-0 items-center gap-1.5">
                        <div className="min-w-0 flex-1">
                            <Select
                                value={selectedDiscountValue}
                                onValueChange={handleDiscountChange}
                                disabled={tuitionControlsDisabled}
                            >
                                <SelectTrigger id="workbench-discount" className="h-8 w-full">
                                    <SelectValue placeholder="No Discount" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="none">No Discount</SelectItem>
                                    {discountId === null && discountPercent > 0 && (
                                        <SelectItem value={CUSTOM_LEGACY_DISCOUNT_VALUE}>
                                            Current Discount ({discount}%)
                                        </SelectItem>
                                    )}
                                    {discountOptions.map((option) => (
                                        <SelectItem key={option.id} value={String(option.id)}>
                                            {option.name} ({option.percentage}%)
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                        <Button
                            type="button"
                            variant="outline"
                            size="icon"
                            className="shrink-0"
                            aria-label="Create discount preset"
                            title="Create discount preset"
                            disabled={tuitionControlsDisabled}
                            onClick={() => setDiscountDialogOpen(true)}
                        >
                            <Plus className="size-4" />
                        </Button>
                    </div>
                </div>

                <div className="space-y-1.5">
                    <Label htmlFor="workbench-misc-fee" className="text-xs">
                        Miscellaneous Fee
                    </Label>
                    <Input
                        id="workbench-misc-fee"
                        type="number"
                        min={0}
                        step="0.01"
                        value={miscellaneousFee}
                        onChange={(event) => setMiscellaneousFee(event.target.value)}
                        disabled={tuitionControlsDisabled}
                        className="tabular-nums"
                    />
                </div>

                <div className="space-y-1.5 border-t border-border pt-3 text-xs tabular-nums">
                    <div className="flex items-center justify-between gap-2">
                        <span className="text-muted-foreground">Lecture Fees</span>
                        <span className="font-medium text-foreground">
                            {formatCurrency(totals.lectureFees)}
                        </span>
                    </div>
                    {totals.discountAmount > 0 && (
                        <div className="flex items-center justify-between gap-2 text-success-foreground dark:text-success">
                            <span>Discount (-{discountPercent}%)</span>
                            <span className="font-medium">-{formatCurrency(totals.discountAmount)}</span>
                        </div>
                    )}
                    <div className="flex items-center justify-between gap-2">
                        <span className="text-muted-foreground">Lab Fees</span>
                        <span className="font-medium text-foreground">
                            {formatCurrency(totals.labFees)}
                        </span>
                    </div>
                    {totals.modularFees > 0 && (
                        <div className="flex items-center justify-between gap-2">
                            <span className="text-muted-foreground">Modular Fees</span>
                            <span className="font-medium text-foreground">
                                {formatCurrency(totals.modularFees)}
                            </span>
                        </div>
                    )}
                    <div className="flex items-center justify-between gap-2">
                        <span className="text-muted-foreground">Misc Fee</span>
                        <span className="font-medium text-foreground">
                            {formatCurrency(totals.miscFee)}
                        </span>
                    </div>
                    {totals.additionalFeesTotal > 0 && (
                        <div className="flex items-center justify-between gap-2">
                            <span className="text-muted-foreground">Additional Fees</span>
                            <span className="font-medium text-foreground">
                                {formatCurrency(totals.additionalFeesTotal)}
                            </span>
                        </div>
                    )}
                    {totals.excludedCount > 0 && (
                        <div className="flex items-center justify-between gap-2 text-warning-foreground dark:text-warning">
                            <span>Excluded from tuition</span>
                            <span className="font-medium">
                                {totals.excludedCount} {totals.excludedCount === 1 ? "subject" : "subjects"}
                            </span>
                        </div>
                    )}
                </div>

                <div className="flex items-baseline justify-between gap-2 border-t border-border pt-3">
                    <span className="text-sm font-semibold text-foreground">Total Assessment</span>
                    <span className="text-lg font-bold tabular-nums text-primary">
                        {formatCurrency(totals.overallTotal)}
                    </span>
                </div>

                <div className="space-y-1.5">
                    <Label htmlFor="workbench-downpayment" className="text-xs">
                        Required Downpayment
                    </Label>
                    <Input
                        id="workbench-downpayment"
                        type="number"
                        min={0}
                        step="0.01"
                        value={downpayment}
                        onChange={(event) => setDownpayment(event.target.value)}
                        disabled={tuitionControlsDisabled}
                        className="tabular-nums"
                    />
                    <p className="text-[11px] text-muted-foreground">
                        Set to ₱0.00 for full scholars or sponsored trainees.
                    </p>
                </div>

                <div
                    className={cn(
                        "flex items-center justify-between gap-2 rounded-lg border px-3 py-2.5 text-sm font-semibold tabular-nums",
                        totals.projectedBalance > 0
                            ? "border-warning/25 bg-warning/10 text-warning-foreground dark:border-warning/30 dark:bg-warning/15 dark:text-warning"
                            : "border-success/25 bg-success/10 text-success-foreground dark:border-success/30 dark:bg-success/15 dark:text-success",
                    )}
                >
                    <span>Projected Balance</span>
                    <span>{formatCurrency(totals.projectedBalance)}</span>
                </div>

                {fieldErrorEntries.length > 0 && (
                    <Alert variant="destructive" className="py-2 text-xs">
                        <AlertTriangle className="size-3.5" />
                        <AlertTitle className="text-xs">Please review before saving</AlertTitle>
                        <AlertDescription className="w-full text-xs">
                            <ul className="w-full list-disc space-y-0.5 pl-4">
                                {fieldErrorEntries.map(([key, message]) => (
                                    <li key={key} className="break-words">
                                        {message}
                                    </li>
                                ))}
                            </ul>
                        </AlertDescription>
                    </Alert>
                )}
            </FramePanel>

            {/* Panel 4 — Remarks, Student Notification & Submit */}
            <FramePanel fit className="shrink-0 space-y-3.5">
                <div className="space-y-1.5">
                    <Label htmlFor="workbench-remarks" className="text-xs">
                        Remarks <span className="font-normal text-muted-foreground">(optional)</span>
                    </Label>
                    <Textarea
                        id="workbench-remarks"
                        value={remarks}
                        onChange={(event) => setRemarks(event.target.value)}
                        placeholder="Internal registrar notes for this enrollment..."
                        rows={2}
                        disabled={isTerminalLocked}
                        className="min-h-14 resize-none text-xs"
                    />
                </div>

                {isEdit && (
                    <div className="space-y-3 border-t border-border pt-3">
                        <div className="flex items-start justify-between gap-3">
                            <div className="space-y-0.5">
                                <Label
                                    htmlFor="workbench-notify-student"
                                    className="cursor-pointer text-xs font-medium"
                                >
                                    Notify student of changes
                                </Label>
                                <p className="text-[11px] text-muted-foreground">
                                    {notifyStudent && selectedStudent?.email
                                        ? `Email will be sent to ${selectedStudent.email}`
                                        : "Send updated schedule & assessment email on save"}
                                </p>
                            </div>
                            <Switch
                                id="workbench-notify-student"
                                checked={notifyStudent}
                                onCheckedChange={setNotifyStudent}
                                disabled={isTerminalLocked}
                            />
                        </div>

                        <div className="space-y-1.5">
                            <Label htmlFor="workbench-change-reason" className="text-xs">
                                Change reason / audit note
                            </Label>
                            <Textarea
                                id="workbench-change-reason"
                                value={changeReason}
                                onChange={(event) => setChangeReason(event.target.value)}
                                placeholder="e.g., Added retake subject or balanced section load"
                                rows={2}
                                disabled={isTerminalLocked}
                                className="min-h-14 resize-none text-xs"
                            />
                        </div>
                    </div>
                )}

                {!hideSubmitButton && (
                    <Button
                        type="button"
                        size="lg"
                        className="w-full"
                        disabled={isSubmitDisabled}
                        onClick={() => handleSubmit()}
                    >
                        {submitting ? (
                            <>
                                <Loader2 className="size-4 animate-spin" />
                                {isEdit ? "Saving Changes..." : "Creating Enrollment..."}
                            </>
                        ) : (
                            <>
                                <CheckCircle2 className="size-4" />
                                {effectiveSubmitLabel}
                            </>
                        )}
                    </Button>
                )}
            </FramePanel>
        </Frame>
    );
}
