import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Checkbox } from "@/components/ui/checkbox";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { ScrollArea } from "@/components/ui/scroll-area";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Skeleton } from "@/components/ui/skeleton";
import { Textarea } from "@/components/ui/textarea";
import { cn } from "@/lib/utils";
import {
    AlertTriangle,
    ArrowRightLeft,
    CalendarClock,
    CheckCircle2,
    Clock,
    Loader2,
    MapPin,
    Plus,
    Search,
    Send,
    ShieldAlert,
    Sparkles,
    Trash2,
    UserPlus,
    Users,
    Wifi,
    X,
} from "lucide-react";
import * as React from "react";
import { toast } from "sonner";
import type { ClassRow } from "../columns";
import { isOnlineDeliveryMode } from "../lib/class-defaults";
import {
    enrollStudentsInClass,
    patchClassScheduleWorkspace,
    postScheduleImpactPreview,
    searchClassCandidates,
    WorkspaceApiError,
} from "./api";
import type {
    BusyOverlayBlock,
    ClassWorkspacePayload,
    EditableScheduleBlock,
    RoomOption,
    ScheduleImpactResponse,
    StudentSearchCandidate,
} from "./types";

export interface DualModeClassStudioProps {
    classRow: ClassRow | null;
    workspace: ClassWorkspacePayload | null;
    loading: boolean;
    mode: "enroll" | "schedule";
    onModeChange: (mode: "enroll" | "schedule") => void;
    onClose: () => void;
    hideCollapseButton?: boolean;
    rooms: RoomOption[];
    selectedClassCount: number;
    canUpdate: boolean;
    enrollSearchInputRef?: React.RefObject<HTMLInputElement | null>;
    onWorkspaceMutated: (classId: number) => Promise<void>;
    onRefreshIndex: () => void;
}

const WEEK_DAYS = ["Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday"] as const;
const ALL_DAYS = ["Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday", "Sunday"] as const;
const GRID_START_HOUR = 7;
const GRID_END_HOUR = 19;
const GRID_TOTAL_MINUTES = (GRID_END_HOUR - GRID_START_HOUR) * 60;

function timeToMinutes(hhmm: string | null | undefined): number | null {
    if (!hhmm) return null;
    const parts = hhmm.split(":");
    if (parts.length < 2) return null;
    const h = Number(parts[0]);
    const m = Number(parts[1]);
    if (Number.isNaN(h) || Number.isNaN(m)) return null;
    return h * 60 + m;
}

function calcTotalHours(blocks: EditableScheduleBlock[]): number {
    let mins = 0;
    for (const block of blocks) {
        const s = timeToMinutes(block.start_time);
        const e = timeToMinutes(block.end_time);
        if (s !== null && e !== null && e > s) {
            mins += e - s;
        }
    }
    return Math.round((mins / 60) * 10) / 10;
}

function parseBatchIdentifiers(raw: string): string[] {
    const tokens = raw
        .split(/[\s,;]+/)
        .map((t) => t.trim())
        .filter((t) => t.length > 0);
    return Array.from(new Set(tokens));
}

function toEditableBlocks(workspace: ClassWorkspacePayload | null, classRow: ClassRow | null): EditableScheduleBlock[] {
    const source = workspace?.schedule_summary?.schedules ?? classRow?.schedules ?? [];
    return source.map((b) => ({
        id: b.id,
        day_of_week: b.day_of_week || "Monday",
        start_time: (b.start_time ?? "08:00").slice(0, 5),
        end_time: (b.end_time ?? "09:00").slice(0, 5),
        room_id: isOnlineDeliveryMode(b.delivery_mode) ? null : b.room_id,
        delivery_mode: b.delivery_mode || "face_to_face",
        component_type: b.component_type === "lab" ? "lab" : "lecture",
    }));
}

function shortRevHash(revision: string | undefined | null): string {
    if (!revision) return "init";
    return revision.slice(0, 7);
}

function formatConflictLabel(c: StudentSearchCandidate["schedule_conflicts"][number]): string {
    if (typeof c.label === "string" && c.label.trim()) return c.label;
    const subject = c.subject_code ? `${c.subject_code}${c.section ? `-${c.section}` : ""}` : "Class";
    const slot = [c.day, c.start_time && c.end_time ? `${c.start_time}–${c.end_time}` : null].filter(Boolean).join(" ");
    return slot ? `${subject} (${slot})` : subject;
}

