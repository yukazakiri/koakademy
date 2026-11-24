import type {
    ClassScheduleData,
    FacultyOption,
    RoomOption,
    ScheduleConflict,
    ScheduleEntry,
} from "../../components/scheduling-types";

export const CANVAS_START_HOUR = 7;
export const CANVAS_END_HOUR = 21;
export const CANVAS_START_MIN = CANVAS_START_HOUR * 60;
export const CANVAS_END_MIN = CANVAS_END_HOUR * 60;
export const CANVAS_HOURS = Array.from(
    { length: CANVAS_END_HOUR - CANVAS_START_HOUR },
    (_, i) => CANVAS_START_HOUR + i,
);

export const BASE_DAYS = ["Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday"] as const;
export const ALL_DAYS = [...BASE_DAYS, "Sunday"] as const;

export function getCanvasDays(allowSunday: boolean): string[] {
    return allowSunday ? [...ALL_DAYS] : [...BASE_DAYS];
}

export function parseTimeToMinutes(value: string | null | undefined): number {
    if (!value) return 8 * 60;
    const trimmed = value.trim();
    const ampm = trimmed.match(/^(\d{1,2}):(\d{2})\s*(AM|PM)$/i);
    if (ampm) {
        let hour = Number(ampm[1]);
        const minute = Number(ampm[2]);
        const meridiem = ampm[3].toUpperCase();
        if (meridiem === "PM" && hour < 12) hour += 12;
        if (meridiem === "AM" && hour === 12) hour = 0;
        return hour * 60 + minute;
    }
    const hhmm = trimmed.match(/^(\d{1,2}):(\d{2})/);
    if (hhmm) {
        return Number(hhmm[1]) * 60 + Number(hhmm[2]);
    }
    return 8 * 60;
}

export function minutesToApiTime(totalMinutes: number): string {
    const clamped = Math.max(0, Math.min(23 * 60 + 45, Math.round(totalMinutes)));
    const h = Math.floor(clamped / 60);
    const m = clamped % 60;
    return `${String(h).padStart(2, "0")}:${String(m).padStart(2, "0")}`;
}

export function formatMinutes12h(totalMinutes: number): string {
    const h24 = Math.floor(totalMinutes / 60);
    const m = totalMinutes % 60;
    const meridiem = h24 >= 12 ? "PM" : "AM";
    const h12 = h24 % 12 === 0 ? 12 : h24 % 12;
    return `${h12}:${String(m).padStart(2, "0")} ${meridiem}`;
}

export function formatCompactRange(startMin: number, endMin: number): string {
    const fmt = (mins: number, showMeridiem: boolean) => {
        const h24 = Math.floor(mins / 60);
        const m = mins % 60;
        const h12 = h24 % 12 === 0 ? 12 : h24 % 12;
        const mer = h24 >= 12 ? "PM" : "AM";
        const minPart = m === 0 ? "" : `:${String(m).padStart(2, "0")}`;
        return `${h12}${minPart}${showMeridiem ? ` ${mer}` : ""}`;
    };
    const sameMer = Math.floor(startMin / 60) >= 12 === Math.floor(endMin / 60) >= 12;
    return `${fmt(startMin, !sameMer)}–${fmt(endMin, true)}`;
}

export function snapMinutes(minutes: number, step = 15): number {
    return Math.round(minutes / step) * step;
}

export function isOnlineMode(mode: string | null | undefined): boolean {
    if (!mode) return false;
    return mode === "online" || mode === "online_synchronous" || mode === "online_asynchronous" || mode === "hybrid_online";
}

