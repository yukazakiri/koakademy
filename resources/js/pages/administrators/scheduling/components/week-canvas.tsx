import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { AlertTriangle, CheckCircle2, GripHorizontal, Loader2, MapPin, Monitor, Plus, Sparkles, Users } from "lucide-react";
import { useCallback, useEffect, useMemo, useRef, useState } from "react";
import { canonicalDeliveryMode, deliveryModeShortLabel, isOnlineDeliveryMode } from "../../classes/lib/class-defaults";
import type { ClassScheduleData, RoomOption, ScheduleEntry } from "../../components/scheduling-types";
import type { ScheduleStudioController } from "../hooks/use-schedule-studio";
import { simulatePlacementConflicts, type SimulationResult } from "../lib/conflict-engine";
import { resolveBlockColor } from "../lib/schedule-colors";
import {
    clampMinutes,
    DEFAULT_END_HOUR,
    DEFAULT_START_HOUR,
    DENSITY_ROW_HEIGHT,
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

type WeekCanvasProps = {
    studio: ScheduleStudioController;
    rooms: RoomOption[];
};

type DragMode = "move" | "resize-start" | "resize-end";

type ActiveBlockDrag = {
    mode: DragMode;
    classItem: ClassScheduleData;
    session: ScheduleEntry;
    originClientX: number;
    originClientY: number;
    initialStartMin: number;
    initialEndMin: number;
    initialDay: string;
    pointerOffsetMin: number;
    hasMoved: boolean;
    targetDay: string;
    targetStartMin: number;
    targetEndMin: number;
};

type BacklogHoverTarget = {
    day: string;
    startMin: number;
    endMin: number;
    simulation: SimulationResult;
};

const START_MIN = DEFAULT_START_HOUR * 60;
const END_MIN = DEFAULT_END_HOUR * 60;
const HOURS = Array.from({ length: DEFAULT_END_HOUR - DEFAULT_START_HOUR }, (_, i) => i + DEFAULT_START_HOUR);

export default function WeekCanvas({ studio, rooms }: WeekCanvasProps) {
    const gridRef = useRef<HTMLDivElement | null>(null);
    const dayColumnsRef = useRef<HTMLDivElement | null>(null);

    const [activeDrag, setActiveDrag] = useState<ActiveBlockDrag | null>(null);
    const [backlogHover, setBacklogHover] = useState<BacklogHoverTarget | null>(null);
    const [emptySlotPrompt, setEmptySlotPrompt] = useState<{ day: string; startMin: number; endMin: number } | null>(null);

    const days = studio.visibleDays;
    const hourHeight = DENSITY_ROW_HEIGHT[studio.density];
    const totalHeight = HOURS.length * hourHeight;

    const pxToMinutesOffset = useCallback((deltaPx: number) => (deltaPx / hourHeight) * 60, [hourHeight]);
    const minutesToPx = useCallback((minutes: number) => ((minutes - START_MIN) / 60) * hourHeight, [hourHeight]);

    // Resolve pointer coordinates into (day, snappedStartMin)
    const resolvePointerSlot = useCallback(
        (clientX: number, clientY: number): { day: string; minute: number } | null => {
            const container = dayColumnsRef.current;
            if (!container) return null;
            const rect = container.getBoundingClientRect();
            if (clientX < rect.left || clientX > rect.right || clientY < rect.top || clientY > rect.bottom) {
                return null;
            }
            const colWidth = rect.width / days.length;
            const dayIndex = Math.max(0, Math.min(days.length - 1, Math.floor((clientX - rect.left) / colWidth)));
            const relY = clientY - rect.top;
            const rawMinutes = START_MIN + (relY / hourHeight) * 60;
            return { day: days[dayIndex], minute: rawMinutes };
        },
        [days, hourHeight],
    );

    // Flatten visible blocks by day & compute overlap lane layouts
    const blocksByDay = useMemo(() => {
        const map = new Map<
            string,
            Array<{
                key: string;
                classItem: ClassScheduleData;
                session: ScheduleEntry;
                startMin: number;
                endMin: number;
            }>
        >();
        for (const day of days) map.set(day, []);

        for (const classItem of studio.visibleClasses) {
            for (const session of classItem.schedules) {
                if (studio.viewFilters.room !== "all" && String(session.room_id) !== studio.viewFilters.room) {
                    continue;
                }
                const list = map.get(session.day_of_week);
                if (!list) continue;
                const startMin = clampMinutes(parseTimeToMinutes(session.start_time), START_MIN, END_MIN - MIN_DURATION_MINUTES);
                const endMin = clampMinutes(
                    parseTimeToMinutes(session.end_time),
                    startMin + MIN_DURATION_MINUTES,
                    END_MIN,
                );
                list.push({
                    key: `${classItem.id}:${session.id ?? `${session.day_of_week}-${startMin}`}`,
                    classItem,
                    session,
                    startMin,
                    endMin,
                });
            }
        }
        return map;
    }, [days, studio.visibleClasses, studio.viewFilters.room]);

    const layoutsByDay = useMemo(() => {
        const result = new Map<string, ReturnType<typeof packOverlappingBlocks>>();
        for (const [day, items] of blocksByDay.entries()) {
            result.set(day, packOverlappingBlocks(items));
        }
        return result;
    }, [blocksByDay]);

    // Live pre-drop conflict simulation while dragging or resizing a block
    const dragSimulation: SimulationResult | null = useMemo(() => {
        if (!activeDrag || !activeDrag.hasMoved) return null;
        return simulatePlacementConflicts(
            {
                scheduleId: activeDrag.session.id,
                classItem: activeDrag.classItem,
                day_of_week: activeDrag.targetDay,
                startMin: activeDrag.targetStartMin,
                endMin: activeDrag.targetEndMin,
                room_id: activeDrag.session.room_id ?? null,
                delivery_mode: canonicalDeliveryMode(activeDrag.session.delivery_mode ?? activeDrag.classItem.delivery_mode),
            },
            studio.classes,
            studio.policy,
        );
    }, [activeDrag, studio.classes, studio.policy]);

    // Handle pointer move & pointer up for existing block drag/resize
    useEffect(() => {
        if (!activeDrag) return;

        const handlePointerMove = (event: PointerEvent) => {
            const dx = event.clientX - activeDrag.originClientX;
            const dy = event.clientY - activeDrag.originClientY;
            const movedEnough = activeDrag.hasMoved || Math.hypot(dx, dy) > 5;
            if (!movedEnough) return;

            if (activeDrag.mode === "move") {
                const container = dayColumnsRef.current;
                if (!container) return;
                const rect = container.getBoundingClientRect();
                const colWidth = rect.width / days.length;
                const dayIdx = Math.max(0, Math.min(days.length - 1, Math.floor((event.clientX - rect.left) / colWidth)));
                const targetDay = days[dayIdx];
                const duration = activeDrag.initialEndMin - activeDrag.initialStartMin;
                const pointerMinute = START_MIN + ((event.clientY - rect.top) / hourHeight) * 60;
                const rawStart = snapMinutes(pointerMinute - activeDrag.pointerOffsetMin, SNAP_MINUTES);
                const targetStartMin = clampMinutes(rawStart, START_MIN, END_MIN - duration);
                const targetEndMin = targetStartMin + duration;

                setActiveDrag((prev) =>
                    prev ? { ...prev, hasMoved: true, targetDay, targetStartMin, targetEndMin } : null,
                );
            } else if (activeDrag.mode === "resize-end") {
                const deltaMin = snapMinutes(pxToMinutesOffset(dy), SNAP_MINUTES);
                const targetEndMin = clampMinutes(
                    activeDrag.initialEndMin + deltaMin,
                    activeDrag.initialStartMin + MIN_DURATION_MINUTES,
                    END_MIN,
                );
                setActiveDrag((prev) => (prev ? { ...prev, hasMoved: true, targetEndMin } : null));
            } else if (activeDrag.mode === "resize-start") {
                const deltaMin = snapMinutes(pxToMinutesOffset(dy), SNAP_MINUTES);
                const targetStartMin = clampMinutes(
                    activeDrag.initialStartMin + deltaMin,
                    START_MIN,
                    activeDrag.initialEndMin - MIN_DURATION_MINUTES,
                );
                setActiveDrag((prev) => (prev ? { ...prev, hasMoved: true, targetStartMin } : null));
            }
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

            const changed =
                drag.targetDay !== drag.initialDay ||
                drag.targetStartMin !== drag.initialStartMin ||
                drag.targetEndMin !== drag.initialEndMin;

            if (!changed) return;

            const startApi = minutesToApiTime(drag.targetStartMin);
            const endApi = minutesToApiTime(drag.targetEndMin);
            const verb = drag.mode === "move" ? "Moved" : "Resized";
            void studio.updateScheduleBlockDirect(
                drag.classItem,
                drag.session,
                {
                    day_of_week: drag.targetDay,
                    start_time: startApi,
                    end_time: endApi,
                },
                `${verb} ${drag.classItem.subject_code} to ${drag.targetDay.slice(0, 3)} ${formatTimeRange(drag.targetStartMin, drag.targetEndMin)}`,
            );
        };

        window.addEventListener("pointermove", handlePointerMove);
        window.addEventListener("pointerup", handlePointerUp);
        return () => {
            window.removeEventListener("pointermove", handlePointerMove);
            window.removeEventListener("pointerup", handlePointerUp);
        };
    }, [activeDrag, days, hourHeight, pxToMinutesOffset, studio]);

    // Handle drag-from-backlog-rail onto the Week Canvas
    useEffect(() => {
        if (!studio.draggingBacklog) {
            setBacklogHover(null);
            return;
        }
        const dragging = studio.draggingBacklog;

        const handlePointerMove = (event: PointerEvent) => {
            const slot = resolvePointerSlot(event.clientX, event.clientY);
            if (!slot) {
                setBacklogHover(null);
                return;
            }
            const duration = dragging.durationMinutes;
            const startMin = clampMinutes(snapMinutes(slot.minute - 30, SNAP_MINUTES), START_MIN, END_MIN - duration);
            const endMin = startMin + duration;
            const simulation = simulatePlacementConflicts(
                {
                    classItem: dragging.classItem,
                    day_of_week: slot.day,
                    startMin,
                    endMin,
                    room_id: dragging.classItem.room_id ?? null,
                    delivery_mode: canonicalDeliveryMode(dragging.classItem.delivery_mode),
                },
                studio.classes,
                studio.policy,
            );
            setBacklogHover({ day: slot.day, startMin, endMin, simulation });
        };

        const handlePointerUp = (event: PointerEvent) => {
            const slot = resolvePointerSlot(event.clientX, event.clientY);
            studio.setDraggingBacklog(null);
            setBacklogHover(null);
            if (!slot) return;

            const duration = dragging.durationMinutes;
            const startMin = clampMinutes(snapMinutes(slot.minute - 30, SNAP_MINUTES), START_MIN, END_MIN - duration);
            const endMin = startMin + duration;
            const deliveryMode = canonicalDeliveryMode(dragging.classItem.delivery_mode);

            void studio.addClassScheduleBlocks(dragging.classItem, {
                days: [slot.day],
                start_time: minutesToApiTime(startMin),
                end_time: minutesToApiTime(endMin),
                room_id: isOnlineDeliveryMode(deliveryMode) ? null : (dragging.classItem.room_id ?? null),
                delivery_mode: deliveryMode,
                component_type: (dragging.classItem.lab_hours ?? 0) > 0 && (dragging.classItem.lecture_hours ?? 0) === 0 ? "lab" : "lecture",
                status: "draft",
            });
        };

        window.addEventListener("pointermove", handlePointerMove);
        window.addEventListener("pointerup", handlePointerUp);
        return () => {
            window.removeEventListener("pointermove", handlePointerMove);
            window.removeEventListener("pointerup", handlePointerUp);
        };
    }, [studio.draggingBacklog, resolvePointerSlot, studio]);

    const startBlockInteraction = (
        event: React.PointerEvent,
        mode: DragMode,
        classItem: ClassScheduleData,
        session: ScheduleEntry,
        startMin: number,
        endMin: number,
    ) => {
        if (event.button !== 0 || session.id === undefined) return;
        event.stopPropagation();
        studio.setInspectedTarget(null);
        setEmptySlotPrompt(null);

        const container = dayColumnsRef.current;
        let pointerOffsetMin = 0;
        if (container && mode === "move") {
            const rect = container.getBoundingClientRect();
            const pointerMin = START_MIN + ((event.clientY - rect.top) / hourHeight) * 60;
            pointerOffsetMin = Math.max(0, Math.min(endMin - startMin, pointerMin - startMin));
        }

        setActiveDrag({
            mode,
            classItem,
            session,
            originClientX: event.clientX,
            originClientY: event.clientY,
            initialStartMin: startMin,
            initialEndMin: endMin,
            initialDay: session.day_of_week,
            pointerOffsetMin,
            hasMoved: false,
            targetDay: session.day_of_week,
            targetStartMin: startMin,
            targetEndMin: endMin,
        });
    };

    const handleEmptyColumnClick = (day: string, event: React.MouseEvent<HTMLDivElement>) => {
        if (activeDrag || studio.draggingBacklog) return;
        const rect = event.currentTarget.getBoundingClientRect();
        const relY = event.clientY - rect.top;
        const rawMin = START_MIN + (relY / hourHeight) * 60;
        const startMin = clampMinutes(snapMinutes(rawMin - 15, 30), START_MIN, END_MIN - 60);
        const endMin = Math.min(END_MIN, startMin + 90);
        studio.setInspectedTarget(null);
        setEmptySlotPrompt({ day, startMin, endMin });
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
            ref={gridRef}
            aria-label="Interactive weekly schedule canvas"
            className={`bg-card border-border/70 relative flex min-w-0 flex-1 flex-col overflow-hidden rounded-xl border select-none ${
                activeDrag?.hasMoved ? "cursor-grabbing" : ""
            }`}
        >
            {/* Top Status & Hint Bar */}
            <div className="bg-muted/25 border-border/60 flex flex-wrap items-center justify-between gap-2 border-b px-3.5 py-2 text-[11px]">
                <div className="text-muted-foreground flex flex-wrap items-center gap-2.5">
                    <span className="font-semibold text-foreground">
                        {days.length === 7 ? "Mon – Sun (7-Day View)" : "Mon – Sat"}
                    </span>
                    <span className="hidden sm:inline">Drag blocks to move · Drag edges to resize · Click empty slot to add</span>
                </div>
                <div className="flex flex-wrap items-center gap-2">
                    {studio.visibleClasses.length > 40 && (
                        <button
                            type="button"
                            onClick={() => studio.setActiveLens("matrix")}
                            className="bg-primary/10 text-primary hover:bg-primary/15 inline-flex items-center gap-1 rounded-md px-2 py-0.5 text-[11px] font-medium transition-colors"
                        >
                            Dense ({studio.visibleClasses.length} classes) · Open Resource Matrix →
                        </button>
                    )}
                    {studio.spotlightMode && (
                        <Badge variant="destructive" className="h-5 gap-1 px-2 text-[10px]">
                            Spotlight Active
                            <button
                                type="button"
                                onClick={() => {
                                    studio.setSpotlightMode(false);
                                    studio.setFocusedScheduleIds([]);
                                }}
                                className="ml-1 underline"
                            >
                                Clear
                            </button>
                        </Badge>
                    )}
                    {studio.draggingBacklog && (
                        <Badge className="h-5 gap-1 bg-sky-600 px-2 text-[10px] text-white">
                            Drop {studio.draggingBacklog.classItem.subject_code} onto any slot
                        </Badge>
                    )}
                </div>
            </div>

            {/* Unified Scrollable Canvas (Sticky Day Headers + Time Grid stay 100% aligned) */}
            <div className="relative flex-1 overflow-auto">
                <div style={{ minWidth: days.length === 7 ? 880 : 760 }}>
                    {/* Sticky Day Headers */}
                    <div
                        className="border-border/60 bg-card/95 sticky top-0 z-10 grid border-b backdrop-blur-xs"
                        style={{ gridTemplateColumns: `56px repeat(${days.length}, minmax(0, 1fr))` }}
                    >
                        <div className="border-border/50 text-muted-foreground flex items-center justify-center border-r py-2 text-[10px] font-medium uppercase">
                            Time
                        </div>
                        {days.map((day) => {
                            const dayBlocks = blocksByDay.get(day) ?? [];
                            const dayConflicts = dayBlocks.filter(
                                (b) => b.session.id !== undefined && studio.conflictScheduleIdSet.has(b.session.id),
                            ).length;
                            const isSunday = day === "Sunday";

                            return (
                                <div
                                    key={day}
                                    className={`border-border/50 flex items-center justify-between gap-1.5 border-r px-2.5 py-2 last:border-r-0 ${
                                        isSunday ? "bg-amber-500/[0.05]" : ""
                                    }`}
                                >
                                    <span className="truncate text-xs font-semibold tracking-tight">
                                        {day.slice(0, 3)}
                                        <span className="text-muted-foreground hidden font-normal 2xl:inline">{day.slice(3)}</span>
                                    </span>
                                    <div className="flex shrink-0 items-center gap-1">
                                        <span className="text-muted-foreground text-[10px] tabular-nums">{dayBlocks.length}</span>
                                        {dayConflicts > 0 && (
                                            <span
                                                className="inline-flex items-center gap-0.5 rounded bg-red-500/15 px-1 py-0.5 text-[10px] font-semibold text-red-700 tabular-nums dark:text-red-300"
                                                title={`${dayConflicts} conflicting blocks on ${day}`}
                                            >
                                                <AlertTriangle className="size-2.5" />
                                                {dayConflicts}
                                            </span>
                                        )}
                                    </div>
                                </div>
                            );
                        })}
                    </div>

                    {/* Time Grid */}
                    <div
                        className="grid"
                        style={{ gridTemplateColumns: `56px repeat(${days.length}, minmax(0, 1fr))` }}
                    >
                    {/* Left Hour Gutter */}
                    <div className="border-border/50 bg-muted/10 relative border-r" style={{ height: totalHeight }}>
                        {HOURS.map((hour, index) => (
                            <div
                                key={hour}
                                className="text-muted-foreground absolute right-2 -translate-y-1/2 text-[10px] font-medium tabular-nums"
                                style={{ top: index === 0 ? 8 : index * hourHeight }}
                            >
                                {minutesToShortHour(hour)}
                            </div>
                        ))}
                    </div>

                    {/* Day Columns Wrapper */}
                    <div
                        ref={dayColumnsRef}
                        className="relative col-span-full col-start-2 grid"
                        style={{
                            gridTemplateColumns: `repeat(${days.length}, minmax(0, 1fr))`,
                            height: totalHeight,
                        }}
                    >
                        {days.map((day) => {
                            const dayBlocks = blocksByDay.get(day) ?? [];
                            const dayLayouts = layoutsByDay.get(day);
                            const isSunday = day === "Sunday";

                            const showActiveDragHere = activeDrag?.hasMoved && activeDrag.targetDay === day;
                            const showBacklogHere = backlogHover?.day === day;
                            const showGhostHere = studio.ghostPreview?.day_of_week === day;

                            return (
                                <div
                                    key={day}
                                    onClick={(event) => handleEmptyColumnClick(day, event)}
                                    className={`border-border/50 relative border-r last:border-r-0 ${
                                        isSunday ? "bg-amber-500/[0.02]" : ""
                                    }`}
                                >
                                    {/* Horizontal Hour + Half-Hour Hairlines */}
                                    {HOURS.map((hour, idx) => (
                                        <div
                                            key={hour}
                                            className="border-border/45 pointer-events-none absolute right-0 left-0 border-t"
                                            style={{ top: idx * hourHeight, height: hourHeight }}
                                        >
                                            <div
                                                className="border-border/20 absolute right-0 left-0 border-t border-dashed"
                                                style={{ top: hourHeight / 2 }}
                                            />
                                        </div>
                                    ))}

                                    {/* Schedule Blocks (with dense-cluster lane protection so blocks stay readable) */}
                                    {(() => {
                                        const MAX_SIDE_LANES = 3;
                                        const hiddenBySlot = new Map<number, number>();

                                        const renderedBlocks = dayBlocks.map(({ key, classItem, session, startMin, endMin }) => {
                                            const layout = dayLayouts?.get(key) ?? { lane: 0, laneCount: 1 };
                                            const isDenseCluster = layout.laneCount > 4;

                                            // In ultra-dense unfiltered clusters (>4 simultaneous classes), show first 3 crisp columns + "+N more" badge
                                            if (isDenseCluster && layout.lane >= MAX_SIDE_LANES) {
                                                const slotBucket = Math.floor(startMin / 60) * 60;
                                                hiddenBySlot.set(slotBucket, (hiddenBySlot.get(slotBucket) ?? 0) + 1);
                                                return null;
                                            }

                                            const effectiveLanes = isDenseCluster ? MAX_SIDE_LANES : layout.laneCount;
                                            const top = minutesToPx(startMin);
                                            const height = Math.max(24, ((endMin - startMin) / 60) * hourHeight);
                                            const widthPct = 100 / effectiveLanes;
                                            const leftPct = layout.lane * widthPct;

                                            const sessionId = session.id;
                                            const hasConflict =
                                                sessionId !== undefined && studio.conflictScheduleIdSet.has(sessionId);
                                            const mode = canonicalDeliveryMode(session.delivery_mode ?? classItem.delivery_mode);
                                            const online = isOnlineDeliveryMode(mode);
                                            const assignedRoom = session.room_id ? studio.roomsById.get(session.room_id) : undefined;
                                            const isOvercrowded =
                                                !online &&
                                                assignedRoom?.capacity != null &&
                                                assignedRoom.capacity > 0 &&
                                                classItem.student_count > assignedRoom.capacity;

                                            const color = resolveBlockColor(
                                                classItem,
                                                session,
                                                studio.colorScheme,
                                                hasConflict,
                                                isOvercrowded,
                                            );

                                            const isBeingDragged =
                                                activeDrag?.hasMoved && activeDrag.session.id === sessionId;
                                            const isSaving = sessionId !== undefined && studio.savingScheduleIds.has(sessionId);
                                            const isInspected = studio.inspectedTarget?.sessionId === sessionId;

                                            const isSpotlighted =
                                                !studio.spotlightMode ||
                                                (studio.focusedScheduleIds.length > 0
                                                    ? sessionId !== undefined && studio.focusedScheduleIds.includes(sessionId)
                                                    : hasConflict);

                                            const compact = height < 40 || effectiveLanes >= 3;

                                            return (
                                                <div
                                                    key={key}
                                                    onPointerDown={(e) =>
                                                        startBlockInteraction(e, "move", classItem, session, startMin, endMin)
                                                    }
                                                    onClick={(e) => e.stopPropagation()}
                                                    style={{
                                                        top: top + 1,
                                                        height: height - 2,
                                                        left: `calc(${leftPct}% + 2px)`,
                                                        width: `calc(${widthPct}% - 4px)`,
                                                    }}
                                                    className={`group absolute overflow-hidden rounded-lg border text-left shadow-2xs transition-[opacity,box-shadow,background-color] duration-150 ${
                                                        color.surface
                                                    } ${session.status === "draft" ? "border-dashed" : ""} ${
                                                        isBeingDragged ? "opacity-30 ring-2 ring-dashed ring-primary/50" : "cursor-grab"
                                                    } ${
                                                        !isSpotlighted ? "opacity-20 saturate-50" : "z-[2]"
                                                    } ${
                                                        hasConflict && isSpotlighted ? "z-[4] ring-1 ring-red-500/60" : ""
                                                    } ${
                                                        isInspected ? "z-[5] ring-2 ring-primary shadow-md" : ""
                                                    }`}
                                                >
                                                    <div className={`absolute top-0 bottom-0 left-0 w-1 ${color.accentBar}`} />

                                                    <div
                                                        onPointerDown={(e) =>
                                                            startBlockInteraction(
                                                                e,
                                                                "resize-start",
                                                                classItem,
                                                                session,
                                                                startMin,
                                                                endMin,
                                                            )
                                                        }
                                                        className="absolute top-0 right-1 left-1 z-10 h-1.5 cursor-ns-resize opacity-0 transition-opacity group-hover:opacity-100"
                                                        title="Drag top edge to adjust start time"
                                                    />

                                                    <div className="flex h-full flex-col justify-between py-1 pr-1.5 pl-2">
                                                        <div className="min-w-0">
                                                            <div className="flex items-center justify-between gap-0.5">
                                                                <span className="truncate text-[11px] font-semibold leading-tight tracking-tight">
                                                                    {classItem.subject_code}
                                                                    {effectiveLanes <= 2 && (
                                                                        <span className="font-normal opacity-75"> · {classItem.section}</span>
                                                                    )}
                                                                </span>
                                                                <div className="flex shrink-0 items-center gap-0.5">
                                                                    {isSaving && <Loader2 className="size-3 animate-spin" />}
                                                                    {hasConflict && (
                                                                        <AlertTriangle className="size-3 text-red-600 dark:text-red-300" />
                                                                    )}
                                                                    {!hasConflict && isOvercrowded && (
                                                                        <Users className="size-3 text-amber-600 dark:text-amber-300" />
                                                                    )}
                                                                </div>
                                                            </div>

                                                            {!compact && (
                                                                <p className="mt-0.5 truncate text-[10px] leading-tight opacity-80 tabular-nums">
                                                                    {session.time_range}
                                                                </p>
                                                            )}
                                                        </div>

                                                        {height >= 46 && (
                                                            <div className="flex items-center justify-between gap-1 text-[10px] opacity-85">
                                                                <span className="inline-flex min-w-0 items-center gap-1 truncate">
                                                                    {online ? (
                                                                        <Monitor className="size-2.5 shrink-0" />
                                                                    ) : (
                                                                        <MapPin className="size-2.5 shrink-0" />
                                                                    )}
                                                                    <span className="truncate">
                                                                        {online
                                                                            ? deliveryModeShortLabel(mode)
                                                                            : (session.room ?? "Unassigned")}
                                                                    </span>
                                                                </span>
                                                                {height >= 64 && effectiveLanes <= 2 && classItem.faculty_name && (
                                                                    <span className="hidden truncate opacity-75 xl:inline">
                                                                        {classItem.faculty_name.split(" ").slice(-1)[0]}
                                                                    </span>
                                                                )}
                                                            </div>
                                                        )}
                                                    </div>

                                                    <div
                                                        onPointerDown={(e) =>
                                                            startBlockInteraction(
                                                                e,
                                                                "resize-end",
                                                                classItem,
                                                                session,
                                                                startMin,
                                                                endMin,
                                                            )
                                                        }
                                                        className="absolute right-1 bottom-0 left-1 z-10 flex h-2 cursor-ns-resize items-center justify-center opacity-0 transition-opacity group-hover:opacity-100"
                                                        title="Drag bottom edge to adjust end time"
                                                    >
                                                        <GripHorizontal className="size-3 opacity-60" />
                                                    </div>
                                                </div>
                                            );
                                        });

                                        const overflowBadges = [...hiddenBySlot.entries()].map(([slotBucket, count]) => (
                                            <button
                                                key={`overflow:${day}:${slotBucket}`}
                                                type="button"
                                                onClick={(e) => {
                                                    e.stopPropagation();
                                                    studio.jumpToDayAndHour(day, "matrix");
                                                }}
                                                style={{ top: minutesToPx(slotBucket) + 4 }}
                                                title={`View all ${count} more overlapping sessions on ${day} in Resource Matrix`}
                                                className="bg-background/95 hover:bg-primary hover:text-primary-foreground border-border/80 absolute right-1.5 z-[8] rounded-md border px-1.5 py-0.5 text-[10px] font-semibold shadow-sm backdrop-blur-xs transition-colors tabular-nums"
                                            >
                                                +{count} more
                                            </button>
                                        ));

                                        return (
                                            <>
                                                {renderedBlocks}
                                                {overflowBadges}
                                            </>
                                        );
                                    })()}

                                    {/* Live Pre-Drop Drag/Resize Simulation Overlay */}
                                    {showActiveDragHere && activeDrag && dragSimulation && (
                                        <div
                                            style={{
                                                top: minutesToPx(activeDrag.targetStartMin) + 1,
                                                height: Math.max(
                                                    26,
                                                    ((activeDrag.targetEndMin - activeDrag.targetStartMin) / 60) * hourHeight - 2,
                                                ),
                                            }}
                                            className={`pointer-events-none absolute right-1 left-1 z-20 rounded-lg border-2 border-dashed p-1.5 shadow-lg backdrop-blur-[2px] ${
                                                dragSimulation.hasConflict
                                                    ? "border-red-500 bg-red-500/20 text-red-950 dark:text-red-50"
                                                    : "border-emerald-500 bg-emerald-500/20 text-emerald-950 dark:text-emerald-50"
                                            }`}
                                        >
                                            {/* Floating explanation pill */}
                                            <div
                                                className={`absolute -top-7 left-0 flex max-w-[260px] items-center gap-1 rounded-md px-2 py-0.5 text-[10px] font-semibold whitespace-nowrap shadow-md ${
                                                    dragSimulation.hasConflict
                                                        ? "bg-red-600 text-white"
                                                        : "bg-emerald-600 text-white"
                                                }`}
                                            >
                                                {dragSimulation.hasConflict ? (
                                                    <AlertTriangle className="size-3 shrink-0" />
                                                ) : (
                                                    <CheckCircle2 className="size-3 shrink-0" />
                                                )}
                                                <span className="truncate">{dragSimulation.reasonSummary}</span>
                                            </div>
                                            <p className="truncate text-[11px] font-semibold">
                                                {activeDrag.classItem.subject_code} · {activeDrag.classItem.section}
                                            </p>
                                            <p className="text-[10px] font-medium tabular-nums">
                                                {formatTimeRange(activeDrag.targetStartMin, activeDrag.targetEndMin)}
                                            </p>
                                        </div>
                                    )}

                                    {/* Backlog Class Drag-to-Grid Live Simulation Overlay */}
                                    {showBacklogHere && backlogHover && studio.draggingBacklog && (
                                        <div
                                            style={{
                                                top: minutesToPx(backlogHover.startMin) + 1,
                                                height: Math.max(
                                                    26,
                                                    ((backlogHover.endMin - backlogHover.startMin) / 60) * hourHeight - 2,
                                                ),
                                            }}
                                            className={`pointer-events-none absolute right-1 left-1 z-20 rounded-lg border-2 border-dashed p-1.5 shadow-lg ${
                                                backlogHover.simulation.hasConflict
                                                    ? "border-red-500 bg-red-500/20 text-red-950 dark:text-red-50"
                                                    : "border-emerald-500 bg-emerald-500/20 text-emerald-950 dark:text-emerald-50"
                                            }`}
                                        >
                                            <div
                                                className={`absolute -top-7 left-0 flex max-w-[260px] items-center gap-1 rounded-md px-2 py-0.5 text-[10px] font-semibold whitespace-nowrap shadow-md ${
                                                    backlogHover.simulation.hasConflict
                                                        ? "bg-red-600 text-white"
                                                        : "bg-emerald-600 text-white"
                                                }`}
                                            >
                                                {backlogHover.simulation.hasConflict ? (
                                                    <AlertTriangle className="size-3 shrink-0" />
                                                ) : (
                                                    <CheckCircle2 className="size-3 shrink-0" />
                                                )}
                                                <span className="truncate">{backlogHover.simulation.reasonSummary}</span>
                                            </div>
                                            <p className="truncate text-[11px] font-semibold">
                                                + {studio.draggingBacklog.classItem.subject_code} · {studio.draggingBacklog.classItem.section}
                                            </p>
                                            <p className="text-[10px] tabular-nums">
                                                {formatTimeRange(backlogHover.startMin, backlogHover.endMin)}
                                            </p>
                                        </div>
                                    )}

                                    {/* Ghost Preview from Conflict Workbench Suggestion Hover */}
                                    {showGhostHere && studio.ghostPreview && (
                                        <div
                                            style={{
                                                top: minutesToPx(parseTimeToMinutes(studio.ghostPreview.start_time)) + 1,
                                                height: Math.max(
                                                    28,
                                                    ((parseTimeToMinutes(studio.ghostPreview.end_time) -
                                                        parseTimeToMinutes(studio.ghostPreview.start_time)) /
                                                        60) *
                                                        hourHeight -
                                                        2,
                                                ),
                                            }}
                                            className="pointer-events-none absolute right-1 left-1 z-20 rounded-lg border-2 border-dashed border-emerald-500 bg-emerald-500/20 p-1.5 text-emerald-950 shadow-md dark:text-emerald-50"
                                        >
                                            <div className="flex items-center gap-1 text-[10px] font-semibold text-emerald-700 dark:text-emerald-200">
                                                <Sparkles className="size-3 shrink-0" />
                                                <span className="truncate">{studio.ghostPreview.label}</span>
                                            </div>
                                            <p className="truncate text-[11px] font-semibold">
                                                {studio.ghostPreview.classItem.subject_code} · {studio.ghostPreview.classItem.section}
                                            </p>
                                            {studio.ghostPreview.room_name && (
                                                <p className="truncate text-[10px] opacity-85">{studio.ghostPreview.room_name}</p>
                                            )}
                                        </div>
                                    )}
                                </div>
                            );
                        })}
                    </div>
                </div>
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

            {/* Quick-Schedule Empty Slot Picker */}
            {emptySlotPrompt && (
                <div className="fixed right-6 bottom-6 z-40 w-[320px] max-w-[calc(100vw-2rem)] rounded-xl border bg-popover p-3.5 text-popover-foreground shadow-xl">
                    <div className="flex items-center justify-between gap-2">
                        <div>
                            <p className="text-xs font-semibold">
                                Schedule on {emptySlotPrompt.day}
                            </p>
                            <p className="text-muted-foreground text-[11px] tabular-nums">
                                {formatTimeRange(emptySlotPrompt.startMin, emptySlotPrompt.endMin)}
                            </p>
                        </div>
                        <Button
                            type="button"
                            variant="ghost"
                            size="sm"
                            className="h-6 px-2 text-xs"
                            onClick={() => setEmptySlotPrompt(null)}
                        >
                            Close
                        </Button>
                    </div>
                    <div className="mt-2.5 max-h-48 space-y-1 overflow-y-auto">
                        {studio.visibleClasses.slice(0, 12).map((cls) => (
                            <button
                                key={cls.id}
                                type="button"
                                onClick={() => {
                                    studio.setEditorTarget({
                                        classId: cls.id,
                                        initialDay: emptySlotPrompt.day,
                                        initialStartTime: minutesToApiTime(emptySlotPrompt.startMin),
                                        initialEndTime: minutesToApiTime(emptySlotPrompt.endMin),
                                    });
                                    setEmptySlotPrompt(null);
                                }}
                                className="hover:bg-muted flex w-full items-center justify-between gap-2 rounded-md px-2 py-1.5 text-left text-xs transition-colors"
                            >
                                <div className="min-w-0">
                                    <span className="font-semibold">{cls.subject_code}</span>
                                    <span className="text-muted-foreground"> · Sec {cls.section}</span>
                                    <p className="text-muted-foreground truncate text-[10px]">{cls.subject_title}</p>
                                </div>
                                <Plus className="text-muted-foreground size-3.5 shrink-0" />
                            </button>
                        ))}
                    </div>
                </div>
            )}
        </section>
    );
}
