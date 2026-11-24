import { isOnlineDeliveryMode } from "../../classes/lib/class-defaults";
import { scheduledHours } from "../../components/scheduling-readiness";
import type {
    ClassScheduleData,
    FacultyOption,
    RoomOption,
    ScheduleConflict,
    ScheduleEntry,
} from "../../components/scheduling-types";
import { parseTimeToMinutes } from "../../scheduling/lib/time-geometry";

export type DirectoryCategory = "sections" | "rooms" | "teachers";

export type BoardSessionItem = {
    key: string;
    classItem: ClassScheduleData;
    session: ScheduleEntry;
    startMin: number;
    endMin: number;
    conflicts: ScheduleConflict[];
};

export type DirectoryEntity = {
    id: string;
    category: DirectoryCategory;
    title: string;
    subtitle: string;
    badge?: string;
    metaDetail: string;
    classes: ClassScheduleData[];
    sessions: BoardSessionItem[];
    weeklyHours: number;
    conflictCount: number;
    conflicts: ScheduleConflict[];
    needsTimesCount: number;
    roomId?: number | null;
    facultyId?: string | null;
};

function computeSessionHours(sessions: BoardSessionItem[]): number {
    let mins = 0;
    for (const item of sessions) {
        if (item.endMin > item.startMin) mins += item.endMin - item.startMin;
    }
    return Math.round((mins / 60) * 10) / 10;
}

function uniqueConflictsForSessions(
    sessions: BoardSessionItem[],
    allConflicts: ScheduleConflict[],
): ScheduleConflict[] {
    const ids = new Set(sessions.map((s) => s.session.id).filter((id): id is number => id !== undefined));
    if (ids.size === 0) return [];
    return allConflicts.filter(
        (c) =>
            (c.schedule_1_id !== undefined && ids.has(c.schedule_1_id)) ||
            (c.schedule_2_id !== undefined && ids.has(c.schedule_2_id)),
    );
}

