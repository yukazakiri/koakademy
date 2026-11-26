import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Checkbox } from "@/components/ui/checkbox";
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from "@/components/ui/dialog";
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Skeleton } from "@/components/ui/skeleton";
import { Textarea } from "@/components/ui/textarea";
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from "@/components/ui/tooltip";
import { cn } from "@/lib/utils";
import { exportStudentList, show } from "@/routes/administrators/classes";
import { Link } from "@inertiajs/react";
import {
    AlertTriangle,
    ArrowRightLeft,
    CalendarClock,
    CheckCircle2,
    ChevronDown,
    ChevronRight,
    Clock,
    Download,
    Layers,
    Loader2,
    Lock,
    MapPin,
    RotateCcw,
    Search,
    Trash2,
    UserPlus,
    Users,
    Wifi,
} from "lucide-react";
import * as React from "react";
import { toast } from "sonner";
import type { ClassCapabilities, ClassRow } from "../columns";
import { classProgramLabel, semesterLabel } from "../components/class-list-details";
import { ClassRowActions, type ClassActionHandlers } from "../components/class-row-actions";
import { deliveryModeLabel, deliveryModeShortLabel, isOnlineDeliveryMode } from "../lib/class-defaults";
import {
    deleteClassEnrollment,
    patchClassEnrollment,
    postBulkMoveStudents,
    postRestoreClassEnrollment,
    WorkspaceApiError,
} from "./api";
import type {
    ClassWorkspacePayload,
    EnrollmentStatusValue,
    IndexScheduleBlock,
    WorkspaceEnrollment,
} from "./types";

export interface ExpandableClassLedgerProps {
    classes: ClassRow[];
    rowSelection: Record<string, boolean>;
    onRowSelectionChange: (updater: Record<string, boolean>) => void;
    expandedClassIds: number[];
    onToggleExpand: (classId: number) => void;
    activeStudioClassId: number | null;
    studioMode: "enroll" | "schedule";
    onOpenStudio: (classId: number, mode: "enroll" | "schedule") => void;
    onOpenSectionStudio: (classId: number) => void;
    workspaceCache: Record<number, ClassWorkspacePayload>;
    loadingWorkspaceIds: Record<number, boolean>;
    onRefreshWorkspace: (classId: number) => Promise<void>;
    onRefreshIndex: () => void;
    capabilities: ClassCapabilities;
    density: "compact" | "comfortable";
    rowActions: ClassActionHandlers;
}

const DAY_SHORT: Record<string, string> = {
    Monday: "M",
    Tuesday: "T",
    Wednesday: "W",
    Thursday: "Th",
    Friday: "F",
    Saturday: "Sa",
    Sunday: "Su",
};

const DAY_MEDIUM: Record<string, string> = {
    Monday: "Mon",
    Tuesday: "Tue",
    Wednesday: "Wed",
    Thursday: "Thu",
    Friday: "Fri",
    Saturday: "Sat",
    Sunday: "Sun",
};

const DAY_ORDER: Record<string, number> = {
    Monday: 0,
    Tuesday: 1,
    Wednesday: 2,
    Thursday: 3,
    Friday: 4,
    Saturday: 5,
    Sunday: 6,
};

type GroupedSchedulePill = {
    key: string;
    daysLabel: string;
    timeLabel: string;
    component: "lecture" | "lab";
    roomLabel: string | null;
    roomCapacity: number | null;
    isOnline: boolean;
    hasConflict: boolean;
    isOvercrowded: boolean;
};

function groupSchedulesForLedger(schedules: IndexScheduleBlock[] | undefined): GroupedSchedulePill[] {
    if (!schedules || schedules.length === 0) return [];

    const groups = new Map<
        string,
        {
            days: string[];
            start: string;
            end: string;
            component: "lecture" | "lab";
            roomLabel: string | null;
            roomCapacity: number | null;
            isOnline: boolean;
            hasConflict: boolean;
            isOvercrowded: boolean;
        }
    >();

    for (const block of schedules) {
        const component: "lecture" | "lab" = block.component_type === "lab" ? "lab" : "lecture";
        const isOnline = isOnlineDeliveryMode(block.delivery_mode);
        const start = (block.start_time ?? "").slice(0, 5);
        const end = (block.end_time ?? "").slice(0, 5);
        const roomLabel = isOnline ? deliveryModeShortLabel(block.delivery_mode) : block.room_name;
        const key = `${component}|${start}|${end}|${roomLabel ?? ""}|${isOnline ? "1" : "0"}`;

        const existing = groups.get(key);
        if (existing) {
            if (!existing.days.includes(block.day_of_week)) {
                existing.days.push(block.day_of_week);
            }
            existing.hasConflict = existing.hasConflict || block.has_conflict;
            existing.isOvercrowded = existing.isOvercrowded || block.is_overcrowded;
        } else {
            groups.set(key, {
                days: [block.day_of_week],
                start,
                end,
                component,
                roomLabel,
                roomCapacity: block.room_capacity,
                isOnline,
                hasConflict: block.has_conflict,
                isOvercrowded: block.is_overcrowded,
            });
        }
    }

    return Array.from(groups.entries()).map(([key, g]) => {
        const sortedDays = [...g.days].sort((a, b) => (DAY_ORDER[a] ?? 9) - (DAY_ORDER[b] ?? 9));
        const daysLabel =
            sortedDays.length === 1
                ? (DAY_MEDIUM[sortedDays[0]] ?? sortedDays[0].slice(0, 3))
                : sortedDays.map((d) => DAY_SHORT[d] ?? d.slice(0, 2)).join("");

        return {
            key,
            daysLabel,
            timeLabel: g.start ? (g.end ? `${g.start}–${g.end}` : g.start) : "TBA",
            component: g.component,
            roomLabel: g.roomLabel,
            roomCapacity: g.roomCapacity,
            isOnline: g.isOnline,
            hasConflict: g.hasConflict,
            isOvercrowded: g.isOvercrowded,
        };
    });
}

function formatHours(value: number | undefined | null): string {
    if (value === undefined || value === null || Number.isNaN(value)) return "0";
    return Number.isInteger(value) ? String(value) : value.toFixed(1);
}

type RosterFilterTab = "all" | "enrolled" | "incomplete" | "audit" | "dropped";

type PendingGradeOverride =
    | {
          kind: "status";
          enrollment: WorkspaceEnrollment;
          nextStatus: EnrollmentStatusValue;
      }
    | {
          kind: "drop";
          enrollment: WorkspaceEnrollment;
      };

interface InlineEnrollmentRowProps {
    classId: number;
    enrollment: WorkspaceEnrollment;
    siblingSections: ClassWorkspacePayload["sibling_sections"];
    checked: boolean;
    onCheckedChange: (checked: boolean) => void;
    canUpdate: boolean;
    onRequestOverride: (override: PendingGradeOverride) => void;
    onMutated: () => Promise<void>;
    onRefreshIndex: () => void;
}

