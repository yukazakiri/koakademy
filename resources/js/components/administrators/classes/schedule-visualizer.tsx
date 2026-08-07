import {
    ContextMenu,
    ContextMenuContent,
    ContextMenuItem,
    ContextMenuLabel,
    ContextMenuSeparator,
    ContextMenuShortcut,
    ContextMenuSub,
    ContextMenuSubContent,
    ContextMenuSubTrigger,
    ContextMenuTrigger,
} from "@/components/ui/context-menu";
import { ScrollArea } from "@/components/ui/scroll-area";
import {
    DndContext,
    type DragEndEvent,
    type DragMoveEvent,
    type DragStartEvent,
    MouseSensor,
    pointerWithin,
    TouchSensor,
    useDraggable,
    useDroppable,
    useSensor,
    useSensors,
} from "@dnd-kit/core";
import { CSS } from "@dnd-kit/utilities";
import { ArrowUpDown, CalendarDays, Check, Clock, Copy, GripVertical, MapPin, Minus, Plus, Trash2 } from "lucide-react";
import { type CSSProperties, type PointerEvent as ReactPointerEvent, useMemo, useRef, useState } from "react";

type EntityOption = { id: string | number; label: string };
type DayOption = { value: string; label: string };

export type ClassSchedule = {
    id?: number;
    day_of_week: string;
    start_time: string;
    end_time: string;
    room_id: number;
    room?: EntityOption | null;
};

export interface ScheduleVisualizerProps {
    schedules: ClassSchedule[];
    rooms: EntityOption[];
    dayOptions?: DayOption[];
    className?: string;
    onScheduleChange?: (index: number, newSchedule: ClassSchedule) => void;
    onDuplicateSchedule?: (index: number) => void;
    onRemoveSchedule?: (index: number) => void;
    canRemoveSchedule?: boolean;
}

type PreviewState = {
    index: number;
    schedule: ClassSchedule;
    type: "move" | "resize" | "resize-top";
} | null;

type ScheduleDragData = {
    type: "schedule";
    index: number;
    schedule: ClassSchedule;
};

type ScheduleLayout = {
    index: number;
    schedule: ClassSchedule;
    dayIndex: number;
    startMinutes: number;
    endMinutes: number;
    top: number;
    height: number;
    lane: number;
    laneCount: number;
};

const DAYS = ["Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday", "Sunday"];
const START_HOUR = 7;
const END_HOUR = 21;
const HOUR_ROWS = Array.from({ length: END_HOUR - START_HOUR }, (_, i) => START_HOUR + i);
const HOUR_HEIGHT = 68;
const SNAP_MINUTES = 15;
const MIN_BLOCK_HEIGHT = 34;
const TIME_COLUMN_WIDTH = 64;
const GRID_START_MINUTES = START_HOUR * 60;
const GRID_END_MINUTES = END_HOUR * 60;
const GRID_HEIGHT = (END_HOUR - START_HOUR) * HOUR_HEIGHT;

function clamp(value: number, min: number, max: number): number {
    return Math.min(Math.max(value, min), max);
}

function formatHour(hour: number): string {
    const ampm = hour >= 12 ? "PM" : "AM";
    const h = hour % 12 || 12;
    return `${h} ${ampm}`;
}

function parseTimeToMinutes(timeStr: string): number {
    const [rawHour, rawMinute] = timeStr.split(":").map(Number);
    const hour = Number.isFinite(rawHour) ? rawHour : START_HOUR;
    const minute = Number.isFinite(rawMinute) ? rawMinute : 0;
    return clamp(hour * 60 + minute, GRID_START_MINUTES, GRID_END_MINUTES);
}

function formatMinutesToTime(totalMinutes: number): string {
    const clampedMinutes = clamp(Math.round(totalMinutes), GRID_START_MINUTES, GRID_END_MINUTES);
    const hour = Math.floor(clampedMinutes / 60);
    const minute = clampedMinutes % 60;
    return `${String(hour).padStart(2, "0")}:${String(minute).padStart(2, "0")}`;
}