export function DualModeClassStudio({
    classRow,
    workspace,
    loading,
    mode,
    onModeChange,
    onClose,
    hideCollapseButton = false,
    rooms,
    selectedClassCount,
    canUpdate,
    enrollSearchInputRef,
    onWorkspaceMutated,
    onRefreshIndex,
}: DualModeClassStudioProps) {
    // ──────────────────────────────────────────────
    // Mode 1: Enroll & Cohort state
    // ──────────────────────────────────────────────
    const [enrollSubTab, setEnrollSubTab] = React.useState<"search" | "batch">("search");
    const [searchQuery, setSearchQuery] = React.useState("");
    const [candidates, setCandidates] = React.useState<StudentSearchCandidate[]>([]);
    const [searching, setSearching] = React.useState(false);
    const [enrollingCandidateId, setEnrollingCandidateId] = React.useState<number | null>(null);
    const [searchOverrideReason, setSearchOverrideReason] = React.useState("");
    const [highlightSearchOverride, setHighlightSearchOverride] = React.useState(false);
    const searchOverrideInputRef = React.useRef<HTMLInputElement | null>(null);

    // Batch intake state
    const [batchText, setBatchText] = React.useState("");
    const [batchRemarks, setBatchRemarks] = React.useState("");
    const [syncSubjectEnrollment, setSyncSubjectEnrollment] = React.useState(true);
    const [allowSiblingTransfer, setAllowSiblingTransfer] = React.useState(false);
    const [forceOverload, setForceOverload] = React.useState(false);
    const [batchOverrideReason, setBatchOverrideReason] = React.useState("");
    const [submittingBatch, setSubmittingBatch] = React.useState(false);
    const [batchSummary, setBatchSummary] = React.useState<{
        enrolled: number;
        transferred: number;
        skipped: number;
        errors: Array<Record<string, unknown>>;
        message: string;
    } | null>(null);

    // ──────────────────────────────────────────────
    // Mode 2: Schedule & Rooms state
    // ──────────────────────────────────────────────
    const [draftBlocks, setDraftBlocks] = React.useState<EditableScheduleBlock[]>([]);
    const [selectedBlockIdx, setSelectedBlockIdx] = React.useState<number>(0);
    const [scheduleDirty, setScheduleDirty] = React.useState(false);
    const [impactPreview, setImpactPreview] = React.useState<ScheduleImpactResponse | null>(null);
    const [previewingImpact, setPreviewingImpact] = React.useState(false);
    const [notifyEnrolled, setNotifyEnrolled] = React.useState(true);
    const [savingSchedule, setSavingSchedule] = React.useState<"draft" | "published" | null>(null);

    const classId = classRow?.id ?? null;

    // Reset state when target class changes
    React.useEffect(() => {
        setSearchQuery("");
        setCandidates([]);
        setSearchOverrideReason("");
        setHighlightSearchOverride(false);
        setBatchText("");
        setBatchSummary(null);
        setBatchOverrideReason("");
        setForceOverload(false);
        setScheduleDirty(false);
        setImpactPreview(null);
    }, [classId]);

    // Hydrate draft blocks when workspace loads or changes revision
    const workspaceRevision = workspace?.schedule_revision ?? classRow?.schedule_revision ?? "";
    React.useEffect(() => {
        if (!classId) {
            setDraftBlocks([]);
            return;
        }
        const initial = toEditableBlocks(workspace, classRow);
        setDraftBlocks(initial);
        setSelectedBlockIdx(0);
        setScheduleDirty(false);
    }, [classId, workspaceRevision]);

    // Debounced candidate search
    React.useEffect(() => {
        if (!classId || mode !== "enroll" || enrollSubTab !== "search") return;
        const trimmed = searchQuery.trim();
        if (trimmed.length === 0) {
            setCandidates([]);
            setSearching(false);
            return;
        }

        let cancelled = false;
        setSearching(true);
        const timer = window.setTimeout(async () => {
            try {
                const list = await searchClassCandidates(classId, trimmed, 15);
                if (!cancelled) setCandidates(list);
            } catch {
                if (!cancelled) setCandidates([]);
            } finally {
                if (!cancelled) setSearching(false);
            }
        }, 240);

        return () => {
            cancelled = true;
            window.clearTimeout(timer);
        };
    }, [classId, mode, enrollSubTab, searchQuery]);

    // Debounced live schedule impact preview
    React.useEffect(() => {
        if (!classId || mode !== "schedule" || !scheduleDirty) return;

        const validBlocks = draftBlocks.filter(
            (b) => b.day_of_week && b.start_time && b.end_time && b.end_time > b.start_time,
        );

        let cancelled = false;
        setPreviewingImpact(true);
        const timer = window.setTimeout(async () => {
            try {
                const impact = await postScheduleImpactPreview(classId, validBlocks);
                if (!cancelled) setImpactPreview(impact);
            } catch {
                // Silent fallback — validation error will surface on save
            } finally {
                if (!cancelled) setPreviewingImpact(false);
            }
        }, 300);

        return () => {
            cancelled = true;
            window.clearTimeout(timer);
        };
    }, [classId, mode, draftBlocks, scheduleDirty]);

    if (!classRow) {
        return (
            <aside className="border-border bg-card flex h-full min-h-[420px] flex-col items-center justify-center rounded-xl border p-6 text-center shadow-xs">
                <Sparkles className="text-muted-foreground mb-2 size-7 opacity-40" aria-hidden="true" />
                <p className="text-foreground text-sm font-semibold">Dual-Mode Class Studio</p>
                <p className="text-muted-foreground mt-1 max-w-xs text-xs">
                    Select <span className="text-foreground font-medium">+ Enroll</span> or{" "}
                    <span className="text-foreground font-medium">Sched</span> on any class row to manage enrollment,
                    preflight guards, and conflict-free schedules.
                </p>
            </aside>
        );
    }

    const activeClass = workspace?.class;
    const currentSeats = activeClass?.active_students_count ?? classRow.students_count;
    const maxSlots = activeClass?.maximum_slots ?? classRow.maximum_slots ?? 0;
    const waitlistCount = workspace?.schedule_summary?.waitlist_count ?? classRow.waitlist_count ?? 0;
    const facultyName = activeClass?.faculty?.name ?? (classRow.faculty && classRow.faculty !== "TBA" ? classRow.faculty : null);
    const requiredHours =
        workspace?.schedule_summary?.schedule_health?.required_hours ?? classRow.schedule_health?.required_hours ?? 0;
    const scheduleStatus =
        workspace?.schedule_summary?.schedule_health?.status ?? classRow.schedule_health?.status ?? "unscheduled";

    const batchTokens = parseBatchIdentifiers(batchText);
    const projectedSeats = enrollSubTab === "batch" ? currentSeats + batchTokens.length : currentSeats;
    const projectedOverCap = maxSlots > 0 && projectedSeats > maxSlots;
    const currentPct = maxSlots > 0 ? Math.min(100, Math.round((currentSeats / maxSlots) * 100)) : 0;
    const projectedPct = maxSlots > 0 ? Math.min(100, Math.round((projectedSeats / maxSlots) * 100)) : 0;

    // ──────────────────────────────────────────────
    // Enroll Handlers
    // ──────────────────────────────────────────────
    const handleCandidateAction = async (candidate: StudentSearchCandidate) => {
        if (!canUpdate || candidate.already_enrolled_here) return;

        const isTransfer = Boolean(candidate.sibling_section_enrollment);
        const hasHold = candidate.holds.blocking_count > 0;
        const hasClash = candidate.schedule_conflicts.length > 0;
        const needsOverload = candidate.would_exceed_capacity;
        const needsOverride = hasHold || hasClash || needsOverload;

        if (needsOverride && searchOverrideReason.trim().length < 3) {
            setHighlightSearchOverride(true);
            toast.error("Provide an audit override reason below to force enrollment with guards active.");
            window.setTimeout(() => searchOverrideInputRef.current?.focus(), 50);
            return;
        }

        setEnrollingCandidateId(candidate.id);
        try {
            const response = await enrollStudentsInClass(classRow.id, {
                student_ids: [candidate.id],
                allow_transfer_from_sibling: isTransfer,
                force_overload: needsOverload,
                allow_hold_override: hasHold,
                allow_clash_override: hasClash,
                override_reason: needsOverride ? searchOverrideReason.trim() : null,
                sync_subject_enrollment: true,
            });
            if (response.result.errors.length > 0) {
                const firstErr = String(response.result.errors[0]?.message ?? response.message);
                toast.error(firstErr);
            } else {
                toast.success(response.message);
                setHighlightSearchOverride(false);
                await onWorkspaceMutated(classRow.id);
                onRefreshIndex();
                // Refresh current candidate search results
                if (searchQuery.trim()) {
                    const refreshed = await searchClassCandidates(classRow.id, searchQuery.trim(), 15);
                    setCandidates(refreshed);
                }
            }
        } catch (err) {
            toast.error(err instanceof WorkspaceApiError ? err.message : "Failed to enroll student.");
        } finally {
            setEnrollingCandidateId(null);
        }
    };

    const handleBatchSubmit = async (event: React.FormEvent) => {
        event.preventDefault();
        if (!canUpdate || batchTokens.length === 0) return;

        const requiresReason = forceOverload || projectedOverCap;
        if (requiresReason && batchOverrideReason.trim().length < 3) {
            toast.error("Provide an audit override reason when forcing overload.");
            return;
        }

        setSubmittingBatch(true);
        setBatchSummary(null);
        try {
            const response = await enrollStudentsInClass(classRow.id, {
                student_ids: batchTokens,
                force_overload: forceOverload || projectedOverCap,
                override_reason: requiresReason ? batchOverrideReason.trim() : null,
                allow_transfer_from_sibling: allowSiblingTransfer,
                sync_subject_enrollment: syncSubjectEnrollment,
                remarks: batchRemarks.trim() || null,
            });

            setBatchSummary({
                enrolled: response.result.enrolled.length,
                transferred: response.result.transferred.length,
                skipped: response.result.skipped.length,
                errors: response.result.errors,
                message: response.message,
            });

            if (response.result.enrolled.length > 0 || response.result.transferred.length > 0) {
                toast.success(response.message);
                await onWorkspaceMutated(classRow.id);
                onRefreshIndex();
            } else {
                toast.warning(response.message);
            }
        } catch (err) {
            toast.error(err instanceof WorkspaceApiError ? err.message : "Batch enrollment failed.");
        } finally {
            setSubmittingBatch(false);
        }
    };

    // ──────────────────────────────────────────────
    // Schedule Handlers
    // ──────────────────────────────────────────────
    const defaultRoomId = activeClass?.room?.id ?? (rooms[0] ? Number(rooms[0].id) : null);

    const mutateBlocks = (updater: (prev: EditableScheduleBlock[]) => EditableScheduleBlock[], nextIdx?: number) => {
        setDraftBlocks((prev) => updater(prev));
        if (nextIdx !== undefined) setSelectedBlockIdx(nextIdx);
        setScheduleDirty(true);
    };

    const applyQuickPattern = (pattern: "mwf" | "tth" | "lab" | "add") => {
        if (!canUpdate) return;
        if (pattern === "mwf") {
            const blocks: EditableScheduleBlock[] = ["Monday", "Wednesday", "Friday"].map((day) => ({
                day_of_week: day,
                start_time: "08:00",
                end_time: "09:00",
                room_id: defaultRoomId,
                delivery_mode: "face_to_face",
                component_type: "lecture",
            }));
            mutateBlocks(() => blocks, 0);
        } else if (pattern === "tth") {
            const blocks: EditableScheduleBlock[] = ["Tuesday", "Thursday"].map((day) => ({
                day_of_week: day,
                start_time: "10:00",
                end_time: "11:30",
                room_id: defaultRoomId,
                delivery_mode: "face_to_face",
                component_type: "lecture",
            }));
            mutateBlocks(() => blocks, 0);
        } else if (pattern === "lab") {
            mutateBlocks((prev) => {
                const next: EditableScheduleBlock[] = [
                    ...prev,
                    {
                        day_of_week: "Thursday",
                        start_time: "13:00",
                        end_time: "15:00",
                        room_id: defaultRoomId,
                        delivery_mode: "face_to_face",
                        component_type: "lab",
                    },
                ];
                setSelectedBlockIdx(next.length - 1);
                return next;
            });
        } else {
            mutateBlocks((prev) => {
                const next: EditableScheduleBlock[] = [
                    ...prev,
                    {
                        day_of_week: "Monday",
                        start_time: "09:00",
                        end_time: "10:00",
                        room_id: defaultRoomId,
                        delivery_mode: "face_to_face",
                        component_type: "lecture",
                    },
                ];
                setSelectedBlockIdx(next.length - 1);
                return next;
            });
        }
    };

    const updateSelectedBlock = (patch: Partial<EditableScheduleBlock>) => {
        if (!canUpdate) return;
        mutateBlocks((prev) =>
            prev.map((block, idx) => {
                if (idx !== selectedBlockIdx) return block;
                const merged = { ...block, ...patch };
                if (isOnlineDeliveryMode(merged.delivery_mode)) {
                    merged.room_id = null;
                }
                return merged;
            }),
        );
    };

    const removeBlock = (idx: number) => {
        if (!canUpdate) return;
        mutateBlocks((prev) => {
            const next = prev.filter((_, i) => i !== idx);
            setSelectedBlockIdx((curr) => Math.max(0, Math.min(curr, next.length - 1)));
            return next;
        });
    };

    const handleSaveSchedule = async (status: "draft" | "published") => {
        if (!canUpdate) return;

        const invalid = draftBlocks.find(
            (b) => !b.day_of_week || !b.start_time || !b.end_time || b.end_time <= b.start_time,
        );
        if (invalid) {
            toast.error("Each meeting block needs an end time after its start time.");
            return;
        }

        setSavingSchedule(status);
        try {
            const response = await patchClassScheduleWorkspace(classRow.id, {
                schedules: draftBlocks,
                schedule_revision: workspaceRevision,
                schedule_status: status,
                notify_enrolled: status === "published" ? notifyEnrolled : false,
            });
            toast.success(response.message);
            setScheduleDirty(false);
            setImpactPreview(null);
            await onWorkspaceMutated(classRow.id);
            onRefreshIndex();
        } catch (err) {
            if (err instanceof WorkspaceApiError && err.status === 409) {
                toast.error("Schedule was modified by someone else. Refreshing latest revision...");
                await onWorkspaceMutated(classRow.id);
            } else {
                toast.error(err instanceof WorkspaceApiError ? err.message : "Failed to save schedule.");
            }
        } finally {
            setSavingSchedule(null);
        }
    };

    const draftHours = calcTotalHours(draftBlocks);
    const selectedBlock = draftBlocks[selectedBlockIdx] ?? null;
    const studentImpact = impactPreview?.student_impact ?? workspace?.student_impact ?? null;
    const resolutions = workspace?.resolutions;

    // Combine busy overlays for weekly grid
    const overlayBlocks: BusyOverlayBlock[] = workspace?.busy_overlay
        ? [
              ...workspace.busy_overlay.faculty_busy,
              ...workspace.busy_overlay.room_busy,
              ...workspace.busy_overlay.cohort_busy,
          ]
        : [];

    const roomConflictsCount = impactPreview?.room_conflicts.length ?? 0;
    const facultyConflictsCount = impactPreview?.faculty_conflicts.length ?? 0;
    const overcrowdedCount = impactPreview?.overcrowded_blocks.length ?? 0;

    return (
        <aside className="border-border bg-card flex h-full flex-col overflow-hidden rounded-xl border shadow-sm">
            {/* Studio Header */}
            <div className="border-border bg-muted/30 border-b px-4 py-3">
                <div className="flex items-center justify-between gap-2">
                    <span className="text-muted-foreground text-[10px] font-bold tracking-widest uppercase">
                        DUAL-MODE CLASS STUDIO
                    </span>
                    {!hideCollapseButton ? (
                        <Button type="button" variant="ghost" size="xs" onClick={onClose} className="text-muted-foreground h-6 px-1.5">
                            <X className="size-3.5" aria-hidden="true" />
                            Collapse
                        </Button>
                    ) : null}
                </div>

                <div className="mt-1.5 flex flex-wrap items-center gap-1.5">
                    <h2 className="text-foreground text-sm font-semibold tracking-tight">
                        Target: {activeClass?.record_title ?? classRow.record_title}
                    </h2>
                    {selectedClassCount > 1 ? (
                        <Badge variant="secondary" className="h-5 text-[10px] tabular-nums">
                            1 of {selectedClassCount} checked
                        </Badge>
                    ) : null}
                </div>

                <p className="text-muted-foreground mt-0.5 line-clamp-1 text-xs">
                    {activeClass?.subject_title ?? classRow.subject_title}
                </p>

                <div className="mt-2 flex flex-wrap items-center gap-1.5 text-[11px]">
                    {facultyName ? (
                        <Badge variant="outline" className="h-5 font-normal">
                            {facultyName}
                        </Badge>
                    ) : (
                        <Badge
                            variant="outline"
                            className="h-5 border-amber-500/30 bg-amber-500/10 text-amber-800 dark:text-amber-300"
                        >
                            Unassigned Faculty
                        </Badge>
                    )}
                    <Badge variant="secondary" className="h-5 tabular-nums">
                        <Users className="size-3" aria-hidden="true" />
                        {currentSeats}/{maxSlots || "—"}
                    </Badge>
                    {waitlistCount > 0 ? (
                        <Badge
                            variant="outline"
                            className="h-5 border-indigo-500/30 bg-indigo-500/10 text-indigo-700 tabular-nums dark:text-indigo-300"
                        >
                            +{waitlistCount} WL
                        </Badge>
                    ) : null}
                    <Badge variant="outline" className="h-5 tabular-nums">
                        <Clock className="size-3" aria-hidden="true" />
                        {draftHours}/{requiredHours}h
                    </Badge>
                </div>

                {/* Mode Switcher */}
                <div className="bg-muted mt-3 grid grid-cols-2 rounded-lg p-0.5 text-xs">
                    <button
                        type="button"
                        onClick={() => onModeChange("enroll")}
                        className={cn(
                            "inline-flex items-center justify-center gap-1.5 rounded-md px-2.5 py-1.5 font-medium transition-colors",
                            mode === "enroll"
                                ? "bg-background text-foreground shadow-xs"
                                : "text-muted-foreground hover:text-foreground",
                        )}
                    >
                        <UserPlus className="size-3.5" aria-hidden="true" />
                        Enroll &amp; Cohort
                    </button>
                    <button
                        type="button"
                        onClick={() => onModeChange("schedule")}
                        className={cn(
                            "inline-flex items-center justify-center gap-1.5 rounded-md px-2.5 py-1.5 font-medium transition-colors",
                            mode === "schedule"
                                ? "bg-background text-foreground shadow-xs"
                                : "text-muted-foreground hover:text-foreground",
                        )}
                    >
                        <CalendarClock className="size-3.5" aria-hidden="true" />
                        Schedule &amp; Rooms
                    </button>
                </div>
            </div>

            {/* Body */}
            {loading && !workspace ? (
                <div className="space-y-3 p-4">
                    <Skeleton className="h-14 w-full rounded-lg" />
                    <Skeleton className="h-32 w-full rounded-lg" />
                    <Skeleton className="h-44 w-full rounded-lg" />
                </div>
            ) : mode === "enroll" ? (
                <ScrollArea className="flex-1">
                    <div className="space-y-4 p-4">
                        {/* Projected Seats Meter */}
                        <div className="border-border bg-muted/25 space-y-2 rounded-lg border p-3">
                            <div className="flex items-center justify-between text-xs">
                                <span className="text-muted-foreground font-medium">Projected Seats</span>
                                <span className="tabular-nums">
                                    <span className="text-foreground font-semibold">{currentSeats}</span>
                                    {projectedSeats !== currentSeats ? (
                                        <span className={cn("font-semibold", projectedOverCap ? "text-rose-600 dark:text-rose-400" : "text-emerald-600 dark:text-emerald-400")}>
                                            {" "}
                                            → {projectedSeats}
                                        </span>
                                    ) : null}
                                    <span className="text-muted-foreground"> / {maxSlots || "∞"}</span>
                                </span>
                            </div>
                            <div className="bg-muted relative h-2 overflow-hidden rounded-full">
                                {projectedPct > currentPct ? (
                                    <div
                                        className={cn(
                                            "absolute inset-y-0 left-0 rounded-full opacity-40 transition-all",
                                            projectedOverCap ? "bg-rose-500" : "bg-emerald-500",
                                        )}
                                        style={{ width: `${projectedPct}%` }}
                                    />
                                ) : null}
                                <div
                                    className={cn(
                                        "relative h-full rounded-full transition-all",
                                        maxSlots > 0 && currentSeats > maxSlots
                                            ? "bg-rose-500"
                                            : maxSlots > 0 && currentSeats >= maxSlots
                                              ? "bg-amber-500"
                                              : "bg-primary",
                                    )}
                                    style={{ width: `${currentPct}%` }}
                                />
                            </div>
                            {projectedOverCap ? (
                                <p className="flex items-center gap-1.5 text-[11px] font-medium text-rose-600 dark:text-rose-400">
                                    <AlertTriangle className="size-3.5 shrink-0" aria-hidden="true" />
                                    Exceeds capacity by {projectedSeats - maxSlots} seat(s). Overload override reason required.
                                </p>
                            ) : null}
                        </div>

                        {/* Sub-tabs: Search Students | Paste IDs / Batch */}
                        <div className="border-border flex items-center gap-1 border-b pb-2">
                            <button
                                type="button"
                                onClick={() => setEnrollSubTab("search")}
                                className={cn(
                                    "rounded-md px-2.5 py-1 text-xs font-medium transition-colors",
                                    enrollSubTab === "search"
                                        ? "bg-primary text-primary-foreground"
                                        : "text-muted-foreground hover:bg-muted hover:text-foreground",
                                )}
                            >
                                Search Students
                            </button>
                            <button
                                type="button"
                                onClick={() => setEnrollSubTab("batch")}
                                className={cn(
                                    "rounded-md px-2.5 py-1 text-xs font-medium transition-colors",
                                    enrollSubTab === "batch"
                                        ? "bg-primary text-primary-foreground"
                                        : "text-muted-foreground hover:bg-muted hover:text-foreground",
                                )}
                            >
                                Paste IDs / Batch
                            </button>
                        </div>

                        {enrollSubTab === "search" ? (
                            <div className="space-y-3">
                                <div className="relative">
                                    <Search
                                        className="text-muted-foreground pointer-events-none absolute left-2.5 top-1/2 size-3.5 -translate-y-1/2"
                                        aria-hidden="true"
                                    />
                                    <Input
                                        ref={enrollSearchInputRef}
                                        type="search"
                                        value={searchQuery}
                                        onChange={(e) => setSearchQuery(e.target.value)}
                                        placeholder="Search name, student ID, LRN, or email..."
                                        className="pl-8 text-xs"
                                    />
                                </div>

                                {/* Inline Override Reason Field (shown when needed or typed) */}
                                <div
                                    className={cn(
                                        "rounded-lg border p-2.5 transition-colors",
                                        highlightSearchOverride
                                            ? "border-amber-500 bg-amber-500/10"
                                            : "border-border bg-muted/20",
                                    )}
                                >
                                    <Label
                                        htmlFor="studio-search-override"
                                        className="text-muted-foreground text-[11px] font-medium"
                                    >
                                        Audit Override Reason (required for overload, hold, or clash overrides)
                                    </Label>
                                    <Input
                                        ref={searchOverrideInputRef}
                                        id="studio-search-override"
                                        value={searchOverrideReason}
                                        onChange={(e) => {
                                            setSearchOverrideReason(e.target.value);
                                            if (e.target.value.trim().length >= 3) setHighlightSearchOverride(false);
                                        }}
                                        placeholder="e.g., Dean-approved overload for graduating student"
                                        className="bg-background mt-1 h-7 text-xs"
                                    />
                                </div>

                                {searching ? (
                                    <div className="space-y-2">
                                        <Skeleton className="h-16 w-full rounded-lg" />
                                        <Skeleton className="h-16 w-full rounded-lg" />
                                    </div>
                                ) : searchQuery.trim().length === 0 ? (
                                    <div className="border-border text-muted-foreground rounded-lg border border-dashed p-5 text-center text-xs">
                                        Type a student name, ID number, LRN, or email to run live preflight schedule,
                                        hold, and sibling-section checks.
                                    </div>
                                ) : candidates.length === 0 ? (
                                    <div className="border-border text-muted-foreground rounded-lg border border-dashed p-5 text-center text-xs">
                                        No matching students found for &ldquo;{searchQuery}&rdquo;.
                                    </div>
                                ) : (
                                    <div className="space-y-2">
                                        {candidates.map((cand) => {
                                            const isBusy = enrollingCandidateId === cand.id;
                                            const hasHold = cand.holds.blocking_count > 0 || cand.holds.active_count > 0;
                                            const hasClash = cand.schedule_conflicts.length > 0;
                                            const isSibling = Boolean(cand.sibling_section_enrollment);
                                            const needsOverride =
                                                cand.holds.blocking_count > 0 ||
                                                hasClash ||
                                                cand.would_exceed_capacity;

                                            return (
                                                <div
                                                    key={cand.id}
                                                    className="border-border bg-background space-y-2 rounded-lg border p-2.5 text-xs transition-colors hover:border-border/90"
                                                >
                                                    <div className="flex items-start justify-between gap-2">
                                                        <div className="min-w-0">
                                                            <p className="text-foreground truncate font-semibold">
                                                                {cand.full_name}
                                                            </p>
                                                            <p className="text-muted-foreground truncate text-[11px] tabular-nums">
                                                                {cand.student_id ?? cand.lrn ?? `#${cand.id}`}
                                                                {cand.course ? ` · ${cand.course}` : ""}
                                                                {cand.academic_year ? ` Yr ${cand.academic_year}` : ""}
                                                            </p>
                                                        </div>

                                                        {cand.already_enrolled_here ? (
                                                            <Badge variant="secondary" className="shrink-0 text-[10px]">
                                                                Enrolled
                                                            </Badge>
                                                        ) : canUpdate ? (
                                                            <Button
                                                                type="button"
                                                                size="xs"
                                                                variant={
                                                                    isSibling
                                                                        ? "outline"
                                                                        : needsOverride
                                                                          ? "destructive"
                                                                          : "default"
                                                                }
                                                                disabled={isBusy}
                                                                onClick={() => void handleCandidateAction(cand)}
                                                                className="shrink-0"
                                                            >
                                                                {isBusy ? (
                                                                    <Loader2 className="size-3 animate-spin" aria-hidden="true" />
                                                                ) : isSibling ? (
                                                                    <ArrowRightLeft className="size-3" aria-hidden="true" />
                                                                ) : (
                                                                    <Plus className="size-3" aria-hidden="true" />
                                                                )}
                                                                {isSibling
                                                                    ? `Transfer Sec ${cand.sibling_section_enrollment?.section} → ${classRow.section}`
                                                                    : needsOverride
                                                                      ? "Force Enroll"
                                                                      : "Enroll"}
                                                            </Button>
                                                        ) : null}
                                                    </div>

                                                    {/* Preflight Guard Chips */}
                                                    <div className="flex flex-wrap items-center gap-1">
                                                        {hasClash ? (
                                                            <span className="inline-flex items-center gap-1 rounded-full border border-rose-500/30 bg-rose-500/10 px-2 py-0.5 text-[10px] font-medium text-rose-700 dark:text-rose-300">
                                                                <X className="size-2.5" aria-hidden="true" />
                                                                Clash: {formatConflictLabel(cand.schedule_conflicts[0])}
                                                                {cand.schedule_conflicts.length > 1
                                                                    ? ` (+${cand.schedule_conflicts.length - 1})`
                                                                    : ""}
                                                            </span>
                                                        ) : (
                                                            <span className="inline-flex items-center gap-1 rounded-full border border-emerald-500/25 bg-emerald-500/10 px-2 py-0.5 text-[10px] font-medium text-emerald-700 dark:text-emerald-300">
                                                                <CheckCircle2 className="size-2.5" aria-hidden="true" />
                                                                Clear
                                                            </span>
                                                        )}

                                                        {hasHold ? (
                                                            <span className="inline-flex items-center gap-1 rounded-full border border-amber-500/30 bg-amber-500/10 px-2 py-0.5 text-[10px] font-medium text-amber-800 dark:text-amber-300">
                                                                <ShieldAlert className="size-2.5" aria-hidden="true" />
                                                                Hold:{" "}
                                                                {cand.holds.offices.length > 0
                                                                    ? cand.holds.offices.join(", ")
                                                                    : `${cand.holds.blocking_count || cand.holds.active_count} active`}
                                                            </span>
                                                        ) : null}

                                                        {cand.would_exceed_capacity ? (
                                                            <span className="inline-flex items-center gap-1 rounded-full border border-amber-500/30 bg-amber-500/10 px-2 py-0.5 text-[10px] font-medium text-amber-800 dark:text-amber-300">
                                                                <AlertTriangle className="size-2.5" aria-hidden="true" />
                                                                Over Cap
                                                            </span>
                                                        ) : null}

                                                        {isSibling ? (
                                                            <span className="inline-flex items-center gap-1 rounded-full border border-indigo-500/30 bg-indigo-500/10 px-2 py-0.5 text-[10px] font-medium text-indigo-700 dark:text-indigo-300">
                                                                In Sec {cand.sibling_section_enrollment?.section}
                                                            </span>
                                                        ) : null}
                                                    </div>
                                                </div>
                                            );
                                        })}
                                    </div>
                                )}
                            </div>
                        ) : (
                            /* Batch / Paste IDs Sub-tab */
                            <form onSubmit={(e) => void handleBatchSubmit(e)} className="space-y-3">
                                <div className="space-y-1.5">
                                    <div className="flex items-center justify-between">
                                        <Label htmlFor="studio-batch-ids" className="text-xs font-medium">
                                            Student IDs, LRNs, or Emails
                                        </Label>
                                        <span className="text-muted-foreground text-[11px] tabular-nums">
                                            {batchTokens.length} detected
                                        </span>
                                    </div>
                                    <Textarea
                                        id="studio-batch-ids"
                                        value={batchText}
                                        onChange={(e) => setBatchText(e.target.value)}
                                        placeholder="Paste comma, space, or newline-separated IDs&#10;2024-00102&#10;2024-00105, juan@school.edu"
                                        rows={4}
                                        className="font-mono text-xs"
                                    />
                                </div>

                                <div className="space-y-1.5">
                                    <Label htmlFor="studio-batch-remarks" className="text-xs font-medium">
                                        Remarks (optional)
                                    </Label>
                                    <Input
                                        id="studio-batch-remarks"
                                        value={batchRemarks}
                                        onChange={(e) => setBatchRemarks(e.target.value)}
                                        placeholder="e.g., Block section intake"
                                        className="h-8 text-xs"
                                    />
                                </div>

                                <div className="space-y-2 pt-1">
                                    <label className="flex cursor-pointer items-center gap-2 text-xs">
                                        <Checkbox
                                            checked={syncSubjectEnrollment}
                                            onCheckedChange={(v) => setSyncSubjectEnrollment(Boolean(v))}
                                        />
                                        <span>Sync SubjectEnrollment records</span>
                                    </label>
                                    <label className="flex cursor-pointer items-center gap-2 text-xs">
                                        <Checkbox
                                            checked={allowSiblingTransfer}
                                            onCheckedChange={(v) => setAllowSiblingTransfer(Boolean(v))}
                                        />
                                        <span>Allow transfer from sibling section</span>
                                    </label>
                                    <label className="flex cursor-pointer items-center gap-2 text-xs">
                                        <Checkbox
                                            checked={forceOverload}
                                            onCheckedChange={(v) => setForceOverload(Boolean(v))}
                                        />
                                        <span>Force overload (override capacity limit)</span>
                                    </label>
                                </div>

                                {forceOverload || projectedOverCap ? (
                                    <div className="space-y-1.5 rounded-lg border border-amber-500/30 bg-amber-500/10 p-2.5">
                                        <Label htmlFor="studio-batch-override" className="text-xs font-medium text-amber-900 dark:text-amber-200">
                                            Audit Override Reason <span className="text-destructive">*</span>
                                        </Label>
                                        <Input
                                            id="studio-batch-override"
                                            value={batchOverrideReason}
                                            onChange={(e) => setBatchOverrideReason(e.target.value)}
                                            placeholder="Required for capacity overload audit trail..."
                                            className="bg-background h-8 text-xs"
                                        />
                                    </div>
                                ) : null}

                                <Button
                                    type="submit"
                                    className="w-full"
                                    disabled={!canUpdate || submittingBatch || batchTokens.length === 0}
                                >
                                    {submittingBatch ? (
                                        <Loader2 className="size-3.5 animate-spin" aria-hidden="true" />
                                    ) : (
                                        <UserPlus className="size-3.5" aria-hidden="true" />
                                    )}
                                    Enroll {batchTokens.length} Student{batchTokens.length === 1 ? "" : "s"}
                                </Button>

                                {batchSummary ? (
                                    <div className="border-border bg-muted/30 space-y-2 rounded-lg border p-3 text-xs">
                                        <p className="text-foreground font-medium">{batchSummary.message}</p>
                                        <div className="flex flex-wrap gap-1.5 text-[11px] tabular-nums">
                                            <Badge variant="secondary" className="bg-emerald-500/15 text-emerald-700 dark:text-emerald-300">
                                                {batchSummary.enrolled} enrolled
                                            </Badge>
                                            <Badge variant="secondary" className="bg-indigo-500/15 text-indigo-700 dark:text-indigo-300">
                                                {batchSummary.transferred} transferred
                                            </Badge>
                                            <Badge variant="outline">{batchSummary.skipped} skipped</Badge>
                                            {batchSummary.errors.length > 0 ? (
                                                <Badge variant="destructive">{batchSummary.errors.length} errors</Badge>
                                            ) : null}
                                        </div>
                                        {batchSummary.errors.length > 0 ? (
                                            <ul className="max-h-28 list-disc space-y-0.5 overflow-y-auto pl-4 text-[11px] text-rose-600 dark:text-rose-400">
                                                {batchSummary.errors.slice(0, 8).map((err, i) => (
                                                    <li key={i}>
                                                        {String(err.identifier ?? err.student_number ?? err.student_id ?? "Row")}:{" "}
                                                        {String(err.message ?? "Failed")}
                                                    </li>
                                                ))}
                                            </ul>
                                        ) : null}
                                    </div>
                                ) : null}
                            </form>
                        )}
                    </div>
                </ScrollArea>
            ) : (
                /* ──────────────────────────────────────────────
                   Mode 2: Schedule & Rooms
                   ────────────────────────────────────────────── */
                <div className="flex min-h-0 flex-1 flex-col">
                    <ScrollArea className="flex-1">
                        <div className="space-y-3.5 p-4">
                            {/* Status + Optimistic Revision Header Strip */}
                            <div className="flex flex-wrap items-center justify-between gap-2 text-xs">
                                <div className="flex items-center gap-1.5">
                                    <span className="text-foreground font-semibold tabular-nums">
                                        {draftHours} / {requiredHours} hrs
                                    </span>
                                    <Badge
                                        variant="outline"
                                        className={cn(
                                            "h-5 text-[10px]",
                                            scheduleStatus === "published"
                                                ? "border-emerald-500/30 bg-emerald-500/10 text-emerald-700 dark:text-emerald-300"
                                                : scheduleStatus === "draft"
                                                  ? "border-amber-500/30 bg-amber-500/10 text-amber-800 dark:text-amber-300"
                                                  : "text-muted-foreground",
                                        )}
                                    >
                                        {scheduleStatus === "published"
                                            ? "Published"
                                            : scheduleStatus === "draft"
                                              ? "Draft"
                                              : "Unscheduled"}
                                    </Badge>
                                </div>
                                <span
                                    className={cn(
                                        "rounded-full border px-2 py-0.5 font-mono text-[10px]",
                                        scheduleDirty
                                            ? "border-amber-500/30 bg-amber-500/10 text-amber-800 dark:text-amber-300"
                                            : "border-border bg-muted/50 text-muted-foreground",
                                    )}
                                >
                                    Rev #{shortRevHash(workspaceRevision)} · {scheduleDirty ? "Modified" : "Synced"}
                                </span>
                            </div>

                            {/* Quick Patterns Row */}
                            {canUpdate ? (
                                <div className="flex flex-wrap items-center gap-1.5">
                                    <Button type="button" variant="outline" size="xs" onClick={() => applyQuickPattern("mwf")}>
                                        + MWF
                                    </Button>
                                    <Button type="button" variant="outline" size="xs" onClick={() => applyQuickPattern("tth")}>
                                        + TTh
                                    </Button>
                                    <Button type="button" variant="outline" size="xs" onClick={() => applyQuickPattern("lab")}>
                                        + Lab Block
                                    </Button>
                                    <Button type="button" variant="secondary" size="xs" onClick={() => applyQuickPattern("add")}>
                                        <Plus className="size-3" aria-hidden="true" />
                                        Add Block
                                    </Button>
                                </div>
                            ) : null}

                            {/* Interactive Weekly Timetable Grid (Mon–Sat, 07:00–19:00) */}
                            <div className="border-border bg-background overflow-hidden rounded-lg border">
                                <div className="border-border bg-muted/40 flex items-center justify-between border-b px-2.5 py-1.5 text-[10px]">
                                    <span className="text-foreground font-semibold">Weekly Grid (07:00–19:00)</span>
                                    <div className="text-muted-foreground flex items-center gap-2">
                                        <span className="inline-flex items-center gap-1">
                                            <span className="bg-primary inline-block size-2 rounded-xs" />
                                            Lec
                                        </span>
                                        <span className="inline-flex items-center gap-1">
                                            <span className="inline-block size-2 rounded-xs bg-amber-500" />
                                            Lab
                                        </span>
                                        <span className="inline-flex items-center gap-1">
                                            <span className="border-muted-foreground/50 bg-muted inline-block size-2 rounded-xs border border-dashed" />
                                            Busy
                                        </span>
                                    </div>
                                </div>

                                <div className="grid grid-cols-[34px_repeat(6,minmax(0,1fr))] border-b border-border/60 bg-muted/20 text-center text-[10px] font-semibold text-muted-foreground">
                                    <div className="py-1" />
                                    {WEEK_DAYS.map((day) => (
                                        <div key={day} className="border-border/50 border-l py-1">
                                            {day.slice(0, 3)}
                                        </div>
                                    ))}
                                </div>

                                <div className="relative grid grid-cols-[34px_repeat(6,minmax(0,1fr))]" style={{ height: 216 }}>
                                    {/* Time axis */}
                                    <div className="relative text-[9px] text-muted-foreground tabular-nums">
                                        {[7, 9, 11, 13, 15, 17].map((hr) => {
                                            const topPct = (((hr - GRID_START_HOUR) * 60) / GRID_TOTAL_MINUTES) * 100;
                                            return (
                                                <span
                                                    key={hr}
                                                    style={{ top: `${topPct}%` }}
                                                    className="absolute right-1 -translate-y-1/2"
                                                >
                                                    {String(hr).padStart(2, "0")}
                                                </span>
                                            );
                                        })}
                                    </div>

                                    {/* Day columns */}
                                    {WEEK_DAYS.map((day) => {
                                        const dayBusy = overlayBlocks.filter((b) => b.day_of_week === day);
                                        const dayDrafts = draftBlocks
                                            .map((block, idx) => ({ block, idx }))
                                            .filter(({ block }) => block.day_of_week === day);

                                        return (
                                            <div key={day} className="border-border/50 relative border-l">
                                                {/* Hour gridlines */}
                                                {[9, 11, 13, 15, 17].map((hr) => {
                                                    const topPct = (((hr - GRID_START_HOUR) * 60) / GRID_TOTAL_MINUTES) * 100;
                                                    return (
                                                        <div
                                                            key={hr}
                                                            style={{ top: `${topPct}%` }}
                                                            className="border-border/40 pointer-events-none absolute inset-x-0 border-t border-dashed"
                                                        />
                                                    );
                                                })}

                                                {/* Busy overlay blocks */}
                                                {dayBusy.map((busy, bi) => {
                                                    const s = timeToMinutes(busy.start_time);
                                                    const e = timeToMinutes(busy.end_time);
                                                    if (s === null || e === null || e <= s) return null;
                                                    const top = Math.max(0, ((s - GRID_START_HOUR * 60) / GRID_TOTAL_MINUTES) * 100);
                                                    const height = Math.max(
                                                        7,
                                                        ((Math.min(e, GRID_END_HOUR * 60) - Math.max(s, GRID_START_HOUR * 60)) /
                                                            GRID_TOTAL_MINUTES) *
                                                            100,
                                                    );
                                                    const prefix =
                                                        busy.kind === "faculty"
                                                            ? "Fac"
                                                            : busy.kind === "room"
                                                              ? "Rm"
                                                              : "Coh";
                                                    return (
                                                        <div
                                                            key={`${busy.kind}-${busy.class_id}-${bi}`}
                                                            style={{ top: `${top}%`, height: `${height}%` }}
                                                            title={`${busy.kind.toUpperCase()}: ${busy.subject_code}-${busy.section} (${busy.start_time}–${busy.end_time})`}
                                                            className="border-muted-foreground/30 bg-muted/70 text-muted-foreground pointer-events-none absolute inset-x-0.5 overflow-hidden rounded-xs border border-dashed px-0.5 text-[8px] leading-tight"
                                                        >
                                                            <span className="truncate block">
                                                                {prefix}: {busy.subject_code}
                                                            </span>
                                                        </div>
                                                    );
                                                })}

                                                {/* Class draft blocks */}
                                                {dayDrafts.map(({ block, idx }) => {
                                                    const s = timeToMinutes(block.start_time);
                                                    const e = timeToMinutes(block.end_time);
                                                    if (s === null || e === null || e <= s) return null;
                                                    const top = Math.max(0, ((s - GRID_START_HOUR * 60) / GRID_TOTAL_MINUTES) * 100);
                                                    const height = Math.max(
                                                        10,
                                                        ((Math.min(e, GRID_END_HOUR * 60) - Math.max(s, GRID_START_HOUR * 60)) /
                                                            GRID_TOTAL_MINUTES) *
                                                            100,
                                                    );
                                                    const isLab = block.component_type === "lab";
                                                    const isSelected = idx === selectedBlockIdx;
                                                    const hasBlockIssue =
                                                        roomConflictsCount > 0 || facultyConflictsCount > 0 || overcrowdedCount > 0;

                                                    return (
                                                        <button
                                                            key={idx}
                                                            type="button"
                                                            onClick={() => setSelectedBlockIdx(idx)}
                                                            style={{ top: `${top}%`, height: `${height}%` }}
                                                            className={cn(
                                                                "absolute inset-x-0.5 z-10 flex flex-col justify-center overflow-hidden rounded-xs px-1 text-left text-[9px] font-semibold leading-tight shadow-xs transition-all",
                                                                isLab
                                                                    ? "bg-amber-600 text-white dark:bg-amber-500 dark:text-zinc-950"
                                                                    : "bg-primary text-primary-foreground",
                                                                isSelected && "ring-2 ring-foreground ring-offset-1 ring-offset-background",
                                                                hasBlockIssue && "ring-2 ring-rose-500",
                                                            )}
                                                        >
                                                            <span className="truncate">{isLab ? "Lab" : "Lec"}</span>
                                                            <span className="truncate text-[8px] opacity-85 tabular-nums">
                                                                {block.start_time}
                                                            </span>
                                                        </button>
                                                    );
                                                })}
                                            </div>
                                        );
                                    })}
                                </div>
                            </div>

                            {/* Block Selector Strip */}
                            {draftBlocks.length > 0 ? (
                                <div className="flex flex-wrap items-center gap-1">
                                    {draftBlocks.map((b, idx) => (
                                        <button
                                            key={idx}
                                            type="button"
                                            onClick={() => setSelectedBlockIdx(idx)}
                                            className={cn(
                                                "inline-flex items-center gap-1 rounded-md border px-2 py-0.5 text-[11px] font-medium tabular-nums transition-colors",
                                                idx === selectedBlockIdx
                                                    ? "border-primary bg-primary/10 text-foreground"
                                                    : "border-border bg-background text-muted-foreground hover:text-foreground",
                                            )}
                                        >
                                            <span>{b.day_of_week.slice(0, 3)}</span>
                                            <span>
                                                {b.start_time}–{b.end_time}
                                            </span>
                                            <span className="text-[9px] uppercase opacity-75">
                                                {b.component_type === "lab" ? "Lab" : "Lec"}
                                            </span>
                                        </button>
                                    ))}
                                </div>
                            ) : null}

                            {/* Selected Meeting Block Editor */}
                            {selectedBlock ? (
                                <div className="border-border bg-muted/20 space-y-2.5 rounded-lg border p-3">
                                    <div className="flex items-center justify-between">
                                        <span className="text-foreground text-xs font-semibold">
                                            Block #{selectedBlockIdx + 1} Editor
                                        </span>
                                        {canUpdate ? (
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="xs"
                                                onClick={() => removeBlock(selectedBlockIdx)}
                                                className="text-destructive hover:bg-destructive/10 hover:text-destructive h-6 px-1.5"
                                            >
                                                <Trash2 className="size-3" aria-hidden="true" />
                                                Remove
                                            </Button>
                                        ) : null}
                                    </div>

                                    <div className="grid grid-cols-2 gap-2">
                                        <div className="space-y-1">
                                            <Label className="text-[11px]">Day</Label>
                                            <Select
                                                value={selectedBlock.day_of_week}
                                                onValueChange={(v) => updateSelectedBlock({ day_of_week: v })}
                                                disabled={!canUpdate}
                                            >
                                                <SelectTrigger size="sm" className="bg-background h-8 w-full text-xs">
                                                    <SelectValue />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    {ALL_DAYS.map((day) => (
                                                        <SelectItem key={day} value={day}>
                                                            {day}
                                                        </SelectItem>
                                                    ))}
                                                </SelectContent>
                                            </Select>
                                        </div>

                                        <div className="space-y-1">
                                            <Label className="text-[11px]">Component</Label>
                                            <Select
                                                value={selectedBlock.component_type}
                                                onValueChange={(v) =>
                                                    updateSelectedBlock({ component_type: v === "lab" ? "lab" : "lecture" })
                                                }
                                                disabled={!canUpdate}
                                            >
                                                <SelectTrigger size="sm" className="bg-background h-8 w-full text-xs">
                                                    <SelectValue />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    <SelectItem value="lecture">Lecture</SelectItem>
                                                    <SelectItem value="lab">Laboratory</SelectItem>
                                                </SelectContent>
                                            </Select>
                                        </div>

                                        <div className="space-y-1">
                                            <Label className="text-[11px]">Start Time</Label>
                                            <Input
                                                type="time"
                                                value={selectedBlock.start_time}
                                                onChange={(e) => updateSelectedBlock({ start_time: e.target.value })}
                                                disabled={!canUpdate}
                                                className="bg-background h-8 text-xs tabular-nums"
                                            />
                                        </div>

                                        <div className="space-y-1">
                                            <Label className="text-[11px]">End Time</Label>
                                            <Input
                                                type="time"
                                                value={selectedBlock.end_time}
                                                onChange={(e) => updateSelectedBlock({ end_time: e.target.value })}
                                                disabled={!canUpdate}
                                                className="bg-background h-8 text-xs tabular-nums"
                                            />
                                        </div>

                                        <div className="space-y-1">
                                            <Label className="text-[11px]">Delivery Mode</Label>
                                            <Select
                                                value={selectedBlock.delivery_mode}
                                                onValueChange={(v) => updateSelectedBlock({ delivery_mode: v })}
                                                disabled={!canUpdate}
                                            >
                                                <SelectTrigger size="sm" className="bg-background h-8 w-full text-xs">
                                                    <SelectValue />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    <SelectItem value="face_to_face">Face to face</SelectItem>
                                                    <SelectItem value="online_synchronous">Online (Sync)</SelectItem>
                                                    <SelectItem value="online">Online (Async)</SelectItem>
                                                </SelectContent>
                                            </Select>
                                        </div>

                                        <div className="space-y-1">
                                            <Label className="text-[11px]">Room</Label>
                                            <Select
                                                value={selectedBlock.room_id ? String(selectedBlock.room_id) : "none"}
                                                onValueChange={(v) =>
                                                    updateSelectedBlock({ room_id: v === "none" ? null : Number(v) })
                                                }
                                                disabled={!canUpdate || isOnlineDeliveryMode(selectedBlock.delivery_mode)}
                                            >
                                                <SelectTrigger size="sm" className="bg-background h-8 w-full text-xs">
                                                    <SelectValue placeholder="Select room..." />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    <SelectItem value="none">Unassigned</SelectItem>
                                                    {rooms.map((rm) => (
                                                        <SelectItem key={rm.id} value={String(rm.id)}>
                                                            {rm.label}
                                                            {rm.capacity ? ` (Cap ${rm.capacity})` : ""}
                                                        </SelectItem>
                                                    ))}
                                                </SelectContent>
                                            </Select>
                                        </div>
                                    </div>
                                </div>
                            ) : (
                                <div className="border-border text-muted-foreground rounded-lg border border-dashed p-4 text-center text-xs">
                                    No meeting blocks yet. Use <span className="text-foreground font-medium">+ MWF</span>,{" "}
                                    <span className="text-foreground font-medium">+ TTh</span>, or{" "}
                                    <span className="text-foreground font-medium">+ Add Block</span> above to start.
                                </div>
                            )}

                            {/* Smart Conflict Resolver */}
                            {resolutions &&
                            (resolutions.available_rooms.length > 0 ||
                                resolutions.available_slots.length > 0 ||
                                resolutions.can_convert_to_online) ? (
                                <div className="border-border bg-muted/20 space-y-2 rounded-lg border p-3">
                                    <div className="flex items-center gap-1.5 text-xs font-semibold">
                                        <Sparkles className="size-3.5 text-amber-500" aria-hidden="true" />
                                        Smart Conflict Resolver
                                    </div>
                                    <div className="space-y-1.5 text-[11px]">
                                        {resolutions.available_rooms.slice(0, 1).map((room) => (
                                            <div
                                                key={`room-${room.room_id}`}
                                                className="border-border bg-background flex items-center justify-between gap-2 rounded-md border px-2.5 py-1.5"
                                            >
                                                <div className="min-w-0">
                                                    <p className="text-foreground truncate font-medium">
                                                        <MapPin className="mr-1 inline size-3 text-emerald-600" aria-hidden="true" />
                                                        Move room →{" "}
                                                        {String(room.room_name || room.name || `Room #${room.room_id}`)}
                                                        {room.capacity ? ` (Cap ${room.capacity})` : ""}
                                                    </p>
                                                    <p className="text-muted-foreground tabular-nums">
                                                        {room.student_clear_count}/{room.student_clear_count + room.student_clashes_count}{" "}
                                                        students clash-free
                                                    </p>
                                                </div>
                                                {canUpdate && selectedBlock ? (
                                                    <Button
                                                        type="button"
                                                        size="xs"
                                                        variant="outline"
                                                        onClick={() =>
                                                            updateSelectedBlock({
                                                                room_id: room.room_id,
                                                                delivery_mode: "face_to_face",
                                                            })
                                                        }
                                                    >
                                                        Apply
                                                    </Button>
                                                ) : null}
                                            </div>
                                        ))}

                                        {resolutions.available_slots.slice(0, 1).map((slot, si) => (
                                            <div
                                                key={`slot-${si}`}
                                                className="border-border bg-background flex items-center justify-between gap-2 rounded-md border px-2.5 py-1.5"
                                            >
                                                <div className="min-w-0">
                                                    <p className="text-foreground truncate font-medium">
                                                        <Clock className="mr-1 inline size-3 text-indigo-600" aria-hidden="true" />
                                                        Shift time → {slot.day_of_week.slice(0, 3)} {slot.start_time.slice(0, 5)}–
                                                        {slot.end_time.slice(0, 5)}
                                                    </p>
                                                    <p className="text-muted-foreground tabular-nums">
                                                        {slot.student_clashes_count} student clash(es)
                                                        {slot.room_name ? ` · ${slot.room_name}` : ""}
                                                    </p>
                                                </div>
                                                {canUpdate && selectedBlock ? (
                                                    <Button
                                                        type="button"
                                                        size="xs"
                                                        variant="outline"
                                                        onClick={() =>
                                                            updateSelectedBlock({
                                                                day_of_week: slot.day_of_week,
                                                                start_time: slot.start_time.slice(0, 5),
                                                                end_time: slot.end_time.slice(0, 5),
                                                                ...(slot.room_id !== undefined ? { room_id: slot.room_id } : {}),
                                                            })
                                                        }
                                                    >
                                                        Apply
                                                    </Button>
                                                ) : null}
                                            </div>
                                        ))}

                                        {resolutions.can_convert_to_online ? (
                                            <div className="border-border bg-background flex items-center justify-between gap-2 rounded-md border px-2.5 py-1.5">
                                                <div className="min-w-0">
                                                    <p className="text-foreground truncate font-medium">
                                                        <Wifi className="mr-1 inline size-3 text-sky-600" aria-hidden="true" />
                                                        Switch to Online (Synchronous)
                                                    </p>
                                                    <p className="text-muted-foreground tabular-nums">
                                                        Frees physical room · {resolutions.online_student_clashes_count} clash(es)
                                                    </p>
                                                </div>
                                                {canUpdate && selectedBlock ? (
                                                    <Button
                                                        type="button"
                                                        size="xs"
                                                        variant="outline"
                                                        onClick={() =>
                                                            updateSelectedBlock({
                                                                delivery_mode: "online_synchronous",
                                                                room_id: null,
                                                            })
                                                        }
                                                    >
                                                        Switch
                                                    </Button>
                                                ) : null}
                                            </div>
                                        ) : null}
                                    </div>
                                </div>
                            ) : null}

                            {/* Live Enrolled-Student Impact Banner */}
                            <div
                                className={cn(
                                    "rounded-lg border p-3 text-xs",
                                    previewingImpact
                                        ? "border-border bg-muted/30"
                                        : (studentImpact?.clashing_count ?? 0) > 0 ||
                                            roomConflictsCount > 0 ||
                                            facultyConflictsCount > 0
                                          ? "border-rose-500/30 bg-rose-500/10 text-rose-800 dark:text-rose-200"
                                          : "border-emerald-500/30 bg-emerald-500/10 text-emerald-800 dark:text-emerald-200",
                                )}
                            >
                                {previewingImpact ? (
                                    <div className="text-muted-foreground flex items-center gap-2">
                                        <Loader2 className="size-3.5 animate-spin" aria-hidden="true" />
                                        Checking enrolled student timetables &amp; room clashes...
                                    </div>
                                ) : studentImpact ? (
                                    <div className="space-y-1.5">
                                        <div className="flex items-center gap-1.5 font-semibold">
                                            {studentImpact.clashing_count === 0 ? (
                                                <>
                                                    <CheckCircle2 className="size-3.5 shrink-0" aria-hidden="true" />
                                                    <span>
                                                        {studentImpact.clear_count}/{studentImpact.total_enrolled} enrolled
                                                        students clash-free
                                                    </span>
                                                </>
                                            ) : (
                                                <>
                                                    <AlertTriangle className="size-3.5 shrink-0" aria-hidden="true" />
                                                    <span>
                                                        {studentImpact.clashing_count} of {studentImpact.total_enrolled} enrolled
                                                        student(s) have clashes
                                                    </span>
                                                </>
                                            )}
                                        </div>

                                        {roomConflictsCount > 0 || facultyConflictsCount > 0 || overcrowdedCount > 0 ? (
                                            <div className="flex flex-wrap gap-1.5 text-[11px]">
                                                {roomConflictsCount > 0 ? (
                                                    <Badge variant="destructive" className="h-5 text-[10px]">
                                                        {roomConflictsCount} room conflict(s)
                                                    </Badge>
                                                ) : null}
                                                {facultyConflictsCount > 0 ? (
                                                    <Badge variant="destructive" className="h-5 text-[10px]">
                                                        {facultyConflictsCount} faculty conflict(s)
                                                    </Badge>
                                                ) : null}
                                                {overcrowdedCount > 0 ? (
                                                    <Badge variant="destructive" className="h-5 text-[10px]">
                                                        {overcrowdedCount} overcrowded block(s)
                                                    </Badge>
                                                ) : null}
                                            </div>
                                        ) : null}

                                        {studentImpact.clashing_students.length > 0 ? (
                                            <ul className="max-h-24 space-y-0.5 overflow-y-auto pl-4 text-[11px] list-disc">
                                                {studentImpact.clashing_students.slice(0, 6).map((cs, i) => (
                                                    <li key={`${cs.student_id}-${i}`}>
                                                        <span className="font-medium">{cs.name}</span> — {cs.conflicting_subject}
                                                        {cs.conflicting_section ? `-${cs.conflicting_section}` : ""} (
                                                        {cs.conflicting_slot})
                                                    </li>
                                                ))}
                                                {studentImpact.clashing_students.length > 6 ? (
                                                    <li>+{studentImpact.clashing_students.length - 6} more student(s)</li>
                                                ) : null}
                                            </ul>
                                        ) : null}
                                    </div>
                                ) : (
                                    <span className="text-muted-foreground">No enrolled impact data available.</span>
                                )}
                            </div>
                        </div>
                    </ScrollArea>

                    {/* Footer Controls */}
                    {canUpdate ? (
                        <div className="border-border bg-muted/20 space-y-2.5 border-t p-3.5">
                            <label className="flex cursor-pointer items-center gap-2 text-xs">
                                <Checkbox
                                    checked={notifyEnrolled}
                                    onCheckedChange={(v) => setNotifyEnrolled(Boolean(v))}
                                />
                                <span className="text-muted-foreground">
                                    Notify <span className="text-foreground font-medium tabular-nums">{currentSeats}</span>{" "}
                                    enrolled students &amp; faculty upon publish
                                </span>
                            </label>
                            <div className="grid grid-cols-2 gap-2">
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    disabled={savingSchedule !== null}
                                    onClick={() => void handleSaveSchedule("draft")}
                                >
                                    {savingSchedule === "draft" ? (
                                        <Loader2 className="size-3.5 animate-spin" aria-hidden="true" />
                                    ) : null}
                                    Save as Draft
                                </Button>
                                <Button
                                    type="button"
                                    size="sm"
                                    disabled={savingSchedule !== null}
                                    onClick={() => void handleSaveSchedule("published")}
                                >
                                    {savingSchedule === "published" ? (
                                        <Loader2 className="size-3.5 animate-spin" aria-hidden="true" />
                                    ) : (
                                        <Send className="size-3.5" aria-hidden="true" />
                                    )}
                                    Publish Schedule
                                </Button>
                            </div>
                        </div>
                    ) : null}
                </div>
            )}
        </aside>
    );
}