function InlineEnrollmentRow({
    classId,
    enrollment,
    siblingSections,
    checked,
    onCheckedChange,
    canUpdate,
    onRequestOverride,
    onMutated,
    onRefreshIndex,
}: InlineEnrollmentRowProps) {
    const [prelim, setPrelim] = React.useState(enrollment.prelim_grade !== null ? String(enrollment.prelim_grade) : "");
    const [midterm, setMidterm] = React.useState(enrollment.midterm_grade !== null ? String(enrollment.midterm_grade) : "");
    const [finals, setFinals] = React.useState(enrollment.finals_grade !== null ? String(enrollment.finals_grade) : "");
    const [remarks, setRemarks] = React.useState(enrollment.remarks ?? "");
    const [busy, setBusy] = React.useState(false);

    React.useEffect(() => {
        setPrelim(enrollment.prelim_grade !== null ? String(enrollment.prelim_grade) : "");
        setMidterm(enrollment.midterm_grade !== null ? String(enrollment.midterm_grade) : "");
        setFinals(enrollment.finals_grade !== null ? String(enrollment.finals_grade) : "");
        setRemarks(enrollment.remarks ?? "");
    }, [enrollment.prelim_grade, enrollment.midterm_grade, enrollment.finals_grade, enrollment.remarks]);

    const gradesLocked = enrollment.is_grades_verified || enrollment.is_grades_finalized;
    const isDroppedOrWithdrawn =
        enrollment.enrollment_status === "dropped" ||
        enrollment.enrollment_status === "withdrawn" ||
        Boolean(enrollment.deleted_at);

    const commitGrade = async (field: "prelim_grade" | "midterm_grade" | "finals_grade", rawValue: string, original: number | null) => {
        if (!canUpdate || gradesLocked) return;
        const trimmed = rawValue.trim();
        const parsed = trimmed === "" ? null : Number(trimmed);

        if (parsed !== null && (Number.isNaN(parsed) || parsed < 0 || parsed > 100)) {
            toast.error("Grades must be between 0 and 100.");
            setPrelim(enrollment.prelim_grade !== null ? String(enrollment.prelim_grade) : "");
            setMidterm(enrollment.midterm_grade !== null ? String(enrollment.midterm_grade) : "");
            setFinals(enrollment.finals_grade !== null ? String(enrollment.finals_grade) : "");
            return;
        }

        if (parsed === original) return;

        setBusy(true);
        try {
            await patchClassEnrollment(classId, enrollment.id, { [field]: parsed });
            toast.success("Grade saved.");
            await onMutated();
        } catch (err) {
            const message = err instanceof WorkspaceApiError ? err.message : "Failed to save grade.";
            toast.error(message);
            setPrelim(enrollment.prelim_grade !== null ? String(enrollment.prelim_grade) : "");
            setMidterm(enrollment.midterm_grade !== null ? String(enrollment.midterm_grade) : "");
            setFinals(enrollment.finals_grade !== null ? String(enrollment.finals_grade) : "");
        } finally {
            setBusy(false);
        }
    };

    const commitRemarks = async () => {
        if (!canUpdate) return;
        const trimmed = remarks.trim();
        const original = (enrollment.remarks ?? "").trim();
        if (trimmed === original) return;

        setBusy(true);
        try {
            await patchClassEnrollment(classId, enrollment.id, { remarks: trimmed || null });
            toast.success("Remarks updated.");
            await onMutated();
        } catch (err) {
            toast.error(err instanceof WorkspaceApiError ? err.message : "Failed to update remarks.");
            setRemarks(enrollment.remarks ?? "");
        } finally {
            setBusy(false);
        }
    };

    const handleStatusChange = async (value: string) => {
        if (!canUpdate) return;
        const nextStatus = value as EnrollmentStatusValue;
        if (nextStatus === enrollment.enrollment_status) return;

        if ((nextStatus === "dropped" || nextStatus === "withdrawn") && enrollment.is_grade_protected) {
            onRequestOverride({ kind: "status", enrollment, nextStatus });
            return;
        }

        setBusy(true);
        try {
            const response = await patchClassEnrollment(classId, enrollment.id, {
                enrollment_status: nextStatus,
            });
            toast.success(response.message);
            await onMutated();
            onRefreshIndex();
        } catch (err) {
            if (err instanceof WorkspaceApiError && err.status === 422 && (nextStatus === "dropped" || nextStatus === "withdrawn")) {
                onRequestOverride({ kind: "status", enrollment, nextStatus });
            } else {
                toast.error(err instanceof WorkspaceApiError ? err.message : "Failed to update status.");
            }
        } finally {
            setBusy(false);
        }
    };

    const handleDropClick = async () => {
        if (!canUpdate) return;
        if (enrollment.is_grade_protected) {
            onRequestOverride({ kind: "drop", enrollment });
            return;
        }

        setBusy(true);
        try {
            const response = await deleteClassEnrollment(classId, enrollment.id);
            toast.success(response.message);
            await onMutated();
            onRefreshIndex();
        } catch (err) {
            if (err instanceof WorkspaceApiError && err.status === 422) {
                onRequestOverride({ kind: "drop", enrollment });
            } else {
                toast.error(err instanceof WorkspaceApiError ? err.message : "Failed to drop student.");
            }
        } finally {
            setBusy(false);
        }
    };

    const handleRestoreClick = async () => {
        if (!canUpdate) return;
        setBusy(true);
        try {
            const response = await postRestoreClassEnrollment(classId, enrollment.id);
            toast.success(response.message);
            await onMutated();
            onRefreshIndex();
        } catch (err) {
            toast.error(err instanceof WorkspaceApiError ? err.message : "Failed to restore student.");
        } finally {
            setBusy(false);
        }
    };

    const handleMoveSingle = async (targetClassId: number) => {
        if (!canUpdate) return;
        setBusy(true);
        try {
            const response = await postBulkMoveStudents(classId, {
                target_class_id: targetClassId,
                student_ids: [enrollment.student_id],
            });
            toast.success(response.message);
            await onMutated();
            onRefreshIndex();
        } catch (err) {
            toast.error(err instanceof WorkspaceApiError ? err.message : "Failed to move student.");
        } finally {
            setBusy(false);
        }
    };

    const handleKeyDownCommit = (event: React.KeyboardEvent<HTMLInputElement>) => {
        if (event.key === "Enter") {
            event.currentTarget.blur();
        }
    };

    return (
        <tr
            className={cn(
                "border-border/60 border-b text-xs transition-colors last:border-b-0 hover:bg-muted/30",
                isDroppedOrWithdrawn && "opacity-70",
                busy && "pointer-events-none opacity-60",
            )}
        >
            <td className="w-8 py-2 pl-3 pr-1 align-middle">
                <Checkbox
                    checked={checked}
                    onCheckedChange={(v) => onCheckedChange(Boolean(v))}
                    disabled={!canUpdate || isDroppedOrWithdrawn}
                    aria-label={`Select ${enrollment.student?.name ?? `student ${enrollment.student_id}`}`}
                />
            </td>
            <td className="min-w-[200px] px-2 py-2 align-middle">
                <div className="flex flex-col gap-0.5">
                    <div className="flex flex-wrap items-center gap-1.5">
                        <span className="text-foreground font-medium">
                            {enrollment.student?.name ?? `Student #${enrollment.student_id}`}
                        </span>
                        {enrollment.timetable_status === "clash" ? (
                            <Tooltip>
                                <TooltipTrigger className="inline-flex items-center gap-1 rounded-full border border-rose-500/30 bg-rose-500/10 px-1.5 py-0.5 text-[10px] font-medium text-rose-700 dark:text-rose-300">
                                    <AlertTriangle className="size-2.5" aria-hidden="true" />
                                    Clash
                                </TooltipTrigger>
                                <TooltipContent className="max-w-xs">
                                    <div className="space-y-1">
                                        <p className="font-semibold">Timetable conflicts:</p>
                                        <ul className="list-disc space-y-0.5 pl-3.5 text-[11px]">
                                            {(enrollment.timetable_conflicts.length > 0
                                                ? enrollment.timetable_conflicts
                                                : ["Overlaps with another enrolled class"]
                                            ).map((c, idx) => (
                                                <li key={idx}>{c}</li>
                                            ))}
                                        </ul>
                                    </div>
                                </TooltipContent>
                            </Tooltip>
                        ) : (
                            <span className="inline-flex items-center gap-0.5 rounded-full border border-emerald-500/25 bg-emerald-500/10 px-1.5 py-0.5 text-[10px] font-medium text-emerald-700 dark:text-emerald-300">
                                <CheckCircle2 className="size-2.5" aria-hidden="true" />
                                Timetable OK
                            </span>
                        )}
                    </div>
                    <div className="text-muted-foreground flex flex-wrap items-center gap-1.5 text-[11px] tabular-nums">
                        <span>{enrollment.student?.student_id ?? `#${enrollment.student_id}`}</span>
                        {enrollment.student?.course ? (
                            <>
                                <span>·</span>
                                <span>
                                    {enrollment.student.course}
                                    {enrollment.student.academic_year ? ` Yr ${enrollment.student.academic_year}` : ""}
                                </span>
                            </>
                        ) : null}
                    </div>
                </div>
            </td>
            <td className="w-36 px-2 py-2 align-middle">
                <Select value={enrollment.enrollment_status} onValueChange={handleStatusChange} disabled={!canUpdate || busy}>
                    <SelectTrigger size="sm" className="h-7 w-32 text-xs">
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="enrolled">Enrolled</SelectItem>
                        <SelectItem value="incomplete">Incomplete</SelectItem>
                        <SelectItem value="audit">Audit</SelectItem>
                        <SelectItem value="dropped">Dropped</SelectItem>
                        <SelectItem value="withdrawn">Withdrawn</SelectItem>
                    </SelectContent>
                </Select>
            </td>
            <td className="px-2 py-2 align-middle">
                <div className="flex items-center gap-1.5">
                    <Input
                        type="number"
                        inputMode="decimal"
                        min={0}
                        max={100}
                        step="0.01"
                        placeholder="P"
                        aria-label="Prelim grade"
                        value={prelim}
                        onChange={(e) => setPrelim(e.target.value)}
                        onBlur={() => void commitGrade("prelim_grade", prelim, enrollment.prelim_grade)}
                        onKeyDown={handleKeyDownCommit}
                        disabled={!canUpdate || gradesLocked || busy}
                        className="h-7 w-14 [appearance:textfield] px-1.5 text-center text-xs tabular-nums [&::-webkit-inner-spin-button]:appearance-none [&::-webkit-outer-spin-button]:appearance-none"
                    />
                    <Input
                        type="number"
                        inputMode="decimal"
                        min={0}
                        max={100}
                        step="0.01"
                        placeholder="M"
                        aria-label="Midterm grade"
                        value={midterm}
                        onChange={(e) => setMidterm(e.target.value)}
                        onBlur={() => void commitGrade("midterm_grade", midterm, enrollment.midterm_grade)}
                        onKeyDown={handleKeyDownCommit}
                        disabled={!canUpdate || gradesLocked || busy}
                        className="h-7 w-14 [appearance:textfield] px-1.5 text-center text-xs tabular-nums [&::-webkit-inner-spin-button]:appearance-none [&::-webkit-outer-spin-button]:appearance-none"
                    />
                    <Input
                        type="number"
                        inputMode="decimal"
                        min={0}
                        max={100}
                        step="0.01"
                        placeholder="F"
                        aria-label="Final grade"
                        value={finals}
                        onChange={(e) => setFinals(e.target.value)}
                        onBlur={() => void commitGrade("finals_grade", finals, enrollment.finals_grade)}
                        onKeyDown={handleKeyDownCommit}
                        disabled={!canUpdate || gradesLocked || busy}
                        className="h-7 w-14 [appearance:textfield] px-1.5 text-center text-xs tabular-nums [&::-webkit-inner-spin-button]:appearance-none [&::-webkit-outer-spin-button]:appearance-none"
                    />
                    {enrollment.total_average !== null ? (
                        <span className="bg-muted text-foreground inline-flex h-6 min-w-10 items-center justify-center rounded-md px-1.5 text-[11px] font-semibold tabular-nums">
                            {Number(enrollment.total_average).toFixed(1)}
                        </span>
                    ) : (
                        <span className="text-muted-foreground inline-flex h-6 min-w-10 items-center justify-center text-[11px]">
                            —
                        </span>
                    )}
                    {gradesLocked ? (
                        <Badge
                            variant="outline"
                            className="border-amber-500/30 bg-amber-500/10 text-[10px] text-amber-700 dark:text-amber-300"
                        >
                            <Lock className="size-2.5" aria-hidden="true" />
                            {enrollment.is_grades_finalized ? "Finalized" : "Verified"}
                        </Badge>
                    ) : null}
                </div>
            </td>
            <td className="min-w-[150px] px-2 py-2 align-middle">
                <Input
                    type="text"
                    placeholder="Add remark..."
                    aria-label="Enrollment remarks"
                    value={remarks}
                    onChange={(e) => setRemarks(e.target.value)}
                    onBlur={() => void commitRemarks()}
                    onKeyDown={handleKeyDownCommit}
                    disabled={!canUpdate || busy}
                    className="h-7 text-xs"
                />
            </td>
            <td className="py-2 pl-2 pr-3 text-right align-middle">
                <div className="flex items-center justify-end gap-1">
                    {canUpdate && !isDroppedOrWithdrawn && siblingSections.length > 0 ? (
                        <DropdownMenu>
                            <DropdownMenuTrigger asChild>
                                <Button type="button" variant="outline" size="xs" disabled={busy}>
                                    <ArrowRightLeft className="size-3" aria-hidden="true" />
                                    Move
                                </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="end" className="w-56">
                                <DropdownMenuLabel>Move to sibling section</DropdownMenuLabel>
                                {siblingSections.map((sec) => (
                                    <DropdownMenuItem
                                        key={sec.id}
                                        onClick={() => void handleMoveSingle(sec.id)}
                                        className="flex items-center justify-between gap-2 text-xs"
                                    >
                                        <span className="font-medium">Sec {sec.section}</span>
                                        <span className="text-muted-foreground tabular-nums">
                                            {sec.students_count}/{sec.maximum_slots || "—"}
                                        </span>
                                    </DropdownMenuItem>
                                ))}
                            </DropdownMenuContent>
                        </DropdownMenu>
                    ) : null}

                    {canUpdate ? (
                        isDroppedOrWithdrawn ? (
                            <Button
                                type="button"
                                variant="outline"
                                size="xs"
                                onClick={() => void handleRestoreClick()}
                                disabled={busy}
                                className="border-emerald-500/30 text-emerald-700 hover:bg-emerald-500/10 dark:text-emerald-300"
                            >
                                <RotateCcw className="size-3" aria-hidden="true" />
                                Restore
                            </Button>
                        ) : enrollment.is_grade_protected ? (
                            <Tooltip>
                                <TooltipTrigger
                                    onClick={() => void handleDropClick()}
                                    disabled={busy}
                                    className="inline-flex h-6 items-center gap-1 rounded-md border border-amber-500/30 bg-amber-500/10 px-2 text-xs font-medium text-amber-800 transition-colors hover:bg-amber-500/20 disabled:opacity-50 dark:text-amber-300"
                                >
                                    <Lock className="size-3" aria-hidden="true" />
                                    Drop
                                </TooltipTrigger>
                                <TooltipContent>
                                    Protected: grades recorded/verified — requires override reason
                                </TooltipContent>
                            </Tooltip>
                        ) : (
                            <Button
                                type="button"
                                variant="ghost"
                                size="xs"
                                onClick={() => void handleDropClick()}
                                disabled={busy}
                                className="text-destructive hover:bg-destructive/10 hover:text-destructive"
                            >
                                <Trash2 className="size-3" aria-hidden="true" />
                                Drop
                            </Button>
                        )
                    ) : null}
                </div>
            </td>
        </tr>
    );
}