// Calm, low-saturation architectural subject tones (6 distinct hues for Students & Sections tab)
export const SUBJECT_TONES = [
    {
        bg: "bg-sky-500/[0.09] dark:bg-sky-400/[0.14]",
        border: "border-sky-500/30 dark:border-sky-400/35",
        accent: "bg-sky-500",
        text: "text-sky-950 dark:text-sky-100",
        sub: "text-sky-800/80 dark:text-sky-200/75",
    },
    {
        bg: "bg-emerald-500/[0.09] dark:bg-emerald-400/[0.14]",
        border: "border-emerald-500/30 dark:border-emerald-400/35",
        accent: "bg-emerald-500",
        text: "text-emerald-950 dark:text-emerald-100",
        sub: "text-emerald-800/80 dark:text-emerald-200/75",
    },
    {
        bg: "bg-amber-500/[0.10] dark:bg-amber-400/[0.14]",
        border: "border-amber-500/35 dark:border-amber-400/35",
        accent: "bg-amber-500",
        text: "text-amber-950 dark:text-amber-100",
        sub: "text-amber-900/75 dark:text-amber-200/75",
    },
    {
        bg: "bg-violet-500/[0.09] dark:bg-violet-400/[0.14]",
        border: "border-violet-500/30 dark:border-violet-400/35",
        accent: "bg-violet-500",
        text: "text-violet-950 dark:text-violet-100",
        sub: "text-violet-900/75 dark:text-violet-200/75",
    },
    {
        bg: "bg-teal-500/[0.09] dark:bg-teal-400/[0.14]",
        border: "border-teal-500/30 dark:border-teal-400/35",
        accent: "bg-teal-500",
        text: "text-teal-950 dark:text-teal-100",
        sub: "text-teal-900/75 dark:text-teal-200/75",
    },
    {
        bg: "bg-indigo-500/[0.09] dark:bg-indigo-400/[0.14]",
        border: "border-indigo-500/30 dark:border-indigo-400/35",
        accent: "bg-indigo-500",
        text: "text-indigo-950 dark:text-indigo-100",
        sub: "text-indigo-900/75 dark:text-indigo-200/75",
    },
] as const;

export function getSubjectToneIndex(subjectCode: string): number {
    let hash = 0;
    for (let i = 0; i < subjectCode.length; i++) {
        hash = (hash * 31 + subjectCode.charCodeAt(i)) >>> 0;
    }
    return hash % SUBJECT_TONES.length;
}

export type CanvasBlock = {
    key: string;
    classItem: ClassScheduleData;
    session: ScheduleEntry;
    day: string;
    startMin: number;
    endMin: number;
    durationMin: number;
    laneIndex: number;
    laneCount: number;
    isOnline: boolean;
    hasConflict: boolean;
    conflictKinds: string[];
    overlapsInColumn: boolean;
    toneIndex: number;
    // Room-specific metrics
    seatCount: number;
    seatCapacity: number;
    seatPct: number;
    overcrowded: boolean;
};

export type OpenGap = {
    key: string;
    day: string;
    startMin: number;
    endMin: number;
    durationMin: number;
    kind: "open_room" | "long_student_gap";
};

export type TeacherHandoff = {
    key: string;
    day: string;
    atMin: number;
    fromRoom: string;
    toRoom: string;
    gapMinutes: number;
};

function indexConflictsBySchedule(conflicts: ScheduleConflict[]): Map<number, Set<string>> {
    const map = new Map<number, Set<string>>();
    for (const c of conflicts) {
        const kinds = c.conflict_types && c.conflict_types.length > 0 ? c.conflict_types : [c.conflict_type];
        for (const sid of [c.schedule_1_id, c.schedule_2_id]) {
            if (typeof sid !== "number") continue;
            const set = map.get(sid) ?? new Set<string>();
            for (const k of kinds) set.add(k);
            map.set(sid, set);
        }
    }
    return map;
}

/**
 * Pack overlapping time blocks into side-by-side lanes using connected overlap components.
 */
