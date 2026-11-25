import type { DeliveryMode, SchedulingPolicy } from "../../classes/lib/class-defaults";
import type { ClassScheduleData, RoomOption, ScheduleEntry } from "../../components/scheduling-types";

export const XRAY_START_HOURS = [7, 8, 9, 10, 11, 12, 13, 14, 15, 16, 17, 18, 19] as const;

export type XRayRoomCandidate = {
    id: number;
    name: string;
    capacity: number;
    type: string;
    building: string | null;
    fitsCapacity: boolean;
    isPreferred: boolean;
    score: number;
};

export type XRayBlockerKind = "faculty" | "section" | "room";

export type XRayCell = {
    key: string;
    day: string;
    startHour: number;
    startMin: number;
    endMin: number;
    status: "current" | "open" | "blocked";
    existingSession?: ScheduleEntry;
    existingHasConflict?: boolean;
    isRelocatingThis?: boolean;
    openRooms: XRayRoomCandidate[];
    fittingRoomsCount: number;
    blockerKind?: XRayBlockerKind;
    shortReason?: string;
    detailReason?: string;
};

export type XRayGridResult = {
    cells: Map<string, XRayCell>;
    openSlotCount: number;
    blockedByFacultyCount: number;
    blockedBySectionCount: number;
    blockedByRoomCount: number;
};

function isOnlineMode(mode?: string | null): boolean {
    return mode === "online" || mode === "online_asynchronous" || mode === "online_synchronous";
}

function isAsyncOnline(mode?: string | null): boolean {
    return mode === "online" || mode === "online_asynchronous";
}

function isSyncOnline(mode?: string | null): boolean {
    return mode === "online_synchronous";
}

function canonicalMode(mode?: string | null): DeliveryMode {
    if (mode === "online_synchronous") return "online_synchronous";
    if (mode === "online" || mode === "online_asynchronous") return "online";
    return "face_to_face";
}

function checksTimeConflicts(mode: DeliveryMode, policy: SchedulingPolicy): boolean {
    if (isAsyncOnline(mode)) return Boolean(policy.check_asynchronous_online_conflicts);
    if (isSyncOnline(mode)) return Boolean(policy.check_synchronous_online_conflicts);
    return true;
}

function checksRoomConflicts(mode: DeliveryMode, policy: SchedulingPolicy): boolean {
    if (!policy.check_room_conflicts) return false;
    return !isOnlineMode(mode);
}

function parseMinutes(value: string | null | undefined): number {
    if (!value) return 8 * 60;
    const trimmed = value.trim();
    const ampm = trimmed.match(/^(\d{1,2}):(\d{2})(?::\d{2})?\s*(AM|PM)$/i);
    if (ampm) {
        let h = Number(ampm[1]);
        const m = Number(ampm[2]);
        const mer = ampm[3].toUpperCase();
        if (mer === "PM" && h < 12) h += 12;
        if (mer === "AM" && h === 12) h = 0;
        return h * 60 + m;
    }
    const tf = trimmed.match(/^(\d{1,2}):(\d{2})(?::\d{2})?$/);
    if (tf) return Number(tf[1]) * 60 + Number(tf[2]);
    return 8 * 60;
}

function firstNameOf(fullName: string | null | undefined): string {
    if (!fullName) return "Teacher";
    const parts = fullName.trim().split(/\s+/);
    return parts[0] || "Teacher";
}

function shareCohort(a: ClassScheduleData, b: ClassScheduleData): boolean {
    if (!a.section || !b.section || a.section !== b.section) return false;
    const classA = a.classification ?? "college";
    const classB = b.classification ?? "college";
    if (classA !== classB) return false;

    if (classA === "shs") {
        if (!a.grade_level || !b.grade_level || a.grade_level !== b.grade_level) return false;
        if (a.shs_strand_id && b.shs_strand_id) return a.shs_strand_id === b.shs_strand_id;
        return true;
    }

    const yearsA = a.year_levels && a.year_levels.length > 0 ? a.year_levels : a.grade_level ? [a.grade_level] : [];
    const yearsB = b.year_levels && b.year_levels.length > 0 ? b.year_levels : b.grade_level ? [b.grade_level] : [];
    if (!yearsA.length || !yearsB.length || !yearsA.some((y) => yearsB.includes(y))) return false;

    const coursesA = Array.isArray(a.course_ids) ? a.course_ids.map(Number).filter((n) => n > 0) : [];
    const coursesB = Array.isArray(b.course_ids) ? b.course_ids.map(Number).filter((n) => n > 0) : [];
    if (coursesA.length > 0 && coursesB.length > 0) {
        return coursesA.some((c) => coursesB.includes(c));
    }

    return Boolean(a.courses && b.courses && a.courses === b.courses);
}

