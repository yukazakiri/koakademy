import { Button } from "@/components/ui/button";
import { Checkbox } from "@/components/ui/checkbox";
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from "@/components/ui/dialog";
import { Label } from "@/components/ui/label";
import { AlertTriangle, CheckCircle2 } from "lucide-react";
import { useState } from "react";
import { getSchedulingReadiness, type ReadinessClass } from "./scheduling-readiness";

type Props = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    classes: ReadinessClass[];
    conflictCount: number;
    draftCount: number;
    termLabel: string;
    isPublishing: boolean;
    onPublish: () => Promise<void>;
    onResolveConflicts: () => void;
};

export default function SchedulingReviewDialog({
    open,
    onOpenChange,
    classes,
    conflictCount,
    draftCount,
    termLabel,
    isPublishing,
    onPublish,
    onResolveConflicts,
}: Props) {
    const [acknowledged, setAcknowledged] = useState(false);
    const items = getSchedulingReadiness(classes);
    const needsAttention = items.filter((item) => item.warnings.length > 0);
    return (
        <Dialog
            open={open}
            onOpenChange={(value) => {
                if (!isPublishing) {
                    setAcknowledged(false);
                    onOpenChange(value);
                }
            }}
        >
            <DialogContent
                className="max-h-[90dvh] overflow-y-auto sm:max-w-2xl"
                showCloseButton={!isPublishing}
                onEscapeKeyDown={(event) => {
                    if (isPublishing) event.preventDefault();
                }}
                onPointerDownOutside={(event) => {
                    if (isPublishing) event.preventDefault();
                }}
            >
                <DialogHeader>
                    <DialogTitle className="text-xl">Review term schedule</DialogTitle>
                    <DialogDescription>
                        {termLabel}. This review includes all {classes.length} classes in the term, including classes outside your current filters.
                    </DialogDescription>
                </DialogHeader>
                <div className="bg-muted/30 grid grid-cols-3 gap-2 rounded-lg border p-4 text-sm">
                    <div>
                        <p className="text-muted-foreground text-xs">Draft sessions</p>
                        <p className="mt-1 text-2xl font-semibold tabular-nums">{draftCount}</p>
                    </div>
                    <div>
                        <p className="text-muted-foreground text-xs">Conflicts</p>
                        <p className="mt-1 text-2xl font-semibold tabular-nums">{conflictCount}</p>
                    </div>
                    <div>
                        <p className="text-muted-foreground text-xs">Classes to review</p>
                        <p className="mt-1 text-2xl font-semibold tabular-nums">{needsAttention.length}</p>
                    </div>
                </div>
                {conflictCount > 0 ? (
                    <div className="border-destructive/30 bg-destructive/5 rounded-lg border p-4 text-sm" role="alert">
                        <p className="text-destructive flex items-center gap-2 font-semibold">
                            <AlertTriangle className="size-4" /> Resolve overlapping sessions first
                        </p>
                        <p className="text-muted-foreground mt-2">Room, instructor, or block section overlaps must be resolved before publishing.</p>
                        <Button variant="outline" className="mt-3" onClick={onResolveConflicts}>
                            Show conflicts
                        </Button>
                    </div>
                ) : (
                    <p className="flex items-center gap-2 text-sm">
                        <CheckCircle2 className="size-4 text-emerald-600" /> No room, instructor, or block section conflicts detected.
                    </p>
                )}
                {needsAttention.length > 0 && (
                    <section aria-label="Classes requiring review" className="space-y-3">
                        <h3 className="text-sm font-semibold">Outstanding assignments and hours</h3>
                        <div className="max-h-64 divide-y overflow-y-auto rounded-lg border">
                            {needsAttention.map((item) => (
                                <div key={item.id} className="p-3 text-sm">
                                    <p className="font-medium">
                                        {item.subject_code} <span className="text-muted-foreground font-normal">· Section {item.section}</span>
                                    </p>
                                    <p className="text-muted-foreground mt-1 text-xs">{item.warnings.join(" · ")}</p>
                                </div>
                            ))}
                        </div>
                        <div className="flex items-start gap-3 rounded-lg border p-4">
                            <Checkbox
                                id="publish-acknowledge"
                                checked={acknowledged}
                                onCheckedChange={(value) => setAcknowledged(value === true)}
                                disabled={isPublishing}
                            />
                            <Label htmlFor="publish-acknowledge" className="text-sm leading-5 font-normal">
                                I reviewed these outstanding items and want to publish the scheduled draft sessions.
                            </Label>
                        </div>
                    </section>
                )}
                <p className="text-muted-foreground text-xs">Publishing updates draft sessions. Classes without sessions remain unscheduled.</p>
                <DialogFooter>
                    <Button variant="outline" disabled={isPublishing} onClick={() => onOpenChange(false)}>
                        Continue planning
                    </Button>
                    <Button
                        disabled={isPublishing || conflictCount > 0 || draftCount === 0 || (needsAttention.length > 0 && !acknowledged)}
                        onClick={() => void onPublish()}
                    >
                        {isPublishing ? "Publishing sessions…" : `Publish ${draftCount} session${draftCount === 1 ? "" : "s"}`}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