export function packColumnBlocks<T extends { startMin: number; endMin: number }>(
    items: T[],
): Array<T & { laneIndex: number; laneCount: number; overlapsInColumn: boolean }> {
    const sorted = [...items].sort((a, b) => a.startMin - b.startMin || b.endMin - a.endMin);
    const result: Array<T & { laneIndex: number; laneCount: number; overlapsInColumn: boolean }> = [];

    let group: T[] = [];
    let groupEnd = -1;

    const flushGroup = () => {
        if (group.length === 0) return;
        const laneEnds: number[] = [];
        const assigned: Array<{ item: T; lane: number }> = [];

        for (const item of group) {
            let lane = laneEnds.findIndex((end) => end <= item.startMin);
            if (lane === -1) {
                lane = laneEnds.length;
                laneEnds.push(item.endMin);
            } else {
                laneEnds[lane] = item.endMin;
            }
            assigned.push({ item, lane });
        }

        const totalLanes = Math.max(1, laneEnds.length);
        for (const { item, lane } of assigned) {
            result.push({
                ...item,
                laneIndex: lane,
                laneCount: totalLanes,
                overlapsInColumn: totalLanes > 1,
            });
        }
        group = [];
        groupEnd = -1;
    };

    for (const item of sorted) {
        if (group.length === 0 || item.startMin < groupEnd) {
            group.push(item);
            groupEnd = Math.max(groupEnd, item.endMin);
        } else {
            flushGroup();
            group.push(item);
            groupEnd = item.endMin;
        }
    }
    flushGroup();

    return result;
}

// ── TAB 1: ROOMS (Weekly Room Occupancy + Open-Gap Discovery + Multi-Room Day Compare) ──

export type RoomSummaryItem = {
    room: RoomOption;
    sessionCount: number;
    weeklyHours: number;
    conflictCount: number;
};

export function buildRoomDirectory(
    rooms: RoomOption[],
    classes: ClassScheduleData[],
    conflicts: ScheduleConflict[],
): RoomSummaryItem[] {
    const roomConflicts = new Map<number, number>();
    for (const c of conflicts) {
        const kinds = c.conflict_types ?? [c.conflict_type];
        if (kinds.includes("room") && c.room_id != null) {
            roomConflicts.set(Number(c.room_id), (roomConflicts.get(Number(c.room_id)) ?? 0) + 1);
        }
    }

    const roomSessions = new Map<number, { count: number; mins: number }>();
    for (const cls of classes) {
        for (const s of cls.schedules) {
            if (isOnlineMode(s.delivery_mode ?? cls.delivery_mode) || s.room_id == null) continue;
            const rid = Number(s.room_id);
            const cur = roomSessions.get(rid) ?? { count: 0, mins: 0 };
            const dur = Math.max(30, parseTimeToMinutes(s.end_time) - parseTimeToMinutes(s.start_time));
            roomSessions.set(rid, { count: cur.count + 1, mins: cur.mins + dur });
        }
    }

    return rooms
        .map((room) => {
            const stats = roomSessions.get(room.id) ?? { count: 0, mins: 0 };
            return {
                room,
                sessionCount: stats.count,
                weeklyHours: Math.round((stats.mins / 60) * 10) / 10,
                conflictCount: roomConflicts.get(room.id) ?? 0,
            };
        })
        .sort((a, b) => b.conflictCount - a.conflictCount || b.sessionCount - a.sessionCount || a.room.name.localeCompare(b.room.name));
}

