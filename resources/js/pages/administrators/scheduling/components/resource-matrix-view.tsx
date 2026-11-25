import { Badge } from "@/components/ui/badge";
import { AlertTriangle, Building2, CheckCircle2, GraduationCap, Loader2, Sparkles, UserCheck, Users } from "lucide-react";
import { useCallback, useEffect, useMemo, useRef, useState } from "react";
import { canonicalDeliveryMode, isOnlineDeliveryMode } from "../../classes/lib/class-defaults";
import type { ClassScheduleData, FacultyOption, RoomOption, ScheduleEntry } from "../../components/scheduling-types";
import type { MatrixPivot, ScheduleStudioController } from "../hooks/use-schedule-studio";
import { simulatePlacementConflicts, type SimulationResult } from "../lib/conflict-engine";
import { resolveBlockColor } from "../lib/schedule-colors";
import {
    clampMinutes,
    DEFAULT_END_HOUR,
    DEFAULT_START_HOUR,
    formatTimeRange,
    MIN_DURATION_MINUTES,
    minutesToApiTime,
    minutesToShortHour,
    packOverlappingBlocks,
    parseTimeToMinutes,
    SNAP_MINUTES,
    snapMinutes,
} from "../lib/time-geometry";
import SessionInspectorPopover from "./session-inspector-popover";

type ResourceMatrixViewProps = {
    studio: ScheduleStudioController;
    rooms: RoomOption[];
    faculty: FacultyOption[];
};

type MatrixRow = {
    id: string;
    label: string;
    sublabel: string;
    roomId?: number | null;
    capacity?: number;
    overcrowded?: boolean;
    blocks: Array<{
        key: string;
        classItem: ClassScheduleData;
        session: ScheduleEntry;
        startMin: number;
        endMin: number;
    }>;
};

type ActiveMatrixDrag = {
    classItem: ClassScheduleData;
    session: ScheduleEntry;
    originClientX: number;
    originClientY: number;
    initialStartMin: number;
    initialEndMin: number;
    initialRowId: string;
    pointerOffsetMin: number;
    hasMoved: boolean;
    targetRowId: string;
    targetRoomId: number | null;
    targetStartMin: number;
    targetEndMin: number;
};

const START_MIN = DEFAULT_START_HOUR * 60;
const END_MIN = DEFAULT_END_HOUR * 60;
const TOTAL_MINS = END_MIN - START_MIN;
const HOURS = Array.from({ length: DEFAULT_END_HOUR - DEFAULT_START_HOUR }, (_, i) => i + DEFAULT_START_HOUR);

const PIVOTS: Array<{ value: MatrixPivot; label: string; icon: typeof Building2 }> = [
    { value: "room", label: "By Room", icon: Building2 },
    { value: "faculty", label: "By Instructor", icon: UserCheck },
    { value: "cohort", label: "By Cohort", icon: GraduationCap },
];

