import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Switch } from "@/components/ui/switch";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table";
import { cn } from "@/lib/utils";
import {
    AlertTriangle,
    Bell,
    Building2,
    Calendar,
    CalendarClock,
    CheckCircle2,
    ChevronDown,
    ChevronUp,
    Clock,
    Loader2,
    Pencil,
    Send,
    Sliders,
    Sparkles,
} from "lucide-react";
import { useCallback, useEffect, useMemo, useRef, useState } from "react";
import { toast } from "sonner";
import { postSendScheduleNotices } from "../crud/api";
import { LiveBusyGrid, type LiveBusyGridBlock } from "../crud/live-busy-grid";
import type { ClassPolicySettings, DossierHealth, DossierScheduleChange } from "../crud/types";
import { deliveryModeLocationLabel, deliveryModeShortLabel, isOnlineDeliveryMode } from "../lib/class-defaults";
import { ScheduleEditor } from "../record/schedule-editor";
import { scheduleDayOrder } from "../record/schedule-utils";
import type { ClassShowData, RawScheduleEntry, RoomOption } from "../record/types";
import { fetchClassWorkspace, patchClassScheduleWorkspace, WorkspaceApiError } from "../workspace/api";
import type { ClassWorkspacePayload, EditableScheduleBlock, RankedRoomResolution } from "../workspace/types";
import { formatShortDateTime, POLICY_TOGGLES } from "./dossier-oversight-tabs";

const QUICK_POLICY_KEYS: ReadonlyArray<keyof ClassPolicySettings> = [
    "enable_grade_visibility",
    "enable_attendance_tracking",
    "allow_late_submissions",
    "notify_students_on_schedule_changes",
];

function formatTimeRange(start?: string | null, end?: string | null): string {
    if (!start || !end) return "TBA";
    const fmt = (raw: string) => {
        const [hRaw, mRaw] = raw.split(":");
        const h = Number(hRaw);
        const m = Number(mRaw ?? 0);
        if (!Number.isFinite(h) || !Number.isFinite(m)) return raw;
        const suffix = h >= 12 ? "PM" : "AM";
        const h12 = h % 12 === 0 ? 12 : h % 12;
        return `${h12}:${String(m).padStart(2, "0")} ${suffix}`;
    };
    return `${fmt(start)} – ${fmt(end)}`;
}

function sortRawSchedules(schedules: RawScheduleEntry[]): RawScheduleEntry[] {
    return [...schedules].sort((a, b) => {
        const dayDiff = scheduleDayOrder.indexOf(a.day_of_week) - scheduleDayOrder.indexOf(b.day_of_week);
        if (dayDiff !== 0) return dayDiff;
        return (a.start_time || "").localeCompare(b.start_time || "");
    });
}

function statusBadgeTone(status: string): string {
    if (status === "published") {
        return "border-emerald-600/30 bg-emerald-500/10 text-emerald-700 dark:text-emerald-300";
    }
    if (status === "draft") {
        return "border-amber-600/30 bg-amber-500/10 text-amber-700 dark:text-amber-300";
    }
    return "border-border bg-muted text-muted-foreground";
}

function toGridBlocks(
    rawSchedules: RawScheduleEntry[],
    classItem: ClassShowData,
    roomsById: Map<number, { label: string; capacity?: number | null }>,
    workspace: ClassWorkspacePayload | null,
): LiveBusyGridBlock[] {
    const wsBlocks = workspace?.schedule_summary?.schedules ?? [];
    if (wsBlocks.length > 0) {
        return wsBlocks.map((b) => ({
            id: b.id,
            day_of_week: b.day_of_week,
            start_time: b.start_time ?? "08:00",
            end_time: b.end_time ?? "09:00",
            room_id: b.room_id,
            room_name: isOnlineDeliveryMode(b.delivery_mode) ? deliveryModeShortLabel(b.delivery_mode) : b.room_name,
            delivery_mode: b.delivery_mode,
            component_type: b.component_type === "lab" ? "lab" : "lecture",
            status: b.status === "draft" ? "draft" : "published",
            has_conflict: b.has_conflict,
            label: b.component_type === "lab" ? "Lab" : "Lec",
        }));
    }

    return rawSchedules.map((s) => ({
        id: s.id,
        day_of_week: s.day_of_week,
        start_time: s.start_time,
        end_time: s.end_time,
        room_id: s.room_id,
        room_name: isOnlineDeliveryMode(s.delivery_mode ?? classItem.delivery_mode)
            ? deliveryModeShortLabel(s.delivery_mode ?? classItem.delivery_mode)
            : (s.room_name ?? (s.room_id ? roomsById.get(s.room_id)?.label : null)),
        delivery_mode: s.delivery_mode ?? classItem.delivery_mode,
        component_type: s.component_type === "lab" ? "lab" : "lecture",
        status: s.status === "draft" ? "draft" : "published",
        label: s.component_type === "lab" ? "Lab" : "Lec",
    }));
}

