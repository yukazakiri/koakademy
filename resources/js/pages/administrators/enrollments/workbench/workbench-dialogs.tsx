import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogMedia,
    AlertDialogTitle,
} from "@/components/reui/alert-dialog";
import { Badge } from "@/components/reui/badge";
import { Button } from "@/components/ui/button";
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from "@/components/ui/dialog";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { AlertTriangle, Loader2, LogOut, ShieldAlert } from "lucide-react";
import type { PendingOverloadSection } from "./types";
import type { EnrollmentDraftState } from "./use-enrollment-draft";

export interface WorkbenchDialogsProps {
    draft: EnrollmentDraftState;
    unsavedDialogOpen: boolean;
    onUnsavedDialogOpenChange: (open: boolean) => void;
    onConfirmLeave: () => void;
}

export function WorkbenchDialogs({
    draft,
    unsavedDialogOpen,
    onUnsavedDialogOpenChange,
    onConfirmLeave,
}: WorkbenchDialogsProps) {
    const {
        isEdit,
        enrollment,
        subjectsEnrolled,
        diff,
        submitting,
        confirmGradeLoss,
        setConfirmGradeLoss,
        pendingGradeLossOpen,
        setPendingGradeLossOpen,
        pendingOverloadConfirmation,
        setPendingOverloadConfirmation,
        discountDialogOpen,
        setDiscountDialogOpen,
        discountName,
        setDiscountName,
        discountPercentage,
        setDiscountPercentage,
        discountErrors,
        creatingDiscount,
        handleCreateDiscount,
        submitEnrollment,
    } = draft;

    const handleConfirmGradeRemoval = () => {
        setConfirmGradeLoss(true);
        setPendingGradeLossOpen(false);

        const initialSubjects = enrollment?.subjects ?? [];
        const fullSections = subjectsEnrolled
            .map((sub): PendingOverloadSection | null => {
                if (!sub.class_id || !sub.sections) return null;
                const sec = sub.sections.find((s) => s.id === sub.class_id);
                if (!sec || !sec.is_full) return null;
                const orig = initialSubjects.find((o) => o.subject_id === sub.subject_id);
                if (isEdit && orig?.class_id === sub.class_id) return null;
                return {
                    subjectCode: sub.subject_code,
                    subjectTitle: sub.subject_title,
                    section: sec.section,
                    enrolledCount: sec.enrolled_count,
                    maxSlots: sec.max_slots,
                };
            })
            .filter((item): item is PendingOverloadSection => item !== null);

        if (fullSections.length > 0) {
            setPendingOverloadConfirmation({ fullSections });
            return;
        }

        submitEnrollment(false, true);
    };

    return (
        <>
            {/* 1. Create Discount Preset Dialog */}
            <Dialog open={discountDialogOpen} onOpenChange={setDiscountDialogOpen}>
                <DialogContent className="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>Create Discount Preset</DialogTitle>
                        <DialogDescription>
                            Add a reusable discount preset for this and future enrollments.
                        </DialogDescription>
                    </DialogHeader>
                    <form className="space-y-4" onSubmit={handleCreateDiscount}>
                        <div className="space-y-1.5">
                            <Label htmlFor="workbench-discount-name">Discount name</Label>
                            <Input
                                id="workbench-discount-name"
                                value={discountName}
                                onChange={(event) => setDiscountName(event.target.value)}
                                placeholder="e.g., Athletic Scholarship"
                                maxLength={120}
                                autoFocus
                            />
                            {discountErrors.name && (
                                <p className="text-xs text-destructive">{discountErrors.name}</p>
                            )}
                        </div>
                        <div className="space-y-1.5">
                            <Label htmlFor="workbench-discount-percentage">Discount percentage (%)</Label>
                            <Input
                                id="workbench-discount-percentage"
                                type="number"
                                min={1}
                                max={100}
                                step={1}
                                value={discountPercentage}
                                onChange={(event) => setDiscountPercentage(event.target.value)}
                                placeholder="1–100"
                                className="tabular-nums"
                            />
                            {discountErrors.percentage && (
                                <p className="text-xs text-destructive">{discountErrors.percentage}</p>
                            )}
                        </div>
                        <DialogFooter>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setDiscountDialogOpen(false)}
                                disabled={creatingDiscount}
                            >
                                Cancel
                            </Button>
                            <Button
                                type="submit"
                                disabled={
                                    creatingDiscount ||
                                    !discountName.trim() ||
                                    !discountPercentage.trim()
                                }
                            >
                                {creatingDiscount && <Loader2 className="size-4 animate-spin" />}
                                Create Discount
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            {/* 2. Class Capacity Overload Confirmation */}
            <AlertDialog
                open={Boolean(pendingOverloadConfirmation)}
                onOpenChange={(open) => {
                    if (!open) setPendingOverloadConfirmation(null);
                }}
            >
                <AlertDialogContent className="sm:max-w-md">
                    <AlertDialogHeader>
                        <AlertDialogMedia className="bg-warning/15 text-warning-foreground dark:text-warning">
                            <AlertTriangle />
                        </AlertDialogMedia>
                        <AlertDialogTitle>Confirm Class Capacity Overload</AlertDialogTitle>
                        <AlertDialogDescription>
                            One or more selected sections have reached maximum capacity. Confirming will
                            record an audited overload approval and save this enrollment.
                        </AlertDialogDescription>
                    </AlertDialogHeader>

                    {pendingOverloadConfirmation && (
                        <div className="max-h-52 space-y-2 overflow-y-auto rounded-lg border border-warning/25 bg-warning/5 p-3">
                            {pendingOverloadConfirmation.fullSections.map((item) => (
                                <div
                                    key={`${item.subjectCode}-${item.section}`}
                                    className="flex items-start justify-between gap-2 rounded-md border border-border bg-card px-2.5 py-2 text-xs"
                                >
                                    <div className="min-w-0">
                                        <div className="truncate font-semibold text-foreground">
                                            {item.subjectCode} · Section {item.section}
                                        </div>
                                        <div className="truncate text-muted-foreground">
                                            {item.subjectTitle}
                                        </div>
                                    </div>
                                    <Badge
                                        variant="warning-light"
                                        size="xs"
                                        className="shrink-0 tabular-nums"
                                    >
                                        {item.enrolledCount}/{item.maxSlots}
                                    </Badge>
                                </div>
                            ))}
                        </div>
                    )}

                    <AlertDialogFooter>
                        <AlertDialogCancel disabled={submitting}>Cancel</AlertDialogCancel>
                        <AlertDialogAction
                            disabled={submitting}
                            onClick={() => submitEnrollment(true, confirmGradeLoss)}
                        >
                            {submitting && <Loader2 className="size-4 animate-spin" />}
                            Confirm Overload &amp; Save
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>

            {/* 3. Graded Subject Removal Confirmation */}
            <AlertDialog
                open={pendingGradeLossOpen}
                onOpenChange={(open) => {
                    if (!open) setPendingGradeLossOpen(false);
                }}
            >
                <AlertDialogContent className="sm:max-w-md">
                    <AlertDialogHeader>
                        <AlertDialogMedia className="bg-destructive/15 text-destructive">
                            <ShieldAlert />
                        </AlertDialogMedia>
                        <AlertDialogTitle>Remove Graded Subject(s)?</AlertDialogTitle>
                        <AlertDialogDescription>
                            Dropping subjects with existing grades will permanently delete their recorded
                            grade data. Review the affected subjects before confirming.
                        </AlertDialogDescription>
                    </AlertDialogHeader>

                    {diff.gradedRemovals.length > 0 && (
                        <div className="max-h-52 space-y-2 overflow-y-auto rounded-lg border border-destructive/25 bg-destructive/5 p-3">
                            {diff.gradedRemovals.map((subject) => (
                                <div
                                    key={subject.subject_id}
                                    className="flex flex-col gap-1 rounded-md border border-border bg-card px-2.5 py-2 text-xs"
                                >
                                    <div className="flex items-center justify-between gap-2">
                                        <span className="truncate font-semibold text-foreground">
                                            {subject.subject_code}
                                        </span>
                                        <Badge variant="destructive-light" size="xs" className="shrink-0">
                                            Graded
                                        </Badge>
                                    </div>
                                    <div className="truncate text-muted-foreground">
                                        {subject.subject_title}
                                    </div>
                                    <div className="rounded bg-muted/60 px-2 py-1 font-mono text-[11px] text-foreground">
                                        {subject.grade_summary || "Recorded grade entries will be removed"}
                                    </div>
                                </div>
                            ))}
                        </div>
                    )}

                    <AlertDialogFooter>
                        <AlertDialogCancel disabled={submitting}>Cancel</AlertDialogCancel>
                        <AlertDialogAction
                            variant="destructive"
                            disabled={submitting}
                            onClick={handleConfirmGradeRemoval}
                        >
                            {submitting && <Loader2 className="size-4 animate-spin" />}
                            Confirm Grade Removal &amp; Save
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>

            {/* 4. Unsaved Changes Confirmation */}
            <AlertDialog open={unsavedDialogOpen} onOpenChange={onUnsavedDialogOpenChange}>
                <AlertDialogContent className="sm:max-w-md">
                    <AlertDialogHeader>
                        <AlertDialogMedia className="bg-warning/15 text-warning-foreground dark:text-warning">
                            <LogOut />
                        </AlertDialogMedia>
                        <AlertDialogTitle>Discard unsaved changes?</AlertDialogTitle>
                        <AlertDialogDescription>
                            You have unsaved edits to this enrollment draft. Leaving now will discard all
                            pending subject, section, and tuition changes.
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel>Stay</AlertDialogCancel>
                        <AlertDialogAction variant="destructive" onClick={onConfirmLeave}>
                            Discard &amp; Leave
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </>
    );
}
