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
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Checkbox } from "@/components/ui/checkbox";
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from "@/components/ui/dialog";
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from "@/components/ui/dropdown-menu";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Switch } from "@/components/ui/switch";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table";
import { Textarea } from "@/components/ui/textarea";
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from "@/components/ui/tooltip";
import { cn } from "@/lib/utils";
import { exportStudentList } from "@/routes/administrators/classes";
import { show as showEnrollment } from "@/routes/administrators/enrollments";
import { Link } from "@inertiajs/react";
import {
    AlertTriangle,
    ArrowRightLeft,
    CheckCircle2,
    Clock,
    Download,
    Loader2,
    Lock,
    RotateCcw,
    Search,
    Trash2,
    UserPlus,
    Users,
} from "lucide-react";
import { useEffect, useMemo, useRef, useState } from "react";
import { toast } from "sonner";
import type { DossierWaitlistEntry, SiblingSection } from "../crud/types";
import type { ClassShowCapabilities, ClassShowData, EnrollmentRow, TransferableClass } from "../record/types";
import {
    deleteClassEnrollment,
    enrollStudentsInClass,
    fetchClassWorkspace,
    patchClassEnrollment,
    postBulkMoveStudents,
    postPromoteWaitlist,
    postRestoreClassEnrollment,
    searchClassCandidates,
    WorkspaceApiError,
} from "../workspace/api";
import type { EnrollmentStatusValue, StudentSearchCandidate, WorkspaceEnrollment } from "../workspace/types";
import { formatShortDateTime } from "./dossier-oversight-tabs";

type RosterFilter = "all" | "enrolled" | "dropped" | "withdrawn" | "clash";

const STATUS_OPTIONS: ReadonlyArray<{ value: EnrollmentStatusValue; label: string }> = [
    { value: "enrolled", label: "Enrolled" },
    { value: "incomplete", label: "Incomplete" },
    { value: "audit", label: "Audit" },
    { value: "withdrawn", label: "Withdrawn" },
    { value: "dropped", label: "Dropped" },
];

function effectiveEnrollmentStatus(row: EnrollmentRow): EnrollmentStatusValue {
    if (row.enrollment_status) return row.enrollment_status;
    return row.status === false ? "dropped" : "enrolled";
}

function statusPillTone(status: EnrollmentStatusValue): string {
    switch (status) {
        case "enrolled":
            return "border-emerald-600/30 bg-emerald-500/10 text-emerald-700 dark:text-emerald-300";
        case "incomplete":
            return "border-amber-600/30 bg-amber-500/10 text-amber-700 dark:text-amber-300";
        case "audit":
            return "border-indigo-600/30 bg-indigo-500/10 text-indigo-700 dark:text-indigo-300";
        case "withdrawn":
        case "dropped":
            return "border-rose-600/30 bg-rose-500/10 text-rose-700 dark:text-rose-300";
    }
}

function mapWorkspaceEnrollment(row: WorkspaceEnrollment): EnrollmentRow {
    return {
        id: row.id,
        student_enrollment_id: null,
        status: row.status,
        enrollment_status: row.enrollment_status,
        status_reason: row.status_reason,
        prelim_grade: row.prelim_grade,
        midterm_grade: row.midterm_grade,
        finals_grade: row.finals_grade,
        total_average: row.total_average,
        remarks: row.remarks,
        is_grades_verified: row.is_grades_verified,
        is_grades_finalized: row.is_grades_finalized,
        is_grade_protected: row.is_grade_protected,
        deleted_at: row.deleted_at,
        timetable_status: row.timetable_status,
        timetable_conflicts: row.timetable_conflicts,
        student: row.student,
    };
}

function recommendedActionBadge(action: StudentSearchCandidate["recommended_action"]): { label: string; className: string } {
    switch (action) {
        case "already_enrolled":
            return { label: "Already Enrolled", className: "border-border bg-muted text-muted-foreground" };
        case "transfer":
            return { label: "Sibling Transfer", className: "border-sky-600/30 bg-sky-500/10 text-sky-700 dark:text-sky-300" };
        case "blocked_hold":
            return { label: "Hold Block", className: "border-rose-600/35 bg-rose-500/10 text-rose-700 dark:text-rose-300" };
        case "blocked_clash":
            return { label: "Timetable Clash", className: "border-rose-600/35 bg-rose-500/10 text-rose-700 dark:text-rose-300" };
        case "overload_required":
            return { label: "Overload Required", className: "border-amber-600/35 bg-amber-500/10 text-amber-700 dark:text-amber-300" };
        default:
            return { label: "Ready to Enroll", className: "border-emerald-600/30 bg-emerald-500/10 text-emerald-700 dark:text-emerald-300" };
    }
}

