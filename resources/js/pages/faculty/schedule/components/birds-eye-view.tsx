import { getTimetableColorTokens } from "@/components/dashboard/timetable-colors";
import { WeeklyScheduleDay } from "@/components/dashboard/weekly-timetable";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Popover, PopoverContent, PopoverTrigger } from "@/components/ui/popover";
import { Separator } from "@/components/ui/separator";
import { cn } from "@/lib/utils";
import { Link } from "@inertiajs/react";
import { IconEye } from "@tabler/icons-react";
import { useMemo } from "react";
import { DiffStatus, parseTimeToMinutes } from "../hooks/use-schedule-diff";
import { DAYS } from "../hooks/use-schedule-filter";

const START_HOUR = 8;
const END_HOUR = 19;

const getSlotPosition = (entry: { start_minutes: number | null; end_minutes: number | null }, hourHeight: number) => {
    const start = clampToRange(entry.start_minutes);
    const end = clampToRange(entry.end_minutes);
    const duration = Math.max(end - start, 30); // Minimum 30 min duration visually
    const top = ((start - START_HOUR * 60) / 60) * hourHeight;
    const height = (duration / 60) * hourHeight;
    return { top, height };
};

const clampToRange = (minutes: number | null): number => {
    if (minutes === null) return START_HOUR * 60;
    const start = START_HOUR * 60;
    const end = END_HOUR * 60;
    return Math.min(Math.max(minutes, start), end);
};

interface BirdsEyeViewProps {
    schedule: WeeklyScheduleDay[];
    statusById: Map<string, DiffStatus>;
    conflicts: Set<string>;
    highlightDay: string;
    baselineEnabled: boolean;
}

