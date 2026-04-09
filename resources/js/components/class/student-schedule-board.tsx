import { ClassData } from "@/components/data-table";
import { ScrollArea } from "@/components/ui/scroll-area";
import { cn } from "@/lib/utils";
import { DAYS, getDayNameFromDate, ScheduleEvent } from "@/pages/classes/hooks/use-class-schedule";
import { Link } from "@inertiajs/react";
import { IconBroadcast, IconClock, IconMapPin, IconSchool, IconUser } from "@tabler/icons-react";
import { useEffect, useMemo, useState } from "react";

interface StudentScheduleBoardProps {
    events: ScheduleEvent[];
    classes: ClassData[];
    filterDay: string;
}

const START_HOUR = 7; // 7 AM
const END_HOUR = 18; // 6 PM
const HOUR_HEIGHT = 60; // Pixels per hour

export function StudentScheduleBoard({ events, classes, filterDay }: StudentScheduleBoardProps) {
    const today = useMemo(() => getDayNameFromDate(new Date()), []);
    const [currentTime, setCurrentTime] = useState(new Date());
    const [isMobile, setIsMobile] = useState(false);

    // Update time every minute
    useEffect(() => {
        const timer = setInterval(() => setCurrentTime(new Date()), 60000);

        const checkMobile = () => setIsMobile(window.innerWidth < 768);
        checkMobile();
        window.addEventListener("resize", checkMobile);

        return () => {
            clearInterval(timer);
            window.removeEventListener("resize", checkMobile);
        };
    }, []);

    const hours = useMemo(() => {
        const h = [];
        for (let i = START_HOUR; i <= END_HOUR; i++) {
            h.push(i);
        }
        return h;
    }, []);

    const displayDays = useMemo(() => {
        if (filterDay !== "all" && filterDay !== "") {
            return [filterDay];
        }
        return DAYS.filter((d) => d !== "Sunday");
    }, [filterDay]);

    // Current time position calculation
    const currentTimeMinutes = currentTime.getHours() * 60 + currentTime.getMinutes();
    const currentDay = getDayNameFromDate(currentTime);
    const isCurrentTimeVisible = currentTimeMinutes >= START_HOUR * 60 && currentTimeMinutes <= (END_HOUR + 1) * 60;
    const currentTimeTop = ((currentTimeMinutes - START_HOUR * 60) / 60) * HOUR_HEIGHT;

    // Helper to calculate basic vertical position
    const getEventVerticalPosition = (event: ScheduleEvent) => {
        const start = event.startMinutes;
        const end = event.endMinutes;
        const duration = end - start;
        const startOffset = start - START_HOUR * 60;

        return {
            top: (startOffset / 60) * HOUR_HEIGHT,
            height: (duration / 60) * HOUR_HEIGHT,
        };
    };

    // Process events for overlaps per day
    const getProcessedDayEvents = (dayEvents: ScheduleEvent[]) => {
        // Sort by start time
        const sorted = [...dayEvents].sort((a, b) => a.startMinutes - b.startMinutes || b.endMinutes - a.endMinutes - (b.endMinutes - a.endMinutes));

        const columns: ScheduleEvent[][] = [];
        const eventPositions = new Map<ScheduleEvent, { colIndex: number; totalCols: number }>();

        // Greedy column assignment
        sorted.forEach((event) => {
            let placed = false;
            for (let i = 0; i < columns.length; i++) {
                const col = columns[i];
                const lastInCol = col[col.length - 1];
                // If this event starts after the last one in this column ends, place it here
                if (lastInCol.endMinutes <= event.startMinutes) {
                    col.push(event);
                    eventPositions.set(event, { colIndex: i, totalCols: 1 }); // totalCols updated later
                    placed = true;
                    break;
                }
            }

            if (!placed) {
                columns.push([event]);
                eventPositions.set(event, { colIndex: columns.length - 1, totalCols: 1 });
            }
        });

        // Now determines totalCols for each overlapping cluster?
        // Simplified: The number of columns is the max overlapping at any point.
        // But simply using `columns.length` as width divisor is safe but maybe too narrow if columns are disparate.
        // For simple visualization: Width = 100% / columns.length. Left = colIndex * width.
        // This ensures no overlap visually.

        const totalColumns = columns.length;

        return sorted.map((event) => {
            const pos = eventPositions.get(event);
            const vert = getEventVerticalPosition(event);
            const colIndex = pos?.colIndex ?? 0;

            return {
                event,
                style: {
                    top: `${vert.top}px`,
                    height: `${vert.height}px`,
                    left: `${(colIndex / totalColumns) * 100}%`,
                    width: `${100 / totalColumns}%`,
                },
                // Add status
                isLive: event.day === currentDay && currentTimeMinutes >= event.startMinutes && currentTimeMinutes < event.endMinutes,
                isPast:
                    (event.day === currentDay && currentTimeMinutes >= event.endMinutes) || DAYS.indexOf(event.day) < DAYS.indexOf(currentDay as any),
                isFuture:
                    (event.day === currentDay && currentTimeMinutes < event.startMinutes) ||
                    DAYS.indexOf(event.day) > DAYS.indexOf(currentDay as any),
            };
        });
    };

    if (classes.length === 0) {
        return (
            <div className="text-muted-foreground bg-muted/10 flex flex-col items-center justify-center rounded-xl border-2 border-dashed py-20 text-center">
                <div className="bg-muted mb-4 rounded-full p-4">
                    <IconSchool className="h-8 w-8" />
                </div>
                <h3 className="text-lg font-semibold">No Classes Enrolled</h3>
                <p className="mt-1 max-w-xs text-sm">You don't have any classes in your schedule yet.</p>
            </div>
        );
    }

    const totalHeight = (END_HOUR - START_HOUR + 1) * HOUR_HEIGHT;

    return (
        <div className="bg-background flex h-full max-h-[800px] flex-col overflow-hidden rounded-xl border shadow-sm">
            {/* Header / Days */}
            <div className="bg-muted/40 flex shrink-0 divide-x overflow-hidden border-b">
                <div className="bg-background/50 flex w-12 shrink-0 items-center justify-center border-r p-2 md:w-16 md:p-4">
                    <IconClock className="text-muted-foreground size-4 md:size-5" />
                </div>
                <div className="grid flex-1" style={{ gridTemplateColumns: `repeat(${displayDays.length}, 1fr)` }}>
                    {displayDays.map((day) => (
                        <div
                            key={day}
                            className={cn(
                                "flex flex-col items-center justify-center overflow-hidden border-r px-1 py-2 text-center transition-colors last:border-r-0 md:py-3",
                                day === today && "bg-primary/5",
                            )}
                        >
                            <span
                                className={cn(
                                    "w-full truncate text-[10px] font-semibold tracking-wide uppercase md:text-sm",
                                    day === today ? "text-primary" : "text-muted-foreground",
                                )}
                            >
                                {isMobile ? day.slice(0, 1) : day.slice(0, 3)}
                            </span>
                            {day === today && (
                                <div className="mt-0.5 flex items-center gap-1 md:mt-1">
                                    <div className="bg-primary h-1.5 w-1.5 animate-pulse rounded-full" />
                                    <span className="text-primary hidden text-[10px] font-medium md:inline-block">Today</span>
                                </div>
                            )}
                        </div>
                    ))}
                </div>
            </div>

            {/* Timetable Grid */}
            <ScrollArea className="flex-1">
                <div className="relative flex" style={{ height: `${totalHeight}px` }}>
                    {/* Time Column */}
                    <div className="bg-background/80 relative z-10 w-12 shrink-0 border-r text-[10px] backdrop-blur-sm select-none md:w-16 md:text-xs">
                        {hours.map((hour) => (
                            <div key={hour} className="absolute w-full text-center" style={{ top: `${(hour - START_HOUR) * HOUR_HEIGHT}px` }}>
                                <span className="text-muted-foreground bg-background/80 relative -top-2 rounded px-1">
                                    {hour > 12 ? `${hour - 12}pm` : hour === 12 ? `12pm` : `${hour}am`}
                                </span>
                            </div>
                        ))}
                    </div>

                    {/* Grid Body */}
                    <div
                        className="relative grid flex-1 divide-x"
                        style={{
                            gridTemplateColumns: `repeat(${displayDays.length}, 1fr)`,
                        }}
                    >
                        {/* Horizontal Hour Lines Background */}
                        <div className="pointer-events-none absolute inset-0 z-0 w-full">
                            {hours.map((hour) => (
                                <div
                                    key={hour}
                                    className="border-border/30 absolute w-full border-t border-dashed"
                                    style={{ top: `${(hour - START_HOUR) * HOUR_HEIGHT}px` }}
                                />
                            ))}
                        </div>

                        {/* Current Time Line */}
                        {isCurrentTimeVisible && displayDays.includes(currentDay as any) && (
                            <div
                                className="pointer-events-none absolute right-0 left-0 z-20 flex items-center"
                                style={{ top: `${currentTimeTop}px` }}
                            >
                                <div className="h-px w-12 bg-red-500/50 md:w-16" />
                                <div className="relative h-px flex-1 bg-red-500 shadow-[0_0_8px_rgba(239,68,68,0.6)]">
                                    <div className="absolute -top-[3px] -left-1 h-2 w-2 rounded-full bg-red-500" />
                                </div>
                            </div>
                        )}

                        {displayDays.map((day) => {
                            const dayEvents = events.filter((e) => e.day === day);
                            const processedEvents = getProcessedDayEvents(dayEvents);

                            return (
                                <div key={day} className={cn("relative h-full", day === today && "bg-primary/5")}>
                                    {processedEvents.map(({ event, style, isLive, isPast }, idx) => {
                                        // Dynamic color based on accent or default
                                        const accentColor = event.classItem.accent_color;
                                        const isBgClass = accentColor?.includes("bg-");
                                        const baseColorClass = isBgClass ? accentColor : "bg-primary";

                                        // Check if event is too short for details
                                        const isShort = event.endMinutes - event.startMinutes < 45;

                                        return (
                                            <Link
                                                key={`${event.classItem.id}-${idx}`}
                                                href={`/student/classes/${event.classItem.id}`}
                                                className={cn(
                                                    "group hover:ring-primary/20 absolute inset-x-0.5 flex flex-col overflow-hidden rounded-[3px] border transition-all hover:z-30 hover:shadow-lg hover:ring-1 md:inset-x-1 md:rounded-md",
                                                    "p-1 md:p-1.5",
                                                    isShort ? "justify-center" : "justify-between",
                                                    isLive
                                                        ? "bg-background border-primary ring-primary z-20 shadow-md ring-1"
                                                        : "bg-card hover:bg-card/90",
                                                    isPast && "opacity-60 grayscale-[0.3] hover:opacity-100 hover:grayscale-0",
                                                )}
                                                style={{
                                                    ...style,
                                                    // If using inline hex color for accent
                                                    ...(!isBgClass && accentColor
                                                        ? {
                                                              borderColor: accentColor,
                                                              backgroundColor: isLive ? undefined : `${accentColor}15`,
                                                          }
                                                        : {}),
                                                }}
                                            >
                                                {/* Status Badges */}
                                                {isLive && (
                                                    <div className="absolute top-1 right-1 z-10 flex animate-pulse items-center gap-1 rounded-full bg-red-500 px-1.5 py-0.5 text-[8px] font-bold text-white shadow-sm">
                                                        <IconBroadcast className="size-2" />
                                                        LIVE
                                                    </div>
                                                )}

                                                {/* Left Accent Strip */}
                                                <div
                                                    className={cn("absolute top-0 bottom-0 left-0 w-0.5 md:w-1", baseColorClass)}
                                                    style={!isBgClass && accentColor ? { backgroundColor: accentColor } : {}}
                                                />

                                                <div className="relative flex h-full w-full flex-col pl-1.5 md:pl-2.5">
                                                    <div
                                                        className={cn(
                                                            "text-foreground truncate pr-8 text-[9px] leading-tight font-bold group-hover:underline md:text-xs",
                                                            isLive && "text-primary",
                                                        )}
                                                    >
                                                        {event.classItem.subject_title}
                                                    </div>

                                                    {!isShort && (
                                                        <>
                                                            <div className="text-muted-foreground mt-0.5 truncate text-[8px] opacity-90 md:text-[10px]">
                                                                {event.classItem.subject_code} • {event.classItem.section}
                                                            </div>

                                                            {event.classItem.faculty_name && (
                                                                <div className="text-muted-foreground/80 mt-1 flex hidden items-center gap-1 text-[8px] md:flex md:text-[10px]">
                                                                    <IconUser className="size-3 shrink-0" />
                                                                    <span className="truncate">{event.classItem.faculty_name}</span>
                                                                </div>
                                                            )}

                                                            <div className="mt-auto hidden space-y-0.5 pt-1 opacity-80 sm:block">
                                                                <div className="flex items-center gap-1 text-[9px] font-medium">
                                                                    <IconClock className="size-2.5" />
                                                                    <span className="truncate">
                                                                        {event.startLabel} - {event.endLabel}
                                                                    </span>
                                                                </div>
                                                                {event.roomLabel && (
                                                                    <div className="flex items-center gap-1 text-[9px]">
                                                                        <IconMapPin className="size-2.5" />
                                                                        <span className="truncate">{event.roomLabel}</span>
                                                                    </div>
                                                                )}
                                                            </div>
                                                        </>
                                                    )}
                                                </div>
                                            </Link>
                                        );
                                    })}
                                </div>
                            );
                        })}
                    </div>
                </div>
            </ScrollArea>
        </div>
    );
}
