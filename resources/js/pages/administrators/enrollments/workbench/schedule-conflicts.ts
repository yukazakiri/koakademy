import {
    canonicalDeliveryMode,
    isAsynchronousOnlineMode,
    isOnlineDeliveryMode,
    isSynchronousOnlineMode,
    type DeliveryMode,
    type SchedulingPolicy,
} from "../../classes/lib/class-defaults";
import type {
    DraftSubjectEnrollment,
    ScheduleConflict,
    SectionOption,
    SectionSchedule,
} from "./types";

export const DEFAULT_ENROLLMENT_SCHEDULING_POLICY: SchedulingPolicy = {
    default_online_delivery_mode: "online",
    check_room_conflicts: true,
    check_faculty_conflicts: true,
    check_student_cohort_conflicts: true,
    check_synchronous_online_conflicts: true,
    check_asynchronous_online_conflicts: false,
    allow_sunday_scheduling: false,
};

export function resolveSchedulingPolicy(
    policy?: Partial<SchedulingPolicy> | null,
): SchedulingPolicy {
    return {
        ...DEFAULT_ENROLLMENT_SCHEDULING_POLICY,
        ...(policy ?? {}),
    };
}

export function effectiveScheduleDeliveryMode(
    schedule: Pick<SectionSchedule, "delivery_mode">,
    fallbackMode?: string | null,
): DeliveryMode {
    return canonicalDeliveryMode(schedule.delivery_mode ?? fallbackMode);
}

/**
 * Mirrors App\Support\ScheduleDelivery::checksStudentCohortConflicts().
 * Determines whether a single meeting block participates in student/enrollment
 * time-conflict checks under the active institution scheduling policy.
 */
export function checksStudentScheduleConflicts(
    mode?: string | null,
    policy?: Partial<SchedulingPolicy> | null,
): boolean {
    const resolved = resolveSchedulingPolicy(policy);

    if (!resolved.check_student_cohort_conflicts) {
        return false;
    }

    if (isSynchronousOnlineMode(mode)) {
        return Boolean(resolved.check_synchronous_online_conflicts);
    }

    if (isAsynchronousOnlineMode(mode)) {
        return Boolean(resolved.check_asynchronous_online_conflicts);
    }

    return true;
}

export function isScheduleExemptFromConflictCheck(
    schedule: Pick<SectionSchedule, "delivery_mode" | "checks_time_conflicts">,
    fallbackMode?: string | null,
    policy?: Partial<SchedulingPolicy> | null,
): boolean {
    if (policy) {
        const mode = effectiveScheduleDeliveryMode(schedule, fallbackMode);
        return !checksStudentScheduleConflicts(mode, policy);
    }

    if (typeof schedule.checks_time_conflicts === "boolean") {
        return !schedule.checks_time_conflicts;
    }

    const mode = effectiveScheduleDeliveryMode(schedule, fallbackMode);
    return !checksStudentScheduleConflicts(mode, DEFAULT_ENROLLMENT_SCHEDULING_POLICY);
}

export function parseTimeToMinutes(timeStr: string): number {
    if (!timeStr) return -1;
    const clean = timeStr.trim().toUpperCase();
    const match12 = clean.match(/^(\d{1,2}):(\d{2})\s*(AM|PM)$/);
    if (match12) {
        let h = parseInt(match12[1], 10);
        const m = parseInt(match12[2], 10);
        if (match12[3] === "PM" && h < 12) h += 12;
        if (match12[3] === "AM" && h === 12) h = 0;
        return h * 60 + m;
    }
    const match24 = clean.match(/^(\d{1,2}):(\d{2})/);
    if (match24) {
        return parseInt(match24[1], 10) * 60 + parseInt(match24[2], 10);
    }
    return -1;
}

export function normalizeDay(day: string): string {
    const key = day.trim().toLowerCase().slice(0, 3);
    const map: Record<string, string> = {
        mon: "Monday",
        tue: "Tuesday",
        wed: "Wednesday",
        thu: "Thursday",
        fri: "Friday",
        sat: "Saturday",
        sun: "Sunday",
    };
    return map[key] ?? day.trim();
}

export function formatMinutes(minutes: number): string {
    const h24 = Math.floor(minutes / 60);
    const m = minutes % 60;
    const period = h24 >= 12 ? "PM" : "AM";
    const h12 = h24 % 12 === 0 ? 12 : h24 % 12;
    return `${h12}:${String(m).padStart(2, "0")} ${period}`;
}

