import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { cn } from "@/lib/utils";
import { AlertTriangle, CalendarDays, Clock, Monitor, Plus, School, Trash2 } from "lucide-react";
import { useEffect, useState, type ReactNode } from "react";
import { type ClassSchedule, type DeliveryMode, type EntityOption, type ValueOption } from "../../lib/class-defaults";

type ScheduleGridProps = {
    schedules: ClassSchedule[];
    rooms: EntityOption[];
    dayOptions: ValueOption[];
    primaryRoomId: number | null;
    defaultDeliveryMode: DeliveryMode;
    onSchedulesChange: (schedules: ClassSchedule[]) => void;
};

const selectClassName =
    "h-10 w-full min-w-0 rounded-xl border border-input bg-background px-3 text-xs text-foreground outline-none focus-visible:border-ring focus-visible:ring-3 focus-visible:ring-ring/50";

const presets = [
    { label: "Mon / Wed / Fri", days: ["Monday", "Wednesday", "Friday"], start: "08:00", end: "09:00" },
    { label: "Tue / Thu", days: ["Tuesday", "Thursday"], start: "08:00", end: "09:30" },
    { label: "Single session", days: ["Monday"], start: "08:00", end: "11:00" },
];

function toMinutes(time: string): number {
    return Number(time.slice(0, 2)) * 60 + Number(time.slice(3, 5));
}

function formatDuration(start: string, end: string): string {
    const diff = toMinutes(end) - toMinutes(start);
    if (diff <= 0) return "0m";
    const hrs = Math.floor(diff / 60);
    const mins = diff % 60;
    if (hrs > 0 && mins > 0) return `${hrs}h ${mins}m`;
    if (hrs > 0) return `${hrs}h`;
    return `${mins}m`;
}

function sessionIssue(schedule: ClassSchedule, others: ClassSchedule[]): string | null {
    if (!schedule.start_time || !schedule.end_time || schedule.end_time <= schedule.start_time) {
        return "The end time must be after the start time.";
    }

    const overlaps = others.some(
        (other) => other.day_of_week === schedule.day_of_week && other.start_time < schedule.end_time && other.end_time > schedule.start_time,
    );

    return overlaps ? "This overlaps another meeting on the same day." : null;
}