export function buildRoomWeekModel(params: {
    room: RoomOption | null;
    days: string[];
    classes: ClassScheduleData[];
    conflicts: ScheduleConflict[];
}): {
    blocksByDay: Record<string, CanvasBlock[]>;
    openGapsByDay: Record<string, OpenGap[]>;
    weeklyHours: number;
    utilizationPct: number;
    conflictCount: number;
    sessionCount: number;
} {
    const { room, days, classes, conflicts } = params;
    const blocksByDay: Record<string, CanvasBlock[]> = {};
    const openGapsByDay: Record<string, OpenGap[]> = {};
    for (const d of days) {
        blocksByDay[d] = [];
        openGapsByDay[d] = [];
    }

    if (!room) {
        return { blocksByDay, openGapsByDay, weeklyHours: 0, utilizationPct: 0, conflictCount: 0, sessionCount: 0 };
    }

    const conflictMap = indexConflictsBySchedule(conflicts);
    const capacity = room.capacity ?? 40;
    let totalMins = 0;
    let sessionCount = 0;
    let conflictCount = 0;

    for (const cls of classes) {
        cls.schedules.forEach((session, idx) => {
            if (isOnlineMode(session.delivery_mode ?? cls.delivery_mode)) return;
            if (Number(session.room_id) !== room.id) return;
            if (!blocksByDay[session.day_of_week]) return;

            const startMin = parseTimeToMinutes(session.start_time);
            const endMin = Math.max(startMin + 30, parseTimeToMinutes(session.end_time));
            const durationMin = endMin - startMin;
            totalMins += durationMin;
            sessionCount++;

            const kinds = session.id !== undefined ? [...(conflictMap.get(session.id) ?? [])] : [];
            const seatCount = cls.student_count ?? 0;
            const seatPct = capacity > 0 ? Math.round((seatCount / capacity) * 100) : 0;

            blocksByDay[session.day_of_week].push({
                key: `${cls.id}:${session.id ?? idx}`,
                classItem: cls,
                session,
                day: session.day_of_week,
                startMin,
                endMin,
                durationMin,
                laneIndex: 0,
                laneCount: 1,
                isOnline: false,
                hasConflict: kinds.includes("room"),
                conflictKinds: kinds,
                overlapsInColumn: false,
                toneIndex: getSubjectToneIndex(cls.subject_code),
                seatCount,
                seatCapacity: capacity,
                seatPct,
                overcrowded: capacity > 0 && seatCount > capacity,
            });
        });
    }

    for (const d of days) {
        const packed = packColumnBlocks(blocksByDay[d]);
        blocksByDay[d] = packed.map((b) => ({
            ...b,
            hasConflict: b.hasConflict || b.overlapsInColumn,
        }));
        conflictCount += blocksByDay[d].filter((b) => b.hasConflict).length;

        // Compute quiet Open Room gaps (>= 90m between 08:00 and 19:00) for instant click-to-schedule
        const mergedBusy: Array<{ start: number; end: number }> = [];
        for (const b of packed) {
            const last = mergedBusy[mergedBusy.length - 1];
            if (!last || b.startMin > last.end) {
                mergedBusy.push({ start: b.startMin, end: b.endMin });
            } else {
                last.end = Math.max(last.end, b.endMin);
            }
        }

        let cursor = 8 * 60;
        const gapEndLimit = 19 * 60;
        for (const busy of mergedBusy) {
            if (busy.start - cursor >= 90) {
                openGapsByDay[d].push({
                    key: `gap:${d}:${cursor}`,
                    day: d,
                    startMin: cursor,
                    endMin: Math.min(busy.start, gapEndLimit),
                    durationMin: Math.min(busy.start, gapEndLimit) - cursor,
                    kind: "open_room",
                });
            }
            cursor = Math.max(cursor, busy.end);
        }
        if (gapEndLimit - cursor >= 120) {
            openGapsByDay[d].push({
                key: `gap:${d}:${cursor}`,
                day: d,
                startMin: cursor,
                endMin: gapEndLimit,
                durationMin: gapEndLimit - cursor,
                kind: "open_room",
            });
        }
    }

    const availableWeeklyMins = days.length * 10 * 60; // 10 prime teaching hours/day
    const weeklyHours = Math.round((totalMins / 60) * 10) / 10;
    const utilizationPct = Math.min(100, Math.round((totalMins / Math.max(1, availableWeeklyMins)) * 100));

    return { blocksByDay, openGapsByDay, weeklyHours, utilizationPct, conflictCount, sessionCount };
}

