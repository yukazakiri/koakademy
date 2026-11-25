import type { DeliveryMode, SchedulingPolicy } from "../../classes/lib/class-defaults";
import type { ClassScheduleData, ConflictKind, ScheduleEntry } from "../../components/scheduling-types";

function isOnlineDeliveryMode(mode?: string | null): boolean {
    return mode === "online" || mode === "online_asynchronous" || mode === "online_synchronous";
}

function isAsynchronousOnlineMode(mode?: string | null): boolean {
    return mode === "online" || mode === "online_asynchronous";
}

function isSynchronousOnlineMode(mode?: string | null): boolean {
    return mode === "online_synchronous";
}

function canonicalDeliveryMode(mode?: string | null): DeliveryMode {
    if (mode === "online_synchronous") return "online_synchronous";
    if (mode === "online" || mode === "online_asynchronous") return "online";
    return "face_to_face";
}

function parseTimeToMinutes(value: string | null | undefined): number {
    if (!value) return 8 * 60;
    const trimmed = value.trim();
    const ampmMatch = trimmed.match(/^(\d{1,2}):(\d{2})(?::\d{2})?\s*(AM|PM)$/i);
    if (ampmMatch) {
        let hours = Number(ampmMatch[1]);
        const minutes = Number(ampmMatch[2]);
        const meridiem = ampmMatch[3].toUpperCase();
        if (meridiem === "PM" && hours < 12) hours += 12;
        if (meridiem === "AM" && hours === 12) hours = 0;
        return hours * 60 + minutes;
    }
    const twentyFourMatch = trimmed.match(/^(\d{1,2}):(\d{2})(?::\d{2})?$/);
    if (twentyFourMatch) {
        return Number(twentyFourMatch[1]) * 60 + Number(twentyFourMatch[2]);
    }
    return 8 * 60;
}

export type CandidateScheduleBlock = {
    scheduleId?: number;
    classItem: ClassScheduleData;
    day_of_week: string;
    startMin: number;
    endMin: number;
    room_id: number | null;
    delivery_mode: DeliveryMode;
};

export type SimulatedConflictHit = {
    otherScheduleId?: number;
    otherClassId: number;
    otherSubjectCode: string;
    otherSection: string;
    otherRoom: string | null;
    otherFaculty: string | null;
    reasons: ConflictKind[];
    overlapMinutes: number;
};

export type SimulationResult = {
    hasConflict: boolean;
    hits: SimulatedConflictHit[];
    reasonSummary: string;
};

function checksTimeConflictsForMode(mode: DeliveryMode, policy: SchedulingPolicy): boolean {
    if (isAsynchronousOnlineMode(mode)) {
        return Boolean(policy.check_asynchronous_online_conflicts);
    }
    if (isSynchronousOnlineMode(mode)) {
        return Boolean(policy.check_synchronous_online_conflicts);
    }
    return true;
}

function checksRoomConflictsForMode(mode: DeliveryMode, policy: SchedulingPolicy): boolean {
    if (!policy.check_room_conflicts) return false;
    return !isOnlineDeliveryMode(mode);
}

function normalizeIdList(ids: number[] | null | undefined): number[] {
    if (!Array.isArray(ids)) return [];
    return ids.map((id) => Number(id)).filter((id) => Number.isFinite(id) && id > 0);
}

function shareCohort(a: ClassScheduleData, b: ClassScheduleData): boolean {
    if (!a.section || !b.section || a.section !== b.section) return false;

    const classA = a.classification ?? "college";
    const classB = b.classification ?? "college";
    if (classA !== classB) return false;

    if (classA === "shs") {
        if (!a.grade_level || !b.grade_level || a.grade_level !== b.grade_level) return false;
        if (a.shs_strand_id && b.shs_strand_id) {
            return a.shs_strand_id === b.shs_strand_id;
        }
        return true;
    }

    // College: check shared courses and shared year levels
    const yearsA = a.year_levels && a.year_levels.length > 0 ? a.year_levels : a.grade_level ? [a.grade_level] : [];
    const yearsB = b.year_levels && b.year_levels.length > 0 ? b.year_levels : b.grade_level ? [b.grade_level] : [];
    const sharedYear = yearsA.length > 0 && yearsB.length > 0 && yearsA.some((y) => yearsB.includes(y));
    if (!sharedYear) return false;

    const coursesA = normalizeIdList(a.course_ids);
    const coursesB = normalizeIdList(b.course_ids);
    if (coursesA.length > 0 && coursesB.length > 0) {
        return coursesA.some((c) => coursesB.includes(c));
    }

    if (a.courses && b.courses) {
        return a.courses === b.courses;
    }

    return true;
}