function useWorkspaceScheduleData(classId: number, autoFetch: boolean, revision: string) {
    const [result, setResult] = useState<{ key: string; workspace: ClassWorkspacePayload } | null>(null);
    const [loading, setLoading] = useState(false);
    const requestId = useRef(0);
    const key = `${classId}:${revision}`;
    const workspace = !loading && result?.key === key ? result.workspace : null;

    const load = useCallback(async () => {
        const current = ++requestId.current;
        setLoading(true);
        try {
            const data = await fetchClassWorkspace(classId, false);
            if (requestId.current === current) setResult({ key, workspace: data });
        } catch {
            // Non-blocking enrichment; base dossier props still render cleanly.
        } finally {
            if (requestId.current === current) setLoading(false);
        }
    }, [classId, key]);

    useEffect(() => {
        if (autoFetch) {
            void load();
        }
        return () => { ++requestId.current; };
    }, [autoFetch, load]);

    return { workspace, loading, reloadWorkspace: load };
}

async function applySuggestedRoomResolution({
    classItem,
    rawSchedules,
    scheduleRevision,
    workspace,
    room,
    scheduleStatus,
    notifyEnrolled,
    onRefresh,
}: {
    classItem: ClassShowData;
    rawSchedules: RawScheduleEntry[];
    scheduleRevision: string;
    workspace: ClassWorkspacePayload;
    room: RankedRoomResolution;
    scheduleStatus: "draft" | "published";
    notifyEnrolled: boolean;
    onRefresh: () => void;
}) {
    const targetScheduleId = workspace.resolutions?.schedule_id;
    const sourceBlocks = workspace.schedule_summary?.schedules?.length
        ? workspace.schedule_summary.schedules.map((s) => ({
              id: s.id,
              day_of_week: s.day_of_week,
              start_time: s.start_time ?? "08:00",
              end_time: s.end_time ?? "09:00",
              room_id: s.room_id,
              delivery_mode: s.delivery_mode,
              component_type: (s.component_type === "lab" ? "lab" : "lecture") as "lecture" | "lab",
              has_conflict: s.has_conflict,
          }))
        : rawSchedules.map((s) => ({
              id: s.id,
              day_of_week: s.day_of_week,
              start_time: s.start_time,
              end_time: s.end_time,
              room_id: s.room_id,
              delivery_mode: s.delivery_mode ?? classItem.delivery_mode,
              component_type: (s.component_type === "lab" ? "lab" : "lecture") as "lecture" | "lab",
              has_conflict: false,
          }));

    const targetIndex = sourceBlocks.findIndex((b) =>
        targetScheduleId ? b.id === targetScheduleId : b.has_conflict,
    );
    if (targetIndex < 0) throw new Error("The conflicting meeting could not be identified. Open the schedule to resolve it.");
    const resolvedIndex = targetIndex;

    const updatedSchedules: EditableScheduleBlock[] = sourceBlocks.map((block, index) => ({
        ...(block.id ? { id: block.id } : {}),
        day_of_week: block.day_of_week,
        start_time: block.start_time,
        end_time: block.end_time,
        room_id: index === resolvedIndex ? room.room_id : block.room_id,
        delivery_mode: index === resolvedIndex ? "face_to_face" : block.delivery_mode,
        component_type: block.component_type,
    }));

    const response = await patchClassScheduleWorkspace(classItem.id, {
        schedules: updatedSchedules,
        schedule_revision: workspace.schedule_revision || scheduleRevision,
        schedule_status: scheduleStatus,
        notify_enrolled: notifyEnrolled,
    });
    toast.success(response.message || `Applied ${room.room_name} to resolve the schedule conflict.`);
    onRefresh();
}

/* ------------------------------------------------------------------ */
/* Right Sticky Operational Rail                                       */
/* ------------------------------------------------------------------ */

