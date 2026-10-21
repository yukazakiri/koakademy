import { Button } from "@/components/ui/button";
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from "@/components/ui/dialog";
import { Label } from "@/components/ui/label";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Separator } from "@/components/ui/separator";
import { Skeleton } from "@/components/ui/skeleton";
import { Switch } from "@/components/ui/switch";
import { Textarea } from "@/components/ui/textarea";
import { cn } from "@/lib/utils";
import axios from "axios";
import { AlertTriangle } from "lucide-react";
import { useCallback, useEffect, useMemo, useState } from "react";
import { route } from "ziggy-js";
import type { ApplicantCancellationReason, ApplicantRow } from "./types";

type ApplicantCancellationDialogProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    applicant: ApplicantRow | null;
    reasons: ApplicantCancellationReason[];
    onConfirm: (payload: { reason: string; note: string; sendEmail: boolean }) => void;
    isCancelling: boolean;
};

type PreviewPayload = {
    subject: string;
    body: string;
    salutation: string;
    reason_label: string;
    reason_description: string;
    requires_note: boolean;
};

export function ApplicantCancellationDialog({ open, onOpenChange, applicant, reasons, onConfirm, isCancelling }: ApplicantCancellationDialogProps) {
    const [reason, setReason] = useState<string>("");
    const [note, setNote] = useState<string>("");
    const [sendEmail, setSendEmail] = useState(true);
    const [preview, setPreview] = useState<PreviewPayload | null>(null);
    const [isPreviewing, setIsPreviewing] = useState(false);

    const selectedReason = useMemo(() => reasons.find((entry) => entry.value === reason) ?? null, [reasons, reason]);

    // Reset every time the dialog opens so a second cancellation never inherits the last one.
    useEffect(() => {
        if (!open) return;

        setReason("");
        setNote("");
        setSendEmail(true);
        setPreview(null);
    }, [open, applicant?.id]);

    const loadPreview = useCallback(async () => {
        if (!applicant || !reason) {
            setPreview(null);
            return;
        }

        setIsPreviewing(true);

        try {
            const response = await axios.get<PreviewPayload>(
                route("administrators.enrollments.applicants.cancel.preview", { student: applicant.id }),
                { params: { reason, note: note || undefined } },
            );
            setPreview(response.data);
        } catch {
            setPreview(null);
        } finally {
            setIsPreviewing(false);
        }
    }, [applicant, reason, note]);

    // The preview follows the reason and the note the registrar writes.
    useEffect(() => {
        if (!open || !reason) return;

        const timer = setTimeout(() => {
            void loadPreview();
        }, 400);

        return () => clearTimeout(timer);
    }, [open, reason, note, loadPreview]);

    const noteRequired = selectedReason?.requires_note ?? false;
    const noteMissing = noteRequired && note.trim() === "";
    // A closure the applicant asked for is not a rejection, so the wording and
    // the confirm button say so.
    const applicantInitiated = selectedReason?.applicant_initiated ?? false;
    const canSubmit = reason !== "" && !noteMissing && !isCancelling;

    const handleConfirm = () => {
        if (!canSubmit) return;

        onConfirm({ reason, note: note.trim(), sendEmail });
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-h-[92vh] overflow-y-auto sm:max-w-2xl">
                <DialogHeader>
                    <DialogTitle className="flex items-center gap-2">
                        <AlertTriangle className="size-5 text-amber-600 dark:text-amber-400" aria-hidden="true" />
                        {applicantInitiated ? "Confirm this closure" : "Close this application"}
                    </DialogTitle>
                    <DialogDescription>
                        {applicant ? (
                            applicantInitiated ? (
                                <>
                                    <strong>{applicant.name}</strong> asked to withdraw. Closing the application records that decision and confirms it
                                    to them.
                                </>
                            ) : (
                                <>
                                    This closes the application of <strong>{applicant.name}</strong> and takes it out of the admissions queue. The
                                    applicant is notified with the message below.
                                </>
                            )
                        ) : (
                            "This closes the application and notifies the applicant."
                        )}
                    </DialogDescription>
                </DialogHeader>

                <div className="grid gap-5 py-2">
                    <div className="space-y-2">
                        <Label htmlFor="cancellation-reason">
                            Reason for closing <span className="text-destructive">*</span>
                        </Label>
                        <Select value={reason} onValueChange={setReason}>
                            <SelectTrigger id="cancellation-reason" className="w-full">
                                <SelectValue placeholder="Select a reason" />
                            </SelectTrigger>
                            <SelectContent>
                                {reasons.map((entry) => (
                                    <SelectItem key={entry.value} value={entry.value}>
                                        {entry.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        {selectedReason && <p className="text-muted-foreground text-xs leading-relaxed">{selectedReason.description}</p>}
                    </div>

                    <div className="space-y-2">
                        <Label htmlFor="cancellation-note">
                            Note to the applicant
                            {noteRequired ? (
                                <span className="text-destructive"> *</span>
                            ) : (
                                <span className="text-muted-foreground font-normal"> (optional)</span>
                            )}
                        </Label>
                        <Textarea
                            id="cancellation-note"
                            rows={3}
                            value={note}
                            onChange={(event) => setNote(event.target.value)}
                            placeholder={
                                noteRequired
                                    ? "Explain the decision. This text is included in the email to the applicant."
                                    : "Add context for the applicant. This text is included in the email."
                            }
                            className={cn(noteMissing && "border-destructive")}
                        />
                        {noteMissing && <p className="text-destructive text-xs">Please explain the decision for this reason.</p>}
                    </div>

                    <div className="space-y-2">
                        <Label>Email preview</Label>
                        {!reason ? (
                            <div className="text-muted-foreground rounded-lg border border-dashed p-4 text-center text-xs">
                                Choose a reason to see the message that will be sent.
                            </div>
                        ) : (
                            <div className="bg-muted/40 space-y-2 rounded-lg border p-3">
                                {isPreviewing && !preview ? (
                                    <>
                                        <Skeleton className="h-4 w-3/4" />
                                        <Skeleton className="h-24 w-full" />
                                    </>
                                ) : preview ? (
                                    <>
                                        <div className="space-y-1">
                                            <p className="text-muted-foreground text-[11px] font-semibold tracking-[0.08em] uppercase">Subject</p>
                                            <p className="text-sm font-medium">{preview.subject}</p>
                                        </div>
                                        <Separator />
                                        <div className="space-y-1">
                                            <p className="text-muted-foreground text-[11px] font-semibold tracking-[0.08em] uppercase">Message</p>
                                            <p className="text-sm leading-relaxed whitespace-pre-line">
                                                {preview.salutation}
                                                {"\n\n"}
                                                {preview.body}
                                            </p>
                                        </div>
                                    </>
                                ) : (
                                    <p className="text-muted-foreground text-xs">The preview could not be loaded.</p>
                                )}
                            </div>
                        )}
                    </div>

                    <div className="flex items-center justify-between gap-4 rounded-lg border p-3">
                        <div className="space-y-0.5">
                            <Label htmlFor="send-cancellation-email" className="cursor-pointer">
                                Email the applicant
                            </Label>
                            <p className="text-muted-foreground text-xs">
                                {applicant?.email
                                    ? `The message will be sent to ${applicant.email}.`
                                    : "This applicant has no email address on file, so no message can be sent."}
                            </p>
                        </div>
                        <Switch id="send-cancellation-email" checked={sendEmail} onCheckedChange={setSendEmail} disabled={!applicant?.email} />
                    </div>
                </div>

                <DialogFooter className="gap-2 sm:justify-end">
                    <Button variant="ghost" onClick={() => onOpenChange(false)} disabled={isCancelling}>
                        Keep application open
                    </Button>
                    <Button variant="destructive" onClick={handleConfirm} disabled={!canSubmit} className="bg-red-600 text-white hover:bg-red-700">
                        {isCancelling ? "Closing…" : applicantInitiated ? "Confirm closure" : "Close application"}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