function formatTimeOnly(timeStr: string): string {
    const totalMinutes = parseTimeToMinutes(timeStr);
    const hour = Math.floor(totalMinutes / 60);
    const minute = totalMinutes % 60;
    const ampm = hour >= 12 ? "PM" : "AM";
    const h = hour % 12 || 12;
    return `${h}:${String(minute).padStart(2, "0")} ${ampm}`;
}

function snapMinutes(totalMinutes: number): number {
    return Math.round(totalMinutes / SNAP_MINUTES) * SNAP_MINUTES;
}

function pixelsToMinutes(pixelOffset: number): number {
    return (pixelOffset / HOUR_HEIGHT) * 60;
}

function calculateTopFromMinutes(minutes: number): number {
    return ((minutes - GRID_START_MINUTES) / 60) * HOUR_HEIGHT;
}

function calculateTop(timeStr: string): number {
    return calculateTopFromMinutes(parseTimeToMinutes(timeStr));
}

function calculateHeight(startStr: string, endStr: string): number {
    const start = parseTimeToMinutes(startStr);
    const end = clamp(Math.max(parseTimeToMinutes(endStr), start + SNAP_MINUTES), start + SNAP_MINUTES, GRID_END_MINUTES);
    return Math.max(MIN_BLOCK_HEIGHT, ((end - start) / 60) * HOUR_HEIGHT);
}

function getDurationMins(startStr: string, endStr: string): number {
    const start = parseTimeToMinutes(startStr);
    const end = parseTimeToMinutes(endStr);
    return clamp(end - start > 0 ? end - start : 60, SNAP_MINUTES, GRID_END_MINUTES - GRID_START_MINUTES);
}

function getMovedSchedule(schedule: ClassSchedule, deltaY: number, dayOfWeek: string): ClassSchedule {
    const duration = getDurationMins(schedule.start_time, schedule.end_time);
    const latestStart = Math.max(GRID_START_MINUTES, GRID_END_MINUTES - duration);
    const nextStart = clamp(snapMinutes(parseTimeToMinutes(schedule.start_time) + pixelsToMinutes(deltaY)), GRID_START_MINUTES, latestStart);

    return {
        ...schedule,
        day_of_week: dayOfWeek,
        start_time: formatMinutesToTime(nextStart),
        end_time: formatMinutesToTime(nextStart + duration),
    };
}

function getShiftedSchedule(schedule: ClassSchedule, deltaMinutes: number): ClassSchedule {
    const duration = getDurationMins(schedule.start_time, schedule.end_time);
    const latestStart = Math.max(GRID_START_MINUTES, GRID_END_MINUTES - duration);
    const nextStart = clamp(parseTimeToMinutes(schedule.start_time) + deltaMinutes, GRID_START_MINUTES, latestStart);

    return {
        ...schedule,
        start_time: formatMinutesToTime(nextStart),
        end_time: formatMinutesToTime(nextStart + duration),
    };
}

function getBottomResizedSchedule(schedule: ClassSchedule, deltaY: number): ClassSchedule {
    const start = parseTimeToMinutes(schedule.start_time);
    const end = parseTimeToMinutes(schedule.end_time);
    const nextEnd = clamp(snapMinutes(end + pixelsToMinutes(deltaY)), start + SNAP_MINUTES, GRID_END_MINUTES);
    return {
        ...schedule,
        end_time: formatMinutesToTime(nextEnd),
    };
}

function getDurationAdjustedSchedule(schedule: ClassSchedule, deltaMinutes: number): ClassSchedule {
    const start = parseTimeToMinutes(schedule.start_time);
    const end = parseTimeToMinutes(schedule.end_time);
    const nextEnd = clamp(end + deltaMinutes, start + SNAP_MINUTES, GRID_END_MINUTES);
    return {
        ...schedule,
        end_time: formatMinutesToTime(nextEnd),
    };
}