export function DossierRosterTab({
    classItem,
    enrollments,
    trashedEnrollments,
    waitlist,
    siblingSections,
    transferableClasses,
    capabilities,
    enrollDialogOpen,
    onSetEnrollDialogOpen,
    onMoveStudent,
    onRefresh,
}: {
    classItem: ClassShowData;
    enrollments: EnrollmentRow[];
    trashedEnrollments?: EnrollmentRow[];
    waitlist: DossierWaitlistEntry[];
    siblingSections: SiblingSection[];
    transferableClasses: TransferableClass[];
    capabilities: ClassShowCapabilities;
    enrollDialogOpen: boolean;
    onSetEnrollDialogOpen: (open: boolean) => void;
    onMoveStudent: (student: EnrollmentRow) => void;
    onRefresh: () => void;
}) {
    const canUpdate = capabilities.update && !classItem.is_archived;

    const [search, setSearch] = useState("");
    const [filter, setFilter] = useState<RosterFilter>("all");
    const [showSoftDeleted, setShowSoftDeleted] = useState(false);
    const [fetchedTrashed, setFetchedTrashed] = useState<EnrollmentRow[] | null>(null);
    const [loadingTrashed, setLoadingTrashed] = useState(false);

    const [selectedStudentIds, setSelectedStudentIds] = useState<number[]>([]);
    const [bulkTargetClassId, setBulkTargetClassId] = useState("");
    const [bulkForceOverload, setBulkForceOverload] = useState(false);
    const [bulkOverloadReason, setBulkOverloadReason] = useState("");
    const [bulkTransferIssues, setBulkTransferIssues] = useState<string[]>([]);
    const [bulkMoving, setBulkMoving] = useState(false);

    const [updatingStatusId, setUpdatingStatusId] = useState<number | null>(null);
    const [restoringId, setRestoringId] = useState<number | null>(null);
    const [promotingId, setPromotingId] = useState<number | null>(null);

    // Drop modal state
    const [dropTarget, setDropTarget] = useState<EnrollmentRow | null>(null);
    const [dropReason, setDropReason] = useState("");
    const [confirmGradeOverride, setConfirmGradeOverride] = useState(false);
    const [dropping, setDropping] = useState(false);

    // Load soft-deleted enrollments on demand if not already provided via props
    useEffect(() => {
        if (!showSoftDeleted) return;
        if (trashedEnrollments !== undefined || fetchedTrashed !== null) return;
        let cancelled = false;
        setLoadingTrashed(true);
        fetchClassWorkspace(classItem.id, true)
            .then((payload) => {
                if (cancelled) return;
                const deleted = (payload.enrollments ?? []).filter((e) => Boolean(e.deleted_at)).map(mapWorkspaceEnrollment);
                setFetchedTrashed(deleted);
            })
            .catch(() => {
                if (!cancelled) {
                    toast.error("Could not load removed enrollments. Try again.");
                    setShowSoftDeleted(false);
                }
            })
            .finally(() => {
                if (!cancelled) setLoadingTrashed(false);
            });
        return () => {
            cancelled = true;
            setLoadingTrashed(false);
        };
    }, [showSoftDeleted, trashedEnrollments, fetchedTrashed, classItem.id]);

    const combinedRows = useMemo(() => {
        if (!showSoftDeleted) return enrollments;
        const extraTrashed = trashedEnrollments ?? fetchedTrashed ?? [];
        const existingIds = new Set(enrollments.map((e) => e.id));
        return [...enrollments, ...extraTrashed.filter((t) => !existingIds.has(t.id))];
    }, [enrollments, showSoftDeleted, trashedEnrollments, fetchedTrashed]);

    const counts = useMemo(() => {
        let enrolled = 0;
        let dropped = 0;
        let withdrawn = 0;
        let clash = 0;
        for (const row of combinedRows) {
            const st = effectiveEnrollmentStatus(row);
            if (st === "enrolled" && !row.deleted_at) enrolled++;
            if (st === "dropped" || row.deleted_at) dropped++;
            if (st === "withdrawn") withdrawn++;
            if (row.timetable_status === "clash") clash++;
        }
        return { all: combinedRows.length, enrolled, dropped, withdrawn, clash };
    }, [combinedRows]);

    const filteredRows = useMemo(() => {
        const q = search.trim().toLowerCase();
        return combinedRows.filter((row) => {
            const st = effectiveEnrollmentStatus(row);
            if (filter === "enrolled" && (st !== "enrolled" || Boolean(row.deleted_at))) return false;
            if (filter === "dropped" && st !== "dropped" && !row.deleted_at) return false;
            if (filter === "withdrawn" && st !== "withdrawn") return false;
            if (filter === "clash" && row.timetable_status !== "clash") return false;

            if (!q) return true;
            return [row.student?.name, row.student?.student_id, row.student?.course, row.remarks].some((val) =>
                String(val ?? "")
                    .toLowerCase()
                    .includes(q),
            );
        });
    }, [combinedRows, filter, search]);

    const selectableRows = useMemo(
        () => filteredRows.filter((row) => !row.deleted_at && row.student?.id !== undefined),
        [filteredRows],
    );

    const allSelectableSelected =
        selectableRows.length > 0 &&
        selectableRows.every((row) => row.student && selectedStudentIds.includes(row.student.id));

    const toggleSelectAll = (checked: boolean) => {
        if (checked) {
            setSelectedStudentIds(selectableRows.map((row) => row.student!.id));
        } else {
            setSelectedStudentIds([]);
        }
    };

    const toggleSelectStudent = (studentId: number, checked: boolean) => {
        setSelectedStudentIds((prev) => (checked ? Array.from(new Set([...prev, studentId])) : prev.filter((id) => id !== studentId)));
    };

    const handleStatusChange = async (row: EnrollmentRow, nextStatus: EnrollmentStatusValue) => {
        setUpdatingStatusId(row.id);
        try {
            const response = await patchClassEnrollment(classItem.id, row.id, { enrollment_status: nextStatus });
            toast.success(response.message || `Updated status to ${nextStatus}.`);
            onRefresh();
        } catch (error) {
            toast.error(error instanceof WorkspaceApiError ? error.message : "Could not update enrollment status.");
        } finally {
            setUpdatingStatusId(null);
        }
    };

    const handleConfirmDrop = async () => {
        if (!dropTarget) return;
        const isProtected = Boolean(dropTarget.is_grade_protected);
        if (isProtected && (!dropReason.trim() || !confirmGradeOverride)) {
            toast.error("Provide a reason and confirm the grade-protection override.");
            return;
        }
        setDropping(true);
        try {
            const response = await deleteClassEnrollment(classItem.id, dropTarget.id, {
                reason: dropReason.trim() || null,
                ...(isProtected ? { force_grade_override: true } : {}),
            });
            toast.success(response.message || `Dropped ${dropTarget.student?.name ?? "student"}.`);
            setDropTarget(null);
            setDropReason("");
            setConfirmGradeOverride(false);
            setFetchedTrashed(null);
            onRefresh();
        } catch (error) {
            toast.error(error instanceof WorkspaceApiError ? error.message : "Could not drop student from class.");
        } finally {
            setDropping(false);
        }
    };

    const handleRestore = async (row: EnrollmentRow) => {
        setRestoringId(row.id);
        try {
            const response = await postRestoreClassEnrollment(classItem.id, row.id);
            toast.success(response.message || `Restored ${row.student?.name ?? "student"}.`);
            setFetchedTrashed(null);
            onRefresh();
        } catch (error) {
            toast.error(error instanceof WorkspaceApiError ? error.message : "Could not restore enrollment.");
        } finally {
            setRestoringId(null);
        }
    };

    const handleBulkMove = async () => {
        if (selectedStudentIds.length === 0 || !bulkTargetClassId) return;
        if (bulkForceOverload && !bulkOverloadReason.trim()) {
            toast.error("Enter a reason for the capacity override.");
            return;
        }
        setBulkMoving(true);
        setBulkTransferIssues([]);
        try {
            const response = await postBulkMoveStudents(classItem.id, {
                target_class_id: Number(bulkTargetClassId),
                student_ids: selectedStudentIds,
                force_overload: bulkForceOverload,
                reason: bulkForceOverload ? bulkOverloadReason.trim() : null,
            });
            const result = response.result as typeof response.result & { skipped?: Array<Record<string, unknown>> };
            const unresolved = [...result.errors, ...(result.skipped ?? [])];
            if (unresolved.length) {
                setBulkTransferIssues(unresolved.map((item) => `${item.student_name ?? `Student #${item.student_id}`}: ${item.message ?? "Not moved"}`));
                toast.warning(`${result.moved_count} moved; ${unresolved.length} need attention.`);
                setSelectedStudentIds(unresolved.map((item) => Number(item.student_id)).filter(Number.isFinite));
            } else {
                toast.success(response.message || `Moved ${result.moved_count} student(s).`);
                setSelectedStudentIds([]);
                setBulkTargetClassId("");
                setBulkForceOverload(false);
                setBulkOverloadReason("");
            }
            onRefresh();
        } catch (error) {
            toast.error(error instanceof WorkspaceApiError ? error.message : "Bulk transfer failed.");
        } finally {
            setBulkMoving(false);
        }
    };

    const handlePromoteWaitlist = async (entry: DossierWaitlistEntry) => {
        setPromotingId(entry.id);
        try {
            const response = await postPromoteWaitlist(classItem.id, { waitlist_id: entry.id });
            toast.success(response.message || `Promoted ${entry.student_name} from waitlist.`);
            onRefresh();
        } catch (error) {
            toast.error(error instanceof WorkspaceApiError ? error.message : "Could not promote waitlisted student.");
        } finally {
            setPromotingId(null);
        }
    };

    const siblingById = useMemo(
        () => new Map(siblingSections.map((sib) => [sib.id, sib])),
        [siblingSections],
    );

    return (
        <TooltipProvider>
            <div className="flex flex-col gap-5">
                {/* Main Roster Card */}
                <section className="bg-card overflow-hidden rounded-xl border">
                    {/* Toolbar */}
                    <div className="bg-muted/30 flex flex-col gap-3 border-b p-4">
                        <div className="flex flex-wrap items-center justify-between gap-2.5">
                            <div className="relative min-w-52 flex-1 sm:max-w-xs">
                                <Search
                                    className="text-muted-foreground pointer-events-none absolute top-1/2 left-3 size-3.5 -translate-y-1/2"
                                    aria-hidden="true"
                                />
                                <Input
                                    type="search"
                                    value={search}
                                    onChange={(event) => setSearch(event.target.value)}
                                    placeholder="Search name, student ID, or course…"
                                    aria-label="Search class roster"
                                    className="h-8 pl-8 text-xs"
                                />
                            </div>

                            <div className="flex flex-wrap items-center gap-2">
                                <label className="text-muted-foreground inline-flex cursor-pointer items-center gap-2 text-xs">
                                    <Switch
                                        checked={showSoftDeleted}
                                        onCheckedChange={setShowSoftDeleted}
                                        aria-label="Show soft-deleted enrollments"
                                    />
                                    <span>Show Soft-Deleted</span>
                                    {loadingTrashed && <Loader2 className="size-3 animate-spin" aria-hidden="true" />}
                                </label>

                                <DropdownMenu>
                                    <DropdownMenuTrigger render={<Button variant="outline" size="sm" />}>
                                        <Download className="size-3.5" aria-hidden="true" />
                                        Export
                                    </DropdownMenuTrigger>
                                    <DropdownMenuContent align="end" className="w-44">
                                        <DropdownMenuItem
                                            onClick={() =>
                                                window.open(
                                                    exportStudentList.url(classItem.id, { query: { format: "excel" } }),
                                                    "_blank",
                                                    "noopener,noreferrer",
                                                )
                                            }
                                        >
                                            Download Excel
                                        </DropdownMenuItem>
                                        <DropdownMenuItem
                                            onClick={() =>
                                                window.open(
                                                    exportStudentList.url(classItem.id, { query: { format: "pdf" } }),
                                                    "_blank",
                                                    "noopener,noreferrer",
                                                )
                                            }
                                        >
                                            Download PDF
                                        </DropdownMenuItem>
                                    </DropdownMenuContent>
                                </DropdownMenu>
                            </div>
                        </div>

                        {/* Status Filter Pills */}
                        <div className="flex flex-wrap items-center justify-between gap-2">
                            <div className="flex flex-wrap items-center gap-1.5" role="group" aria-label="Filter roster by status">
                                {(
                                    [
                                        { id: "all", label: "All", count: counts.all },
                                        { id: "enrolled", label: "Enrolled", count: counts.enrolled },
                                        { id: "dropped", label: "Dropped", count: counts.dropped },
                                        { id: "withdrawn", label: "Withdrawn", count: counts.withdrawn },
                                        { id: "clash", label: "Clash", count: counts.clash },
                                    ] as const
                                ).map((pill) => {
                                    const active = filter === pill.id;
                                    return (
                                        <button
                                            key={pill.id}
                                            type="button"
                                            onClick={() => setFilter(pill.id)}
                                            className={cn(
                                                "inline-flex h-7 items-center gap-1.5 rounded-full border px-2.5 text-xs font-medium transition-colors",
                                                active
                                                    ? "border-foreground bg-foreground text-background"
                                                    : "border-border bg-background text-muted-foreground hover:text-foreground",
                                                pill.id === "clash" && pill.count > 0 && !active && "border-rose-500/40 text-rose-700 dark:text-rose-300",
                                            )}
                                        >
                                            <span>{pill.label}</span>
                                            <span className="font-mono text-[11px] opacity-80 tabular-nums">{pill.count}</span>
                                        </button>
                                    );
                                })}
                            </div>
                            <span className="text-muted-foreground text-xs tabular-nums">
                                Showing {filteredRows.length} of {combinedRows.length}
                            </span>
                        </div>

                        {bulkTransferIssues.length > 0 && (
                            <div role="alert" className="border-destructive/30 bg-destructive/5 flex w-full items-start justify-between gap-2 rounded-lg border px-3 py-2 text-xs text-destructive">
                                <div>
                                    <p className="font-medium">Students not moved:</p>
                                    <ul className="list-inside list-disc">{bulkTransferIssues.map((issue, index) => <li key={index}>{issue}</li>)}</ul>
                                </div>
                                <Button type="button" variant="ghost" size="xs" onClick={() => setBulkTransferIssues([])}>
                                    Dismiss
                                </Button>
                            </div>
                        )}
                        {/* Inline Bulk Action Bar */}
                        {canUpdate && selectedStudentIds.length > 0 && (
                            <div className="bg-background flex flex-wrap items-center justify-between gap-2.5 rounded-lg border px-3 py-2">
                                <div className="flex flex-wrap items-center gap-2">
                                    <Badge variant="secondary" className="tabular-nums">
                                        {selectedStudentIds.length} selected
                                    </Badge>
                                    <Select value={bulkTargetClassId} onValueChange={setBulkTargetClassId}>
                                        <SelectTrigger className="h-8 w-56 text-xs" aria-label="Target section for bulk move">
                                            <SelectValue placeholder="Move selected to Section…" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {transferableClasses.length === 0 ? (
                                                <SelectItem value="none" disabled>
                                                    No sibling sections available
                                                </SelectItem>
                                            ) : (
                                                transferableClasses.map((target) => {
                                                    const cleanLabel = target.label
                                                        .replace(/\s*\(\s*Available:\s*-?\d+\/0\s*\)/gi, "")
                                                        .replace(/\s*(\[FULL\]|·\s*Full)\s*$/i, "")
                                                        .trim();
                                                    const isUncapped = target.available_slots === null || target.available_slots === -1;
                                                    return (
                                                        <SelectItem key={target.id} value={String(target.id)}>
                                                            {cleanLabel}
                                                            {!isUncapped && target.is_full ? " · Full" : ""}
                                                        </SelectItem>
                                                    );
                                                })
                                            )}
                                        </SelectContent>
                                    </Select>
                                    <label className="text-muted-foreground inline-flex cursor-pointer items-center gap-1.5 text-xs">
                                        <Checkbox
                                            checked={bulkForceOverload}
                                            onCheckedChange={(checked) => setBulkForceOverload(Boolean(checked))}
                                        />
                                        <span>Allow overload</span>
                                    </label>
                                    {bulkForceOverload && (
                                        <Input value={bulkOverloadReason} onChange={(event) => setBulkOverloadReason(event.target.value)} placeholder="Reason for overload" aria-label="Reason for overload" className="h-8 w-56 text-xs" />
                                    )}
                                </div>
                                <div className="flex items-center gap-1.5">
                                    <Button
                                        type="button"
                                        size="sm"
                                        disabled={!bulkTargetClassId || bulkMoving}
                                        onClick={handleBulkMove}
                                    >
                                        {bulkMoving ? (
                                            <Loader2 className="size-3.5 animate-spin" aria-hidden="true" />
                                        ) : (
                                            <ArrowRightLeft className="size-3.5" aria-hidden="true" />
                                        )}
                                        Move {selectedStudentIds.length} to Section
                                    </Button>
                                    <Button type="button" variant="ghost" size="sm" onClick={() => { setSelectedStudentIds([]); setBulkTransferIssues([]); }}>
                                        Cancel
                                    </Button>
                                </div>
                            </div>
                        )}
                    </div>

                    {/* Roster Table */}
                    <div className="overflow-x-auto">
                        <Table>
                            <TableHeader className="bg-muted/30">
                                <TableRow>
                                    {canUpdate && (
                                        <TableHead className="w-10 px-3 text-center">
                                            <Checkbox
                                                checked={allSelectableSelected}
                                                onCheckedChange={(checked) => toggleSelectAll(Boolean(checked))}
                                                aria-label="Select all visible students"
                                            />
                                        </TableHead>
                                    )}
                                    <TableHead className="px-3 text-xs">Student</TableHead>
                                    <TableHead className="w-36 px-3 text-xs">Status</TableHead>
                                    <TableHead className="w-28 px-3 text-xs">Timetable</TableHead>
                                    <TableHead className="w-32 px-3 text-xs">Grades</TableHead>
                                    <TableHead className="px-3 text-right text-xs">Actions</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {filteredRows.length === 0 ? (
                                    <TableRow>
                                        <TableCell colSpan={canUpdate ? 6 : 5} className="py-12 text-center">
                                            <Users className="text-muted-foreground/60 mx-auto mb-2 size-7" aria-hidden="true" />
                                            <p className="text-sm font-medium">
                                                {search || filter !== "all" ? "No matching students" : "No students enrolled yet"}
                                            </p>
                                            <p className="text-muted-foreground mt-0.5 text-xs">
                                                {search || filter !== "all"
                                                    ? "Try clearing your search or status filter."
                                                    : "Use Enroll Student to add students to this section."}
                                            </p>
                                        </TableCell>
                                    </TableRow>
                                ) : (
                                    filteredRows.map((row) => {
                                        const isDeleted = Boolean(row.deleted_at);
                                        const statusValue = effectiveEnrollmentStatus(row);
                                        const isProtected = Boolean(row.is_grade_protected);
                                        const hasClash = row.timetable_status === "clash";
                                        const studentId = row.student?.id;
                                        const isSelected = Boolean(studentId && selectedStudentIds.includes(studentId));
                                        const canMoveRow = capabilities.update && !classItem.is_archived && !isDeleted;

                                        return (
                                            <TableRow
                                                key={row.id}
                                                className={cn(
                                                    isSelected && "bg-primary/[0.04]",
                                                    isDeleted && "bg-muted/35 opacity-80",
                                                )}
                                            >
                                                {canUpdate && (
                                                    <TableCell className="px-3 text-center">
                                                        {!isDeleted && studentId ? (
                                                            <Checkbox
                                                                checked={isSelected}
                                                                onCheckedChange={(checked) => toggleSelectStudent(studentId, Boolean(checked))}
                                                                aria-label={`Select ${row.student?.name ?? "student"}`}
                                                            />
                                                        ) : null}
                                                    </TableCell>
                                                )}
                                                <TableCell className="px-3 py-2.5">
                                                    <div className="min-w-44 space-y-0.5">
                                                        <p
                                                            className={cn(
                                                                "text-sm leading-tight font-medium",
                                                                (isDeleted || statusValue === "dropped") && "text-muted-foreground line-through",
                                                            )}
                                                        >
                                                            {row.student?.name || "Student unavailable"}
                                                        </p>
                                                        <div className="text-muted-foreground flex flex-wrap items-center gap-1.5 text-[11px]">
                                                            <span className="font-mono">{row.student?.student_id || "No ID"}</span>
                                                            {row.student?.course && (
                                                                <>
                                                                    <span aria-hidden="true">·</span>
                                                                    <span>
                                                                        {row.student.course}
                                                                        {row.student.academic_year ? ` Y${row.student.academic_year}` : ""}
                                                                    </span>
                                                                </>
                                                            )}
                                                            {isDeleted && (
                                                                <Badge variant="outline" className="h-4 px-1 text-[10px]">
                                                                    Removed
                                                                </Badge>
                                                            )}
                                                        </div>
                                                    </div>
                                                </TableCell>
                                                <TableCell className="px-3 py-2.5">
                                                    {canUpdate && !isDeleted && !isProtected ? (
                                                        <select
                                                            value={statusValue}
                                                            disabled={updatingStatusId === row.id}
                                                            onChange={(event) =>
                                                                handleStatusChange(row, event.target.value as EnrollmentStatusValue)
                                                            }
                                                            aria-label={`Enrollment status for ${row.student?.name ?? "student"}`}
                                                            className={cn(
                                                                "h-7 rounded-full border px-2.5 text-xs font-semibold outline-none focus-visible:ring-2 focus-visible:ring-ring disabled:opacity-50",
                                                                statusPillTone(statusValue),
                                                            )}
                                                        >
                                                            {STATUS_OPTIONS.map((opt) => (
                                                                <option
                                                                    key={opt.value}
                                                                    value={opt.value}
                                                                    className="bg-background text-foreground"
                                                                >
                                                                    {opt.label}
                                                                </option>
                                                            ))}
                                                        </select>
                                                    ) : (
                                                        <Tooltip>
                                                            <TooltipTrigger
                                                                render={
                                                                    <Badge
                                                                        variant="outline"
                                                                        className={cn("gap-1 text-xs capitalize", statusPillTone(statusValue))}
                                                                    />
                                                                }
                                                            >
                                                                {isProtected && <Lock className="size-3" aria-hidden="true" />}
                                                                {statusValue}
                                                            </TooltipTrigger>
                                                            <TooltipContent>
                                                                {isProtected
                                                                    ? "Grade-protected enrollment: status changes are locked."
                                                                    : isDeleted
                                                                      ? "Soft-deleted enrollment. Restore to reactivate."
                                                                      : "Read-only enrollment status."}
                                                            </TooltipContent>
                                                        </Tooltip>
                                                    )}
                                                </TableCell>
                                                <TableCell className="px-3 py-2.5">
                                                    {hasClash ? (
                                                        <Tooltip>
                                                            <TooltipTrigger
                                                                render={
                                                                    <Badge
                                                                        variant="outline"
                                                                        className="gap-1 border-rose-600/35 bg-rose-500/10 text-xs text-rose-700 dark:text-rose-300"
                                                                    />
                                                                }
                                                            >
                                                                <AlertTriangle className="size-3" aria-hidden="true" />
                                                                Clash
                                                            </TooltipTrigger>
                                                            <TooltipContent className="max-w-xs">
                                                                <div className="space-y-1">
                                                                    <p className="font-semibold">Timetable Conflicts</p>
                                                                    {(row.timetable_conflicts ?? []).length > 0 ? (
                                                                        <ul className="list-disc pl-4 text-xs">
                                                                            {row.timetable_conflicts!.map((c, i) => (
                                                                                <li key={i}>{c}</li>
                                                                            ))}
                                                                        </ul>
                                                                    ) : (
                                                                        <p className="text-xs">Overlaps another enrolled section.</p>
                                                                    )}
                                                                </div>
                                                            </TooltipContent>
                                                        </Tooltip>
                                                    ) : (
                                                        <Badge
                                                            variant="outline"
                                                            className="gap-1 border-emerald-600/30 bg-emerald-500/10 text-xs text-emerald-700 dark:text-emerald-300"
                                                        >
                                                            <CheckCircle2 className="size-3" aria-hidden="true" />
                                                            OK
                                                        </Badge>
                                                    )}
                                                </TableCell>
                                                <TableCell className="px-3 py-2.5">
                                                    {row.is_grades_finalized ? (
                                                        <Badge
                                                            variant="outline"
                                                            className="gap-1 border-emerald-600/35 bg-emerald-500/10 text-[11px] text-emerald-700 dark:text-emerald-300"
                                                        >
                                                            <Lock className="size-2.5" aria-hidden="true" />
                                                            Finalized
                                                        </Badge>
                                                    ) : row.is_grades_verified ? (
                                                        <Badge
                                                            variant="outline"
                                                            className="border-sky-600/35 bg-sky-500/10 text-[11px] text-sky-700 dark:text-sky-300"
                                                        >
                                                            Verified
                                                        </Badge>
                                                    ) : row.total_average !== null ? (
                                                        <span className="font-mono text-xs font-medium tabular-nums">
                                                            {Number(row.total_average).toFixed(2)}
                                                        </span>
                                                    ) : (
                                                        <Badge variant="secondary" className="text-[11px]">
                                                            Pending
                                                        </Badge>
                                                    )}
                                                </TableCell>
                                                <TableCell className="px-3 py-2.5 text-right">
                                                    <div className="inline-flex items-center justify-end gap-1">
                                                        {row.student_enrollment_id && (
                                                            <Button variant="ghost" size="xs" nativeButton={false} render={<Link href={showEnrollment.url(row.student_enrollment_id)} />}>
                                                                View enrollment
                                                            </Button>
                                                        )}
                                                        {isDeleted || effectiveEnrollmentStatus(row) === "dropped" ? (
                                                            canUpdate && (
                                                                <Button
                                                                    type="button"
                                                                    variant="outline"
                                                                    size="xs"
                                                                    disabled={restoringId === row.id}
                                                                    onClick={() => handleRestore(row)}
                                                                >
                                                                    {restoringId === row.id ? (
                                                                        <Loader2 className="size-3 animate-spin" />
                                                                    ) : (
                                                                        <RotateCcw className="size-3" />
                                                                    )}
                                                                    Restore
                                                                </Button>
                                                            )
                                                        ) : (
                                                            <>
                                                                {canMoveRow && (
                                                                    <Button
                                                                        type="button"
                                                                        variant="outline"
                                                                        size="xs"
                                                                        onClick={() => onMoveStudent(row)}
                                                                    >
                                                                        Move
                                                                    </Button>
                                                                )}
                                                                {canUpdate && (
                                                                    <Button
                                                                        type="button"
                                                                        variant="ghost"
                                                                        size="xs"
                                                                        className="text-destructive hover:bg-destructive/10 hover:text-destructive"
                                                                        onClick={() => {
                                                                            setDropTarget(row);
                                                                            setDropReason("");
                                                                            setConfirmGradeOverride(false);
                                                                        }}
                                                                    >
                                                                        {isProtected ? (
                                                                            <Lock className="size-3" aria-hidden="true" />
                                                                        ) : (
                                                                            <Trash2 className="size-3" aria-hidden="true" />
                                                                        )}
                                                                        Drop
                                                                    </Button>
                                                                )}
                                                            </>
                                                        )}
                                                    </div>
                                                </TableCell>
                                            </TableRow>
                                        );
                                    })
                                )}
                            </TableBody>
                        </Table>
                    </div>
                </section>

                {/* FIFO Waitlist Queue Section */}
                <section className="bg-card overflow-hidden rounded-xl border">
                    <div className="bg-muted/30 flex flex-wrap items-center justify-between gap-2 border-b px-4 py-3">
                        <div className="flex items-center gap-2">
                            <Clock className="size-4 text-amber-600 dark:text-amber-400" aria-hidden="true" />
                            <h3 className="text-sm font-semibold">FIFO Waitlist Queue</h3>
                            <Badge
                                variant="outline"
                                className="border-amber-600/35 bg-amber-500/10 text-xs text-amber-700 tabular-nums dark:text-amber-300"
                            >
                                {waitlist.length} waiting
                            </Badge>
                        </div>
                        <span className="text-muted-foreground text-xs">Ordered first-in, first-out by request timestamp</span>
                    </div>

                    {waitlist.length === 0 ? (
                        <div className="px-4 py-6 text-center text-xs text-muted-foreground">
                            No students are currently waiting for a seat in this section.
                        </div>
                    ) : (
                        <ul className="divide-y">
                            {waitlist.map((entry, idx) => {
                                const compatEntries = Object.entries(entry.compatibility ?? {});
                                return (
                                    <li
                                        key={entry.id}
                                        className="flex flex-col gap-2.5 px-4 py-3 sm:flex-row sm:items-center sm:justify-between"
                                    >
                                        <div className="flex min-w-0 items-start gap-3">
                                            <span className="flex size-7 shrink-0 items-center justify-center rounded-md border border-amber-500/35 bg-amber-500/10 font-mono text-xs font-bold text-amber-700 dark:text-amber-300">
                                                #{entry.position ?? idx + 1}
                                            </span>
                                            <div className="min-w-0 space-y-1">
                                                <div className="flex flex-wrap items-center gap-2">
                                                    <span className="text-sm font-semibold">{entry.student_name}</span>
                                                    {entry.student_number && (
                                                        <span className="text-muted-foreground font-mono text-xs">
                                                            {entry.student_number}
                                                        </span>
                                                    )}
                                                    {entry.queued_at && (
                                                        <span className="text-muted-foreground text-[11px]">
                                                            Queued {formatShortDateTime(entry.queued_at)}
                                                        </span>
                                                    )}
                                                </div>
                                                {compatEntries.length > 0 && (
                                                    <div className="flex flex-wrap items-center gap-1.5">
                                                        {compatEntries.map(([secIdStr, compat]) => {
                                                            const sib = siblingById.get(Number(secIdStr));
                                                            const secLabel = sib ? `Sec ${sib.section}` : `#${secIdStr}`;
                                                            const tone =
                                                                compat.status === "clear"
                                                                    ? "border-emerald-600/30 bg-emerald-500/10 text-emerald-700 dark:text-emerald-300"
                                                                    : compat.status === "full"
                                                                      ? "border-amber-600/30 bg-amber-500/10 text-amber-700 dark:text-amber-300"
                                                                      : "border-rose-600/30 bg-rose-500/10 text-rose-700 dark:text-rose-300";
                                                            return (
                                                                <Badge
                                                                    key={secIdStr}
                                                                    variant="outline"
                                                                    title={compat.reason ?? undefined}
                                                                    className={cn("text-[10px]", tone)}
                                                                >
                                                                    {secLabel}: {compat.status}
                                                                </Badge>
                                                            );
                                                        })}
                                                    </div>
                                                )}
                                            </div>
                                        </div>

                                        {canUpdate && (
                                            <Button
                                                type="button"
                                                size="sm"
                                                variant="outline"
                                                disabled={promotingId === entry.id}
                                                onClick={() => handlePromoteWaitlist(entry)}
                                                className="shrink-0 self-start sm:self-center"
                                            >
                                                {promotingId === entry.id ? (
                                                    <Loader2 className="size-3.5 animate-spin" />
                                                ) : (
                                                    <UserPlus className="size-3.5" />
                                                )}
                                                Promote
                                            </Button>
                                        )}
                                    </li>
                                );
                            })}
                        </ul>
                    )}
                </section>

                {/* Drop / Grade-Protected Override Dialog */}
                <AlertDialog open={Boolean(dropTarget)} onOpenChange={(open) => !open && setDropTarget(null)}>
                    <AlertDialogContent>
                        <AlertDialogHeader>
                            <AlertDialogTitle>
                                {dropTarget?.is_grade_protected ? "Drop grade-protected student?" : "Drop student from class?"}
                            </AlertDialogTitle>
                            <AlertDialogDescription>
                                {dropTarget?.is_grade_protected
                                    ? `${dropTarget.student?.name ?? "This student"} has verified or finalized grades. Dropping requires an explicit reason and grade-override confirmation.`
                                    : `Remove ${dropTarget?.student?.name ?? "this student"} from ${classItem.record_title}. You can restore soft-deleted enrollments later.`}
                            </AlertDialogDescription>
                        </AlertDialogHeader>

                        <div className="space-y-3 py-1">
                            <div className="space-y-1.5">
                                <Label htmlFor="drop-reason" className="text-xs">
                                    Reason {dropTarget?.is_grade_protected ? "(required)" : "(optional)"}
                                </Label>
                                <Textarea
                                    id="drop-reason"
                                    value={dropReason}
                                    onChange={(event) => setDropReason(event.target.value)}
                                    placeholder="Document why this student is being dropped…"
                                    rows={3}
                                    className="text-sm"
                                />
                            </div>

                            {dropTarget?.is_grade_protected && (
                                <label className="flex items-start gap-2 rounded-lg border border-rose-500/30 bg-rose-500/10 p-3 text-xs text-rose-800 dark:text-rose-200">
                                    <Checkbox
                                        checked={confirmGradeOverride}
                                        onCheckedChange={(checked) => setConfirmGradeOverride(Boolean(checked))}
                                        className="mt-0.5"
                                    />
                                    <span>
                                        I understand this enrollment is grade-protected and authorize forcing a grade override drop.
                                    </span>
                                </label>
                            )}
                        </div>

                        <AlertDialogFooter>
                            <AlertDialogCancel disabled={dropping}>Cancel</AlertDialogCancel>
                            <AlertDialogAction
                                disabled={
                                    dropping ||
                                    (Boolean(dropTarget?.is_grade_protected) && (!dropReason.trim() || !confirmGradeOverride))
                                }
                                onClick={(event) => {
                                    event.preventDefault();
                                    void handleConfirmDrop();
                                }}
                                className="bg-destructive text-destructive-foreground hover:bg-destructive/90"
                            >
                                {dropping ? "Dropping…" : "Confirm Drop"}
                            </AlertDialogAction>
                        </AlertDialogFooter>
                    </AlertDialogContent>
                </AlertDialog>

                {/* Enroll Student Dialog */}
                <EnrollStudentDialog
                    open={enrollDialogOpen}
                    onOpenChange={onSetEnrollDialogOpen}
                    classItem={classItem}
                    onEnrolled={onRefresh}
                />
            </div>
        </TooltipProvider>
    );
}

/* ------------------------------------------------------------------ */
/* Enroll Student Dialog                                               */
/* ------------------------------------------------------------------ */

function EnrollStudentDialog({
    open,
    onOpenChange,
    classItem,
    onEnrolled,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    classItem: ClassShowData;
    onEnrolled: () => void;
}) {
    const [query, setQuery] = useState("");
    const [candidates, setCandidates] = useState<StudentSearchCandidate[]>([]);
    const [searching, setSearching] = useState(false);
    const [searchError, setSearchError] = useState(false);
    const [searchAttempt, setSearchAttempt] = useState(0);
    const [selectedIds, setSelectedIds] = useState<number[]>([]);
    const [forceOverload, setForceOverload] = useState(false);
    const [allowClashOverride, setAllowClashOverride] = useState(false);
    const [allowHoldOverride, setAllowHoldOverride] = useState(false);
    const [allowTransferFromSibling, setAllowTransferFromSibling] = useState(false);
    const [overrideReason, setOverrideReason] = useState("");
    const [submitting, setSubmitting] = useState(false);
    const requestSeq = useRef(0);

    useEffect(() => {
        if (!open) return;
        const trimmed = query.trim();
        const seq = ++requestSeq.current;
        const timer = window.setTimeout(() => {
            setSearching(true);
            setSearchError(false);
            searchClassCandidates(classItem.id, trimmed, 15)
                .then((rows) => {
                    if (seq === requestSeq.current) {
                        setCandidates(rows);
                    }
                })
                .catch(() => {
                    if (seq === requestSeq.current) {
                        setCandidates([]);
                        setSearchError(true);
                    }
                })
                .finally(() => {
                    if (seq === requestSeq.current) {
                        setSearching(false);
                    }
                });
        }, 280);

        return () => window.clearTimeout(timer);
    }, [open, query, classItem.id, searchAttempt]);

    const toggleCandidate = (candidate: StudentSearchCandidate, checked: boolean) => {
        if (candidate.already_enrolled_here) return;
        setSelectedIds((prev) => (checked ? Array.from(new Set([...prev, candidate.id])) : prev.filter((id) => id !== candidate.id)));
    };

    const anyOverride = forceOverload || allowClashOverride || allowHoldOverride || allowTransferFromSibling;

    const resetAndClose = () => {
        setQuery("");
        setSelectedIds([]);
        setForceOverload(false);
        setAllowClashOverride(false);
        setAllowHoldOverride(false);
        setAllowTransferFromSibling(false);
        setOverrideReason("");
        onOpenChange(false);
    };

    const handleSubmit = async () => {
        if (selectedIds.length === 0) return;
        setSubmitting(true);
        try {
            const response = await enrollStudentsInClass(classItem.id, {
                student_ids: selectedIds,
                force_overload: forceOverload,
                allow_clash_override: allowClashOverride,
                allow_hold_override: allowHoldOverride,
                allow_transfer_from_sibling: allowTransferFromSibling,
                override_reason: overrideReason.trim() || null,
            });
            const errCount = response.result?.errors?.length ?? 0;
            if (errCount > 0) {
                toast.warning(response.message || `Enrolled with ${errCount} error(s).`);
            } else {
                toast.success(response.message || "Student(s) enrolled successfully.");
            }
            resetAndClose();
            onEnrolled();
        } catch (error) {
            toast.error(error instanceof WorkspaceApiError ? error.message : "Could not enroll selected student(s).");
        } finally {
            setSubmitting(false);
        }
    };

    return (
        <Dialog open={open} onOpenChange={(next) => (next ? onOpenChange(true) : resetAndClose())}>
            <DialogContent className="sm:max-w-2xl">
                <DialogHeader>
                    <DialogTitle>Enroll Student in {classItem.record_title}</DialogTitle>
                    <DialogDescription>
                        Search students by name, ID, or LRN. Eligibility checks for holds, timetable clashes, sibling enrollment, and
                        capacity run automatically.
                    </DialogDescription>
                </DialogHeader>

                <div className="space-y-4">
                    <div className="relative">
                        <Search
                            className="text-muted-foreground pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2"
                            aria-hidden="true"
                        />
                        <Input
                            value={query}
                            onChange={(event) => setQuery(event.target.value)}
                            placeholder="Type student name, student ID, or LRN…"
                            className="pl-9"
                            autoFocus
                        />
                        {searching && (
                            <Loader2
                                className="text-muted-foreground absolute top-1/2 right-3 size-4 -translate-y-1/2 animate-spin"
                                aria-hidden="true"
                            />
                        )}
                    </div>

                    {/* Candidate Results */}
                    <div className="max-h-72 overflow-y-auto rounded-lg border">
                        {candidates.length === 0 ? (
                            <div className="px-4 py-8 text-center text-xs text-muted-foreground">
                                {searching ? "Searching candidates…" : searchError ? (
                                    <span>Search failed. <button type="button" className="underline" onClick={() => setSearchAttempt((value) => value + 1)}>Try again</button></span>
                                ) : "No matching students found."}
                            </div>
                        ) : (
                            <ul className="divide-y">
                                {candidates.map((candidate) => {
                                    const badge = recommendedActionBadge(candidate.recommended_action);
                                    const checked = selectedIds.includes(candidate.id);

                                    return (
                                        <li
                                            key={candidate.id}
                                            className={cn(
                                                "flex items-start gap-3 px-3.5 py-2.5 transition-colors",
                                                candidate.already_enrolled_here ? "bg-muted/30 opacity-65" : "hover:bg-muted/25",
                                                checked && "bg-primary/[0.05]",
                                            )}
                                        >
                                            <Checkbox
                                                checked={checked}
                                                disabled={candidate.already_enrolled_here}
                                                onCheckedChange={(val) => toggleCandidate(candidate, Boolean(val))}
                                                className="mt-1"
                                                aria-label={`Select ${candidate.full_name}`}
                                            />
                                            <div className="min-w-0 flex-1 space-y-1">
                                                <div className="flex flex-wrap items-center justify-between gap-2">
                                                    <div className="min-w-0">
                                                        <p className="truncate text-sm font-medium">{candidate.full_name}</p>
                                                        <p className="text-muted-foreground font-mono text-[11px]">
                                                            {candidate.student_id || candidate.lrn || "No ID"}
                                                            {candidate.course ? ` · ${candidate.course}` : ""}
                                                            {candidate.academic_year ? ` Y${candidate.academic_year}` : ""}
                                                        </p>
                                                    </div>
                                                    <Badge variant="outline" className={cn("text-[11px]", badge.className)}>
                                                        {badge.label}
                                                    </Badge>
                                                </div>

                                                {/* Risk / Eligibility Notices */}
                                                <div className="flex flex-wrap gap-1.5 text-[11px]">
                                                    {candidate.holds.active_count > 0 && (
                                                        <span className="rounded bg-rose-500/10 px-1.5 py-0.5 text-rose-700 dark:text-rose-300">
                                                            Holds ({candidate.holds.active_count}):{" "}
                                                            {candidate.holds.offices.join(", ") || "Active hold"}
                                                        </span>
                                                    )}
                                                    {candidate.schedule_conflicts.length > 0 && (
                                                        <span className="rounded bg-rose-500/10 px-1.5 py-0.5 text-rose-700 dark:text-rose-300">
                                                            Clash:{" "}
                                                            {candidate.schedule_conflicts
                                                                .map((c) => c.label || `${c.subject_code ?? ""} ${c.day ?? ""}`.trim())
                                                                .filter(Boolean)
                                                                .join("; ")}
                                                        </span>
                                                    )}
                                                    {candidate.sibling_section_enrollment && (
                                                        <span className="rounded bg-sky-500/10 px-1.5 py-0.5 text-sky-700 dark:text-sky-300">
                                                            Enrolled in Sec {candidate.sibling_section_enrollment.section}
                                                        </span>
                                                    )}
                                                    {(candidate.is_class_full || candidate.would_exceed_capacity) && (
                                                        <span className="rounded bg-amber-500/10 px-1.5 py-0.5 text-amber-700 dark:text-amber-300">
                                                            Exceeds section capacity
                                                        </span>
                                                    )}
                                                </div>
                                            </div>
                                        </li>
                                    );
                                })}
                            </ul>
                        )}
                    </div>

                    {/* Override Checkboxes */}
                    <div className="bg-muted/25 space-y-2.5 rounded-lg border p-3.5">
                        <p className="text-xs font-semibold">Enrollment Overrides (Optional)</p>
                        <div className="grid grid-cols-1 gap-2 sm:grid-cols-2">
                            <label className="flex cursor-pointer items-center gap-2 text-xs">
                                <Checkbox checked={forceOverload} onCheckedChange={(v) => setForceOverload(Boolean(v))} />
                                <span>Force capacity overload</span>
                            </label>
                            <label className="flex cursor-pointer items-center gap-2 text-xs">
                                <Checkbox checked={allowClashOverride} onCheckedChange={(v) => setAllowClashOverride(Boolean(v))} />
                                <span>Allow timetable clash override</span>
                            </label>
                            <label className="flex cursor-pointer items-center gap-2 text-xs">
                                <Checkbox checked={allowHoldOverride} onCheckedChange={(v) => setAllowHoldOverride(Boolean(v))} />
                                <span>Allow administrative hold override</span>
                            </label>
                            <label className="flex cursor-pointer items-center gap-2 text-xs">
                                <Checkbox
                                    checked={allowTransferFromSibling}
                                    onCheckedChange={(v) => setAllowTransferFromSibling(Boolean(v))}
                                />
                                <span>Transfer from sibling section</span>
                            </label>
                        </div>

                        {anyOverride && (
                            <div className="space-y-1 pt-1">
                                <Label htmlFor="enroll-override-reason" className="text-xs">
                                    Override reason
                                </Label>
                                <Input
                                    id="enroll-override-reason"
                                    value={overrideReason}
                                    onChange={(event) => setOverrideReason(event.target.value)}
                                    placeholder="Document registrar approval or justification…"
                                    className="h-8 text-xs"
                                />
                            </div>
                        )}
                    </div>
                </div>

                <DialogFooter>
                    <Button type="button" variant="outline" disabled={submitting} onClick={resetAndClose}>
                        Cancel
                    </Button>
                    <Button type="button" disabled={submitting || selectedIds.length === 0} onClick={handleSubmit}>
                        {submitting ? (
                            <>
                                <Loader2 className="size-4 animate-spin" aria-hidden="true" />
                                Enrolling…
                            </>
                        ) : (
                            <>
                                <UserPlus className="size-4" aria-hidden="true" />
                                Enroll Selected{selectedIds.length > 0 ? ` (${selectedIds.length})` : ""}
                            </>
                        )}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