type FlatBlock = {
    classItem: ClassScheduleData;
    session: ScheduleEntry;
    day: string;
    startMin: number;
    endMin: number;
    mode: DeliveryMode;
};

/**
 * Evaluates every (day, startHour) slot in the week for a specific target class
 * against all 3 live constraints (Instructor, Student Section, and Physical Rooms).
 */
export function evaluateXRayGrid(params: {
    targetClass: ClassScheduleData;
    allClasses: ClassScheduleData[];
    rooms: RoomOption[];
    policy: SchedulingPolicy;
    days: string[];
    startHours?: readonly number[];
    durationMinutes: number;
    deliveryMode: DeliveryMode;
    relocatingScheduleId?: number | null;
    conflictScheduleIds?: Set<number>;
}): XRayGridResult {
    const {
        targetClass,
        allClasses,
        rooms,
        policy,
        days,
        startHours = XRAY_START_HOURS,
        durationMinutes,
        deliveryMode,
        relocatingScheduleId = null,
        conflictScheduleIds = new Set<number>(),
    } = params;

    const candidateMode = canonicalMode(deliveryMode);
    const needRoom = !isOnlineMode(candidateMode);
    const enforceRoom = checksRoomConflicts(candidateMode, policy);
    const enforceTime = checksTimeConflicts(candidateMode, policy);
    const requiredCapacity = Math.max(targetClass.student_count ?? 0, targetClass.maximum_slots ?? 0);
    const preferLab = (targetClass.lab_hours ?? 0) > 0 && (targetClass.lecture_hours ?? 0) === 0;
    const teacherShort = firstNameOf(targetClass.faculty_name);

    // Pre-index all blocks by day once for sub-millisecond evaluation
    const blocksByDay = new Map<string, FlatBlock[]>();
    const ownBlocksByDay = new Map<string, FlatBlock[]>();
    for (const day of days) {
        blocksByDay.set(day, []);
        ownBlocksByDay.set(day, []);
    }

    for (const cls of allClasses) {
        for (const session of cls.schedules) {
            const dayList = blocksByDay.get(session.day_of_week);
            if (!dayList) continue;
            const startMin = parseMinutes(session.start_time);
            const endMin = Math.max(startMin + 30, parseMinutes(session.end_time));
            const block: FlatBlock = {
                classItem: cls,
                session,
                day: session.day_of_week,
                startMin,
                endMin,
                mode: canonicalMode(session.delivery_mode ?? cls.delivery_mode),
            };
            if (cls.id === targetClass.id) {
                ownBlocksByDay.get(session.day_of_week)?.push(block);
            }
            if (relocatingScheduleId !== null && session.id === relocatingScheduleId) {
                continue;
            }
            dayList.push(block);
        }
    }

    const cells = new Map<string, XRayCell>();
    let openSlotCount = 0;
    let blockedByFacultyCount = 0;
    let blockedBySectionCount = 0;
    let blockedByRoomCount = 0;

    for (const day of days) {
        const dayBlocks = blocksByDay.get(day) ?? [];
        const ownDayBlocks = ownBlocksByDay.get(day) ?? [];

        for (const startHour of startHours) {
            const startMin = startHour * 60;
            const endMin = startMin + durationMinutes;
            const key = `${day}:${startHour}`;

            // Check if this class already occupies this exact starting hour
            const ownMatch = ownDayBlocks.find((b) => b.startMin <= startMin && b.endMin > startMin);
            if (ownMatch) {
                const isRelocatingThis =
                    relocatingScheduleId !== null && ownMatch.session.id === relocatingScheduleId;
                cells.set(key, {
                    key,
                    day,
                    startHour,
                    startMin: ownMatch.startMin,
                    endMin: ownMatch.endMin,
                    status: "current",
                    existingSession: ownMatch.session,
                    existingHasConflict:
                        ownMatch.session.id !== undefined && conflictScheduleIds.has(ownMatch.session.id),
                    isRelocatingThis,
                    openRooms: [],
                    fittingRoomsCount: 0,
                });
                continue;
            }

            // Evaluate conflicts in [startMin, endMin)
            let facultyBlocker: FlatBlock | null = null;
            let sectionBlocker: FlatBlock | null = null;
            const busyRoomIds = new Set<number>();

            for (const other of dayBlocks) {
                if (startMin >= other.endMin || endMin <= other.startMin) continue;

                if (enforceRoom && checksRoomConflicts(other.mode, policy) && other.session.room_id != null) {
                    busyRoomIds.add(Number(other.session.room_id));
                }

                if (enforceTime && checksTimeConflicts(other.mode, policy)) {
                    if (
                        !facultyBlocker &&
                        policy.check_faculty_conflicts &&
                        targetClass.faculty_id &&
                        other.classItem.faculty_id &&
                        String(targetClass.faculty_id) === String(other.classItem.faculty_id)
                    ) {
                        facultyBlocker = other;
                    }

                    if (
                        !sectionBlocker &&
                        policy.check_student_cohort_conflicts &&
                        other.classItem.id !== targetClass.id &&
                        shareCohort(targetClass, other.classItem)
                    ) {
                        sectionBlocker = other;
                    }
                }
            }

            // Rank open physical rooms
            const openRooms: XRayRoomCandidate[] = needRoom
                ? rooms
                      .filter((r) => !enforceRoom || !busyRoomIds.has(Number(r.id)))
                      .map((r) => {
                          const capacity = r.capacity ?? 40;
                          const fitsCapacity = capacity >= requiredCapacity;
                          const isPreferred = targetClass.room_id != null && Number(r.id) === Number(targetClass.room_id);
                          const isLabRoom = (r.type ?? "").toLowerCase().includes("lab");
                          const typeMatch = preferLab ? isLabRoom : !isLabRoom;
                          const score =
                              (fitsCapacity ? 0 : 1000) +
                              (isPreferred ? -50 : 0) +
                              (typeMatch ? 0 : 120) +
                              Math.abs(capacity - requiredCapacity);
                          return {
                              id: r.id,
                              name: r.name,
                              capacity,
                              type: r.type ?? "lecture",
                              building: r.building ?? null,
                              fitsCapacity,
                              isPreferred,
                              score,
                          };
                      })
                      .sort((a, b) => a.score - b.score || a.name.localeCompare(b.name))
                : [];

            const fittingRoomsCount = openRooms.filter((r) => r.fitsCapacity).length;

            if (facultyBlocker) {
                blockedByFacultyCount++;
                cells.set(key, {
                    key,
                    day,
                    startHour,
                    startMin,
                    endMin,
                    status: "blocked",
                    openRooms,
                    fittingRoomsCount,
                    blockerKind: "faculty",
                    shortReason: `${teacherShort} busy`,
                    detailReason: `${targetClass.faculty_name ?? "Instructor"} teaches ${facultyBlocker.classItem.subject_code} (Sec ${facultyBlocker.classItem.section})`,
                });
            } else if (sectionBlocker) {
                blockedBySectionCount++;
                cells.set(key, {
                    key,
                    day,
                    startHour,
                    startMin,
                    endMin,
                    status: "blocked",
                    openRooms,
                    fittingRoomsCount,
                    blockerKind: "section",
                    shortReason: `Sec ${targetClass.section} in ${sectionBlocker.classItem.subject_code}`,
                    detailReason: `Section ${targetClass.section} already has ${sectionBlocker.classItem.subject_code} (${sectionBlocker.session.time_range})`,
                });
            } else if (needRoom && rooms.length > 0 && openRooms.length === 0) {
                blockedByRoomCount++;
                cells.set(key, {
                    key,
                    day,
                    startHour,
                    startMin,
                    endMin,
                    status: "blocked",
                    openRooms: [],
                    fittingRoomsCount: 0,
                    blockerKind: "room",
                    shortReason: "No open rooms",
                    detailReason: "Every active campus room is booked during this window",
                });
            } else {
                openSlotCount++;
                cells.set(key, {
                    key,
                    day,
                    startHour,
                    startMin,
                    endMin,
                    status: "open",
                    openRooms,
                    fittingRoomsCount,
                });
            }
        }
    }

    return {
        cells,
        openSlotCount,
        blockedByFacultyCount,
        blockedBySectionCount,
        blockedByRoomCount,
    };
}

/**
 * Checks whether a recurring multi-day pattern (e.g. MWF or TTh) at the given startHour
 * is completely open across all days in the pattern, and returns rooms free on ALL days.
 */
export function evaluatePatternFit(
    grid: XRayGridResult,
    patternDays: string[],
    startHour: number,
    isOnline: boolean,
): { allOpen: boolean; commonRooms: XRayRoomCandidate[] } {
    const cells = patternDays.map((day) => grid.cells.get(`${day}:${startHour}`));
    if (cells.some((cell) => !cell || cell.status !== "open")) {
        return { allOpen: false, commonRooms: [] };
    }
    if (isOnline) {
        return { allOpen: true, commonRooms: [] };
    }

    const firstRooms = cells[0]!.openRooms;
    const commonRooms = firstRooms.filter((room) =>
        cells.every((cell) => cell!.openRooms.some((candidate) => candidate.id === room.id)),
    );

    return {
        allOpen: commonRooms.length > 0,
        commonRooms,
    };
}
