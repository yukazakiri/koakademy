import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { cn } from "@/lib/utils";
import { CalendarDays, Plus, Trash2 } from "lucide-react";
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
    "h-11 w-full min-w-0 rounded-lg border border-input bg-background px-3 text-sm text-foreground outline-none focus-visible:border-ring focus-visible:ring-3 focus-visible:ring-ring/50";

const presets = [
    { label: "Mon / Wed / Fri", days: ["Monday", "Wednesday", "Friday"], start: "08:00", end: "09:00" },
    { label: "Tue / Thu", days: ["Tuesday", "Thursday"], start: "08:00", end: "09:30" },
    { label: "One long meeting", days: ["Monday"], start: "08:00", end: "11:00" },
];

function toMinutes(time: string): number {
    return Number(time.slice(0, 2)) * 60 + Number(time.slice(3, 5));
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
            setError("Pick at least one day, and an end time after the start time.");

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
            <section aria-label="Add meetings" className="bg-muted/30 space-y-4 rounded-lg border p-4 sm:p-5">
                <div className="space-y-1">
                    <h4 className="text-sm font-semibold">Add meeting times</h4>
                    <p className="text-muted-foreground text-sm">Pick the days and the time. The class repeats on those days every week.</p>
                </div>

                <div className="flex flex-wrap gap-2">
                    {presets.map((preset) => (
                        <Button
                            key={preset.label}
                            type="button"
                            variant="outline"
                            className="h-10 px-3"
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

                <div className="flex flex-wrap gap-2" role="group" aria-label="Meeting days">
                    {dayOptions.map((day) => {
                        const selected = days.includes(day.value);

                        return (
                            <Button
                                key={day.value}
                                type="button"
                                variant="outline"
                                aria-pressed={selected}
                                aria-label={day.label}
                                className={cn("h-10 min-w-12 px-3", selected && "border-primary bg-primary/10 text-primary")}
                                onClick={() => setDays(selected ? days.filter((value) => value !== day.value) : [...days, day.value])}
                            >
                                {day.label.slice(0, 3)}
                            </Button>
                        );
                    })}
                </div>

                <div className="flex flex-wrap items-end gap-4">
                    <div className="space-y-2">
                        <Label htmlFor="meeting-delivery">Meeting type</Label>
                        <select
                            id="meeting-delivery"
                            className={selectClassName}
                            value={deliveryMode}
                            onChange={(event) => setDeliveryMode(event.target.value as DeliveryMode)}
                        >
                            <option value="face_to_face">Face to face</option>
                            <option value="online">Online</option>
                        </select>
                    </div>
                    <div className="space-y-2">
                        <Label htmlFor="meeting-start">Starts</Label>
                        <Input
                            id="meeting-start"
                            type="time"
                            className="h-11 w-36"
                            value={start}
                            onChange={(event) => setStart(event.target.value)}
                        />
                    </div>
                    <div className="space-y-2">
                        <Label htmlFor="meeting-end">Ends</Label>
                        <Input id="meeting-end" type="time" className="h-11 w-36" value={end} onChange={(event) => setEnd(event.target.value)} />
                    </div>
                    <Button type="button" className="h-11 px-4" onClick={addMeetings}>
                        <Plus className="size-4" aria-hidden="true" />
                        Add {days.length} {days.length === 1 ? "meeting" : "meetings"}
                    </Button>
                </div>

                {error ? (
                    <p role="alert" className="text-destructive text-sm">
                        {error}
                    </p>
                ) : null}
            </section>

            {schedules.length === 0 ? (
                <div className="flex items-start gap-4 rounded-lg border border-dashed p-5">
                    <CalendarDays className="text-muted-foreground mt-0.5 size-5 shrink-0" aria-hidden="true" />
                    <p className="text-muted-foreground max-w-xl text-sm">
                        No meetings yet. You can save the class now and set its times later from the schedule planner.
                    </p>
                </div>
            ) : (
                <div className="space-y-3">
                    <p className="text-muted-foreground text-sm" aria-live="polite">
                        <span className="text-foreground font-medium">
                            {schedules.length} {schedules.length === 1 ? "meeting" : "meetings"}
                        </span>{" "}
                        · {Number(weeklyHours.toFixed(1))} hours per week
                    </p>
                    <ul className="divide-y rounded-lg border">
                        {schedules.map((schedule, index) => {
                            const issue = sessionIssue(
                                schedule,
                                schedules.filter((_, otherIndex) => otherIndex !== index),
                            );

                            return (
                                <li key={schedule.id ?? `meeting-${index}`} className={cn("space-y-3 p-4", issue && "bg-destructive/5")}>
                                    <div className="grid gap-3 sm:grid-cols-3">
                                        <div className="space-y-1.5">
                                            <Label htmlFor={`meeting-${index}-day`}>Day</Label>
                                            <select
                                                id={`meeting-${index}-day`}
                                                className={selectClassName}
                                                value={schedule.day_of_week}
                                                onChange={(event) => update(index, { day_of_week: event.target.value })}
                                            >
                                                {dayOptions.map((day) => (
                                                    <option key={day.value} value={day.value}>
                                                        {day.label}
                                                    </option>
                                                ))}
                                            </select>
                                        </div>
                                        <div className="space-y-1.5">
                                            <Label htmlFor={`meeting-${index}-start`}>Starts</Label>
                                            <Input
                                                id={`meeting-${index}-start`}
                                                type="time"
                                                className="h-11"
                                                value={schedule.start_time}
                                                aria-invalid={Boolean(issue)}
                                                onChange={(event) => update(index, { start_time: event.target.value })}
                                            />
                                        </div>
                                        <div className="space-y-1.5">
                                            <Label htmlFor={`meeting-${index}-end`}>Ends</Label>
                                            <Input
                                                id={`meeting-${index}-end`}
                                                type="time"
                                                className="h-11"
                                                value={schedule.end_time}
                                                aria-invalid={Boolean(issue)}
                                                onChange={(event) => update(index, { end_time: event.target.value })}
                                            />
                                        </div>
                                        <div className="space-y-1.5">
                                            <Label htmlFor={`meeting-${index}-delivery`}>Meeting type</Label>
                                            <select
                                                id={`meeting-${index}-delivery`}
                                                className={selectClassName}
                                                value={schedule.delivery_mode ?? defaultDeliveryMode}
                                                onChange={(event) =>
                                                    update(index, {
                                                        delivery_mode: event.target.value as DeliveryMode,
                                                        room_id: event.target.value === "online" ? null : schedule.room_id,
                                                    })
                                                }
                                            >
                                                <option value="face_to_face">Face to face</option>
                                                <option value="online">Online</option>
                                            </select>
                                        </div>
                                        {(schedule.delivery_mode ?? defaultDeliveryMode) === "online" ? (
                                            <p className="text-muted-foreground self-end py-3 text-sm">Online · No room reserved</p>
                                        ) : (
                                            <div className="space-y-1.5">
                                                <Label htmlFor={`meeting-${index}-room`}>Room</Label>
                                                <select
                                                    id={`meeting-${index}-room`}
                                                    className={selectClassName}
                                                    value={schedule.room_id ?? 0}
                                                    onChange={(event) => update(index, { room_id: Number(event.target.value) || null })}
                                                >
                                                    <option value={0}>Assign later</option>
                                                    {rooms.map((room) => (
                                                        <option key={room.id} value={room.id}>
                                                            {room.label}
                                                        </option>
                                                    ))}
                                                </select>
                                            </div>
                                        )}
                                    </div>
                                    <div className="flex items-center justify-between gap-2">
                                        <p
                                            className={cn("text-sm", issue ? "text-destructive" : "text-muted-foreground")}
                                            role={issue ? "alert" : undefined}
                                        >
                                            {issue ?? `Every ${schedule.day_of_week}`}
                                        </p>
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            className="text-muted-foreground hover:text-destructive h-10 px-3"
                                            aria-label={`Remove meeting ${index + 1}`}
                                            onClick={() => onSchedulesChange(schedules.filter((_, itemIndex) => itemIndex !== index))}
                                        >
                                            <Trash2 className="size-4" aria-hidden="true" />
                                            Remove
                                        </Button>
                                    </div>
                                </li>
                            );
                        })}
                    </ul>
                </div>
            )}
        </div>
    );
}