export function DossierScheduleRail({
    classItem,
    rawSchedules,
    scheduleRevision,
    rooms,
    health,
    scheduleChanges,
    policyState,
    savingPolicyKey,
    canUpdate,
    onTogglePolicy,
    onOpenScheduleTab,
    onEditSchedule,
    onRefresh,
}: {
    classItem: ClassShowData;
    rawSchedules: RawScheduleEntry[];
    scheduleRevision: string;
    rooms: RoomOption[];
    health: DossierHealth;
    scheduleChanges: DossierScheduleChange[];
    policyState: ClassPolicySettings;
    savingPolicyKey: keyof ClassPolicySettings | null;
    canUpdate: boolean;
    onTogglePolicy: (key: keyof ClassPolicySettings, value: boolean) => void;
    onOpenScheduleTab: () => void;
    onEditSchedule: () => void;
    onRefresh: () => void;
}) {
    const isEditable = canUpdate && !classItem.is_archived;
    const [showBusyGrid, setShowBusyGrid] = useState(false);
    const [applyingFix, setApplyingFix] = useState(false);
    const [sendingNotices, setSendingNotices] = useState(false);

    const hasConflicts = health.schedule.conflict_count > 0;
    const { workspace, loading: workspaceLoading, reloadWorkspace } = useWorkspaceScheduleData(
        classItem.id,
        hasConflicts || showBusyGrid,
        scheduleRevision,
    );

    const roomsById = useMemo(
        () => new Map(rooms.map((r) => [Number(r.id), { label: r.label, capacity: r.capacity }])),
        [rooms],
    );

    const sortedSchedules = useMemo(() => sortRawSchedules(rawSchedules), [rawSchedules]);
    const gridBlocks = useMemo(
        () => toGridBlocks(sortedSchedules, classItem, roomsById, workspace),
        [sortedSchedules, classItem, roomsById, workspace],
    );

    const mixedStatuses = new Set(rawSchedules.map((s) => s.status === "draft" ? "draft" : "published")).size > 1;
    const bestRoom = mixedStatuses ? null : workspace?.resolutions?.available_rooms?.[0] ?? null;
    const quickPolicies = useMemo(
        () => POLICY_TOGGLES.filter((p) => QUICK_POLICY_KEYS.includes(p.key)),
        [],
    );

    const handleApplyRoom = async () => {
        if (!workspace || !bestRoom) return;
        setApplyingFix(true);
        try {
            await applySuggestedRoomResolution({
                classItem,
                rawSchedules: sortedSchedules,
                scheduleRevision,
                workspace,
                room: bestRoom,
                scheduleStatus: health.schedule.status === "draft" ? "draft" : "published",
                notifyEnrolled: policyState.notify_students_on_schedule_changes,
                onRefresh: () => {
                    onRefresh();
                    void reloadWorkspace();
                },
            });
        } catch (error) {
            toast.error(error instanceof Error ? error.message : "Could not apply suggested room.");
        } finally {
            setApplyingFix(false);
        }
    };

    const handleSendNotices = async () => {
        setSendingNotices(true);
        try {
            const response = await postSendScheduleNotices(classItem.id);
            const message = response.message || `Queued ${response.count - response.failed} notice(s) across ${response.recipients} delivery channel(s).`;
            if (response.failed > 0) toast.warning(message);
            else toast.success(message);
            onRefresh();
        } catch (error) {
            toast.error(error instanceof WorkspaceApiError ? error.message : "Could not send schedule notices.");
        } finally {
            setSendingNotices(false);
        }
    };

    return (
        <aside className="flex flex-col gap-4 xl:sticky xl:top-6" aria-label="Operational Rail">
            {/* Weekly Schedule Card */}
            <section className="bg-card overflow-hidden rounded-xl border">
                <div className="bg-muted/30 flex items-center justify-between gap-2 border-b px-4 py-3">
                    <div className="flex items-center gap-2">
                        <Calendar className="text-primary size-4" aria-hidden="true" />
                        <h2 className="text-sm font-semibold">Weekly Schedule</h2>
                    </div>
                    <div className="flex items-center gap-1.5">
                        <Badge variant="outline" className={cn("text-[11px] capitalize", statusBadgeTone(health.schedule.status))}>
                            {health.schedule.status}
                        </Badge>
                        <Badge variant="secondary" className="font-mono text-[11px]">
                            {health.schedule.revision_label}
                        </Badge>
                    </div>
                </div>

                <div className="space-y-3 p-4">
                    {/* Conflict Alert + 1-click room fix */}
                    {hasConflicts && (
                        <div className="rounded-lg border border-rose-500/30 bg-rose-500/10 p-3 text-xs">
                            <div className="flex items-start gap-2 text-rose-700 dark:text-rose-300">
                                <AlertTriangle className="mt-0.5 size-4 shrink-0" aria-hidden="true" />
                                <div className="min-w-0 space-y-1">
                                    <p className="font-semibold">
                                        {health.schedule.conflict_count}{" "}
                                        {health.schedule.conflict_count === 1 ? "Schedule Conflict" : "Schedule Conflicts"}
                                    </p>
                                    <p className="leading-snug opacity-90">Review the affected meetings and resolve their overlaps.</p>
                                    {health.schedule.conflict_summary && (
                                        <details className="pt-1">
                                            <summary className="cursor-pointer font-medium">Show conflict details</summary>
                                            <p className="mt-1 break-words leading-relaxed">{health.schedule.conflict_summary}</p>
                                        </details>
                                    )}
                                    <button type="button" onClick={onOpenScheduleTab} className="mt-1 underline underline-offset-2">
                                        Review schedule
                                    </button>
                                </div>
                            </div>
                            {mixedStatuses && isEditable && (
                                <p className="mt-2 text-rose-700 dark:text-rose-300">This class has both draft and published meetings. Edit the schedule to resolve conflicts without changing their statuses.</p>
                            )}
                            {isEditable && bestRoom && (
                                <Button
                                    type="button"
                                    size="sm"
                                    disabled={applyingFix}
                                    onClick={handleApplyRoom}
                                    className="mt-2.5 w-full justify-center gap-1.5"
                                >
                                    {applyingFix ? (
                                        <Loader2 className="size-3.5 animate-spin" aria-hidden="true" />
                                    ) : (
                                        <Sparkles className="size-3.5" aria-hidden="true" />
                                    )}
                                    Move to {bestRoom.room_name}
                                    {bestRoom.capacity ? ` (${bestRoom.capacity} cap)` : ""}
                                </Button>
                            )}
                            {isEditable && !bestRoom && workspaceLoading && (
                                <p className="text-muted-foreground mt-2 flex items-center gap-1.5 text-[11px]">
                                    <Loader2 className="size-3 animate-spin" aria-hidden="true" />
                                    Finding conflict-free room suggestions…
                                </p>
                            )}
                        </div>
                    )}

                    {/* Concise Day / Time / Room list */}
                    {sortedSchedules.length === 0 ? (
                        <div className="bg-muted/20 rounded-lg border border-dashed px-3 py-5 text-center">
                            <p className="text-xs font-medium">No meetings scheduled yet</p>
                            <p className="text-muted-foreground mt-0.5 text-[11px]">Add recurring lecture or lab blocks.</p>
                        </div>
                    ) : (
                        <ul className="space-y-2">
                            {sortedSchedules.map((block, index) => {
                                const mode = block.delivery_mode ?? classItem.delivery_mode;
                                const isLab = block.component_type === "lab";
                                const roomLabel = deliveryModeLocationLabel(
                                    mode,
                                    block.room_name ?? (block.room_id ? roomsById.get(block.room_id)?.label : null),
                                );
                                const wsMatch = workspace?.schedule_summary?.schedules?.find((s) => s.id === block.id);
                                const blockHasConflict = Boolean(wsMatch?.has_conflict);

                                return (
                                    <li
                                        key={block.id ?? `${block.day_of_week}-${block.start_time}-${index}`}
                                        className={cn(
                                            "rounded-lg border px-3 py-2 text-xs",
                                            blockHasConflict
                                                ? "border-rose-500/40 bg-rose-500/5"
                                                : "bg-muted/25 border-border",
                                        )}
                                    >
                                        <div className="flex items-center justify-between gap-2">
                                            <span className="font-semibold">
                                                {block.day_of_week.slice(0, 3)}{" "}
                                                <span className="font-mono font-medium">
                                                    {formatTimeRange(block.start_time, block.end_time)}
                                                </span>
                                            </span>
                                            <Badge
                                                variant="outline"
                                                className={cn(
                                                    "h-5 px-1.5 text-[10px] font-semibold uppercase",
                                                    isLab
                                                        ? "border-emerald-600/35 bg-emerald-500/10 text-emerald-700 dark:text-emerald-300"
                                                        : "border-primary/35 bg-primary/10 text-primary",
                                                )}
                                            >
                                                {isLab ? "Lab" : "Lec"}
                                            </Badge>
                                        </div>
                                        <div className="text-muted-foreground mt-1 flex items-center justify-between gap-2 text-[11px]">
                                            <span className="inline-flex min-w-0 items-center gap-1 truncate">
                                                <Building2 className="size-3 shrink-0" aria-hidden="true" />
                                                <span className="truncate">{roomLabel}</span>
                                            </span>
                                            {blockHasConflict && (
                                                <span className="shrink-0 font-semibold text-rose-600 dark:text-rose-400">
                                                    Clash
                                                </span>
                                            )}
                                        </div>
                                    </li>
                                );
                            })}
                        </ul>
                    )}

                    {/* Expandable Compact Busy Grid */}
                    <div className="space-y-2 border-t pt-2.5">
                        <Button
                            type="button"
                            variant="ghost"
                            size="sm"
                            className="w-full justify-between text-xs"
                            onClick={() => setShowBusyGrid((open) => !open)}
                        >
                            <span>{showBusyGrid ? "Hide Compact Busy Grid" : "Show Compact Busy Grid"}</span>
                            {showBusyGrid ? <ChevronUp className="size-3.5" /> : <ChevronDown className="size-3.5" />}
                        </Button>
                        {showBusyGrid && (
                            <div className="pt-1">
                                <LiveBusyGrid
                                    compact
                                    schedules={gridBlocks}
                                    busyOverlay={workspace?.busy_overlay}
                                    roomsById={roomsById}
                                />
                            </div>
                        )}
                    </div>

                    <div className="grid grid-cols-2 gap-2 pt-0.5">
                        <Button type="button" variant="outline" size="sm" onClick={onOpenScheduleTab}>
                            <CalendarClock className="size-3.5" aria-hidden="true" />
                            Full View
                        </Button>
                        {isEditable && (
                            <Button type="button" variant="outline" size="sm" onClick={onEditSchedule}>
                                <Pencil className="size-3.5" aria-hidden="true" />
                                Edit Schedule
                            </Button>
                        )}
                    </div>
                </div>
            </section>

            {/* Schedule History & Pending Notices Card */}
            <section className="bg-card overflow-hidden rounded-xl border">
                <div className="bg-muted/30 flex items-center justify-between gap-2 border-b px-4 py-3">
                    <div className="flex items-center gap-2">
                        <Bell className="text-primary size-4" aria-hidden="true" />
                        <h2 className="text-sm font-semibold">History &amp; Notices</h2>
                    </div>
                    {health.schedule.pending_notices > 0 && (
                        <Badge
                            variant="outline"
                            className="border-amber-600/35 bg-amber-500/10 text-[11px] text-amber-700 tabular-nums dark:text-amber-300"
                        >
                            {health.schedule.pending_notices} pending
                        </Badge>
                    )}
                </div>
                <div className="space-y-2.5 p-4">
                    {scheduleChanges.length === 0 ? (
                        <p className="text-muted-foreground text-xs">No recent schedule revisions recorded.</p>
                    ) : (
                        <ul className="space-y-2">
                            {scheduleChanges.slice(0, 3).map((change) => (
                                <li
                                    key={change.id}
                                    className={cn(
                                        "rounded-lg border px-2.5 py-2 text-xs",
                                        change.pending_count > 0
                                            ? "border-amber-500/35 bg-amber-500/10"
                                            : "bg-muted/25 border-border",
                                    )}
                                >
                                    <p className="leading-snug font-medium">{change.summary}</p>
                                    <div className="text-muted-foreground mt-1 flex items-center justify-between gap-2 text-[11px]">
                                        <span className="truncate">{change.changed_by || "System"}</span>
                                        <span className="shrink-0 tabular-nums">{formatShortDateTime(change.changed_at)}</span>
                                    </div>
                                </li>
                            ))}
                        </ul>
                    )}

                    {health.schedule.pending_notices > 0 && isEditable && (
                        <Button
                            type="button"
                            size="sm"
                            variant="outline"
                            disabled={sendingNotices}
                            onClick={handleSendNotices}
                            className="w-full gap-1.5 border-amber-600/40 bg-amber-500/10 text-amber-800 hover:bg-amber-500/20 dark:text-amber-300"
                        >
                            {sendingNotices ? (
                                <Loader2 className="size-3.5 animate-spin" aria-hidden="true" />
                            ) : (
                                <Send className="size-3.5" aria-hidden="true" />
                            )}
                            Send Pending Notices ({health.schedule.pending_notices})
                        </Button>
                    )}
                </div>
            </section>

            {/* Quick Policy Toggles Card */}
            <section className="bg-card overflow-hidden rounded-xl border">
                <div className="bg-muted/30 flex items-center justify-between gap-2 border-b px-4 py-3">
                    <div className="flex items-center gap-2">
                        <Sliders className="text-primary size-4" aria-hidden="true" />
                        <h2 className="text-sm font-semibold">Quick Policies</h2>
                    </div>
                    {!isEditable && (
                        <span className="text-muted-foreground text-[11px]">Read-only</span>
                    )}
                </div>
                <div className="grid grid-cols-1 gap-2 p-3.5 sm:grid-cols-2 xl:grid-cols-1">
                    {quickPolicies.map((policy) => {
                        const checked = Boolean(policyState[policy.key]);
                        return (
                            <div
                                key={policy.key}
                                className="bg-muted/25 flex items-center justify-between gap-2 rounded-lg border px-3 py-2"
                            >
                                <label
                                    htmlFor={`rail-policy-${policy.key}`}
                                    className="cursor-pointer text-xs font-medium"
                                >
                                    {policy.shortLabel}
                                </label>
                                <Switch
                                    id={`rail-policy-${policy.key}`}
                                    checked={checked}
                                    disabled={!isEditable || savingPolicyKey !== null}
                                    onCheckedChange={(next) => onTogglePolicy(policy.key, next)}
                                    aria-label={policy.label}
                                />
                            </div>
                        );
                    })}
                </div>
            </section>
        </aside>
    );
}

