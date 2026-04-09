import { IconCalendarTime, IconClock, IconMapPin, IconNotebook } from "@tabler/icons-react";
import { useMemo } from "react";

import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Popover, PopoverContent, PopoverTrigger } from "@/components/ui/popover";
import { Separator } from "@/components/ui/separator";
import { Link } from "@inertiajs/react";

import { getTimetableColorTokens } from "@/components/dashboard/timetable-colors";

export interface WeeklyScheduleEntry {
    id: number | string;
    class_id?: number | string;
    day: string;
    start_time: string;
    end_time: string;
    start_time_24h?: string | null;
    end_time_24h?: string | null;
    subject_code: string;
    subject_title: string;
    section: string;
    room: string;
    course_codes?: string;
    classification?: string;
}

export interface WeeklyScheduleDay {
    day: string;
    entries: WeeklyScheduleEntry[];
}

interface WeeklyTimetableProps {
    schedule?: WeeklyScheduleDay[];
}

const DAYS = ["Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday"];
const START_HOUR = 8;
const END_HOUR = 19; // 7 PM
const HOUR_HEIGHT = 80; // Increased for better readability

const slotToMinutes = (value?: string | null): number | null => {
    if (!value) return null;
    const [hours, minutes] = value.split(":").map(Number);
    return hours * 60 + minutes;
};

const formatTimeRange = (start?: string, end?: string): string => (start && end ? `${start} – ${end}` : "Schedule TBA");

const clampToRange = (minutes: number | null): number => {
    if (minutes === null) return START_HOUR * 60;
    const start = START_HOUR * 60;
    const end = END_HOUR * 60;
    return Math.min(Math.max(minutes, start), end);
};

const getSlotPosition = (entry: WeeklyScheduleEntry) => {
    const start = clampToRange(slotToMinutes(entry.start_time_24h ?? entry.start_time));
    const end = clampToRange(slotToMinutes(entry.end_time_24h ?? entry.end_time));
    const duration = Math.max(end - start, 30);
    const top = ((start - START_HOUR * 60) / 60) * HOUR_HEIGHT;
    const height = (duration / 60) * HOUR_HEIGHT;
    return { top, height };
};

