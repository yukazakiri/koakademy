import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Checkbox } from "@/components/ui/checkbox";
import { Input } from "@/components/ui/input";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table";
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from "@/components/ui/tooltip";
import { cn } from "@/lib/utils";
import { Check, CheckCircle2, Lock, RotateCcw, Save, ShieldCheck, Unlock, X } from "lucide-react";
import { useMemo, useState } from "react";
import { toast } from "sonner";
import { postVerifyGrades } from "../crud/api";
import type { VerifyGradesRequest } from "../crud/types";
import type { ClassShowData, EnrollmentRow } from "../record/types";
import { patchClassEnrollment, WorkspaceApiError } from "../workspace/api";

type GradeDraft = {
    prelim_grade: string;
    midterm_grade: string;
    finals_grade: string;
    remarks: string;
};

function toInputString(value: number | null | undefined): string {
    return value === null || value === undefined ? "" : String(value);
}

function parseGradeNumber(value: string): number | null {
    const trimmed = value.trim();
    if (!trimmed) return null;
    const num = Number(trimmed);
    return Number.isFinite(num) ? num : null;
}

function computeDraftAverage(draft: GradeDraft, fallback: number | null): number | null {
    const nums = [parseGradeNumber(draft.prelim_grade), parseGradeNumber(draft.midterm_grade), parseGradeNumber(draft.finals_grade)].filter(
        (n): n is number => n !== null,
    );
    if (nums.length === 0) return fallback;
    if (nums.length < 3) {
        return nums.reduce((acc, v) => acc + v, 0) / nums.length;
    }
    return nums.reduce((acc, v) => acc + v, 0) / 3;
}