/* ------------------------------------------------------------------ */
/* Full Schedule & History Tab                                         */
/* ------------------------------------------------------------------ */

export function DossierScheduleTab({
    classItem,
    rawSchedules,
    scheduleRevision,
    rooms,
    health,
    scheduleChanges,
    notifyEnrolled,
    canUpdate,
    scheduleEditing,
    onSetScheduleEditing,
    onRefresh,
}: {
    classItem: ClassShowData;
    rawSchedules: RawScheduleEntry[];
    scheduleRevision: string;
    rooms: RoomOption[];
    health: DossierHealth;
    scheduleChanges: DossierScheduleChange[];
    notifyEnrolled: boolean;
    canUpdate: boolean;
    scheduleEditing: boolean;
    onSetScheduleEditing: (editing: boolean) => void;
    onRefresh: () => void;
}) {
    const isEditable = canUpdate && !classItem.is_archived;
    const { workspace, reloadWorkspace } = useWorkspaceScheduleData(classItem.id, true, scheduleRevision);
    const [applyingFix, setApplyingFix] = useState(false);
    const [sendingNotices, setSendingNotices] = useState(false);

    const roomsById = useMemo(
        () => new Map(rooms.map((r) => [Number(r.id), { label: r.label, capacity: r.capacity }])),
        [rooms],
    );

    const sortedSchedules = useMemo(() => sortRawSchedules(rawSchedules), [rawSchedules]);
    const gridBlocks = useMemo(
        () => toGridBlocks(sortedSchedules, classItem, roomsById, workspace),
        [sortedSchedules, classItem, roomsById, workspace],
    );

    const mixedStatuses = new Set(rawSchedules.map((s) => s.status === "draft" ? "draft" : "published")).size > 1;
    const bestRoom = mixedStatuses ? null : workspace?.resolutions?.available_rooms?.[0] ?? null;

    if (scheduleEditing && isEditable) {
        return (
            <ScheduleEditor
                classItem={classItem}
                initialSchedules={rawSchedules}
                revision={scheduleRevision}
                rooms={rooms}
                onClose={() => {
                    onSetScheduleEditing(false);
                    onRefresh();
                    void reloadWorkspace();
                }}
            />
        );
    }

    const handleApplyFix = async () => {
        if (!workspace || !bestRoom) return;
        setApplyingFix(true);
        try {
            await applySuggestedRoomResolution({
                classItem,
                rawSchedules: sortedSchedules,
                scheduleRevision,
                workspace,
                room: bestRoom,
                scheduleStatus: health.schedule.status === "draft" ? "draft" : "published",
                notifyEnrolled,
                onRefresh: () => {
                    onRefresh();
                    void reloadWorkspace();
                },
            });
        } catch (error) {
            toast.error(error instanceof Error ? error.message : "Could not apply suggested room.");
        } finally {
            setApplyingFix(false);
        }
    };

    const handleSendNotices = async () => {
        setSendingNotices(true);
        try {
            const response = await postSendScheduleNotices(classItem.id);
            toast.success(response.message || `Sent ${response.count} notice(s) to ${response.recipients} student(s).`);
            onRefresh();
        } catch (error) {
            toast.error(error instanceof WorkspaceApiError ? error.message : "Could not send pending schedule notices.");
        } finally {
            setSendingNotices(false);
        }
    };

    return (
        <div className="flex flex-col gap-5">
            {/* Busy Grid & Block Breakdown */}
            <section className="bg-card overflow-hidden rounded-xl border">
                <div className="bg-muted/30 flex flex-wrap items-center justify-between gap-3 border-b px-4 py-3 sm:px-5">
                    <div className="space-y-0.5">
                        <div className="flex flex-wrap items-center gap-2">
                            <Clock className="text-primary size-4" aria-hidden="true" />
                            <h2 className="text-sm font-semibold">Weekly Timetable &amp; Busy Overlay</h2>
                            <Badge variant="outline" className={cn("text-[11px] capitalize", statusBadgeTone(health.schedule.status))}>
                                {health.schedule.status}
                            </Badge>
                            <Badge variant="secondary" className="font-mono text-[11px]">
                                {health.schedule.revision_label}
                            </Badge>
                        </div>
                        <p className="text-muted-foreground text-xs tabular-nums">
                            {health.schedule.scheduled_hours.toFixed(1)}h
                            {health.schedule.required_hours ? ` / ${health.schedule.required_hours.toFixed(1)}h required` : " scheduled"} ·{" "}
                            {health.schedule.conflict_count > 0
                                ? `${health.schedule.conflict_count} conflict(s) detected`
                                : "All clear"}
                        </p>
                    </div>
                    <div className="flex flex-wrap items-center gap-2">
                        {isEditable && health.schedule.conflict_count > 0 && bestRoom && (
                            <Button type="button" size="sm" disabled={applyingFix} onClick={handleApplyFix}>
                                {applyingFix ? <Loader2 className="size-3.5 animate-spin" /> : <Sparkles className="size-3.5" />}
                                Apply {bestRoom.room_name}
                            </Button>
                        )}
                        {isEditable && (
                            <Button type="button" variant="outline" size="sm" onClick={() => onSetScheduleEditing(true)}>
                                <Pencil className="size-3.5" aria-hidden="true" />
                                Edit Schedule
                            </Button>
                        )}
                    </div>
                </div>

                <div className="space-y-5 p-4 sm:p-5">
                    <LiveBusyGrid
                        schedules={gridBlocks}
                        busyOverlay={workspace?.busy_overlay}
                        roomsById={roomsById}
                    />

                    {/* Full Schedule Block Table */}
                    <div className="overflow-hidden rounded-lg border">
                        <Table>
                            <TableHeader className="bg-muted/30">
                                <TableRow>
                                    <TableHead className="px-3 text-xs">Day</TableHead>
                                    <TableHead className="px-3 text-xs">Time</TableHead>
                                    <TableHead className="px-3 text-xs">Component</TableHead>
                                    <TableHead className="px-3 text-xs">Delivery</TableHead>
                                    <TableHead className="px-3 text-xs">Room &amp; Fit</TableHead>
                                    <TableHead className="px-3 text-right text-xs">Status</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {sortedSchedules.length === 0 ? (
                                    <TableRow>
                                        <TableCell colSpan={6} className="text-muted-foreground py-8 text-center text-xs">
                                            No weekly schedule blocks configured yet.
                                        </TableCell>
                                    </TableRow>
                                ) : (
                                    sortedSchedules.map((block, index) => {
                                        const mode = block.delivery_mode ?? classItem.delivery_mode;
                                        const isOnline = isOnlineDeliveryMode(mode);
                                        const roomMeta = block.room_id ? roomsById.get(block.room_id) : null;
                                        const roomName = block.room_name ?? roomMeta?.label ?? null;
                                        const wsBlock = workspace?.schedule_summary?.schedules?.find((s) => s.id === block.id);
                                        const capacity = wsBlock?.room_capacity ?? roomMeta?.capacity ?? null;
                                        const overcrowded =
                                            Boolean(wsBlock?.is_overcrowded) ||
                                            (capacity !== null && classItem.maximum_slots > 0 && capacity < classItem.maximum_slots);
                                        const blockStatus = wsBlock?.status ?? block.status ?? health.schedule.status;

                                        return (
                                            <TableRow key={block.id ?? `${block.day_of_week}-${index}`}>
                                                <TableCell className="px-3 py-2.5 text-xs font-semibold">{block.day_of_week}</TableCell>
                                                <TableCell className="px-3 py-2.5 font-mono text-xs tabular-nums">
                                                    {formatTimeRange(block.start_time, block.end_time)}
                                                </TableCell>
                                                <TableCell className="px-3 py-2.5">
                                                    <Badge variant="outline" className="text-[11px] capitalize">
                                                        {block.component_type === "lab" ? "Lab" : "Lecture"}
                                                    </Badge>
                                                </TableCell>
                                                <TableCell className="text-muted-foreground px-3 py-2.5 text-xs">
                                                    {deliveryModeShortLabel(mode)}
                                                </TableCell>
                                                <TableCell className="px-3 py-2.5 text-xs">
                                                    {isOnline ? (
                                                        <span className="text-muted-foreground">Virtual / Online</span>
                                                    ) : (
                                                        <div className="flex flex-wrap items-center gap-1.5">
                                                            <span className="font-medium">{roomName || "Unassigned"}</span>
                                                            {capacity !== null && (
                                                                <Badge
                                                                    variant="outline"
                                                                    className={cn(
                                                                        "text-[10px] tabular-nums",
                                                                        overcrowded
                                                                            ? "border-rose-500/40 bg-rose-500/10 text-rose-700 dark:text-rose-300"
                                                                            : "border-emerald-600/30 bg-emerald-500/10 text-emerald-700 dark:text-emerald-300",
                                                                    )}
                                                                >
                                                                    {capacity} cap {overcrowded ? "· Tight" : "· Fits"}
                                                                </Badge>
                                                            )}
                                                        </div>
                                                    )}
                                                </TableCell>
                                                <TableCell className="px-3 py-2.5 text-right">
                                                    <Badge variant="outline" className={cn("text-[11px] capitalize", statusBadgeTone(blockStatus))}>
                                                        {blockStatus}
                                                    </Badge>
                                                </TableCell>
                                            </TableRow>
                                        );
                                    })
                                )}
                            </TableBody>
                        </Table>
                    </div>
                </div>
            </section>

            {/* Full Schedule Change History */}
            <section className="bg-card overflow-hidden rounded-xl border">
                <div className="bg-muted/30 flex flex-wrap items-center justify-between gap-3 border-b px-4 py-3 sm:px-5">
                    <div className="space-y-0.5">
                        <h3 className="text-sm font-semibold">Schedule Change History &amp; Notifications</h3>
                        <p className="text-muted-foreground text-xs">
                            {scheduleChanges.length} logged {scheduleChanges.length === 1 ? "revision" : "revisions"} ·{" "}
                            {health.schedule.pending_notices} pending student notice(s)
                        </p>
                    </div>
                    {isEditable && (
                        <Button
                            type="button"
                            size="sm"
                            variant={health.schedule.pending_notices > 0 ? "default" : "outline"}
                            disabled={sendingNotices || health.schedule.pending_notices === 0}
                            onClick={handleSendNotices}
                        >
                            {sendingNotices ? <Loader2 className="size-3.5 animate-spin" /> : <Send className="size-3.5" />}
                            Send Pending Notices
                            {health.schedule.pending_notices > 0 ? ` (${health.schedule.pending_notices})` : ""}
                        </Button>
                    )}
                </div>

                {scheduleChanges.length === 0 ? (
                    <div className="flex flex-col items-center justify-center gap-1.5 px-6 py-10 text-center">
                        <CheckCircle2 className="text-muted-foreground/60 size-7" aria-hidden="true" />
                        <p className="text-sm font-medium">No schedule changes logged</p>
                        <p className="text-muted-foreground text-xs">
                            Schedule edits and student notice dispatches will appear here.
                        </p>
                    </div>
                ) : (
                    <div className="overflow-x-auto">
                        <Table>
                            <TableHeader className="bg-muted/30">
                                <TableRow>
                                    <TableHead className="px-4 text-xs">When</TableHead>
                                    <TableHead className="px-4 text-xs">Change Summary</TableHead>
                                    <TableHead className="px-4 text-xs">Changed By</TableHead>
                                    <TableHead className="px-4 text-right text-xs">Notices</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {scheduleChanges.map((change) => (
                                    <TableRow key={change.id}>
                                        <TableCell className="text-muted-foreground px-4 py-3 text-xs whitespace-nowrap tabular-nums">
                                            {formatShortDateTime(change.changed_at)}
                                        </TableCell>
                                        <TableCell className="px-4 py-3 text-sm">{change.summary}</TableCell>
                                        <TableCell className="px-4 py-3 text-xs font-medium">
                                            {change.changed_by || "System"}
                                        </TableCell>
                                        <TableCell className="px-4 py-3 text-right">
                                            <div className="inline-flex items-center gap-1.5">
                                                <Badge variant="outline" className="text-[11px] tabular-nums">
                                                    {change.notified_count} sent
                                                </Badge>
                                                {change.pending_count > 0 && (
                                                    <Badge
                                                        variant="outline"
                                                        className="border-amber-600/35 bg-amber-500/10 text-[11px] text-amber-700 tabular-nums dark:text-amber-300"
                                                    >
                                                        {change.pending_count} pending
                                                    </Badge>
                                                )}
                                            </div>
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </div>
                )}
            </section>
        </div>
    );
}