export function ScheduleGrid({ schedules, rooms, dayOptions, primaryRoomId, defaultDeliveryMode, onSchedulesChange }: ScheduleGridProps): ReactNode {
    const [days, setDays] = useState<string[]>(["Monday", "Wednesday", "Friday"]);
    const [start, setStart] = useState("08:00");
    const [end, setEnd] = useState("09:00");
    const [error, setError] = useState<string | null>(null);
    const [deliveryMode, setDeliveryMode] = useState<DeliveryMode>(defaultDeliveryMode);

    useEffect(() => setDeliveryMode(defaultDeliveryMode), [defaultDeliveryMode]);

    const weeklyHours = schedules.reduce((total, item) => total + Math.max(0, toMinutes(item.end_time) - toMinutes(item.start_time)), 0) / 60;

    const update = (index: number, changes: Partial<ClassSchedule>): void => {
        onSchedulesChange(schedules.map((item, itemIndex) => (itemIndex === index ? { ...item, ...changes } : item)));
    };

    const addMeetings = (): void => {
        if (days.length === 0 || !start || !end || end <= start) {
            setError("Pick at least one day and set an end time after the start time.");
            return;
        }

        const groupId = days.length > 1 ? crypto.randomUUID() : null;
        const additions = dayOptions
            .filter((day) => days.includes(day.value))
            .map((day): ClassSchedule => ({
                day_of_week: day.value,
                start_time: start,
                end_time: end,
                delivery_mode: deliveryMode,
                room_id: deliveryMode === "online" ? null : primaryRoomId,
                component_type: "lecture",
                pattern_group_id: groupId,
            }));

        onSchedulesChange([...schedules, ...additions]);
        setError(null);
    };

    return (
        <div className="space-y-6">
            {/* Schedule Builder Card */}
            <div className="bg-muted/20 space-y-4 rounded-xl border p-4 sm:p-5">
                <div className="flex flex-wrap items-center justify-between gap-2">
                    <div className="space-y-0.5">
                        <h4 className="text-foreground flex items-center gap-1.5 text-sm font-semibold">
                            <Clock className="text-primary size-4" />
                            Add weekly meeting sessions
                        </h4>
                        <p className="text-muted-foreground text-xs">Select days and meeting window. The class recurs on these days each week.</p>
                    </div>

                    {/* Presets */}
                    <div className="flex flex-wrap gap-1.5">
                        {presets.map((preset) => (
                            <Button
                                key={preset.label}
                                type="button"
                                variant="outline"
                                size="sm"
                                className="h-8 rounded-lg px-2.5 text-xs font-medium"
                                onClick={() => {
                                    setDays(preset.days);
                                    setStart(preset.start);
                                    setEnd(preset.end);
                                }}
                            >
                                {preset.label}
                            </Button>
                        ))}
                    </div>
                </div>

                {/* Day selector buttons */}
                <div className="space-y-1.5">
                    <span className="text-foreground text-xs font-semibold">Select days</span>
                    <div className="flex flex-wrap gap-1.5" role="group" aria-label="Meeting days">
                        {dayOptions.map((day) => {
                            const selected = days.includes(day.value);
                            return (
                                <button
                                    key={day.value}
                                    type="button"
                                    aria-pressed={selected}
                                    className={cn(
                                        "h-9 min-w-11 rounded-lg border px-3 text-xs font-semibold transition-all",
                                        selected
                                            ? "border-primary bg-primary text-primary-foreground shadow-2xs"
                                            : "border-border bg-card text-muted-foreground hover:bg-muted/60 hover:text-foreground",
                                    )}
                                    onClick={() => setDays(selected ? days.filter((v) => v !== day.value) : [...days, day.value])}
                                >
                                    {day.label.slice(0, 3)}
                                </button>
                            );
                        })}
                    </div>
                </div>

                {/* Meeting configurations */}
                <div className="grid items-end gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    <div className="space-y-1">
                        <Label htmlFor="meeting-delivery" className="text-muted-foreground text-xs font-medium">
                            Meeting format
                        </Label>
                        <select
                            id="meeting-delivery"
                            className={selectClassName}
                            value={deliveryMode}
                            onChange={(event) => setDeliveryMode(event.target.value as DeliveryMode)}
                        >
                            <option value="face_to_face">Face to face (Campus)</option>
                            <option value="online">Online (Virtual)</option>
                        </select>
                    </div>

                    <div className="space-y-1">
                        <Label htmlFor="meeting-start" className="text-muted-foreground text-xs font-medium">
                            Start time
                        </Label>
                        <Input
                            id="meeting-start"
                            type="time"
                            className="h-10 rounded-xl font-mono text-xs"
                            value={start}
                            onChange={(event) => setStart(event.target.value)}
                        />
                    </div>

                    <div className="space-y-1">
                        <Label htmlFor="meeting-end" className="text-muted-foreground text-xs font-medium">
                            End time {start && end && end > start ? `(${formatDuration(start, end)})` : ""}
                        </Label>
                        <Input
                            id="meeting-end"
                            type="time"
                            className="h-10 rounded-xl font-mono text-xs"
                            value={end}
                            onChange={(event) => setEnd(event.target.value)}
                        />
                    </div>

                    <Button type="button" className="h-10 gap-1.5 rounded-xl text-xs font-semibold" onClick={addMeetings}>
                        <Plus className="size-3.5" aria-hidden="true" />
                        Add {days.length} {days.length === 1 ? "meeting" : "meetings"}
                    </Button>
                </div>

                {error ? (
                    <p role="alert" className="text-destructive text-xs font-medium">
                        {error}
                    </p>
                ) : null}
            </div>

            {/* Existing Scheduled Meetings List */}
            {schedules.length === 0 ? (
                <div className="text-muted-foreground flex items-center gap-3.5 rounded-xl border border-dashed p-4">
                    <CalendarDays className="text-muted-foreground size-5 shrink-0" aria-hidden="true" />
                    <p className="text-xs text-pretty">
                        No recurring sessions added yet. You can create this class now as an unscheduled offering and configure weekly meeting times
                        later.
                    </p>
                </div>
            ) : (
                <div className="space-y-3">
                    <div className="flex items-center justify-between">
                        <span className="text-foreground text-xs font-semibold">Scheduled meetings ({schedules.length})</span>
                        <Badge variant="secondary" className="font-mono text-xs tabular-nums">
                            {Number(weeklyHours.toFixed(1))} total hrs/week
                        </Badge>
                    </div>

                    <div className="grid gap-2.5">
                        {schedules.map((schedule, index) => {
                            const issue = sessionIssue(
                                schedule,
                                schedules.filter((_, otherIndex) => otherIndex !== index),
                            );
                            const isOnline = (schedule.delivery_mode ?? defaultDeliveryMode) === "online";

                            return (
                                <div
                                    key={schedule.id ?? `meeting-row-${index}`}
                                    className={cn(
                                        "bg-card flex flex-col gap-3 rounded-xl border p-3.5 shadow-2xs transition-all sm:flex-row sm:items-center sm:justify-between",
                                        issue && "border-destructive/40 bg-destructive/[0.03]",
                                    )}
                                >
                                    <div className="flex min-w-0 flex-wrap items-center gap-2.5">
                                        <Badge variant="outline" className="px-2.5 py-0.5 text-xs font-semibold">
                                            {schedule.day_of_week}
                                        </Badge>

                                        <span className="text-foreground inline-flex items-center gap-1 font-mono text-xs font-medium">
                                            <Clock className="text-muted-foreground size-3" />
                                            {schedule.start_time} – {schedule.end_time}
                                        </span>

                                        <Badge variant="secondary" className="px-1.5 py-0 font-mono text-[11px]">
                                            {formatDuration(schedule.start_time, schedule.end_time)}
                                        </Badge>

                                        <Badge
                                            variant="outline"
                                            className={cn(
                                                "gap-1 text-[11px]",
                                                isOnline
                                                    ? "border-indigo-500/30 text-indigo-600 dark:text-indigo-400"
                                                    : "border-blue-500/30 text-blue-600 dark:text-blue-400",
                                            )}
                                        >
                                            {isOnline ? <Monitor className="size-3" /> : <School className="size-3" />}
                                            {isOnline ? "Online" : "Campus"}
                                        </Badge>
                                    </div>

                                    <div className="flex items-center justify-between gap-2 sm:justify-end">
                                        {!isOnline ? (
                                            <select
                                                aria-label={`Room for ${schedule.day_of_week}`}
                                                className="border-input bg-background text-foreground h-8 max-w-40 rounded-lg border px-2 text-xs outline-none"
                                                value={schedule.room_id ?? 0}
                                                onChange={(event) => update(index, { room_id: Number(event.target.value) || null })}
                                            >
                                                <option value={0}>Assign room later</option>
                                                {rooms.map((room) => (
                                                    <option key={room.id} value={room.id}>
                                                        {room.label}
                                                    </option>
                                                ))}
                                            </select>
                                        ) : (
                                            <span className="text-muted-foreground text-[11px] italic">No room needed</span>
                                        )}

                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="sm"
                                            className="text-muted-foreground hover:text-destructive h-8 px-2 text-xs"
                                            aria-label={`Remove meeting ${index + 1}`}
                                            onClick={() => onSchedulesChange(schedules.filter((_, itemIndex) => itemIndex !== index))}
                                        >
                                            <Trash2 className="size-3.5" aria-hidden="true" />
                                        </Button>
                                    </div>

                                    {issue ? (
                                        <div className="text-destructive flex items-center gap-1.5 text-xs font-medium sm:col-span-full">
                                            <AlertTriangle className="size-3.5 shrink-0" />
                                            <span>{issue}</span>
                                        </div>
                                    ) : null}
                                </div>
                            );
                        })}
                    </div>
                </div>
            )}
        </div>
    );
}