function getTopResizedSchedule(schedule: ClassSchedule, deltaY: number): ClassSchedule {
    const start = parseTimeToMinutes(schedule.start_time);
    const end = parseTimeToMinutes(schedule.end_time);
    const nextStart = clamp(snapMinutes(start + pixelsToMinutes(deltaY)), GRID_START_MINUTES, end - SNAP_MINUTES);
    return {
        ...schedule,
        start_time: formatMinutesToTime(nextStart),
    };
}

function getFallbackDayFromDelta(dayOfWeek: string, deltaX: number, gridWidth: number): string {
    const currentDayIndex = DAYS.indexOf(dayOfWeek);
    if (currentDayIndex === -1 || gridWidth <= 0) {
        return dayOfWeek;
    }

    const dayWidth = gridWidth / DAYS.length;
    const nextDayIndex = clamp(currentDayIndex + Math.round(deltaX / dayWidth), 0, DAYS.length - 1);
    return DAYS[nextDayIndex];
}

function getOverDay(over: DragMoveEvent["over"] | DragEndEvent["over"]): string | null {
    const day = over?.data.current?.day;
    return typeof day === "string" ? day : null;
}

function getScheduleDragData(data: unknown): ScheduleDragData | null {
    if (typeof data !== "object" || data === null) {
        return null;
    }

    const maybeData = data as Partial<ScheduleDragData>;
    if (maybeData.type !== "schedule" || typeof maybeData.index !== "number" || !maybeData.schedule) {
        return null;
    }

    return maybeData as ScheduleDragData;
}

function buildScheduleLayouts(schedules: ClassSchedule[]): ScheduleLayout[] {
    const layouts: ScheduleLayout[] = [];

    DAYS.forEach((day, dayIndex) => {
        const candidates = schedules
            .map((schedule, index) => {
                if (schedule.day_of_week !== day || !schedule.start_time || !schedule.end_time) {
                    return null;
                }

                const startMinutes = parseTimeToMinutes(schedule.start_time);
                const endMinutes = Math.max(parseTimeToMinutes(schedule.end_time), startMinutes + SNAP_MINUTES);

                return {
                    index,
                    schedule,
                    dayIndex,
                    startMinutes,
                    endMinutes: clamp(endMinutes, startMinutes + SNAP_MINUTES, GRID_END_MINUTES),
                    top: calculateTop(schedule.start_time),
                    height: calculateHeight(schedule.start_time, schedule.end_time),
                };
            })
            .filter((candidate): candidate is Omit<ScheduleLayout, "lane" | "laneCount"> => candidate !== null)
            .sort((a, b) => a.startMinutes - b.startMinutes || a.endMinutes - b.endMinutes);

        let cluster: Array<Omit<ScheduleLayout, "lane" | "laneCount">> = [];
        let clusterEnd = -Infinity;

        const flushCluster = () => {
            if (cluster.length === 0) {
                return;
            }

            const laneEnds: number[] = [];
            const assigned = cluster.map((candidate) => {
                let lane = laneEnds.findIndex((laneEnd) => laneEnd <= candidate.startMinutes);
                if (lane === -1) {
                    lane = laneEnds.length;
                    laneEnds.push(candidate.endMinutes);
                } else {
                    laneEnds[lane] = candidate.endMinutes;
                }

                return { ...candidate, lane };
            });

            const laneCount = Math.max(1, laneEnds.length);
            assigned.forEach((candidate) => {
                layouts.push({
                    ...candidate,
                    laneCount,
                });
            });

            cluster = [];
            clusterEnd = -Infinity;
        };

        candidates.forEach((candidate) => {
            if (cluster.length > 0 && candidate.startMinutes >= clusterEnd) {
                flushCluster();
            }

            cluster.push(candidate);
            clusterEnd = Math.max(clusterEnd, candidate.endMinutes);
        });

        flushCluster();
    });

    return layouts.sort((a, b) => a.index - b.index);
}

