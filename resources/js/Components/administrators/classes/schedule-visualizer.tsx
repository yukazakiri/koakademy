import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from "@/components/ui/tooltip";
import { motion, PanInfo } from "framer-motion";
import { Clock, MapPin } from "lucide-react";
import { useRef, useState } from "react";

type EntityOption = { id: number; label: string };

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
    className?: string;
    onScheduleChange?: (index: number, newSchedule: ClassSchedule) => void;
}

type PreviewState = {
    index: number;
    schedule: ClassSchedule;
    type: "move" | "resize" | "resize-top";
} | null;

const DAYS = ["Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday", "Sunday"];
const START_HOUR = 7; // 7 AM
const END_HOUR = 21; // 9 PM
const HOURS = Array.from({ length: END_HOUR - START_HOUR + 1 }, (_, i) => START_HOUR + i);
const HOUR_HEIGHT = 68; // Use 68px to give ample space for text

function formatHour(hour: number) {
    const ampm = hour >= 12 ? "PM" : "AM";
    const h = hour % 12 || 12;
    return `${h} ${ampm}`;
}

function parseTime(timeStr: string) {
    if (!timeStr) return { hour: 0, minute: 0 };
    const [h, m] = timeStr.split(":").map(Number);
    return { hour: h ?? 0, minute: m ?? 0 };
}

function formatTimeOnly(timeStr: string) {
    if (!timeStr) return "";
    const { hour, minute } = parseTime(timeStr);
    const ampm = hour >= 12 ? "PM" : "AM";
    const h = hour % 12 || 12;
    const m = minute.toString().padStart(2, "0");
    return `${h}:${m} ${ampm}`;
}