export function WeeklyTimetable({ schedule = [] }: WeeklyTimetableProps) {
    const normalizedSchedule = useMemo(() => {
        const map = new Map(schedule.map((day) => [day.day.toLowerCase(), day.entries ?? []]));
        return DAYS.map((day) => ({
            day,
            entries: (map.get(day.toLowerCase()) ?? []).slice(),
        }));
    }, [schedule]);

    const flatEntries = useMemo(() => {
        return normalizedSchedule
            .flatMap((day) => day.entries.map((entry) => ({ ...entry, day: day.day })))
            .sort((a, b) => {
                const aMinutes = slotToMinutes(a.start_time_24h ?? a.start_time) ?? 0;
                const bMinutes = slotToMinutes(b.start_time_24h ?? b.start_time) ?? 0;
                return aMinutes - bMinutes;
            });
    }, [normalizedSchedule]);

    const hasData = flatEntries.length > 0;
    const hours = useMemo(() => Array.from({ length: END_HOUR - START_HOUR + 1 }, (_, idx) => START_HOUR + idx), []);
    const timelineHeight = (END_HOUR - START_HOUR) * HOUR_HEIGHT;

    return (
        <div className="flex h-full flex-col">
            {hasData ? (
                <>
                    <div className="bg-background hidden h-[560px] w-full touch-pan-x overflow-auto rounded-md border sm:block">
                        <div className="min-w-[860px] p-4">
                            <div
                                className="grid"
                                style={{
                                    gridTemplateColumns: "60px repeat(6, minmax(0, 1fr))",
                                }}
                            >
                                {/* Header Row */}
                                <div className="bg-background text-muted-foreground sticky top-0 z-20 pb-4 text-xs font-medium">Time</div>
                                {DAYS.map((day) => (
                                    <div
                                        key={day}
                                        className="bg-background text-muted-foreground sticky top-0 z-20 pb-4 text-center text-xs font-medium"
                                    >
                                        {day.slice(0, 3)}
                                    </div>
                                ))}

                                {/* Time Column */}
                                <div className="relative border-r pr-4">
                                    {hours.map((hour) => (
                                        <div key={`time-${hour}`} className="relative" style={{ height: HOUR_HEIGHT }}>
                                            <span className="text-muted-foreground absolute -top-2 right-0 text-xs">
                                                {hour === 12 ? "12 PM" : hour > 12 ? `${hour - 12} PM` : `${hour} AM`}
                                            </span>
                                        </div>
                                    ))}
                                </div>

                                {/* Days Columns */}
                                {normalizedSchedule.map((day) => (
                                    <div key={`grid-${day.day}`} className="relative border-r last:border-r-0" style={{ height: timelineHeight }}>
                                        {/* Grid Lines */}
                                        {hours.map((_, index) => (
                                            <div
                                                key={`line-${day.day}-${index}`}
                                                className="border-border/50 absolute w-full border-b border-dashed"
                                                style={{ top: index * HOUR_HEIGHT, height: 1 }}
                                            />
                                        ))}

                                        {/* Entries */}
                                        {day.entries.map((entry) => {
                                            const { top, height } = getSlotPosition(entry);
                                            const colors = getTimetableColorTokens(`${entry.subject_code}-${entry.section}-${entry.room}`);
                                            return (
                                                <Popover key={`${day.day}-${entry.id}`}>
                                                    <PopoverTrigger asChild>
                                                        <button
                                                            type="button"
                                                            className="focus-visible:ring-ring absolute right-1 left-1 cursor-pointer rounded-md border p-2 text-left text-xs transition-all hover:z-10 hover:shadow-md focus-visible:ring-2 focus-visible:outline-none"
                                                            style={{
                                                                top: top + 1,
                                                                height: height - 2,
                                                                backgroundColor: colors.fill,
                                                                borderColor: colors.border,
                                                                color: "hsl(var(--foreground))",
                                                            }}
                                                        >
                                                            <div className="flex h-full flex-col gap-1 overflow-hidden">
                                                                <div className="truncate leading-tight font-semibold">{entry.subject_code}</div>
                                                                <div className="text-muted-foreground truncate text-[10px]">{entry.room}</div>
                                                                {height > 50 && (
                                                                    <Badge variant="secondary" className="mt-auto h-5 w-fit px-1 py-0 text-[10px]">
                                                                        {entry.section}
                                                                    </Badge>
                                                                )}
                                                            </div>
                                                        </button>
                                                    </PopoverTrigger>

                                                    <PopoverContent side="right" align="start" className="w-80 overflow-hidden p-0">
                                                        <div className="border-b px-3 py-2" style={{ backgroundColor: colors.fill }}>
                                                            <p className="text-sm font-semibold">{entry.subject_title}</p>
                                                            <p className="text-muted-foreground text-xs">{entry.subject_code}</p>
                                                        </div>

                                                        <div className="space-y-2 p-3 text-xs">
                                                            <div className="flex items-center gap-2">
                                                                <IconClock className="text-muted-foreground size-3.5" />
                                                                <span>{formatTimeRange(entry.start_time, entry.end_time)}</span>
                                                            </div>
                                                            <div className="flex items-center gap-2">
                                                                <IconMapPin className="text-muted-foreground size-3.5" />
                                                                <span>{entry.room}</span>
                                                            </div>
                                                            <div className="flex items-center gap-2">
                                                                <IconNotebook className="text-muted-foreground size-3.5" />
                                                                <span>Section {entry.section}</span>
                                                            </div>

                                                            <Separator />

                                                            <div className="flex gap-2">
                                                                <Button asChild size="sm" className="flex-1">
                                                                    <Link
                                                                        href={
                                                                            entry.class_id ? `/faculty/classes/${entry.class_id}` : "/faculty/classes"
                                                                        }
                                                                    >
                                                                        Open class
                                                                    </Link>
                                                                </Button>
                                                                <Button asChild variant="outline" size="sm">
                                                                    <Link href="/faculty/classes">All</Link>
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

                    <div className="mt-4 sm:hidden">
                        <h3 className="text-muted-foreground mb-3 text-sm font-medium">Schedule Summary</h3>
                        <div className="space-y-3">
                            {flatEntries.map((entry) => (
                                <ListRow key={`${entry.day}-${entry.id}`} entry={entry} />
                            ))}
                        </div>
                    </div>
                </>
            ) : (
                <EmptyState />
            )}
        </div>
    );
}

interface ListRowProps {
    entry: WeeklyScheduleEntry & { day: string };
}

function ListRow({ entry }: ListRowProps) {
    const colors = getTimetableColorTokens(`${entry.subject_code}-${entry.section}-${entry.room}`);

    return (
        <div
            className="flex flex-col gap-2 rounded-lg border p-4 shadow-sm"
            style={{
                backgroundColor: colors.fill,
                borderColor: colors.border,
                color: "hsl(var(--foreground))",
            }}
        >
            <div className="flex items-start justify-between gap-2">
                <div>
                    <div className="font-semibold">{entry.subject_title}</div>
                    <div className="text-muted-foreground text-sm">
                        {entry.subject_code} • {entry.section}
                    </div>
                </div>
                <Badge variant="outline">{entry.day}</Badge>
            </div>
            <Separator />
            <div className="text-muted-foreground flex flex-col gap-2 text-sm sm:flex-row sm:items-center sm:gap-4">
                <div className="flex items-center gap-1.5">
                    <IconClock className="size-4" />
                    {formatTimeRange(entry.start_time, entry.end_time)}
                </div>
                <div className="flex items-center gap-1.5">
                    <IconMapPin className="size-4" />
                    {entry.room}
                </div>
            </div>

            <div className="pt-1">
                <Button asChild size="sm" className="w-full">
                    <Link href={entry.class_id ? `/faculty/classes/${entry.class_id}` : "/faculty/classes"}>Open class</Link>
                </Button>
            </div>
        </div>
    );
}

function EmptyState() {
    return (
        <div className="animate-in fade-in-50 flex flex-col items-center justify-center gap-2 rounded-lg border border-dashed p-8 text-center">
            <div className="bg-muted rounded-full p-3">
                <IconCalendarTime className="text-muted-foreground size-6" />
            </div>
            <h3 className="font-semibold">No schedule found</h3>
            <p className="text-muted-foreground max-w-xs text-sm">
                Your class schedule for this week is empty. Classes will appear here once assigned.
            </p>
        </div>
    );
}