export function buildBoardDirectories(
    classes: ClassScheduleData[],
    rooms: RoomOption[],
    faculty: FacultyOption[],
    allConflicts: ScheduleConflict[],
): Record<DirectoryCategory, DirectoryEntity[]> {
    const conflictsByScheduleId = new Map<number, ScheduleConflict[]>();
    for (const conflict of allConflicts) {
        for (const sid of [conflict.schedule_1_id, conflict.schedule_2_id]) {
            if (typeof sid === "number") {
                const list = conflictsByScheduleId.get(sid) ?? [];
                list.push(conflict);
                conflictsByScheduleId.set(sid, list);
            }
        }
    }

    const toBoardSession = (classItem: ClassScheduleData, session: ScheduleEntry, idx: number): BoardSessionItem => {
        const startMin = parseTimeToMinutes(session.start_time);
        const endMin = Math.max(startMin + 30, parseTimeToMinutes(session.end_time));
        return {
            key: `${classItem.id}:${session.id ?? idx}`,
            classItem,
            session,
            startMin,
            endMin,
            conflicts: session.id !== undefined ? (conflictsByScheduleId.get(session.id) ?? []) : [],
        };
    };

    // 1. SECTIONS directory (Course · Year Level · Section)
    // Split multi-program shared classes (e.g. "BSBA-FM, BSBA-HRM") into each individual program section
    // so every section shows its complete real weekly timetable (including shared GE/core classes).
    const sectionBuckets = new Map<
        string,
        { course: string; year: string; section: string; classes: ClassScheduleData[] }
    >();

    for (const cls of classes) {
        const rawCourses = (cls.courses || (cls.classification === "shs" ? "Senior High" : "General"))
            .split(",")
            .map((part) => part.trim())
            .filter(Boolean);
        const courseList = rawCourses.length > 0 ? rawCourses : ["General"];
        const year = (cls.grade_level || cls.year_levels?.[0] || "All Years").trim();
        const section = (cls.section || "A").trim();

        for (const course of courseList) {
            const key = `${course}||${year}||${section}`;
            const bucket = sectionBuckets.get(key);
            if (bucket) {
                if (!bucket.classes.some((existing) => existing.id === cls.id)) {
                    bucket.classes.push(cls);
                }
            } else {
                sectionBuckets.set(key, { course, year, section, classes: [cls] });
            }
        }
    }

    const sections: DirectoryEntity[] = [...sectionBuckets.entries()]
        .map(([key, bucket]) => {
            const sessions = bucket.classes.flatMap((cls) =>
                cls.schedules.map((s, idx) => toBoardSession(cls, s, idx)),
            );
            const entityConflicts = uniqueConflictsForSessions(sessions, allConflicts);
            const weeklyHours = computeSessionHours(sessions);
            const needsTimesCount = bucket.classes.filter(
                (c) => scheduledHours(c.schedules) < (c.required_hours ?? 3) || c.schedules.length === 0,
            ).length;

            return {
                id: `section:${key}`,
                category: "sections" as const,
                title: `${bucket.course} · ${bucket.year}`,
                subtitle: `${bucket.classes.length} ${bucket.classes.length === 1 ? "class" : "classes"} · ${weeklyHours}h/wk`,
                badge: `Sec ${bucket.section}`,
                metaDetail: `${bucket.course} · ${bucket.year} · Section ${bucket.section}`,
                classes: bucket.classes,
                sessions,
                weeklyHours,
                conflictCount: entityConflicts.length,
                conflicts: entityConflicts,
                needsTimesCount,
            };
        })
        .sort((a, b) => a.title.localeCompare(b.title) || (a.badge ?? "").localeCompare(b.badge ?? ""));

    // 2. ROOMS directory
    const roomEntities: DirectoryEntity[] = rooms.map((room) => {
        const matchingSessions: BoardSessionItem[] = [];
        const classSet = new Map<number, ClassScheduleData>();

        for (const cls of classes) {
            cls.schedules.forEach((s, idx) => {
                if (!isOnlineDeliveryMode(s.delivery_mode ?? cls.delivery_mode) && s.room_id === room.id) {
                    matchingSessions.push(toBoardSession(cls, s, idx));
                    classSet.set(cls.id, cls);
                }
            });
        }

        const entityConflicts = uniqueConflictsForSessions(matchingSessions, allConflicts);
        const weeklyHours = computeSessionHours(matchingSessions);
        const capLabel = room.capacity ? `Cap ${room.capacity}` : null;

        return {
            id: `room:${room.id}`,
            category: "rooms",
            title: room.name,
            subtitle: `${matchingSessions.length} ${matchingSessions.length === 1 ? "session" : "sessions"} · ${weeklyHours}h/wk`,
            badge: capLabel ?? undefined,
            metaDetail: [room.building, room.type, capLabel].filter(Boolean).join(" · ") || "Campus room",
            classes: [...classSet.values()],
            sessions: matchingSessions,
            weeklyHours,
            conflictCount: entityConflicts.length,
            conflicts: entityConflicts,
            needsTimesCount: 0,
            roomId: room.id,
        };
    });

    // Include Online virtual room bucket if online sessions exist
    const onlineSessions: BoardSessionItem[] = [];
    const onlineClasses = new Map<number, ClassScheduleData>();
    for (const cls of classes) {
        cls.schedules.forEach((s, idx) => {
            if (isOnlineDeliveryMode(s.delivery_mode ?? cls.delivery_mode)) {
                onlineSessions.push(toBoardSession(cls, s, idx));
                onlineClasses.set(cls.id, cls);
            }
        });
    }
    if (onlineSessions.length > 0) {
        const onlineConflicts = uniqueConflictsForSessions(onlineSessions, allConflicts);
        const weeklyHours = computeSessionHours(onlineSessions);
        roomEntities.push({
            id: "room:online",
            category: "rooms",
            title: "Online / Virtual",
            subtitle: `${onlineSessions.length} sessions · ${weeklyHours}h/wk`,
            badge: "Virtual",
            metaDetail: "No physical room reserved",
            classes: [...onlineClasses.values()],
            sessions: onlineSessions,
            weeklyHours,
            conflictCount: onlineConflicts.length,
            conflicts: onlineConflicts,
            needsTimesCount: 0,
            roomId: null,
        });
    }

    // 3. TEACHERS directory
    const teacherEntities: DirectoryEntity[] = faculty
        .map((f) => {
            const teacherClasses = classes.filter((c) => c.faculty_id === f.id);
            const sessions = teacherClasses.flatMap((cls) =>
                cls.schedules.map((s, idx) => toBoardSession(cls, s, idx)),
            );
            const entityConflicts = uniqueConflictsForSessions(sessions, allConflicts);
            const weeklyHours = computeSessionHours(sessions);
            const needsTimesCount = teacherClasses.filter(
                (c) => scheduledHours(c.schedules) < (c.required_hours ?? 3) || c.schedules.length === 0,
            ).length;

            return {
                id: `teacher:${f.id}`,
                category: "teachers" as const,
                title: f.name,
                subtitle: `${teacherClasses.length} ${teacherClasses.length === 1 ? "class" : "classes"} · ${weeklyHours}h/wk`,
                badge: f.department ? f.department.slice(0, 12) : undefined,
                metaDetail: f.department ?? "Faculty member",
                classes: teacherClasses,
                sessions,
                weeklyHours,
                conflictCount: entityConflicts.length,
                conflicts: entityConflicts,
                needsTimesCount,
                facultyId: f.id,
            };
        })
        .sort((a, b) => b.sessions.length - a.sessions.length || a.title.localeCompare(b.title));

    return {
        sections,
        rooms: roomEntities,
        teachers: teacherEntities,
    };
}
