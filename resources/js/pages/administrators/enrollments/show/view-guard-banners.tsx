import { Alert, AlertAction, AlertDescription, AlertTitle } from "@/components/reui/alert";
import { Button, buttonVariants } from "@/components/ui/button";
import { cn } from "@/lib/utils";
import { Link } from "@inertiajs/react";
import {
    AlertTriangle,
    Ban,
    BellRing,
    CalendarClock,
    ClockAlert,
    Lock,
    RotateCcw,
    ShieldAlert,
    SlidersHorizontal,
    Trash2,
    Unlink,
} from "lucide-react";
import { formatShortDate } from "../enrollment-utils";
import type { EnrollmentPeriodSummary } from "../types";
import type { DocumentLinks, EnrollmentData, ShowEnrollmentPageProps } from "./types";
import type { ViewDialogKind } from "./view-header-bar";

export interface ViewGuardBannersProps {
    enrollment: EnrollmentData;
    enrollmentPeriod: EnrollmentPeriodSummary | null | undefined;
    scheduleConflictsCount: number;
    documentLinks: DocumentLinks;
    auth: ShowEnrollmentPageProps["auth"];
    onJumpToTab: (tab: string) => void;
    onOpenDialog: (dialog: ViewDialogKind) => void;
}

export function ViewGuardBanners({
    enrollment,
    enrollmentPeriod,
    scheduleConflictsCount,
    documentLinks,
    auth,
    onJumpToTab,
    onOpenDialog,
}: ViewGuardBannersProps) {
    const activeBlockingHolds = enrollment.holds.filter(
        (hold) =>
            hold.status === "active" &&
            (hold.severity === "blocking" || hold.blocks_enrollment),
    );
    const hasFinanceLock = Boolean(
        enrollment.tuition?.active_revision_id || enrollment.tuition?.needs_finance_review,
    );
    const missingClassesCount = enrollment.missing_classes.length;
    const pendingColCount = enrollment.load_changes_summary?.pending_count ?? 0;
    const pendingScheduleNotificationsCount =
        enrollment.pending_class_schedule_changes?.length ?? 0;
    const isCancelledOrTerminal =
        !enrollment.is_trashed &&
        (enrollment.workflow.terminal_outcome === "cancelled" ||
            enrollment.workflow.is_terminal_locked);
    const isPeriodClosed = Boolean(
        enrollmentPeriod &&
            (enrollmentPeriod.phase === "closed" || enrollmentPeriod.is_manually_closed),
    );
    const isPeriodLate = Boolean(
        enrollmentPeriod && !isPeriodClosed && enrollmentPeriod.phase === "late",
    );

    const hasAnyBanner =
        enrollment.is_trashed ||
        isCancelledOrTerminal ||
        activeBlockingHolds.length > 0 ||
        hasFinanceLock ||
        scheduleConflictsCount > 0 ||
        (missingClassesCount > 0 && !enrollment.is_trashed && !isCancelledOrTerminal) ||
        pendingColCount > 0 ||
        pendingScheduleNotificationsCount > 0 ||
        isPeriodClosed ||
        isPeriodLate;

    if (!hasAnyBanner) {
        return null;
    }

    return (
        <section aria-label="Enrollment alerts and guards" className="space-y-2">
            {/* 1. Trashed */}
            {enrollment.is_trashed && (
                <Alert variant="destructive">
                    <Trash2 className="size-4" />
                    <AlertTitle>This enrollment is in the trash</AlertTitle>
                    <AlertDescription>
                        Archived on {formatShortDate(enrollment.deleted_at)}. Workflow transitions
                        and edits are locked until the record is restored.
                    </AlertDescription>
                    {auth.can_restore && (
                        <AlertAction>
                            <Button
                                type="button"
                                size="sm"
                                variant="outline"
                                onClick={() => onOpenDialog("restore")}
                            >
                                <RotateCcw className="mr-1.5 size-3.5" />
                                Restore
                            </Button>
                        </AlertAction>
                    )}
                </Alert>
            )}

            {/* 2. Cancelled / Terminal Locked */}
            {isCancelledOrTerminal && (
                <Alert variant="destructive">
                    <Ban className="size-4" />
                    <AlertTitle>
                        {enrollment.workflow.terminal_outcome === "cancelled"
                            ? "Enrollment cancelled"
                            : "Enrollment is terminal-locked"}
                    </AlertTitle>
                    <AlertDescription>
                        This record no longer counts as actively enrolled for the term, and any
                        reserved class seats were released.
                    </AlertDescription>
                    {auth.can_reopen && (
                        <AlertAction>
                            <Button
                                type="button"
                                size="sm"
                                variant="outline"
                                onClick={() => onOpenDialog("reopen")}
                            >
                                <RotateCcw className="mr-1.5 size-3.5" />
                                Reopen
                            </Button>
                        </AlertAction>
                    )}
                </Alert>
            )}

            {/* 3. Blocking Holds */}
            {activeBlockingHolds.length > 0 && (
                <Alert variant="destructive">
                    <ShieldAlert className="size-4" />
                    <AlertTitle>
                        {activeBlockingHolds.length} blocking student hold
                        {activeBlockingHolds.length === 1 ? "" : "s"} active
                    </AlertTitle>
                    <AlertDescription>
                        {activeBlockingHolds[0].office_label}: {activeBlockingHolds[0].title}
                        {activeBlockingHolds.length > 1
                            ? ` (+${activeBlockingHolds.length - 1} more)`
                            : ""}
                        . Resolve or override holds before final clearance.
                    </AlertDescription>
                    <AlertAction>
                        <Button
                            type="button"
                            size="sm"
                            variant="outline"
                            onClick={() => onJumpToTab("holds")}
                        >
                            Manage Holds
                        </Button>
                    </AlertAction>
                </Alert>
            )}

            {/* 4. Finance Revision Lock / Needs Finance Review */}
            {hasFinanceLock && (
                <Alert variant="warning">
                    <Lock className="size-4" />
                    <AlertTitle>
                        {enrollment.tuition?.active_revision_id
                            ? "Finance revision lock active"
                            : "Tuition assessment requires finance review"}
                    </AlertTitle>
                    <AlertDescription>
                        Billing figures are pending reconciliation in Tuition Adjustments before
                        settlement is finalized.
                    </AlertDescription>
                    <AlertAction>
                        <Link
                            href={documentLinks.tuition_adjustments_url}
                            className={cn(buttonVariants({ variant: "outline", size: "sm" }))}
                        >
                            Open Adjustments
                        </Link>
                    </AlertAction>
                </Alert>
            )}

            {/* 5. Schedule Clashes */}
            {scheduleConflictsCount > 0 && (
                <Alert variant="destructive">
                    <CalendarClock className="size-4" />
                    <AlertTitle>
                        {scheduleConflictsCount} schedule clash
                        {scheduleConflictsCount === 1 ? "" : "es"} detected
                    </AlertTitle>
                    <AlertDescription>
                        Overlapping meeting windows exist across assigned class sections. Reassign
                        or swap conflicting sections.
                    </AlertDescription>
                    <AlertAction>
                        <Button
                            type="button"
                            size="sm"
                            variant="outline"
                            onClick={() => onJumpToTab("schedule")}
                        >
                            Inspect Schedule
                        </Button>
                    </AlertAction>
                </Alert>
            )}

            {/* 6. Missing Sections */}
            {missingClassesCount > 0 && !enrollment.is_trashed && !isCancelledOrTerminal && (
                <Alert variant="warning">
                    <Unlink className="size-4" />
                    <AlertTitle>
                        {missingClassesCount} enrolled subject
                        {missingClassesCount === 1 ? "" : "s"} missing a class section
                    </AlertTitle>
                    <AlertDescription>
                        The student is registered for the subject load but still needs section seat
                        assignments.
                    </AlertDescription>
                    <AlertAction>
                        <Button
                            type="button"
                            size="sm"
                            variant="outline"
                            onClick={() => onJumpToTab("subjects")}
                        >
                            Inspect Load
                        </Button>
                    </AlertAction>
                </Alert>
            )}

            {/* 7. Pending COL Requests */}
            {pendingColCount > 0 && (
                <Alert variant="info">
                    <SlidersHorizontal className="size-4" />
                    <AlertTitle>
                        {pendingColCount} pending Change of Load request
                        {pendingColCount === 1 ? "" : "s"}
                    </AlertTitle>
                    <AlertDescription>
                        Pending add/drop/swap requests await review before final load settlement.
                    </AlertDescription>
                    <AlertAction>
                        <Button
                            type="button"
                            size="sm"
                            variant="outline"
                            onClick={() => onJumpToTab("changes")}
                        >
                            Open COL
                        </Button>
                    </AlertAction>
                </Alert>
            )}

            {/* 8. Pending Schedule Change Notifications */}
            {pendingScheduleNotificationsCount > 0 && (
                <Alert variant="info">
                    <BellRing className="size-4" />
                    <AlertTitle>
                        {pendingScheduleNotificationsCount} unnotified class schedule change
                        {pendingScheduleNotificationsCount === 1 ? "" : "s"}
                    </AlertTitle>
                    <AlertDescription>
                        Section times or rooms changed since enrollment. Preview and notify the
                        student.
                    </AlertDescription>
                    <AlertAction>
                        <Button
                            type="button"
                            size="sm"
                            variant="outline"
                            onClick={() => onOpenDialog("schedule_changes")}
                        >
                            Preview &amp; Notify
                        </Button>
                    </AlertAction>
                </Alert>
            )}

            {/* 9. Closed / Late Enrollment Period */}
            {(isPeriodClosed || isPeriodLate) && enrollmentPeriod && (
                <Alert variant={isPeriodClosed ? "destructive" : "warning"}>
                    {isPeriodClosed ? (
                        <ClockAlert className="size-4" />
                    ) : (
                        <AlertTriangle className="size-4" />
                    )}
                    <AlertTitle>
                        Enrollment period: {enrollmentPeriod.phase_label}
                    </AlertTitle>
                    <AlertDescription>
                        {enrollmentPeriod.summary_text}
                        {isPeriodLate && enrollmentPeriod.auto_apply_late_fee
                            ? ` (${enrollmentPeriod.late_fee_label})`
                            : ""}
                    </AlertDescription>
                </Alert>
            )}
        </section>
    );
}