export function buildRoomCompareDayModel(params: {
    day: string;
    rooms: RoomOption[];
    classes: ClassScheduleData[];
    conflicts: ScheduleConflict[];
}): Array<{ room: RoomOption; blocks: CanvasBlock[]; conflictCount: number }> {
    const { day, rooms, classes, conflicts } = params;
    const conflictMap = indexConflictsBySchedule(conflicts);

    return rooms.map((room) => {
        const raw: CanvasBlock[] = [];
        const capacity = room.capacity ?? 40;

        for (const cls of classes) {
            cls.schedules.forEach((session, idx) => {
                if (session.day_of_week !== day) return;
                if (isOnlineMode(session.delivery_mode ?? cls.delivery_mode)) return;
                if (Number(session.room_id) !== room.id) return;

                const startMin = parseTimeToMinutes(session.start_time);
                const endMin = Math.max(startMin + 30, parseTimeToMinutes(session.end_time));
                const kinds = session.id !== undefined ? [...(conflictMap.get(session.id) ?? [])] : [];
                const seatCount = cls.student_count ?? 0;

                raw.push({
                    key: `${cls.id}:${session.id ?? idx}`,
                    classItem: cls,
                    session,
                    day,
                    startMin,
                    endMin,
                    durationMin: endMin - startMin,
                    laneIndex: 0,
                    laneCount: 1,
                    isOnline: false,
                    hasConflict: kinds.includes("room"),
                    conflictKinds: kinds,
                    overlapsInColumn: false,
                    toneIndex: getSubjectToneIndex(cls.subject_code),
                    seatCount,
                    seatCapacity: capacity,
                    seatPct: capacity > 0 ? Math.round((seatCount / capacity) * 100) : 0,
                    overcrowded: capacity > 0 && seatCount > capacity,
                });
            });
        }

        const packed = packColumnBlocks(raw).map((b) => ({
            ...b,
            hasConflict: b.hasConflict || b.overlapsInColumn,
        }));

        return {
            room,
            blocks: packed,
            conflictCount: packed.filter((b) => b.hasConflict).length,
        };
    });
}

// ── TAB 2: STUDENTS & SECTIONS (Cohort Curriculum Rhythm + Individual Student Timetable) ──

export type CohortOptions = {
    programs: string[];
    yearLevelsByProgram: Record<string, string[]>;
    sectionsByProgramYear: Record<string, string[]>;
};

export function splitClassPrograms(coursesText: string | null | undefined): string[] {
    if (!coursesText || coursesText.trim() === "" || coursesText.trim() === "General") {
        return ["General"];
    }
    return coursesText
        .split(/[,/|]+/)
        .map((part) => part.trim())
        .filter(Boolean);
}

export function extractCohortOptions(classes: ClassScheduleData[]): CohortOptions {
    const programSet = new Set<string>();
    const yearsMap = new Map<string, Set<string>>();
    const sectionsMap = new Map<string, Set<string>>();

    for (const cls of classes) {
        const programs = splitClassPrograms(cls.courses);
        const section = cls.section || "A";

        for (const program of programs) {
            programSet.add(program);
            const explicitYears =
                cls.course_year_levels?.[program] && cls.course_year_levels[program].length > 0
                    ? cls.course_year_levels[program]
                    : cls.year_levels && cls.year_levels.length > 0
                      ? cls.year_levels
                      : cls.grade_level
                        ? [cls.grade_level]
                        : ["1st Year"];

            const yrSet = yearsMap.get(program) ?? new Set<string>();
            for (const yr of explicitYears) {
                yrSet.add(yr);
                const key = `${program}::${yr}`;
                const secSet = sectionsMap.get(key) ?? new Set<string>();
                secSet.add(section);
                sectionsMap.set(key, secSet);
            }
            yearsMap.set(program, yrSet);
        }
    }

    const programs = [...programSet].sort((a, b) => a.localeCompare(b));
    const yearLevelsByProgram: Record<string, string[]> = {};
    for (const [prog, set] of yearsMap.entries()) {
        yearLevelsByProgram[prog] = [...set].sort((a, b) => a.localeCompare(b, undefined, { numeric: true }));
    }
    const sectionsByProgramYear: Record<string, string[]> = {};
    for (const [k, set] of sectionsMap.entries()) {
        sectionsByProgramYear[k] = [...set].sort((a, b) => a.localeCompare(b));
    }

    return { programs, yearLevelsByProgram, sectionsByProgramYear };
}