export function DossierGradesTab({
    classItem,
    enrollments,
    canUpdate,
    onRefresh,
}: {
    classItem: ClassShowData;
    enrollments: EnrollmentRow[];
    canUpdate: boolean;
    onRefresh: () => void;
}) {
    const isEditable = canUpdate && !classItem.is_archived;
    const activeEnrollments = useMemo(
        () => enrollments.filter((row) => !row.deleted_at && row.enrollment_status !== "dropped" && row.enrollment_status !== "withdrawn"),
        [enrollments],
    );

    const [selectedIds, setSelectedIds] = useState<number[]>([]);
    const [drafts, setDrafts] = useState<Record<number, GradeDraft>>({});
    const [savingRowId, setSavingRowId] = useState<number | null>(null);
    const [bulkActionLoading, setBulkActionLoading] = useState<VerifyGradesRequest["action"] | null>(null);
    const [governanceNotes, setGovernanceNotes] = useState("");

    const summary = useMemo(() => {
        let verified = 0;
        let finalized = 0;
        let inProgress = 0;
        let pending = 0;
        for (const row of activeEnrollments) {
            if (row.is_grades_finalized) finalized++;
            if (row.is_grades_verified) {
                verified++;
            } else if (
                row.total_average !== null ||
                row.prelim_grade !== null ||
                row.midterm_grade !== null ||
                row.finals_grade !== null
            ) {
                inProgress++;
            } else {
                pending++;
            }
        }
        return { total: activeEnrollments.length, verified, finalized, inProgress, pending };
    }, [activeEnrollments]);

    const allSelected = activeEnrollments.length > 0 && selectedIds.length === activeEnrollments.length;

    const toggleSelectAll = (checked: boolean) => {
        setSelectedIds(checked ? activeEnrollments.map((row) => row.id) : []);
    };

    const toggleSelectRow = (id: number, checked: boolean) => {
        setSelectedIds((prev) => (checked ? Array.from(new Set([...prev, id])) : prev.filter((item) => item !== id)));
    };

    const getDraft = (row: EnrollmentRow): GradeDraft => {
        return (
            drafts[row.id] ?? {
                prelim_grade: toInputString(row.prelim_grade),
                midterm_grade: toInputString(row.midterm_grade),
                finals_grade: toInputString(row.finals_grade),
                remarks: row.remarks ?? "",
            }
        );
    };

    const updateDraft = (row: EnrollmentRow, patch: Partial<GradeDraft>) => {
        setDrafts((prev) => ({
            ...prev,
            [row.id]: {
                ...getDraft(row),
                ...patch,
            },
        }));
    };

    const isRowDirty = (row: EnrollmentRow): boolean => {
        const draft = drafts[row.id];
        if (!draft) return false;
        return (
            draft.prelim_grade !== toInputString(row.prelim_grade) ||
            draft.midterm_grade !== toInputString(row.midterm_grade) ||
            draft.finals_grade !== toInputString(row.finals_grade) ||
            draft.remarks !== (row.remarks ?? "")
        );
    };

    const cancelRowDraft = (rowId: number) => {
        setDrafts((prev) => {
            const next = { ...prev };
            delete next[rowId];
            return next;
        });
    };

    const saveRow = async (row: EnrollmentRow) => {
        const draft = drafts[row.id];
        if (!draft) return;
        setSavingRowId(row.id);
        try {
            const response = await patchClassEnrollment(classItem.id, row.id, {
                prelim_grade: parseGradeNumber(draft.prelim_grade),
                midterm_grade: parseGradeNumber(draft.midterm_grade),
                finals_grade: parseGradeNumber(draft.finals_grade),
                remarks: draft.remarks.trim() || null,
            });
            toast.success(response.message || `Saved grades for ${row.student?.name ?? "student"}.`);
            cancelRowDraft(row.id);
            onRefresh();
        } catch (error) {
            toast.error(error instanceof WorkspaceApiError ? error.message : "Could not save grades. Please try again.");
        } finally {
            setSavingRowId(null);
        }
    };

    const runGovernanceAction = async (action: VerifyGradesRequest["action"], targetIds?: number[]) => {
        const ids = targetIds ?? selectedIds;
        if (ids.length === 0) {
            toast.error("Select at least one student first.");
            return;
        }
        setBulkActionLoading(action);
        try {
            const response = await postVerifyGrades(classItem.id, {
                enrollment_ids: ids,
                action,
                notes: governanceNotes.trim() || null,
            });
            const skippedSuffix = response.skipped?.length ? ` (${response.skipped.length} skipped)` : "";
            toast.success(`${response.message || `Updated ${response.updated} record(s).`}${skippedSuffix}`);
            setSelectedIds([]);
            setGovernanceNotes("");
            onRefresh();
        } catch (error) {
            toast.error(error instanceof WorkspaceApiError ? error.message : "Grade governance action failed.");
        } finally {
            setBulkActionLoading(null);
        }
    };

    return (
        <TooltipProvider>
            <section className="bg-card overflow-hidden rounded-xl border">
                {/* Governance Summary Bar */}
                <div className="bg-muted/30 flex flex-col gap-3 border-b px-4 py-3.5 sm:px-5">
                    <div className="flex flex-wrap items-center justify-between gap-3">
                        <div className="space-y-0.5">
                            <div className="flex items-center gap-2">
                                <ShieldCheck className="text-primary size-4" aria-hidden="true" />
                                <h2 className="text-sm font-semibold">Grade Governance &amp; Verification</h2>
                            </div>
                            <p className="text-muted-foreground text-xs tabular-nums">
                                {summary.verified}/{summary.total} Verified · {summary.finalized} Finalized · {summary.inProgress} In Progress ·{" "}
                                {summary.pending} Pending
                            </p>
                        </div>

                        {isEditable && (
                            <div className="flex flex-wrap items-center gap-1.5">
                                <Button
                                    type="button"
                                    size="sm"
                                    disabled={selectedIds.length === 0 || bulkActionLoading !== null}
                                    onClick={() => runGovernanceAction("verify")}
                                    className="bg-emerald-600 text-white hover:bg-emerald-700"
                                >
                                    <CheckCircle2 className="size-3.5" aria-hidden="true" />
                                    Verify Selected{selectedIds.length > 0 ? ` (${selectedIds.length})` : ""}
                                </Button>
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    disabled={selectedIds.length === 0 || bulkActionLoading !== null}
                                    onClick={() => runGovernanceAction("unverify")}
                                >
                                    <RotateCcw className="size-3.5" aria-hidden="true" />
                                    Unverify
                                </Button>
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    disabled={selectedIds.length === 0 || bulkActionLoading !== null}
                                    onClick={() => runGovernanceAction("finalize")}
                                >
                                    <Lock className="size-3.5" aria-hidden="true" />
                                    Finalize
                                </Button>
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    disabled={selectedIds.length === 0 || bulkActionLoading !== null}
                                    onClick={() => runGovernanceAction("unlock")}
                                >
                                    <Unlock className="size-3.5" aria-hidden="true" />
                                    Unlock
                                </Button>
                            </div>
                        )}
                    </div>

                    {isEditable && selectedIds.length > 0 && (
                        <div className="flex flex-wrap items-center gap-2 rounded-lg border bg-background px-3 py-2">
                            <span className="text-xs font-medium tabular-nums">{selectedIds.length} selected</span>
                            <Input
                                value={governanceNotes}
                                onChange={(event) => setGovernanceNotes(event.target.value)}
                                placeholder="Optional verification / unlock audit note…"
                                className="h-7 max-w-sm flex-1 text-xs"
                                aria-label="Grade governance audit note"
                            />
                            <Button type="button" variant="ghost" size="xs" onClick={() => setSelectedIds([])}>
                                Clear
                            </Button>
                        </div>
                    )}
                </div>

                {/* Inline Grade Sheet Table */}
                {activeEnrollments.length === 0 ? (
                    <div className="flex flex-col items-center justify-center gap-2 px-6 py-12 text-center">
                        <ShieldCheck className="text-muted-foreground/60 size-8" aria-hidden="true" />
                        <p className="text-sm font-medium">No active enrollments to grade</p>
                        <p className="text-muted-foreground max-w-sm text-xs">
                            Enrolled students will appear here for inline grade entry, verification, and finalization.
                        </p>
                    </div>
                ) : (
                    <div className="overflow-x-auto">
                        <Table>
                            <TableHeader className="bg-muted/30">
                                <TableRow>
                                    {isEditable && (
                                        <TableHead className="w-10 px-3 text-center">
                                            <Checkbox
                                                checked={allSelected}
                                                onCheckedChange={(checked) => toggleSelectAll(Boolean(checked))}
                                                aria-label="Select all students for grade governance"
                                            />
                                        </TableHead>
                                    )}
                                    <TableHead className="px-3 text-xs">Student</TableHead>
                                    <TableHead className="w-24 px-2 text-xs">Prelim</TableHead>
                                    <TableHead className="w-24 px-2 text-xs">Midterm</TableHead>
                                    <TableHead className="w-24 px-2 text-xs">Finals</TableHead>
                                    <TableHead className="w-28 px-3 text-xs">Average</TableHead>
                                    <TableHead className="min-w-36 px-2 text-xs">Remarks</TableHead>
                                    <TableHead className="w-32 px-3 text-xs">Governance</TableHead>
                                    {isEditable && <TableHead className="w-28 px-3 text-right text-xs">Action</TableHead>}
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {activeEnrollments.map((row) => {
                                    const draft = getDraft(row);
                                    const dirty = isRowDirty(row);
                                    const isFinalized = Boolean(row.is_grades_finalized);
                                    const isVerified = Boolean(row.is_grades_verified);
                                    const rowLocked = !isEditable || isFinalized;
                                    const computedAvg = dirty ? computeDraftAverage(draft, row.total_average) : row.total_average;
                                    const selected = selectedIds.includes(row.id);

                                    return (
                                        <TableRow key={row.id} className={cn(selected && "bg-primary/[0.04]")}>
                                            {isEditable && (
                                                <TableCell className="px-3 text-center">
                                                    <Checkbox
                                                        checked={selected}
                                                        onCheckedChange={(checked) => toggleSelectRow(row.id, Boolean(checked))}
                                                        aria-label={`Select ${row.student?.name ?? "student"}`}
                                                    />
                                                </TableCell>
                                            )}
                                            <TableCell className="px-3 py-2.5">
                                                <div className="min-w-36">
                                                    <p className="text-sm leading-tight font-medium">
                                                        {row.student?.name || "Student unavailable"}
                                                    </p>
                                                    <p className="text-muted-foreground font-mono text-[11px]">
                                                        {row.student?.student_id || "No ID"}
                                                    </p>
                                                </div>
                                            </TableCell>
                                            <TableCell className="px-2 py-2">
                                                <Input
                                                    type="number"
                                                    step="0.01"
                                                    min="0"
                                                    max="100"
                                                    value={draft.prelim_grade}
                                                    disabled={rowLocked || savingRowId === row.id}
                                                    onChange={(event) => updateDraft(row, { prelim_grade: event.target.value })}
                                                    placeholder="—"
                                                    aria-label={`Prelim grade for ${row.student?.name ?? "student"}`}
                                                    className="h-8 w-20 font-mono text-xs tabular-nums"
                                                />
                                            </TableCell>
                                            <TableCell className="px-2 py-2">
                                                <Input
                                                    type="number"
                                                    step="0.01"
                                                    min="0"
                                                    max="100"
                                                    value={draft.midterm_grade}
                                                    disabled={rowLocked || savingRowId === row.id}
                                                    onChange={(event) => updateDraft(row, { midterm_grade: event.target.value })}
                                                    placeholder="—"
                                                    aria-label={`Midterm grade for ${row.student?.name ?? "student"}`}
                                                    className="h-8 w-20 font-mono text-xs tabular-nums"
                                                />
                                            </TableCell>
                                            <TableCell className="px-2 py-2">
                                                <Input
                                                    type="number"
                                                    step="0.01"
                                                    min="0"
                                                    max="100"
                                                    value={draft.finals_grade}
                                                    disabled={rowLocked || savingRowId === row.id}
                                                    onChange={(event) => updateDraft(row, { finals_grade: event.target.value })}
                                                    placeholder="—"
                                                    aria-label={`Finals grade for ${row.student?.name ?? "student"}`}
                                                    className="h-8 w-20 font-mono text-xs tabular-nums"
                                                />
                                            </TableCell>
                                            <TableCell className="px-3 py-2">
                                                <div className="flex flex-col gap-0.5">
                                                    <span className="font-mono text-xs font-semibold tabular-nums">
                                                        {computedAvg !== null ? Number(computedAvg).toFixed(2) : "—"}
                                                    </span>
                                                    {(row.grade_symbol || row.grade_outcome) && (
                                                        <span className="text-muted-foreground text-[11px]">
                                                            {[row.grade_symbol, row.grade_outcome].filter(Boolean).join(" · ")}
                                                        </span>
                                                    )}
                                                </div>
                                            </TableCell>
                                            <TableCell className="px-2 py-2">
                                                <Input
                                                    type="text"
                                                    value={draft.remarks}
                                                    disabled={rowLocked || savingRowId === row.id}
                                                    onChange={(event) => updateDraft(row, { remarks: event.target.value })}
                                                    placeholder="Add remark…"
                                                    aria-label={`Remarks for ${row.student?.name ?? "student"}`}
                                                    className="h-8 min-w-32 text-xs"
                                                />
                                            </TableCell>
                                            <TableCell className="px-3 py-2">
                                                {isFinalized ? (
                                                    <Tooltip>
                                                        <TooltipTrigger
                                                            render={
                                                                <Badge
                                                                    variant="outline"
                                                                    className="gap-1 border-emerald-600/35 bg-emerald-500/15 text-[11px] text-emerald-700 dark:text-emerald-300"
                                                                />
                                                            }
                                                        >
                                                            <Lock className="size-3" aria-hidden="true" />
                                                            Finalized
                                                        </TooltipTrigger>
                                                        <TooltipContent>Finalized grades are locked. Unlock to modify.</TooltipContent>
                                                    </Tooltip>
                                                ) : isVerified ? (
                                                    <Badge
                                                        variant="outline"
                                                        className="gap-1 border-sky-600/35 bg-sky-500/10 text-[11px] text-sky-700 dark:text-sky-300"
                                                    >
                                                        <Check className="size-3" aria-hidden="true" />
                                                        Verified
                                                    </Badge>
                                                ) : computedAvg !== null ? (
                                                    <Badge
                                                        variant="outline"
                                                        className="border-amber-600/35 bg-amber-500/10 text-[11px] text-amber-700 dark:text-amber-300"
                                                    >
                                                        In Progress
                                                    </Badge>
                                                ) : (
                                                    <Badge variant="secondary" className="text-[11px]">
                                                        Pending
                                                    </Badge>
                                                )}
                                            </TableCell>
                                            {isEditable && (
                                                <TableCell className="px-3 py-2 text-right">
                                                    {dirty ? (
                                                        <div className="inline-flex items-center gap-1">
                                                            <Button
                                                                type="button"
                                                                size="xs"
                                                                disabled={savingRowId === row.id}
                                                                onClick={() => saveRow(row)}
                                                            >
                                                                <Save className="size-3" aria-hidden="true" />
                                                                Save
                                                            </Button>
                                                            <Button
                                                                type="button"
                                                                variant="ghost"
                                                                size="icon-xs"
                                                                disabled={savingRowId === row.id}
                                                                onClick={() => cancelRowDraft(row.id)}
                                                                aria-label="Cancel grade edits"
                                                            >
                                                                <X className="size-3" aria-hidden="true" />
                                                            </Button>
                                                        </div>
                                                    ) : isFinalized ? (
                                                        <Button
                                                            type="button"
                                                            variant="ghost"
                                                            size="xs"
                                                            disabled={bulkActionLoading !== null}
                                                            onClick={() => runGovernanceAction("unlock", [row.id])}
                                                        >
                                                            <Unlock className="size-3" aria-hidden="true" />
                                                            Unlock
                                                        </Button>
                                                    ) : !isVerified && computedAvg !== null ? (
                                                        <Button
                                                            type="button"
                                                            variant="outline"
                                                            size="xs"
                                                            disabled={bulkActionLoading !== null}
                                                            onClick={() => runGovernanceAction("verify", [row.id])}
                                                        >
                                                            <CheckCircle2 className="size-3" aria-hidden="true" />
                                                            Verify
                                                        </Button>
                                                    ) : (
                                                        <span className="text-muted-foreground text-xs">—</span>
                                                    )}
                                                </TableCell>
                                            )}
                                        </TableRow>
                                    );
                                })}
                            </TableBody>
                        </Table>
                    </div>
                )}
            </section>
        </TooltipProvider>
    );
}
