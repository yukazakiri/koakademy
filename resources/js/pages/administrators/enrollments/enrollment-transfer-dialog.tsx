import {
    index as transfersIndexRoute,
    preview as transferPreviewRoute,
    shiftProgram as shiftProgramRoute,
    transferSection as transferSectionRoute,
} from "@/actions/App/Http/Controllers/AdministratorEnrollmentTransferController";
import { Badge } from "@/components/reui/badge";
import { Frame, FramePanel } from "@/components/reui/frame";
import { Button } from "@/components/ui/button";
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from "@/components/ui/dialog";
import { Label } from "@/components/ui/label";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Switch } from "@/components/ui/switch";
import { Tabs, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { Textarea } from "@/components/ui/textarea";
import { cn } from "@/lib/utils";
import axios from "axios";
import { ArrowRightLeft, GraduationCap, Layers, Loader2 } from "lucide-react";
import { useCallback, useEffect, useState } from "react";
import { toast } from "sonner";
import { formatMoney, formatShortDate } from "./enrollment-utils";

interface TransferHistoryItem {
    id: number;
    transfer_type: "section_transfer" | "program_shift";
    from_course: string | null;
    to_course: string | null;
    from_section: string | null;
    to_section: string | null;
    credited_subjects_count: number;
    reassigned_classes_count: number;
    unmatched_subjects_count: number;
    previous_tuition_total: number;
    new_tuition_total: number;
    fee_delta: number;
    reason: string;
    performed_by: string;
    created_at: string | null;
}

interface TransferOptionsPayload {
    current_course: { id: number; code: string; title: string | null } | null;
    current_section: string | null;
    academic_year: number;
    available_sections: string[];
    courses: Array<{ id: number; code: string; title: string | null; is_current: boolean }>;
    history: TransferHistoryItem[];
}

interface SectionPreview {
    transfer_type: "section_transfer";
    target_section: string;
    movable_count: number;
    full_count: number;
    unmatched_count: number;
    movable: Array<{ subject_code: string; from_section: string; to_section: string }>;
    full: Array<{ subject_code: string; to_section: string; enrolled_count: number; max_slots: number }>;
    unmatched: Array<{ subject_code: string }>;
}

interface ProgramShiftPreview {
    transfer_type: "program_shift";
    from_course: { code: string | null };
    to_course: { code: string | null };
    credited_subjects: Array<{ to_code: string; title: string; units: number }>;
    added_subjects: Array<{ code: string; title: string; units: number }>;
    dropped_subjects: Array<{ code: string; title: string; units: number }>;
    previous_tuition_total: number;
    new_tuition_total: number;
    fee_delta: number;
}

type EnrollmentTransferDialogProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    enrollmentId: number | null;
    studentName?: string | null;
    currency?: string;
    onTransferred?: () => void;
};