export function buildCohortWeekModel(params: {
    program: string;
    yearLevel: string;
    section: string;
    days: string[];
    classes: ClassScheduleData[];
    conflicts: ScheduleConflict[];
}): {
    blocksByDay: Record<string, CanvasBlock[]>;
    idleGapsByDay: Record<string, OpenGap[]>;
    hoursByDay: Record<string, number>;
    weeklyHours: number;
    subjectCount: number;
    onlineSessionCount: number;
    conflictCount: number;
} {
    const { program, yearLevel, section, days, classes, conflicts } = params;
    const matchingClasses = classes.filter((cls) => {
        if ((cls.section || "A") !== section) return false;
        const programs = splitClassPrograms(cls.courses);
        if (!programs.includes(program)) return false;
        const years =
            cls.course_year_levels?.[program] && cls.course_year_levels[program].length > 0
                ? cls.course_year_levels[program]
                : cls.year_levels && cls.year_levels.length > 0
                  ? cls.year_levels
                  : cls.grade_level
                    ? [cls.grade_level]
                    : ["1st Year"];
        return years.includes(yearLevel);
    });

    return buildStudentStyleWeekModel(matchingClasses, days, conflicts);
}

export function buildStudentStyleWeekModel(
    matchingClasses: ClassScheduleData[],
    days: string[],
    conflicts: ScheduleConflict[],
): {
    blocksByDay: Record<string, CanvasBlock[]>;
    idleGapsByDay: Record<string, OpenGap[]>;
    hoursByDay: Record<string, number>;
    weeklyHours: number;
    subjectCount: number;
    onlineSessionCount: number;
    conflictCount: number;
} {
    const blocksByDay: Record<string, CanvasBlock[]> = {};
    const idleGapsByDay: Record<string, OpenGap[]> = {};
    const hoursByDay: Record<string, number> = {};
    for (const d of days) {
        blocksByDay[d] = [];
        idleGapsByDay[d] = [];
        hoursByDay[d] = 0;
    }

    const conflictMap = indexConflictsBySchedule(conflicts);
    let totalMins = 0;
    let onlineSessionCount = 0;
    let conflictCount = 0;

    for (const cls of matchingClasses) {
        cls.schedules.forEach((session, idx) => {
            if (!blocksByDay[session.day_of_week]) return;
            const startMin = parseTimeToMinutes(session.start_time);
            const endMin = Math.max(startMin + 30, parseTimeToMinutes(session.end_time));
            const durationMin = endMin - startMin;
            totalMins += durationMin;

            const online = isOnlineMode(session.delivery_mode ?? cls.delivery_mode);
            if (online) onlineSessionCount++;

            const kinds = session.id !== undefined ? [...(conflictMap.get(session.id) ?? [])] : [];
            blocksByDay[session.day_of_week].push({
                key: `${cls.id}:${session.id ?? idx}`,
                classItem: cls,
                session,
                day: session.day_of_week,
                startMin,
                endMin,
                durationMin,
                laneIndex: 0,
                laneCount: 1,
                isOnline: online,
                hasConflict: kinds.includes("section"),
                conflictKinds: kinds,
                overlapsInColumn: false,
                toneIndex: getSubjectToneIndex(cls.subject_code),
                seatCount: cls.student_count ?? 0,
                seatCapacity: cls.maximum_slots ?? 40,
                seatPct: 0,
                overcrowded: false,
            });
        });
    }

    for (const d of days) {
        const packed = packColumnBlocks(blocksByDay[d]).map((b) => ({
            ...b,
            hasConflict: b.hasConflict || b.overlapsInColumn,
        }));
        blocksByDay[d] = packed;

        const dayMins = packed.reduce((sum, b) => sum + b.durationMin, 0);
        hoursByDay[d] = Math.round((dayMins / 60) * 10) / 10;
        conflictCount += packed.filter((b) => b.hasConflict).length;

        // Detect long student idle gaps (>= 2.5 hours between classes on the same day)
        const sorted = [...packed].sort((a, b) => a.startMin - b.startMin);
        let prevEnd = sorted[0]?.endMin ?? 0;
        for (let i = 1; i < sorted.length; i++) {
            const gap = sorted[i].startMin - prevEnd;
            if (gap >= 150) {
                idleGapsByDay[d].push({
                    key: `idle:${d}:${prevEnd}`,
                    day: d,
                    startMin: prevEnd,
                    endMin: sorted[i].startMin,
                    durationMin: gap,
                    kind: "long_student_gap",
                });
            }
            prevEnd = Math.max(prevEnd, sorted[i].endMin);
        }
    }

    return {
        blocksByDay,
        idleGapsByDay,
        hoursByDay,
        weeklyHours: Math.round((totalMins / 60) * 10) / 10,
        subjectCount: matchingClasses.length,
        onlineSessionCount,
        conflictCount,
    };
}