export function ClassScheduleVisualizer({ schedules, rooms, className = "", onScheduleChange }: ScheduleVisualizerProps) {
    const containerRef = useRef<HTMLDivElement>(null);

    // Add some state to force un-hovering during drag
    const [isDragging, setIsDragging] = useState(false);
    const [activeDragIndex, setActiveDragIndex] = useState<number | null>(null);
    const [previewState, setPreviewState] = useState<PreviewState>(null);

    const calculateTop = (timeStr: string) => {
        const { hour, minute } = parseTime(timeStr);
        const normalizedHour = Math.max(START_HOUR, Math.min(END_HOUR, hour));
        return (normalizedHour - START_HOUR + minute / 60) * HOUR_HEIGHT;
    };

    const calculateHeight = (startStr: string, endStr: string) => {
        const start = parseTime(startStr);
        const end = parseTime(endStr);

        const startTotalHours = Math.max(START_HOUR, start.hour + start.minute / 60);
        let endTotalHours = end.hour + end.minute / 60;

        if (endTotalHours <= startTotalHours) {
            endTotalHours = startTotalHours + 1; // Default to 1 hour if invalid
        }

        const height = (endTotalHours - startTotalHours) * HOUR_HEIGHT;
        // Cap height at minimum 30px
        return Math.max(30, height);
    };

    // Snap to nearest 15 minutes
    const snapToTime = (yOffset: number) => {
        const totalHours = yOffset / HOUR_HEIGHT + START_HOUR;
        const hours = Math.floor(totalHours);
        const minutes = Math.round((totalHours - hours) * 60);

        // Snap minutes to nearest 15
        const snappedMinutes = Math.round(minutes / 15) * 15;

        let finalHour = hours;
        let finalMinute = snappedMinutes;

        if (finalMinute >= 60) {
            finalHour += 1;
            finalMinute -= 60;
        }

        finalHour = Math.max(START_HOUR, Math.min(END_HOUR, finalHour));
        return `${finalHour.toString().padStart(2, "0")}:${finalMinute.toString().padStart(2, "0")}`;
    };

    // Get duration in minutes between two time strings
    const getDurationMins = (startStr: string, endStr: string) => {
        const s = parseTime(startStr);
        const e = parseTime(endStr);
        return e.hour * 60 + e.minute - (s.hour * 60 + s.minute);
    };

    // Add minutes to a time string
    const addMinutes = (timeStr: string, mins: number) => {
        const { hour, minute } = parseTime(timeStr);
        const totalMins = hour * 60 + minute + mins;

        const newHour = Math.max(START_HOUR, Math.min(END_HOUR, Math.floor(totalMins / 60)));
        const newMinute = totalMins % 60;
        return `${newHour.toString().padStart(2, "0")}:${newMinute.toString().padStart(2, "0")}`;
    };

    const getNewDragSchedule = (schedule: ClassSchedule, info: PanInfo, containerRect: DOMRect): ClassSchedule => {
        const colWidth = containerRect.width / 7;
        const relativeX = info.point.x - containerRect.left;

        let newDayIndex = Math.floor(relativeX / colWidth);

        // Clamp to valid days
        newDayIndex = Math.max(0, Math.min(6, newDayIndex));
        const newDay = DAYS[newDayIndex];

        // Calculate new time based on Y offset
        const currentTop = calculateTop(schedule.start_time);
        const newTop = currentTop + info.offset.y;
        const newStartTime = snapToTime(Math.max(0, newTop));

        // Maintain duration
        const duration = getDurationMins(schedule.start_time, schedule.end_time);
        const newEndTime = addMinutes(newStartTime, duration > 0 ? duration : 60);

        return {
            ...schedule,
            day_of_week: newDay,
            start_time: newStartTime,
            end_time: newEndTime,
        };
    };

    const getNewResizeSchedule = (schedule: ClassSchedule, info: PanInfo): ClassSchedule => {
        // Height changes based on Y drag offset
        const currentHeight = calculateHeight(schedule.start_time, schedule.end_time);
        const newHeight = Math.max(30, currentHeight + info.offset.y); // minimum 30px

        const durationHours = newHeight / HOUR_HEIGHT;
        const durationMins = Math.round(durationHours * 60);

        // Snap duration to nearest 15 mins
        const snappedMins = Math.max(15, Math.round(durationMins / 15) * 15);
        let newEndTime = addMinutes(schedule.start_time, snappedMins);

        // Clamp to max 22:00
        const endMins = getDurationMins(START_HOUR + ":00", newEndTime);
        const maxEndMins = getDurationMins(START_HOUR + ":00", END_HOUR + ":00");
        if (endMins > maxEndMins) newEndTime = END_HOUR + ":00";

        return {
            ...schedule,
            end_time: newEndTime,
        };
    };

    const getNewTopResizeSchedule = (schedule: ClassSchedule, info: PanInfo): ClassSchedule => {
        // Top position changes based on Y drag offset
        const currentTop = calculateTop(schedule.start_time);
        const newTop = Math.max(0, currentTop + info.offset.y);

        let newStartTime = snapToTime(newTop);

        // Ensure duration remains at least 15 mins (start time doesn't cross end time)
        const currentEndMins = getDurationMins(START_HOUR + ":00", schedule.end_time);
        const proposedStartMins = getDurationMins(START_HOUR + ":00", newStartTime);

        if (proposedStartMins > currentEndMins - 15) {
            newStartTime = addMinutes(schedule.end_time, -15);
        }

        return {
            ...schedule,
            start_time: newStartTime,
        };
    };

    const handleDrag = (index: number, schedule: ClassSchedule, info: PanInfo) => {
        const container = containerRef.current;
        if (!container) return;
        const newSchedule = getNewDragSchedule(schedule, info, container.getBoundingClientRect());
        setPreviewState({ index, schedule: newSchedule, type: "move" });
    };

    const handleDragEnd = (index: number, schedule: ClassSchedule, info: PanInfo) => {
        setIsDragging(false);
        setActiveDragIndex(null);
        setPreviewState(null);
        if (!onScheduleChange) return;

        const container = containerRef.current;
        if (!container) return;

        const newSchedule = getNewDragSchedule(schedule, info, container.getBoundingClientRect());
        onScheduleChange(index, newSchedule);
    };

    const handleResizeDrag = (index: number, schedule: ClassSchedule, info: PanInfo) => {
        const newSchedule = getNewResizeSchedule(schedule, info);
        setPreviewState({ index, schedule: newSchedule, type: "resize" });
    };

    const handleResizeEnd = (index: number, schedule: ClassSchedule, info: PanInfo) => {
        setIsDragging(false);
        setActiveDragIndex(null);
        setPreviewState(null);
        if (!onScheduleChange) return;

        const newSchedule = getNewResizeSchedule(schedule, info);
        onScheduleChange(index, newSchedule);
    };

    const handleTopResizeDrag = (index: number, schedule: ClassSchedule, info: PanInfo) => {
        const newSchedule = getNewTopResizeSchedule(schedule, info);
        setPreviewState({ index, schedule: newSchedule, type: "resize-top" });
    };

    const handleTopResizeEnd = (index: number, schedule: ClassSchedule, info: PanInfo) => {
        setIsDragging(false);
        setActiveDragIndex(null);
        setPreviewState(null);
        if (!onScheduleChange) return;

        const newSchedule = getNewTopResizeSchedule(schedule, info);
        onScheduleChange(index, newSchedule);
    };
    return (
        <div
            className={`bg-card/50 relative flex h-full min-h-[500px] w-full flex-col overflow-hidden rounded-xl border shadow-sm backdrop-blur-sm ${className}`}
        >
            {/* Header */}
            <div className="bg-muted/40 sticky top-0 z-10 grid grid-cols-[60px_repeat(7,1fr)] border-b">
                <div className="border-border/50 border-r p-2"></div>
                {DAYS.map((day) => (
                    <div
                        key={day}
                        className="text-muted-foreground border-border/50 bg-muted/20 truncate border-r p-3 text-center text-xs font-semibold tracking-wider uppercase last:border-r-0"
                    >
                        {day.substring(0, 3)}
                    </div>
                ))}
            </div>

            {/* Body container */}
            <div className="relative min-h-[400px] flex-1 overflow-x-hidden overflow-y-auto">
                {/* Grid background lines */}
                <div className="absolute top-0 left-0 grid w-full grid-cols-[60px_repeat(7,1fr)]">
                    <div className="border-border/50 bg-muted/10 border-r">
                        {HOURS.map((hour) => (
                            <div
                                key={hour}
                                style={{ height: `${HOUR_HEIGHT}px` }}
                                className="border-border/30 text-muted-foreground/70 flex items-start justify-end border-b p-1 pt-1 pr-2 text-[10px] font-medium"
                            >
                                {formatHour(hour)}
                            </div>
                        ))}
                    </div>
                    {DAYS.map((day) => (
                        <div key={day} className="border-border/50 relative border-r last:border-r-0">
                            {HOURS.map((hour) => (
                                <div key={hour} style={{ height: `${HOUR_HEIGHT}px` }} className="border-border/30 w-full border-b"></div>
                            ))}
                        </div>
                    ))}
                </div>

                {/* Ghost Preview Overlay */}
                {previewState && (
                    <div className="pointer-events-none absolute top-0 left-0 z-40 grid h-full w-full grid-cols-[60px_1fr]">
                        <div />
                        <div className="relative h-[1500px] w-full">
                            {(() => {
                                const s = previewState.schedule;
                                const dayIndex = DAYS.indexOf(s.day_of_week);
                                const top = calculateTop(s.start_time);
                                const height = calculateHeight(s.start_time, s.end_time);
                                const leftPercent = (dayIndex / 7) * 100;
                                const widthPercent = 100 / 7;

                                return (
                                    <div
                                        className="border-primary/60 bg-primary/10 absolute flex flex-col items-center justify-start overflow-hidden rounded-lg border-2 border-dashed pt-2 shadow-sm backdrop-blur-[1px] transition-all duration-75"
                                        style={{
                                            top: `${top}px`,
                                            height: `${height}px`,
                                            left: `${leftPercent}%`,
                                            width: `calc(${widthPercent}% - 8px)`,
                                        }}
                                    >
                                        <div className="bg-primary text-primary-foreground z-10 rounded px-2 py-0.5 text-[10px] font-bold whitespace-nowrap shadow-md">
                                            {s.day_of_week.substring(0, 3)} {formatTimeOnly(s.start_time)} - {formatTimeOnly(s.end_time)}
                                        </div>
                                        {previewState.type === "resize" && (
                                            <div className="text-primary/80 bg-background/50 z-10 mt-1 rounded px-1 text-[10px] font-medium">
                                                {formatTimeOnly(s.end_time)}
                                            </div>
                                        )}
                                        {previewState.type === "resize-top" && (
                                            <div className="text-primary/80 bg-background/50 z-10 mt-1 rounded px-1 text-[10px] font-medium">
                                                {formatTimeOnly(s.start_time)}
                                            </div>
                                        )}
                                    </div>
                                );
                            })()}
                        </div>
                    </div>
                )}
                {/* Schedule Blocks (absolute positioned) */}
                <div className="pointer-events-none absolute top-0 left-0 grid h-full w-full grid-cols-[60px_1fr]">
                    <div /> {/* Time column offset */}
                    <div className="relative h-[1500px] w-full" ref={containerRef}>
                        {schedules.map((s, index) => {
                            // Only render if it has valid times
                            if (!s.start_time || !s.end_time) return null;

                            const dayIndex = DAYS.indexOf(s.day_of_week);
                            if (dayIndex === -1) return null;

                            const top = calculateTop(s.start_time);
                            const height = calculateHeight(s.start_time, s.end_time);
                            const roomName = rooms.find((r) => r.id === s.room_id)?.label ?? s.room?.label ?? "TBA";

                            // Width is 1/7th of container
                            const leftPercent = (dayIndex / 7) * 100;
                            const widthPercent = 100 / 7;

                            // Overlap logic
                            const exactOverlaps = schedules
                                .filter((ds) => ds.day_of_week === s.day_of_week && ds.start_time === s.start_time)
                                .sort((a, b) => a.room_id - b.room_id);

                            const overlapIndex = exactOverlaps.findIndex((ds) => ds === s);
                            const leftOffset = overlapIndex > 0 ? `calc(${leftPercent}% + ${overlapIndex * 15}px)` : `${leftPercent}%`;
                            const zIndex = 20 + overlapIndex;

                            const canEdit = !!onScheduleChange;
                            const isActiveDrag = activeDragIndex === index;

                            return (
                                <TooltipProvider key={`${index}-${s.day_of_week}-${s.start_time}`}>
                                    <Tooltip delayDuration={isDragging ? 10000 : 300}>
                                        <TooltipTrigger asChild>
                                            <motion.div
                                                drag={canEdit}
                                                dragMomentum={false}
                                                onDragStart={() => {
                                                    setIsDragging(true);
                                                    setActiveDragIndex(index);
                                                }}
                                                onDrag={(e, info) => handleDrag(index, s, info)}
                                                onDragEnd={(_, info) => handleDragEnd(index, s, info)}
                                                initial={{ opacity: 0, scale: 0.9, y: 10 }}
                                                animate={{
                                                    opacity: isActiveDrag ? 0.35 : 1,
                                                    scale: 1,
                                                    y: 0,
                                                    top: top,
                                                    height: height,
                                                    left: leftOffset,
                                                }}
                                                exit={{ opacity: 0, scale: 0.9, y: 10 }}
                                                transition={{
                                                    type: "spring",
                                                    stiffness: 400,
                                                    damping: 30, // Entrance animation
                                                    top: { type: "tween", duration: 0 }, // Instant snap for top/height to avoid drag lag
                                                    height: { type: "tween", duration: 0 },
                                                    left: { type: "spring", stiffness: 300, damping: 25 }, // Smooth column transition
                                                }}
                                                className={`group pointer-events-auto absolute flex flex-col overflow-hidden rounded-lg border shadow-sm transition-colors duration-200 ${
                                                    canEdit ? "cursor-grab hover:shadow-md active:cursor-grabbing" : ""
                                                } ${isActiveDrag ? "ring-primary/50 relative z-50 shadow-xl ring-2" : ""}`}
                                                style={{
                                                    width: `calc(${widthPercent}% - 8px)`, // Subtract gap
                                                    zIndex: isActiveDrag ? 50 : zIndex,
                                                    borderColor: "hsl(var(--primary) / 0.4)",
                                                    backgroundColor: "hsl(var(--primary) / 0.08)",
                                                }}
                                            >
                                                <div className="bg-primary/70 pointer-events-none absolute top-0 bottom-0 left-0 w-1 rounded-l-lg" />

                                                <div className="pointer-events-none flex h-full flex-col overflow-hidden p-2 pl-3 select-none">
                                                    <div className="text-primary/80 group-hover:text-primary mb-0.5 flex items-center gap-1 truncate text-[11px] leading-tight font-semibold transition-colors">
                                                        <Clock className="h-3 w-3 flex-shrink-0" />
                                                        {formatTimeOnly(s.start_time)} - {formatTimeOnly(s.end_time)}
                                                    </div>
                                                    <div className="text-muted-foreground flex items-center gap-1 truncate text-[10px] opacity-90">
                                                        <MapPin className="h-3 w-3 flex-shrink-0" />
                                                        {roomName}
                                                    </div>
                                                </div>

                                                {/* Top Resize Handle */}
                                                {canEdit && (
                                                    <motion.div
                                                        className="hover:bg-primary/20 pointer-events-auto absolute top-0 right-0 left-0 z-10 flex h-3 cursor-ns-resize items-center justify-center rounded-t-lg opacity-0 transition-opacity group-hover:opacity-100"
                                                        drag="y"
                                                        dragMomentum={false}
                                                        dragConstraints={{ top: -9999, bottom: 9999 }} // Free space, clamped by pure math
                                                        dragElastic={0}
                                                        onDragStart={(e) => {
                                                            e.stopPropagation(); // Prevent parent drag
                                                            setIsDragging(true);
                                                            setActiveDragIndex(index);
                                                        }}
                                                        onDrag={(e, info) => {
                                                            e.stopPropagation();
                                                            handleTopResizeDrag(index, s, info);
                                                        }}
                                                        onDragEnd={(_, info) => {
                                                            handleTopResizeEnd(index, s, info);
                                                        }}
                                                    >
                                                        <div className="bg-primary/40 h-1 w-8 rounded-full" />
                                                    </motion.div>
                                                )}

                                                {/* Bottom Resize Handle */}
                                                {canEdit && (
                                                    <motion.div
                                                        className="hover:bg-primary/20 pointer-events-auto absolute right-0 bottom-0 left-0 z-10 flex h-3 cursor-ns-resize items-center justify-center rounded-b-lg opacity-0 transition-opacity group-hover:opacity-100"
                                                        drag="y"
                                                        dragMomentum={false}
                                                        dragConstraints={{ top: -9999, bottom: 9999 }} // Free space, clamped by pure math
                                                        dragElastic={0}
                                                        onDragStart={(e) => {
                                                            e.stopPropagation(); // Prevent parent drag
                                                            setIsDragging(true);
                                                            setActiveDragIndex(index);
                                                        }}
                                                        onDrag={(e, info) => {
                                                            e.stopPropagation();
                                                            handleResizeDrag(index, s, info);
                                                        }}
                                                        onDragEnd={(_, info) => {
                                                            handleResizeEnd(index, s, info);
                                                        }}
                                                    >
                                                        <div className="bg-primary/40 h-1 w-8 rounded-full" />
                                                    </motion.div>
                                                )}
                                            </motion.div>
                                        </TooltipTrigger>

                                        {!isDragging && (
                                            <TooltipContent side="right" className="z-[60] flex flex-col gap-1 p-3">
                                                <p className="text-sm font-semibold">Class Schedule</p>
                                                <div className="text-muted-foreground mt-1 flex items-center gap-2 text-xs">
                                                    <Clock className="h-3.5 w-3.5" />
                                                    {s.day_of_week}, {formatTimeOnly(s.start_time)} to {formatTimeOnly(s.end_time)}
                                                </div>
                                                <div className="text-muted-foreground flex items-center gap-2 text-xs">
                                                    <MapPin className="h-3.5 w-3.5" />
                                                    {roomName}
                                                </div>
                                                {canEdit && (
                                                    <p className="text-muted-foreground mt-2 border-t pt-2 text-[10px] italic">
                                                        Drag to move. Drag bottom edge to resize.
                                                    </p>
                                                )}
                                            </TooltipContent>
                                        )}
                                    </Tooltip>
                                </TooltipProvider>
                            );
                        })}
                    </div>
                </div>
            </div>
        </div>
    );
}