export function EnrollmentTransferDialog({
    open,
    onOpenChange,
    enrollmentId,
    studentName,
    currency = "PHP",
    onTransferred,
}: EnrollmentTransferDialogProps) {
    const [mode, setMode] = useState<"section_transfer" | "program_shift">("section_transfer");
    const [loading, setLoading] = useState(false);
    const [options, setOptions] = useState<TransferOptionsPayload | null>(null);

    const [targetSection, setTargetSection] = useState("");
    const [targetCourseId, setTargetCourseId] = useState("");
    const [targetYearLevel, setTargetYearLevel] = useState("1");
    const [reconcileCurriculum, setReconcileCurriculum] = useState(true);
    const [forceOverload, setForceOverload] = useState(false);
    const [reason, setReason] = useState("");

    const [sectionPreview, setSectionPreview] = useState<SectionPreview | null>(null);
    const [programPreview, setProgramPreview] = useState<ProgramShiftPreview | null>(null);
    const [submitting, setSubmitting] = useState(false);

    const loadOptions = useCallback(async () => {
        if (!enrollmentId) return;
        setLoading(true);
        try {
            const res = await axios.get<TransferOptionsPayload>(transfersIndexRoute.url(enrollmentId));
            setOptions(res.data);
            setTargetYearLevel(String(res.data.academic_year || 1));
        } catch {
            toast.error("Could not load program shift and section transfer options.");
        } finally {
            setLoading(false);
        }
    }, [enrollmentId]);

    useEffect(() => {
        if (!open || !enrollmentId) return;
        void loadOptions();
    }, [open, enrollmentId, loadOptions]);

    // Live preview for section transfer or program shift
    useEffect(() => {
        if (!open || !enrollmentId) return;
        setSectionPreview(null);
        setProgramPreview(null);

        if (mode === "section_transfer" && !targetSection) return;
        if (mode === "program_shift" && !targetCourseId) return;

        const timer = setTimeout(() => {
            axios
                .post<SectionPreview | ProgramShiftPreview>(transferPreviewRoute.url(enrollmentId), {
                    transfer_type: mode,
                    target_section: mode === "section_transfer" ? targetSection : null,
                    target_course_id: mode === "program_shift" ? Number(targetCourseId) : null,
                    target_year_level: mode === "program_shift" ? Number(targetYearLevel) : null,
                    force_overload: forceOverload,
                })
                .then((res) => {
                    if (res.data.transfer_type === "section_transfer") {
                        setSectionPreview(res.data);
                    } else {
                        setProgramPreview(res.data);
                    }
                })
                .catch(() => {
                    setSectionPreview(null);
                    setProgramPreview(null);
                });
        }, 180);

        return () => clearTimeout(timer);
    }, [open, enrollmentId, mode, targetSection, targetCourseId, targetYearLevel, forceOverload]);

    const handleSubmit = async (event: React.FormEvent) => {
        event.preventDefault();
        if (!enrollmentId) return;
        if (reason.trim().length < 3) {
            toast.error("Enter a transfer/shift reason.");
            return;
        }

        setSubmitting(true);
        try {
            if (mode === "section_transfer") {
                const res = await axios.post(transferSectionRoute.url(enrollmentId), {
                    target_section: targetSection,
                    reason: reason.trim(),
                    force_overload: forceOverload,
                });
                toast.success(res.data?.message ?? "Section transfer completed.");
            } else {
                const res = await axios.post(shiftProgramRoute.url(enrollmentId), {
                    target_course_id: Number(targetCourseId),
                    target_year_level: Number(targetYearLevel),
                    reconcile_curriculum: reconcileCurriculum,
                    force_overload: forceOverload,
                    reason: reason.trim(),
                });
                toast.success(res.data?.message ?? "Program shift completed.");
            }

            setReason("");
            setTargetSection("");
            setTargetCourseId("");
            setSectionPreview(null);
            setProgramPreview(null);
            await loadOptions();
            onTransferred?.();
        } catch (error) {
            if (axios.isAxiosError(error) && typeof error.response?.data?.message === "string") {
                toast.error(error.response.data.message);
            } else {
                toast.error("Transfer operation failed.");
            }
        } finally {
            setSubmitting(false);
        }
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="flex max-h-[88vh] flex-col gap-0 overflow-hidden p-0 sm:max-w-2xl">
                <DialogHeader className="border-b px-5 py-4">
                    <DialogTitle className="flex items-center gap-2 text-base">
                        <ArrowRightLeft className="size-4" />
                        Program Shift &amp; Section Transfer
                    </DialogTitle>
                    <DialogDescription className="text-xs">
                        {studentName ? `${studentName} · ` : ""}Reconcile class seats, credited overlapping subjects, and tuition deltas with full audit
                        history.
                    </DialogDescription>
                </DialogHeader>

                <div className="flex-1 space-y-4 overflow-y-auto px-5 py-4">
                    {loading && !options ? (
                        <div className="flex justify-center py-10">
                            <Loader2 className="text-muted-foreground size-5 animate-spin" />
                        </div>
                    ) : options ? (
                        <>
                            <div className="bg-muted/40 flex flex-wrap items-center gap-2 rounded-lg border px-3 py-2 text-xs">
                                <span className="text-muted-foreground">Current placement:</span>
                                <Badge variant="secondary" size="xs">
                                    {options.current_course?.code ?? "Unassigned"} · Year {options.academic_year}
                                </Badge>
                                <Badge variant="outline" size="xs">
                                    Section {options.current_section ?? "Mixed / Unassigned"}
                                </Badge>
                            </div>

                            <form onSubmit={handleSubmit} className="space-y-3.5">
                                <Tabs value={mode} onValueChange={(v) => setMode(v as "section_transfer" | "program_shift")}>
                                    <TabsList className="grid w-full grid-cols-2">
                                        <TabsTrigger value="section_transfer" className="gap-1.5 text-xs">
                                            <Layers className="size-3.5" />
                                            Transfer Section
                                        </TabsTrigger>
                                        <TabsTrigger value="program_shift" className="gap-1.5 text-xs">
                                            <GraduationCap className="size-3.5" />
                                            Shift Program
                                        </TabsTrigger>
                                    </TabsList>
                                </Tabs>

                                {mode === "section_transfer" ? (
                                    <div className="space-y-2">
                                        <Label className="text-xs">Target block section</Label>
                                        <Select value={targetSection} onValueChange={setTargetSection}>
                                            <SelectTrigger className="h-9 text-xs">
                                                <SelectValue placeholder="Choose target section" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {options.available_sections.map((sec) => (
                                                    <SelectItem key={sec} value={sec}>
                                                        Section {sec}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>

                                        {sectionPreview && (
                                            <Frame spacing="xs">
                                                <FramePanel className="bg-muted/30 grid grid-cols-3 gap-2 p-3 text-xs">
                                                    <div>
                                                        <p className="text-muted-foreground text-[11px]">Ready to move</p>
                                                        <p className="font-semibold text-emerald-600 tabular-nums dark:text-emerald-400">
                                                            {sectionPreview.movable_count} class(es)
                                                        </p>
                                                    </div>
                                                    <div>
                                                        <p className="text-muted-foreground text-[11px]">Full in target</p>
                                                        <p className="font-semibold text-amber-600 tabular-nums dark:text-amber-400">
                                                            {sectionPreview.full_count}
                                                        </p>
                                                    </div>
                                                    <div>
                                                        <p className="text-muted-foreground text-[11px]">No match</p>
                                                        <p className="text-muted-foreground font-semibold tabular-nums">
                                                            {sectionPreview.unmatched_count}
                                                        </p>
                                                    </div>
                                                </FramePanel>
                                            </Frame>
                                        )}
                                    </div>
                                ) : (
                                    <div className="space-y-3">
                                        <div className="grid grid-cols-1 gap-3 sm:grid-cols-3">
                                            <div className="space-y-1.5 sm:col-span-2">
                                                <Label className="text-xs">Target program / course</Label>
                                                <Select value={targetCourseId} onValueChange={setTargetCourseId}>
                                                    <SelectTrigger className="h-9 text-xs">
                                                        <SelectValue placeholder="Choose target program" />
                                                    </SelectTrigger>
                                                    <SelectContent>
                                                        {options.courses
                                                            .filter((c) => !c.is_current)
                                                            .map((c) => (
                                                                <SelectItem key={c.id} value={String(c.id)}>
                                                                    {c.code} — {c.title ?? "Program"}
                                                                </SelectItem>
                                                            ))}
                                                    </SelectContent>
                                                </Select>
                                            </div>
                                            <div className="space-y-1.5">
                                                <Label className="text-xs">Target year level</Label>
                                                <Select value={targetYearLevel} onValueChange={setTargetYearLevel}>
                                                    <SelectTrigger className="h-9 text-xs">
                                                        <SelectValue />
                                                    </SelectTrigger>
                                                    <SelectContent>
                                                        {["1", "2", "3", "4", "5"].map((y) => (
                                                            <SelectItem key={y} value={y}>
                                                                Year {y}
                                                            </SelectItem>
                                                        ))}
                                                    </SelectContent>
                                                </Select>
                                            </div>
                                        </div>

                                        {programPreview && (
                                            <Frame spacing="xs">
                                                <FramePanel className="bg-muted/30 space-y-2 p-3 text-xs">
                                                    <div className="grid grid-cols-4 gap-2">
                                                        <div>
                                                            <p className="text-muted-foreground text-[11px]">Credited</p>
                                                            <p className="font-semibold text-emerald-600 tabular-nums dark:text-emerald-400">
                                                                {programPreview.credited_subjects.length}
                                                            </p>
                                                        </div>
                                                        <div>
                                                            <p className="text-muted-foreground text-[11px]">New subjects</p>
                                                            <p className="font-semibold text-blue-600 tabular-nums dark:text-blue-400">
                                                                +{programPreview.added_subjects.length}
                                                            </p>
                                                        </div>
                                                        <div>
                                                            <p className="text-muted-foreground text-[11px]">Dropped</p>
                                                            <p className="text-destructive font-semibold tabular-nums">
                                                                −{programPreview.dropped_subjects.length}
                                                            </p>
                                                        </div>
                                                        <div>
                                                            <p className="text-muted-foreground text-[11px]">Fee delta</p>
                                                            <p
                                                                className={cn(
                                                                    "font-semibold tabular-nums",
                                                                    programPreview.fee_delta > 0
                                                                        ? "text-amber-600 dark:text-amber-400"
                                                                        : programPreview.fee_delta < 0
                                                                          ? "text-emerald-600 dark:text-emerald-400"
                                                                          : "",
                                                                )}
                                                            >
                                                                {programPreview.fee_delta >= 0 ? "+" : ""}
                                                                {formatMoney(programPreview.fee_delta, currency)}
                                                            </p>
                                                        </div>
                                                    </div>
                                                </FramePanel>
                                            </Frame>
                                        )}

                                        <label className="flex items-center justify-between text-xs">
                                            <span>Reconcile curriculum subjects &amp; recalculate tuition automatically</span>
                                            <Switch checked={reconcileCurriculum} onCheckedChange={setReconcileCurriculum} />
                                        </label>
                                    </div>
                                )}

                                <label className="flex items-center justify-between text-xs">
                                    <span>Allow class seat overload if target section is full</span>
                                    <Switch checked={forceOverload} onCheckedChange={setForceOverload} />
                                </label>

                                <div className="space-y-1.5">
                                    <Label htmlFor="transfer-reason" className="text-xs">
                                        Approval reason / registrar note
                                    </Label>
                                    <Textarea
                                        id="transfer-reason"
                                        rows={2}
                                        value={reason}
                                        onChange={(e) => setReason(e.target.value)}
                                        placeholder="e.g. Dean-approved program shift form #SH-2026-12..."
                                        className="text-xs"
                                    />
                                </div>

                                <div className="flex justify-end">
                                    <Button
                                        type="submit"
                                        size="sm"
                                        disabled={
                                            submitting ||
                                            (mode === "section_transfer" && !targetSection) ||
                                            (mode === "program_shift" && !targetCourseId)
                                        }
                                        className="gap-1.5"
                                    >
                                        {submitting && <Loader2 className="size-3.5 animate-spin" />}
                                        {mode === "section_transfer" ? "Execute section transfer" : "Execute program shift"}
                                    </Button>
                                </div>
                            </form>

                            {/* History */}
                            <div className="space-y-2 border-t pt-3">
                                <p className="text-muted-foreground text-[11px] font-semibold tracking-wider uppercase">
                                    Transfer &amp; Shift History ({options.history.length})
                                </p>
                                {options.history.length === 0 ? (
                                    <p className="text-muted-foreground text-xs">No section transfers or program shifts recorded yet.</p>
                                ) : (
                                    <div className="divide-y rounded-lg border">
                                        {options.history.map((log) => (
                                            <div key={log.id} className="space-y-1 px-3 py-2.5 text-xs">
                                                <div className="flex items-center justify-between gap-2">
                                                    <div className="flex items-center gap-1.5">
                                                        <Badge variant="secondary" size="xs" className="capitalize">
                                                            {log.transfer_type.replace("_", " ")}
                                                        </Badge>
                                                        <span className="font-semibold">
                                                            {log.transfer_type === "program_shift"
                                                                ? `${log.from_course ?? "—"} → ${log.to_course ?? "—"}`
                                                                : `Sec ${log.from_section ?? "—"} → Sec ${log.to_section ?? "—"}`}
                                                        </span>
                                                    </div>
                                                    <span className="text-muted-foreground text-[11px]">{formatShortDate(log.created_at)}</span>
                                                </div>
                                                <p className="text-muted-foreground text-[11px]">
                                                    {log.reason} · by {log.performed_by}
                                                    {log.credited_subjects_count > 0 ? ` · ${log.credited_subjects_count} credited` : ""}
                                                    {log.reassigned_classes_count > 0 ? ` · ${log.reassigned_classes_count} class(es) moved` : ""}
                                                    {log.fee_delta !== 0
                                                        ? ` · ${log.fee_delta > 0 ? "+" : ""}${formatMoney(log.fee_delta, currency)}`
                                                        : ""}
                                                </p>
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