export function computePairConflictReasons(
    candidate: CandidateScheduleBlock,
    otherClass: ClassScheduleData,
    otherSchedule: ScheduleEntry,
    policy: SchedulingPolicy,
): { reasons: ConflictKind[]; overlapMinutes: number } {
    if (candidate.scheduleId !== undefined && otherSchedule.id !== undefined && candidate.scheduleId === otherSchedule.id) {
        return { reasons: [], overlapMinutes: 0 };
    }

    if (candidate.day_of_week !== otherSchedule.day_of_week) {
        return { reasons: [], overlapMinutes: 0 };
    }

    const otherStart = parseTimeToMinutes(otherSchedule.start_time);
    const otherEnd = parseTimeToMinutes(otherSchedule.end_time);

    if (candidate.startMin >= otherEnd || candidate.endMin <= otherStart) {
        return { reasons: [], overlapMinutes: 0 };
    }

    const overlapMinutes = Math.min(candidate.endMin, otherEnd) - Math.max(candidate.startMin, otherStart);
    if (overlapMinutes <= 0) {
        return { reasons: [], overlapMinutes: 0 };
    }

    const modeA = canonicalDeliveryMode(candidate.delivery_mode);
    const modeB = canonicalDeliveryMode(otherSchedule.delivery_mode ?? otherClass.delivery_mode);
    const reasons: ConflictKind[] = [];

    // 1. Room conflict (physical only)
    if (
        checksRoomConflictsForMode(modeA, policy) &&
        checksRoomConflictsForMode(modeB, policy) &&
        candidate.room_id != null &&
        otherSchedule.room_id != null &&
        Number(candidate.room_id) === Number(otherSchedule.room_id)
    ) {
        reasons.push("room");
    }

    // 2. Faculty & Cohort conflicts (policy-gated by delivery mode)
    if (checksTimeConflictsForMode(modeA, policy) && checksTimeConflictsForMode(modeB, policy)) {
        if (
            policy.check_faculty_conflicts &&
            candidate.classItem.faculty_id &&
            otherClass.faculty_id &&
            String(candidate.classItem.faculty_id) === String(otherClass.faculty_id)
        ) {
            reasons.push("faculty");
        }

        if (
            policy.check_student_cohort_conflicts &&
            candidate.classItem.id !== otherClass.id &&
            shareCohort(candidate.classItem, otherClass)
        ) {
            reasons.push("section");
        }
    }

    return { reasons, overlapMinutes };
}

/**
 * Pure client-side mirror of ScheduleConflictService for instant (<5ms)
 * pre-drop conflict simulation during pointer drag and edge resize.
 */
export function simulatePlacementConflicts(
    candidate: CandidateScheduleBlock,
    allClasses: ClassScheduleData[],
    policy: SchedulingPolicy,
): SimulationResult {
    const hits: SimulatedConflictHit[] = [];

    for (const otherClass of allClasses) {
        for (const otherSchedule of otherClass.schedules) {
            const { reasons, overlapMinutes } = computePairConflictReasons(candidate, otherClass, otherSchedule, policy);
            if (reasons.length > 0) {
                hits.push({
                    otherScheduleId: otherSchedule.id,
                    otherClassId: otherClass.id,
                    otherSubjectCode: otherClass.subject_code,
                    otherSection: otherClass.section,
                    otherRoom: otherSchedule.room ?? otherClass.room_name,
                    otherFaculty: otherClass.faculty_name,
                    reasons,
                    overlapMinutes,
                });
            }
        }
    }

    if (hits.length === 0) {
        return {
            hasConflict: false,
            hits: [],
            reasonSummary: "Conflict-free slot",
        };
    }

    const first = hits[0];
    const labelMap: Record<ConflictKind, string> = {
        room: "Room clash",
        faculty: "Instructor clash",
        section: "Cohort clash",
    };
    const primaryLabels = first.reasons.map((r) => labelMap[r]).join(" + ");
    const extra = hits.length > 1 ? ` (+${hits.length - 1} more)` : "";

    return {
        hasConflict: true,
        hits,
        reasonSummary: `${primaryLabels} with ${first.otherSubjectCode} · Sec ${first.otherSection}${extra}`,
    };
}

export function conflictKindLabel(kind: ConflictKind): string {
    switch (kind) {
        case "room":
            return "Room Double-Booked";
        case "faculty":
            return "Instructor Overlap";
        case "section":
            return "Cohort Clash";
    }
}

export function conflictKindShortLabel(kind: ConflictKind): string {
    switch (kind) {
        case "room":
            return "Room";
        case "faculty":
            return "Faculty";
        case "section":
            return "Cohort";
    }
}
