import { show as showClass } from "@/routes/administrators/classes";
import { router } from "@inertiajs/react";
import {
    AlertTriangle,
    ArrowUpRight,
    CalendarDays,
    CheckCircle2,
    Eye,
    GripHorizontal,
    List,
    Loader2,
    MapPin,
    Monitor,
    Pencil,
    Plus,
    Sparkles,
    Sun,
    Trash2,
    Undo2,
    User,
} from "lucide-react";
import { useCallback, useEffect, useMemo, useRef, useState } from "react";
import { canonicalDeliveryMode, deliveryModeShortLabel, isOnlineDeliveryMode } from "../../classes/lib/class-defaults";
import type { ClassScheduleData } from "../../components/scheduling-types";
import {
    FloatingContextMenu,
    FloatingMenuItem,
    FloatingMenuLabel,
    FloatingMenuSeparator,
} from "../../schedule-canvas/components/floating-context-menu";
import type { GhostPreview, ScheduleStudioController } from "../../scheduling/hooks/use-schedule-studio";
import { simulatePlacementConflicts, type SimulationResult } from "../../scheduling/lib/conflict-engine";
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
} from "../../scheduling/lib/time-geometry";
import type { BoardSessionItem, DirectoryCategory } from "../lib/board-entities";

type BoardWeekGridProps = {
    studio: ScheduleStudioController;
    days: string[];
    sessions: BoardSessionItem[];
    category: DirectoryCategory;
    ghostPreview: GhostPreview | null;
    selectedSessionId?: number;
    onSelectSession: (item: BoardSessionItem) => void;
    onClickEmptySlot: (day: string, startTime: string, endTime: string) => void;
    onSwitchToList?: () => void;
};

type DragMode = "move" | "resize-end";

type ActiveDrag = {
    mode: DragMode;
    item: BoardSessionItem;
    originClientX: number;
    originClientY: number;
    pointerOffsetMin: number;
    hasMoved: boolean;
    targetDay: string;
    targetStartMin: number;
    targetEndMin: number;
};

const START_MIN = DEFAULT_START_HOUR * 60;
const END_MIN = DEFAULT_END_HOUR * 60;
const HOURS = Array.from({ length: DEFAULT_END_HOUR - DEFAULT_START_HOUR }, (_, i) => i + DEFAULT_START_HOUR);
const HOUR_HEIGHT = 56;
const TOTAL_HEIGHT = HOURS.length * HOUR_HEIGHT;

const CALM_PALETTE = [
    {
        card: "bg-sky-500/[0.08] hover:bg-sky-500/[0.13] border-sky-500/20 text-sky-950 dark:bg-sky-500/15 dark:text-sky-50 dark:border-sky-400/25",
        bar: "bg-sky-500",
    },
    {
        card: "bg-emerald-500/[0.08] hover:bg-emerald-500/[0.13] border-emerald-500/20 text-emerald-950 dark:bg-emerald-500/15 dark:text-emerald-50 dark:border-emerald-400/25",
        bar: "bg-emerald-500",
    },
    {
        card: "bg-violet-500/[0.08] hover:bg-violet-500/[0.13] border-violet-500/20 text-violet-950 dark:bg-violet-500/15 dark:text-violet-50 dark:border-violet-400/25",
        bar: "bg-violet-500",
    },
    {
        card: "bg-amber-500/[0.09] hover:bg-amber-500/[0.14] border-amber-500/25 text-amber-950 dark:bg-amber-500/15 dark:text-amber-50 dark:border-amber-400/25",
        bar: "bg-amber-500",
    },
    {
        card: "bg-teal-500/[0.08] hover:bg-teal-500/[0.13] border-teal-500/20 text-teal-950 dark:bg-teal-500/15 dark:text-teal-50 dark:border-teal-400/25",
        bar: "bg-teal-500",
    },
    {
        card: "bg-indigo-500/[0.08] hover:bg-indigo-500/[0.13] border-indigo-500/20 text-indigo-950 dark:bg-indigo-500/15 dark:text-indigo-50 dark:border-indigo-400/25",
        bar: "bg-indigo-500",
    },
];