export function schedulesOverlap(
    a: SectionSchedule,
    b: SectionSchedule,
    policy?: Partial<SchedulingPolicy> | null,
    fallbackModeA?: string | null,
    fallbackModeB?: string | null,
): string | null {
    if (
        isScheduleExemptFromConflictCheck(a, fallbackModeA, policy) ||
        isScheduleExemptFromConflictCheck(b, fallbackModeB, policy)
    ) {
        return null;
    }

    if (normalizeDay(a.day) !== normalizeDay(b.day)) return null;
    const startA = parseTimeToMinutes(a.start_time);
    const endA = parseTimeToMinutes(a.end_time);
    const startB = parseTimeToMinutes(b.start_time);
    const endB = parseTimeToMinutes(b.end_time);
    if (startA < 0 || endA <= startA || startB < 0 || endB <= startB) return null;
    const overlapStart = Math.max(startA, startB);
    const overlapEnd = Math.min(endA, endB);
    if (overlapStart < overlapEnd) {
        return `${normalizeDay(a.day).slice(0, 3)} ${formatMinutes(overlapStart)}–${formatMinutes(overlapEnd)}`;
    }
    return null;
}

export function detectDraftScheduleConflicts(
    subjectsEnrolled: DraftSubjectEnrollment[],
    policy?: Partial<SchedulingPolicy> | null,
): ScheduleConflict[] {
    const resolvedPolicy = resolveSchedulingPolicy(policy);
    if (!resolvedPolicy.check_student_cohort_conflicts) {
        return [];
    }

    const assigned = subjectsEnrolled
        .map((sub) => {
            const sec = sub.sections?.find((s) => s.id === sub.class_id);
            return sec ? { sub, sec } : null;
        })
        .filter(Boolean) as { sub: DraftSubjectEnrollment; sec: SectionOption }[];

    const found: ScheduleConflict[] = [];
    for (let i = 0; i < assigned.length; i++) {
        for (let j = i + 1; j < assigned.length; j++) {
            const itemA = assigned[i];
            const itemB = assigned[j];
            const schedulesA = itemA.sec.schedules ?? [];
            const schedulesB = itemB.sec.schedules ?? [];

            for (let schedAIdx = 0; schedAIdx < schedulesA.length; schedAIdx++) {
                const schedA = schedulesA[schedAIdx];
                for (let schedBIdx = 0; schedBIdx < schedulesB.length; schedBIdx++) {
                    const schedB = schedulesB[schedBIdx];
                    const overlap = schedulesOverlap(
                        schedA,
                        schedB,
                        resolvedPolicy,
                        itemA.sec.delivery_mode,
                        itemB.sec.delivery_mode,
                    );
                    if (overlap) {
                        found.push({
                            subjectAId: itemA.sub.subject_id,
                            subjectACode: itemA.sub.subject_code,
                            subjectBId: itemB.sub.subject_id,
                            subjectBCode: itemB.sub.subject_code,
                            day: normalizeDay(schedA.day),
                            overlapWindow: overlap,
                            scheduleAIndex: schedAIdx,
                            scheduleBIndex: schedBIdx,
                        });
                    }
                }
            }
        }
    }

    return found;
}

export function findCandidateSectionConflicts(
    subjectId: number,
    candidateSection: SectionOption,
    subjectsEnrolled: DraftSubjectEnrollment[],
    policy?: Partial<SchedulingPolicy> | null,
): string[] {
    const resolvedPolicy = resolveSchedulingPolicy(policy);
    if (!resolvedPolicy.check_student_cohort_conflicts) {
        return [];
    }

    const warnings: string[] = [];
    for (const other of subjectsEnrolled) {
        if (other.subject_id === subjectId || !other.class_id) continue;
        const otherSec = other.sections?.find((s) => s.id === other.class_id);
        if (!otherSec) continue;

        for (const schedA of candidateSection.schedules ?? []) {
            for (const schedB of otherSec.schedules ?? []) {
                const overlap = schedulesOverlap(
                    schedA,
                    schedB,
                    resolvedPolicy,
                    candidateSection.delivery_mode,
                    otherSec.delivery_mode,
                );
                if (overlap) {
                    warnings.push(`${other.subject_code} (${overlap})`);
                }
            }
        }
    }

    return Array.from(new Set(warnings));
}

export {
    canonicalDeliveryMode,
    isAsynchronousOnlineMode,
    isOnlineDeliveryMode,
    isSynchronousOnlineMode,
};