interface ExpandedSubRowProps {
    row: ClassRow;
    workspace: ClassWorkspacePayload | undefined;
    loading: boolean;
    canUpdate: boolean;
    onOpenStudio: (classId: number, mode: "enroll" | "schedule") => void;
    onOpenSectionStudio: (classId: number) => void;
    onRefreshWorkspace: (classId: number) => Promise<void>;
    onRefreshIndex: () => void;
}

function ExpandedClassSubRow({
    row,
    workspace,
    loading,
    canUpdate,
    onOpenStudio,
    onOpenSectionStudio,
    onRefreshWorkspace,
    onRefreshIndex,
}: ExpandedSubRowProps) {
    const [activeTab, setActiveTab] = React.useState<"roster" | "meetings">("roster");
    const [statusFilter, setStatusFilter] = React.useState<RosterFilterTab>("all");
    const [rosterSearch, setRosterSearch] = React.useState("");
    const [selectedStudentIds, setSelectedStudentIds] = React.useState<number[]>([]);
    const [bulkTargetSectionId, setBulkTargetSectionId] = React.useState<string>("");
    const [bulkMoving, setBulkMoving] = React.useState(false);

    // Grade-protected override dialog state
    const [pendingOverride, setPendingOverride] = React.useState<PendingGradeOverride | null>(null);
    const [overrideReason, setOverrideReason] = React.useState("");
    const [submittingOverride, setSubmittingOverride] = React.useState(false);

    const enrollments = workspace?.enrollments ?? [];
    const schedules = workspace?.schedule_summary?.schedules ?? row.schedules ?? [];
    const scheduledHours = workspace?.schedule_summary?.schedule_health?.scheduled_hours ?? row.schedule_health?.scheduled_hours ?? 0;
    const siblingSections = workspace?.sibling_sections ?? [];

    const filteredEnrollments = React.useMemo(() => {
        const q = rosterSearch.trim().toLowerCase();
        return enrollments.filter((e) => {
            if (statusFilter === "enrolled" && e.enrollment_status !== "enrolled") return false;
            if (statusFilter === "incomplete" && e.enrollment_status !== "incomplete") return false;
            if (statusFilter === "audit" && e.enrollment_status !== "audit") return false;
            if (
                statusFilter === "dropped" &&
                e.enrollment_status !== "dropped" &&
                e.enrollment_status !== "withdrawn" &&
                !e.deleted_at
            ) {
                return false;
            }
            if (!q) return true;
            const hay = [
                e.student?.name,
                e.student?.student_id ? String(e.student.student_id) : "",
                e.student?.course,
                e.student?.email,
                e.remarks,
            ]
                .filter(Boolean)
                .join(" ")
                .toLowerCase();
            return hay.includes(q);
        });
    }, [enrollments, statusFilter, rosterSearch]);

    const selectableFiltered = React.useMemo(
        () =>
            filteredEnrollments.filter(
                (e) => e.enrollment_status !== "dropped" && e.enrollment_status !== "withdrawn" && !e.deleted_at,
            ),
        [filteredEnrollments],
    );

    const allFilteredSelected =
        selectableFiltered.length > 0 && selectableFiltered.every((e) => selectedStudentIds.includes(e.student_id));
    const someFilteredSelected =
        !allFilteredSelected && selectableFiltered.some((e) => selectedStudentIds.includes(e.student_id));

    const toggleSelectAllFiltered = (checked: boolean) => {
        if (checked) {
            setSelectedStudentIds(Array.from(new Set([...selectedStudentIds, ...selectableFiltered.map((e) => e.student_id)])));
        } else {
            const removeSet = new Set(selectableFiltered.map((e) => e.student_id));
            setSelectedStudentIds(selectedStudentIds.filter((id) => !removeSet.has(id)));
        }
    };

    const handleBulkMove = async () => {
        if (!bulkTargetSectionId || selectedStudentIds.length === 0) return;
        setBulkMoving(true);
        try {
            const response = await postBulkMoveStudents(row.id, {
                target_class_id: Number(bulkTargetSectionId),
                student_ids: selectedStudentIds,
            });
            toast.success(response.message);
            setSelectedStudentIds([]);
            setBulkTargetSectionId("");
            await onRefreshWorkspace(row.id);
            onRefreshIndex();
        } catch (err) {
            toast.error(err instanceof WorkspaceApiError ? err.message : "Failed to move selected students.");
        } finally {
            setBulkMoving(false);
        }
    };

    const handleConfirmOverride = async () => {
        if (!pendingOverride) return;
        const trimmedReason = overrideReason.trim();
        if (trimmedReason.length < 3) {
            toast.error("Please provide an override reason (at least 3 characters).");
            return;
        }

        setSubmittingOverride(true);
        try {
            if (pendingOverride.kind === "drop") {
                const response = await deleteClassEnrollment(row.id, pendingOverride.enrollment.id, {
                    reason: trimmedReason,
                    force_grade_override: true,
                });
                toast.success(response.message);
            } else {
                const response = await patchClassEnrollment(row.id, pendingOverride.enrollment.id, {
                    enrollment_status: pendingOverride.nextStatus,
                    status_reason: trimmedReason,
                    override_reason: trimmedReason,
                    force_grade_override: true,
                });
                toast.success(response.message);
            }
            setPendingOverride(null);
            setOverrideReason("");
            await onRefreshWorkspace(row.id);
            onRefreshIndex();
        } catch (err) {
            toast.error(err instanceof WorkspaceApiError ? err.message : "Override action failed.");
        } finally {
            setSubmittingOverride(false);
        }
    };

    if (loading && !workspace) {
        return (
            <tr className="bg-muted/25 border-border border-b">
                <td colSpan={5} className="p-4">
                    <div className="space-y-3">
                        <div className="flex items-center justify-between gap-4">
                            <Skeleton className="h-8 w-72" />
                            <Skeleton className="h-8 w-48" />
                        </div>
                        <Skeleton className="h-28 w-full rounded-lg" />
                    </div>
                </td>
            </tr>
        );
    }

    const filterCounts: Record<RosterFilterTab, number> = {
        all: enrollments.length,
        enrolled: enrollments.filter((e) => e.enrollment_status === "enrolled").length,
        incomplete: enrollments.filter((e) => e.enrollment_status === "incomplete").length,
        audit: enrollments.filter((e) => e.enrollment_status === "audit").length,
        dropped: enrollments.filter(
            (e) => e.enrollment_status === "dropped" || e.enrollment_status === "withdrawn" || Boolean(e.deleted_at),
        ).length,
    };

    return (
        <tr className="bg-muted/20 border-border border-b">
            <td colSpan={5} className="p-0">
                <div className="border-primary/40 border-l-2 px-4 py-3">
                    {/* Top Segmented Controls & Quick Actions */}
                    <div className="flex flex-wrap items-center justify-between gap-2 pb-3">
                        <div className="bg-muted inline-flex items-center rounded-lg p-0.5 text-xs">
                            <button
                                type="button"
                                onClick={() => setActiveTab("roster")}
                                className={cn(
                                    "inline-flex items-center gap-1.5 rounded-md px-2.5 py-1 font-medium transition-colors",
                                    activeTab === "roster"
                                        ? "bg-background text-foreground shadow-xs"
                                        : "text-muted-foreground hover:text-foreground",
                                )}
                            >
                                <Users className="size-3.5" aria-hidden="true" />
                                Roster &amp; Grades
                                <span className="bg-muted text-muted-foreground rounded-full px-1.5 py-0.2 text-[10px] tabular-nums">
                                    {enrollments.length}
                                </span>
                            </button>
                            <button
                                type="button"
                                onClick={() => setActiveTab("meetings")}
                                className={cn(
                                    "inline-flex items-center gap-1.5 rounded-md px-2.5 py-1 font-medium transition-colors",
                                    activeTab === "meetings"
                                        ? "bg-background text-foreground shadow-xs"
                                        : "text-muted-foreground hover:text-foreground",
                                )}
                            >
                                <CalendarClock className="size-3.5" aria-hidden="true" />
                                Meetings &amp; Rooms
                                <span className="bg-muted text-muted-foreground rounded-full px-1.5 py-0.2 text-[10px] tabular-nums">
                                    {schedules.length} {schedules.length === 1 ? "block" : "blocks"} · {formatHours(scheduledHours)}h
                                </span>
                            </button>
                        </div>

                        <div className="flex flex-wrap items-center gap-1.5">
                            {canUpdate ? (
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="xs"
                                    onClick={() => onOpenStudio(row.id, "enroll")}
                                >
                                    <UserPlus className="size-3" aria-hidden="true" />
                                    Quick Add
                                </Button>
                            ) : null}
                            <Button
                                type="button"
                                variant="outline"
                                size="xs"
                                onClick={() => onOpenSectionStudio(row.id)}
                            >
                                <Layers className="size-3" aria-hidden="true" />
                                Section Studio
                            </Button>
                            <a
                                href={exportStudentList.url(row.id)}
                                className="border-border bg-background text-foreground hover:bg-muted inline-flex h-6 items-center gap-1 rounded-md border px-2 text-xs font-medium transition-colors"
                            >
                                <Download className="size-3" aria-hidden="true" />
                                Export
                            </a>
                        </div>
                    </div>

                    {activeTab === "roster" ? (
                        <div className="space-y-2.5">
                            {/* Filter Pills + Inline Search */}
                            <div className="flex flex-wrap items-center justify-between gap-2">
                                <div className="flex flex-wrap items-center gap-1">
                                    {(
                                        [
                                            { id: "all", label: "All" },
                                            { id: "enrolled", label: "Enrolled" },
                                            { id: "incomplete", label: "Incomplete" },
                                            { id: "audit", label: "Audit" },
                                            { id: "dropped", label: "Dropped" },
                                        ] as const
                                    ).map((tab) => (
                                        <button
                                            key={tab.id}
                                            type="button"
                                            onClick={() => setStatusFilter(tab.id)}
                                            className={cn(
                                                "inline-flex items-center gap-1 rounded-full border px-2.5 py-0.5 text-[11px] font-medium transition-colors",
                                                statusFilter === tab.id
                                                    ? "border-primary bg-primary text-primary-foreground"
                                                    : "border-border bg-background text-muted-foreground hover:text-foreground",
                                            )}
                                        >
                                            {tab.label}
                                            <span className="opacity-75 tabular-nums">{filterCounts[tab.id]}</span>
                                        </button>
                                    ))}
                                </div>

                                <div className="relative w-full sm:w-56">
                                    <Search
                                        className="text-muted-foreground pointer-events-none absolute left-2.5 top-1/2 size-3.5 -translate-y-1/2"
                                        aria-hidden="true"
                                    />
                                    <Input
                                        type="search"
                                        value={rosterSearch}
                                        onChange={(e) => setRosterSearch(e.target.value)}
                                        placeholder="Filter roster..."
                                        className="bg-background h-7 pl-8 text-xs"
                                    />
                                </div>
                            </div>

                            {/* Contextual Student Bulk Action Bar */}
                            {canUpdate && selectedStudentIds.length > 0 ? (
                                <div className="border-primary/30 bg-primary/5 flex flex-wrap items-center justify-between gap-2 rounded-lg border px-3 py-2 text-xs">
                                    <div className="flex items-center gap-2">
                                        <Badge variant="default" className="tabular-nums">
                                            {selectedStudentIds.length} selected
                                        </Badge>
                                        <span className="text-muted-foreground">Bulk student action:</span>
                                    </div>
                                    <div className="flex flex-wrap items-center gap-2">
                                        {siblingSections.length > 0 ? (
                                            <>
                                                <Select value={bulkTargetSectionId} onValueChange={setBulkTargetSectionId}>
                                                    <SelectTrigger size="sm" className="bg-background h-7 w-48 text-xs">
                                                        <SelectValue placeholder="Move to Section..." />
                                                    </SelectTrigger>
                                                    <SelectContent>
                                                        {siblingSections.map((sec) => (
                                                            <SelectItem key={sec.id} value={String(sec.id)}>
                                                                Sec {sec.section} ({sec.students_count}/{sec.maximum_slots || "—"})
                                                            </SelectItem>
                                                        ))}
                                                    </SelectContent>
                                                </Select>
                                                <Button
                                                    type="button"
                                                    size="xs"
                                                    disabled={!bulkTargetSectionId || bulkMoving}
                                                    onClick={() => void handleBulkMove()}
                                                >
                                                    {bulkMoving ? (
                                                        <Loader2 className="size-3 animate-spin" aria-hidden="true" />
                                                    ) : (
                                                        <ArrowRightLeft className="size-3" aria-hidden="true" />
                                                    )}
                                                    Move Selected
                                                </Button>
                                            </>
                                        ) : (
                                            <span className="text-muted-foreground text-[11px]">
                                                No sibling sections available to move into.
                                            </span>
                                        )}
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="xs"
                                            onClick={() => setSelectedStudentIds([])}
                                        >
                                            Clear
                                        </Button>
                                    </div>
                                </div>
                            ) : null}

                            {/* Inline Roster Table */}
                            <div className="border-border bg-card overflow-x-auto rounded-lg border">
                                {filteredEnrollments.length === 0 ? (
                                    <div className="text-muted-foreground flex flex-col items-center justify-center gap-2 py-8 text-center text-xs">
                                        <Users className="size-5 opacity-40" aria-hidden="true" />
                                        <p>
                                            {enrollments.length === 0
                                                ? "No students enrolled in this class yet."
                                                : "No roster entries match the current filter."}
                                        </p>
                                        {canUpdate && enrollments.length === 0 ? (
                                            <Button
                                                type="button"
                                                variant="outline"
                                                size="xs"
                                                onClick={() => onOpenStudio(row.id, "enroll")}
                                            >
                                                <UserPlus className="size-3" aria-hidden="true" />
                                                Enroll students in Studio
                                            </Button>
                                        ) : null}
                                    </div>
                                ) : (
                                    <table className="w-full border-collapse text-left">
                                        <thead>
                                            <tr className="border-border bg-muted/40 text-muted-foreground border-b text-[10px] font-semibold tracking-wider uppercase">
                                                <th className="w-8 py-2 pl-3 pr-1">
                                                    <Checkbox
                                                        checked={
                                                            allFilteredSelected
                                                                ? true
                                                                : someFilteredSelected
                                                                  ? "indeterminate"
                                                                  : false
                                                        }
                                                        onCheckedChange={(v) => toggleSelectAllFiltered(Boolean(v))}
                                                        disabled={!canUpdate || selectableFiltered.length === 0}
                                                        aria-label="Select all students in filtered roster"
                                                    />
                                                </th>
                                                <th className="px-2 py-2">Student &amp; Timetable</th>
                                                <th className="px-2 py-2">Status</th>
                                                <th className="px-2 py-2">Prelim · Midterm · Final · Avg</th>
                                                <th className="px-2 py-2">Inline Remarks</th>
                                                <th className="py-2 pl-2 pr-3 text-right">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {filteredEnrollments.map((enrollment) => (
                                                <InlineEnrollmentRow
                                                    key={enrollment.id}
                                                    classId={row.id}
                                                    enrollment={enrollment}
                                                    siblingSections={siblingSections}
                                                    checked={selectedStudentIds.includes(enrollment.student_id)}
                                                    onCheckedChange={(checked) => {
                                                        setSelectedStudentIds((prev) =>
                                                            checked
                                                                ? Array.from(new Set([...prev, enrollment.student_id]))
                                                                : prev.filter((id) => id !== enrollment.student_id),
                                                        );
                                                    }}
                                                    canUpdate={canUpdate}
                                                    onRequestOverride={(ov) => {
                                                        setOverrideReason("");
                                                        setPendingOverride(ov);
                                                    }}
                                                    onMutated={() => onRefreshWorkspace(row.id)}
                                                    onRefreshIndex={onRefreshIndex}
                                                />
                                            ))}
                                        </tbody>
                                    </table>
                                )}
                            </div>
                        </div>
                    ) : (
                        /* Tab 2: Meetings & Rooms */
                        <div className="space-y-2.5">
                            <div className="border-border bg-card overflow-hidden rounded-lg border">
                                {schedules.length === 0 ? (
                                    <div className="text-muted-foreground flex flex-col items-center justify-center gap-2 py-8 text-center text-xs">
                                        <CalendarClock className="size-5 opacity-40" aria-hidden="true" />
                                        <p>No meeting blocks scheduled for this section.</p>
                                        {canUpdate ? (
                                            <Button
                                                type="button"
                                                variant="outline"
                                                size="xs"
                                                onClick={() => onOpenStudio(row.id, "schedule")}
                                            >
                                                <CalendarClock className="size-3" aria-hidden="true" />
                                                Open Schedule Studio
                                            </Button>
                                        ) : null}
                                    </div>
                                ) : (
                                    <div className="divide-border divide-y">
                                        {schedules.map((block) => {
                                            const isLab = block.component_type === "lab";
                                            const isOnline = isOnlineDeliveryMode(block.delivery_mode);
                                            const overBy =
                                                !isOnline && block.room_capacity !== null && row.students_count > block.room_capacity
                                                    ? row.students_count - block.room_capacity
                                                    : 0;
                                            return (
                                                <div
                                                    key={block.id}
                                                    className="flex flex-wrap items-center justify-between gap-3 px-3.5 py-2.5 text-xs"
                                                >
                                                    <div className="flex flex-wrap items-center gap-2.5">
                                                        <span className="text-foreground w-24 font-semibold">
                                                            {block.day_of_week}
                                                        </span>
                                                        <span className="text-muted-foreground inline-flex items-center gap-1 tabular-nums">
                                                            <Clock className="size-3.5" aria-hidden="true" />
                                                            {(block.start_time ?? "TBA").slice(0, 5)}–{(block.end_time ?? "TBA").slice(0, 5)}
                                                        </span>
                                                        <span
                                                            className={cn(
                                                                "inline-flex items-center rounded-md px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wide",
                                                                isLab
                                                                    ? "bg-amber-500/15 text-amber-800 dark:text-amber-300"
                                                                    : "bg-primary/10 text-primary",
                                                            )}
                                                        >
                                                            {isLab ? "LAB" : "LECTURE"}
                                                        </span>
                                                        <span className="text-foreground inline-flex items-center gap-1">
                                                            {isOnline ? (
                                                                <Wifi className="text-muted-foreground size-3.5" aria-hidden="true" />
                                                            ) : (
                                                                <MapPin className="text-muted-foreground size-3.5" aria-hidden="true" />
                                                            )}
                                                            {isOnline
                                                                ? deliveryModeLabel(block.delivery_mode)
                                                                : block.room_name || "Unassigned Room"}
                                                            {!isOnline && block.room_capacity !== null ? (
                                                                <span className="text-muted-foreground tabular-nums">
                                                                    ({block.room_capacity} cap)
                                                                </span>
                                                            ) : null}
                                                        </span>
                                                        {overBy > 0 || block.is_overcrowded ? (
                                                            <Badge
                                                                variant="destructive"
                                                                className="h-5 text-[10px] tabular-nums"
                                                            >
                                                                <AlertTriangle className="size-2.5" aria-hidden="true" />
                                                                Overcrowded{overBy > 0 ? ` +${overBy}` : ""}
                                                            </Badge>
                                                        ) : null}
                                                    </div>

                                                    <div className="flex items-center gap-2">
                                                        {block.has_conflict ? (
                                                            <Badge variant="destructive" className="h-5 text-[10px]">
                                                                <AlertTriangle className="size-2.5" aria-hidden="true" />
                                                                Conflict
                                                            </Badge>
                                                        ) : null}
                                                        <Badge
                                                            variant="outline"
                                                            className={cn(
                                                                "h-5 text-[10px] capitalize",
                                                                block.status === "published"
                                                                    ? "border-emerald-500/30 bg-emerald-500/10 text-emerald-700 dark:text-emerald-300"
                                                                    : "border-amber-500/30 bg-amber-500/10 text-amber-700 dark:text-amber-300",
                                                            )}
                                                        >
                                                            {block.status || "published"}
                                                        </Badge>
                                                    </div>
                                                </div>
                                            );
                                        })}
                                    </div>
                                )}
                            </div>
                            <div className="flex justify-end">
                                <Button
                                    type="button"
                                    size="xs"
                                    variant="outline"
                                    onClick={() => onOpenStudio(row.id, "schedule")}
                                >
                                    <CalendarClock className="size-3" aria-hidden="true" />
                                    Edit in Schedule Studio
                                </Button>
                            </div>
                        </div>
                    )}
                </div>

                {/* Grade-Protected Override Dialog */}
                <Dialog open={Boolean(pendingOverride)} onOpenChange={(open) => !open && setPendingOverride(null)}>
                    <DialogContent className="sm:max-w-md">
                        <DialogHeader>
                            <DialogTitle className="flex items-center gap-2">
                                <Lock className="size-4 text-amber-600 dark:text-amber-400" aria-hidden="true" />
                                Grade-Protected Enrollment Override
                            </DialogTitle>
                            <DialogDescription>
                                {pendingOverride?.enrollment.student?.name ?? "This student"} has recorded or verified
                                grades in {row.record_title}. Provide an audit reason to authorize this{" "}
                                {pendingOverride?.kind === "drop" ? "drop" : `status change to "${pendingOverride?.nextStatus}"`}.
                            </DialogDescription>
                        </DialogHeader>
                        <div className="space-y-2 py-2">
                            <Label htmlFor={`override-reason-${row.id}`} className="text-xs font-medium">
                                Audit Override Reason <span className="text-destructive">*</span>
                            </Label>
                            <Textarea
                                id={`override-reason-${row.id}`}
                                value={overrideReason}
                                onChange={(e) => setOverrideReason(e.target.value)}
                                placeholder="e.g., Registrar-approved late withdrawal per memo #2026-14..."
                                rows={3}
                            />
                        </div>
                        <DialogFooter>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setPendingOverride(null)}
                                disabled={submittingOverride}
                            >
                                Cancel
                            </Button>
                            <Button
                                type="button"
                                variant="destructive"
                                onClick={() => void handleConfirmOverride()}
                                disabled={submittingOverride || overrideReason.trim().length < 3}
                            >
                                {submittingOverride ? <Loader2 className="size-3.5 animate-spin" aria-hidden="true" /> : null}
                                Confirm Override
                            </Button>
                        </DialogFooter>
                    </DialogContent>
                </Dialog>
            </td>
        </tr>
    );
}