// ── TAB 3: TEACHERS (Faculty Workload, Burnout Detection & Back-to-Back Room Handoffs) ──

export type FacultySummaryItem = {
    faculty: FacultyOption;
    weeklyHours: number;
    classCount: number;
    conflictCount: number;
    loadStatus: "light" | "balanced" | "heavy" | "overloaded";
};

export function buildFacultyDirectory(
    facultyList: FacultyOption[],
    classes: ClassScheduleData[],
    conflicts: ScheduleConflict[],
): FacultySummaryItem[] {
    const clashCount = new Map<string, number>();
    for (const c of conflicts) {
        const kinds = c.conflict_types ?? [c.conflict_type];
        if (kinds.includes("faculty") && c.faculty_id) {
            const fid = String(c.faculty_id);
            clashCount.set(fid, (clashCount.get(fid) ?? 0) + 1);
        }
    }

    const loadMap = new Map<string, { mins: number; classes: number }>();
    for (const cls of classes) {
        if (!cls.faculty_id) continue;
        const fid = String(cls.faculty_id);
        const cur = loadMap.get(fid) ?? { mins: 0, classes: 0 };
        let addMins = 0;
        for (const s of cls.schedules) {
            addMins += Math.max(30, parseTimeToMinutes(s.end_time) - parseTimeToMinutes(s.start_time));
        }
        loadMap.set(fid, { mins: cur.mins + addMins, classes: cur.classes + 1 });
    }

    return facultyList
        .map((faculty) => {
            const fid = String(faculty.id);
            const stat = loadMap.get(fid) ?? { mins: 0, classes: 0 };
            const weeklyHours = Math.round((stat.mins / 60) * 10) / 10;
            const loadStatus: FacultySummaryItem["loadStatus"] =
                weeklyHours > 24 ? "overloaded" : weeklyHours > 18 ? "heavy" : weeklyHours >= 9 ? "balanced" : "light";
            return {
                faculty,
                weeklyHours,
                classCount: stat.classes,
                conflictCount: clashCount.get(fid) ?? 0,
                loadStatus,
            };
        })
        .sort((a, b) => b.conflictCount - a.conflictCount || b.weeklyHours - a.weeklyHours || a.faculty.name.localeCompare(b.faculty.name));
}