function cardStyleFor(classItem: ClassScheduleData) {
    let hash = 0;
    for (let i = 0; i < classItem.subject_code.length; i++) {
        hash = (hash * 31 + classItem.subject_code.charCodeAt(i)) | 0;
    }
    return CALM_PALETTE[Math.abs(hash) % CALM_PALETTE.length];
}

export default function BoardWeekGrid({
    studio,
    days,
    sessions,
    category,
    ghostPreview,
    selectedSessionId,
    onSelectSession,
    onClickEmptySlot,
    onSwitchToList,
}: BoardWeekGridProps) {
    const columnsRef = useRef<HTMLDivElement | null>(null);
    const [activeDrag, setActiveDrag] = useState<ActiveDrag | null>(null);
    const [contextTarget, setContextTarget] = useState<
        | { kind: "session"; x: number; y: number; item: BoardSessionItem }
        | { kind: "empty"; x: number; y: number; day: string; startMin: number; endMin: number }
        | null
    >(null);

    const minutesToPx = useCallback((mins: number) => ((mins - START_MIN) / 60) * HOUR_HEIGHT, []);

    const sessionsByDay = useMemo(() => {
        const map = new Map<string, BoardSessionItem[]>();
        for (const day of days) map.set(day, []);
        for (const item of sessions) {
            const list = map.get(item.session.day_of_week);
            if (list) list.push(item);
        }
        return map;
    }, [days, sessions]);

    const layoutsByDay = useMemo(() => {
        const map = new Map<string, ReturnType<typeof packOverlappingBlocks>>();
        for (const [day, list] of sessionsByDay.entries()) {
            map.set(day, packOverlappingBlocks(list));
        }
        return map;
    }, [sessionsByDay]);

    const dragSimulation: SimulationResult | null = useMemo(() => {
        if (!activeDrag || !activeDrag.hasMoved) return null;
        return simulatePlacementConflicts(
            {
                scheduleId: activeDrag.item.session.id,
                classItem: activeDrag.item.classItem,
                day_of_week: activeDrag.targetDay,
                startMin: activeDrag.targetStartMin,
                endMin: activeDrag.targetEndMin,
                room_id: activeDrag.item.session.room_id ?? null,
                delivery_mode: canonicalDeliveryMode(
                    activeDrag.item.session.delivery_mode ?? activeDrag.item.classItem.delivery_mode,
                ),
            },
            studio.classes,
            studio.policy,
        );
    }, [activeDrag, studio.classes, studio.policy]);

    useEffect(() => {
        if (!activeDrag) return;

        const onMove = (event: PointerEvent) => {
            const dx = event.clientX - activeDrag.originClientX;
            const dy = event.clientY - activeDrag.originClientY;
            const movedEnough = activeDrag.hasMoved || Math.hypot(dx, dy) > 5;
            if (!movedEnough) return;

            if (activeDrag.mode === "move") {
                const el = columnsRef.current;
                if (!el) return;
                const rect = el.getBoundingClientRect();
                const colWidth = rect.width / days.length;
                const dayIdx = Math.max(0, Math.min(days.length - 1, Math.floor((event.clientX - rect.left) / colWidth)));
                const targetDay = days[dayIdx];
                const duration = activeDrag.item.endMin - activeDrag.item.startMin;
                const pointerMin = START_MIN + ((event.clientY - rect.top) / HOUR_HEIGHT) * 60;
                const targetStartMin = clampMinutes(
                    snapMinutes(pointerMin - activeDrag.pointerOffsetMin, SNAP_MINUTES),
                    START_MIN,
                    END_MIN - duration,
                );
                setActiveDrag((prev) =>
                    prev
                        ? { ...prev, hasMoved: true, targetDay, targetStartMin, targetEndMin: targetStartMin + duration }
                        : null,
                );
            } else {
                const deltaMin = snapMinutes((dy / HOUR_HEIGHT) * 60, SNAP_MINUTES);
                const targetEndMin = clampMinutes(
                    activeDrag.item.endMin + deltaMin,
                    activeDrag.item.startMin + MIN_DURATION_MINUTES,
                    END_MIN,
                );
                setActiveDrag((prev) => (prev ? { ...prev, hasMoved: true, targetEndMin } : null));
            }
        };

        const onUp = () => {
            const drag = activeDrag;
            setActiveDrag(null);

            if (!drag.hasMoved) {
                onSelectSession(drag.item);
                return;
            }

            const changed =
                drag.targetDay !== drag.item.session.day_of_week ||
                drag.targetStartMin !== drag.item.startMin ||
                drag.targetEndMin !== drag.item.endMin;

            if (!changed) return;

            void studio.updateScheduleBlockDirect(
                drag.item.classItem,
                drag.item.session,
                {
                    day_of_week: drag.targetDay,
                    start_time: minutesToApiTime(drag.targetStartMin),
                    end_time: minutesToApiTime(drag.targetEndMin),
                },
                `Moved ${drag.item.classItem.subject_code} to ${drag.targetDay.slice(0, 3)} ${formatTimeRange(drag.targetStartMin, drag.targetEndMin)}`,
            );
        };

        window.addEventListener("pointermove", onMove);
        window.addEventListener("pointerup", onUp);
        return () => {
            window.removeEventListener("pointermove", onMove);
            window.removeEventListener("pointerup", onUp);
        };
    }, [activeDrag, days, onSelectSession, studio]);

    const beginDrag = (event: React.PointerEvent, mode: DragMode, item: BoardSessionItem) => {
        if (event.button !== 0 || item.session.id === undefined) return;
        event.stopPropagation();

        let pointerOffsetMin = 0;
        const el = columnsRef.current;
        if (el && mode === "move") {
            const rect = el.getBoundingClientRect();
            const pointerMin = START_MIN + ((event.clientY - rect.top) / HOUR_HEIGHT) * 60;
            pointerOffsetMin = Math.max(0, Math.min(item.endMin - item.startMin, pointerMin - item.startMin));
        }

        setActiveDrag({
            mode,
            item,
            originClientX: event.clientX,
            originClientY: event.clientY,
            pointerOffsetMin,
            hasMoved: false,
            targetDay: item.session.day_of_week,
            targetStartMin: item.startMin,
            targetEndMin: item.endMin,
        });
    };

    const computeSlotAtPointer = (day: string, event: React.MouseEvent<HTMLDivElement>) => {
        const rect = event.currentTarget.getBoundingClientRect();
        const rawMin = START_MIN + ((event.clientY - rect.top) / HOUR_HEIGHT) * 60;
        const startMin = clampMinutes(snapMinutes(rawMin - 15, 30), START_MIN, END_MIN - 60);
        const endMin = Math.min(END_MIN, startMin + 90);
        return { day, startMin, endMin };
    };

    const handleEmptyClick = (day: string, event: React.MouseEvent<HTMLDivElement>) => {
        if (activeDrag) return;
        const slot = computeSlotAtPointer(day, event);
        onClickEmptySlot(slot.day, minutesToApiTime(slot.startMin), minutesToApiTime(slot.endMin));
    };

    const shiftSessionHour = (item: BoardSessionItem, deltaMin: number) => {
        const duration = item.endMin - item.startMin;
        const nextStart = clampMinutes(item.startMin + deltaMin, START_MIN, END_MIN - duration);
        const nextEnd = nextStart + duration;
        void studio.updateScheduleBlockDirect(
            item.classItem,
            item.session,
            {
                day_of_week: item.session.day_of_week,
                start_time: minutesToApiTime(nextStart),
                end_time: minutesToApiTime(nextEnd),
            },
            `Shifted ${item.classItem.subject_code} to ${formatTimeRange(nextStart, nextEnd)}`,
        );
    };

    return (
        <div className="relative flex-1 overflow-auto select-none">
            <div style={{ minWidth: days.length === 7 ? 840 : 720 }}>
                {/* Calm Sticky Day Headers */}
                <div
                    className="bg-card/95 border-border/50 sticky top-0 z-10 grid border-b backdrop-blur-xs"
                    style={{ gridTemplateColumns: `60px repeat(${days.length}, minmax(0, 1fr))` }}
                >
                    <div className="border-border/40 border-r py-2.5" />
                    {days.map((day) => {
                        const list = sessionsByDay.get(day) ?? [];
                        const isSunday = day === "Sunday";
                        return (
                            <div
                                key={day}
                                className={`border-border/40 flex items-baseline justify-between border-r px-3 py-2.5 last:border-r-0 ${
                                    isSunday ? "bg-amber-500/[0.03]" : ""
                                }`}
                            >
                                <span className="text-xs font-semibold tracking-tight">{day}</span>
                                {list.length > 0 && (
                                    <span className="text-muted-foreground text-[11px] tabular-nums">{list.length}</span>
                                )}
                            </div>
                        );
                    })}
                </div>

                {/* Time Grid */}
                <div
                    className="grid"
                    style={{ gridTemplateColumns: `60px repeat(${days.length}, minmax(0, 1fr))` }}
                >
                    {/* Left Time Axis */}
                    <div className="border-border/40 relative border-r" style={{ height: TOTAL_HEIGHT }}>
                        {HOURS.map((hour, index) => (
                            <div
                                key={hour}
                                className="text-muted-foreground/80 absolute right-2.5 -translate-y-1/2 text-[11px] font-medium tabular-nums"
                                style={{ top: index === 0 ? 10 : index * HOUR_HEIGHT }}
                            >
                                {minutesToShortHour(hour)}
                            </div>
                        ))}
                    </div>

                    {/* Day Columns */}
                    <div
                        ref={columnsRef}
                        className="relative col-span-full col-start-2 grid"
                        style={{
                            gridTemplateColumns: `repeat(${days.length}, minmax(0, 1fr))`,
                            height: TOTAL_HEIGHT,
                        }}
                    >
                        {days.map((day) => {
                            const daySessions = sessionsByDay.get(day) ?? [];
                            const dayLayout = layoutsByDay.get(day);
                            const isSunday = day === "Sunday";
                            const showDragHere = activeDrag?.hasMoved && activeDrag.targetDay === day;
                            const showGhostHere = ghostPreview?.day_of_week === day;

                            return (
                                <div
                                    key={day}
                                    data-board-column={day}
                                    onClick={(e) => handleEmptyClick(day, e)}
                                    onContextMenu={(e) => {
                                        if ((e.target as HTMLElement | null)?.closest("[data-board-session]")) {
                                            return;
                                        }
                                        e.preventDefault();
                                        setContextTarget({
                                            kind: "empty",
                                            x: e.clientX,
                                            y: e.clientY,
                                            ...computeSlotAtPointer(day, e),
                                        });
                                    }}
                                    className={`border-border/40 relative border-r last:border-r-0 ${
                                        isSunday ? "bg-amber-500/[0.02]" : ""
                                    }`}
                                >
                                            {/* Whisper-subtle hourly lines */}
                                            {HOURS.map((hour, idx) => (
                                                <div
                                                    key={hour}
                                                    className="border-border/35 pointer-events-none absolute right-0 left-0 border-t"
                                                    style={{ top: idx * HOUR_HEIGHT, height: HOUR_HEIGHT }}
                                                />
                                            ))}

                                            {/* Session Cards */}
                                            {(() => {
                                                const hiddenBuckets = new Map<number, number>();
                                                const cards = daySessions.map((item) => {
                                                    const layout = dayLayout?.get(item.key) ?? { lane: 0, laneCount: 1 };
                                                    const isCrowded = layout.laneCount > 3;
                                                    if (isCrowded && layout.lane >= 2) {
                                                        const bucket = Math.floor(item.startMin / 60) * 60;
                                                        hiddenBuckets.set(bucket, (hiddenBuckets.get(bucket) ?? 0) + 1);
                                                        return null;
                                                    }
                                                    const effectiveLanes = isCrowded ? 2 : layout.laneCount;
                                                    const top = minutesToPx(clampMinutes(item.startMin, START_MIN, END_MIN - 30));
                                                    const height = Math.max(40, ((item.endMin - item.startMin) / 60) * HOUR_HEIGHT);
                                                    const widthPct = 100 / effectiveLanes;
                                                    const leftPct = layout.lane * widthPct;

                                                    const style = cardStyleFor(item.classItem);
                                                    const hasOverlap = item.conflicts.length > 0;
                                                    const isSelected =
                                                        selectedSessionId !== undefined && item.session.id === selectedSessionId;
                                                    const isDragging =
                                                        activeDrag?.hasMoved && activeDrag.item.session.id === item.session.id;
                                                    const isSaving =
                                                        item.session.id !== undefined &&
                                                        studio.savingScheduleIds.has(item.session.id);

                                                    const mode = canonicalDeliveryMode(
                                                        item.session.delivery_mode ?? item.classItem.delivery_mode,
                                                    );
                                                    const online = isOnlineDeliveryMode(mode);

                                                    return (
                                                        <div
                                                            key={item.key}
                                                            data-board-session={item.key}
                                                            onPointerDown={(e) => beginDrag(e, "move", item)}
                                                            onClick={(e) => e.stopPropagation()}
                                                            onContextMenu={(e) => {
                                                                e.preventDefault();
                                                                e.stopPropagation();
                                                                setContextTarget({
                                                                    kind: "session",
                                                                    x: e.clientX,
                                                                    y: e.clientY,
                                                                    item,
                                                                });
                                                            }}
                                                            style={{
                                                                top: top + 2,
                                                                height: height - 4,
                                                                left: `calc(${leftPct}% + 4px)`,
                                                                width: `calc(${widthPct}% - 8px)`,
                                                            }}
                                                            className={`group absolute overflow-hidden rounded-xl border text-left transition-[box-shadow,opacity,background-color] duration-150 ${
                                                                style.card
                                                            } ${hasOverlap ? "border-rose-400/55" : ""} ${
                                                                isDragging ? "opacity-30" : "cursor-grab active:cursor-grabbing"
                                                            } ${isSelected ? "z-[5] ring-2 ring-foreground/60 shadow-md" : "z-[2] shadow-2xs"}`}
                                                        >
                                                            <div
                                                                className={`absolute top-1.5 bottom-1.5 left-1.5 w-1 rounded-full ${
                                                                    hasOverlap ? "bg-rose-500" : style.bar
                                                                }`}
                                                            />

                                                            <div className="flex h-full flex-col justify-between py-2 pr-2 pl-3.5">
                                                                <div className="min-w-0 space-y-0.5">
                                                                    <div className="flex items-center justify-between gap-1">
                                                                        <span className="truncate text-xs font-semibold tracking-tight">
                                                                            {item.classItem.subject_code}
                                                                            {category !== "sections" && effectiveLanes === 1 && (
                                                                                <span className="font-normal opacity-70">
                                                                                    {" "}
                                                                                    · {item.classItem.section}
                                                                                </span>
                                                                            )}
                                                                        </span>
                                                                        {isSaving ? (
                                                                            <Loader2 className="size-3 shrink-0 animate-spin" />
                                                                        ) : hasOverlap ? (
                                                                            <span
                                                                                title="Has schedule overlap — click to fix"
                                                                                className="inline-flex size-4 shrink-0 items-center justify-center rounded-full bg-rose-500/15 text-rose-600 dark:text-rose-300"
                                                                            >
                                                                                <AlertTriangle className="size-2.5" />
                                                                            </span>
                                                                        ) : null}
                                                                    </div>

                                                                    {height >= 58 && (
                                                                        <p className="line-clamp-1 text-[11px] opacity-80">
                                                                            {item.classItem.subject_title}
                                                                        </p>
                                                                    )}
                                                                </div>

                                                                <div className="space-y-0.5 text-[10px] opacity-80">
                                                                    <p className="truncate font-medium tabular-nums">
                                                                        {formatTimeRange(item.startMin, item.endMin)}
                                                                    </p>
                                                                    {height >= 76 && (
                                                                        <div className="flex items-center gap-2 truncate">
                                                                            {category !== "rooms" && (
                                                                                <span className="inline-flex min-w-0 items-center gap-1 truncate">
                                                                                    {online ? (
                                                                                        <Monitor className="size-2.5 shrink-0" />
                                                                                    ) : (
                                                                                        <MapPin className="size-2.5 shrink-0" />
                                                                                    )}
                                                                                    <span className="truncate">
                                                                                        {online
                                                                                            ? deliveryModeShortLabel(mode)
                                                                                            : (item.session.room ?? "No room")}
                                                                                    </span>
                                                                                </span>
                                                                            )}
                                                                            {category === "rooms" && item.classItem.faculty_name && (
                                                                                <span className="inline-flex min-w-0 items-center gap-1 truncate">
                                                                                    <User className="size-2.5 shrink-0" />
                                                                                    <span className="truncate">
                                                                                        {item.classItem.faculty_name}
                                                                                    </span>
                                                                                </span>
                                                                            )}
                                                                        </div>
                                                                    )}
                                                                </div>
                                                            </div>

                                                            <div
                                                                onPointerDown={(e) => beginDrag(e, "resize-end", item)}
                                                                className="absolute right-2 bottom-0 left-2 z-10 flex h-2.5 cursor-ns-resize items-center justify-center opacity-0 transition-opacity group-hover:opacity-100"
                                                                title="Drag bottom edge to resize"
                                                            >
                                                                <GripHorizontal className="size-3 opacity-60" />
                                                            </div>
                                                        </div>
                                                    );
                                                });

                                                const overflowPills = [...hiddenBuckets.entries()].map(([bucket, count]) => (
                                                    <span
                                                        key={`more:${day}:${bucket}`}
                                                        style={{ top: minutesToPx(bucket) + 4 }}
                                                        className="bg-background/95 border-border/80 pointer-events-none absolute right-1.5 z-[6] rounded-full border px-2 py-0.5 text-[10px] font-semibold shadow-xs tabular-nums"
                                                    >
                                                        +{count} more
                                                    </span>
                                                ));

                                                return (
                                                    <>
                                                        {cards}
                                                        {overflowPills}
                                                    </>
                                                );
                                            })()}

                                            {/* Live Pre-Drop Drag Ghost */}
                                            {showDragHere && activeDrag && dragSimulation && (
                                                <div
                                                    style={{
                                                        top: minutesToPx(activeDrag.targetStartMin) + 2,
                                                        height: Math.max(
                                                            40,
                                                            ((activeDrag.targetEndMin - activeDrag.targetStartMin) / 60) * HOUR_HEIGHT - 4,
                                                        ),
                                                    }}
                                                    className={`pointer-events-none absolute right-1 left-1 z-20 rounded-xl border-2 border-dashed p-2 shadow-lg backdrop-blur-[2px] ${
                                                        dragSimulation.hasConflict
                                                            ? "border-rose-500 bg-rose-500/18 text-rose-950 dark:text-rose-50"
                                                            : "border-emerald-500 bg-emerald-500/18 text-emerald-950 dark:text-emerald-50"
                                                    }`}
                                                >
                                                    <div
                                                        className={`absolute -top-7 left-0 inline-flex max-w-[260px] items-center gap-1 rounded-full px-2.5 py-0.5 text-[10px] font-semibold whitespace-nowrap shadow-md ${
                                                            dragSimulation.hasConflict
                                                                ? "bg-rose-600 text-white"
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
                                                    <p className="truncate text-xs font-semibold">
                                                        {activeDrag.item.classItem.subject_code}
                                                    </p>
                                                    <p className="text-[11px] tabular-nums">
                                                        {formatTimeRange(activeDrag.targetStartMin, activeDrag.targetEndMin)}
                                                    </p>
                                                </div>
                                            )}

                                            {/* Suggested Fix Hover Preview */}
                                            {showGhostHere && ghostPreview && (
                                                <div
                                                    style={{
                                                        top: minutesToPx(parseTimeToMinutes(ghostPreview.start_time)) + 2,
                                                        height: Math.max(
                                                            40,
                                                            ((parseTimeToMinutes(ghostPreview.end_time) -
                                                                parseTimeToMinutes(ghostPreview.start_time)) /
                                                                60) *
                                                                HOUR_HEIGHT -
                                                                4,
                                                        ),
                                                    }}
                                                    className="pointer-events-none absolute right-1 left-1 z-20 rounded-xl border-2 border-dashed border-emerald-500 bg-emerald-500/18 p-2 text-emerald-950 shadow-md dark:text-emerald-50"
                                                >
                                                    <span className="flex items-center gap-1 text-[10px] font-semibold text-emerald-700 dark:text-emerald-200">
                                                        <Sparkles className="size-3 shrink-0" />
                                                        <span className="truncate">{ghostPreview.label}</span>
                                                    </span>
                                                    <p className="truncate text-xs font-semibold">
                                                        {ghostPreview.classItem.subject_code}
                                                    </p>
                                                </div>
                                            )}
                                </div>
                            );
                        })}
                    </div>
                </div>
            </div>

            <FloatingContextMenu
                position={contextTarget ? { x: contextTarget.x, y: contextTarget.y } : null}
                onClose={() => setContextTarget(null)}
            >
                {contextTarget?.kind === "session" ? (() => {
                    const item = contextTarget.item;
                    const hasOverlap = item.conflicts.length > 0;
                    const mode = canonicalDeliveryMode(item.session.delivery_mode ?? item.classItem.delivery_mode);
                    const online = isOnlineDeliveryMode(mode);
                    const close = () => setContextTarget(null);
                    return (
                        <>
                            <FloatingMenuLabel
                                title={`${item.classItem.subject_code} · Sec ${item.classItem.section}`}
                                subtitle={`${item.session.day_of_week} · ${formatTimeRange(item.startMin, item.endMin)}`}
                            />
                            <FloatingMenuSeparator />
                            <FloatingMenuItem
                                icon={<Eye className="size-3.5" />}
                                label="View details & edit"
                                onSelect={() => {
                                    close();
                                    onSelectSession(item);
                                }}
                            />
                            <FloatingMenuItem
                                icon={<Pencil className="size-3.5" />}
                                label={hasOverlap ? "Resolve overlap" : "Edit time & room"}
                                onSelect={() => {
                                    close();
                                    onSelectSession(item);
                                }}
                            />
                            <FloatingMenuItem
                                icon={<CalendarDays className="size-3.5" />}
                                label="Move 1 hour later"
                                onSelect={() => {
                                    close();
                                    shiftSessionHour(item, 60);
                                }}
                            />
                            {!online && (
                                <FloatingMenuItem
                                    icon={<Monitor className="size-3.5" />}
                                    label="Switch to Online"
                                    onSelect={() => {
                                        close();
                                        void studio.updateScheduleBlockDirect(
                                            item.classItem,
                                            item.session,
                                            {
                                                delivery_mode: studio.policy.default_online_delivery_mode,
                                                room_id: null,
                                            },
                                            `Switched ${item.classItem.subject_code} to Online`,
                                        );
                                    }}
                                />
                            )}
                            <FloatingMenuSeparator />
                            <FloatingMenuItem
                                icon={<ArrowUpRight className="size-3.5" />}
                                label="Open class record"
                                onSelect={() => {
                                    close();
                                    router.visit(showClass.url(item.classItem.id));
                                }}
                            />
                            {item.session.id !== undefined && (
                                <>
                                    <FloatingMenuSeparator />
                                    <FloatingMenuItem
                                        icon={<Trash2 className="size-3.5" />}
                                        label="Remove session"
                                        destructive
                                        onSelect={() => {
                                            close();
                                            void studio.deleteScheduleBlock(item.session.id!, false);
                                        }}
                                    />
                                </>
                            )}
                        </>
                    );
                })() : contextTarget?.kind === "empty" ? (() => {
                    const slot = contextTarget;
                    const close = () => setContextTarget(null);
                    return (
                        <>
                            <FloatingMenuLabel
                                title={slot.day}
                                subtitle={formatTimeRange(slot.startMin, slot.endMin)}
                            />
                            <FloatingMenuSeparator />
                            <FloatingMenuItem
                                icon={<Plus className="size-3.5" />}
                                label="Schedule a class here"
                                onSelect={() => {
                                    close();
                                    onClickEmptySlot(
                                        slot.day,
                                        minutesToApiTime(slot.startMin),
                                        minutesToApiTime(slot.endMin),
                                    );
                                }}
                            />
                            {onSwitchToList && (
                                <FloatingMenuItem
                                    icon={<List className="size-3.5" />}
                                    label="Switch to List view"
                                    onSelect={() => {
                                        close();
                                        onSwitchToList();
                                    }}
                                />
                            )}
                            {studio.history.canUndo && (
                                <FloatingMenuItem
                                    icon={<Undo2 className="size-3.5" />}
                                    label="Undo last move"
                                    onSelect={() => {
                                        close();
                                        void studio.history.undo();
                                    }}
                                />
                            )}
                            <FloatingMenuSeparator />
                            <FloatingMenuItem
                                icon={<Sun className="size-3.5" />}
                                label={
                                    studio.policy.allow_sunday_scheduling
                                        ? "Hide Sunday classes"
                                        : "Include Sunday classes"
                                }
                                onSelect={() => {
                                    close();
                                    void studio.toggleSundayScheduling();
                                }}
                            />
                        </>
                    );
                })() : null}
            </FloatingContextMenu>
        </div>
    );
}