export function ExpandableClassLedger({
    classes,
    rowSelection,
    onRowSelectionChange,
    expandedClassIds,
    onToggleExpand,
    activeStudioClassId,
    studioMode,
    onOpenStudio,
    onOpenSectionStudio,
    workspaceCache,
    loadingWorkspaceIds,
    onRefreshWorkspace,
    onRefreshIndex,
    capabilities,
    density,
    rowActions,
}: ExpandableClassLedgerProps) {
    const allSelected = classes.length > 0 && classes.every((row) => Boolean(rowSelection[String(row.id)]));
    const someSelected = !allSelected && classes.some((row) => Boolean(rowSelection[String(row.id)]));

    const toggleAll = (checked: boolean) => {
        const next: Record<string, boolean> = { ...rowSelection };
        for (const row of classes) {
            if (checked) {
                next[String(row.id)] = true;
            } else {
                delete next[String(row.id)];
            }
        }
        onRowSelectionChange(next);
    };

    const toggleOne = (classId: number, checked: boolean) => {
        const next: Record<string, boolean> = { ...rowSelection };
        if (checked) {
            next[String(classId)] = true;
        } else {
            delete next[String(classId)];
        }
        onRowSelectionChange(next);
    };

    const cellPadding = density === "compact" ? "py-2" : "py-3.5";

    if (classes.length === 0) {
        return (
            <div className="border-border bg-card text-muted-foreground flex flex-col items-center justify-center gap-2 rounded-xl border py-14 text-center text-sm">
                <Users className="size-7 opacity-40" aria-hidden="true" />
                <p className="text-foreground font-medium">No classes found</p>
                <p className="text-xs">Adjust your search or filters to view matching class sections.</p>
            </div>
        );
    }

    return (
        <TooltipProvider>
            <div className="border-border bg-card overflow-x-auto rounded-xl border shadow-xs">
                <table className="w-full border-collapse text-left">
                    <thead>
                        <tr className="border-border bg-muted/40 text-muted-foreground border-b text-[11px] font-semibold tracking-wider uppercase">
                            <th className="w-16 py-2.5 pl-3 pr-1">
                                <div className="flex items-center gap-1.5">
                                    <Checkbox
                                        checked={allSelected ? true : someSelected ? "indeterminate" : false}
                                        onCheckedChange={(v) => toggleAll(Boolean(v))}
                                        aria-label="Select all classes on this page"
                                    />
                                </div>
                            </th>
                            <th className="px-3 py-2.5">Class &amp; Section</th>
                            <th className="px-3 py-2.5">Faculty, Schedule &amp; Room Fit</th>
                            <th className="w-44 px-3 py-2.5">Seats / WL</th>
                            <th className="w-48 py-2.5 pl-3 pr-3 text-right">Quick Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        {classes.map((row) => {
                            const isSelected = Boolean(rowSelection[String(row.id)]);
                            const isExpanded = expandedClassIds.includes(row.id);
                            const isStudioActive = activeStudioClassId === row.id;
                            const isArchived = Boolean(row.deleted_at);
                            const isUnassignedFaculty = !row.faculty || row.faculty === "TBA";
                            const health = row.schedule_health;
                            const groupedPills = groupSchedulesForLedger(row.schedules);
                            const waitlistCount = row.waitlist_count ?? 0;

                            const maxSlots = row.maximum_slots || 0;
                            const enrolled = row.students_count;
                            const isOverCap = maxSlots > 0 && enrolled > maxSlots;
                            const isFull = maxSlots > 0 && enrolled === maxSlots;
                            const isNearFull = maxSlots > 0 && !isOverCap && !isFull && enrolled / maxSlots >= 0.85;
                            const fillPct = maxSlots > 0 ? Math.min(100, Math.round((enrolled / maxSlots) * 100)) : 0;

                            return (
                                <React.Fragment key={row.id}>
                                    <tr
                                        className={cn(
                                            "border-border/70 border-b transition-colors hover:bg-muted/30",
                                            isStudioActive && "bg-amber-500/[0.06] dark:bg-amber-400/[0.08]",
                                            isSelected && !isStudioActive && "bg-primary/[0.04]",
                                            isArchived && "opacity-75",
                                        )}
                                    >
                                        {/* 1. Checkbox + Expand Chevron */}
                                        <td className={cn("pl-3 pr-1 align-top", cellPadding)}>
                                            <div className="flex items-center gap-1 pt-0.5">
                                                <Checkbox
                                                    checked={isSelected}
                                                    onCheckedChange={(v) => toggleOne(row.id, Boolean(v))}
                                                    aria-label={`Select ${row.record_title}`}
                                                />
                                                <button
                                                    type="button"
                                                    onClick={() => onToggleExpand(row.id)}
                                                    aria-expanded={isExpanded}
                                                    aria-label={`${isExpanded ? "Collapse" : "Expand"} ${row.record_title}`}
                                                    className="text-muted-foreground hover:bg-muted hover:text-foreground inline-flex size-6 items-center justify-center rounded-md transition-colors"
                                                >
                                                    {isExpanded ? (
                                                        <ChevronDown className="size-4" aria-hidden="true" />
                                                    ) : (
                                                        <ChevronRight className="size-4" aria-hidden="true" />
                                                    )}
                                                </button>
                                            </div>
                                        </td>

                                        {/* 2. Class & Section */}
                                        <td className={cn("min-w-[220px] max-w-[300px] px-3 align-top", cellPadding)}>
                                            <div className="space-y-1">
                                                <div className="flex flex-wrap items-center gap-1.5">
                                                    {isStudioActive ? (
                                                        <span
                                                            className="inline-block size-2 shrink-0 rounded-full bg-amber-500 ring-2 ring-amber-500/25"
                                                            title="Active in Dual-Mode Studio"
                                                        />
                                                    ) : null}
                                                    <Link
                                                        href={show.url(row.id)}
                                                        className="text-foreground hover:text-primary text-sm font-semibold tracking-tight transition-colors"
                                                    >
                                                        {row.subject_code}
                                                    </Link>
                                                    <Badge variant="secondary" className="h-5 px-1.5 text-[11px] font-semibold">
                                                        Sec {row.section}
                                                    </Badge>
                                                    <Badge variant="outline" className="h-5 px-1.5 text-[10px] uppercase">
                                                        {row.classification === "shs" ? "SHS" : "College"}
                                                    </Badge>
                                                    {isArchived ? (
                                                        <Badge variant="outline" className="h-5 text-[10px]">
                                                            Archived
                                                        </Badge>
                                                    ) : null}
                                                </div>
                                                <p className="text-foreground/85 line-clamp-1 text-xs font-medium">
                                                    {row.subject_title}
                                                </p>
                                                <p className="text-muted-foreground text-[11px]">
                                                    {classProgramLabel(row)} · {row.school_year} {semesterLabel(row.semester)}
                                                </p>
                                            </div>
                                        </td>

                                        {/* 3. Faculty, Schedule & Room Fit */}
                                        <td className={cn("min-w-[290px] px-3 align-top", cellPadding)}>
                                            <div className="space-y-1.5">
                                                {/* Faculty + Schedule Health + Conflict */}
                                                <div className="flex flex-wrap items-center gap-1.5">
                                                    {isUnassignedFaculty ? (
                                                        <span className="inline-flex items-center gap-1 rounded-full border border-amber-500/30 bg-amber-500/10 px-2 py-0.5 text-[11px] font-medium text-amber-800 dark:text-amber-300">
                                                            Unassigned Faculty
                                                        </span>
                                                    ) : (
                                                        <span className="text-foreground text-xs font-medium">{row.faculty}</span>
                                                    )}

                                                    {health ? (
                                                        <span
                                                            className={cn(
                                                                "inline-flex items-center gap-1 rounded-full border px-2 py-0.5 text-[10px] font-medium tabular-nums",
                                                                health.status === "published"
                                                                    ? "border-emerald-500/25 bg-emerald-500/10 text-emerald-700 dark:text-emerald-300"
                                                                    : health.status === "draft"
                                                                      ? "border-amber-500/30 bg-amber-500/10 text-amber-800 dark:text-amber-300"
                                                                      : "border-border bg-muted text-muted-foreground",
                                                            )}
                                                        >
                                                            {formatHours(health.scheduled_hours)}/{formatHours(health.required_hours)}h ·{" "}
                                                            {health.status === "published"
                                                                ? "Published"
                                                                : health.status === "draft"
                                                                  ? "Draft"
                                                                  : "Unscheduled"}
                                                        </span>
                                                    ) : null}

                                                    {health?.has_conflict ? (
                                                        <Tooltip>
                                                            <TooltipTrigger className="inline-flex items-center gap-1 rounded-full border border-rose-500/30 bg-rose-500/10 px-2 py-0.5 text-[10px] font-semibold text-rose-700 dark:text-rose-300">
                                                                <AlertTriangle className="size-2.5" aria-hidden="true" />
                                                                {health.has_room_conflict
                                                                    ? "Room Clash"
                                                                    : health.has_faculty_conflict
                                                                      ? "Faculty Clash"
                                                                      : "Conflict"}
                                                            </TooltipTrigger>
                                                            <TooltipContent>
                                                                {health.conflict_summary || "Scheduling conflict detected."}
                                                            </TooltipContent>
                                                        </Tooltip>
                                                    ) : null}
                                                </div>

                                                {/* Schedule Pills + Room Capacity Fit */}
                                                {groupedPills.length > 0 ? (
                                                    <div className="flex flex-wrap items-center gap-1.5">
                                                        {groupedPills.map((pill) => {
                                                            const overBy =
                                                                !pill.isOnline &&
                                                                pill.roomCapacity !== null &&
                                                                enrolled > pill.roomCapacity
                                                                    ? enrolled - pill.roomCapacity
                                                                    : 0;
                                                            return (
                                                                <span
                                                                    key={pill.key}
                                                                    className={cn(
                                                                        "inline-flex items-center gap-1 rounded-md border px-1.5 py-0.5 text-[11px] tabular-nums",
                                                                        pill.component === "lab"
                                                                            ? "border-amber-500/30 bg-amber-500/10 text-amber-900 dark:text-amber-200"
                                                                            : "border-border bg-muted/60 text-foreground",
                                                                        pill.hasConflict && "border-rose-500/50 ring-1 ring-rose-500/20",
                                                                    )}
                                                                >
                                                                    <span className="font-semibold">{pill.daysLabel}</span>
                                                                    <span>{pill.timeLabel}</span>
                                                                    <span className="text-[10px] font-semibold uppercase opacity-75">
                                                                        {pill.component === "lab" ? "Lab" : "Lec"}
                                                                    </span>
                                                                    {pill.roomLabel ? (
                                                                        <span className="text-muted-foreground border-border/80 ml-0.5 border-l pl-1">
                                                                            {pill.roomLabel}
                                                                            {pill.roomCapacity !== null
                                                                                ? ` (${pill.roomCapacity} cap)`
                                                                                : ""}
                                                                        </span>
                                                                    ) : null}
                                                                    {overBy > 0 || pill.isOvercrowded ? (
                                                                        <span className="font-semibold text-rose-600 dark:text-rose-400">
                                                                            ▲{overBy > 0 ? ` +${overBy}` : " Over"}
                                                                        </span>
                                                                    ) : null}
                                                                </span>
                                                            );
                                                        })}
                                                    </div>
                                                ) : (
                                                    <div className="text-muted-foreground flex items-center gap-1.5 text-[11px]">
                                                        <span>No meeting times set</span>
                                                        {row.room ? (
                                                            <span>
                                                                · {row.room}
                                                                {row.room_capacity ? ` (${row.room_capacity} cap)` : ""}
                                                            </span>
                                                        ) : null}
                                                    </div>
                                                )}
                                            </div>
                                        </td>

                                        {/* 4. Seats / WL */}
                                        <td className={cn("px-3 align-top", cellPadding)}>
                                            <div className="space-y-1.5">
                                                <div className="flex items-center justify-between gap-2 text-xs tabular-nums">
                                                    <span>
                                                        <span className="text-foreground font-semibold">{enrolled}</span>
                                                        <span className="text-muted-foreground">/{maxSlots || "—"}</span>
                                                    </span>
                                                    <div className="flex items-center gap-1">
                                                        {isOverCap ? (
                                                            <span className="rounded-full bg-rose-500/15 px-1.5 py-0.5 text-[10px] font-semibold text-rose-700 dark:text-rose-300">
                                                                +{enrolled - maxSlots} over
                                                            </span>
                                                        ) : isFull ? (
                                                            <span className="rounded-full bg-amber-500/15 px-1.5 py-0.5 text-[10px] font-semibold text-amber-800 dark:text-amber-300">
                                                                Full
                                                            </span>
                                                        ) : maxSlots > 0 ? (
                                                            <span className="text-muted-foreground text-[11px]">
                                                                {maxSlots - enrolled} open
                                                            </span>
                                                        ) : null}

                                                        {waitlistCount > 0 ? (
                                                            <span className="rounded-full border border-indigo-500/30 bg-indigo-500/10 px-1.5 py-0.5 text-[10px] font-semibold text-indigo-700 dark:text-indigo-300">
                                                                +{waitlistCount} WL
                                                            </span>
                                                        ) : null}
                                                    </div>
                                                </div>
                                                <div className="bg-muted h-1.5 w-full overflow-hidden rounded-full" aria-hidden="true">
                                                    <div
                                                        className={cn(
                                                            "h-full rounded-full transition-all",
                                                            isOverCap
                                                                ? "bg-rose-500"
                                                                : isFull || isNearFull
                                                                  ? "bg-amber-500"
                                                                  : "bg-emerald-600 dark:bg-emerald-500",
                                                        )}
                                                        style={{ width: `${fillPct}%` }}
                                                    />
                                                </div>
                                            </div>
                                        </td>

                                        {/* 5. Quick Actions */}
                                        <td className={cn("pl-3 pr-3 text-right align-top", cellPadding)}>
                                            <div className="flex items-center justify-end gap-1">
                                                {!isArchived ? (
                                                    <>
                                                        <Button
                                                            type="button"
                                                            size="xs"
                                                            variant={
                                                                isStudioActive && studioMode === "enroll"
                                                                    ? "default"
                                                                    : "outline"
                                                            }
                                                            onClick={() => onOpenStudio(row.id, "enroll")}
                                                        >
                                                            <UserPlus className="size-3" aria-hidden="true" />
                                                            Enroll
                                                        </Button>
                                                        <Button
                                                            type="button"
                                                            size="xs"
                                                            variant={
                                                                isStudioActive && studioMode === "schedule"
                                                                    ? "default"
                                                                    : "outline"
                                                            }
                                                            onClick={() => onOpenStudio(row.id, "schedule")}
                                                        >
                                                            <CalendarClock className="size-3" aria-hidden="true" />
                                                            Sched
                                                        </Button>
                                                    </>
                                                ) : null}
                                                <div
                                                    className={cn(
                                                        !isArchived && "[&>div>button:not([aria-label])]:hidden",
                                                    )}
                                                >
                                                    <ClassRowActions classRow={row} {...rowActions} />
                                                </div>
                                            </div>
                                        </td>
                                    </tr>

                                    {isExpanded ? (
                                        <ExpandedClassSubRow
                                            row={row}
                                            workspace={workspaceCache[row.id]}
                                            loading={Boolean(loadingWorkspaceIds[row.id])}
                                            canUpdate={capabilities.update && !isArchived}
                                            onOpenStudio={onOpenStudio}
                                            onOpenSectionStudio={onOpenSectionStudio}
                                            onRefreshWorkspace={onRefreshWorkspace}
                                            onRefreshIndex={onRefreshIndex}
                                        />
                                    ) : null}
                                </React.Fragment>
                            );
                        })}
                    </tbody>
                </table>
            </div>
        </TooltipProvider>
    );
}