export function buildTeacherWeekModel(params: {
    faculty: FacultyOption | null;
    days: string[];
    classes: ClassScheduleData[];
    conflicts: ScheduleConflict[];
}): {
    blocksByDay: Record<string, CanvasBlock[]>;
    hoursByDay: Record<string, number>;
    handoffsByDay: Record<string, TeacherHandoff[]>;
    weeklyHours: number;
    loadStatus: FacultySummaryItem["loadStatus"];
    conflictCount: number;
    handoffCount: number;
    teachingDaysCount: number;
} {
    const { faculty, days, classes, conflicts } = params;
    const blocksByDay: Record<string, CanvasBlock[]> = {};
    const hoursByDay: Record<string, number> = {};
    const handoffsByDay: Record<string, TeacherHandoff[]> = {};
    for (const d of days) {
        blocksByDay[d] = [];
        hoursByDay[d] = 0;
        handoffsByDay[d] = [];
    }

    if (!faculty) {
        return {
            blocksByDay,
            hoursByDay,
            handoffsByDay,
            weeklyHours: 0,
            loadStatus: "light",
            conflictCount: 0,
            handoffCount: 0,
            teachingDaysCount: 0,
        };
    }

    const fid = String(faculty.id);
    const conflictMap = indexConflictsBySchedule(conflicts);
    let totalMins = 0;
    let conflictCount = 0;
    let handoffCount = 0;

    for (const cls of classes) {
        if (String(cls.faculty_id ?? "") !== fid) continue;
        cls.schedules.forEach((session, idx) => {
            if (!blocksByDay[session.day_of_week]) return;
            const startMin = parseTimeToMinutes(session.start_time);
            const endMin = Math.max(startMin + 30, parseTimeToMinutes(session.end_time));
            const durationMin = endMin - startMin;
            totalMins += durationMin;

            const online = isOnlineMode(session.delivery_mode ?? cls.delivery_mode);
            const kinds = session.id !== undefined ? [...(conflictMap.get(session.id) ?? [])] : [];

            blocksByDay[session.day_of_week].push({
                key: `${cls.id}:${session.id ?? idx}`,
                classItem: cls,
                session,
                day: session.day_of_week,
                startMin,
                endMin,
                durationMin,
                laneIndex: 0,
                laneCount: 1,
                isOnline: online,
                hasConflict: kinds.includes("faculty"),
                conflictKinds: kinds,
                overlapsInColumn: false,
                toneIndex: getSubjectToneIndex(cls.subject_code),
                seatCount: cls.student_count ?? 0,
                seatCapacity: cls.maximum_slots ?? 40,
                seatPct: 0,
                overcrowded: false,
            });
        });
    }

    let teachingDaysCount = 0;
    for (const d of days) {
        const packed = packColumnBlocks(blocksByDay[d]).map((b) => ({
            ...b,
            hasConflict: b.hasConflict || b.overlapsInColumn,
        }));
        blocksByDay[d] = packed;
        if (packed.length > 0) teachingDaysCount++;

        const dayMins = packed.reduce((sum, b) => sum + b.durationMin, 0);
        hoursByDay[d] = Math.round((dayMins / 60) * 10) / 10;
        conflictCount += packed.filter((b) => b.hasConflict).length;

        // Detect back-to-back zero-break room changes (<= 10m gap between two physical rooms that differ)
        const sorted = [...packed].sort((a, b) => a.startMin - b.startMin);
        for (let i = 0; i < sorted.length - 1; i++) {
            const current = sorted[i];
            const next = sorted[i + 1];
            const gap = next.startMin - current.endMin;
            if (
                gap >= 0 &&
                gap <= 10 &&
                !current.isOnline &&
                !next.isOnline &&
                current.session.room &&
                next.session.room &&
                current.session.room !== next.session.room
            ) {
                handoffsByDay[d].push({
                    key: `handoff:${d}:${current.endMin}:${i}`,
                    day: d,
                    atMin: current.endMin,
                    fromRoom: current.session.room,
                    toRoom: next.session.room,
                    gapMinutes: gap,
                });
                handoffCount++;
            }
        }
    }

    const weeklyHours = Math.round((totalMins / 60) * 10) / 10;
    const loadStatus: FacultySummaryItem["loadStatus"] =
        weeklyHours > 24 ? "overloaded" : weeklyHours > 18 ? "heavy" : weeklyHours >= 9 ? "balanced" : "light";

    return {
        blocksByDay,
        hoursByDay,
        handoffsByDay,
        weeklyHours,
        loadStatus,
        conflictCount,
        handoffCount,
        teachingDaysCount,
    };
}