export function BirdsEyeView({ schedule, statusById, conflicts, highlightDay, baselineEnabled }: BirdsEyeViewProps) {
    const hourHeight = 44;
    const hours = useMemo(() => Array.from({ length: END_HOUR - START_HOUR + 1 }, (_, idx) => START_HOUR + idx), []);

    const normalized = useMemo(() => {
        const map = new Map(schedule.map((day) => [day.day.toLowerCase(), day.entries ?? []]));
        return DAYS.map((day) => ({
            day,
            entries: (map.get(day.toLowerCase()) ?? []).slice(),
        }));
    }, [schedule]);

    const hasData = normalized.some((d) => d.entries.length);
    const timelineHeight = (END_HOUR - START_HOUR) * hourHeight;

    if (!hasData) {
        return (
            <div className="text-muted-foreground bg-muted/10 flex flex-col items-center justify-center rounded-xl border border-dashed py-16 text-center">
                <IconEye className="mb-4 size-10 opacity-20" />
                <p>No classes found to display.</p>
            </div>
        );
    }

    return (
        <div className="bg-background/50 h-[600px] w-full touch-pan-x overflow-auto rounded-xl border shadow-sm backdrop-blur-sm">
            <div className="relative min-w-[800px]">
                {/* Header */}
                <div
                    className="bg-background/95 sticky top-0 z-30 grid border-b backdrop-blur"
                    style={{ gridTemplateColumns: "60px repeat(6, minmax(0, 1fr))" }}
                >
                    <div className="text-muted-foreground border-r p-3 text-center text-xs font-medium">Time</div>
                    {DAYS.map((day) => (
                        <div
                            key={day}
                            className={cn(
                                "text-muted-foreground border-r p-3 text-center text-xs font-semibold last:border-r-0",
                                day.toLowerCase() === highlightDay.toLowerCase() && "text-primary bg-primary/5",
                            )}
                        >
                            {day.slice(0, 3)}
                        </div>
                    ))}
                </div>

                {/* Grid Body */}
                <div className="relative grid" style={{ gridTemplateColumns: "60px repeat(6, minmax(0, 1fr))" }}>
                    {/* Time Column Background */}
                    <div className="bg-muted/10 relative border-r">
                        {hours.map((hour) => (
                            <div
                                key={`time-${hour}`}
                                className="border-border/40 relative border-b border-dashed last:border-0"
                                style={{ height: hourHeight }}
                            >
                                {hour % 2 === 0 && (
                                    <span className="text-muted-foreground bg-background absolute -top-2.5 right-2 rounded px-1 text-[10px]">
                                        {hour === 12 ? "12 PM" : hour > 12 ? `${hour - 12} PM` : `${hour} AM`}
                                    </span>
                                )}
                            </div>
                        ))}
                    </div>

                    {/* Day Columns */}
                    {normalized.map((day) => (
                        <div
                            key={`day-${day.day}`}
                            className={cn(
                                "relative border-r last:border-r-0",
                                day.day.toLowerCase() === highlightDay.toLowerCase() && "bg-primary/[0.02]",
                            )}
                            style={{ height: timelineHeight }}
                        >
                            {/* Horizontal Grid Lines */}
                            {hours.map((_, index) => (
                                <div
                                    key={`line-${day.day}-${index}`}
                                    className="border-border/30 absolute w-full border-b border-dashed"
                                    style={{ top: index * hourHeight, height: 1 }}
                                />
                            ))}

                            {/* Class Blocks */}
                            {day.entries.map((entry) => {
                                const start_minutes = parseTimeToMinutes(entry.start_time_24h ?? entry.start_time);
                                const end_minutes = parseTimeToMinutes(entry.end_time_24h ?? entry.end_time);
                                const { top, height } = getSlotPosition({ start_minutes, end_minutes }, hourHeight);

                                const id = String(entry.id);
                                const baseStatus = statusById.get(id) ?? "unchanged";
                                const status: DiffStatus = conflicts.has(id) ? "conflict" : baseStatus;
                                const colors = getTimetableColorTokens(`${entry.subject_code}-${entry.section}-${entry.room}`);

                                return (
                                    <Popover key={`${day.day}-${entry.id}`}>
                                        <PopoverTrigger asChild>
                                            <button
                                                type="button"
                                                className={cn(
                                                    "group absolute right-1 left-1 flex flex-col gap-0.5 overflow-hidden rounded-md border px-2 py-1 text-left text-[10px] shadow-sm transition-all hover:z-20 hover:scale-[1.02] hover:shadow-lg active:scale-95",
                                                    status === "added" && "z-10 ring-2 ring-emerald-500/50",
                                                    status === "changed" && "z-10 ring-2 ring-amber-500/50",
                                                    status === "conflict" && "z-10 ring-2 ring-rose-500/60",
                                                )}
                                                style={{
                                                    top: top + 1,
                                                    height: Math.max(height - 2, 28),
                                                    backgroundColor: colors.fill,
                                                    borderColor: colors.border,
                                                    color: "hsl(var(--foreground))",
                                                }}
                                            >
                                                <div className="flex w-full items-center justify-between">
                                                    <span className="truncate font-bold">{entry.subject_code}</span>
                                                    {status !== "unchanged" && (
                                                        <div
                                                            className={cn(
                                                                "size-1.5 rounded-full",
                                                                status === "added"
                                                                    ? "bg-emerald-500"
                                                                    : status === "changed"
                                                                      ? "bg-amber-500"
                                                                      : "bg-destructive",
                                                            )}
                                                        />
                                                    )}
                                                </div>
                                                <div className="truncate opacity-80">{entry.room}</div>
                                                {height > 40 && (
                                                    <div className="mt-auto truncate opacity-70">
                                                        {entry.start_time}-{entry.end_time}
                                                    </div>
                                                )}
                                            </button>
                                        </PopoverTrigger>
                                        <PopoverContent className="w-80 overflow-hidden p-0 shadow-xl" align="start" side="right">
                                            <div className="border-b px-4 py-3" style={{ backgroundColor: colors.fill }}>
                                                <div className="text-sm leading-tight font-bold">{entry.subject_title}</div>
                                                <div className="text-muted-foreground mt-1 flex items-center gap-2 text-xs">
                                                    <Badge variant="secondary" className="bg-background/50 h-5 px-1.5 text-[10px]">
                                                        {entry.subject_code}
                                                    </Badge>
                                                    <span>Sec {entry.section}</span>
                                                </div>
                                            </div>

                                            <div className="space-y-3 p-4">
                                                <div className="grid grid-cols-2 gap-2 text-xs">
                                                    <div className="text-muted-foreground">Time</div>
                                                    <div className="text-right font-medium">
                                                        {entry.start_time} – {entry.end_time}
                                                    </div>

                                                    <div className="text-muted-foreground">Day</div>
                                                    <div className="text-right font-medium">{day.day}</div>

                                                    <div className="text-muted-foreground">Room</div>
                                                    <div className="text-right font-medium">{entry.room}</div>
                                                </div>

                                                <Separator />

                                                <div className="flex gap-2">
                                                    <Button asChild size="sm" className="w-full">
                                                        <Link href={entry.class_id ? `/classes/${entry.class_id}` : "/classes"}>
                                                            View Class Details
                                                        </Link>
                                                    </Button>
                                                </div>
                                            </div>
                                        </PopoverContent>
                                    </Popover>
                                );
                            })}
                        </div>
                    ))}
                </div>
            </div>
        </div>
    );
}