export default function ResourceMatrixView({ studio, rooms, faculty }: ResourceMatrixViewProps) {
    const timelineRef = useRef<HTMLDivElement | null>(null);
    const [activeDrag, setActiveDrag] = useState<ActiveMatrixDrag | null>(null);

    const days = studio.visibleDays;
    const activeDay = days.includes(studio.matrixDay) ? studio.matrixDay : days[0];

    // Count sessions & conflicts per day for the day switcher badges
    const dayCounts = useMemo(() => {
        const counts = new Map<string, { total: number; conflicts: number }>();
        for (const day of days) counts.set(day, { total: 0, conflicts: 0 });
        for (const cls of studio.visibleClasses) {
            for (const s of cls.schedules) {
                const entry = counts.get(s.day_of_week);
                if (!entry) continue;
                entry.total++;
                if (s.id !== undefined && studio.conflictScheduleIdSet.has(s.id)) {
                    entry.conflicts++;
                }
            }
        }
        return counts;
    }, [days, studio.visibleClasses, studio.conflictScheduleIdSet]);

    // Build rows based on active pivot
    const rows: MatrixRow[] = useMemo(() => {
        const daySessions: Array<{
            key: string;
            classItem: ClassScheduleData;
            session: ScheduleEntry;
            startMin: number;
            endMin: number;
        }> = [];

        for (const classItem of studio.visibleClasses) {
            for (const session of classItem.schedules) {
                if (session.day_of_week !== activeDay) continue;
                const startMin = clampMinutes(parseTimeToMinutes(session.start_time), START_MIN, END_MIN - MIN_DURATION_MINUTES);
                const endMin = clampMinutes(parseTimeToMinutes(session.end_time), startMin + MIN_DURATION_MINUTES, END_MIN);
                daySessions.push({
                    key: `${classItem.id}:${session.id ?? startMin}`,
                    classItem,
                    session,
                    startMin,
                    endMin,
                });
            }
        }

        if (studio.matrixPivot === "room") {
            const roomRows: MatrixRow[] = rooms.map((room) => {
                const blocks = daySessions.filter(
                    (b) => !isOnlineDeliveryMode(b.session.delivery_mode ?? b.classItem.delivery_mode) && b.session.room_id === room.id,
                );
                const cap = room.capacity ?? 0;
                const overcrowded = cap > 0 && blocks.some((b) => b.classItem.student_count > cap);
                return {
                    id: `room:${room.id}`,
                    label: room.name,
                    sublabel: [room.building, room.type, cap > 0 ? `Cap ${cap}` : null].filter(Boolean).join(" · "),
                    roomId: room.id,
                    capacity: cap,
                    overcrowded,
                    blocks,
                };
            });

            const onlineBlocks = daySessions.filter((b) =>
                isOnlineDeliveryMode(b.session.delivery_mode ?? b.classItem.delivery_mode),
            );
            const unassignedBlocks = daySessions.filter(
                (b) => !isOnlineDeliveryMode(b.session.delivery_mode ?? b.classItem.delivery_mode) && !b.session.room_id,
            );

            if (unassignedBlocks.length > 0) {
                roomRows.unshift({
                    id: "room:unassigned",
                    label: "Unassigned Room",
                    sublabel: "Drag onto any room row below",
                    roomId: null,
                    blocks: unassignedBlocks,
                });
            }
            if (onlineBlocks.length > 0) {
                roomRows.push({
                    id: "room:online",
                    label: "Online / Virtual",
                    sublabel: "No physical room reserved",
                    roomId: null,
                    blocks: onlineBlocks,
                });
            }
            return roomRows;
        }

        if (studio.matrixPivot === "faculty") {
            const grouped = new Map<string, MatrixRow>();
            for (const f of faculty) {
                const blocks = daySessions.filter((b) => b.classItem.faculty_id === f.id);
                if (blocks.length > 0 || studio.viewFilters.faculty === f.id) {
                    grouped.set(f.id, {
                        id: `faculty:${f.id}`,
                        label: f.name,
                        sublabel: f.department ?? "Faculty",
                        blocks,
                    });
                }
            }
            const unassigned = daySessions.filter((b) => !b.classItem.faculty_id);
            if (unassigned.length > 0) {
                grouped.set("unassigned", {
                    id: "faculty:unassigned",
                    label: "Unassigned Instructor",
                    sublabel: "Needs faculty assignment",
                    blocks: unassigned,
                });
            }
            return [...grouped.values()].sort((a, b) => b.blocks.length - a.blocks.length || a.label.localeCompare(b.label));
        }

        // Cohort pivot: Program + Year + Section
        const cohortMap = new Map<string, MatrixRow>();
        for (const block of daySessions) {
            const cohortKey = `${block.classItem.courses || block.classItem.classification || "General"} · ${block.classItem.grade_level ?? "All"} · Sec ${block.classItem.section}`;
            const existing = cohortMap.get(cohortKey);
            if (existing) {
                existing.blocks.push(block);
            } else {
                cohortMap.set(cohortKey, {
                    id: `cohort:${cohortKey}`,
                    label: `${block.classItem.courses || "Cohort"} · Sec ${block.classItem.section}`,
                    sublabel: block.classItem.grade_level ?? "General",
                    blocks: [block],
                });
            }
        }
        return [...cohortMap.values()].sort((a, b) => a.label.localeCompare(b.label));
    }, [studio.visibleClasses, studio.matrixPivot, studio.viewFilters.faculty, activeDay, rooms, faculty]);

    // Live simulation during matrix drag
    const dragSimulation: SimulationResult | null = useMemo(() => {
        if (!activeDrag || !activeDrag.hasMoved) return null;
        return simulatePlacementConflicts(
            {
                scheduleId: activeDrag.session.id,
                classItem: activeDrag.classItem,
                day_of_week: activeDay,
                startMin: activeDrag.targetStartMin,
                endMin: activeDrag.targetEndMin,
                room_id: studio.matrixPivot === "room" ? activeDrag.targetRoomId : (activeDrag.session.room_id ?? null),
                delivery_mode: canonicalDeliveryMode(activeDrag.session.delivery_mode ?? activeDrag.classItem.delivery_mode),
            },
            studio.classes,
            studio.policy,
        );
    }, [activeDrag, activeDay, studio.matrixPivot, studio.classes, studio.policy]);

    const resolveRowAtY = useCallback(
        (clientY: number): MatrixRow | null => {
            const rowNodes = timelineRef.current?.querySelectorAll<HTMLElement>("[data-matrix-row-id]");
            if (!rowNodes) return null;
            for (const node of rowNodes) {
                const rect = node.getBoundingClientRect();
                if (clientY >= rect.top && clientY <= rect.bottom) {
                    const id = node.dataset.matrixRowId;
                    return rows.find((r) => r.id === id) ?? null;
                }
            }
            return null;
        },
        [rows],
    );

    useEffect(() => {
        if (!activeDrag) return;

        const handlePointerMove = (event: PointerEvent) => {
            const dx = event.clientX - activeDrag.originClientX;
            const dy = event.clientY - activeDrag.originClientY;
            const movedEnough = activeDrag.hasMoved || Math.hypot(dx, dy) > 5;
            if (!movedEnough) return;

            const timeline = timelineRef.current?.querySelector<HTMLElement>("[data-matrix-track]");
            if (!timeline) return;
            const rect = timeline.getBoundingClientRect();
            const relX = Math.max(0, Math.min(rect.width, event.clientX - rect.left));
            const pointerMin = START_MIN + (relX / rect.width) * TOTAL_MINS;
            const duration = activeDrag.initialEndMin - activeDrag.initialStartMin;
            const targetStartMin = clampMinutes(snapMinutes(pointerMin - activeDrag.pointerOffsetMin, SNAP_MINUTES), START_MIN, END_MIN - duration);
            const targetEndMin = targetStartMin + duration;

            let targetRowId = activeDrag.targetRowId;
            let targetRoomId = activeDrag.targetRoomId;

            if (studio.matrixPivot === "room" && !isOnlineDeliveryMode(activeDrag.session.delivery_mode ?? activeDrag.classItem.delivery_mode)) {
                const hoveredRow = resolveRowAtY(event.clientY);
                if (hoveredRow && hoveredRow.id !== "room:online") {
                    targetRowId = hoveredRow.id;
                    targetRoomId = hoveredRow.roomId ?? null;
                }
            }

            setActiveDrag((prev) =>
                prev
                    ? { ...prev, hasMoved: true, targetStartMin, targetEndMin, targetRowId, targetRoomId }
                    : null,
            );
        };

        const handlePointerUp = () => {
            const drag = activeDrag;
            setActiveDrag(null);

            if (!drag.hasMoved) {
                if (drag.session.id !== undefined) {
                    studio.setInspectedTarget({ classId: drag.classItem.id, sessionId: drag.session.id });
                }
                return;
            }

            const timeChanged =
                drag.targetStartMin !== drag.initialStartMin || drag.targetEndMin !== drag.initialEndMin;
            const roomChanged =
                studio.matrixPivot === "room" && drag.targetRowId !== drag.initialRowId;

            if (!timeChanged && !roomChanged) return;

            const targetRoomName = drag.targetRoomId ? studio.roomsById.get(drag.targetRoomId)?.name : "Unassigned";
            const label = roomChanged
                ? `Moved ${drag.classItem.subject_code} to ${targetRoomName} (${formatTimeRange(drag.targetStartMin, drag.targetEndMin)})`
                : `Shifted ${drag.classItem.subject_code} to ${formatTimeRange(drag.targetStartMin, drag.targetEndMin)}`;

            void studio.updateScheduleBlockDirect(
                drag.classItem,
                drag.session,
                {
                    day_of_week: activeDay,
                    start_time: minutesToApiTime(drag.targetStartMin),
                    end_time: minutesToApiTime(drag.targetEndMin),
                    ...(roomChanged ? { room_id: drag.targetRoomId } : {}),
                },
                label,
            );
        };

        window.addEventListener("pointermove", handlePointerMove);
        window.addEventListener("pointerup", handlePointerUp);
        return () => {
            window.removeEventListener("pointermove", handlePointerMove);
            window.removeEventListener("pointerup", handlePointerUp);
        };
    }, [activeDrag, activeDay, resolveRowAtY, studio]);

    const startDrag = (
        event: React.PointerEvent,
        row: MatrixRow,
        classItem: ClassScheduleData,
        session: ScheduleEntry,
        startMin: number,
        endMin: number,
    ) => {
        if (event.button !== 0 || session.id === undefined) return;
        event.stopPropagation();
        studio.setInspectedTarget(null);

        const timeline = timelineRef.current?.querySelector<HTMLElement>("[data-matrix-track]");
        let pointerOffsetMin = 0;
        if (timeline) {
            const rect = timeline.getBoundingClientRect();
            const pointerMin = START_MIN + ((event.clientX - rect.left) / rect.width) * TOTAL_MINS;
            pointerOffsetMin = Math.max(0, Math.min(endMin - startMin, pointerMin - startMin));
        }

        setActiveDrag({
            classItem,
            session,
            originClientX: event.clientX,
            originClientY: event.clientY,
            initialStartMin: startMin,
            initialEndMin: endMin,
            initialRowId: row.id,
            pointerOffsetMin,
            hasMoved: false,
            targetRowId: row.id,
            targetRoomId: row.roomId ?? null,
            targetStartMin: startMin,
            targetEndMin: endMin,
        });
    };

    const inspectedClass = studio.inspectedTarget
        ? studio.classes.find((c) => c.id === studio.inspectedTarget?.classId)
        : undefined;
    const inspectedSession =
        inspectedClass && studio.inspectedTarget
            ? inspectedClass.schedules.find((s) => s.id === studio.inspectedTarget?.sessionId)
            : undefined;

    return (
        <section
            aria-label="Resource matrix timeline"
            className="bg-card border-border/70 relative flex min-w-0 flex-1 flex-col overflow-hidden rounded-xl border select-none"
        >
            {/* Top Controls: Pivot Tabs + Day Switcher */}
            <div className="border-border/60 flex flex-wrap items-center justify-between gap-2.5 border-b px-3.5 py-2.5">
                <div className="bg-muted/70 inline-flex items-center rounded-lg p-0.5">
                    {PIVOTS.map((pivot) => {
                        const Icon = pivot.icon;
                        const active = studio.matrixPivot === pivot.value;
                        return (
                            <button
                                key={pivot.value}
                                type="button"
                                onClick={() => studio.setMatrixPivot(pivot.value)}
                                className={`inline-flex items-center gap-1.5 rounded-md px-2.5 py-1 text-xs font-medium transition-colors ${
                                    active
                                        ? "bg-background text-foreground shadow-xs"
                                        : "text-muted-foreground hover:text-foreground"
                                }`}
                            >
                                <Icon className="size-3.5" aria-hidden="true" />
                                <span>{pivot.label}</span>
                            </button>
                        );
                    })}
                </div>

                {/* Day Selector Pills */}
                <div className="flex flex-wrap items-center gap-1">
                    {days.map((day) => {
                        const stats = dayCounts.get(day) ?? { total: 0, conflicts: 0 };
                        const active = activeDay === day;
                        return (
                            <button
                                key={day}
                                type="button"
                                onClick={() => studio.setMatrixDay(day)}
                                className={`inline-flex items-center gap-1.5 rounded-lg border px-2.5 py-1 text-xs font-medium transition-colors tabular-nums ${
                                    active
                                        ? "bg-primary text-primary-foreground border-primary"
                                        : "bg-background hover:bg-muted border-border/70 text-foreground"
                                }`}
                            >
                                <span>{day.slice(0, 3)}</span>
                                <span className={`text-[10px] ${active ? "opacity-85" : "text-muted-foreground"}`}>
                                    {stats.total}
                                </span>
                                {stats.conflicts > 0 && (
                                    <span className="size-1.5 rounded-full bg-red-500" title={`${stats.conflicts} conflicts`} />
                                )}
                            </button>
                        );
                    })}
                </div>
            </div>

            <div className="bg-muted/20 border-border/60 text-muted-foreground border-b px-3.5 py-1.5 text-[11px]">
                {studio.matrixPivot === "room"
                    ? "Drag any campus session vertically across rows to reassign its room, or horizontally to shift its time."
                    : "Drag any session horizontally to adjust its start & end time on this day."}
            </div>

            {/* Matrix Body */}
            <div ref={timelineRef} className="flex-1 overflow-auto">
                <div className="min-w-[1040px]">
                    {/* Time Scale Header */}
                    <div className="bg-card/95 border-border/60 sticky top-0 z-10 grid border-b" style={{ gridTemplateColumns: "200px 1fr" }}>
                        <div className="border-border/50 text-muted-foreground border-r px-3 py-2 text-[11px] font-semibold">
                            {studio.matrixPivot === "room"
                                ? `Rooms (${rows.length})`
                                : studio.matrixPivot === "faculty"
                                  ? `Instructors (${rows.length})`
                                  : `Cohorts (${rows.length})`}
                        </div>
                        <div className="relative grid" style={{ gridTemplateColumns: `repeat(${HOURS.length}, minmax(0, 1fr))` }}>
                            {HOURS.map((hour) => (
                                <div
                                    key={hour}
                                    className="border-border/40 text-muted-foreground border-r px-1.5 py-2 text-[10px] font-medium whitespace-nowrap tabular-nums last:border-r-0"
                                >
                                    {minutesToShortHour(hour)}
                                </div>
                            ))}
                        </div>
                    </div>

                    {/* Rows */}
                    {rows.length === 0 ? (
                        <div className="text-muted-foreground py-16 text-center text-sm">
                            No sessions scheduled on {activeDay} matching the current filters.
                        </div>
                    ) : (
                        <div className="divide-border/50 divide-y">
                            {rows.map((row) => {
                                const packed = packOverlappingBlocks(row.blocks);
                                const maxLanes = Math.max(
                                    1,
                                    ...row.blocks.map((b) => packed.get(b.key)?.laneCount ?? 1),
                                );
                                const laneHeight = studio.density === "birdseye" ? 42 : 48;
                                const rowHeight = Math.max(56, maxLanes * laneHeight + 12);

                                const isDropRow = activeDrag?.hasMoved && activeDrag.targetRowId === row.id;
                                const showGhostHere =
                                    studio.ghostPreview &&
                                    studio.ghostPreview.day_of_week === activeDay &&
                                    studio.matrixPivot === "room" &&
                                    studio.ghostPreview.room_id === row.roomId;

                                return (
                                    <div
                                        key={row.id}
                                        data-matrix-row-id={row.id}
                                        className={`grid transition-colors ${isDropRow ? "bg-primary/[0.04]" : ""}`}
                                        style={{ gridTemplateColumns: "200px 1fr", minHeight: rowHeight }}
                                    >
                                        {/* Row Header */}
                                        <div className="border-border/50 flex flex-col justify-center gap-0.5 border-r px-3 py-2">
                                            <div className="flex items-center justify-between gap-1.5">
                                                <span className="truncate text-xs font-semibold tracking-tight">{row.label}</span>
                                                <Badge variant="outline" className="h-4 px-1 text-[10px] tabular-nums">
                                                    {row.blocks.length}
                                                </Badge>
                                            </div>
                                            <p className="text-muted-foreground truncate text-[10px]">{row.sublabel}</p>
                                            {row.overcrowded && (
                                                <span className="inline-flex items-center gap-1 text-[10px] font-medium text-amber-700 dark:text-amber-300">
                                                    <Users className="size-3" /> Exceeds room capacity
                                                </span>
                                            )}
                                        </div>

                                        {/* Row Timeline Track */}
                                        <div data-matrix-track className="relative" style={{ height: rowHeight }}>
                                            {/* Vertical hour gridlines */}
                                            <div
                                                className="pointer-events-none absolute inset-0 grid"
                                                style={{ gridTemplateColumns: `repeat(${HOURS.length}, minmax(0, 1fr))` }}
                                            >
                                                {HOURS.map((hour) => (
                                                    <div key={hour} className="border-border/35 border-r last:border-r-0" />
                                                ))}
                                            </div>

                                            {/* Blocks */}
                                            {row.blocks.map(({ key, classItem, session, startMin, endMin }) => {
                                                const layout = packed.get(key) ?? { lane: 0, laneCount: 1 };
                                                const leftPct = ((startMin - START_MIN) / TOTAL_MINS) * 100;
                                                const widthPct = ((endMin - startMin) / TOTAL_MINS) * 100;
                                                const top = 6 + layout.lane * laneHeight;

                                                const sessionId = session.id;
                                                const hasConflict =
                                                    sessionId !== undefined && studio.conflictScheduleIdSet.has(sessionId);
                                                const isOvercrowded =
                                                    row.capacity != null &&
                                                    row.capacity > 0 &&
                                                    classItem.student_count > row.capacity;
                                                const color = resolveBlockColor(
                                                    classItem,
                                                    session,
                                                    studio.colorScheme,
                                                    hasConflict,
                                                    isOvercrowded,
                                                );
                                                const isBeingDragged =
                                                    activeDrag?.hasMoved && activeDrag.session.id === sessionId;
                                                const isSaving =
                                                    sessionId !== undefined && studio.savingScheduleIds.has(sessionId);

                                                const isSpotlighted =
                                                    !studio.spotlightMode ||
                                                    (studio.focusedScheduleIds.length > 0
                                                        ? sessionId !== undefined && studio.focusedScheduleIds.includes(sessionId)
                                                        : hasConflict);

                                                return (
                                                    <div
                                                        key={key}
                                                        onPointerDown={(e) =>
                                                            startDrag(e, row, classItem, session, startMin, endMin)
                                                        }
                                                        style={{
                                                            left: `calc(${leftPct}% + 2px)`,
                                                            width: `calc(${widthPct}% - 4px)`,
                                                            top,
                                                            height: laneHeight - 4,
                                                        }}
                                                        className={`absolute overflow-hidden rounded-md border px-2 py-1 text-left transition-opacity ${
                                                            color.surface
                                                        } ${isBeingDragged ? "opacity-30" : "cursor-grab"} ${
                                                            !isSpotlighted ? "opacity-20 saturate-50" : "z-[2]"
                                                        } ${hasConflict && isSpotlighted ? "z-[4] ring-1 ring-red-500/60" : ""}`}
                                                    >
                                                        <div className={`absolute top-0 bottom-0 left-0 w-1 ${color.accentBar}`} />
                                                        <div className="flex items-center justify-between gap-1 pl-1">
                                                            <span className="truncate text-[11px] font-semibold">
                                                                {classItem.subject_code} · {classItem.section}
                                                            </span>
                                                            {isSaving ? (
                                                                <Loader2 className="size-3 shrink-0 animate-spin" />
                                                            ) : hasConflict ? (
                                                                <AlertTriangle className="size-3 shrink-0 text-red-600 dark:text-red-300" />
                                                            ) : null}
                                                        </div>
                                                        <p className="truncate pl-1 text-[10px] opacity-80 tabular-nums">
                                                            {session.time_range}
                                                            {classItem.faculty_name ? ` · ${classItem.faculty_name}` : ""}
                                                        </p>
                                                    </div>
                                                );
                                            })}

                                            {/* Live Drag Target Preview in Row */}
                                            {isDropRow && activeDrag && dragSimulation && (
                                                <div
                                                    style={{
                                                        left: `${((activeDrag.targetStartMin - START_MIN) / TOTAL_MINS) * 100}%`,
                                                        width: `${((activeDrag.targetEndMin - activeDrag.targetStartMin) / TOTAL_MINS) * 100}%`,
                                                        top: 4,
                                                        bottom: 4,
                                                    }}
                                                    className={`pointer-events-none absolute z-20 flex flex-col justify-center rounded-md border-2 border-dashed px-2 shadow-md ${
                                                        dragSimulation.hasConflict
                                                            ? "border-red-500 bg-red-500/20 text-red-950 dark:text-red-50"
                                                            : "border-emerald-500 bg-emerald-500/20 text-emerald-950 dark:text-emerald-50"
                                                    }`}
                                                >
                                                    <div className="flex items-center gap-1 text-[10px] font-semibold">
                                                        {dragSimulation.hasConflict ? (
                                                            <AlertTriangle className="size-3 shrink-0" />
                                                        ) : (
                                                            <CheckCircle2 className="size-3 shrink-0" />
                                                        )}
                                                        <span className="truncate">{dragSimulation.reasonSummary}</span>
                                                    </div>
                                                    <span className="truncate text-[10px] tabular-nums">
                                                        {formatTimeRange(activeDrag.targetStartMin, activeDrag.targetEndMin)}
                                                    </span>
                                                </div>
                                            )}

                                            {/* Ghost Preview from Conflict Workbench */}
                                            {showGhostHere && studio.ghostPreview && (
                                                <div
                                                    style={{
                                                        left: `${((parseTimeToMinutes(studio.ghostPreview.start_time) - START_MIN) / TOTAL_MINS) * 100}%`,
                                                        width: `${((parseTimeToMinutes(studio.ghostPreview.end_time) - parseTimeToMinutes(studio.ghostPreview.start_time)) / TOTAL_MINS) * 100}%`,
                                                        top: 4,
                                                        bottom: 4,
                                                    }}
                                                    className="pointer-events-none absolute z-20 flex flex-col justify-center rounded-md border-2 border-dashed border-emerald-500 bg-emerald-500/20 px-2 text-emerald-950 shadow-md dark:text-emerald-50"
                                                >
                                                    <span className="flex items-center gap-1 truncate text-[10px] font-semibold">
                                                        <Sparkles className="size-3 shrink-0" />
                                                        {studio.ghostPreview.label}
                                                    </span>
                                                </div>
                                            )}
                                        </div>
                                    </div>
                                );
                            })}
                        </div>
                    )}
                </div>
            </div>

            {/* Inline Session Inspector Floating Panel */}
            {inspectedClass && inspectedSession && (
                <div className="fixed right-6 bottom-6 z-40 w-[340px] max-w-[calc(100vw-2rem)]">
                    <SessionInspectorPopover
                        studio={studio}
                        classItem={inspectedClass}
                        session={inspectedSession}
                        rooms={rooms}
                        onClose={() => studio.setInspectedTarget(null)}
                    />
                </div>
            )}
        </section>
    );
}
