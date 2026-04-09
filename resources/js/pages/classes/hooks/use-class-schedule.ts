import { ClassData } from "@/components/data-table";
import { useMemo } from "react";

export type DayOfWeek = "Monday" | "Tuesday" | "Wednesday" | "Thursday" | "Friday" | "Saturday" | "Sunday";

export type ClassSchedule = {
    day_of_week: DayOfWeek | string;
    start_time: string;
    end_time: string;
    room_id?: string | number | null;
};

export type AccentVisual = {
    className: string;
    style?: React.CSSProperties;
};

export type ScheduleEvent = {
    classItem: ClassData;
    day: DayOfWeek;
    startMinutes: number;
    endMinutes: number;
    startLabel: string;
    endLabel: string;
    roomLabel: string;
    accent: AccentVisual;
};

export const DAYS: DayOfWeek[] = ["Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday"];

const DAY_TO_INDEX: Record<DayOfWeek, number> = {
    Monday: 1,
    Tuesday: 2,
    Wednesday: 3,
    Thursday: 4,
    Friday: 5,
    Saturday: 6,
    Sunday: 0,
};

export function parseTimeToMinutes(value: string): number | null {
    if (!value) {
        return null;
    }

    const match = value.match(/^(\d{1,2}):(\d{2})/);
    if (!match) {
        return null;
    }

    const hours = Number.parseInt(match[1] ?? "", 10);
    const minutes = Number.parseInt(match[2] ?? "", 10);

    if (!Number.isFinite(hours) || !Number.isFinite(minutes)) {
        return null;
    }

    if (hours < 0 || hours > 23 || minutes < 0 || minutes > 59) {
        return null;
    }

    return hours * 60 + minutes;
}

export function formatMinutesToTime(minutes: number): string {
    const hours = Math.floor(minutes / 60);
    const mins = minutes % 60;
    return `${String(hours).padStart(2, "0")}:${String(mins).padStart(2, "0")}`;
}

export function getDayNameFromDate(date: Date): DayOfWeek {
    const dayIndex = date.getDay();
    const map: Record<number, DayOfWeek> = {
        0: "Sunday",
        1: "Monday",
        2: "Tuesday",
        3: "Wednesday",
        4: "Thursday",
        5: "Friday",
        6: "Saturday",
    };

    return map[dayIndex];
}

export function formatTime12Hour(minutes: number): string {
    const h = Math.floor(minutes / 60);
    const m = minutes % 60;
    const ampm = h >= 12 ? "PM" : "AM";
    const h12 = h % 12 || 12;
    return `${h12}:${String(m).padStart(2, "0")} ${ampm}`;
}

export function formatClassSchedule(schedules: any[]): string | undefined {
    if (!Array.isArray(schedules) || schedules.length === 0) return undefined;

    const validSchedules = schedules.filter((s) => s.day_of_week && s.start_time && s.end_time);
    if (validSchedules.length === 0) return undefined;

    // Group by start_time-end_time
    const groups = new Map<string, DayOfWeek[]>();

    validSchedules.forEach((s) => {
        const key = `${s.start_time}-${s.end_time}`;
        const current = groups.get(key) || [];
        if (!current.includes(s.day_of_week)) {
            current.push(s.day_of_week);
        }
        groups.set(key, current);
    });

    const parts: string[] = [];

    groups.forEach((days, timeKey) => {
        const [startStr, endStr] = timeKey.split("-");
        const startMin = parseTimeToMinutes(startStr);
        const endMin = parseTimeToMinutes(endStr);

        if (startMin === null || endMin === null) return;

        const timeStr = `${formatTime12Hour(startMin)} - ${formatTime12Hour(endMin)}`;

        // Sort days
        const sortedDays = days.sort((a, b) => DAY_TO_INDEX[a] - DAY_TO_INDEX[b]);
        const shortDays = sortedDays.map((d) => d.substring(0, 3));

        parts.push(`${shortDays.join("/")} ${timeStr}`);
    });

    return parts.join(", ");
}

export function getTimeUntil(target: Date, now: Date): string {
    const diffMs = target.getTime() - now.getTime();
    if (diffMs <= 0) {
        return "now";
    }

    const diffMinutes = Math.floor(diffMs / 60000);
    const hours = Math.floor(diffMinutes / 60);
    const minutes = diffMinutes % 60;

    if (hours <= 0) {
        return `${minutes}m`;
    }

    if (minutes <= 0) {
        return `${hours}h`;
    }

    return `${hours}h ${minutes}m`;
}

export function isValidDay(value: string): value is DayOfWeek {
    return (
        value === "Monday" ||
        value === "Tuesday" ||
        value === "Wednesday" ||
        value === "Thursday" ||
        value === "Friday" ||
        value === "Saturday" ||
        value === "Sunday"
    );
}