function DayDropColumn({ day }: { day: string }) {
    const { setNodeRef, isOver } = useDroppable({
        id: `day-${day}`,
        data: { day },
    });

    return (
        <div
            ref={setNodeRef}
            data-testid={`schedule-day-${day.toLowerCase()}`}
            className={`border-border/50 relative border-r transition-colors last:border-r-0 ${
                isOver ? "bg-primary/8 dark:bg-primary/12" : "bg-background/30"
            }`}
        >
            {HOUR_ROWS.map((hour) => (
                <div key={hour} style={{ height: `${HOUR_HEIGHT}px` }} className="border-border/30 w-full border-b" />
            ))}
        </div>
    );
}

function ScheduleBlock({
    layout,
    dayOptions,
    rooms,
    roomName,
    canEdit,
    canRemoveSchedule,
    activeDragIndex,
    onScheduleChange,
    onDuplicateSchedule,
    onRemoveSchedule,
    onResizePointerDown,
}: {
    layout: ScheduleLayout;
    dayOptions: DayOption[];
    rooms: EntityOption[];
    roomName: string;
    canEdit: boolean;
    canRemoveSchedule: boolean;
    activeDragIndex: number | null;
    onScheduleChange?: (index: number, newSchedule: ClassSchedule) => void;
    onDuplicateSchedule?: (index: number) => void;
    onRemoveSchedule?: (index: number) => void;
    onResizePointerDown: (index: number, schedule: ClassSchedule, direction: "top" | "bottom", event: ReactPointerEvent<HTMLDivElement>) => void;
}) {
    const [isContextMenuOpen, setIsContextMenuOpen] = useState(false);
    const { attributes, listeners, setNodeRef, transform, isDragging } = useDraggable({
        id: `schedule-${layout.index}`,
        data: {
            type: "schedule",
            index: layout.index,
            schedule: layout.schedule,
        } satisfies ScheduleDragData,
        disabled: !canEdit || isContextMenuOpen,
    });

    const dayWidth = 100 / DAYS.length;
    const laneWidth = dayWidth / layout.laneCount;
    const leftPercent = layout.dayIndex * dayWidth + layout.lane * laneWidth;
    const isActiveDrag = activeDragIndex === layout.index;

    const blockStyle: CSSProperties = {
        top: `${layout.top}px`,
        height: `${layout.height}px`,
        left: `calc(${leftPercent}% + 4px)`,
        width: `calc(${laneWidth}% - 8px)`,
        zIndex: isActiveDrag ? 50 : 20 + layout.lane,
        transform: CSS.Translate.toString(transform),
        opacity: isDragging ? 0.32 : 1,
        borderColor: "hsl(var(--primary) / 0.38)",
        backgroundColor: "hsl(var(--primary) / 0.09)",
    };

    const duration = getDurationMins(layout.schedule.start_time, layout.schedule.end_time);
    const canShiftEarlier = parseTimeToMinutes(layout.schedule.start_time) > GRID_START_MINUTES;
    const canShiftLater = parseTimeToMinutes(layout.schedule.end_time) < GRID_END_MINUTES;
    const canShorten = duration > SNAP_MINUTES;
    const canExtend = parseTimeToMinutes(layout.schedule.end_time) < GRID_END_MINUTES;

    const scheduleBlock = (
        <div
            ref={setNodeRef}
            data-testid={`schedule-block-${layout.index}`}
            {...attributes}
            {...listeners}
            className={`group focus-visible:ring-ring absolute flex touch-none flex-col overflow-hidden rounded-md border shadow-sm transition-[box-shadow,opacity] outline-none focus-visible:ring-2 ${
                canEdit ? "cursor-grab active:cursor-grabbing" : ""
            } ${isActiveDrag ? "ring-primary/50 shadow-xl ring-2" : ""}`}
            style={blockStyle}
        >
            <div className="bg-primary/70 pointer-events-none absolute top-0 bottom-0 left-0 w-1 rounded-l-md" />

            <div className="pointer-events-none flex h-full min-h-0 flex-col overflow-hidden p-2 pl-3 select-none">
                <div className="text-primary/80 group-hover:text-primary mb-0.5 flex min-w-0 items-center gap-1 text-[11px] leading-tight font-semibold transition-colors">
                    {canEdit ? <GripVertical className="h-3 w-3 shrink-0 opacity-70" /> : <Clock className="h-3 w-3 shrink-0" />}
                    <span className="truncate">
                        {formatTimeOnly(layout.schedule.start_time)} - {formatTimeOnly(layout.schedule.end_time)}
                    </span>
                </div>
                <div className="text-muted-foreground flex min-w-0 items-center gap-1 text-[10px] opacity-90">
                    <MapPin className="h-3 w-3 shrink-0" />
                    <span className="truncate">{roomName}</span>
                </div>
            </div>

            {canEdit && (
                <>
                    <div
                        className="hover:bg-primary/20 absolute top-0 right-0 left-0 z-10 flex h-3 cursor-ns-resize items-center justify-center rounded-t-md opacity-0 transition-opacity group-hover:opacity-100"
                        onPointerDown={(event) => onResizePointerDown(layout.index, layout.schedule, "top", event)}
                    >
                        <div className="bg-primary/40 h-1 w-8 rounded-full" />
                    </div>
                    <div
                        className="hover:bg-primary/20 absolute right-0 bottom-0 left-0 z-10 flex h-3 cursor-ns-resize items-center justify-center rounded-b-md opacity-0 transition-opacity group-hover:opacity-100"
                        onPointerDown={(event) => onResizePointerDown(layout.index, layout.schedule, "bottom", event)}
                    >
                        <div className="bg-primary/40 h-1 w-8 rounded-full" />
                    </div>
                </>
            )}
        </div>
    );

    if (!canEdit) {
        return scheduleBlock;
    }

    return (
        <ContextMenu onOpenChange={setIsContextMenuOpen}>
            <ContextMenuTrigger asChild>{scheduleBlock}</ContextMenuTrigger>
            <ContextMenuContent className="w-64">
                <ContextMenuLabel>Schedule Block {layout.index + 1}</ContextMenuLabel>

                <ContextMenuSub>
                    <ContextMenuSubTrigger className="gap-2">
                        <CalendarDays className="h-4 w-4" />
                        Move to day
                    </ContextMenuSubTrigger>
                    <ContextMenuSubContent className="w-44">
                        {dayOptions.map((day) => (
                            <ContextMenuItem
                                key={day.value}
                                disabled={layout.schedule.day_of_week === day.value}
                                onSelect={() => onScheduleChange?.(layout.index, { ...layout.schedule, day_of_week: day.value })}
                            >
                                {layout.schedule.day_of_week === day.value ? <Check className="h-4 w-4" /> : <span className="w-4" />}
                                {day.label}
                            </ContextMenuItem>
                        ))}
                    </ContextMenuSubContent>
                </ContextMenuSub>

                <ContextMenuSub>
                    <ContextMenuSubTrigger className="gap-2">
                        <MapPin className="h-4 w-4" />
                        Move to room
                    </ContextMenuSubTrigger>
                    <ContextMenuSubContent className="max-h-64 w-52 overflow-y-auto">
                        {rooms.map((room) => (
                            <ContextMenuItem
                                key={room.id}
                                disabled={Number(layout.schedule.room_id) === Number(room.id)}
                                onSelect={() =>
                                    onScheduleChange?.(layout.index, {
                                        ...layout.schedule,
                                        room: undefined,
                                        room_id: Number(room.id),
                                    })
                                }
                            >
                                {Number(layout.schedule.room_id) === Number(room.id) ? <Check className="h-4 w-4" /> : <span className="w-4" />}
                                {room.label}
                            </ContextMenuItem>
                        ))}
                    </ContextMenuSubContent>
                </ContextMenuSub>

                <ContextMenuSeparator />

                <ContextMenuItem
                    disabled={!canShiftEarlier}
                    onSelect={() => onScheduleChange?.(layout.index, getShiftedSchedule(layout.schedule, -SNAP_MINUTES))}
                >
                    <ArrowUpDown className="h-4 w-4" />
                    Move 15 min earlier
                    <ContextMenuShortcut>Alt+Up</ContextMenuShortcut>
                </ContextMenuItem>
                <ContextMenuItem
                    disabled={!canShiftLater}
                    onSelect={() => onScheduleChange?.(layout.index, getShiftedSchedule(layout.schedule, SNAP_MINUTES))}
                >
                    <ArrowUpDown className="h-4 w-4" />
                    Move 15 min later
                    <ContextMenuShortcut>Alt+Dn</ContextMenuShortcut>
                </ContextMenuItem>
                <ContextMenuItem
                    disabled={!canShorten}
                    onSelect={() => onScheduleChange?.(layout.index, getDurationAdjustedSchedule(layout.schedule, -SNAP_MINUTES))}
                >
                    <Minus className="h-4 w-4" />
                    Shorten by 15 min
                    <ContextMenuShortcut>Shift+-</ContextMenuShortcut>
                </ContextMenuItem>
                <ContextMenuItem
                    disabled={!canExtend}
                    onSelect={() => onScheduleChange?.(layout.index, getDurationAdjustedSchedule(layout.schedule, SNAP_MINUTES))}
                >
                    <Plus className="h-4 w-4" />
                    Extend by 15 min
                    <ContextMenuShortcut>Shift++</ContextMenuShortcut>
                </ContextMenuItem>

                <ContextMenuSeparator />

                {onDuplicateSchedule && (
                    <ContextMenuItem onSelect={() => onDuplicateSchedule(layout.index)}>
                        <Copy className="h-4 w-4" />
                        Duplicate block
                        <ContextMenuShortcut>Ctrl+D</ContextMenuShortcut>
                    </ContextMenuItem>
                )}
                {onRemoveSchedule && (
                    <ContextMenuItem variant="destructive" disabled={!canRemoveSchedule} onSelect={() => onRemoveSchedule(layout.index)}>
                        <Trash2 className="h-4 w-4" />
                        Remove block
                        <ContextMenuShortcut>Del</ContextMenuShortcut>
                    </ContextMenuItem>
                )}
            </ContextMenuContent>
        </ContextMenu>
    );
}

