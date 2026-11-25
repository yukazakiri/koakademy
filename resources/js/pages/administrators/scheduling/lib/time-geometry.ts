export const WEEKDAYS_MON_SAT = ["Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday"] as const;
export const ALL_DAYS = ["Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday", "Sunday"] as const;

export type DayName = (typeof ALL_DAYS)[number];
export type GridDensity = "birdseye" | "standard" | "expanded";

export const DEFAULT_START_HOUR = 7;
export const DEFAULT_END_HOUR = 21; // 9:00 PM
export const SNAP_MINUTES = 15;
export const MIN_DURATION_MINUTES = 30;

export const DENSITY_ROW_HEIGHT: Record<GridDensity, number> = {
    birdseye: 42,
    standard: 60,
    expanded: 84,
};

export function parseTimeToMinutes(value: string | null | undefined): number {
    if (!value) return 8 * 60;
    const trimmed = value.trim();

    // 12-hour format: e.g. "08:30 AM" or "1:15 PM"
    const ampmMatch = trimmed.match(/^(\d{1,2}):(\d{2})(?::\d{2})?\s*(AM|PM)$/i);
    if (ampmMatch) {
        let hours = Number(ampmMatch[1]);
        const minutes = Number(ampmMatch[2]);
        const meridiem = ampmMatch[3].toUpperCase();
        if (meridiem === "PM" && hours < 12) hours += 12;
        if (meridiem === "AM" && hours === 12) hours = 0;
        return hours * 60 + minutes;
    }

    // 24-hour format: e.g. "08:30" or "13:45:00"
    const twentyFourMatch = trimmed.match(/^(\d{1,2}):(\d{2})(?::\d{2})?$/);
    if (twentyFourMatch) {
        return Number(twentyFourMatch[1]) * 60 + Number(twentyFourMatch[2]);
    }

    return 8 * 60;
}

export function minutesToApiTime(totalMinutes: number): string {
    const clamped = Math.max(0, Math.min(23 * 60 + 55, Math.round(totalMinutes)));
    const hours = Math.floor(clamped / 60);
    const mins = clamped % 60;
    return `${String(hours).padStart(2, "0")}:${String(mins).padStart(2, "0")}`;
}

export function minutesToDisplayTime(totalMinutes: number): string {
    const clamped = Math.max(0, Math.min(24 * 60, Math.round(totalMinutes)));
    const rawHour = Math.floor(clamped / 60);
    const mins = clamped % 60;
    const meridiem = rawHour >= 12 && rawHour < 24 ? "PM" : "AM";
    const hour12 = rawHour % 12 === 0 ? 12 : rawHour % 12;
    return `${hour12}:${String(mins).padStart(2, "0")} ${meridiem}`;
}

export function minutesToShortHour(hour: number): string {
    const meridiem = hour >= 12 && hour < 24 ? "PM" : "AM";
    const hour12 = hour % 12 === 0 ? 12 : hour % 12;
    return `${hour12} ${meridiem}`;
}

export function formatTimeRange(startMinutes: number, endMinutes: number): string {
    return `${minutesToDisplayTime(startMinutes)} – ${minutesToDisplayTime(endMinutes)}`;
}

export function snapMinutes(minutes: number, step: number = SNAP_MINUTES): number {
    return Math.round(minutes / step) * step;
}

export function clampMinutes(minutes: number, minMinutes: number, maxMinutes: number): number {
    return Math.max(minMinutes, Math.min(maxMinutes, minutes));
}

export function getVisibleDays(allowSunday: boolean, hasSundaySchedules: boolean): string[] {
    return allowSunday || hasSundaySchedules ? [...ALL_DAYS] : [...WEEKDAYS_MON_SAT];
}

export type TimeBlockInput = {
    key: string;
    startMin: number;
    endMin: number;
};

export type PackedBlockLayout = {
    lane: number;
    laneCount: number;
};

/**
 * Computes non-overlapping horizontal columns (lanes) for concurrent time blocks
 * within a single day column or horizontal swimlane row.
 */
export function packOverlappingBlocks<T extends TimeBlockInput>(blocks: T[]): Map<string, PackedBlockLayout> {
    const result = new Map<string, PackedBlockLayout>();
    if (blocks.length === 0) return result;

    const sorted = [...blocks].sort((a, b) => a.startMin - b.startMin || b.endMin - a.endMin);

    // Partition into connected overlap clusters
    let cluster: T[] = [];
    let clusterMaxEnd = -1;

    const flushCluster = (items: T[]) => {
        const laneEndTimes: number[] = [];
        const assigned: Array<{ key: string; lane: number }> = [];

        for (const item of items) {
            let assignedLane = -1;
            for (let i = 0; i < laneEndTimes.length; i++) {
                if (laneEndTimes[i] <= item.startMin) {
                    assignedLane = i;
                    laneEndTimes[i] = item.endMin;
                    break;
                }
            }
            if (assignedLane === -1) {
                assignedLane = laneEndTimes.length;
                laneEndTimes.push(item.endMin);
            }
            assigned.push({ key: item.key, lane: assignedLane });
        }

        const totalLanes = Math.max(1, laneEndTimes.length);
        for (const entry of assigned) {
            result.set(entry.key, { lane: entry.lane, laneCount: totalLanes });
        }
    };

    for (const block of sorted) {
        if (cluster.length === 0) {
            cluster = [block];
            clusterMaxEnd = block.endMin;
        } else if (block.startMin < clusterMaxEnd) {
            cluster.push(block);
            clusterMaxEnd = Math.max(clusterMaxEnd, block.endMin);
        } else {
            flushCluster(cluster);
            cluster = [block];
            clusterMaxEnd = block.endMin;
        }
    }

    if (cluster.length > 0) {
        flushCluster(cluster);
    }

    return result;
}