function resolveAccentVisual(classItem: ClassData): AccentVisual {
    const settings = (classItem.settings ?? {}) as Record<string, unknown>;
    const settingsAccent = typeof settings.accent_color === "string" ? settings.accent_color : null;
    const modelAccent = typeof classItem.accent_color === "string" ? classItem.accent_color : null;

    const raw = settingsAccent || modelAccent;
    if (raw && raw.includes("bg-")) {
        return { className: raw };
    }

    if (raw) {
        return {
            className: "bg-transparent",
            style: { backgroundColor: raw },
        };
    }

    return { className: "bg-primary" };
}

function buildScheduleEvents(
    classes: ClassData[],
    roomsById: Map<string, string>,
): {
    events: ScheduleEvent[];
    unscheduled: ClassData[];
    conflictIds: Set<string>;
} {
    const events: ScheduleEvent[] = [];
    const unscheduled: ClassData[] = [];

    for (const classItem of classes) {
        const schedules = (classItem.schedules ?? []) as unknown[];

        const parsedSchedules = schedules
            .map((schedule) => schedule as Partial<ClassSchedule>)
            .filter((schedule) =>
                Boolean(schedule.day_of_week && schedule.start_time && schedule.end_time && isValidDay(String(schedule.day_of_week))),
            ) as Array<Required<Pick<ClassSchedule, "day_of_week" | "start_time" | "end_time">> & Partial<Pick<ClassSchedule, "room_id">>>;

        if (!parsedSchedules.length) {
            unscheduled.push(classItem);
            continue;
        }

        for (const schedule of parsedSchedules) {
            const startMinutes = parseTimeToMinutes(String(schedule.start_time));
            const endMinutes = parseTimeToMinutes(String(schedule.end_time));

            if (startMinutes === null || endMinutes === null || endMinutes <= startMinutes) {
                continue;
            }

            const roomId = schedule.room_id !== undefined && schedule.room_id !== null ? String(schedule.room_id) : null;

            const roomLabel = classItem.room || (roomId ? roomsById.get(roomId) : undefined) || "Room TBA";

            events.push({
                classItem,
                day: String(schedule.day_of_week) as DayOfWeek,
                startMinutes,
                endMinutes,
                startLabel: formatMinutesToTime(startMinutes),
                endLabel: formatMinutesToTime(endMinutes),
                roomLabel,
                accent: resolveAccentVisual(classItem),
            });
        }
    }

    // Conflict detection
    const conflictIds = new Set<string>();
    for (const day of DAYS) {
        const dayEvents = events.filter((event) => event.day === day).sort((a, b) => a.startMinutes - b.startMinutes);

        for (let index = 1; index < dayEvents.length; index++) {
            const previous = dayEvents[index - 1];
            const current = dayEvents[index];

            if (previous && current && current.startMinutes < previous.endMinutes) {
                conflictIds.add(String(previous.classItem.id));
                conflictIds.add(String(current.classItem.id));
            }
        }
    }

    return { events, unscheduled, conflictIds };
}

function getNextEvent(events: ScheduleEvent[], now: Date): { event: ScheduleEvent; start: Date } | null {
    const currentDay = getDayNameFromDate(now);
    const nowMinutes = now.getHours() * 60 + now.getMinutes();

    let best: { event: ScheduleEvent; start: Date } | null = null;

    for (const event of events) {
        const targetDayIndex = DAY_TO_INDEX[event.day];
        const currentDayIndex = DAY_TO_INDEX[currentDay];

        let deltaDays = targetDayIndex - currentDayIndex;
        if (deltaDays < 0) {
            deltaDays += 7;
        }

        if (deltaDays === 0 && event.startMinutes <= nowMinutes) {
            deltaDays = 7;
        }

        const start = new Date(now);
        start.setHours(0, 0, 0, 0);
        start.setDate(start.getDate() + deltaDays);
        start.setMinutes(event.startMinutes);

        if (!best || start.getTime() < best.start.getTime()) {
            best = { event, start };
        }
    }

    return best;
}

export function useClassSchedule(classes: ClassData[], rooms: { id: number; name: string }[]) {
    const roomsById = useMemo(() => {
        const map = new Map<string, string>();
        for (const room of rooms ?? []) {
            map.set(String(room.id), room.name);
        }
        return map;
    }, [rooms]);

    const { events, unscheduled, conflictIds } = useMemo(() => {
        return buildScheduleEvents(classes, roomsById);
    }, [classes, roomsById]);

    const nextUp = useMemo(() => {
        const now = new Date();
        const next = getNextEvent(events, now);
        if (!next) {
            return null;
        }

        return {
            ...next,
            now,
            in: getTimeUntil(next.start, now),
        };
    }, [events]);

    return {
        roomsById,
        events,
        unscheduled,
        conflictIds,
        nextUp,
    };
}
