import { WeeklyScheduleDay } from "@/components/dashboard/weekly-timetable";
import { useMemo, useState } from "react";
import { DiffStatus } from "./use-schedule-diff";

export const DAYS = ["Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday"];

export interface ScheduleFilterState {
    query: string;
    day: string;
    showOnlyChanges: boolean;
}

export const entryMatchesQuery = (
    entry: { day: string; subject_code: string; subject_title: string; section: string; room: string },
    query: string,
): boolean => {
    const q = query.trim().toLowerCase();
    if (!q) return true;

    return (
        entry.day.toLowerCase().includes(q) ||
        entry.subject_code.toLowerCase().includes(q) ||
        entry.subject_title.toLowerCase().includes(q) ||
        entry.section.toLowerCase().includes(q) ||
        entry.room.toLowerCase().includes(q)
    );
};

export const toFilteredSchedule = (
    schedule: WeeklyScheduleDay[],
    query: string,
    day: string,
    statusById: Map<string, DiffStatus>,
    conflicts: Set<string>,
    onlyChanges: boolean,
): WeeklyScheduleDay[] => {
    const targetDay = day === "All" ? null : day;

    const filtered = schedule
        .filter((d) => !targetDay || d.day === targetDay)
        .map((d) => {
            const entries = (d.entries ?? []).filter((entry) => {
                const matches = entryMatchesQuery(
                    {
                        day: d.day,
                        subject_code: entry.subject_code,
                        subject_title: entry.subject_title,
                        section: entry.section,
                        room: entry.room,
                    },
                    query,
                );

                if (!matches) return false;

                if (!onlyChanges) return true;

                const id = String(entry.id);
                if (conflicts.has(id)) return true;
                return statusById.get(id) !== "unchanged";
            });

            return { ...d, entries };
        });

    return filtered.filter((d) => d.entries.length > 0); // Optional: remove days with 0 entries
};

export function useScheduleFilter(initialDay = "All") {
    const [query, setQuery] = useState("");
    const [day, setDay] = useState<string>(initialDay);
    const [showOnlyChanges, setShowOnlyChanges] = useState(false);

    const dayOptions = useMemo(() => ["All", ...DAYS], []);

    const resetFilters = () => {
        setQuery("");
        setDay("All");
        setShowOnlyChanges(false);
    };

    return {
        query,
        setQuery,
        day,
        setDay,
        showOnlyChanges,
        setShowOnlyChanges,
        dayOptions,
        resetFilters,
    };
}