export function ClassScheduleVisualizer({
    schedules,
    rooms,
    dayOptions,
    className = "",
    onScheduleChange,
    onDuplicateSchedule,
    onRemoveSchedule,
    canRemoveSchedule = true,
}: ScheduleVisualizerProps) {
    const dayGridRef = useRef<HTMLDivElement>(null);
    const [activeDragIndex, setActiveDragIndex] = useState<number | null>(null);
    const [previewState, setPreviewState] = useState<PreviewState>(null);

    const sensors = useSensors(
        useSensor(MouseSensor, { activationConstraint: { distance: 6 } }),
        useSensor(TouchSensor, { activationConstraint: { delay: 150, tolerance: 6 } }),
    );

    const layouts = useMemo(() => buildScheduleLayouts(schedules), [schedules]);
    const menuDayOptions = useMemo<DayOption[]>(
        () => (dayOptions?.length ? dayOptions : DAYS.map((day) => ({ value: day, label: day }))),
        [dayOptions],
    );

    const resolveDragDay = (schedule: ClassSchedule, deltaX: number, over: DragMoveEvent["over"] | DragEndEvent["over"]): string => {
        return getOverDay(over) ?? getFallbackDayFromDelta(schedule.day_of_week, deltaX, dayGridRef.current?.clientWidth ?? 0);
    };

    const updateDragPreview = (event: DragMoveEvent | DragEndEvent): ClassSchedule | null => {
        const data = getScheduleDragData(event.active.data.current);
        if (!data) {
            return null;
        }

        const day = resolveDragDay(data.schedule, event.delta.x, event.over);
        const nextSchedule = getMovedSchedule(data.schedule, event.delta.y, day);
        setPreviewState({ index: data.index, schedule: nextSchedule, type: "move" });
        return nextSchedule;
    };

    const clearInteractionState = () => {
        setActiveDragIndex(null);
        setPreviewState(null);
    };

    const handleDragStart = (event: DragStartEvent) => {
        const data = getScheduleDragData(event.active.data.current);
        if (!data) {
            return;
        }
        setActiveDragIndex(data.index);
        setPreviewState({ index: data.index, schedule: data.schedule, type: "move" });
    };

    const handleDragMove = (event: DragMoveEvent) => {
        updateDragPreview(event);
    };

    const handleDragEnd = (event: DragEndEvent) => {
        const data = getScheduleDragData(event.active.data.current);
        const nextSchedule = updateDragPreview(event);
        clearInteractionState();

        if (!data || !nextSchedule || !onScheduleChange) {
            return;
        }

        onScheduleChange(data.index, nextSchedule);
    };

    const handleResizePointerDown = (
        index: number,
        schedule: ClassSchedule,
        direction: "top" | "bottom",
        event: ReactPointerEvent<HTMLDivElement>,
    ) => {
        event.preventDefault();
        event.stopPropagation();

        const startY = event.clientY;
        const getNextSchedule = (clientY: number): ClassSchedule => {
            const deltaY = clientY - startY;
            return direction === "top" ? getTopResizedSchedule(schedule, deltaY) : getBottomResizedSchedule(schedule, deltaY);
        };

        setActiveDragIndex(index);
        setPreviewState({ index, schedule: getNextSchedule(startY), type: direction === "top" ? "resize-top" : "resize" });

        const handlePointerMove = (moveEvent: PointerEvent) => {
            moveEvent.preventDefault();
            setPreviewState({ index, schedule: getNextSchedule(moveEvent.clientY), type: direction === "top" ? "resize-top" : "resize" });
        };

        const handlePointerUp = (upEvent: PointerEvent) => {
            document.removeEventListener("pointermove", handlePointerMove);
            document.removeEventListener("pointerup", handlePointerUp);

            const nextSchedule = getNextSchedule(upEvent.clientY);
            clearInteractionState();
            onScheduleChange?.(index, nextSchedule);
        };

        document.addEventListener("pointermove", handlePointerMove);
        document.addEventListener("pointerup", handlePointerUp, { once: true });
    };

    const canEdit = Boolean(onScheduleChange);

    return (
        <div
            className={`bg-card/50 relative flex h-full min-h-[500px] w-full flex-col overflow-hidden rounded-lg border shadow-sm backdrop-blur-sm ${className}`}
        >
            <div className="bg-muted/40 sticky top-0 z-10 grid min-w-[760px] grid-cols-[64px_repeat(7,minmax(96px,1fr))] border-b">
                <div className="border-border/50 border-r p-2" />
                {DAYS.map((day) => (
                    <div
                        key={day}
                        className="text-muted-foreground border-border/50 bg-muted/20 truncate border-r p-3 text-center text-xs font-semibold tracking-wider uppercase last:border-r-0"
                    >
                        {day.substring(0, 3)}
                    </div>
                ))}
            </div>

            <DndContext
                sensors={sensors}
                collisionDetection={pointerWithin}
                onDragStart={handleDragStart}
                onDragMove={handleDragMove}
                onDragCancel={clearInteractionState}
                onDragEnd={handleDragEnd}
            >
                <ScrollArea className="min-h-[400px] flex-1">
                    <div className="relative min-w-[760px]" style={{ height: `${GRID_HEIGHT}px` }}>
                        <div className="absolute inset-0 grid grid-cols-[64px_repeat(7,minmax(96px,1fr))]">
                            <div className="border-border/50 bg-muted/10 border-r">
                                {HOUR_ROWS.map((hour) => (
                                    <div
                                        key={hour}
                                        style={{ height: `${HOUR_HEIGHT}px` }}
                                        className="border-border/30 text-muted-foreground/70 flex items-start justify-end border-b p-1 pr-2 text-[10px] font-medium"
                                    >
                                        {formatHour(hour)}
                                    </div>
                                ))}
                            </div>
                            {DAYS.map((day) => (
                                <DayDropColumn key={day} day={day} />
                            ))}
                        </div>

                        <div ref={dayGridRef} className="pointer-events-none absolute inset-y-0 right-0" style={{ left: `${TIME_COLUMN_WIDTH}px` }} />

                        {previewState && (
                            <div className="pointer-events-none absolute inset-y-0 right-0 z-40" style={{ left: `${TIME_COLUMN_WIDTH}px` }}>
                                {(() => {
                                    const schedule = previewState.schedule;
                                    const dayIndex = DAYS.indexOf(schedule.day_of_week);
                                    if (dayIndex === -1) {
                                        return null;
                                    }

                                    const dayWidth = 100 / DAYS.length;
                                    const top = calculateTop(schedule.start_time);
                                    const height = calculateHeight(schedule.start_time, schedule.end_time);

                                    return (
                                        <div
                                            className="border-primary/60 bg-primary/10 absolute flex flex-col items-center justify-start overflow-hidden rounded-md border-2 border-dashed pt-2 shadow-sm"
                                            style={{
                                                top: `${top}px`,
                                                height: `${height}px`,
                                                left: `calc(${dayIndex * dayWidth}% + 4px)`,
                                                width: `calc(${dayWidth}% - 8px)`,
                                            }}
                                        >
                                            <div className="bg-primary text-primary-foreground z-10 rounded px-2 py-0.5 text-[10px] font-bold whitespace-nowrap shadow-md">
                                                {schedule.day_of_week.substring(0, 3)} {formatTimeOnly(schedule.start_time)} -{" "}
                                                {formatTimeOnly(schedule.end_time)}
                                            </div>
                                        </div>
                                    );
                                })()}
                            </div>
                        )}

                        <div className="absolute inset-y-0 right-0" style={{ left: `${TIME_COLUMN_WIDTH}px` }}>
                            {layouts.length === 0 ? (
                                <div className="text-muted-foreground bg-background/70 absolute inset-x-6 top-8 rounded-md border border-dashed p-6 text-center text-sm">
                                    Add a block to start building this class schedule.
                                </div>
                            ) : (
                                layouts.map((layout) => {
                                    const roomName =
                                        rooms.find((room) => Number(room.id) === Number(layout.schedule.room_id))?.label ??
                                        layout.schedule.room?.label ??
                                        "TBA";

                                    return (
                                        <ScheduleBlock
                                            key={`${layout.index}-${layout.schedule.day_of_week}-${layout.schedule.start_time}-${layout.schedule.end_time}`}
                                            layout={layout}
                                            dayOptions={menuDayOptions}
                                            rooms={rooms}
                                            roomName={roomName}
                                            canEdit={canEdit}
                                            canRemoveSchedule={canRemoveSchedule}
                                            activeDragIndex={activeDragIndex}
                                            onScheduleChange={onScheduleChange}
                                            onDuplicateSchedule={onDuplicateSchedule}
                                            onRemoveSchedule={onRemoveSchedule}
                                            onResizePointerDown={handleResizePointerDown}
                                        />
                                    );
                                })
                            )}
                        </div>
                    </div>
                </ScrollArea>
            </DndContext>
        </div>
    );
}
