import { WeeklyScheduleDay, WeeklyScheduleEntry } from "@/components/dashboard/weekly-timetable";

export type DiffStatus = "unchanged" | "added" | "changed" | "conflict";

export interface SnapshotEntry {
    day: string;
    subject_code: string;
    subject_title: string;
    section: string;
    room: string;
    start_time: string;
    end_time: string;
    start_minutes: number | null;
    end_minutes: number | null;
}

export interface ScheduleBaselineSnapshot {
    savedAt: string;
    entries: SnapshotEntry[];
}

export interface CurrentEntry extends WeeklyScheduleEntry {
    day: string;
    start_minutes: number | null;
    end_minutes: number | null;
    group_key: string;
}

// Reuse or duplicate parse/time logic for now to be safe
export const parseTimeToMinutes = (value?: string | null): number | null => {
    if (!value) return null;

    const trimmed = value.trim();

    const hhmm24 = trimmed.match(/^(\d{1,2}):(\d{2})$/);
    if (hhmm24) {
        const hours = Number(hhmm24[1]);
        const minutes = Number(hhmm24[2]);
        if (Number.isNaN(hours) || Number.isNaN(minutes)) return null;
        return hours * 60 + minutes;
    }

    const hhmm12 = trimmed.match(/^(\d{1,2}):(\d{2})\s*(AM|PM)$/i);
    if (hhmm12) {
        let hours = Number(hhmm12[1]);
        const minutes = Number(hhmm12[2]);
        const meridiem = hhmm12[3].toUpperCase();
        if (Number.isNaN(hours) || Number.isNaN(minutes)) return null;

        if (meridiem === "AM") {
            hours = hours === 12 ? 0 : hours;
        } else {
            hours = hours === 12 ? 12 : hours + 12;
        }

        return hours * 60 + minutes;
    }

    return null;
};

export const buildSnapshot = (schedule: WeeklyScheduleDay[]): ScheduleBaselineSnapshot => {
    const entries: SnapshotEntry[] = schedule.flatMap((day) =>
        (day.entries ?? []).map((entry) => {
            const start_value = entry.start_time_24h ?? entry.start_time;
            const end_value = entry.end_time_24h ?? entry.end_time;

            return {
                day: day.day,
                subject_code: entry.subject_code,
                subject_title: entry.subject_title,
                section: entry.section,
                room: entry.room,
                start_time: entry.start_time,
                end_time: entry.end_time,
                start_minutes: parseTimeToMinutes(start_value),
                end_minutes: parseTimeToMinutes(end_value),
            };
        }),
    );

    return {
        savedAt: new Date().toISOString(),
        entries,
    };
};

export const normalizeCurrentEntries = (schedule: WeeklyScheduleDay[]): CurrentEntry[] => {
    return schedule.flatMap((day) =>
        (day.entries ?? []).map((entry) => {
            const group_key = `${entry.subject_code}::${entry.section}::${day.day}`;
            return {
                ...entry,
                day: day.day,
                group_key,
                start_minutes: parseTimeToMinutes(entry.start_time_24h ?? entry.start_time),
                end_minutes: parseTimeToMinutes(entry.end_time_24h ?? entry.end_time),
            };
        }),
    );
};

export const detectConflicts = (entries: CurrentEntry[]): Set<string> => {
    const conflicts = new Set<string>();
    const byDay = new Map<string, CurrentEntry[]>();

    entries.forEach((entry) => {
        const dayKey = entry.day.toLowerCase();
        const existing = byDay.get(dayKey);
        if (existing) {
            existing.push(entry);
        } else {
            byDay.set(dayKey, [entry]);
        }
    });

    for (const group of byDay.values()) {
        const sorted = group
            .filter((entry) => entry.start_minutes !== null && entry.end_minutes !== null)
            .slice()
            .sort((a, b) => (a.start_minutes ?? 0) - (b.start_minutes ?? 0));

        for (let idx = 0; idx < sorted.length - 1; idx++) {
            const current = sorted[idx];
            const next = sorted[idx + 1];

            if ((current.end_minutes ?? 0) > (next.start_minutes ?? 0)) {
                conflicts.add(String(current.id));
                conflicts.add(String(next.id));
            }
        }
    }

    return conflicts;
};

export const computeDiff = (current: CurrentEntry[], baseline: ScheduleBaselineSnapshot | null) => {
    const statusById = new Map<string, DiffStatus>();
    const changed: Array<{ before: SnapshotEntry; after: CurrentEntry }> = [];
    const added: CurrentEntry[] = [];
    const removed: SnapshotEntry[] = [];

    if (!baseline) {
        current.forEach((entry) => {
            statusById.set(String(entry.id), "unchanged");
        });

        return { statusById, changed, added, removed };
    }

    const baselineByGroup = new Map<string, SnapshotEntry[]>();
    baseline.entries.forEach((entry) => {
        const groupKey = `${entry.subject_code}::${entry.section}::${entry.day}`;
        const existing = baselineByGroup.get(groupKey);
        if (existing) {
            existing.push(entry);
        } else {
            baselineByGroup.set(groupKey, [entry]);
        }
    });

    const currentByGroup = new Map<string, CurrentEntry[]>();
    current.forEach((entry) => {
        const existing = currentByGroup.get(entry.group_key);
        if (existing) {
            existing.push(entry);
        } else {
            currentByGroup.set(entry.group_key, [entry]);
        }
    });

    const allGroupKeys = new Set<string>([...baselineByGroup.keys(), ...currentByGroup.keys()]);

    for (const groupKey of allGroupKeys) {
        const baselineGroup = (baselineByGroup.get(groupKey) ?? []).slice();
        const currentGroup = (currentByGroup.get(groupKey) ?? []).slice();

        baselineGroup.sort((a, b) => (a.start_minutes ?? 0) - (b.start_minutes ?? 0));
        currentGroup.sort((a, b) => (a.start_minutes ?? 0) - (b.start_minutes ?? 0));

        const shared = Math.min(baselineGroup.length, currentGroup.length);

        for (let idx = 0; idx < shared; idx++) {
            const before = baselineGroup[idx];
            const after = currentGroup[idx];

            const isChanged = before.room !== after.room || before.start_minutes !== after.start_minutes || before.end_minutes !== after.end_minutes;

            if (isChanged) {
                statusById.set(String(after.id), "changed");
                changed.push({ before, after });
            } else {
                statusById.set(String(after.id), "unchanged");
            }
        }

        if (currentGroup.length > shared) {
            currentGroup.slice(shared).forEach((entry) => {
                statusById.set(String(entry.id), "added");
                added.push(entry);
            });
        }

        if (baselineGroup.length > shared) {
            removed.push(...baselineGroup.slice(shared));
        }
    }

    return { statusById, changed, added, removed };
};
