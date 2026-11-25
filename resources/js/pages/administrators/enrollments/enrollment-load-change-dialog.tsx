import {
    approve as approveLoadChangeRoute,
    index as loadChangesIndexRoute,
    preview as loadChangePreviewRoute,
    reject as rejectLoadChangeRoute,
    store as storeLoadChangeRoute,
} from "@/actions/App/Http/Controllers/AdministratorEnrollmentLoadChangeController";
import { getSubjectSections } from "@/actions/App/Http/Controllers/AdministratorEnrollmentManagementController";
import { Alert, AlertDescription, AlertTitle } from "@/components/reui/alert";
import { Badge } from "@/components/reui/badge";
import { Frame, FramePanel } from "@/components/reui/frame";
import { Button } from "@/components/ui/button";
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from "@/components/ui/dialog";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Switch } from "@/components/ui/switch";
import { Tabs, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { Textarea } from "@/components/ui/textarea";
import { cn } from "@/lib/utils";
import axios from "axios";
import {
    AlertTriangle,
    ArrowLeftRight,
    Check,
    Loader2,
    MinusCircle,
    PlusCircle,
    Printer,
    X,
} from "lucide-react";
import { useCallback, useEffect, useState } from "react";
import { toast } from "sonner";
import { formatMoney, formatShortDate } from "./enrollment-utils";

interface LoadChangeRecord {
    id: number;
    reference_number: string;
    change_type: "add" | "drop" | "swap";
    status: "pending" | "approved" | "rejected";
    added_subject_code: string | null;
    added_subject_title: string | null;
    added_class_section: string | null;
    dropped_subject_code: string | null;
    dropped_subject_title: string | null;
    units_delta: number;
    tuition_delta: number;
    previous_tuition_total: number;
    new_tuition_total: number;
    within_window: boolean;
    reason: string;
    requested_by: string;
    approved_by: string | null;
    approved_at: string | null;
    rejected_by: string | null;
    rejection_reason: string | null;
    created_at: string | null;
    slip_url: string;
}

interface LoadChangesIndexPayload {
    course_id: number | null;
    load_changes: LoadChangeRecord[];
    enrolled_subjects: Array<{
        id: number;
        subject_id: number;
        code: string;
        title: string;
        section: string | null;
        units: number;
    }>;
    available_subjects: Array<{
        id: number;
        code: string;
        title: string;
        units: number;
    }>;
    window: {
        add_drop_open: boolean;
        enforce_windows: boolean;
        add_drop_deadline_at: string | null;
    };
    blocking_holds_count: number;
}

interface LoadChangePreview {
    units_delta: number;
    tuition_delta: number;
    previous_tuition_total: number;
    new_tuition_total: number;
    within_window: boolean;
}

interface SectionOption {
    id: number;
    section: string;
    label: string;
    is_full: boolean;
}

type EnrollmentLoadChangeDialogProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    enrollmentId: number | null;
    courseId: number | null;
    studentName?: string | null;
    currency?: string;
    onChanged?: () => void;
};

