import type { WeeklyScheduleEntry } from "./types";

export const scheduleDayOrder = ["Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday", "Sunday"];

export function parseTimeToMinutes(time: string): number {
    const [hours, minutes] = (time || "0:0").split(":").map(Number);
    return hours * 60 + minutes;
}

export function formatTimeLabel(minutesOfDay: number): string {
    const hours = Math.floor(minutesOfDay / 60);
    const minutes = minutesOfDay % 60;
    return `${((hours + 11) % 12) + 1}:${String(minutes).padStart(2, "0")} ${hours >= 12 ? "PM" : "AM"}`;
}

export function getScheduleEntriesForDay(
    schedule: Record<string, WeeklyScheduleEntry[]> | undefined,
    dayKeyLowercase: string,
): WeeklyScheduleEntry[] {
    if (!schedule) return [];

    const normalized = dayKeyLowercase.toLowerCase();
    const titleCase = normalized.charAt(0).toUpperCase() + normalized.slice(1);
    const shortTitleCase = titleCase.slice(0, 3);

    const directHit = schedule[normalized] ?? schedule[titleCase] ?? schedule[shortTitleCase];
    if (directHit) return directHit;

    const matchedKey = Object.keys(schedule).find((key) => key.toLowerCase() === normalized || key.toLowerCase() === normalized.slice(0, 3));
    if (matchedKey) return schedule[matchedKey] ?? [];

    return [];
}

export type TimetableBlock = {
    id: string;
    dayKey: string;
    startMinutes: number;
    endMinutes: number;
    timeRange: string;
    roomName: string;
    hasConflict: boolean;
    lane: number;
    laneCount: number;
};

export function buildTimetableBlocksForDay(dayKey: string, entries: WeeklyScheduleEntry[]): TimetableBlock[] {
    const blocks = entries
        .map((entry) => {
            const startMinutes = parseTimeToMinutes(entry.start_time);
            const endMinutes = parseTimeToMinutes(entry.end_time);
            if (!Number.isFinite(startMinutes) || !Number.isFinite(endMinutes) || endMinutes <= startMinutes) return null;

            return {
                id: `${dayKey}-${entry.start_time}-${entry.end_time}-${entry.room.id}`,
                dayKey,
                startMinutes,
                endMinutes,
                timeRange: entry.time_range,
                roomName: entry.room.name,
                hasConflict: entry.has_conflict,
            };
        })
        .filter((block): block is Omit<TimetableBlock, "lane" | "laneCount"> => block !== null)
        .sort((a, b) => a.startMinutes - b.startMinutes || a.endMinutes - b.endMinutes);

    if (blocks.length === 0) return [];

    const result: TimetableBlock[] = [];
    let idx = 0;

    while (idx < blocks.length) {
        const group: Array<Omit<TimetableBlock, "lane" | "laneCount">> = [];
        let groupEnd = blocks[idx].endMinutes;

        while (idx < blocks.length && blocks[idx].startMinutes < groupEnd) {
            const next = blocks[idx];
            group.push(next);
            groupEnd = Math.max(groupEnd, next.endMinutes);
            idx++;
        }

        if (group.length === 0) {
            idx++;
            continue;
        }

        const lanes: number[] = [];
        const groupWithLanes: TimetableBlock[] = [];

        group
            .slice()
            .sort((a, b) => a.startMinutes - b.startMinutes || a.endMinutes - b.endMinutes)
            .forEach((entry) => {
                let laneIndex = lanes.findIndex((laneEnd) => laneEnd <= entry.startMinutes);
                if (laneIndex === -1) {
                    laneIndex = lanes.length;
                    lanes.push(entry.endMinutes);
                } else {
                    lanes[laneIndex] = entry.endMinutes;
                }

                groupWithLanes.push({
                    ...entry,
                    lane: laneIndex,
                    laneCount: 0,
                });
            });

        const laneCount = Math.max(1, lanes.length);
        groupWithLanes.forEach((entry) => {
            result.push({ ...entry, laneCount });
        });
    }

    return result;
}

export function roundDown(value: number, step: number): number {
    return Math.floor(value / step) * step;
}

export function roundUp(value: number, step: number): number {
    return Math.ceil(value / step) * step;
}

export type TimetableWindow = {
    startMinutes: number;
    endMinutes: number;
    slotMinutes: number;
    slotHeight: number;
};

export function computeTimetableWindow(blocks: TimetableBlock[], slotMinutes = 30): TimetableWindow | null {
    if (blocks.length === 0) return null;

    const start = blocks.reduce((min, block) => Math.min(min, block.startMinutes), blocks[0].startMinutes);
    const end = blocks.reduce((max, block) => Math.max(max, block.endMinutes), blocks[0].endMinutes);

    if (end <= start) return null;

    const roundedStart = Math.max(0, roundDown(start - 30, slotMinutes));
    const roundedEnd = Math.min(24 * 60, roundUp(end + 30, slotMinutes));

    return {
        startMinutes: roundedStart,
        endMinutes: roundedEnd,
        slotMinutes,
        slotHeight: 40,
    };
}
