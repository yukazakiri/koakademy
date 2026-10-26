export type WeeklyScheduleEvent = {
    id: string;
    day: string;
    start: string;
    end: string;
    title: string;
    location?: string;
    online?: boolean;
    conflict?: boolean;
};

export const weekDays = ["Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday", "Sunday"] as const;

export type WeeklyScheduleBlock = {
    event: WeeklyScheduleEvent;
    startMinutes: number;
    endMinutes: number;
    lane: number;
    laneCount: number;
};

export function scheduleMinutes(time: string): number {
    const match = time.trim().match(/^(\d{1,2}):(\d{2})(?::(\d{2}))?\s*(AM|PM)?$/i);
    if (!match) return NaN;
    let hours = Number(match[1]);
    const minutes = Number(match[2]);
    const seconds = Number(match[3] ?? 0);
    if (match[4]) {
        if (hours < 1 || hours > 12) return NaN;
        hours = (hours % 12) + (match[4].toUpperCase() === "PM" ? 12 : 0);
    }
    if (hours > 24 || minutes > 59 || seconds > 59 || (hours === 24 && (minutes !== 0 || seconds !== 0))) return NaN;
    return hours * 60 + minutes + seconds / 60;
}

export function scheduleTimeLabel(minutes: number): string {
    const hour = Math.floor(minutes / 60) % 24;
    const minute = Math.floor(minutes % 60);
    return `${hour % 12 || 12}:${String(minute).padStart(2, "0")} ${hour >= 12 ? "PM" : "AM"}`;
}

export function scheduleTimeRange(start: number, end: number): string {
    const shortLabel = (minutes: number) => scheduleTimeLabel(minutes).replace(":00", "");
    const from = shortLabel(start);
    const to = shortLabel(end);
    return `${from.slice(-2) === to.slice(-2) ? from.slice(0, -3) : from}–${to}`;
}

export function buildScheduleDays(events: WeeklyScheduleEvent[]) {
    return weekDays.map((day) => {
        const blocks: WeeklyScheduleBlock[] = events
            .filter((event) => event.day.trim().toLowerCase().slice(0, 3) === day.slice(0, 3).toLowerCase())
            .map((event) => ({ event, startMinutes: scheduleMinutes(event.start), endMinutes: scheduleMinutes(event.end), lane: 0, laneCount: 1 }))
            .filter((block) => Number.isFinite(block.startMinutes) && Number.isFinite(block.endMinutes) && block.endMinutes > block.startMinutes)
            .sort((a, b) => a.startMinutes - b.startMinutes || a.endMinutes - b.endMinutes || a.event.id.localeCompare(b.event.id));

        let index = 0;
        while (index < blocks.length) {
            const groupStart = index;
            let groupEnd = blocks[index].endMinutes;
            const laneEnds: number[] = [];

            while (index < blocks.length && blocks[index].startMinutes < groupEnd) {
                const block = blocks[index];
                let lane = laneEnds.findIndex((end) => end <= block.startMinutes);
                if (lane === -1) lane = laneEnds.length;
                laneEnds[lane] = block.endMinutes;
                block.lane = lane;
                groupEnd = Math.max(groupEnd, block.endMinutes);
                index++;
            }

            for (let i = groupStart; i < index; i++) blocks[i].laneCount = laneEnds.length;
        }

        return { day, blocks };
    });
}

export function scheduleWindow(blocks: WeeklyScheduleBlock[]) {
    if (!blocks.length) return null;
    const first = Math.min(...blocks.map((block) => block.startMinutes));
    const last = Math.max(...blocks.map((block) => block.endMinutes));
    let start = Math.max(0, Math.floor((first - 30) / 60) * 60);
    let end = Math.min(1440, Math.ceil((last + 30) / 60) * 60);

    // Keep a short teaching day readable without showing an entire empty day.
    if (end - start < 240) {
        end = Math.min(1440, start + 240);
        start = Math.max(0, end - 240);
    }

    return { start, end };
}