export function EnrollmentLoadChangeDialog({
    open,
    onOpenChange,
    enrollmentId,
    courseId,
    studentName,
    currency = "PHP",
    onChanged,
}: EnrollmentLoadChangeDialogProps) {
    const [loading, setLoading] = useState(false);
    const [data, setData] = useState<LoadChangesIndexPayload | null>(null);

    const [changeType, setChangeType] = useState<"add" | "drop" | "swap">("swap");
    const [addedSubjectId, setAddedSubjectId] = useState<string>("");
    const [addedClassId, setAddedClassId] = useState<string>("none");
    const [sections, setSections] = useState<SectionOption[]>([]);
    const [loadingSections, setLoadingSections] = useState(false);
    const [droppedEnrollmentId, setDroppedEnrollmentId] = useState<string>("");
    const [reason, setReason] = useState("");
    const [applyImmediately, setApplyImmediately] = useState(true);
    const [overrideWindow, setOverrideWindow] = useState(false);
    const [overrideHolds, setOverrideHolds] = useState(false);

    const [preview, setPreview] = useState<LoadChangePreview | null>(null);
    const [submitting, setSubmitting] = useState(false);
    const [busyId, setBusyId] = useState<number | null>(null);
    const [rejectingId, setRejectingId] = useState<number | null>(null);
    const [rejectReason, setRejectReason] = useState("");

    const loadData = useCallback(async () => {
        if (!enrollmentId) return;
        setLoading(true);
        try {
            const response = await axios.get<LoadChangesIndexPayload>(loadChangesIndexRoute.url(enrollmentId));
            setData(response.data);
        } catch {
            toast.error("Could not load Change-of-Load details.");
        } finally {
            setLoading(false);
        }
    }, [enrollmentId]);

    useEffect(() => {
        if (!open || !enrollmentId) return;
        void loadData();
    }, [open, enrollmentId, loadData]);

    // Load open sections when an added subject is picked
    const effectiveCourseId = courseId ?? data?.course_id ?? null;
    useEffect(() => {
        if (!addedSubjectId || !effectiveCourseId) {
            setSections([]);
            setAddedClassId("none");
            return;
        }
        setLoadingSections(true);
        axios
            .get<SectionOption[]>(getSubjectSections.url(), {
                params: { subject_id: Number(addedSubjectId), course_id: effectiveCourseId },
            })
            .then((res) => {
                setSections(Array.isArray(res.data) ? res.data : []);
                setAddedClassId("none");
            })
            .catch(() => setSections([]))
            .finally(() => setLoadingSections(false));
    }, [addedSubjectId, effectiveCourseId]);

    // Live tuition & units delta preview
    useEffect(() => {
        if (!open || !enrollmentId) {
            setPreview(null);
            return;
        }
        const needsAdd = changeType === "add" || changeType === "swap";
        const needsDrop = changeType === "drop" || changeType === "swap";
        if ((needsAdd && !addedSubjectId) || (needsDrop && !droppedEnrollmentId)) {
            setPreview(null);
            return;
        }

        const timer = setTimeout(() => {
            axios
                .post<LoadChangePreview>(loadChangePreviewRoute.url(enrollmentId), {
                    change_type: changeType,
                    added_subject_id: needsAdd ? Number(addedSubjectId) : null,
                    dropped_subject_enrollment_id: needsDrop ? Number(droppedEnrollmentId) : null,
                })
                .then((res) => setPreview(res.data))
                .catch(() => setPreview(null));
        }, 180);

        return () => clearTimeout(timer);
    }, [open, enrollmentId, changeType, addedSubjectId, droppedEnrollmentId]);

    const handleSubmit = async (event: React.FormEvent) => {
        event.preventDefault();
        if (!enrollmentId) return;
        if (!reason.trim()) {
            toast.error("Provide a reason for this Change-of-Load transaction.");
            return;
        }

        setSubmitting(true);
        try {
            const response = await axios.post(storeLoadChangeRoute.url(enrollmentId), {
                change_type: changeType,
                added_subject_id: changeType !== "drop" && addedSubjectId ? Number(addedSubjectId) : null,
                added_class_id: changeType !== "drop" && addedClassId !== "none" ? Number(addedClassId) : null,
                dropped_subject_enrollment_id: changeType !== "add" && droppedEnrollmentId ? Number(droppedEnrollmentId) : null,
                reason: reason.trim(),
                apply_immediately: applyImmediately,
                override_window: overrideWindow,
                override_holds: overrideHolds,
            });
            toast.success(response.data?.message ?? "Change-of-Load saved.");
            setAddedSubjectId("");
            setAddedClassId("none");
            setDroppedEnrollmentId("");
            setReason("");
            setPreview(null);
            await loadData();
            onChanged?.();
        } catch (error) {
            if (axios.isAxiosError(error) && typeof error.response?.data?.message === "string") {
                toast.error(error.response.data.message);
            } else {
                toast.error("Failed to process Change-of-Load request.");
            }
        } finally {
            setSubmitting(false);
        }
    };

    const handleApprove = async (changeId: number) => {
        if (!enrollmentId) return;
        setBusyId(changeId);
        try {
            const response = await axios.post(approveLoadChangeRoute.url({ enrollment: enrollmentId, loadChange: changeId }), {});
            toast.success(response.data?.message ?? "Load change approved.");
            await loadData();
            onChanged?.();
        } catch (error) {
            if (axios.isAxiosError(error) && typeof error.response?.data?.message === "string") {
                toast.error(error.response.data.message);
            } else {
                toast.error("Failed to approve load change.");
            }
        } finally {
            setBusyId(null);
        }
    };

    const handleReject = async (changeId: number) => {
        if (!enrollmentId || rejectReason.trim().length < 3) {
            toast.error("Enter a rejection reason.");
            return;
        }
        setBusyId(changeId);
        try {
            const response = await axios.post(rejectLoadChangeRoute.url({ enrollment: enrollmentId, loadChange: changeId }), {
                rejection_reason: rejectReason.trim(),
            });
            toast.success(response.data?.message ?? "Load change rejected.");
            setRejectingId(null);
            setRejectReason("");
            await loadData();
        } catch {
            toast.error("Failed to reject load change.");
        } finally {
            setBusyId(null);
        }
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="flex max-h-[88vh] flex-col gap-0 overflow-hidden p-0 sm:max-w-2xl">
                <DialogHeader className="border-b px-5 py-4">
                    <div className="flex items-center justify-between gap-2">
                        <div>
                            <DialogTitle className="flex items-center gap-2 text-base">
                                <ArrowLeftRight className="size-4" />
                                Formal Add / Drop / Swap Subject
                            </DialogTitle>
                            <DialogDescription className="text-xs">
                                {studentName ? `${studentName} · ` : ""}Tracked Change-of-Load with automatic tuition delta and printable COL slip.
                            </DialogDescription>
                        </div>
                        {data && (
                            <Badge variant={data.window.add_drop_open ? "success-light" : "destructive-light"} radius="full" size="sm">
                                {data.window.add_drop_open ? "Add/Drop Open" : "Past Cutoff"}
                            </Badge>
                        )}
                    </div>
                </DialogHeader>

                <div className="flex-1 space-y-4 overflow-y-auto px-5 py-4">
                    {loading && !data ? (
                        <div className="flex items-center justify-center py-10">
                            <Loader2 className="text-muted-foreground size-5 animate-spin" />
                        </div>
                    ) : data ? (
                        <>
                            {!data.window.add_drop_open && (
                                <Alert variant="warning">
                                    <AlertTriangle className="size-4" />
                                    <AlertTitle>Add / Drop deadline has passed</AlertTitle>
                                    <AlertDescription className="flex items-center justify-between gap-2 text-xs">
                                        <span>
                                            Cutoff was {formatShortDate(data.window.add_drop_deadline_at)}.
                                            {data.window.enforce_windows ? " Override required to submit." : ""}
                                        </span>
                                        <label className="flex items-center gap-1.5 font-medium">
                                            <Switch checked={overrideWindow} onCheckedChange={setOverrideWindow} />
                                            Override
                                        </label>
                                    </AlertDescription>
                                </Alert>
                            )}

                            {data.blocking_holds_count > 0 && (
                                <Alert variant="destructive">
                                    <AlertTriangle className="size-4" />
                                    <AlertTitle>{data.blocking_holds_count} hold(s) block Add/Drop</AlertTitle>
                                    <AlertDescription className="flex items-center justify-between gap-2 text-xs">
                                        <span>An active cross-office hold restricts load changes on this student.</span>
                                        <label className="flex items-center gap-1.5 font-medium">
                                            <Switch checked={overrideHolds} onCheckedChange={setOverrideHolds} />
                                            Override
                                        </label>
                                    </AlertDescription>
                                </Alert>
                            )}

                            <form id="load-change-form" onSubmit={handleSubmit} className="space-y-3.5">
                                <Tabs value={changeType} onValueChange={(v) => setChangeType(v as "add" | "drop" | "swap")}>
                                    <TabsList className="grid w-full grid-cols-3">
                                        <TabsTrigger value="add" className="gap-1.5 text-xs">
                                            <PlusCircle className="size-3.5" />
                                            Add Subject
                                        </TabsTrigger>
                                        <TabsTrigger value="drop" className="gap-1.5 text-xs">
                                            <MinusCircle className="size-3.5" />
                                            Drop Subject
                                        </TabsTrigger>
                                        <TabsTrigger value="swap" className="gap-1.5 text-xs">
                                            <ArrowLeftRight className="size-3.5" />
                                            Swap Subject
                                        </TabsTrigger>
                                    </TabsList>
                                </Tabs>

                                {(changeType === "drop" || changeType === "swap") && (
                                    <div className="space-y-1.5">
                                        <Label className="text-xs">Subject to drop</Label>
                                        <Select value={droppedEnrollmentId} onValueChange={setDroppedEnrollmentId}>
                                            <SelectTrigger className="h-9 text-xs">
                                                <SelectValue placeholder="Choose currently enrolled subject" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {data.enrolled_subjects.map((se) => (
                                                    <SelectItem key={se.id} value={String(se.id)}>
                                                        {se.code} — {se.title} ({se.units} u{se.section ? ` · Sec ${se.section}` : ""})
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                    </div>
                                )}

                                {(changeType === "add" || changeType === "swap") && (
                                    <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                                        <div className="space-y-1.5">
                                            <Label className="text-xs">Subject to add</Label>
                                            <Select value={addedSubjectId} onValueChange={setAddedSubjectId}>
                                                <SelectTrigger className="h-9 text-xs">
                                                    <SelectValue placeholder="Choose curriculum subject" />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    {data.available_subjects.map((subj) => (
                                                        <SelectItem key={subj.id} value={String(subj.id)}>
                                                            {subj.code} — {subj.title} ({subj.units} u)
                                                        </SelectItem>
                                                    ))}
                                                </SelectContent>
                                            </Select>
                                        </div>

                                        <div className="space-y-1.5">
                                            <Label className="text-xs">Class section (optional)</Label>
                                            <Select
                                                value={addedClassId}
                                                onValueChange={setAddedClassId}
                                                disabled={!addedSubjectId || loadingSections}
                                            >
                                                <SelectTrigger className="h-9 text-xs">
                                                    <SelectValue placeholder={loadingSections ? "Loading sections..." : "Assign later"} />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    <SelectItem value="none">Leave unassigned for now</SelectItem>
                                                    {sections.map((sec) => (
                                                        <SelectItem key={sec.id} value={String(sec.id)}>
                                                            {sec.label}
                                                        </SelectItem>
                                                    ))}
                                                </SelectContent>
                                            </Select>
                                        </div>
                                    </div>
                                )}

                                {preview && (
                                    <Frame spacing="xs">
                                        <FramePanel className="bg-muted/35 grid grid-cols-3 gap-2 p-3 text-xs">
                                            <div>
                                                <p className="text-muted-foreground text-[11px]">Units delta</p>
                                                <p className="font-semibold tabular-nums">
                                                    {preview.units_delta >= 0 ? "+" : ""}
                                                    {preview.units_delta} unit(s)
                                                </p>
                                            </div>
                                            <div>
                                                <p className="text-muted-foreground text-[11px]">Tuition adjustment</p>
                                                <p
                                                    className={cn(
                                                        "font-semibold tabular-nums",
                                                        preview.tuition_delta > 0
                                                            ? "text-amber-600 dark:text-amber-400"
                                                            : preview.tuition_delta < 0
                                                              ? "text-emerald-600 dark:text-emerald-400"
                                                              : "",
                                                    )}
                                                >
                                                    {preview.tuition_delta >= 0 ? "+" : ""}
                                                    {formatMoney(preview.tuition_delta, currency)}
                                                </p>
                                            </div>
                                            <div>
                                                <p className="text-muted-foreground text-[11px]">New assessment</p>
                                                <p className="font-semibold tabular-nums">{formatMoney(preview.new_tuition_total, currency)}</p>
                                            </div>
                                        </FramePanel>
                                    </Frame>
                                )}

                                <div className="space-y-1.5">
                                    <Label htmlFor="col-reason" className="text-xs">
                                        Reason / Registrar remarks
                                    </Label>
                                    <Textarea
                                        id="col-reason"
                                        rows={2}
                                        value={reason}
                                        onChange={(e) => setReason(e.target.value)}
                                        placeholder="e.g. Schedule conflict with major laboratory, student requested elective swap..."
                                        className="text-xs"
                                    />
                                </div>

                                <div className="flex items-center justify-between text-xs">
                                    <label className="flex items-center gap-2">
                                        <Switch checked={applyImmediately} onCheckedChange={setApplyImmediately} />
                                        Approve &amp; apply immediately (recalculates tuition ledger)
                                    </label>
                                    <Button type="submit" size="sm" disabled={submitting} className="gap-1.5">
                                        {submitting && <Loader2 className="size-3.5 animate-spin" />}
                                        {applyImmediately ? "Apply & generate COL slip" : "Queue for approval"}
                                    </Button>
                                </div>
                            </form>

                            {/* History Table */}
                            <div className="space-y-2 border-t pt-3">
                                <p className="text-muted-foreground text-[11px] font-semibold tracking-wider uppercase">
                                    Change-of-Load History ({data.load_changes.length})
                                </p>
                                {data.load_changes.length === 0 ? (
                                    <p className="text-muted-foreground text-xs">No tracked Add / Drop / Swap slips on this enrollment yet.</p>
                                ) : (
                                    <div className="divide-y rounded-lg border">
                                        {data.load_changes.map((item) => (
                                            <div key={item.id} className="space-y-1.5 px-3 py-2.5 text-xs">
                                                <div className="flex flex-wrap items-center justify-between gap-2">
                                                    <div className="flex flex-wrap items-center gap-1.5">
                                                        <span className="font-mono font-semibold">{item.reference_number}</span>
                                                        <Badge variant="outline" size="xs" className="uppercase">
                                                            {item.change_type}
                                                        </Badge>
                                                        <Badge
                                                            variant={
                                                                item.status === "approved"
                                                                    ? "success-light"
                                                                    : item.status === "rejected"
                                                                      ? "destructive-light"
                                                                      : "warning-light"
                                                            }
                                                            size="xs"
                                                            radius="full"
                                                            className="capitalize"
                                                        >
                                                            {item.status}
                                                        </Badge>
                                                    </div>
                                                    <div className="flex items-center gap-1">
                                                        {item.status === "pending" && (
                                                            <>
                                                                <Button
                                                                    type="button"
                                                                    size="xs"
                                                                    variant="secondary"
                                                                    className="h-6 gap-1 px-2 text-[11px]"
                                                                    disabled={busyId === item.id}
                                                                    onClick={() => void handleApprove(item.id)}
                                                                >
                                                                    <Check className="size-3" />
                                                                    Approve
                                                                </Button>
                                                                <Button
                                                                    type="button"
                                                                    size="xs"
                                                                    variant="ghost"
                                                                    className="text-destructive h-6 gap-1 px-1.5 text-[11px]"
                                                                    onClick={() => setRejectingId(item.id)}
                                                                >
                                                                    <X className="size-3" />
                                                                    Reject
                                                                </Button>
                                                            </>
                                                        )}
                                                        <a
                                                            href={item.slip_url}
                                                            target="_blank"
                                                            rel="noreferrer"
                                                            className="text-muted-foreground hover:text-foreground inline-flex h-6 items-center gap-1 rounded px-2 text-[11px] font-medium"
                                                        >
                                                            <Printer className="size-3" />
                                                            COL Slip
                                                        </a>
                                                    </div>
                                                </div>
                                                <div className="text-muted-foreground flex flex-wrap items-center gap-x-2 text-[11px]">
                                                    {item.dropped_subject_code && (
                                                        <span className="text-destructive">− {item.dropped_subject_code}</span>
                                                    )}
                                                    {item.added_subject_code && (
                                                        <span className="text-emerald-600 dark:text-emerald-400">
                                                            + {item.added_subject_code}
                                                            {item.added_class_section ? ` (${item.added_class_section})` : ""}
                                                        </span>
                                                    )}
                                                    <span>·</span>
                                                    <span className="tabular-nums">
                                                        {item.tuition_delta >= 0 ? "+" : ""}
                                                        {formatMoney(item.tuition_delta, currency)}
                                                    </span>
                                                    <span>·</span>
                                                    <span>{formatShortDate(item.created_at)}</span>
                                                </div>
                                                {rejectingId === item.id && (
                                                    <div className="flex items-center gap-1.5 pt-1">
                                                        <Input
                                                            value={rejectReason}
                                                            onChange={(e) => setRejectReason(e.target.value)}
                                                            placeholder="Reason for rejection..."
                                                            className="h-7 flex-1 text-xs"
                                                        />
                                                        <Button
                                                            type="button"
                                                            size="xs"
                                                            variant="destructive"
                                                            disabled={busyId === item.id}
                                                            onClick={() => void handleReject(item.id)}
                                                        >
                                                            Reject
                                                        </Button>
                                                    </div>
                                                )}
                                            </div>
                                        ))}
                                    </div>
                                )}
                            </div>
                        </>
                    ) : null}
                </div>

                <DialogFooter className="border-t px-5 py-3">
                    <Button type="button" variant="outline" size="sm" onClick={() => onOpenChange(false)}>
                        Close
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
